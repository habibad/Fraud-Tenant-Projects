<?php
/**
 * Owner tenant screening against the central tenant identity.
 *
 * Search (owner, verified property, permissible purpose, applicant's written consent):
 *   name + SSN → IdentityService::find_exact() (SSN token AND legal name must both match).
 *   A non-matching name, an unknown SSN and "no records" all produce the same response, so the
 *   search cannot be used to learn whether an SSN or person is on file.
 * Results (rule 3 in ReportVisibilityService), re-evaluated every time they are viewed:
 *   - Free plan: whether eligible records exist and how many.
 *   - Standard plan: category, date, amount, city/state, factual summary, review status,
 *     dispute status/outcome. Never evidence, the filing owner, street address, dispute
 *     statements/evidence or internal notes.
 * No score, ranking, blacklist or recommendation is produced: the owner makes the rental decision.
 *
 * @package FraudTenant\Services
 */

namespace FraudTenant\Services;

use FraudTenant\Utils\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TenantScreeningService {

	public const RESULT_BASIC = 'basic';
	public const RESULT_FULL  = 'full';

	public const MAX_SEARCHES_PER_DAY = 50;

	/**
	 * Register request handlers.
	 */
	public static function register(): void {
		add_action( 'admin_post_ft_owner_tenant_search', array( __CLASS__, 'handle_owner_search' ) );
	}

	/**
	 * Per-owner daily search cap (in addition to the 5-minute window in ScreeningService).
	 *
	 * @param int $owner_id Owner.
	 * @return bool
	 */
	protected static function within_daily_cap( int $owner_id ): bool {
		global $wpdb;
		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}" . Constants::TABLE_SCREENING_REQUESTS . ' WHERE owner_id = %d AND search_date > %s', $owner_id, gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) ) );
		return $count < self::MAX_SEARCHES_PER_DAY;
	}

	/**
	 * Owner search by applicant identity.
	 *
	 * @param int   $owner_id Owner.
	 * @param array $data     property_id, purpose_reason, permissible_purpose_attestation, applicant_consent,
	 *                        applicant_first_name, applicant_last_name, applicant_ssn.
	 * @return array{success: bool, error?: string, message: string, request_id?: int}
	 */
	public static function search_applicant( int $owner_id, array $data ): array {
		global $wpdb;

		$actor = get_userdata( $owner_id );
		if ( ! $actor || ! in_array( Constants::ROLE_OWNER, (array) $actor->roles, true ) || ! ScreeningService::can_initiate_screening( $owner_id ) ) {
			return array( 'success' => false, 'error' => 'unauthorized', 'message' => __( 'Only verified property owners can run tenant screening.', 'fraud-tenant-core' ) );
		}
		if ( ! ComplianceService::owner_search_ready() ) {
			return array( 'success' => false, 'error' => 'screening_unavailable', 'message' => __( 'Tenant screening is not available on this platform yet.', 'fraud-tenant-core' ) );
		}
		if ( ! self::within_daily_cap( $owner_id ) || ! ScreeningService::check_rate_limit( $owner_id ) ) {
			return array( 'success' => false, 'error' => 'rate_limited', 'message' => __( 'Too many screening searches. Please wait before searching again.', 'fraud-tenant-core' ) );
		}
		$property_id = (int) ( $data['property_id'] ?? 0 );
		$prop        = ScreeningService::validate_property_for_screening( $property_id, $owner_id );
		if ( ! $prop['valid'] ) {
			return array( 'success' => false, 'error' => $prop['error'], 'message' => $prop['message'] );
		}
		$purpose = sanitize_key( (string) ( $data['purpose_reason'] ?? '' ) );
		if ( ! array_key_exists( $purpose, ScreeningService::ALLOWED_PURPOSES ) ) {
			return array( 'success' => false, 'error' => 'invalid_purpose', 'message' => __( 'Select a valid screening purpose.', 'fraud-tenant-core' ) );
		}
		$truthy = array( 1, '1', true, 'true', 'on' );
		if ( ! in_array( $data['permissible_purpose_attestation'] ?? null, $truthy, true ) || ! in_array( $data['applicant_consent'] ?? null, $truthy, true ) ) {
			return array( 'success' => false, 'error' => 'missing_attestation', 'message' => __( 'Certify the screening purpose and that you hold the applicant\'s written consent.', 'fraud-tenant-core' ) );
		}
		$first = IdentityService::clean_name( $data['applicant_first_name'] ?? '' );
		$last  = IdentityService::clean_name( $data['applicant_last_name'] ?? '' );
		$ssn   = trim( (string) wp_unslash( (string) ( $data['applicant_ssn'] ?? '' ) ) );
		if ( ! IdentityService::valid_name( $first, $last ) ) {
			return array( 'success' => false, 'error' => 'invalid_applicant_name', 'message' => __( 'Enter the applicant\'s legal first and last name.', 'fraud-tenant-core' ) );
		}
		$digits = IdentityService::normalize_ssn( $ssn );
		if ( null === $digits ) {
			return array( 'success' => false, 'error' => 'invalid_applicant_ssn', 'message' => __( 'Enter a valid 9-digit SSN (format 123-45-6789).', 'fraud-tenant-core' ) );
		}
		$name_enc = SensitiveDataService::encrypt_array( array( 'first' => $first, 'last' => $last ), SensitiveDataService::CTX_APPLICANT_NAME );
		if ( null === $name_enc ) {
			return array( 'success' => false, 'error' => 'identity_protection_failed', 'message' => __( 'The search could not be protected. Nothing was saved.', 'fraud-tenant-core' ) );
		}

		$identity = IdentityService::find_exact( $first, $last, $digits );
		unset( $ssn );
		$records = $identity ? ReportVisibilityService::get_screening_records( (int) $identity->id, $owner_id ) : array();
		$ids     = array();
		if ( $records ) {
			global $wpdb;
			$ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}" . Constants::TABLE_INCIDENT_REPORTS . ' WHERE tenant_identity_id = %d AND status = %s AND screening_disclosure = 1', (int) $identity->id, Constants::INCIDENT_STATUS_APPROVED ) ) );
		}
		$level = MembershipService::can_access_feature( $owner_id, 'full_reports' ) ? self::RESULT_FULL : self::RESULT_BASIC;

		$wpdb->insert(
			$wpdb->prefix . Constants::TABLE_SCREENING_REQUESTS,
			array(
				'owner_id'                        => $owner_id,
				'property_id'                     => $property_id,
				'tenant_identity_id'              => $identity ? (int) $identity->id : null,
				'permissible_purpose_attestation' => 1,
				'purpose_reason'                  => $purpose,
				'ssn_last_four'                   => substr( $digits, -4 ),
				'match_found'                     => $ids ? 1 : 0,
				'records_count'                   => count( $ids ),
				'disclosure_level'                => $level,
				'applicant_token_hash'            => null,
				'applicant_name_enc'              => $name_enc,
				'matched_report_ids'              => wp_json_encode( $ids ),
				'search_date'                     => current_time( 'mysql', true ),
				'ip_address'                      => AuditLogger::get_client_ip(),
			)
		);
		$request_id = (int) $wpdb->insert_id;
		if ( $request_id <= 0 ) {
			return array( 'success' => false, 'error' => 'db_insert_failed', 'message' => __( 'The screening search could not be recorded.', 'fraud-tenant-core' ) );
		}

		AuditLogger::log( 'tenant_screening_search', 'screening_request', $request_id, array( 'records_found' => (bool) $ids, 'records_count' => count( $ids ), 'level' => $level, 'purpose' => $purpose, 'property_id' => $property_id ), $owner_id );
		if ( $ids && $identity && ! empty( $identity->user_id ) ) {
			NotificationService::notify( (int) $identity->user_id, __( 'Screening inquiry', 'fraud-tenant-core' ), __( 'A verified property owner ran an authorized tenant screening that included reviewed records on your account. You can review and dispute your records from your dashboard.', 'fraud-tenant-core' ), home_url( '/tenant-dashboard/?section=records' ), 'screening_inquiry', 'screening' );
		}

		return array(
			'success'    => true,
			'request_id' => $request_id,
			'message'    => $ids ? __( 'Screening complete: reviewed records were found.', 'fraud-tenant-core' ) : __( 'Screening complete: no reviewed records were found.', 'fraud-tenant-core' ),
		);
	}

	/**
	 * Result view for the owner who ran the search (ownership enforced; level and disclosure re-checked).
	 *
	 * @param int $owner_id   Owner.
	 * @param int $request_id Screening request.
	 * @return array|null
	 */
	public static function get_search_result( int $owner_id, int $request_id ): ?array {
		global $wpdb;
		$req = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}" . Constants::TABLE_SCREENING_REQUESTS . ' WHERE id = %d AND owner_id = %d', $request_id, $owner_id ) );
		if ( ! $req || empty( $req->applicant_name_enc ) ) {
			return null;
		}
		$ids     = array_map( 'intval', (array) json_decode( (string) $req->matched_report_ids, true ) );
		$records = ( $req->tenant_identity_id && $ids ) ? ReportVisibilityService::get_screening_records( (int) $req->tenant_identity_id, $owner_id, $ids ) : array();
		$level   = MembershipService::can_access_feature( $owner_id, 'full_reports' ) ? self::RESULT_FULL : self::RESULT_BASIC;
		$name    = SensitiveDataService::decrypt_array( $req->applicant_name_enc, SensitiveDataService::CTX_APPLICANT_NAME );
		return array(
			'request_id'  => (int) $req->id,
			'reference'   => sprintf( 'SCR-%06d', (int) $req->id ),
			'searched_at' => $req->search_date,
			'applicant'   => trim( ( $name['first'] ?? '' ) . ' ' . ( $name['last'] ?? '' ) ),
			'ssn_last4'   => (string) $req->ssn_last_four,
			'level'       => $level,
			'count'       => count( $records ),
			'records'     => self::RESULT_FULL === $level ? $records : array(),
			'purpose'     => ScreeningService::ALLOWED_PURPOSES[ $req->purpose_reason ] ?? $req->purpose_reason,
		);
	}

	/**
	 * Owner's search history (most recent first).
	 *
	 * @param int $owner_id Owner.
	 * @param int $limit    Limit.
	 * @return array
	 */
	public static function get_owner_search_history( int $owner_id, int $limit = 20 ): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, search_date, match_found, records_count, ssn_last_four, applicant_name_enc, purpose_reason FROM {$wpdb->prefix}" . Constants::TABLE_SCREENING_REQUESTS . ' WHERE owner_id = %d AND applicant_name_enc IS NOT NULL ORDER BY id DESC LIMIT %d', $owner_id, $limit ) ) ?: array();
		$out  = array();
		foreach ( $rows as $r ) {
			$n     = SensitiveDataService::decrypt_array( $r->applicant_name_enc, SensitiveDataService::CTX_APPLICANT_NAME );
			$out[] = array(
				'id'        => (int) $r->id,
				'reference' => sprintf( 'SCR-%06d', (int) $r->id ),
				'date'      => $r->search_date,
				'applicant' => trim( ( $n['first'] ?? '' ) . ' ' . ( $n['last'] ?? '' ) ),
				'last4'     => (string) $r->ssn_last_four,
				'found'     => (int) $r->match_found,
				'count'     => (int) $r->records_count,
				'purpose'   => ScreeningService::ALLOWED_PURPOSES[ $r->purpose_reason ] ?? $r->purpose_reason,
			);
		}
		return $out;
	}

	/**
	 * Screening activity for administrators (no applicant names or SSN fragments beyond last four).
	 *
	 * @param int $page     Page.
	 * @param int $per_page Per page.
	 * @return array{items: array, total: int}
	 */
	public static function get_admin_activity( int $page = 1, int $per_page = 25 ): array {
		global $wpdb;
		$t     = $wpdb->prefix . Constants::TABLE_SCREENING_REQUESTS;
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t}" );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT s.id, s.owner_id, s.property_id, s.purpose_reason, s.match_found, s.records_count, s.disclosure_level, s.search_date, s.tenant_identity_id, u.display_name AS owner_name FROM {$t} s LEFT JOIN {$wpdb->users} u ON u.ID = s.owner_id ORDER BY s.id DESC LIMIT %d OFFSET %d", $per_page, ( max( 1, $page ) - 1 ) * $per_page ) ) ?: array();
		return array( 'items' => $rows, 'total' => $total );
	}

	/**
	 * Owner search form handler (nonce + server-side authorization). The SSN is never echoed back.
	 */
	public static function handle_owner_search(): void {
		$uid = get_current_user_id();
		if ( ! $uid || ! isset( $_POST['ft_tenant_search_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ft_tenant_search_nonce'] ) ), 'ft_tenant_search_' . $uid ) ) {
			wp_die( esc_html__( 'Security check failed. Please refresh and try again.', 'fraud-tenant-core' ), 403 );
		}
		$res = self::search_applicant( $uid, wp_unslash( $_POST ) );
		unset( $_POST['applicant_ssn'] );
		$args = array( 'section' => 'screening' );
		if ( $res['success'] ) {
			$args['screening_request'] = $res['request_id'];
		} else {
			\FraudTenant\Auth\AuthHandler::set_flash_notice( 'error', $res['message'] );
		}
		wp_safe_redirect( add_query_arg( $args, home_url( '/owner-dashboard/' ) ) );
		exit;
	}
}
