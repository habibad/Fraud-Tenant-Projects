<?php
/**
 * Tenant Screening Management Service.
 *
 * Screening authorization, permissible purposes, property eligibility and rate limiting shared by
 * the identity-based search (TenantScreeningService).
 *
 * @package FraudTenant\Services
 */

namespace FraudTenant\Services;

use FraudTenant\Auth\AuthService;
use FraudTenant\Auth\Capabilities;
use FraudTenant\Services\AuditLogger;
use FraudTenant\Services\PropertyService;
use FraudTenant\Services\SecurityService;
use FraudTenant\Utils\Constants;
use FraudTenant\Utils\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ScreeningService {

	/**
	 * Permissible screening purpose reasons allowed by the platform.
	 */
	public const ALLOWED_PURPOSES = array(
		'rental_application' => 'Rental Application Evaluation',
		'lease_renewal'      => 'Lease Renewal Assessment',
		'tenant_evaluation'  => 'Tenant Sublease / Occupant Evaluation',
	);

	/**
	 * Attestation version identifier recorded on submissions.
	 */
	public const ATTESTATION_VERSION = '1.0';

	/**
	 * Rate limiting thresholds.
	 */
	public const MAX_SCREENINGS_PER_WINDOW = 10;
	public const RATE_LIMIT_WINDOW_SECS    = 300; // 5 minutes

	/**
	 * Verify whether an actor satisfies all authorization requirements to initiate screening.
	 *
	 * Strict checks:
	 * - Authenticated user with role ft_owner (or core administrator).
	 * - Required capability ft_initiate_screening (or manage_options).
	 * - User profile approval_status === 'approved'.
	 * - User account status === 'approved' (not suspended, rejected, unverified, or pending).
	 *
	 * @param int $user_id User ID.
	 * @return bool True if authorized, false otherwise.
	 */
	public static function can_initiate_screening( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}

		// Core administrator inspection privilege
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		// Must have the required initiate screening capability
		if ( ! user_can( $user_id, Capabilities::INITIATE_SCREENING ) && ! user_can( $user_id, Capabilities::REQUEST_TENANT_SCREENING ) ) {
			return false;
		}

		// Must have property owner role
		$user = get_user_by( 'id', $user_id );
		if ( ! $user || ! in_array( Constants::ROLE_OWNER, (array) $user->roles, true ) ) {
			return false;
		}

		// Profile approval status check
		$profile = AuthService::get_user_profile( $user_id );
		if ( ! $profile || Constants::STATUS_APPROVED !== $profile->approval_status ) {
			return false;
		}

		// Account lifecycle status check
		$account_status = AuthService::get_user_account_status( $user_id );
		if ( Constants::STATUS_APPROVED !== $account_status ) {
			return false;
		}

		return true;
	}

	/**
	 * Verify whether an actor can view screening reports.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function can_view_screening_reports( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}

		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		if ( ! user_can( $user_id, Capabilities::VIEW_SCREENING_REPORTS ) && ! user_can( $user_id, Capabilities::VIEW_OWN_SCREENINGS ) ) {
			return false;
		}

		return self::can_initiate_screening( $user_id );
	}

	/**
	 * Validate a property for screening eligibility.
	 *
	 * Enforces:
	 * - Property exists.
	 * - Property belongs to the requesting owner (prevents IDOR).
	 * - Property verification_status is 'verified'.
	 * - Property is active and not archived.
	 *
	 * @param int $property_id Target property ID.
	 * @param int $owner_id    Authenticated owner ID.
	 * @return array Validation result array.
	 */
	public static function validate_property_for_screening( int $property_id, int $owner_id ): array {
		if ( $property_id <= 0 ) {
			return array(
				'valid'   => false,
				'error'   => 'missing_property',
				'message' => __( 'Please select an associated verified property.', 'fraud-tenant-core' ),
			);
		}

		// Ownership check: Retrieve property scoped strictly to this owner (or admin)
		$property = PropertyService::get_property( $property_id, $owner_id );
		if ( ! $property ) {
			return array(
				'valid'   => false,
				'error'   => 'property_not_found_or_forbidden',
				'message' => __( 'Property not found or does not belong to your account.', 'fraud-tenant-core' ),
			);
		}

		// Active status check (must precede verification status check)
		if ( ! empty( $property->archived_at ) || Constants::PROP_STATUS_ARCHIVED === $property->status ) {
			return array(
				'valid'   => false,
				'error'   => 'property_archived',
				'message' => __( 'Tenant screening cannot be initiated for an archived property.', 'fraud-tenant-core' ),
			);
		}

		// Verification status check
		if ( Constants::PROP_STATUS_VERIFIED !== $property->status ) {
			return array(
				'valid'   => false,
				'error'   => 'property_not_verified',
				'message' => __( 'Tenant screening can only be initiated for verified properties.', 'fraud-tenant-core' ),
			);
		}

		return array(
			'valid'    => true,
			'property' => $property,
		);
	}

	/**
	 * Enforce submission rate limiting per owner using a fixed-window / sliding-TTL transient counter.
	 *
	 * Uses WordPress transients ('ft_scr_rate_' . $owner_id) expiring after 300 seconds.
	 * Note: Each increment resets the transient expiration to 300 seconds.
	 *
	 * @param int $owner_id Owner user ID.
	 * @return bool True if within rate limit, false if rate limited.
	 */
	public static function check_rate_limit( int $owner_id ): bool {
		$key   = 'ft_scr_rate_' . $owner_id;
		$count = (int) get_transient( $key );

		if ( $count >= self::MAX_SCREENINGS_PER_WINDOW ) {
			return false;
		}

		set_transient( $key, $count + 1, self::RATE_LIMIT_WINDOW_SECS );
		return true;
	}

	/**
	 * Retrieve verified, active properties owned by an owner for the screening form dropdown.
	 *
	 * @param int $owner_id Owner user ID.
	 * @return array Array of eligible property objects.
	 */
	public static function get_owner_verified_properties( int $owner_id ): array {
		global $wpdb;

		if ( $owner_id <= 0 ) {
			return array();
		}

		$prop_table = $wpdb->prefix . Constants::TABLE_PROPERTIES;
		$sql        = "SELECT id, title, address_street, city, state, postal_code, units_count 
					   FROM {$prop_table} 
					   WHERE owner_id = %d 
						 AND status = 'verified' 
						 AND (archived_at IS NULL OR archived_at = '') 
					   ORDER BY id DESC";

		$results = $wpdb->get_results( $wpdb->prepare( $sql, $owner_id ) );

		if ( ! empty( $results ) ) {
			foreach ( $results as &$p ) {
				$p->id          = (int) $p->id;
				$p->units_count = (int) $p->units_count;
			}
			unset( $p );
		}

		return $results ?: array();
	}
}

