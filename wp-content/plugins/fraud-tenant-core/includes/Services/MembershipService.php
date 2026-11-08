<?php
/**
 * Membership and Entitlement Service.
 *
 * Single authoritative source for membership plan resolution, feature
 * entitlements, subscription state, and property quota evaluation.
 *
 * @package FraudTenant\Services
 */

namespace FraudTenant\Services;

use FraudTenant\Auth\AuthService;
use FraudTenant\Auth\Capabilities;
use FraudTenant\Database\MigrationManager;
use FraudTenant\Utils\Constants;
use FraudTenant\Utils\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MembershipService {

	/**
	 * Default feature matrix by plan code.
	 *
	 * @param string $plan_code Plan code.
	 * @return array<string, mixed>
	 */
	public static function get_default_features( string $plan_code ): array {
		if ( 'standard_monthly' === $plan_code || 'standard_yearly' === $plan_code ) {
			return array(
				'tenant_search_basic'   => true,
				'full_reports'          => true,
				'incident_reporting'    => true,
				'unlimited_properties'  => true,
			);
		}

		// Default Free features
		return array(
			'tenant_search_basic'   => true,
			'full_reports'          => false,
			'incident_reporting'    => false,
			'unlimited_properties'  => false,
			'max_active_properties' => 1,
		);
	}

	/**
	 * Retrieve a membership plan by its plan code.
	 *
	 * @param string $plan_code Plan code (e.g. 'free', 'standard_monthly', 'standard_yearly').
	 * @return object|null Plan row or null if not found.
	 */
	public static function get_plan_by_code( string $plan_code ): ?object {
		global $wpdb;

		$table = $wpdb->prefix . Constants::TABLE_MEMBERSHIP_PLANS;
		$plan  = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE plan_code = %s LIMIT 1",
				sanitize_key( $plan_code )
			)
		);

		return $plan ? (object) $plan : null;
	}

	/**
	 * Retrieve a membership plan by its database ID.
	 *
	 * @param int $plan_id Plan database ID.
	 * @return object|null Plan row or null if not found.
	 */
	public static function get_plan_by_id( int $plan_id ): ?object {
		global $wpdb;

		if ( $plan_id <= 0 ) {
			return null;
		}

		$table = $wpdb->prefix . Constants::TABLE_MEMBERSHIP_PLANS;
		$plan  = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
				$plan_id
			)
		);

		return $plan ? (object) $plan : null;
	}

	/**
	 * Retrieve the Free membership plan with guaranteed fallback.
	 *
	 * @return object
	 */
	public static function get_free_plan(): object {
		$plan = self::get_plan_by_code( 'free' );
		if ( $plan ) {
			return $plan;
		}

		// Fallback safe object if table not yet seeded
		return (object) array(
			'id'                => 1,
			'plan_code'         => 'free',
			'name'              => 'Free Plan',
			'description'       => 'Essential features for property owners with neutral search status.',
			'billing_interval'  => 'free',
			'price_amount'      => 0.00,
			'currency'          => 'USD',
			'stripe_price_id'   => null,
			'stripe_product_id' => null,
			'features_json'     => wp_json_encode( self::get_default_features( 'free' ) ),
			'is_active'         => 1,
			'created_at'        => current_time( 'mysql' ),
			'updated_at'        => current_time( 'mysql' ),
		);
	}

	/**
	 * Retrieve all membership plans.
	 *
	 * @param bool $active_only Whether to return only active plans.
	 * @return array<object> List of plan objects.
	 */
	public static function get_plans( bool $active_only = false ): array {
		global $wpdb;

		$table = $wpdb->prefix . Constants::TABLE_MEMBERSHIP_PLANS;
		$where = $active_only ? 'WHERE is_active = 1' : '';

		$plans = $wpdb->get_results( "SELECT * FROM {$table} {$where} ORDER BY id ASC" );
		return $plans ? (array) $plans : array();
	}

	/**
	 * Resolve a paid plan from a mode-specific Stripe Price ID.
	 *
	 * Live prices only match live columns; test prices only match test (or legacy) columns.
	 *
	 * @param string $price_id Stripe Price ID.
	 * @param string $mode     'test' or 'live'.
	 * @return object|null
	 */
	public static function get_plan_by_price_id( string $price_id, string $mode ): ?object {
		global $wpdb;
		if ( '' === $price_id || ! preg_match( '/^price_[a-zA-Z0-9_]+$/', $price_id ) ) {
			return null;
		}
		$table = $wpdb->prefix . Constants::TABLE_MEMBERSHIP_PLANS;
		if ( Constants::STRIPE_MODE_LIVE === $mode ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE stripe_live_price_id = %s AND plan_code <> 'free' LIMIT 1", $price_id ) );
		} else {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE ( stripe_test_price_id = %s OR ( ( stripe_test_price_id IS NULL OR stripe_test_price_id = '' ) AND stripe_price_id = %s ) ) AND plan_code <> 'free' LIMIT 1", $price_id, $price_id ) );
		}
		return $row ?: null;
	}

	/**
	 * Subscription history for an owner (all modes; newest first). Billing records are never deleted.
	 *
	 * @param int $user_id User ID.
	 * @param int $limit   Max rows.
	 * @return array<object>
	 */
	public static function get_subscription_history( int $user_id, int $limit = 20 ): array {
		global $wpdb;
		$sub_table  = $wpdb->prefix . Constants::TABLE_SUBSCRIPTIONS;
		$plan_table = $wpdb->prefix . Constants::TABLE_MEMBERSHIP_PLANS;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.id, s.status, s.livemode, s.current_period_start, s.current_period_end, s.cancel_at_period_end, s.created_at, s.updated_at, p.name AS plan_name, p.plan_code
				 FROM {$sub_table} s LEFT JOIN {$plan_table} p ON p.id = s.plan_id
				 WHERE s.user_id = %d ORDER BY s.id DESC LIMIT %d",
				$user_id,
				max( 1, min( 100, $limit ) )
			)
		) ?: array();
	}

	/**
	 * Invoice/payment history for an owner, derived from verified webhook events (minimized summaries).
	 *
	 * @param int $user_id User ID.
	 * @param int $limit   Max rows.
	 * @return array<array<string,mixed>>
	 */
	public static function get_billing_history( int $user_id, int $limit = 25 ): array {
		global $wpdb;
		$table = $wpdb->prefix . Constants::TABLE_PAYMENT_EVENTS;
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT event_type, livemode, payload_json, received_at FROM {$table}
				 WHERE user_id = %d AND status = 'processed' AND event_type IN ('invoice.paid','invoice.payment_failed')
				 ORDER BY id DESC LIMIT %d",
				$user_id,
				max( 1, min( 100, $limit ) )
			)
		) ?: array();
		$out  = array();
		$seen = array();
		foreach ( $rows as $r ) {
			$p   = json_decode( (string) $r->payload_json, true );
			$sum = is_array( $p['summary'] ?? null ) ? $p['summary'] : array();
			$key = ( $sum['invoice_id'] ?? '' ) . '|' . $r->event_type;
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = array(
				'date'       => $r->received_at,
				'result'     => 'invoice.paid' === $r->event_type ? 'paid' : 'failed',
				'amount'     => (int) ( 'invoice.paid' === $r->event_type ? ( $sum['amount_paid'] ?? 0 ) : ( $sum['amount_due'] ?? 0 ) ),
				'currency'   => strtoupper( (string) ( $sum['currency'] ?? 'usd' ) ),
				'invoice_id' => (string) ( $sum['invoice_id'] ?? '' ),
				'livemode'   => (int) $r->livemode,
			);
		}
		return $out;
	}

	/**
	 * Determine whether a user is an eligible Property Owner.
	 *
	 * Criteria:
	 * 1. Must be a valid WordPress user.
	 * 2. Must possess the ft_owner role.
	 * 3. Must NOT possess ft_tenant or administrator roles for automatic paid entitlements.
	 * 4. Must be an 'approved' owner in the extended profile table.
	 *
	 * @param int $user_id User ID.
	 * @return bool True if eligible, false otherwise.
	 */
	public static function is_eligible_owner( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return false;
		}

		$roles = (array) $user->roles;

		// Strictly require ft_owner
		if ( ! in_array( Constants::ROLE_OWNER, $roles, true ) ) {
			return false;
		}

		// Strictly exclude tenants and administrators from owner membership entitlements
		if ( in_array( Constants::ROLE_TENANT, $roles, true ) || in_array( 'administrator', $roles, true ) ) {
			return false;
		}

		// Require approved profile status
		$profile = AuthService::get_user_profile( $user_id );
		if ( ! $profile || Constants::STATUS_APPROVED !== $profile->approval_status ) {
			return false;
		}

		return true;
	}

	/**
	 * Retrieve the current active, non-expired subscription for an eligible owner.
	 *
	 * Criteria:
	 * - User must be an eligible approved owner.
	 * - Subscription status must be 'active'.
	 * - current_period_end must be non-empty and in the future (>= now UTC).
	 * - If cancel_at_period_end = 1, remains valid until current_period_end.
	 * - Fails closed to null if status is past_due, canceled, unpaid, incomplete, or expired.
	 *
	 * @param int $user_id User ID.
	 * @return object|null Active subscription record with joined plan metadata or null.
	 */
	public static function get_active_subscription( int $user_id ): ?object {
		global $wpdb;

		if ( ! self::is_eligible_owner( $user_id ) ) {
			return null;
		}

		// Mode isolation: only subscriptions created in the currently entitled Stripe mode may
		// grant entitlements (Test-mode subscriptions never grant access while Live is active).
		$livemode = StripeCredentialService::entitlement_livemode_for_user( $user_id );
		if ( null === $livemode ) {
			return null;
		}

		$sub_table  = $wpdb->prefix . Constants::TABLE_SUBSCRIPTIONS;
		$plan_table = $wpdb->prefix . Constants::TABLE_MEMBERSHIP_PLANS;

		$subscription = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT s.*, p.plan_code, p.name AS plan_name, p.billing_interval, p.price_amount, p.currency, p.features_json, p.is_active AS plan_is_active
				 FROM {$sub_table} s
				 INNER JOIN {$plan_table} p ON s.plan_id = p.id
				 WHERE s.user_id = %d AND s.status = 'active' AND s.livemode = %d
				 ORDER BY s.id DESC
				 LIMIT 1",
				$user_id,
				$livemode
			)
		);

		if ( ! $subscription ) {
			return null;
		}

		// Date validation: fail-closed if current_period_end is missing or expired
		if ( empty( $subscription->current_period_end ) ) {
			return null;
		}

		$period_end_ts = strtotime( $subscription->current_period_end );
		$now_ts        = time();

		if ( false === $period_end_ts || $period_end_ts < $now_ts ) {
			return null;
		}

		return (object) $subscription;
	}

	/**
	 * Check if a user currently has an active, valid paid subscription.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function has_active_subscription( int $user_id ): bool {
		return null !== self::get_active_subscription( $user_id );
	}

	/**
	 * Retrieve the authoritative membership plan for a property owner.
	 *
	 * If user is not an eligible owner, or has no valid active paid subscription,
	 * returns the Free plan.
	 *
	 * @param int $user_id User ID.
	 * @return object Plan object.
	 */
	public static function get_owner_plan( int $user_id ): object {
		if ( ! self::is_eligible_owner( $user_id ) ) {
			return self::get_free_plan();
		}

		$subscription = self::get_active_subscription( $user_id );
		if ( $subscription ) {
			$plan = self::get_plan_by_id( (int) $subscription->plan_id );
			if ( $plan ) {
				return $plan;
			}
		}

		return self::get_free_plan();
	}

	/**
	 * Check whether a user is entitled to a specific feature.
	 *
	 * @param int    $user_id     User ID.
	 * @param string $feature_key Feature identifier (e.g. 'tenant_search_basic', 'full_reports', 'incident_reporting', 'unlimited_properties').
	 * @return bool True if entitled, false otherwise.
	 */
	public static function can_access_feature( int $user_id, string $feature_key ): bool {
		// Non-eligible users (tenants, admins, unapproved owners) do not get paid feature entitlements
		if ( ! self::is_eligible_owner( $user_id ) ) {
			return false;
		}

		$plan = self::get_owner_plan( $user_id );
		if ( ! $plan || empty( $plan->features_json ) ) {
			return false;
		}

		$features = json_decode( $plan->features_json, true );
		if ( ! is_array( $features ) ) {
			return false; // Malformed JSON fails closed
		}

		if ( ! isset( $features[ $feature_key ] ) ) {
			return false;
		}

		return true === $features[ $feature_key ] || 1 === $features[ $feature_key ] || '1' === $features[ $feature_key ];
	}

	/**
	 * Retrieve the maximum active property quota for a user.
	 *
	 * Returns -1 for unlimited properties.
	 * Returns 1 for the Free plan.
	 * Fails safely to 1 if the quota configuration is missing or corrupt.
	 *
	 * @param int $user_id User ID.
	 * @return int Limit count or -1 for unlimited.
	 */
	public static function get_property_limit( int $user_id ): int {
		if ( ! self::is_eligible_owner( $user_id ) ) {
			return 1; // Default safe limit for unapproved or non-owner
		}

		$plan = self::get_owner_plan( $user_id );
		if ( ! $plan || empty( $plan->features_json ) ) {
			return 1;
		}

		$features = json_decode( $plan->features_json, true );
		if ( ! is_array( $features ) ) {
			return 1; // Corrupted JSON fails closed to 1 (safe property quota, not unlimited)
		}

		// Standard plans with unlimited properties
		if ( ! empty( $features['unlimited_properties'] ) ) {
			return -1;
		}

		// Specific quota defined
		if ( isset( $features['max_active_properties'] ) && is_numeric( $features['max_active_properties'] ) ) {
			$quota = (int) $features['max_active_properties'];
			return $quota > 0 ? $quota : 1;
		}

		// Free plan default quota
		return 1;
	}

	/**
	 * Retrieve structured membership state for diagnostic or dashboard display.
	 *
	 * @param int $user_id User ID.
	 * @return array<string, mixed>
	 */
	public static function get_effective_membership_status( int $user_id ): array {
		$is_eligible   = self::is_eligible_owner( $user_id );
		$subscription  = $is_eligible ? self::get_active_subscription( $user_id ) : null;
		$plan          = $is_eligible ? self::get_owner_plan( $user_id ) : self::get_free_plan();
		$limit         = self::get_property_limit( $user_id );
		$features      = ! empty( $plan->features_json ) ? (array) json_decode( $plan->features_json, true ) : array();

		return array(
			'is_eligible'            => $is_eligible,
			'has_active_subscription'=> null !== $subscription,
			'subscription_id'        => $subscription ? (int) $subscription->id : null,
			'plan_id'                => (int) $plan->id,
			'plan_code'              => (string) $plan->plan_code,
			'plan_name'              => (string) $plan->name,
			'billing_interval'       => (string) $plan->billing_interval,
			'price_amount'           => (float) $plan->price_amount,
			'currency'               => (string) $plan->currency,
			'property_limit'         => $limit,
			'cancel_at_period_end'   => $subscription ? (bool) $subscription->cancel_at_period_end : false,
			'current_period_end'     => $subscription ? $subscription->current_period_end : null,
			'features'               => $features,
		);
	}

	/**
	 * Update an existing membership plan configuration (Administrator action).
	 *
	 * Restrictions enforced:
	 * - plan_code is immutable.
	 * - prices, currency, billing_interval remain unchanged in Phase 9.1.
	 * - Free plan cannot be deactivated.
	 * - Stripe IDs are validated for ^prod_... and ^price_... syntax.
	 * - Audit log event emitted.
	 *
	 * @param int   $plan_id  Plan database ID.
	 * @param array $data     Updated field values.
	 * @param int   $actor_id Admin user ID performing the update.
	 * @return array{success: bool, message: string}
	 */
	public static function update_plan( int $plan_id, array $data, int $actor_id ): array {
		global $wpdb;

		if ( $plan_id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid plan ID.', 'fraud-tenant-core' ),
			);
		}

		$plan = self::get_plan_by_id( $plan_id );
		if ( ! $plan ) {
			return array(
				'success' => false,
				'message' => __( 'Plan not found.', 'fraud-tenant-core' ),
			);
		}

		$name        = isset( $data['name'] ) ? sanitize_text_field( trim( $data['name'] ) ) : $plan->name;
		$description = isset( $data['description'] ) ? sanitize_textarea_field( trim( $data['description'] ) ) : $plan->description;

		if ( empty( $name ) ) {
			return array(
				'success' => false,
				'message' => __( 'Plan name cannot be empty.', 'fraud-tenant-core' ),
			);
		}

		// Free plan cannot be deactivated
		$is_active = isset( $data['is_active'] ) ? ( (bool) $data['is_active'] ? 1 : 0 ) : (int) $plan->is_active;
		if ( 'free' === $plan->plan_code && 0 === $is_active ) {
			return array(
				'success' => false,
				'message' => __( 'The Free Plan cannot be deactivated.', 'fraud-tenant-core' ),
			);
		}

		// Stripe Product ID validation
		$stripe_prod_id = isset( $data['stripe_product_id'] ) ? sanitize_text_field( trim( $data['stripe_product_id'] ) ) : (string) ( $plan->stripe_product_id ?? '' );
		if ( '' !== $stripe_prod_id && ! preg_match( '/^prod_[a-zA-Z0-9]+$/', $stripe_prod_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid Stripe Product ID format. Must match ^prod_[a-zA-Z0-9]+$', 'fraud-tenant-core' ),
			);
		}

		// Stripe Price ID validation
		$stripe_price_id = isset( $data['stripe_price_id'] ) ? sanitize_text_field( trim( $data['stripe_price_id'] ) ) : (string) ( $plan->stripe_price_id ?? '' );
		if ( '' !== $stripe_price_id && ! preg_match( '/^price_[a-zA-Z0-9]+$/', $stripe_price_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid Stripe Price ID format. Must match ^price_[a-zA-Z0-9]+$', 'fraud-tenant-core' ),
			);
		}

		// Dual-mode Test & Live identifiers
		$test_prod = isset( $data['stripe_test_product_id'] )
			? sanitize_text_field( trim( $data['stripe_test_product_id'] ) )
			: ( ! empty( $plan->stripe_test_product_id ) ? (string) $plan->stripe_test_product_id : $stripe_prod_id );
		if ( '' !== $test_prod && ! preg_match( '/^prod_[a-zA-Z0-9]+$/', $test_prod ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid Stripe Test Product ID format.', 'fraud-tenant-core' ),
			);
		}

		$test_price = isset( $data['stripe_test_price_id'] )
			? sanitize_text_field( trim( $data['stripe_test_price_id'] ) )
			: ( ! empty( $plan->stripe_test_price_id ) ? (string) $plan->stripe_test_price_id : $stripe_price_id );
		if ( '' !== $test_price && ! preg_match( '/^price_[a-zA-Z0-9]+$/', $test_price ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid Stripe Test Price ID format.', 'fraud-tenant-core' ),
			);
		}

		$live_prod = isset( $data['stripe_live_product_id'] )
			? sanitize_text_field( trim( $data['stripe_live_product_id'] ) )
			: (string) ( $plan->stripe_live_product_id ?? '' );
		if ( '' !== $live_prod && ! preg_match( '/^prod_[a-zA-Z0-9]+$/', $live_prod ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid Stripe Live Product ID format.', 'fraud-tenant-core' ),
			);
		}

		$live_price = isset( $data['stripe_live_price_id'] )
			? sanitize_text_field( trim( $data['stripe_live_price_id'] ) )
			: (string) ( $plan->stripe_live_price_id ?? '' );
		if ( '' !== $live_price && ! preg_match( '/^price_[a-zA-Z0-9]+$/', $live_price ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid Stripe Live Price ID format.', 'fraud-tenant-core' ),
			);
		}

		// Features compilation
		$existing_features = ! empty( $plan->features_json ) ? json_decode( $plan->features_json, true ) : array();
		if ( ! is_array( $existing_features ) ) {
			$existing_features = self::get_default_features( $plan->plan_code );
		}

		$updated_features = $existing_features;

		if ( isset( $data['features'] ) && is_array( $data['features'] ) ) {
			$updated_features['tenant_search_basic']  = ! empty( $data['features']['tenant_search_basic'] );
			$updated_features['full_reports']         = ! empty( $data['features']['full_reports'] );
			$updated_features['incident_reporting']   = ! empty( $data['features']['incident_reporting'] );
			$updated_features['unlimited_properties'] = ! empty( $data['features']['unlimited_properties'] );
		}

		// Property limit for free plan
		if ( 'free' === $plan->plan_code ) {
			$max_props = isset( $data['max_active_properties'] ) ? (int) $data['max_active_properties'] : ( $existing_features['max_active_properties'] ?? 1 );
			$updated_features['max_active_properties'] = max( 1, $max_props );
			$updated_features['unlimited_properties']  = false;
		}

		$table  = $wpdb->prefix . Constants::TABLE_MEMBERSHIP_PLANS;
		$update = $wpdb->update(
			$table,
			array(
				'name'                   => $name,
				'description'            => $description,
				'stripe_product_id'      => '' !== $test_prod ? $test_prod : null,
				'stripe_price_id'        => '' !== $test_price ? $test_price : null,
				'stripe_test_product_id' => '' !== $test_prod ? $test_prod : null,
				'stripe_test_price_id'   => '' !== $test_price ? $test_price : null,
				'stripe_live_product_id' => '' !== $live_prod ? $live_prod : null,
				'stripe_live_price_id'   => '' !== $live_price ? $live_price : null,
				'features_json'          => wp_json_encode( $updated_features ),
				'is_active'              => $is_active,
				'updated_at'             => current_time( 'mysql' ),
			),
			array( 'id' => $plan->id ),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' ),
			array( '%d' )
		);

		if ( false === $update ) {
			return array(
				'success' => false,
				'message' => __( 'Database error while saving plan.', 'fraud-tenant-core' ),
			);
		}

		// Audit Log
		AuditLogger::log(
			'membership_plan_updated',
			'membership_plan',
			$plan->id,
			array(
				'plan_code'         => $plan->plan_code,
				'name'              => $name,
				'is_active'         => $is_active,
				'stripe_product_id' => $stripe_prod_id,
				'stripe_price_id'   => $stripe_price_id,
				'features'          => $updated_features,
			),
			$actor_id
		);

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: %s: Plan name */
				__( 'Plan "%s" updated successfully.', 'fraud-tenant-core' ),
				$name
			),
		);
	}
}
