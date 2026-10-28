<?php
/**
 * Database Migration Manager.
 *
 * @package FraudTenant\Database
 */

namespace FraudTenant\Database;

use FraudTenant\Utils\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MigrationManager {

	/**
	 * Run database migrations.
	 *
	 * @param bool $force Force migration regardless of stored version.
	 * @return bool True on success.
	 */
	public static function migrate( bool $force = false ): bool {
		$installed_version = get_option( Constants::DB_VERSION_OPTION, '0.0.0' );

		if ( ! $force && version_compare( $installed_version, Constants::DB_VERSION, '>=' ) ) {
			return true;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$statements = Schema::get_schema_statements();
		foreach ( $statements as $sql ) {
			dbDelta( $sql );
		}

		// Seed initial default membership plans if empty
		self::seed_default_plans();

		update_option( Constants::DB_VERSION_OPTION, Constants::DB_VERSION );

		return true;
	}

	/**
	 * Seed initial default membership tiers if none exist.
	 * Idempotent: checks for existing plans by plan_code before inserting.
	 */
	protected static function seed_default_plans(): void {
		global $wpdb;

		$table = $wpdb->prefix . Constants::TABLE_MEMBERSHIP_PLANS;

		// Check if table exists yet
		$check = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $check !== $table ) {
			return;
		}

		$existing = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		if ( (int) $existing > 0 ) {
			return;
		}

		$now   = current_time( 'mysql' );
		$plans = array(
			array(
				'plan_code'        => 'free',
				'name'             => 'Free Plan',
				'description'      => 'Essential features for property owners with neutral search status.',
				'billing_interval' => 'free',
				'price_amount'     => 0.00,
				'currency'         => 'USD',
				'features_json'    => wp_json_encode(
					array(
						'tenant_search_basic'   => true,
						'full_reports'          => false,
						'incident_reporting'    => false,
						'unlimited_properties'  => false,
					)
				),
				'is_active'        => 1,
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array(
				'plan_code'        => 'standard_monthly',
				'name'             => 'Standard Monthly',
				'description'      => 'Full screening reports and verified incident reporting for active landlords.',
				'billing_interval' => 'monthly',
				'price_amount'     => 29.00,
				'currency'         => 'USD',
				'features_json'    => wp_json_encode(
					array(
						'tenant_search_basic'   => true,
						'full_reports'          => true,
						'incident_reporting'    => true,
						'unlimited_properties'  => true,
					)
				),
				'is_active'        => 1,
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array(
				'plan_code'        => 'standard_yearly',
				'name'             => 'Standard Annual',
				'description'      => 'Full screening reports and verified incident reporting with annual discount.',
				'billing_interval' => 'yearly',
				'price_amount'     => 290.00,
				'currency'         => 'USD',
				'features_json'    => wp_json_encode(
					array(
						'tenant_search_basic'   => true,
						'full_reports'          => true,
						'incident_reporting'    => true,
						'unlimited_properties'  => true,
					)
				),
				'is_active'        => 1,
				'created_at'       => $now,
				'updated_at'       => $now,
			),
		);

		foreach ( $plans as $plan ) {
			$wpdb->insert( $table, $plan );
		}
	}
}

