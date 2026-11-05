<?php
/**
 * Incident Reporting Management Service.
 *
 * Handles authorized property owner incident report submission,
 * property eligibility verification, factual attestation recording,
 * privacy gating (BLK-08), and owner-isolated report management for Phase 8.1.
 *
 * @package FraudTenant\Services
 */

namespace FraudTenant\Services;

use FraudTenant\Auth\AuthService;
use FraudTenant\Auth\Capabilities;
use FraudTenant\Services\AuditLogger;
use FraudTenant\Services\DocumentService;
use FraudTenant\Services\PropertyService;
use FraudTenant\Utils\Constants;
use FraudTenant\Utils\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IncidentService {

	/**
	 * Permissible incident report categories.
	 */
	public const ALLOWED_CATEGORIES = array(
		'unpaid_rent'      => 'Unpaid Rent / Default',
		'property_damage'  => 'Property Damage',
		'late_rent'        => 'Repeated Late Rent',
		'lease_violation'  => 'Lease Violation',
		'unauthorized_pet' => 'Unauthorized Pet',
		'noise_complaint'  => 'Noise / Nuisance Complaint',
		'other_violation'  => 'Other Lease Violation',
	);

	/**
	 * Attestation version identifier recorded on incident submissions.
	 */
	public const ATTESTATION_VERSION = '1.0';

	/**
	 * Rate limiting thresholds per owner.
	 */
	public const MAX_INCIDENTS_PER_WINDOW = 10;
	public const RATE_LIMIT_WINDOW_SECS    = 300; // 5 minutes

	/**
	 * Check whether non-production test mode is active for incident submissions.
	 *
	 * Strict Security Gate Policy:
	 * 1. Constant FT_INCIDENT_TEST_MODE must be explicitly defined and strictly equal to boolean true.
	 * 2. Constant WP_ENVIRONMENT_TYPE must be explicitly defined as a direct PHP constant.
	 * 3. Accepted environment values are strictly: 'local', 'development', 'staging'.
	 * 4. 'production', unknown, undefined, or missing values strictly fail-closed.
	 * 5. Direct PHP constant evaluation only: wp_get_environment_type() and WordPress core
	 *    filters (including the 'wp_get_environment_type' filter) are deliberately NOT used
	 *    to eliminate any third-party filter influence on security decisions.
	 * 6. Immunity: No GET, POST, AJAX, REST parameters, cookies, user capabilities, or plugin filters
	 *    can enable or influence test mode.
	 *
	 * @return bool True if test mode is enabled and in non-production environment, false otherwise.
	 */
	public static function is_test_mode_enabled(): bool {
		// 1. Strict constant check for test mode flag
		if ( ! defined( 'FT_INCIDENT_TEST_MODE' ) || true !== constant( 'FT_INCIDENT_TEST_MODE' ) ) {
			return false;
		}

		// 2. Strict constant check for environment type (direct PHP constant only, no filter hooks)
		if ( ! defined( 'WP_ENVIRONMENT_TYPE' ) ) {
			return false;
		}

		$env = constant( 'WP_ENVIRONMENT_TYPE' );
		if ( ! is_string( $env ) ) {
			return false;
		}

		// 3. Strict whitelist check: ONLY 'local', 'development', or 'staging'
		$allowed_envs = array( 'local', 'development', 'staging' );
		return in_array( strtolower( trim( $env ) ), $allowed_envs, true );
	}

	/**
	 * Verify whether an actor satisfies all authorization requirements to submit an incident report.
	 *
	 * Strict checks:
	 * - Authenticated user with role ft_owner (or core administrator).
	 * - Required capability ft_submit_incident_report (or manage_options).
	 * - User profile approval_status === 'approved'.
	 * - User account status === 'approved' (not suspended, rejected, unverified, or pending).
	 *
	 * @param int $user_id User ID.
	 * @return bool True if authorized, false otherwise.
	 */
	public static function can_submit_incident( int $user_id, bool $check_membership = true ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}

		// Core administrator inspection privilege
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		// Must have the required submit incident report capability
		if ( ! user_can( $user_id, Capabilities::SUBMIT_INCIDENT_REPORT ) ) {
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

		// Membership Plan Feature Entitlement Check (Phase 9.4)
		// Business rule: incident reporting requires an active Standard membership. Enforced by
		// default; only legacy pre-9.4 CLI suites (FT_DOING_TESTS without FT_ENFORCE_MEMBERSHIP_QUOTA)
		// are exempt, mirroring the property-quota exemption in PropertyService.
		$check_feature = (bool) apply_filters( 'ft_enforce_incident_feature_entitlement', true, $user_id );
		if ( defined( 'FT_DOING_TESTS' ) && ! defined( 'FT_ENFORCE_MEMBERSHIP_QUOTA' ) ) {
			$check_feature = false;
		}
		if ( defined( 'FT_ENFORCE_MEMBERSHIP_QUOTA' ) && FT_ENFORCE_MEMBERSHIP_QUOTA ) {
			$check_feature = true;
		}

		if ( $check_membership && $check_feature && ! MembershipService::can_access_feature( $user_id, 'incident_reporting' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Verify whether an actor can view a specific incident report.
	 *
	 * @param int $user_id     User ID.
	 * @param int $incident_id Incident Report ID.
	 * @return bool True if authorized, false otherwise.
	 */
	public static function can_view_incident( int $user_id, int $incident_id ): bool {
		if ( $user_id <= 0 || $incident_id <= 0 ) {
			return false;
		}

		if ( user_can( $user_id, 'manage_options' ) ||
			 user_can( $user_id, Capabilities::REVIEW_INCIDENT_REPORTS ) ||
			 user_can( $user_id, Capabilities::APPROVE_INCIDENT_REPORTS ) ) {
			return true;
		}

		if ( ! user_can( $user_id, Capabilities::VIEW_OWN_REPORTS ) ) {
			return false;
		}

		global $wpdb;
		$table    = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;
		$owner_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT owner_id FROM {$table} WHERE id = %d", $incident_id ) );

		return $owner_id === $user_id;
	}

	/**
	 * Validate a property for incident report association.
	 *
	 * Enforces:
	 * - Property exists.
	 * - Property belongs to the requesting owner (prevents IDOR).
	 * - Property is active and not archived.
	 * - Property verification_status is 'verified'.
	 *
	 * @param int $property_id Target property ID.
	 * @param int $owner_id    Authenticated owner ID.
	 * @return array Validation result array.
	 */
	public static function validate_property_for_incident( int $property_id, int $owner_id ): array {
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
				'message' => __( 'Incident reports cannot be submitted for an archived property.', 'fraud-tenant-core' ),
			);
		}

		// Verification status check
		if ( Constants::PROP_STATUS_VERIFIED !== $property->status ) {
			return array(
				'valid'   => false,
				'error'   => 'property_not_verified',
				'message' => __( 'Incident reports can only be submitted for verified properties.', 'fraud-tenant-core' ),
			);
		}

		return array(
			'valid'    => true,
			'property' => $property,
		);
	}

	/**
	 * Enforce submission rate limiting per owner using a sliding inactivity TTL transient counter.
	 *
	 * Rate-Limiting Semantics & Architecture Notes:
	 * - Window Semantics: Sliding inactivity TTL window (RATE_LIMIT_WINDOW_SECS = 300s).
	 *   Each new submission resets the transient expiration to 300 seconds from the time of submission.
	 *   An owner must remain inactive for the full 300 seconds for the counter to expire.
	 * - Concurrency & Race Condition Characteristics: Standard WordPress transients stored in the wp_options
	 *   table without an external in-memory object cache (e.g. Redis INCR) exhibit read-modify-write race
	 *   conditions under highly concurrent requests. In single-server environments with modest request rates,
	 *   transient counters provide effective flood deterrence; production scaling with strict atomic rate
	 *   limits requires Redis/Memcached atomic increments.
	 *
	 * @param int $owner_id Owner user ID.
	 * @return bool True if within rate limit, false if rate limited.
	 */
	public static function check_rate_limit( int $owner_id ): bool {
		$key   = 'ft_inc_rate_' . $owner_id;
		$count = (int) get_transient( $key );

		if ( $count >= self::MAX_INCIDENTS_PER_WINDOW ) {
			return false;
		}

		set_transient( $key, $count + 1, self::RATE_LIMIT_WINDOW_SECS );
		return true;
	}

	/**
	 * Record a new incident report submitted by an authorized owner.
	 *
	 * Directives:
	 * - Privacy Gate: In production mode, submissions are locked pending BLK-08 encryption infrastructure.
	 * - In test mode, synthetic submissions are recorded with initial status 'submitted' and disclosable = 0.
	 * - No sensitive PII or unencrypted free-text descriptions are stored in audit logs, transients, or API responses.
	 * - Optional supporting evidence document is safely uploaded via DocumentService.
	 *
	 * @param int        $owner_id Authenticated owner ID.
	 * @param array      $data     Incident report form fields.
	 * @param array|null $doc_file Optional $_FILES entry for supporting evidence document.
	 * @return array Result array with success, incident_id, reference, status, and message.
	 */
	public static function record_incident_report( int $owner_id, array $data, ?array $doc_file = null ): array {
		global $wpdb;

		// 1. Production Privacy & Evidence Gate (BLK-08 Unresolved) — evaluated first, matching
		//    the REST permission callback order.
		if ( ! ComplianceService::is_data_gate_open() ) {
			return array(
				'success' => false,
				'error'   => 'submission_locked_blk08',
				'message' => __( 'Incident submission is currently locked pending completion of sensitive PII encryption infrastructure (BLK-08). Non-production test mode is required for synthetic testing.', 'fraud-tenant-core' ),
			);
		}

		// 2. Strict Owner Authorization + Standard membership entitlement gate
		if ( ! self::can_submit_incident( $owner_id ) ) {
			$needs_membership = self::can_submit_incident( $owner_id, false ); // Only membership is missing.
			return array(
				'success' => false,
				'error'   => $needs_membership ? 'membership_required' : 'unauthorized',
				'message' => $needs_membership
					? __( 'Incident reporting requires an active Standard membership.', 'fraud-tenant-core' )
					: __( 'You are not authorized to submit incident reports.', 'fraud-tenant-core' ),
			);
		}

		// 3. Rate Limiting Check
		if ( ! self::check_rate_limit( $owner_id ) ) {
			return array(
				'success' => false,
				'error'   => 'rate_limited',
				'message' => __( 'Too many incident report submissions. Please wait a few minutes before submitting another report.', 'fraud-tenant-core' ),
			);
		}

		// 4. Property Validation & IDOR Prevention
		$property_id = isset( $data['property_id'] ) ? (int) $data['property_id'] : 0;
		$prop_check  = self::validate_property_for_incident( $property_id, $owner_id );
		if ( ! $prop_check['valid'] ) {
			return array(
				'success' => false,
				'error'   => $prop_check['error'],
				'message' => $prop_check['message'],
			);
		}

		// 5. Category Validation
		$category = isset( $data['category'] ) ? sanitize_key( $data['category'] ) : '';
		if ( ! array_key_exists( $category, self::ALLOWED_CATEGORIES ) ) {
			return array(
				'success' => false,
				'error'   => 'invalid_category',
				'message' => __( 'Please select a valid incident category from the approved list.', 'fraud-tenant-core' ),
			);
		}

		// 6. Incident Date Validation (must not be in the future)
		$incident_date_raw = isset( $data['incident_date'] ) ? sanitize_text_field( $data['incident_date'] ) : '';
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $incident_date_raw ) ) {
			return array(
				'success' => false,
				'error'   => 'invalid_incident_date',
				'message' => __( 'Please provide a valid incident date in YYYY-MM-DD format.', 'fraud-tenant-core' ),
			);
		}

		$date_parts = explode( '-', $incident_date_raw );
		if ( ! checkdate( (int) $date_parts[1], (int) $date_parts[2], (int) $date_parts[0] ) ) {
			return array(
				'success' => false,
				'error'   => 'invalid_incident_date',
				'message' => __( 'The specified incident date is not a valid calendar date.', 'fraud-tenant-core' ),
			);
		}

		$date_ts  = strtotime( $incident_date_raw );
		$today_ts = strtotime( current_time( 'Y-m-d' ) );
		if ( false === $date_ts || $date_ts > $today_ts || $date_ts < 0 ) {
			return array(
				'success' => false,
				'error'   => 'invalid_incident_date',
				'message' => __( 'Incident date cannot be in the future or invalid.', 'fraud-tenant-core' ),
			);
		}

		// 7. Amount Involved Validation
		$amount_raw = isset( $data['amount_involved'] ) ? $data['amount_involved'] : 0.00;
		if ( ! is_numeric( $amount_raw ) || (float) $amount_raw < 0 ) {
			return array(
				'success' => false,
				'error'   => 'invalid_amount',
				'message' => __( 'Amount involved must be a non-negative number.', 'fraud-tenant-core' ),
			);
		}
		$amount_involved = round( (float) $amount_raw, 2 );

		// 8. Factual Description Validation
		$description = isset( $data['factual_description'] ) ? sanitize_textarea_field( wp_unslash( $data['factual_description'] ) ) : '';
		if ( mb_strlen( trim( $description ) ) < 10 ) {
			return array(
				'success' => false,
				'error'   => 'missing_description',
				'message' => __( 'Please provide a factual narrative description of the incident (at least 10 characters).', 'fraud-tenant-core' ),
			);
		}

		// 9. Owner Attestation Validation
		$attestation_val = $data['owner_attestation'] ?? null;
		$attestation_ok  = ! empty( $attestation_val ) && in_array( $attestation_val, array( 1, '1', true, 'true', 'on' ), true );
		if ( ! $attestation_ok ) {
			return array(
				'success' => false,
				'error'   => 'missing_attestation',
				'message' => __( 'You must attest that all provided incident information is strictly factual and accurate.', 'fraud-tenant-core' ),
			);
		}

		// 10. Optional Evidence File Pre-Validation
		$has_evidence_upload = false;
		if ( ! empty( $doc_file ) && isset( $doc_file['error'] ) && UPLOAD_ERR_NO_FILE !== $doc_file['error'] ) {
			$has_evidence_upload = true;
			// Basic checks before inserting incident
			if ( $doc_file['error'] !== UPLOAD_ERR_OK ) {
				return array(
					'success' => false,
					'error'   => 'invalid_upload_payload',
					'message' => __( 'Supporting document upload failed. Please check the file and try again.', 'fraud-tenant-core' ),
				);
			}

			if ( (int) $doc_file['size'] > DocumentService::MAX_FILE_SIZE ) {
				return array(
					'success' => false,
					'error'   => 'file_size_limit_exceeded',
					'message' => __( 'Evidence document exceeds the 10 MB maximum permitted size.', 'fraud-tenant-core' ),
				);
			}

			$ext = strtolower( pathinfo( (string) $doc_file['name'], PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, DocumentService::ALLOWED_EXTENSIONS, true ) ) {
				return array(
					'success' => false,
					'error'   => 'invalid_file_extension',
					'message' => __( 'Invalid evidence document file type. Only PDF, PNG, and JPEG files are accepted.', 'fraud-tenant-core' ),
				);
			}
		}

		// 10b. Tenant (subject) identity — who this incident is about.
		$subject = self::prepare_subject_identity( $data );
		if ( ! $subject['ok'] ) {
			return array(
				'success' => false,
				'error'   => $subject['error'],
				'message' => $subject['message'],
			);
		}

		// 11. Database Insertion into wp_ft_incident_reports
		$now   = current_time( 'mysql', true );
		$table = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;

		$inserted = $wpdb->insert(
			$table,
			array(
				'owner_id'            => $owner_id,
				'tenant_identity_id'  => null, // Unlinked / synthetic in Phase 8.1 (BLK-08 pending)
				'property_id'         => $property_id,
				'category'            => $category,
				'incident_date'       => $incident_date_raw,
				'amount_involved'     => $amount_involved,
				'factual_description' => $description,
				'owner_attestation'   => 1,
				'status'              => Constants::INCIDENT_STATUS_SUBMITTED,
				'disclosable'         => 0,
				'subject_token_hash'  => $subject['token'],
				'subject_ssn_last4'   => $subject['last4'],
				'subject_name_enc'    => $subject['name_enc'],
				'created_at'          => $now,
				'updated_at'          => $now,
			),
			array( '%d', '%d', '%d', '%s', '%s', '%f', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return array(
				'success' => false,
				'error'   => 'db_insert_failed',
				'message' => __( 'Could not record incident report in the database. Please try again.', 'fraud-tenant-core' ),
			);
		}

		$incident_id = (int) $wpdb->insert_id;
		$reference   = sprintf( 'INC-%06d', $incident_id );

		// 12. Store Evidence Document if Provided
		$document_id = null;
		if ( $has_evidence_upload && $doc_file ) {
			$doc_res = DocumentService::store_incident_document( $owner_id, $incident_id, $doc_file );
			if ( $doc_res['success'] ) {
				$document_id = $doc_res['document_id'];
			}
		}

		// 13. Application-Level Append-Only Audit Logging
		// CRITICAL PRIVACY DIRECTIVE: Zero sensitive narrative or tenant PII written to audit log.
		AuditLogger::log(
			'incident.report_submitted',
			'incident_report',
			$incident_id,
			array(
				'incident_id'         => $incident_id,
				'reference'           => $reference,
				'property_id'         => $property_id,
				'category'            => $category,
				'incident_date'       => $incident_date_raw,
				'amount_involved'     => $amount_involved,
				'initial_status'      => Constants::INCIDENT_STATUS_SUBMITTED,
				'disclosable'         => 0,
				'has_evidence_doc'    => (bool) $document_id,
				'document_id'         => $document_id,
				'attestation_version' => self::ATTESTATION_VERSION,
				'test_mode'           => true,
			),
			$owner_id
		);

		return array(
			'success'     => true,
			'incident_id' => $incident_id,
			'reference'   => $reference,
			'status'      => Constants::INCIDENT_STATUS_SUBMITTED,
			'disclosable' => 0,
			'message'     => __( 'Incident report submitted successfully for administrative review.', 'fraud-tenant-core' ),
		);
	}

	/**
	 * Retrieve incident reports submitted by a specific owner with strict ownership isolation.
	 *
	 * @param int   $owner_id Authenticated owner ID.
	 * @param array $args     Query pagination/filtering arguments.
	 * @return array Array of formatted incident report objects.
	 */
	public static function get_owner_incident_reports( int $owner_id, array $args = array() ): array {
		global $wpdb;

		if ( $owner_id <= 0 ) {
			return array();
		}

		$rep_table  = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;
		$prop_table = $wpdb->prefix . Constants::TABLE_PROPERTIES;

		$limit  = isset( $args['limit'] ) ? max( 1, min( 100, (int) $args['limit'] ) ) : 20;
		$offset = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;

		$sql = $wpdb->prepare(
			"SELECT r.id, r.owner_id, r.property_id, r.category, r.incident_date, 
			        r.amount_involved, r.status, r.disclosable, r.created_at,
			        p.title AS property_title, p.address_street AS property_street, 
			        p.unit_number AS property_unit, p.city AS property_city, p.state AS property_state
			 FROM {$rep_table} r
			 LEFT JOIN {$prop_table} p ON r.property_id = p.id
			 WHERE r.owner_id = %d
			 ORDER BY r.id DESC
			 LIMIT %d OFFSET %d",
			$owner_id,
			$limit,
			$offset
		);

		$rows = $wpdb->get_results( $sql ) ?: array();

		foreach ( $rows as &$row ) {
			$row->reference      = sprintf( 'INC-%06d', (int) $row->id );
			$row->category_label = self::ALLOWED_CATEGORIES[ $row->category ] ?? ucwords( str_replace( '_', ' ', $row->category ) );
			$row->amount_formatted = '$' . number_format( (float) $row->amount_involved, 2 );
		}
		unset( $row );

		return $rows;
	}

	/**
	 * Retrieve a single incident report record with strict authorization checks.
	 *
	 * Prevents IDOR: non-admins can only retrieve reports they submitted.
	 *
	 * @param int $report_id           Target report ID.
	 * @param int $requesting_user_id  Authenticated user ID.
	 * @return object|null Formatted report object or null.
	 */
	public static function get_incident_report( int $report_id, int $requesting_user_id ): ?object {
		global $wpdb;

		if ( $report_id <= 0 || $requesting_user_id <= 0 ) {
			return null;
		}

		if ( ! self::can_view_incident( $requesting_user_id, $report_id ) ) {
			return null;
		}

		$rep_table  = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;
		$prop_table = $wpdb->prefix . Constants::TABLE_PROPERTIES;
		$doc_table  = $wpdb->prefix . Constants::TABLE_DOCUMENTS;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT r.*, 
				        p.title AS property_title, p.address_street AS property_street, 
				        p.unit_number AS property_unit, p.city AS property_city, p.state AS property_state, p.postal_code AS property_zip
				 FROM {$rep_table} r
				 LEFT JOIN {$prop_table} p ON r.property_id = p.id
				 WHERE r.id = %d",
				$report_id
			)
		);

		if ( ! $row ) {
			return null;
		}

		$row->reference        = sprintf( 'INC-%06d', (int) $row->id );
		$row->category_label   = self::ALLOWED_CATEGORIES[ $row->category ] ?? ucwords( str_replace( '_', ' ', $row->category ) );
		$row->amount_formatted = '$' . number_format( (float) $row->amount_involved, 2 );

		// Load attached evidence document metadata if present
		$evidence_doc = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, original_name, file_size, mime_type, uploaded_at 
				 FROM {$doc_table} 
				 WHERE object_type = 'incident_evidence' AND object_id = %d 
				 ORDER BY id DESC LIMIT 1",
				$report_id
			)
		);

		$row->evidence_document = $evidence_doc ?: null;

		return $row;
	}

	/**
	 * Retrieve verified, active properties owned by an owner eligible for incident report linking.
	 *
	 * @param int $owner_id Owner user ID.
	 * @return array Array of eligible property objects.
	 */
	public static function get_eligible_properties( int $owner_id ): array {
		if ( $owner_id <= 0 ) {
			return array();
		}

		$all_properties = PropertyService::get_owner_properties( $owner_id, false );
		$eligible       = array();

		foreach ( $all_properties as $prop ) {
			if ( empty( $prop->archived_at ) &&
				 Constants::PROP_STATUS_ARCHIVED !== $prop->status &&
				 Constants::PROP_STATUS_VERIFIED === $prop->status ) {
				$eligible[] = $prop;
			}
		}

		return $eligible;
	}

	/**
	 * Whether a legacy (pre-1.8.0) CLI suite is running, which predates subject identity capture.
	 *
	 * @return bool
	 */
	protected static function is_legacy_test_context(): bool {
		return defined( 'FT_DOING_TESTS' ) && ! defined( 'FT_ENFORCE_MEMBERSHIP_QUOTA' );
	}

	/**
	 * Plausibility check for an SSN beyond format (area 000/666/900-999, group 00, serial 0000 rejected).
	 *
	 * @param string $ssn SSN (formatted or 9 digits).
	 * @return bool
	 */
	public static function is_plausible_ssn( string $ssn ): bool {
		if ( ! SecurityService::validate_ssn_format( $ssn ) ) {
			return false;
		}
		$d      = str_replace( '-', '', trim( $ssn ) );
		$area   = (int) substr( $d, 0, 3 );
		$group  = (int) substr( $d, 3, 2 );
		$serial = (int) substr( $d, 5, 4 );
		return $area > 0 && 666 !== $area && $area < 900 && $group > 0 && $serial > 0;
	}

	/**
	 * Validate and protect the identity of the tenant an incident is about.
	 *
	 * Required fields: tenant_first_name, tenant_last_name, tenant_ssn, identity_attestation.
	 * Stored: HMAC-SHA256 match token (FT_MATCH_SECRET_KEY), last four digits, and the name
	 * encrypted with AES-256-GCM. The plaintext SSN is never persisted or logged.
	 *
	 * @param array $data Submitted data.
	 * @return array{ok: bool, error?: string, message?: string, token?: ?string, last4?: ?string, name_enc?: ?string}
	 */
	public static function prepare_subject_identity( array $data ): array {
		$first = isset( $data['tenant_first_name'] ) ? sanitize_text_field( wp_unslash( (string) $data['tenant_first_name'] ) ) : '';
		$last  = isset( $data['tenant_last_name'] ) ? sanitize_text_field( wp_unslash( (string) $data['tenant_last_name'] ) ) : '';
		$ssn   = isset( $data['tenant_ssn'] ) ? trim( (string) wp_unslash( $data['tenant_ssn'] ) ) : '';

		if ( '' === $first && '' === $last && '' === $ssn && self::is_legacy_test_context() ) {
			return array( 'ok' => true, 'token' => null, 'last4' => null, 'name_enc' => null );
		}

		if ( ! ComplianceService::identity_capture_ready() ) {
			return array(
				'ok'      => false,
				'error'   => 'identity_matching_unavailable',
				'message' => __( 'Tenant identity protection is not configured on this platform yet (match key / encryption). Please contact support.', 'fraud-tenant-core' ),
			);
		}
		if ( mb_strlen( $first ) < 1 || mb_strlen( $last ) < 2 || mb_strlen( $first ) > 100 || mb_strlen( $last ) > 100 ) {
			return array(
				'ok'      => false,
				'error'   => 'invalid_tenant_name',
				'message' => __( 'Enter the tenant\'s legal first and last name as shown on the rental application.', 'fraud-tenant-core' ),
			);
		}
		if ( ! self::is_plausible_ssn( $ssn ) ) {
			return array(
				'ok'      => false,
				'error'   => 'invalid_tenant_ssn',
				'message' => __( 'Enter a valid 9-digit Social Security Number for the tenant (format 123-45-6789).', 'fraud-tenant-core' ),
			);
		}
		$id_attest = $data['identity_attestation'] ?? null;
		if ( empty( $id_attest ) || ! in_array( $id_attest, array( 1, '1', true, 'true', 'on' ), true ) ) {
			return array(
				'ok'      => false,
				'error'   => 'missing_identity_attestation',
				'message' => __( 'Confirm that the tenant identity details come from the tenant\'s own rental application or lease.', 'fraud-tenant-core' ),
			);
		}

		$token    = SecurityService::generate_ssn_match_token( $ssn );
		$name_enc = SensitiveDataService::encrypt_array( array( 'first' => $first, 'last' => $last ) );
		if ( false === $token || null === $name_enc ) {
			return array(
				'ok'      => false,
				'error'   => 'identity_protection_failed',
				'message' => __( 'Tenant identity could not be protected. The report was not saved.', 'fraud-tenant-core' ),
			);
		}
		return array(
			'ok'       => true,
			'token'    => $token,
			'last4'    => substr( str_replace( '-', '', $ssn ), -4 ),
			'name_enc' => $name_enc,
		);
	}

	/**
	 * Decrypted subject identity for authorized display (name + last four only).
	 *
	 * @param object $report Incident row.
	 * @return array{has_subject: bool, first: string, last: string, last4: string}
	 */
	public static function get_subject_display( object $report ): array {
		$n = SensitiveDataService::decrypt_array( $report->subject_name_enc ?? null );
		return array(
			'has_subject' => ! empty( $report->subject_token_hash ),
			'first'       => (string) ( $n['first'] ?? '' ),
			'last'        => (string) ( $n['last'] ?? '' ),
			'last4'       => (string) ( $report->subject_ssn_last4 ?? '' ),
		);
	}
}
