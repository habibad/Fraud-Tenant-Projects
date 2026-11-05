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

		// Reconcile incident reports schema for unlinked identity semantics (NULL)
		self::reconcile_incident_schema();

		// Reconcile report reviews schema for decision audit metadata
		self::reconcile_report_reviews_schema();

		// Reconcile membership plans schema and quota baseline
		self::reconcile_membership_plans();

		// Reconcile subscriptions table schema (composite index)
		self::reconcile_subscriptions_schema();

		// Reconcile payment events table schema (webhook columns)
		self::reconcile_payment_events_schema();

		// Reconcile dual-mode Stripe schema (test/live products, prices, and event isolation)
		self::reconcile_dual_stripe_schema();

		// 1.8.0: tenant subject identity on incidents + identity-matched screening (additive)
		self::reconcile_identity_screening_schema();

		update_option( Constants::DB_VERSION_OPTION, Constants::DB_VERSION );

		// Ensure roles and capabilities are synchronized with current schema version
		\FraudTenant\Auth\RoleManager::register_roles();

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
						'max_active_properties' => 1,
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

	/**
	 * Reconcile incident reports schema for unlinked tenant identity semantics.
	 * Ensures tenant_identity_id is nullable (DEFAULT NULL) and converts legacy 0s to NULL.
	 */
	public static function reconcile_incident_schema(): void {
		global $wpdb;

		$table = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;
		$check = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $check !== $table ) {
			return;
		}

		$col = $wpdb->get_row( "SHOW COLUMNS FROM {$table} LIKE 'tenant_identity_id'" );
		if ( $col && 'NO' === $col->Null ) {
			$wpdb->query( "ALTER TABLE {$table} MODIFY tenant_identity_id bigint(20) unsigned DEFAULT NULL" );
		}

		$wpdb->query( "UPDATE {$table} SET tenant_identity_id = NULL WHERE tenant_identity_id = 0" );
	}

	/**
	 * Reconcile report reviews schema for Phase 8.2 administrative review history.
	 * Ensures previous_status, new_status, and reason_code columns exist non-destructively.
	 */
	public static function reconcile_report_reviews_schema(): void {
		global $wpdb;

		$table = $wpdb->prefix . Constants::TABLE_REPORT_REVIEWS;
		$check = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $check !== $table ) {
			return;
		}

		$col_prev = $wpdb->get_row( "SHOW COLUMNS FROM {$table} LIKE 'previous_status'" );
		if ( ! $col_prev ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN previous_status varchar(30) DEFAULT NULL AFTER decision" );
		}

		$col_new = $wpdb->get_row( "SHOW COLUMNS FROM {$table} LIKE 'new_status'" );
		if ( ! $col_new ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN new_status varchar(30) DEFAULT NULL AFTER previous_status" );
		}

		$col_reason = $wpdb->get_row( "SHOW COLUMNS FROM {$table} LIKE 'reason_code'" );
		if ( ! $col_reason ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN reason_code varchar(50) DEFAULT NULL AFTER new_status" );
		}
	}

	/**
	 * Reconcile membership plans schema and baseline feature configuration.
	 * Ensures the Free plan features_json explicitly contains max_active_properties: 1.
	 */
	public static function reconcile_membership_plans(): void {
		global $wpdb;

		$table = $wpdb->prefix . Constants::TABLE_MEMBERSHIP_PLANS;
		$check = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $check !== $table ) {
			return;
		}

		$free_plan = $wpdb->get_row( "SELECT id, features_json FROM {$table} WHERE plan_code = 'free' LIMIT 1" );
		if ( $free_plan ) {
			$features = ! empty( $free_plan->features_json ) ? json_decode( $free_plan->features_json, true ) : array();
			if ( ! is_array( $features ) ) {
				$features = array();
			}
			if ( ! isset( $features['max_active_properties'] ) ) {
				$features['max_active_properties'] = 1;
				$wpdb->update(
					$table,
					array(
						'features_json' => wp_json_encode( $features ),
						'updated_at'    => current_time( 'mysql' ),
					),
					array( 'id' => $free_plan->id ),
					array( '%s', '%s' ),
					array( '%d' )
				);
			}
		}
	}

	/**
	 * Reconcile subscriptions table schema.
	 * Ensures composite index (user_id, status) exists for efficient active subscription lookups.
	 */
	public static function reconcile_subscriptions_schema(): void {
		global $wpdb;

		$table = $wpdb->prefix . Constants::TABLE_SUBSCRIPTIONS;
		$check = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $check !== $table ) {
			return;
		}

		$indexes = $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'user_id_status'" );
		if ( empty( $indexes ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD KEY user_id_status (user_id, status)" );
		}
	}

	/**
	 * Reconcile payment events table schema for robust webhook processing.
	 */
	public static function reconcile_payment_events_schema(): void {
		global $wpdb;

		$table = $wpdb->prefix . Constants::TABLE_PAYMENT_EVENTS;
		$check = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $check !== $table ) {
			return;
		}

		$cols = $wpdb->get_col( "DESC {$table}" );
		if ( ! in_array( 'attempt_count', $cols, true ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN attempt_count int(11) unsigned NOT NULL DEFAULT 0 AFTER status" );
		}
		if ( ! in_array( 'received_at', $cols, true ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN received_at datetime DEFAULT NULL AFTER error_message" );
		}
		if ( ! in_array( 'locked_at', $cols, true ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN locked_at datetime DEFAULT NULL AFTER received_at" );
		}
	}

	/**
	 * Reconcile dual-mode Stripe schema for independent Test/Live environments.
	 *
	 * - Ensures membership plans support separate Test and Live Product/Price IDs.
	 * - Populates test IDs from existing legacy Stripe columns.
	 * - Ensures subscriptions and payment events have livemode flags for total isolation.
	 */
	public static function reconcile_dual_stripe_schema(): void {
		global $wpdb;

		// 1. Membership plans: separate Test and Live IDs
		$plans_table = $wpdb->prefix . Constants::TABLE_MEMBERSHIP_PLANS;
		$check_plans = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $plans_table ) );
		if ( $check_plans === $plans_table ) {
			$plan_cols = $wpdb->get_col( "DESC {$plans_table}" );

			if ( ! in_array( 'stripe_test_product_id', $plan_cols, true ) ) {
				$wpdb->query( "ALTER TABLE {$plans_table} ADD COLUMN stripe_test_product_id varchar(100) DEFAULT NULL AFTER stripe_product_id" );
			}
			if ( ! in_array( 'stripe_test_price_id', $plan_cols, true ) ) {
				$wpdb->query( "ALTER TABLE {$plans_table} ADD COLUMN stripe_test_price_id varchar(100) DEFAULT NULL AFTER stripe_test_product_id" );
			}
			if ( ! in_array( 'stripe_live_product_id', $plan_cols, true ) ) {
				$wpdb->query( "ALTER TABLE {$plans_table} ADD COLUMN stripe_live_product_id varchar(100) DEFAULT NULL AFTER stripe_test_price_id" );
			}
			if ( ! in_array( 'stripe_live_price_id', $plan_cols, true ) ) {
				$wpdb->query( "ALTER TABLE {$plans_table} ADD COLUMN stripe_live_price_id varchar(100) DEFAULT NULL AFTER stripe_live_product_id" );
			}

			// Backfill stripe_test_* from legacy columns if empty
			$wpdb->query(
				"UPDATE {$plans_table}
				 SET stripe_test_product_id = stripe_product_id
				 WHERE (stripe_test_product_id IS NULL OR stripe_test_product_id = '')
				   AND stripe_product_id IS NOT NULL AND stripe_product_id != ''"
			);
			$wpdb->query(
				"UPDATE {$plans_table}
				 SET stripe_test_price_id = stripe_price_id
				 WHERE (stripe_test_price_id IS NULL OR stripe_test_price_id = '')
				   AND stripe_price_id IS NOT NULL AND stripe_price_id != ''"
			);
		}

		// 2. Subscriptions: livemode isolation
		$sub_table = $wpdb->prefix . Constants::TABLE_SUBSCRIPTIONS;
		$check_sub = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $sub_table ) );
		if ( $check_sub === $sub_table ) {
			$sub_cols = $wpdb->get_col( "DESC {$sub_table}" );
			if ( ! in_array( 'livemode', $sub_cols, true ) ) {
				$wpdb->query( "ALTER TABLE {$sub_table} ADD COLUMN livemode tinyint(1) NOT NULL DEFAULT 0 AFTER stripe_subscription_id" );
			}
			$sub_indexes = $wpdb->get_results( "SHOW INDEX FROM {$sub_table} WHERE Key_name = 'livemode'" );
			if ( empty( $sub_indexes ) ) {
				$wpdb->query( "ALTER TABLE {$sub_table} ADD KEY livemode (livemode)" );
			}
		}

		// 3. Payment Events: livemode isolation
		$event_table = $wpdb->prefix . Constants::TABLE_PAYMENT_EVENTS;
		$check_event = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $event_table ) );
		if ( $check_event === $event_table ) {
			$event_cols = $wpdb->get_col( "DESC {$event_table}" );
			if ( ! in_array( 'livemode', $event_cols, true ) ) {
				$wpdb->query( "ALTER TABLE {$event_table} ADD COLUMN livemode tinyint(1) NOT NULL DEFAULT 0 AFTER event_type" );
			}
			$event_indexes = $wpdb->get_results( "SHOW INDEX FROM {$event_table} WHERE Key_name = 'livemode'" );
			if ( empty( $event_indexes ) ) {
				$wpdb->query( "ALTER TABLE {$event_table} ADD KEY livemode (livemode)" );
			}
		}
	}

	/**
	 * Schema 1.8.0 (additive, idempotent): incident subject identity (HMAC token, last four,
	 * encrypted name), admin-controlled screening disclosure, and applicant match columns.
	 */
	public static function reconcile_identity_screening_schema(): void {
		global $wpdb;
		$inc = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;
		$scr = $wpdb->prefix . Constants::TABLE_SCREENING_REQUESTS;
		$cols = array(
			$inc => array(
				'subject_token_hash'     => 'varchar(64) DEFAULT NULL',
				'subject_ssn_last4'      => 'varchar(4) DEFAULT NULL',
				'subject_name_enc'       => 'text DEFAULT NULL',
				'screening_disclosure'   => 'tinyint(1) NOT NULL DEFAULT 0',
				'screening_disclosed_by' => 'bigint(20) unsigned DEFAULT NULL',
				'screening_disclosed_at' => 'datetime DEFAULT NULL',
			),
			$scr => array(
				'applicant_token_hash' => 'varchar(64) DEFAULT NULL',
				'applicant_name_enc'   => 'text DEFAULT NULL',
				'matched_report_ids'   => 'text DEFAULT NULL',
			),
		);
		foreach ( $cols as $table => $defs ) {
			foreach ( $defs as $col => $def ) {
				if ( ! $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", $col ) ) ) {
					$wpdb->query( "ALTER TABLE {$table} ADD COLUMN {$col} {$def}" );
				}
			}
		}
		$idx = array(
			$inc => array( 'subject_token_hash', 'screening_disclosure' ),
			$scr => array( 'applicant_token_hash' ),
		);
		foreach ( $idx as $table => $keys ) {
			foreach ( $keys as $k ) {
				if ( ! $wpdb->get_row( $wpdb->prepare( "SHOW INDEX FROM {$table} WHERE Key_name = %s", $k ) ) ) {
					$wpdb->query( "ALTER TABLE {$table} ADD KEY {$k} ({$k})" );
				}
			}
		}
	}
}

