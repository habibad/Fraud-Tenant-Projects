<?php
/**
 * Central Tenant Identity service — the authoritative relationship between incident reports,
 * tenant identities and tenant accounts.
 *
 * Model
 * - One account-independent identity per SSN: wp_ft_tenant_identities (stable internal id,
 *   token_hash = HMAC-SHA256(FT_MATCH_SECRET_KEY, 9-digit SSN) UNIQUE, ssn_last_four,
 *   name_enc = AES-256-GCM legal name). user_id is set only after a verified claim.
 * - Reports link through wp_ft_incident_reports.tenant_identity_id; identity_match records how.
 * - A tenant account reaches its reports through identity.user_id — no per-report association.
 *
 * Deterministic matching policy (documented in IMPLEMENTATION_PLAN.md):
 * 1. The SSN is normalized (9 ASCII digits) and must be structurally valid and plausible
 *    (no 000/666/9xx area, 00 group or 0000 serial).
 * 2. token = HMAC-SHA256(FT_MATCH_SECRET_KEY, normalized SSN). Plain SHA-256 is never used.
 * 3. Lookup is by token only (unique index). A name-only lookup never identifies anyone.
 * 4. No identity for the token → a new unclaimed identity is created (race-safe INSERT IGNORE).
 * 5. Identity found → the normalized first AND last names (case/space/punctuation/accent
 *    insensitive) must both equal the stored legal name → "matched".
 *    Any difference → "exception": the report is NOT linked; it waits for administrator review.
 * Raw SSNs are never stored, logged, returned or placed in URLs.
 *
 * @package FraudTenant\Services
 */

namespace FraudTenant\Services;

use FraudTenant\Auth\Capabilities;
use FraudTenant\Utils\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IdentityService {

	public const NAME_MAX = 100;

	// ------------------------------------------------------------------ validation

	/**
	 * Normalize and validate an SSN. Returns 9 digits, or null when malformed / implausible.
	 *
	 * @param string $ssn Raw input (123-45-6789 or 123456789).
	 * @return string|null
	 */
	public static function normalize_ssn( string $ssn ): ?string {
		$ssn = trim( $ssn );
		if ( ! SecurityService::validate_ssn_format( $ssn ) ) {
			return null;
		}
		$d      = str_replace( '-', '', $ssn );
		$area   = (int) substr( $d, 0, 3 );
		$group  = (int) substr( $d, 3, 2 );
		$serial = (int) substr( $d, 5, 4 );
		if ( $area <= 0 || 666 === $area || $area >= 900 || $group <= 0 || $serial <= 0 ) {
			return null;
		}
		return $d;
	}

	/**
	 * Validate a legal name pair.
	 *
	 * @param string $first First name.
	 * @param string $last  Last name.
	 * @return bool
	 */
	public static function valid_name( string $first, string $last ): bool {
		$f = SensitiveDataService::normalize_name( $first );
		$l = SensitiveDataService::normalize_name( $last );
		return '' !== $f && strlen( $l ) >= 2 && mb_strlen( $first ) <= self::NAME_MAX && mb_strlen( $last ) <= self::NAME_MAX;
	}

	/**
	 * Clean a submitted name fragment for storage.
	 *
	 * @param mixed $v Value.
	 * @return string
	 */
	public static function clean_name( $v ): string {
		return trim( preg_replace( '/\s+/', ' ', sanitize_text_field( wp_unslash( (string) $v ) ) ) );
	}

	/**
	 * Names match under the deterministic policy (normalized first AND last).
	 *
	 * @param array  $stored Stored name {first,last}.
	 * @param string $first  Candidate first name.
	 * @param string $last   Candidate last name.
	 * @return bool
	 */
	public static function names_match( array $stored, string $first, string $last ): bool {
		$sf = SensitiveDataService::normalize_name( (string) ( $stored['first'] ?? '' ) );
		$sl = SensitiveDataService::normalize_name( (string) ( $stored['last'] ?? '' ) );
		if ( '' === $sf || '' === $sl ) {
			return false;
		}
		return hash_equals( $sf, SensitiveDataService::normalize_name( $first ) ) && hash_equals( $sl, SensitiveDataService::normalize_name( $last ) );
	}

	// ------------------------------------------------------------------ lookup

	/**
	 * Identity table name.
	 *
	 * @return string
	 */
	protected static function table(): string {
		global $wpdb;
		return $wpdb->prefix . Constants::TABLE_TENANT_IDENTITIES;
	}

	/**
	 * Get an identity by id.
	 *
	 * @param int $id Identity ID.
	 * @return object|null
	 */
	public static function get( int $id ): ?object {
		global $wpdb;
		if ( $id <= 0 ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ) ) ?: null;
	}

	/**
	 * Get an identity by match token.
	 *
	 * @param string $token HMAC token.
	 * @return object|null
	 */
	public static function get_by_token( string $token ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE token_hash = %s LIMIT 1', $token ) ) ?: null;
	}

	/**
	 * Identity claimed by a tenant account (at most one).
	 *
	 * @param int $user_id User.
	 * @return object|null
	 */
	public static function get_for_user( int $user_id ): ?object {
		global $wpdb;
		if ( $user_id <= 0 ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE user_id = %d ORDER BY id ASC LIMIT 1', $user_id ) ) ?: null;
	}

	/**
	 * Decrypted legal name {first,last}; legacy plaintext columns are used only when name_enc is empty.
	 *
	 * @param object $identity Identity row.
	 * @return array{first: string, last: string}
	 */
	public static function name( object $identity ): array {
		$n = SensitiveDataService::decrypt_array( $identity->name_enc ?? null, SensitiveDataService::CTX_IDENTITY_NAME );
		if ( ! empty( $n['last'] ) ) {
			return array( 'first' => (string) ( $n['first'] ?? '' ), 'last' => (string) $n['last'] );
		}
		return array( 'first' => (string) ( $identity->first_name ?? '' ), 'last' => (string) ( $identity->last_name ?? '' ) );
	}

	/**
	 * Full display name for authorized viewers.
	 *
	 * @param object $identity Identity row.
	 * @return string
	 */
	public static function display_name( object $identity ): string {
		$n = self::name( $identity );
		return trim( $n['first'] . ' ' . $n['last'] );
	}

	/**
	 * Legacy placeholder identities (created by the pre-1.9 manual association without an SSN).
	 *
	 * @param object $identity Identity row.
	 * @return bool
	 */
	public static function is_placeholder( object $identity ): bool {
		return ! preg_match( '/^[0-9]{4}$/', (string) $identity->ssn_last_four );
	}

	/**
	 * Masked name for list views ("J. S•••").
	 *
	 * @param object $identity Identity row.
	 * @return string
	 */
	public static function masked_name( object $identity ): string {
		$n = self::name( $identity );
		if ( '' === $n['last'] ) {
			return '—';
		}
		return mb_substr( $n['first'], 0, 1 ) . '. ' . mb_substr( $n['last'], 0, 1 ) . '•••';
	}

	// ------------------------------------------------------------------ creation & resolution

	/**
	 * Create an identity (or return the existing one for the token — race-safe).
	 *
	 * @param string   $token   Match token.
	 * @param string   $last4   SSN last four.
	 * @param string   $first   Legal first name.
	 * @param string   $last    Legal last name.
	 * @param int|null $user_id Claimed account (claims only).
	 * @return array{ok: bool, id?: int, created?: bool, error?: string}
	 */
	public static function create_or_get( string $token, string $last4, string $first, string $last, ?int $user_id = null ): array {
		global $wpdb;
		$enc = SensitiveDataService::encrypt_array( array( 'first' => $first, 'last' => $last ), SensitiveDataService::CTX_IDENTITY_NAME );
		if ( null === $enc ) {
			return array( 'ok' => false, 'error' => 'encryption_unavailable' );
		}
		$now = current_time( 'mysql', true );
		// INSERT IGNORE + unique token_hash: concurrent submissions for the same SSN never duplicate.
		$cols = 'INSERT IGNORE INTO ' . self::table() . ' (user_id, token_hash, ssn_last_four, first_name, last_name, name_enc, claimed_at, created_at, updated_at) ';
		if ( null === $user_id ) {
			$sql = $wpdb->prepare( $cols . "VALUES (NULL, %s, %s, '', '', %s, NULL, %s, %s)", $token, $last4, $enc, $now, $now );
		} else {
			$sql = $wpdb->prepare( $cols . "VALUES (%d, %s, %s, '', '', %s, %s, %s, %s)", $user_id, $token, $last4, $enc, $now, $now, $now );
		}
		$wpdb->query( $sql );
		$created = (int) $wpdb->rows_affected > 0;
		$row     = self::get_by_token( $token );
		if ( ! $row ) {
			return array( 'ok' => false, 'error' => 'identity_write_failed' );
		}
		if ( $created ) {
			AuditLogger::log( 'identity.created', 'tenant_identity', (int) $row->id, array( 'claimed' => null !== $user_id ) );
		}
		return array( 'ok' => true, 'id' => (int) $row->id, 'created' => $created );
	}

	/**
	 * Apply the deterministic matching policy for a submitted (name, SSN).
	 *
	 * @param string $first Legal first name.
	 * @param string $last  Legal last name.
	 * @param string $ssn   SSN input.
	 * @return array{ok: bool, error?: string, message?: string, status?: string, identity_id?: int|null, candidate_id?: int|null, token?: string, last4?: string}
	 */
	public static function resolve( string $first, string $last, string $ssn ): array {
		if ( ! ComplianceService::identity_ready() ) {
			return array( 'ok' => false, 'error' => 'identity_matching_unavailable', 'message' => __( 'Tenant identity protection is not configured on this platform yet. Please contact support.', 'fraud-tenant-core' ) );
		}
		if ( ! self::valid_name( $first, $last ) ) {
			return array( 'ok' => false, 'error' => 'invalid_tenant_name', 'message' => __( 'Enter the tenant\'s legal first and last name as shown on the rental application or lease.', 'fraud-tenant-core' ) );
		}
		$digits = self::normalize_ssn( $ssn );
		if ( null === $digits ) {
			return array( 'ok' => false, 'error' => 'invalid_tenant_ssn', 'message' => __( 'Enter a valid 9-digit Social Security Number (format 123-45-6789).', 'fraud-tenant-core' ) );
		}
		$token = SecurityService::generate_ssn_match_token( $digits );
		if ( false === $token ) {
			return array( 'ok' => false, 'error' => 'identity_matching_unavailable', 'message' => __( 'Identity matching is unavailable.', 'fraud-tenant-core' ) );
		}
		$last4    = substr( $digits, -4 );
		$existing = self::get_by_token( $token );
		if ( ! $existing ) {
			$c = self::create_or_get( $token, $last4, $first, $last );
			if ( ! $c['ok'] ) {
				return array( 'ok' => false, 'error' => 'identity_protection_failed', 'message' => __( 'The tenant identity could not be protected. Nothing was saved.', 'fraud-tenant-core' ) );
			}
			if ( $c['created'] ) {
				return array( 'ok' => true, 'status' => Constants::IDENTITY_CREATED, 'identity_id' => $c['id'], 'candidate_id' => null, 'token' => $token, 'last4' => $last4 );
			}
			$existing = self::get( $c['id'] ); // Lost a creation race: evaluate the winner's record.
		}
		if ( self::names_match( self::name( $existing ), $first, $last ) ) {
			return array( 'ok' => true, 'status' => Constants::IDENTITY_MATCHED, 'identity_id' => (int) $existing->id, 'candidate_id' => null, 'token' => $token, 'last4' => $last4 );
		}
		return array( 'ok' => true, 'status' => Constants::IDENTITY_EXCEPTION, 'identity_id' => null, 'candidate_id' => (int) $existing->id, 'token' => $token, 'last4' => $last4 );
	}

	/**
	 * Find an identity for a screening search WITHOUT creating anything.
	 * Returns the identity only when token AND names match; otherwise null (no distinction is
	 * exposed to the caller, so a search cannot be used to learn whether an SSN is on file).
	 *
	 * @param string $first First name.
	 * @param string $last  Last name.
	 * @param string $ssn   SSN.
	 * @return object|null
	 */
	public static function find_exact( string $first, string $last, string $ssn ): ?object {
		$digits = self::normalize_ssn( $ssn );
		if ( null === $digits || ! self::valid_name( $first, $last ) ) {
			return null;
		}
		$token = SecurityService::generate_ssn_match_token( $digits );
		if ( false === $token ) {
			return null;
		}
		$identity = self::get_by_token( $token );
		return ( $identity && self::names_match( self::name( $identity ), $first, $last ) ) ? $identity : null;
	}

	// ------------------------------------------------------------------ admin: report links

	/**
	 * Whether a user may manage identities, exceptions and claims.
	 *
	 * @param int $user_id User.
	 * @return bool
	 */
	public static function can_manage( int $user_id ): bool {
		return $user_id > 0 && ( user_can( $user_id, Capabilities::MANAGE_TENANT_IDENTITIES ) || user_can( $user_id, 'manage_options' ) );
	}

	/**
	 * Link (or re-link) a report to an identity (administrator exception handling).
	 * Disclosure flags are reset so the new subject is never shown anything without a fresh decision.
	 *
	 * @param int    $report_id   Report.
	 * @param int    $identity_id Identity.
	 * @param int    $admin_id    Administrator.
	 * @param string $reason      Reason (required).
	 * @return array{success: bool, error?: string, message: string}
	 */
	public static function link_report( int $report_id, int $identity_id, int $admin_id, string $reason ): array {
		global $wpdb;
		if ( ! self::can_manage( $admin_id ) ) {
			return array( 'success' => false, 'error' => 'unauthorized', 'message' => __( 'You are not authorized to manage tenant identities.', 'fraud-tenant-core' ) );
		}
		if ( ! ComplianceService::identity_ready() ) {
			return array( 'success' => false, 'error' => 'identity_matching_unavailable', 'message' => __( 'Tenant identity protection is not configured.', 'fraud-tenant-core' ) );
		}
		$reason = trim( sanitize_textarea_field( $reason ) );
		if ( mb_strlen( $reason ) < 5 ) {
			return array( 'success' => false, 'error' => 'reason_required', 'message' => __( 'Enter a reason for the identity link (at least 5 characters).', 'fraud-tenant-core' ) );
		}
		$identity = self::get( $identity_id );
		if ( ! $identity || self::is_placeholder( $identity ) ) {
			return array( 'success' => false, 'error' => 'invalid_identity', 'message' => __( 'Select a valid tenant identity (legacy placeholder records cannot be linked).', 'fraud-tenant-core' ) );
		}
		$rep_table = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;
		$wpdb->query( 'START TRANSACTION' );
		$report = $wpdb->get_row( $wpdb->prepare( "SELECT id, owner_id, status, tenant_identity_id FROM {$rep_table} WHERE id = %d FOR UPDATE", $report_id ) );
		if ( ! $report ) {
			$wpdb->query( 'ROLLBACK' );
			return array( 'success' => false, 'error' => 'report_not_found', 'message' => __( 'Incident report not found.', 'fraud-tenant-core' ) );
		}
		if ( (int) $report->tenant_identity_id === $identity_id ) {
			$wpdb->query( 'ROLLBACK' );
			return array( 'success' => false, 'error' => 'already_linked', 'message' => __( 'The report is already linked to this identity.', 'fraud-tenant-core' ) );
		}
		$previous = (int) $report->tenant_identity_id;
		$wpdb->update(
			$rep_table,
			array(
				'tenant_identity_id'   => $identity_id,
				'identity_match'       => Constants::IDENTITY_MANUAL,
				'subject_token_hash'   => null,
				'subject_ssn_last4'    => null,
				'subject_name_enc'     => null,
				'disclosable'          => 0,
				'screening_disclosure' => 0,
				'updated_at'           => current_time( 'mysql', true ),
			),
			array( 'id' => $report_id )
		);
		ReportVisibilityService::record_history( $report_id, $previous ? 'identity_relinked' : 'identity_linked', $admin_id, $reason, $identity_id, (int) $identity->user_id, 'none', 'hidden' );
		$wpdb->query( 'COMMIT' );
		AuditLogger::log( 'report.identity_linked', 'incident_report', $report_id, array( 'identity_id' => $identity_id, 'previous_identity_id' => $previous ?: null ), $admin_id );
		if ( $previous ) {
			self::notify_record_removed( $previous, $report_id );
		}
		return array( 'success' => true, 'message' => __( 'The report is now linked to the selected tenant identity. Review the disclosure settings before releasing it.', 'fraud-tenant-core' ) );
	}

	/**
	 * Remove a mistaken identity link. The report, its evidence and the tenant's own dispute history
	 * are preserved; the previously linked tenant immediately loses access to the report.
	 *
	 * @param int    $report_id Report.
	 * @param int    $admin_id  Administrator.
	 * @param string $reason    Reason (required).
	 * @return array{success: bool, error?: string, message: string}
	 */
	public static function unlink_report( int $report_id, int $admin_id, string $reason ): array {
		global $wpdb;
		if ( ! self::can_manage( $admin_id ) ) {
			return array( 'success' => false, 'error' => 'unauthorized', 'message' => __( 'You are not authorized to manage tenant identities.', 'fraud-tenant-core' ) );
		}
		$reason = trim( sanitize_textarea_field( $reason ) );
		if ( mb_strlen( $reason ) < 5 ) {
			return array( 'success' => false, 'error' => 'reason_required', 'message' => __( 'Enter a reason for removing the identity link (at least 5 characters).', 'fraud-tenant-core' ) );
		}
		$rep_table = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;
		$wpdb->query( 'START TRANSACTION' );
		$report = $wpdb->get_row( $wpdb->prepare( "SELECT id, tenant_identity_id FROM {$rep_table} WHERE id = %d FOR UPDATE", $report_id ) );
		if ( ! $report || empty( $report->tenant_identity_id ) ) {
			$wpdb->query( 'ROLLBACK' );
			return array( 'success' => false, 'error' => 'not_linked', 'message' => __( 'The report is not linked to a tenant identity.', 'fraud-tenant-core' ) );
		}
		$previous = (int) $report->tenant_identity_id;
		$identity = self::get( $previous );
		$wpdb->update(
			$rep_table,
			array(
				'tenant_identity_id'   => null,
				'identity_match'       => Constants::IDENTITY_UNLINKED,
				'disclosable'          => 0,
				'screening_disclosure' => 0,
				'updated_at'           => current_time( 'mysql', true ),
			),
			array( 'id' => $report_id )
		);
		ReportVisibilityService::record_history( $report_id, 'identity_unlinked', $admin_id, $reason, $previous, $identity ? (int) $identity->user_id : null, 'visible', 'revoked' );
		$wpdb->query( 'COMMIT' );
		AuditLogger::log( 'report.identity_unlinked', 'incident_report', $report_id, array( 'previous_identity_id' => $previous ), $admin_id );
		self::notify_record_removed( $previous, $report_id );
		return array( 'success' => true, 'message' => __( 'The identity link was removed. The previously linked tenant can no longer see this report.', 'fraud-tenant-core' ) );
	}

	/**
	 * Resolve an identity match exception: confirm the candidate identity, or ask the owner to
	 * correct the tenant details (needs clarification).
	 *
	 * @param int    $report_id Report in exception state.
	 * @param string $action    'confirm' | 'clarify'.
	 * @param int    $admin_id  Administrator.
	 * @param string $note      Reason / note to the owner.
	 * @return array{success: bool, error?: string, message: string}
	 */
	public static function resolve_exception( int $report_id, string $action, int $admin_id, string $note ): array {
		global $wpdb;
		if ( ! self::can_manage( $admin_id ) ) {
			return array( 'success' => false, 'error' => 'unauthorized', 'message' => __( 'You are not authorized to manage tenant identities.', 'fraud-tenant-core' ) );
		}
		$report = $wpdb->get_row( $wpdb->prepare( "SELECT id, owner_id, status, identity_match, subject_token_hash FROM {$wpdb->prefix}" . Constants::TABLE_INCIDENT_REPORTS . ' WHERE id = %d', $report_id ) );
		if ( ! $report || Constants::IDENTITY_EXCEPTION !== $report->identity_match || empty( $report->subject_token_hash ) ) {
			return array( 'success' => false, 'error' => 'not_exception', 'message' => __( 'This report has no open identity exception.', 'fraud-tenant-core' ) );
		}
		if ( 'confirm' === $action ) {
			$candidate = self::get_by_token( (string) $report->subject_token_hash );
			if ( ! $candidate ) {
				return array( 'success' => false, 'error' => 'candidate_missing', 'message' => __( 'The matching identity record no longer exists.', 'fraud-tenant-core' ) );
			}
			return self::link_report( $report_id, (int) $candidate->id, $admin_id, $note );
		}
		if ( 'clarify' === $action ) {
			if ( (int) $report->owner_id === $admin_id ) {
				return array( 'success' => false, 'error' => 'self_review_forbidden', 'message' => __( 'You cannot review your own report.', 'fraud-tenant-core' ) );
			}
			$note = trim( sanitize_textarea_field( $note ) );
			if ( mb_strlen( $note ) < 5 ) {
				return array( 'success' => false, 'error' => 'reason_required', 'message' => __( 'Explain what the owner must correct (at least 5 characters).', 'fraud-tenant-core' ) );
			}
			return IncidentReviewService::request_identity_clarification( $report_id, $admin_id, $note );
		}
		return array( 'success' => false, 'error' => 'invalid_action', 'message' => __( 'Unknown exception action.', 'fraud-tenant-core' ) );
	}

	/**
	 * Administrator links a report by entering the tenant's legal name + SSN (legacy or unlinked
	 * reports). Uses the same deterministic policy; a name conflict is refused, not overridden.
	 *
	 * @param int    $report_id Report.
	 * @param string $first     First name.
	 * @param string $last      Last name.
	 * @param string $ssn       SSN.
	 * @param int    $admin_id  Administrator.
	 * @param string $reason    Reason.
	 * @return array{success: bool, error?: string, message: string}
	 */
	public static function admin_link_by_identity( int $report_id, string $first, string $last, string $ssn, int $admin_id, string $reason ): array {
		if ( ! self::can_manage( $admin_id ) ) {
			return array( 'success' => false, 'error' => 'unauthorized', 'message' => __( 'You are not authorized to manage tenant identities.', 'fraud-tenant-core' ) );
		}
		$r = self::resolve( $first, $last, $ssn );
		if ( ! $r['ok'] ) {
			return array( 'success' => false, 'error' => $r['error'], 'message' => $r['message'] );
		}
		if ( Constants::IDENTITY_EXCEPTION === $r['status'] ) {
			return array( 'success' => false, 'error' => 'name_conflict', 'message' => __( 'An identity with this SSN exists under a different legal name. Verify the documents, then link the report to that identity from Tenant Identity Management.', 'fraud-tenant-core' ) );
		}
		return self::link_report( $report_id, (int) $r['identity_id'], $admin_id, $reason );
	}

	/**
	 * Revoke a tenant account's claim on an identity (e.g. a claim later found to be mistaken).
	 *
	 * @param int    $identity_id Identity.
	 * @param int    $admin_id    Administrator.
	 * @param string $reason      Reason.
	 * @return array{success: bool, error?: string, message: string}
	 */
	public static function revoke_claim( int $identity_id, int $admin_id, string $reason ): array {
		global $wpdb;
		if ( ! self::can_manage( $admin_id ) ) {
			return array( 'success' => false, 'error' => 'unauthorized', 'message' => __( 'You are not authorized to manage tenant identities.', 'fraud-tenant-core' ) );
		}
		$reason   = trim( sanitize_textarea_field( $reason ) );
		$identity = self::get( $identity_id );
		if ( ! $identity || empty( $identity->user_id ) ) {
			return array( 'success' => false, 'error' => 'not_claimed', 'message' => __( 'This identity is not claimed by any account.', 'fraud-tenant-core' ) );
		}
		if ( mb_strlen( $reason ) < 5 ) {
			return array( 'success' => false, 'error' => 'reason_required', 'message' => __( 'Enter a reason (at least 5 characters).', 'fraud-tenant-core' ) );
		}
		$user_id = (int) $identity->user_id;
		$wpdb->update( self::table(), array( 'user_id' => null, 'claimed_at' => null, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $identity_id ) );
		$wpdb->update( $wpdb->prefix . Constants::TABLE_USER_PROFILES, array( 'identity_verified' => 0 ), array( 'user_id' => $user_id ) );
		AuditLogger::log( 'identity.claim_revoked', 'tenant_identity', $identity_id, array( 'user_id' => $user_id ), $admin_id );
		NotificationService::notify( $user_id, __( 'Identity verification removed', 'fraud-tenant-core' ), __( 'An administrator removed the identity verification on your account after review. Rental records are no longer shown in your dashboard. You can submit a new identity verification request.', 'fraud-tenant-core' ), home_url( '/tenant-dashboard/?section=identity' ), 'identity', 'account' );
		return array( 'success' => true, 'message' => __( 'The identity claim was revoked.', 'fraud-tenant-core' ) );
	}

	/**
	 * Tell the previously linked tenant (if any) that a record was removed from their account.
	 *
	 * @param int $identity_id Previous identity.
	 * @param int $report_id   Report.
	 */
	protected static function notify_record_removed( int $identity_id, int $report_id ): void {
		$identity = self::get( $identity_id );
		if ( $identity && ! empty( $identity->user_id ) ) {
			NotificationService::notify( (int) $identity->user_id, __( 'A record was removed from your account', 'fraud-tenant-core' ), sprintf( /* translators: %s: report reference */ __( 'After review, report %s is no longer associated with your identity. Your dispute history is kept.', 'fraud-tenant-core' ), sprintf( 'INC-%06d', $report_id ) ), home_url( '/tenant-dashboard/?section=records' ), 'record_removed', 'reports' );
		}
	}

	// ------------------------------------------------------------------ admin listing

	/**
	 * Paginated identity list for administrators (masked names in lists).
	 *
	 * @param array $args search, claimed (all|yes|no), page, per_page.
	 * @return array{items: array, total: int}
	 */
	public static function list_identities( array $args = array() ): array {
		global $wpdb;
		$t      = self::table();
		$r      = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;
		$where  = array( '1=1' );
		$params = array();
		$search = trim( (string) ( $args['search'] ?? '' ) );
		if ( preg_match( '/^[0-9]{4}$/', $search ) ) {
			$where[]  = 'i.ssn_last_four = %s';
			$params[] = $search;
		} elseif ( preg_match( '/^#?([0-9]+)$/', $search, $m ) ) {
			$where[]  = 'i.id = %d';
			$params[] = (int) $m[1];
		} elseif ( '' !== $search ) {
			$where[]  = '(u.user_email LIKE %s OR u.display_name LIKE %s)';
			$params[] = '%' . $wpdb->esc_like( $search ) . '%';
			$params[] = '%' . $wpdb->esc_like( $search ) . '%';
		}
		$claimed = (string) ( $args['claimed'] ?? 'all' );
		if ( 'yes' === $claimed ) {
			$where[] = 'i.user_id IS NOT NULL';
		} elseif ( 'no' === $claimed ) {
			$where[] = 'i.user_id IS NULL';
		}
		$per    = max( 1, min( 100, (int) ( $args['per_page'] ?? 25 ) ) );
		$page   = max( 1, (int) ( $args['page'] ?? 1 ) );
		$sql_w  = implode( ' AND ', $where );
		$count  = "SELECT COUNT(*) FROM {$t} i LEFT JOIN {$wpdb->users} u ON u.ID = i.user_id WHERE {$sql_w}";
		$total  = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count, $params ) ) : $wpdb->get_var( $count ) );
		$query  = "SELECT i.*, u.user_email, u.display_name AS account_name,
		                  (SELECT COUNT(*) FROM {$r} x WHERE x.tenant_identity_id = i.id) AS reports_total,
		                  (SELECT COUNT(*) FROM {$r} x WHERE x.tenant_identity_id = i.id AND x.status = 'approved') AS reports_approved
		           FROM {$t} i LEFT JOIN {$wpdb->users} u ON u.ID = i.user_id
		           WHERE {$sql_w} ORDER BY i.id DESC LIMIT %d OFFSET %d";
		$rows   = $wpdb->get_results( $wpdb->prepare( $query, array_merge( $params, array( $per, ( $page - 1 ) * $per ) ) ) ) ?: array();
		return array( 'items' => $rows, 'total' => $total );
	}

	/**
	 * Reports linked to an identity (admin view).
	 *
	 * @param int $identity_id Identity.
	 * @return array
	 */
	public static function get_reports( int $identity_id ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( "SELECT id, owner_id, category, incident_date, amount_involved, status, disclosable, screening_disclosure, identity_match, created_at FROM {$wpdb->prefix}" . Constants::TABLE_INCIDENT_REPORTS . ' WHERE tenant_identity_id = %d ORDER BY id DESC', $identity_id ) ) ?: array();
	}

	/**
	 * Reports waiting on identity exception review.
	 *
	 * @return array
	 */
	public static function get_exceptions(): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT r.id, r.owner_id, r.category, r.status, r.subject_token_hash, r.subject_ssn_last4, r.subject_name_enc, r.created_at, u.display_name AS owner_name FROM {$wpdb->prefix}" . Constants::TABLE_INCIDENT_REPORTS . " r LEFT JOIN {$wpdb->users} u ON u.ID = r.owner_id WHERE r.identity_match = %s ORDER BY r.id ASC", Constants::IDENTITY_EXCEPTION ) ) ?: array();
		foreach ( $rows as $row ) {
			$row->reported_name = SensitiveDataService::decrypt_array( $row->subject_name_enc, SensitiveDataService::CTX_SUBJECT_NAME );
			$row->candidate     = $row->subject_token_hash ? self::get_by_token( (string) $row->subject_token_hash ) : null;
			unset( $row->subject_token_hash, $row->subject_name_enc );
		}
		return $rows;
	}

	/**
	 * Protect legacy plaintext identity names: encrypt into name_enc, verify, then blank plaintext.
	 * Runs only when encryption is available; safe to repeat.
	 *
	 * @return array{protected: int, failed: int}
	 */
	public static function protect_legacy_names(): array {
		global $wpdb;
		$out = array( 'protected' => 0, 'failed' => 0 );
		if ( ! SensitiveDataService::is_available() ) {
			return $out;
		}
		$rows = $wpdb->get_results( 'SELECT id, first_name, last_name FROM ' . self::table() . " WHERE (name_enc IS NULL OR name_enc = '') AND (first_name <> '' OR last_name <> '')" ) ?: array();
		foreach ( $rows as $row ) {
			$plain = array( 'first' => (string) $row->first_name, 'last' => (string) $row->last_name );
			$enc   = SensitiveDataService::encrypt_array( $plain, SensitiveDataService::CTX_IDENTITY_NAME );
			if ( null === $enc || SensitiveDataService::decrypt_array( $enc, SensitiveDataService::CTX_IDENTITY_NAME ) !== $plain ) {
				$out['failed']++;
				continue;
			}
			$wpdb->update( self::table(), array( 'name_enc' => $enc, 'first_name' => '', 'last_name' => '' ), array( 'id' => (int) $row->id ) );
			$out['protected']++;
		}
		if ( $out['protected'] || $out['failed'] ) {
			AuditLogger::log( 'security.legacy_identity_names_protected', 'system', null, $out );
		}
		return $out;
	}
}
