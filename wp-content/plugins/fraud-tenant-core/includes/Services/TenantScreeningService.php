<?php
/**
 * Identity-matched tenant screening and administrator-controlled screening disclosure.
 *
 * Flow:
 * 1. An owner files an incident naming the tenant (name + SSN). Only an HMAC match token, the
 *    last four digits and the AES-256-GCM-encrypted name are stored (IncidentService).
 * 2. An administrator reviews the report. Only APPROVED reports that an administrator explicitly
 *    releases for screening (screening_disclosure = 1) can ever appear in another owner's results.
 * 3. Another owner, with a verified property, a permissible purpose, and the applicant's written
 *    consent, searches by the applicant's name + SSN. Records match only when BOTH the SSN token and
 *    the normalized last name match.
 *       - Free plan: "record found / no record" and the count only.
 *       - Standard plan: summaries (category, month, amount, location city/state, dispute state,
 *         reviewed narrative). Never evidence files, the filing owner's identity, or full SSNs.
 * 4. A tenant account actively associated with a matched report is notified that an inquiry
 *    included their record. Every search and disclosure change is audited without PII.
 *
 * All of this is gated by ComplianceService::owner_search_ready() (BLK-03/04/08).
 *
 * @package FraudTenant\Services
 */

namespace FraudTenant\Services;

use FraudTenant\Auth\Capabilities;
use FraudTenant\Utils\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TenantScreeningService {

	public const RESULT_BASIC = 'basic';
	public const RESULT_FULL  = 'full';

	/**
	 * Register request handlers.
	 */
	public static function register(): void {
		// Safety net for missed Stripe webhooks: periodically re-verify active subscriptions.
		add_action( 'ft_reconcile_subscriptions', array( __CLASS__, 'reconcile_subscriptions' ) );
		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( 'ft_reconcile_subscriptions' ) ) {
			wp_schedule_event( time() + 300, 'hourly', 'ft_reconcile_subscriptions' );
		}
		add_action( 'admin_post_ft_owner_tenant_search', array( __CLASS__, 'handle_owner_search' ) );
		add_action( 'admin_post_ft_admin_screening_disclosure', array( __CLASS__, 'handle_admin_disclosure' ) );
		add_action( 'admin_post_ft_admin_compliance_gate', array( __CLASS__, 'handle_compliance_gate' ) );
	}

	/**
	 * Re-verify active subscriptions not synchronized for 6+ hours (missed-webhook safety net).
	 * Uses the same fully verified StripeWebhookService::sync_subscription() path.
	 *
	 * @param int $limit Max subscriptions per run.
	 * @return array{checked: int, changed: int, failed: int}
	 */
	public static function reconcile_subscriptions( int $limit = 50 ): array {
		global $wpdb;
		$table  = $wpdb->prefix . Constants::TABLE_SUBSCRIPTIONS;
		$cutoff = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 6 * HOUR_IN_SECONDS ); // same clock as updated_at
		$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT id, stripe_subscription_id, livemode, status FROM {$table} WHERE status IN ('active','trialing','past_due') AND stripe_subscription_id LIKE 'sub\\_%%' AND updated_at < %s ORDER BY updated_at ASC LIMIT %d", $cutoff, max( 1, $limit ) ) ) ?: array();
		$out    = array( 'checked' => 0, 'changed' => 0, 'failed' => 0 );
		foreach ( $rows as $r ) {
			$mode = $r->livemode ? Constants::STRIPE_MODE_LIVE : Constants::STRIPE_MODE_TEST;
			if ( empty( StripeCredentialService::get_secret_key( $mode ) ) ) {
				continue;
			}
			$out['checked']++;
			try {
				$res = StripeWebhookService::sync_subscription( (string) $r->stripe_subscription_id, $mode, 'cron.reconcile' );
				if ( $res['status'] !== $r->status ) {
					$out['changed']++;
				}
			} catch ( \Throwable $e ) {
				$out['failed']++;
				// Touch updated_at so a permanently failing row does not starve others.
				$wpdb->update( $table, array( 'updated_at' => current_time( 'mysql' ) ), array( 'id' => (int) $r->id ) );
			}
		}
		return $out;
	}

	/**
	 * Whether a user may control screening disclosure.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function can_manage_disclosure( int $user_id ): bool {
		return user_can( $user_id, 'manage_options' )
			|| user_can( $user_id, Capabilities::DECIDE_INCIDENTS )
			|| user_can( $user_id, Capabilities::MANAGE_REPORT_VISIBILITY );
	}

	/**
	 * Release or withdraw an approved incident for owner screening results.
	 *
	 * @param int  $report_id Report ID.
	 * @param bool $enable    Release (true) or withdraw (false).
	 * @param int  $admin_id  Acting administrator.
	 * @return array{success: bool, error?: string, message: string}
	 */
	public static function set_screening_disclosure( int $report_id, bool $enable, int $admin_id ): array {
		global $wpdb;
		if ( ! self::can_manage_disclosure( $admin_id ) ) {
			return array( 'success' => false, 'error' => 'unauthorized', 'message' => __( 'You are not authorized to change screening disclosure.', 'fraud-tenant-core' ) );
		}
		if ( ! ComplianceService::is_data_gate_open() ) {
			return array( 'success' => false, 'error' => 'locked_blk08', 'message' => __( 'Incident data handling is locked (BLK-08).', 'fraud-tenant-core' ) );
		}
		$table  = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;
		$report = $wpdb->get_row( $wpdb->prepare( "SELECT id, owner_id, status, subject_token_hash, screening_disclosure FROM {$table} WHERE id = %d", $report_id ) );
		if ( ! $report ) {
			return array( 'success' => false, 'error' => 'not_found', 'message' => __( 'Incident report not found.', 'fraud-tenant-core' ) );
		}
		if ( (int) $report->owner_id === $admin_id ) {
			return array( 'success' => false, 'error' => 'self_report', 'message' => __( 'You cannot release your own incident report.', 'fraud-tenant-core' ) );
		}
		if ( $enable ) {
			if ( Constants::INCIDENT_STATUS_APPROVED !== $report->status ) {
				return array( 'success' => false, 'error' => 'not_approved', 'message' => __( 'Only approved incident reports can be released for screening.', 'fraud-tenant-core' ) );
			}
			if ( empty( $report->subject_token_hash ) ) {
				return array( 'success' => false, 'error' => 'no_subject', 'message' => __( 'This report has no protected tenant identity and cannot be matched in screening.', 'fraud-tenant-core' ) );
			}
		}
		$now = current_time( 'mysql', true );
		$wpdb->update(
			$table,
			array(
				'screening_disclosure'   => $enable ? 1 : 0,
				'screening_disclosed_by' => $enable ? $admin_id : null,
				'screening_disclosed_at' => $enable ? $now : null,
				'updated_at'             => $now,
			),
			array( 'id' => $report_id )
		);
		AuditLogger::log( $enable ? 'screening_disclosure_released' : 'screening_disclosure_withdrawn', 'incident_report', $report_id, array(), $admin_id );
		return array(
			'success' => true,
			'message' => $enable ? __( 'Report released for owner screening results.', 'fraud-tenant-core' ) : __( 'Report withdrawn from owner screening results.', 'fraud-tenant-core' ),
		);
	}

	/**
	 * Owner search by applicant identity.
	 *
	 * @param int   $owner_id Owner.
	 * @param array $data     Submitted data.
	 * @return array{success: bool, error?: string, message: string, request_id?: int}
	 */
	public static function search_applicant( int $owner_id, array $data ): array {
		global $wpdb;

		$actor = get_userdata( $owner_id );
		if ( ! $actor || ! in_array( Constants::ROLE_OWNER, (array) $actor->roles, true ) || ! ScreeningService::can_initiate_screening( $owner_id ) ) {
			return array( 'success' => false, 'error' => 'unauthorized', 'message' => __( 'Only approved property owners can run tenant screening.', 'fraud-tenant-core' ) );
		}
		if ( ! ComplianceService::owner_search_ready() ) {
			return array( 'success' => false, 'error' => 'screening_unavailable', 'message' => __( 'Identity-based tenant screening is not enabled on this platform yet.', 'fraud-tenant-core' ) );
		}
		if ( ! ScreeningService::check_rate_limit( $owner_id ) ) {
			return array( 'success' => false, 'error' => 'rate_limited', 'message' => __( 'Too many screening searches. Please wait a few minutes.', 'fraud-tenant-core' ) );
		}
		$property_id = isset( $data['property_id'] ) ? (int) $data['property_id'] : 0;
		$prop        = ScreeningService::validate_property_for_screening( $property_id, $owner_id );
		if ( ! $prop['valid'] ) {
			return array( 'success' => false, 'error' => $prop['error'], 'message' => $prop['message'] );
		}
		$purpose = isset( $data['purpose_reason'] ) ? sanitize_key( $data['purpose_reason'] ) : '';
		if ( ! array_key_exists( $purpose, ScreeningService::ALLOWED_PURPOSES ) ) {
			return array( 'success' => false, 'error' => 'invalid_purpose', 'message' => __( 'Select a valid permissible screening purpose.', 'fraud-tenant-core' ) );
		}
		$truthy = array( 1, '1', true, 'true', 'on' );
		if ( ! in_array( $data['permissible_purpose_attestation'] ?? null, $truthy, true ) || ! in_array( $data['applicant_consent'] ?? null, $truthy, true ) ) {
			return array( 'success' => false, 'error' => 'missing_attestation', 'message' => __( 'Certify the permissible purpose and that you hold the applicant\'s written consent.', 'fraud-tenant-core' ) );
		}
		$first = isset( $data['applicant_first_name'] ) ? sanitize_text_field( wp_unslash( (string) $data['applicant_first_name'] ) ) : '';
		$last  = isset( $data['applicant_last_name'] ) ? sanitize_text_field( wp_unslash( (string) $data['applicant_last_name'] ) ) : '';
		$ssn   = isset( $data['applicant_ssn'] ) ? trim( (string) wp_unslash( $data['applicant_ssn'] ) ) : '';
		if ( mb_strlen( $first ) < 1 || mb_strlen( $last ) < 2 ) {
			return array( 'success' => false, 'error' => 'invalid_applicant_name', 'message' => __( 'Enter the applicant\'s legal first and last name.', 'fraud-tenant-core' ) );
		}
		if ( ! IncidentService::is_plausible_ssn( $ssn ) ) {
			return array( 'success' => false, 'error' => 'invalid_applicant_ssn', 'message' => __( 'Enter a valid 9-digit SSN (format 123-45-6789).', 'fraud-tenant-core' ) );
		}
		$token = SecurityService::generate_ssn_match_token( $ssn );
		if ( false === $token ) {
			return array( 'success' => false, 'error' => 'identity_matching_unavailable', 'message' => __( 'Identity matching is unavailable.', 'fraud-tenant-core' ) );
		}

		$inc  = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, subject_name_enc FROM {$inc} WHERE subject_token_hash = %s AND status = %s AND screening_disclosure = 1", $token, Constants::INCIDENT_STATUS_APPROVED ) ) ?: array();
		$want = SensitiveDataService::normalize_name( $last );
		$ids  = array();
		foreach ( $rows as $r ) {
			$n = SensitiveDataService::decrypt_array( $r->subject_name_enc );
			if ( '' !== $want && SensitiveDataService::normalize_name( (string) ( $n['last'] ?? '' ) ) === $want ) {
				$ids[] = (int) $r->id;
			}
		}

		$level = MembershipService::can_access_feature( $owner_id, 'full_reports' ) ? self::RESULT_FULL : self::RESULT_BASIC;
		$now   = current_time( 'mysql', true );
		$wpdb->insert(
			$wpdb->prefix . Constants::TABLE_SCREENING_REQUESTS,
			array(
				'owner_id'                        => $owner_id,
				'property_id'                     => $property_id,
				'tenant_identity_id'              => null,
				'permissible_purpose_attestation' => 1,
				'purpose_reason'                  => $purpose,
				'ssn_last_four'                   => substr( str_replace( '-', '', $ssn ), -4 ),
				'match_found'                     => $ids ? 1 : 0,
				'records_count'                   => count( $ids ),
				'disclosure_level'                => $level,
				'applicant_token_hash'            => $token,
				'applicant_name_enc'              => SensitiveDataService::encrypt_array( array( 'first' => $first, 'last' => $last ) ),
				'matched_report_ids'              => wp_json_encode( $ids ),
				'search_date'                     => $now,
				'ip_address'                      => sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ),
			)
		);
		$request_id = (int) $wpdb->insert_id;
		if ( $request_id <= 0 ) {
			return array( 'success' => false, 'error' => 'db_insert_failed', 'message' => __( 'The screening search could not be recorded.', 'fraud-tenant-core' ) );
		}

		AuditLogger::log( 'tenant_screening_search', 'screening_request', $request_id, array( 'match_found' => (bool) $ids, 'records_count' => count( $ids ), 'level' => $level, 'purpose' => $purpose ), $owner_id );
		self::notify_associated_tenants( $ids );

		return array(
			'success'    => true,
			'request_id' => $request_id,
			'message'    => $ids ? __( 'Screening complete: records found.', 'fraud-tenant-core' ) : __( 'Screening complete: no records found.', 'fraud-tenant-core' ),
		);
	}

	/**
	 * Notify tenant accounts actively associated with matched reports (inquiry transparency).
	 *
	 * @param int[] $report_ids Matched reports.
	 */
	protected static function notify_associated_tenants( array $report_ids ): void {
		global $wpdb;
		if ( empty( $report_ids ) ) {
			return;
		}
		$assoc = $wpdb->prefix . Constants::TABLE_REPORT_ASSOCIATIONS;
		$in    = implode( ',', array_map( 'intval', $report_ids ) );
		$users = $wpdb->get_col( "SELECT DISTINCT tenant_user_id FROM {$assoc} WHERE status = 'active' AND report_id IN ({$in})" );
		foreach ( $users as $uid ) {
			$wpdb->insert(
				$wpdb->prefix . Constants::TABLE_NOTIFICATIONS,
				array(
					'user_id'    => (int) $uid,
					'title'      => __( 'Screening Inquiry', 'fraud-tenant-core' ),
					'message'    => __( 'A verified property owner ran an authorized tenant screening that included a record associated with your account. You can review and dispute your records from your dashboard.', 'fraud-tenant-core' ),
					'type'       => 'info',
					'action_url' => esc_url_raw( home_url( '/tenant-dashboard/' ) ),
					'is_read'    => 0,
					'created_at' => current_time( 'mysql' ),
				)
			);
		}
	}

	/**
	 * Result view for the owner who ran the search (ownership enforced; disclosure re-checked).
	 *
	 * @param int $owner_id   Owner.
	 * @param int $request_id Screening request.
	 * @return array|null
	 */
	public static function get_search_result( int $owner_id, int $request_id ): ?array {
		global $wpdb;
		$req = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}" . Constants::TABLE_SCREENING_REQUESTS . ' WHERE id = %d AND owner_id = %d', $request_id, $owner_id ) );
		if ( ! $req || empty( $req->applicant_token_hash ) ) {
			return null;
		}
		$ids   = array_map( 'intval', (array) json_decode( (string) $req->matched_report_ids, true ) );
		$name  = SensitiveDataService::decrypt_array( $req->applicant_name_enc );
		$level = MembershipService::can_access_feature( $owner_id, 'full_reports' ) ? self::RESULT_FULL : self::RESULT_BASIC;
		$recs  = array();
		if ( $ids ) {
			$inc  = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;
			$prop = $wpdb->prefix . Constants::TABLE_PROPERTIES;
			$dsp  = $wpdb->prefix . Constants::TABLE_TENANT_DISPUTES;
			$in   = implode( ',', $ids );
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT r.id, r.owner_id, r.category, r.incident_date, r.amount_involved, r.factual_description, r.created_at, p.city, p.state,
					        (SELECT COUNT(*) FROM {$dsp} d WHERE d.report_id = r.id AND d.status IN ('pending','under_review')) AS open_disputes,
					        (SELECT COUNT(*) FROM {$dsp} d WHERE d.report_id = r.id AND d.status IN ('resolved','rejected')) AS closed_disputes
					 FROM {$inc} r LEFT JOIN {$prop} p ON p.id = r.property_id
					 WHERE r.id IN ({$in}) AND r.status = %s AND r.screening_disclosure = 1 AND r.subject_token_hash = %s
					 ORDER BY r.incident_date DESC",
					Constants::INCIDENT_STATUS_APPROVED,
					$req->applicant_token_hash
				)
			) ?: array();
			foreach ( $rows as $r ) {
				$recs[] = array(
					'category'    => IncidentService::ALLOWED_CATEGORIES[ $r->category ] ?? $r->category,
					'month'       => mysql2date( 'M Y', $r->incident_date ),
					'amount'      => (float) $r->amount_involved,
					'location'    => trim( (string) $r->city . ', ' . (string) $r->state, ', ' ),
					'reported'    => mysql2date( 'M j, Y', $r->created_at ),
					'dispute'     => (int) $r->open_disputes > 0 ? 'open' : ( (int) $r->closed_disputes > 0 ? 'closed' : 'none' ),
					'narrative'   => (string) $r->factual_description,
					'own_report'  => (int) $r->owner_id === $owner_id,
				);
			}
		}
		return array(
			'request_id'   => (int) $req->id,
			'searched_at'  => $req->search_date,
			'applicant'    => trim( ( $name['first'] ?? '' ) . ' ' . ( $name['last'] ?? '' ) ),
			'ssn_last4'    => (string) $req->ssn_last_four,
			'level'        => $level,
			'count'        => count( $recs ),
			'records'      => self::RESULT_FULL === $level ? $recs : array(),
			'purpose'      => ScreeningService::ALLOWED_PURPOSES[ $req->purpose_reason ] ?? $req->purpose_reason,
		);
	}

	/**
	 * Owner's identity-search history (most recent first).
	 *
	 * @param int $owner_id Owner.
	 * @param int $limit    Limit.
	 * @return array
	 */
	public static function get_owner_search_history( int $owner_id, int $limit = 10 ): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, search_date, match_found, records_count, ssn_last_four, applicant_name_enc FROM {$wpdb->prefix}" . Constants::TABLE_SCREENING_REQUESTS . ' WHERE owner_id = %d AND applicant_token_hash IS NOT NULL ORDER BY id DESC LIMIT %d', $owner_id, $limit ) ) ?: array();
		$out  = array();
		foreach ( $rows as $r ) {
			$n     = SensitiveDataService::decrypt_array( $r->applicant_name_enc );
			$out[] = array(
				'id'        => (int) $r->id,
				'date'      => $r->search_date,
				'applicant' => trim( ( $n['first'] ?? '' ) . ' ' . ( $n['last'] ?? '' ) ),
				'last4'     => (string) $r->ssn_last_four,
				'found'     => (int) $r->match_found,
				'count'     => (int) $r->records_count,
			);
		}
		return $out;
	}

	// ------------------------------------------------------------------ request handlers

	/**
	 * Owner search form handler (nonce + server-side authorization).
	 */
	public static function handle_owner_search(): void {
		$uid = get_current_user_id();
		if ( ! $uid || ! isset( $_POST['ft_tenant_search_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ft_tenant_search_nonce'] ) ), 'ft_tenant_search_' . $uid ) ) {
			wp_die( esc_html__( 'Security check failed. Please refresh and try again.', 'fraud-tenant-core' ), 403 );
		}
		$res = self::search_applicant( $uid, wp_unslash( $_POST ) );
		unset( $_POST['applicant_ssn'] );
		$args = array( 'ft_tab' => 'screening' );
		if ( $res['success'] ) {
			$args['screening_request'] = $res['request_id'];
		} else {
			\FraudTenant\Auth\AuthHandler::set_flash_notice( 'error', $res['message'] );
		}
		wp_safe_redirect( add_query_arg( $args, home_url( '/owner-dashboard/' ) ) );
		exit;
	}

	/**
	 * Admin release/withdraw handler.
	 */
	public static function handle_admin_disclosure(): void {
		$report_id = isset( $_POST['report_id'] ) ? absint( $_POST['report_id'] ) : 0;
		check_admin_referer( 'ft_screening_disclosure_' . $report_id );
		$res = self::set_screening_disclosure( $report_id, ! empty( $_POST['enable'] ), get_current_user_id() );
		set_transient( 'ft_admin_scr_notice_' . get_current_user_id(), $res, 60 );
		wp_safe_redirect( add_query_arg( array( 'page' => 'fraud-tenant-incidents', 'action' => 'review', 'report_id' => $report_id ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Compliance gate handler (Platform Settings).
	 */
	public static function handle_compliance_gate(): void {
		check_admin_referer( 'ft_compliance_gate' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'fraud-tenant-core' ), 403 );
		}
		$gate = isset( $_POST['gate'] ) ? sanitize_key( $_POST['gate'] ) : '';
		$res  = ComplianceService::set_gate( $gate, ! empty( $_POST['enable'] ), get_current_user_id(), isset( $_POST['statement'] ) ? wp_unslash( (string) $_POST['statement'] ) : '' );
		set_transient( 'ft_admin_gate_notice_' . get_current_user_id(), $res, 60 );
		wp_safe_redirect( add_query_arg( array( 'page' => 'fraud-tenant-admin', 'tab' => 'platform_settings' ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
