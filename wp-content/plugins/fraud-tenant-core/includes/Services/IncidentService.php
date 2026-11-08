<?php
/**
 * Incident report submission, owner views, clarification responses and administrator corrections.
 *
 * Submission (paid Standard owners only, enforced here — not just in the UI):
 * - Property must be the owner's own, active and verified.
 * - Tenant identity (legal name + SSN) is resolved against the central identity record
 *   (IdentityService) inside the same database transaction as the report insert:
 *     matched   → linked to the existing identity (and through it to a claimed tenant account),
 *     created   → linked to a new unclaimed identity,
 *     exception → NOT linked; held for administrator identity review (fail closed).
 * - Only the HMAC token, last four digits and encrypted name are ever stored, and on the report
 *   itself only while an exception is open. Raw SSNs are never stored or logged.
 * - A submitted report is an allegation pending review: status "submitted", not disclosed to anyone.
 *
 * @package FraudTenant\Services
 */

namespace FraudTenant\Services;

use FraudTenant\Auth\AuthService;
use FraudTenant\Auth\Capabilities;
use FraudTenant\Utils\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IncidentService {

	/**
	 * Approved incident categories.
	 */
	public const ALLOWED_CATEGORIES = array(
		'unpaid_rent'      => 'Unpaid Rent',
		'property_damage'  => 'Property Damage',
		'late_rent'        => 'Late Rent',
		'lease_violation'  => 'Lease Violation',
		'unauthorized_pet' => 'Unauthorized Pet',
		'noise_complaint'  => 'Noise Complaint',
		'other_violation'  => 'Other Violation',
	);

	public const ATTESTATION_VERSION      = '1.1';
	public const MAX_INCIDENTS_PER_WINDOW = 10;
	public const RATE_LIMIT_WINDOW_SECS   = 300;
	public const DESCRIPTION_MIN          = 10;
	public const DESCRIPTION_MAX          = 5000;

	/**
	 * Owner (or administrator) authorization to submit, including the Standard membership entitlement.
	 *
	 * @param int  $user_id          User.
	 * @param bool $check_membership Include the membership check.
	 * @return bool
	 */
	public static function can_submit_incident( int $user_id, bool $check_membership = true ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}
		if ( ! user_can( $user_id, Capabilities::SUBMIT_INCIDENT_REPORT ) ) {
			return false;
		}
		$user = get_user_by( 'id', $user_id );
		if ( ! $user || ! in_array( Constants::ROLE_OWNER, (array) $user->roles, true ) ) {
			return false;
		}
		if ( Constants::STATUS_APPROVED !== AuthService::get_user_account_status( $user_id ) ) {
			return false;
		}
		return ! $check_membership || MembershipService::can_access_feature( $user_id, 'incident_reporting' );
	}

	/**
	 * Owner of the report or an incident reviewer.
	 *
	 * @param int $user_id     User.
	 * @param int $incident_id Report.
	 * @return bool
	 */
	public static function can_view_incident( int $user_id, int $incident_id ): bool {
		global $wpdb;
		if ( $user_id <= 0 || $incident_id <= 0 ) {
			return false;
		}
		if ( IncidentReviewService::can_review_incidents( $user_id ) ) {
			return true;
		}
		if ( ! user_can( $user_id, Capabilities::VIEW_OWN_REPORTS ) ) {
			return false;
		}
		$owner_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT owner_id FROM {$wpdb->prefix}" . Constants::TABLE_INCIDENT_REPORTS . ' WHERE id = %d', $incident_id ) );
		return $owner_id === $user_id;
	}

	/**
	 * Property must belong to the owner, be active and verified.
	 *
	 * @param int $property_id Property.
	 * @param int $owner_id    Owner.
	 * @return array
	 */
	public static function validate_property_for_incident( int $property_id, int $owner_id ): array {
		if ( $property_id <= 0 ) {
			return array( 'valid' => false, 'error' => 'missing_property', 'message' => __( 'Select the verified property the incident relates to.', 'fraud-tenant-core' ) );
		}
		$property = PropertyService::get_property( $property_id, $owner_id );
		if ( ! $property ) {
			return array( 'valid' => false, 'error' => 'property_not_found_or_forbidden', 'message' => __( 'Property not found or does not belong to your account.', 'fraud-tenant-core' ) );
		}
		if ( ! empty( $property->archived_at ) || Constants::PROP_STATUS_ARCHIVED === $property->status ) {
			return array( 'valid' => false, 'error' => 'property_archived', 'message' => __( 'Incident reports cannot be submitted for an archived property.', 'fraud-tenant-core' ) );
		}
		if ( Constants::PROP_STATUS_VERIFIED !== $property->status ) {
			return array( 'valid' => false, 'error' => 'property_not_verified', 'message' => __( 'Incident reports can only be submitted for verified properties.', 'fraud-tenant-core' ) );
		}
		return array( 'valid' => true, 'property' => $property );
	}

	/**
	 * Per-owner submission rate limit (transient counter, 5-minute sliding window).
	 *
	 * @param int $owner_id Owner.
	 * @return bool
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
	 * Plausible SSN (format + area/group/serial rules).
	 *
	 * @param string $ssn SSN.
	 * @return bool
	 */
	public static function is_plausible_ssn( string $ssn ): bool {
		return null !== IdentityService::normalize_ssn( $ssn );
	}

	/**
	 * Validate the factual incident fields shared by submission, clarification and correction.
	 *
	 * @param array $data      Input.
	 * @param bool  $partial   Allow missing fields (keep existing values).
	 * @return array{ok: bool, error?: string, message?: string, fields?: array}
	 */
	public static function validate_fields( array $data, bool $partial = false ): array {
		$out = array();
		if ( ! $partial || isset( $data['category'] ) ) {
			$category = sanitize_key( (string) ( $data['category'] ?? '' ) );
			if ( ! array_key_exists( $category, self::ALLOWED_CATEGORIES ) ) {
				return array( 'ok' => false, 'error' => 'invalid_category', 'message' => __( 'Select a valid incident category.', 'fraud-tenant-core' ) );
			}
			$out['category'] = $category;
		}
		if ( ! $partial || ( isset( $data['incident_date'] ) && '' !== $data['incident_date'] ) ) {
			$date = sanitize_text_field( (string) ( $data['incident_date'] ?? '' ) );
			$p    = explode( '-', $date );
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || ! checkdate( (int) $p[1], (int) $p[2], (int) $p[0] ) ) {
				return array( 'ok' => false, 'error' => 'invalid_incident_date', 'message' => __( 'Enter a valid incident date.', 'fraud-tenant-core' ) );
			}
			if ( strtotime( $date ) > strtotime( current_time( 'Y-m-d' ) ) ) {
				return array( 'ok' => false, 'error' => 'invalid_incident_date', 'message' => __( 'The incident date cannot be in the future.', 'fraud-tenant-core' ) );
			}
			$out['incident_date'] = $date;
		}
		if ( ! $partial || ( isset( $data['amount_involved'] ) && '' !== $data['amount_involved'] ) ) {
			$amount = $data['amount_involved'] ?? 0;
			$amount = '' === $amount ? 0 : $amount;
			if ( ! is_numeric( $amount ) || (float) $amount < 0 || (float) $amount > 99999999 ) {
				return array( 'ok' => false, 'error' => 'invalid_amount', 'message' => __( 'The amount must be a non-negative number.', 'fraud-tenant-core' ) );
			}
			$out['amount_involved'] = round( (float) $amount, 2 );
		}
		if ( ! $partial || isset( $data['factual_description'] ) ) {
			$desc = trim( sanitize_textarea_field( wp_unslash( (string) ( $data['factual_description'] ?? '' ) ) ) );
			if ( mb_strlen( $desc ) < self::DESCRIPTION_MIN || mb_strlen( $desc ) > self::DESCRIPTION_MAX ) {
				return array( 'ok' => false, 'error' => 'missing_description', 'message' => __( 'Provide a factual description of the incident (10–5,000 characters).', 'fraud-tenant-core' ) );
			}
			$out['factual_description'] = $desc;
		}
		return array( 'ok' => true, 'fields' => $out );
	}

	/**
	 * Pre-validate an optional evidence upload.
	 *
	 * @param array|null $file $_FILES entry.
	 * @return array{ok: bool, has_file: bool, error?: string, message?: string}
	 */
	protected static function precheck_evidence( ?array $file ): array {
		if ( empty( $file ) || ! isset( $file['error'] ) || UPLOAD_ERR_NO_FILE === $file['error'] ) {
			return array( 'ok' => true, 'has_file' => false );
		}
		if ( UPLOAD_ERR_OK !== $file['error'] ) {
			return array( 'ok' => false, 'has_file' => true, 'error' => 'invalid_upload_payload', 'message' => __( 'The evidence upload failed. Check the file and try again.', 'fraud-tenant-core' ) );
		}
		if ( (int) $file['size'] > DocumentService::MAX_FILE_SIZE ) {
			return array( 'ok' => false, 'has_file' => true, 'error' => 'file_size_limit_exceeded', 'message' => __( 'Evidence documents must be 10 MB or smaller.', 'fraud-tenant-core' ) );
		}
		if ( ! in_array( strtolower( pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) ), DocumentService::ALLOWED_EXTENSIONS, true ) ) {
			return array( 'ok' => false, 'has_file' => true, 'error' => 'invalid_file_extension', 'message' => __( 'Evidence must be a PDF, PNG or JPEG file.', 'fraud-tenant-core' ) );
		}
		return array( 'ok' => true, 'has_file' => true );
	}

	/**
	 * Record a new incident report.
	 *
	 * @param int        $owner_id Owner.
	 * @param array      $data     Form fields (property_id, category, incident_date, amount_involved,
	 *                             factual_description, owner_attestation, tenant_first_name,
	 *                             tenant_last_name, tenant_ssn, identity_attestation).
	 * @param array|null $doc_file Optional evidence ($_FILES entry).
	 * @return array
	 */
	public static function record_incident_report( int $owner_id, array $data, ?array $doc_file = null ): array {
		global $wpdb;

		if ( ! ComplianceService::is_data_gate_open() ) {
			return array( 'success' => false, 'error' => 'submission_locked_blk08', 'message' => __( 'Incident reporting is unavailable until tenant data protection (match key and encryption key) is configured.', 'fraud-tenant-core' ) );
		}
		if ( ! self::can_submit_incident( $owner_id ) ) {
			$needs_membership = self::can_submit_incident( $owner_id, false );
			return array(
				'success' => false,
				'error'   => $needs_membership ? 'membership_required' : 'unauthorized',
				'message' => $needs_membership ? __( 'Incident reporting requires an active Standard membership.', 'fraud-tenant-core' ) : __( 'You are not authorized to submit incident reports.', 'fraud-tenant-core' ),
			);
		}
		if ( ! self::check_rate_limit( $owner_id ) ) {
			return array( 'success' => false, 'error' => 'rate_limited', 'message' => __( 'Too many incident reports in a short time. Please wait a few minutes.', 'fraud-tenant-core' ) );
		}
		$property_id = (int) ( $data['property_id'] ?? 0 );
		$prop        = self::validate_property_for_incident( $property_id, $owner_id );
		if ( ! $prop['valid'] ) {
			return array( 'success' => false, 'error' => $prop['error'], 'message' => $prop['message'] );
		}
		$v = self::validate_fields( $data );
		if ( ! $v['ok'] ) {
			return array( 'success' => false, 'error' => $v['error'], 'message' => $v['message'] );
		}
		$truthy = array( 1, '1', true, 'true', 'on' );
		if ( ! in_array( $data['owner_attestation'] ?? null, $truthy, true ) ) {
			return array( 'success' => false, 'error' => 'missing_attestation', 'message' => __( 'Confirm that the information is factual and accurate.', 'fraud-tenant-core' ) );
		}
		if ( ! in_array( $data['identity_attestation'] ?? null, $truthy, true ) ) {
			return array( 'success' => false, 'error' => 'missing_identity_attestation', 'message' => __( 'Confirm that the tenant identity details come from the tenant\'s own rental application or lease.', 'fraud-tenant-core' ) );
		}
		$ev = self::precheck_evidence( $doc_file );
		if ( ! $ev['ok'] ) {
			return array( 'success' => false, 'error' => $ev['error'], 'message' => $ev['message'] );
		}
		$first = IdentityService::clean_name( $data['tenant_first_name'] ?? '' );
		$last  = IdentityService::clean_name( $data['tenant_last_name'] ?? '' );
		$ssn   = trim( (string) wp_unslash( (string) ( $data['tenant_ssn'] ?? '' ) ) );

		$now   = current_time( 'mysql', true );
		$table = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;
		$wpdb->query( 'START TRANSACTION' );
		$id = IdentityService::resolve( $first, $last, $ssn );
		unset( $ssn );
		if ( ! $id['ok'] ) {
			$wpdb->query( 'ROLLBACK' );
			return array( 'success' => false, 'error' => $id['error'], 'message' => $id['message'] );
		}
		$is_exception = Constants::IDENTITY_EXCEPTION === $id['status'];
		$subject_enc  = $is_exception ? SensitiveDataService::encrypt_array( array( 'first' => $first, 'last' => $last ), SensitiveDataService::CTX_SUBJECT_NAME ) : null;
		if ( $is_exception && null === $subject_enc ) {
			$wpdb->query( 'ROLLBACK' );
			return array( 'success' => false, 'error' => 'identity_protection_failed', 'message' => __( 'The tenant identity could not be protected. Nothing was saved.', 'fraud-tenant-core' ) );
		}
		$inserted = $wpdb->insert(
			$table,
			array(
				'owner_id'             => $owner_id,
				'tenant_identity_id'   => $is_exception ? null : (int) $id['identity_id'],
				'property_id'          => $property_id,
				'category'             => $v['fields']['category'],
				'incident_date'        => $v['fields']['incident_date'],
				'amount_involved'      => $v['fields']['amount_involved'],
				'factual_description'  => $v['fields']['factual_description'],
				'owner_attestation'    => 1,
				'status'               => Constants::INCIDENT_STATUS_SUBMITTED,
				'disclosable'          => 0,
				'identity_match'       => $id['status'],
				'subject_token_hash'   => $is_exception ? $id['token'] : null,
				'subject_ssn_last4'    => $is_exception ? $id['last4'] : null,
				'subject_name_enc'     => $subject_enc,
				'screening_disclosure' => 0,
				'created_at'           => $now,
				'updated_at'           => $now,
			)
		);
		$incident_id = (int) $wpdb->insert_id;
		if ( false === $inserted || $incident_id <= 0 ) {
			$wpdb->query( 'ROLLBACK' );
			return array( 'success' => false, 'error' => 'db_insert_failed', 'message' => __( 'The report could not be saved. Please try again.', 'fraud-tenant-core' ) );
		}
		$wpdb->query( 'COMMIT' );
		$reference = sprintf( 'INC-%06d', $incident_id );

		$document_id = null;
		$warning     = '';
		if ( $ev['has_file'] ) {
			$doc = DocumentService::store_incident_document( $owner_id, $incident_id, $doc_file );
			if ( ! empty( $doc['success'] ) ) {
				$document_id = (int) $doc['document_id'];
			} else {
				$warning = ' ' . __( 'The evidence file could not be stored; you can add it from the report page.', 'fraud-tenant-core' );
			}
		}

		AuditLogger::log(
			'incident.report_submitted',
			'incident_report',
			$incident_id,
			array(
				'reference'           => $reference,
				'property_id'         => $property_id,
				'category'            => $v['fields']['category'],
				'identity_match'      => $id['status'],
				'identity_id'         => $is_exception ? null : (int) $id['identity_id'],
				'has_evidence_doc'    => (bool) $document_id,
				'attestation_version' => self::ATTESTATION_VERSION,
			),
			$owner_id
		);
		NotificationService::notify( $owner_id, __( 'Incident report submitted', 'fraud-tenant-core' ), sprintf( /* translators: %s: reference */ __( 'Report %s was received and is waiting for review. It is not shown to anyone until it is approved.', 'fraud-tenant-core' ), $reference ), home_url( '/owner-dashboard/?section=reports&report_id=' . $incident_id ), 'incident_submitted', 'reports' );
		NotificationService::notify_admins( __( 'New incident report', 'fraud-tenant-core' ), sprintf( /* translators: %s: reference */ __( 'Report %s is waiting for review.', 'fraud-tenant-core' ), $reference ) . ( $is_exception ? ' ' . __( 'Identity exception: the tenant name did not match the identity on file.', 'fraud-tenant-core' ) : '' ), home_url( '/admin-dashboard/?section=incidents&report_id=' . $incident_id ), 'incident_submitted' );

		return array(
			'success'        => true,
			'incident_id'    => $incident_id,
			'reference'      => $reference,
			'status'         => Constants::INCIDENT_STATUS_SUBMITTED,
			'identity_match' => $id['status'],
			'disclosable'    => 0,
			'document_id'    => $document_id,
			'message'        => sprintf( /* translators: %s: reference */ __( 'Report %s was submitted for review.', 'fraud-tenant-core' ), $reference ) . $warning,
		);
	}

	/**
	 * Tenant name + last four for authorized viewers of a report (owner of the report / admins).
	 *
	 * @param object $report Report row (needs tenant_identity_id, subject_name_enc, subject_ssn_last4).
	 * @return array{name: string, last4: string, linked: bool}
	 */
	public static function subject_display( object $report ): array {
		if ( ! empty( $report->tenant_identity_id ) ) {
			$identity = IdentityService::get( (int) $report->tenant_identity_id );
			if ( $identity ) {
				return array( 'name' => IdentityService::display_name( $identity ), 'last4' => (string) $identity->ssn_last_four, 'linked' => true );
			}
		}
		$n = SensitiveDataService::decrypt_array( $report->subject_name_enc ?? null, SensitiveDataService::CTX_SUBJECT_NAME );
		return array( 'name' => trim( ( $n['first'] ?? '' ) . ' ' . ( $n['last'] ?? '' ) ), 'last4' => (string) ( $report->subject_ssn_last4 ?? '' ), 'linked' => false );
	}

	/**
	 * Owner-facing label for the identity link state.
	 *
	 * @param string|null $state identity_match value.
	 * @return string
	 */
	public static function identity_state_label( ?string $state ): string {
		switch ( $state ) {
			case Constants::IDENTITY_MATCHED:
			case Constants::IDENTITY_CREATED:
			case Constants::IDENTITY_MANUAL:
				return __( 'Tenant identity confirmed', 'fraud-tenant-core' );
			case Constants::IDENTITY_EXCEPTION:
				return __( 'Tenant identity under review', 'fraud-tenant-core' );
			case Constants::IDENTITY_UNLINKED:
				return __( 'Tenant identity removed after review', 'fraud-tenant-core' );
			default:
				return __( 'No tenant identity recorded', 'fraud-tenant-core' );
		}
	}

	/**
	 * Owner's reports (strict ownership isolation).
	 *
	 * @param int   $owner_id Owner.
	 * @param array $args     limit, offset.
	 * @return array
	 */
	public static function get_owner_incident_reports( int $owner_id, array $args = array() ): array {
		global $wpdb;
		if ( $owner_id <= 0 ) {
			return array();
		}
		$limit  = max( 1, min( 100, (int) ( $args['limit'] ?? 50 ) ) );
		$offset = max( 0, (int) ( $args['offset'] ?? 0 ) );
		$r      = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;
		$p      = $wpdb->prefix . Constants::TABLE_PROPERTIES;
		$rows   = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.id, r.owner_id, r.property_id, r.tenant_identity_id, r.category, r.incident_date, r.amount_involved, r.status, r.identity_match,
				        r.subject_name_enc, r.subject_ssn_last4, r.created_at, r.updated_at,
				        p.title AS property_title, p.address_street AS property_street, p.city AS property_city, p.state AS property_state
				 FROM {$r} r LEFT JOIN {$p} p ON r.property_id = p.id
				 WHERE r.owner_id = %d ORDER BY r.id DESC LIMIT %d OFFSET %d",
				$owner_id,
				$limit,
				$offset
			)
		) ?: array();
		foreach ( $rows as $row ) {
			ReportVisibilityService::decorate( $row );
			$row->subject        = self::subject_display( $row );
			$row->identity_label = self::identity_state_label( $row->identity_match );
			unset( $row->subject_name_enc );
		}
		return $rows;
	}

	/**
	 * Single report for its owner or a reviewer (null when not authorized — IDOR safe).
	 *
	 * @param int $report_id          Report.
	 * @param int $requesting_user_id Viewer.
	 * @return object|null
	 */
	public static function get_incident_report( int $report_id, int $requesting_user_id ): ?object {
		global $wpdb;
		if ( $report_id <= 0 || ! self::can_view_incident( $requesting_user_id, $report_id ) ) {
			return null;
		}
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT r.*, p.title AS property_title, p.address_street AS property_street, p.unit_number AS property_unit,
				        p.city AS property_city, p.state AS property_state, p.postal_code AS property_zip
				 FROM {$wpdb->prefix}" . Constants::TABLE_INCIDENT_REPORTS . " r LEFT JOIN {$wpdb->prefix}" . Constants::TABLE_PROPERTIES . ' p ON r.property_id = p.id
				 WHERE r.id = %d',
				$report_id
			)
		);
		if ( ! $row ) {
			return null;
		}
		ReportVisibilityService::decorate( $row );
		$row->subject        = self::subject_display( $row );
		$row->identity_label = self::identity_state_label( $row->identity_match );
		unset( $row->subject_token_hash, $row->subject_name_enc );

		$docs = $wpdb->get_results( $wpdb->prepare( "SELECT id, original_name, file_size, mime_type, uploaded_at FROM {$wpdb->prefix}" . Constants::TABLE_DOCUMENTS . " WHERE object_type = 'incident_evidence' AND object_id = %d ORDER BY id ASC", $report_id ) ) ?: array();
		foreach ( $docs as $doc ) {
			$doc->view_url = add_query_arg( array( 'action' => 'ft_view_document', 'document_id' => (int) $doc->id, '_wpnonce' => wp_create_nonce( 'ft_view_doc_' . (int) $doc->id ) ), admin_url( 'admin-post.php' ) );
		}
		$row->evidence_docs     = $docs;
		$row->evidence_document = $docs ? end( $docs ) : null;
		$row->review_feedback   = $wpdb->get_results( $wpdb->prepare( "SELECT decision, new_status, feedback_to_owner, created_at FROM {$wpdb->prefix}" . Constants::TABLE_REPORT_REVIEWS . ' WHERE report_id = %d ORDER BY id ASC', $report_id ) ) ?: array();
		$dispute                = $wpdb->get_row( $wpdb->prepare( "SELECT status, resolution_action FROM {$wpdb->prefix}" . Constants::TABLE_TENANT_DISPUTES . ' WHERE report_id = %d ORDER BY id DESC LIMIT 1', $report_id ) );
		$row->dispute_label     = ReportVisibilityService::dispute_label( (string) ( $dispute->status ?? '' ), (string) ( $dispute->resolution_action ?? '' ) );
		return $row;
	}

	/**
	 * Verified, active properties eligible for incident reporting.
	 *
	 * @param int $owner_id Owner.
	 * @return array
	 */
	public static function get_eligible_properties( int $owner_id ): array {
		$out = array();
		foreach ( PropertyService::get_owner_properties( $owner_id, false ) as $prop ) {
			if ( empty( $prop->archived_at ) && Constants::PROP_STATUS_VERIFIED === $prop->status ) {
				$out[] = $prop;
			}
		}
		return $out;
	}

	/**
	 * Owner response to a clarification request: corrected facts / tenant identity / evidence.
	 * Returns the report to "submitted" for another review.
	 *
	 * @param int        $report_id Report.
	 * @param int        $owner_id  Owner.
	 * @param array      $data      factual_description, amount_involved, incident_date, response_note,
	 *                              optional tenant_first_name / tenant_last_name / tenant_ssn.
	 * @param array|null $file      Optional additional evidence.
	 * @return array
	 */
	public static function respond_to_clarification( int $report_id, int $owner_id, array $data, ?array $file = null ): array {
		global $wpdb;
		if ( ! ComplianceService::is_data_gate_open() ) {
			return array( 'success' => false, 'error' => 'submission_locked_blk08', 'message' => __( 'Incident reporting is unavailable until tenant data protection is configured.', 'fraud-tenant-core' ) );
		}
		$table  = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;
		$report = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND owner_id = %d", $report_id, $owner_id ) );
		if ( ! $report ) {
			return array( 'success' => false, 'error' => 'not_found', 'message' => __( 'Report not found.', 'fraud-tenant-core' ) );
		}
		if ( Constants::INCIDENT_STATUS_NEEDS_CLARIFICATION !== $report->status ) {
			return array( 'success' => false, 'error' => 'invalid_status', 'message' => __( 'Only reports that need clarification can be updated.', 'fraud-tenant-core' ) );
		}
		$v = self::validate_fields( $data, true );
		if ( ! $v['ok'] ) {
			return array( 'success' => false, 'error' => $v['error'], 'message' => $v['message'] );
		}
		$note = trim( sanitize_textarea_field( wp_unslash( (string) ( $data['response_note'] ?? '' ) ) ) );
		if ( mb_strlen( $note ) < 5 ) {
			return array( 'success' => false, 'error' => 'response_required', 'message' => __( 'Explain what you clarified or corrected (at least 5 characters).', 'fraud-tenant-core' ) );
		}
		$ev = self::precheck_evidence( $file );
		if ( ! $ev['ok'] ) {
			return array( 'success' => false, 'error' => $ev['error'], 'message' => $ev['message'] );
		}
		$update = $v['fields'];
		$first  = IdentityService::clean_name( $data['tenant_first_name'] ?? '' );
		$last   = IdentityService::clean_name( $data['tenant_last_name'] ?? '' );
		$ssn    = trim( (string) wp_unslash( (string) ( $data['tenant_ssn'] ?? '' ) ) );
		$now    = current_time( 'mysql', true );

		$wpdb->query( 'START TRANSACTION' );
		if ( '' !== $first || '' !== $last || '' !== $ssn ) {
			if ( ! empty( $report->tenant_identity_id ) && Constants::IDENTITY_EXCEPTION !== $report->identity_match ) {
				$wpdb->query( 'ROLLBACK' );
				return array( 'success' => false, 'error' => 'identity_locked', 'message' => __( 'The tenant identity on this report is already confirmed. Contact support if it is wrong.', 'fraud-tenant-core' ) );
			}
			$id = IdentityService::resolve( $first, $last, $ssn );
			if ( ! $id['ok'] ) {
				$wpdb->query( 'ROLLBACK' );
				return array( 'success' => false, 'error' => $id['error'], 'message' => $id['message'] );
			}
			$is_exception                   = Constants::IDENTITY_EXCEPTION === $id['status'];
			$update['tenant_identity_id']   = $is_exception ? null : (int) $id['identity_id'];
			$update['identity_match']       = $id['status'];
			$update['subject_token_hash']   = $is_exception ? $id['token'] : null;
			$update['subject_ssn_last4']    = $is_exception ? $id['last4'] : null;
			$update['subject_name_enc']     = $is_exception ? SensitiveDataService::encrypt_array( array( 'first' => $first, 'last' => $last ), SensitiveDataService::CTX_SUBJECT_NAME ) : null;
		}
		unset( $ssn );
		$update['status']     = Constants::INCIDENT_STATUS_SUBMITTED;
		$update['updated_at'] = $now;
		$wpdb->update( $table, $update, array( 'id' => $report_id, 'status' => Constants::INCIDENT_STATUS_NEEDS_CLARIFICATION ) );
		$wpdb->insert(
			$wpdb->prefix . Constants::TABLE_REPORT_REVIEWS,
			array(
				'report_id'         => $report_id,
				'reviewer_id'       => $owner_id,
				'decision'          => 'owner_response',
				'previous_status'   => Constants::INCIDENT_STATUS_NEEDS_CLARIFICATION,
				'new_status'        => Constants::INCIDENT_STATUS_SUBMITTED,
				'reason_code'       => 'owner_clarification',
				'internal_notes'    => $note,
				'feedback_to_owner' => null,
				'created_at'        => $now,
			)
		);
		$wpdb->query( 'COMMIT' );

		if ( $ev['has_file'] ) {
			DocumentService::store_incident_document( $owner_id, $report_id, $file );
		}
		AuditLogger::log( 'incident.clarification_submitted', 'incident_report', $report_id, array( 'fields' => array_keys( $v['fields'] ), 'identity_updated' => isset( $update['identity_match'] ) ), $owner_id );
		NotificationService::notify_admins( __( 'Clarification received', 'fraud-tenant-core' ), sprintf( /* translators: %s: reference */ __( 'The owner responded to the clarification request on %s.', 'fraud-tenant-core' ), sprintf( 'INC-%06d', $report_id ) ), home_url( '/admin-dashboard/?section=incidents&report_id=' . $report_id ), 'incident_clarified' );
		return array( 'success' => true, 'message' => __( 'Your clarification was submitted. The report is back in the review queue.', 'fraud-tenant-core' ) );
	}

	/**
	 * Administrator correction of a report's factual fields — a separately recorded operation
	 * (e.g. after a dispute determined a correction is warranted). Previous values are kept in the
	 * review history (admin-only); the audit log records which fields changed, never the narrative.
	 *
	 * @param int    $report_id  Report.
	 * @param int    $admin_id   Administrator.
	 * @param array  $fields     category, incident_date, amount_involved, factual_description.
	 * @param string $reason     Reason shown to the owner (required).
	 * @param int    $dispute_id Related dispute (optional).
	 * @return array
	 */
	public static function admin_correct_report( int $report_id, int $admin_id, array $fields, string $reason, int $dispute_id = 0 ): array {
		global $wpdb;
		if ( ! IncidentReviewService::can_decide_incidents( $admin_id ) ) {
			return array( 'success' => false, 'error' => 'unauthorized', 'message' => __( 'You are not authorized to correct incident reports.', 'fraud-tenant-core' ) );
		}
		if ( ! ComplianceService::is_data_gate_open() ) {
			return array( 'success' => false, 'error' => 'locked_blk08', 'message' => __( 'Report corrections are unavailable until tenant data protection is configured.', 'fraud-tenant-core' ) );
		}
		$reason = trim( sanitize_textarea_field( $reason ) );
		if ( mb_strlen( $reason ) < 5 ) {
			return array( 'success' => false, 'error' => 'reason_required', 'message' => __( 'Enter the reason for the correction (at least 5 characters).', 'fraud-tenant-core' ) );
		}
		$table  = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;
		$report = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $report_id ) );
		if ( ! $report ) {
			return array( 'success' => false, 'error' => 'not_found', 'message' => __( 'Report not found.', 'fraud-tenant-core' ) );
		}
		if ( (int) $report->owner_id === $admin_id ) {
			return array( 'success' => false, 'error' => 'self_review_forbidden', 'message' => __( 'You cannot correct your own report.', 'fraud-tenant-core' ) );
		}
		$v = self::validate_fields( $fields, true );
		if ( ! $v['ok'] ) {
			return array( 'success' => false, 'error' => $v['error'], 'message' => $v['message'] );
		}
		$changes = array();
		foreach ( $v['fields'] as $k => $new ) {
			$old = $report->{$k};
			if ( 'amount_involved' === $k ? round( (float) $old, 2 ) !== (float) $new : (string) $old !== (string) $new ) {
				$changes[ $k ] = array( 'from' => $old, 'to' => $new );
			}
		}
		if ( ! $changes ) {
			return array( 'success' => false, 'error' => 'no_changes', 'message' => __( 'No field was changed.', 'fraud-tenant-core' ) );
		}
		$now  = current_time( 'mysql', true );
		$data = array_map( static fn( $c ) => $c['to'], $changes );
		$data['updated_at'] = $now;
		$wpdb->query( 'START TRANSACTION' );
		$wpdb->update( $table, $data, array( 'id' => $report_id ) );
		$wpdb->insert(
			$wpdb->prefix . Constants::TABLE_REPORT_REVIEWS,
			array(
				'report_id'         => $report_id,
				'reviewer_id'       => $admin_id,
				'decision'          => 'corrected',
				'previous_status'   => $report->status,
				'new_status'        => $report->status,
				'reason_code'       => $dispute_id > 0 ? 'dispute_correction' : 'admin_correction',
				'internal_notes'    => (string) wp_json_encode( array( 'dispute_id' => $dispute_id ?: null, 'changes' => $changes ) ),
				'feedback_to_owner' => $reason,
				'created_at'        => $now,
			)
		);
		$identity = IdentityService::get( (int) $report->tenant_identity_id );
		ReportVisibilityService::record_history( $report_id, 'report_corrected', $admin_id, $reason, (int) $report->tenant_identity_id ?: null, $identity ? (int) $identity->user_id : null );
		$wpdb->query( 'COMMIT' );
		AuditLogger::log( 'incident.report_corrected', 'incident_report', $report_id, array( 'fields' => array_keys( $changes ), 'dispute_id' => $dispute_id ?: null ), $admin_id );

		$ref = sprintf( 'INC-%06d', $report_id );
		NotificationService::notify( (int) $report->owner_id, __( 'Report corrected after review', 'fraud-tenant-core' ), sprintf( /* translators: %s: reference */ __( 'An administrator corrected report %s after review.', 'fraud-tenant-core' ), $ref ), home_url( '/owner-dashboard/?section=reports&report_id=' . $report_id ), 'incident_corrected', 'reports' );
		if ( $identity && ! empty( $identity->user_id ) && ReportVisibilityService::tenant_can_view( $report_id, (int) $identity->user_id ) ) {
			NotificationService::notify( (int) $identity->user_id, __( 'A record on your account was corrected', 'fraud-tenant-core' ), sprintf( /* translators: %s: reference */ __( 'Report %s was corrected after review.', 'fraud-tenant-core' ), $ref ), home_url( '/tenant-dashboard/?section=records&report_id=' . $report_id ), 'record_corrected', 'reports' );
		}
		return array( 'success' => true, 'message' => __( 'The report was corrected and the change was recorded in its history.', 'fraud-tenant-core' ) );
	}
}
