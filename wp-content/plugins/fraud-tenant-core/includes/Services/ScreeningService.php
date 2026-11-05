<?php
/**
 * Tenant Screening Management Service.
 *
 * Handles authorized property owner screening request initiation,
 * permissible-purpose attestation recording, property eligibility verification,
 * and owner-isolated request tracking for Phase 7.1.
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
	 * Initiate and record a tenant screening request foundation for an authorized owner.
	 *
	 * Privacy & Data-Minimization Directives (Phase 7.1.1):
	 * - Zero applicant identity (SSN or applicant name) collection in Phase 7.1.
	 * - Zero writing to ssn_last_four column (remains unpopulated/default).
	 * - Zero SSN fragments or applicant names returned in API responses or stored in audit logs.
	 * - Action-specific, time-limited WordPress nonce validation combined with independent
	 *   capability, account-status, and ownership authorization.
	 * - Requests are registered with controlled initial status: 'pending', disclosure_level: 'locked'.
	 * - Permissible purpose attestation recorded as an unverified owner-provided statement.
	 *   Does not constitute independently verified tenant consent, proof of tenant identity,
	 *   proof of legal permissible purpose, or a statutory compliance determination.
	 * - Application-level append-only audit logging (database-level immutability not independently verified).
	 *
	 * @param int   $owner_id Authenticated owner ID.
	 * @param array $data     Submitted screening request parameters.
	 * @return array Result array with success, request_id, status, and message.
	 */
	public static function record_screening_request( int $owner_id, array $data ): array {
		global $wpdb;

		// 1. Strict Owner Authorization Gate
		if ( ! self::can_initiate_screening( $owner_id ) ) {
			return array(
				'success' => false,
				'error'   => 'unauthorized',
				'message' => __( 'You do not have permission to initiate tenant screening requests.', 'fraud-tenant-core' ),
			);
		}

		// 2. Rate Limiting Gate
		if ( ! self::check_rate_limit( $owner_id ) ) {
			return array(
				'success' => false,
				'error'   => 'rate_limited',
				'message' => __( 'Too many screening requests submitted recently. Please wait a moment before trying again.', 'fraud-tenant-core' ),
			);
		}

		// 3. Property Association & Verification Gate
		$property_id = isset( $data['property_id'] ) ? (int) $data['property_id'] : 0;
		$prop_check  = self::validate_property_for_screening( $property_id, $owner_id );
		if ( ! $prop_check['valid'] ) {
			return array(
				'success' => false,
				'error'   => $prop_check['error'],
				'message' => $prop_check['message'],
			);
		}
		$property = $prop_check['property'];

		// 4. Stated Screening Purpose Gate
		$purpose = isset( $data['purpose_reason'] ) ? sanitize_key( $data['purpose_reason'] ) : '';
		if ( empty( $purpose ) || ! array_key_exists( $purpose, self::ALLOWED_PURPOSES ) ) {
			return array(
				'success' => false,
				'error'   => 'invalid_purpose',
				'message' => __( 'Please select a valid permissible screening purpose.', 'fraud-tenant-core' ),
			);
		}

		// 5. Permissible Purpose Attestation Gate (Owner-provided certification)
		$attestation_given = ! empty( $data['permissible_purpose_attestation'] ) &&
			( '1' === (string) $data['permissible_purpose_attestation'] || true === $data['permissible_purpose_attestation'] || 'on' === (string) $data['permissible_purpose_attestation'] );

		if ( ! $attestation_given ) {
			return array(
				'success' => false,
				'error'   => 'missing_attestation',
				'message' => __( 'You must complete and certify the permissible purpose attestation statement before submitting a screening request.', 'fraud-tenant-core' ),
			);
		}

		// 6. Record Request Foundation in Database (Identity-Free Foundation)
		// Zero SSN or applicant name collection in Phase 7.1.
		// ssn_last_four is strictly omitted from the insert array.
		// tenant_identity_id remains unset/null; match_found = 0; records_count = 0.
		$now       = current_time( 'mysql', true );
		$client_ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );

		$table_screening = $wpdb->prefix . Constants::TABLE_SCREENING_REQUESTS;
		$inserted        = $wpdb->insert(
			$table_screening,
			array(
				'owner_id'                        => $owner_id,
				'property_id'                     => $property_id,
				'tenant_identity_id'              => null,
				'permissible_purpose_attestation' => 1,
				'purpose_reason'                  => $purpose,
				'match_found'                     => 0,
				'records_count'                   => 0,
				'disclosure_level'                => 'locked',
				'search_date'                     => $now,
				'ip_address'                      => $client_ip,
			),
			array( '%d', '%d', '%d', '%d', '%s', '%d', '%d', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return array(
				'success' => false,
				'error'   => 'db_insert_failed',
				'message' => __( 'Failed to record screening request in database.', 'fraud-tenant-core' ),
			);
		}

		$request_id        = (int) $wpdb->insert_id;
		$request_reference = sprintf( 'REQ-%06d', $request_id );

		// 7. Application-Level Append-Only Audit Trail
		// Database-level immutability has not been independently verified.
		// Zero SSN or applicant name recorded.
		AuditLogger::log(
			'screening.request_initiated',
			'screening_request',
			$request_id,
			array(
				'request_id'          => $request_id,
				'request_reference'   => $request_reference,
				'owner_id'            => $owner_id,
				'property_id'         => $property_id,
				'property_title'      => $property->title ?: $property->address_street,
				'purpose_reason'      => $purpose,
				'attestation_version' => self::ATTESTATION_VERSION,
				'attestation_date'    => $now,
				'initial_status'      => 'pending',
				'disclosure_level'    => 'locked',
			),
			$owner_id
		);

		// 8. Return Safe Summary Payload (Zero SSN or applicant name exposure)
		return array(
			'success'    => true,
			'request_id' => $request_id,
			'status'     => 'pending',
			'data'       => array(
				'id'                => $request_id,
				'request_reference' => $request_reference,
				'property_id'       => $property_id,
				'property_title'    => $property->title ?: $property->address_street,
				'purpose_reason'    => $purpose,
				'purpose_label'     => self::ALLOWED_PURPOSES[ $purpose ] ?? $purpose,
				'status'            => 'pending',
				'match_found'       => false,
				'disclosure_level'  => 'locked',
				'search_date'       => $now,
			),
			'message'    => __( 'Tenant screening request foundation recorded successfully. Request status: Pending.', 'fraud-tenant-core' ),
		);
	}

	/**
	 * Retrieve all screening requests for an owner, strictly scoped to their user ID.
	 *
	 * Joins property details and formats masked identifiers.
	 * Prevents cross-owner data leakage.
	 *
	 * @param int   $owner_id Owner user ID.
	 * @param array $args     Optional query filters (limit, offset).
	 * @return array Array of formatted screening request records.
	 */
	public static function get_owner_screening_requests( int $owner_id, array $args = array() ): array {
		global $wpdb;

		if ( $owner_id <= 0 ) {
			return array();
		}

		$scr_table  = $wpdb->prefix . Constants::TABLE_SCREENING_REQUESTS;
		$prop_table = $wpdb->prefix . Constants::TABLE_PROPERTIES;

		$limit  = isset( $args['limit'] ) ? max( 1, (int) $args['limit'] ) : 50;
		$offset = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;

		$sql = "SELECT s.*, 
					   p.title AS property_title, 
					   p.address_street AS property_street, 
					   p.city AS property_city, 
					   p.state AS property_state, 
					   p.postal_code AS property_zip
				FROM {$scr_table} s
				LEFT JOIN {$prop_table} p ON s.property_id = p.id
				WHERE s.owner_id = %d
				ORDER BY s.id DESC
				LIMIT %d OFFSET %d";

		$results = $wpdb->get_results( $wpdb->prepare( $sql, $owner_id, $limit, $offset ) );

		if ( ! empty( $results ) ) {
			foreach ( $results as &$row ) {
				$row->id                = (int) $row->id;
				$row->owner_id          = (int) $row->owner_id;
				$row->property_id       = (int) $row->property_id;
				$row->match_found       = (int) $row->match_found;
				$row->records_count     = (int) $row->records_count;
				$row->request_reference = sprintf( 'REQ-%06d', $row->id );
				$row->purpose_label     = self::ALLOWED_PURPOSES[ $row->purpose_reason ] ?? ucwords( str_replace( '_', ' ', $row->purpose_reason ) );
				$row->status            = 'pending'; // Controlled initial status for Phase 7.1
				unset( $row->ssn_last_four );
			}
			unset( $row );
		}

		return $results ?: array();
	}

	/**
	 * Retrieve a single screening request by ID, strictly enforcing owner authorization.
	 *
	 * Prevents Insecure Direct Object Reference (IDOR).
	 *
	 * @param int      $request_id Screening request ID.
	 * @param int|null $owner_id   Owner user ID for strict scoping (null only for admin inspection).
	 * @return object|null Formatted request object or null if not found or forbidden.
	 */
	public static function get_screening_request( int $request_id, ?int $owner_id = null ): ?object {
		global $wpdb;

		if ( $request_id <= 0 ) {
			return null;
		}

		$scr_table  = $wpdb->prefix . Constants::TABLE_SCREENING_REQUESTS;
		$prop_table = $wpdb->prefix . Constants::TABLE_PROPERTIES;

		if ( null !== $owner_id && $owner_id > 0 ) {
			$sql = "SELECT s.*, 
						   p.title AS property_title, 
						   p.address_street AS property_street, 
						   p.city AS property_city, 
						   p.state AS property_state, 
						   p.postal_code AS property_zip
					FROM {$scr_table} s
					LEFT JOIN {$prop_table} p ON s.property_id = p.id
					WHERE s.id = %d AND s.owner_id = %d
					LIMIT 1";
			$row = $wpdb->get_row( $wpdb->prepare( $sql, $request_id, $owner_id ) );
		} else {
			$sql = "SELECT s.*, 
						   p.title AS property_title, 
						   p.address_street AS property_street, 
						   p.city AS property_city, 
						   p.state AS property_state, 
						   p.postal_code AS property_zip
					FROM {$scr_table} s
					LEFT JOIN {$prop_table} p ON s.property_id = p.id
					WHERE s.id = %d
					LIMIT 1";
			$row = $wpdb->get_row( $wpdb->prepare( $sql, $request_id ) );
		}

		if ( ! $row ) {
			return null;
		}

		$row->id                = (int) $row->id;
		$row->owner_id          = (int) $row->owner_id;
		$row->property_id       = (int) $row->property_id;
		$row->match_found       = (int) $row->match_found;
		$row->records_count     = (int) $row->records_count;
		$row->request_reference = sprintf( 'REQ-%06d', $row->id );
		$row->purpose_label     = self::ALLOWED_PURPOSES[ $row->purpose_reason ] ?? ucwords( str_replace( '_', ' ', $row->purpose_reason ) );
		$row->status            = 'pending';
		unset( $row->ssn_last_four );

		return $row;
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

