<?php
/**
 * Tenant identity claims — the secure, pending-claim workflow that links a verified tenant account
 * to its central identity record.
 *
 * Why a reviewed claim: knowing someone's name and SSN is not proof of being that person. Without
 * an external identity-proofing service, every claim is verified by an administrator against a
 * supporting document (government ID or lease naming the tenant) before any record is shown.
 *
 * Anti-enumeration: the tenant always receives the same response ("submitted for verification")
 * regardless of whether an identity exists, matches, or is claimed by someone else. The match
 * result is visible to administrators only.
 *
 * @package FraudTenant\Services
 */

namespace FraudTenant\Services;

use FraudTenant\Auth\AuthService;
use FraudTenant\Utils\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IdentityClaimService {

	public const MAX_CLAIMS_PER_DAY = 3;

	public const RESULT_MATCH            = 'match';
	public const RESULT_NAME_MISMATCH    = 'name_mismatch';
	public const RESULT_NO_IDENTITY      = 'no_identity';
	public const RESULT_CLAIMED_BY_OTHER = 'claimed_by_other';

	/**
	 * Register the tenant claim form handler.
	 */
	public static function register(): void {
		add_action( 'admin_post_ft_identity_claim', array( __CLASS__, 'handle_submit' ) );
	}

	/**
	 * Claim form handler (nonce + server-side checks). The SSN is never echoed back.
	 */
	public static function handle_submit(): void {
		$uid = get_current_user_id();
		if ( ! $uid || ! check_admin_referer( 'ft_identity_claim_' . $uid ) ) {
			wp_die( esc_html__( 'Security check failed.', 'fraud-tenant-core' ), 403 );
		}
		$file = isset( $_FILES['claim_document'] ) && is_array( $_FILES['claim_document'] ) ? $_FILES['claim_document'] : null;
		$res  = self::submit( $uid, wp_unslash( $_POST ), $file );
		unset( $_POST['ssn'] );
		\FraudTenant\Auth\AuthHandler::set_flash_notice( $res['success'] ? 'success' : 'error', $res['message'] );
		wp_safe_redirect( add_query_arg( 'section', 'identity', home_url( '/tenant-dashboard/' ) ) );
		exit;
	}

	/**
	 * Claims table.
	 *
	 * @return string
	 */
	protected static function table(): string {
		global $wpdb;
		return $wpdb->prefix . Constants::TABLE_IDENTITY_CLAIMS;
	}

	/**
	 * Latest claim for a user.
	 *
	 * @param int $user_id User.
	 * @return object|null
	 */
	public static function get_latest_for_user( int $user_id ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT id, status, ssn_last4, decision_note, created_at, decided_at FROM ' . self::table() . ' WHERE user_id = %d ORDER BY id DESC LIMIT 1', $user_id ) ) ?: null;
	}

	/**
	 * Tenant-facing claim state: verified | pending | rejected | none | unavailable.
	 *
	 * @param int $user_id Tenant.
	 * @return array{state: string, claim: ?object, identity: ?object}
	 */
	public static function get_state( int $user_id ): array {
		if ( ! ComplianceService::identity_ready() ) {
			return array( 'state' => 'unavailable', 'claim' => null, 'identity' => null );
		}
		$identity = IdentityService::get_for_user( $user_id );
		$claim    = self::get_latest_for_user( $user_id );
		if ( $identity ) {
			return array( 'state' => 'verified', 'claim' => $claim, 'identity' => $identity );
		}
		if ( $claim && Constants::CLAIM_PENDING === $claim->status ) {
			return array( 'state' => 'pending', 'claim' => $claim, 'identity' => null );
		}
		if ( $claim && Constants::CLAIM_REJECTED === $claim->status ) {
			return array( 'state' => 'rejected', 'claim' => $claim, 'identity' => null );
		}
		return array( 'state' => 'none', 'claim' => $claim, 'identity' => null );
	}

	/**
	 * Submit a claim.
	 *
	 * @param int        $user_id Tenant account.
	 * @param array      $data    first_name, last_name, ssn, claim_attestation.
	 * @param array|null $file    Supporting document ($_FILES entry) — required.
	 * @return array{success: bool, error?: string, message: string}
	 */
	public static function submit( int $user_id, array $data, ?array $file ): array {
		global $wpdb;
		$user = get_userdata( $user_id );
		if ( ! $user || ! in_array( Constants::ROLE_TENANT, (array) $user->roles, true ) || Constants::STATUS_APPROVED !== AuthService::get_user_account_status( $user_id ) ) {
			return array( 'success' => false, 'error' => 'unauthorized', 'message' => __( 'Only verified tenant accounts can request identity verification.', 'fraud-tenant-core' ) );
		}
		if ( ! ComplianceService::identity_ready() ) {
			return array( 'success' => false, 'error' => 'identity_matching_unavailable', 'message' => __( 'Identity verification is not available on this platform yet.', 'fraud-tenant-core' ) );
		}
		$state = self::get_state( $user_id );
		if ( 'verified' === $state['state'] ) {
			return array( 'success' => false, 'error' => 'already_verified', 'message' => __( 'Your identity is already verified.', 'fraud-tenant-core' ) );
		}
		if ( 'pending' === $state['state'] ) {
			return array( 'success' => false, 'error' => 'claim_pending', 'message' => __( 'You already have a verification request under review.', 'fraud-tenant-core' ) );
		}
		$recent = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE user_id = %d AND created_at > %s', $user_id, gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) ) );
		if ( $recent >= self::MAX_CLAIMS_PER_DAY ) {
			return array( 'success' => false, 'error' => 'rate_limited', 'message' => __( 'Too many verification requests today. Please try again tomorrow.', 'fraud-tenant-core' ) );
		}

		$first  = IdentityService::clean_name( $data['first_name'] ?? '' );
		$last   = IdentityService::clean_name( $data['last_name'] ?? '' );
		$digits = IdentityService::normalize_ssn( (string) wp_unslash( (string) ( $data['ssn'] ?? '' ) ) );
		if ( ! IdentityService::valid_name( $first, $last ) ) {
			return array( 'success' => false, 'error' => 'invalid_name', 'message' => __( 'Enter your legal first and last name.', 'fraud-tenant-core' ) );
		}
		if ( null === $digits ) {
			return array( 'success' => false, 'error' => 'invalid_ssn', 'message' => __( 'Enter a valid 9-digit Social Security Number (format 123-45-6789).', 'fraud-tenant-core' ) );
		}
		if ( ! in_array( $data['claim_attestation'] ?? null, array( 1, '1', true, 'true', 'on' ), true ) ) {
			return array( 'success' => false, 'error' => 'missing_attestation', 'message' => __( 'Confirm that the name and SSN are your own.', 'fraud-tenant-core' ) );
		}
		if ( empty( $file ) || ! isset( $file['error'] ) || UPLOAD_ERR_NO_FILE === $file['error'] ) {
			return array( 'success' => false, 'error' => 'missing_document', 'message' => __( 'Upload a document that verifies your identity (government ID or a lease in your name).', 'fraud-tenant-core' ) );
		}

		$token = SecurityService::generate_ssn_match_token( $digits );
		$enc   = SensitiveDataService::encrypt_array( array( 'first' => $first, 'last' => $last ), SensitiveDataService::CTX_CLAIM_NAME );
		if ( false === $token || null === $enc ) {
			return array( 'success' => false, 'error' => 'identity_protection_failed', 'message' => __( 'Your request could not be protected. Nothing was saved.', 'fraud-tenant-core' ) );
		}
		$candidate = IdentityService::get_by_token( $token );
		$result    = self::RESULT_NO_IDENTITY;
		if ( $candidate ) {
			if ( ! empty( $candidate->user_id ) && (int) $candidate->user_id !== $user_id ) {
				$result = self::RESULT_CLAIMED_BY_OTHER;
			} elseif ( IdentityService::names_match( IdentityService::name( $candidate ), $first, $last ) ) {
				$result = self::RESULT_MATCH;
			} else {
				$result = self::RESULT_NAME_MISMATCH;
			}
		}

		$now = current_time( 'mysql', true );
		$wpdb->query( 'START TRANSACTION' );
		$ok = $wpdb->insert(
			self::table(),
			array(
				'user_id'      => $user_id,
				'token_hash'   => $token,
				'ssn_last4'    => substr( $digits, -4 ),
				'name_enc'     => $enc,
				'identity_id'  => $candidate ? (int) $candidate->id : null,
				'match_result' => $result,
				'status'       => Constants::CLAIM_PENDING,
				'created_at'   => $now,
				'updated_at'   => $now,
			)
		);
		$claim_id = (int) $wpdb->insert_id;
		if ( ! $ok || $claim_id <= 0 ) {
			$wpdb->query( 'ROLLBACK' );
			return array( 'success' => false, 'error' => 'db_insert_failed', 'message' => __( 'Your request could not be saved. Please try again.', 'fraud-tenant-core' ) );
		}
		$doc = DocumentService::store_claim_document( $user_id, $claim_id, $file );
		if ( empty( $doc['success'] ) ) {
			$wpdb->query( 'ROLLBACK' );
			return array( 'success' => false, 'error' => $doc['error'] ?? 'upload_error', 'message' => $doc['message'] ?? __( 'The document could not be stored.', 'fraud-tenant-core' ) );
		}
		$wpdb->update( self::table(), array( 'document_id' => (int) $doc['document_id'] ), array( 'id' => $claim_id ) );
		$wpdb->query( 'COMMIT' );

		AuditLogger::log( 'identity.claim_submitted', 'identity_claim', $claim_id, array( 'match_result' => $result ), $user_id );
		NotificationService::notify_admins( __( 'Identity verification request', 'fraud-tenant-core' ), sprintf( /* translators: %s: claim ref */ __( 'Claim %s is waiting for review.', 'fraud-tenant-core' ), sprintf( 'IDC-%06d', $claim_id ) ), home_url( '/admin-dashboard/?section=claims&claim_id=' . $claim_id ), 'identity_claim' );

		return array( 'success' => true, 'message' => __( 'Your identity verification request was submitted. Our team will review it and notify you of the decision.', 'fraud-tenant-core' ) );
	}

	/**
	 * Admin queue.
	 *
	 * @param string $status Status filter (pending|approved|rejected|all).
	 * @return array
	 */
	public static function get_queue( string $status = 'pending' ): array {
		global $wpdb;
		$where = 'all' === $status ? '1=1' : $wpdb->prepare( 'c.status = %s', $status );
		return $wpdb->get_results( "SELECT c.id, c.user_id, c.ssn_last4, c.match_result, c.status, c.created_at, c.decided_at, u.user_email, u.display_name FROM " . self::table() . " c LEFT JOIN {$wpdb->users} u ON u.ID = c.user_id WHERE {$where} ORDER BY c.id DESC LIMIT 200" ) ?: array();
	}

	/**
	 * Claim detail for an administrator (decrypted claimed name; candidate summary).
	 *
	 * @param int $claim_id Claim.
	 * @param int $admin_id Administrator.
	 * @return array|null
	 */
	public static function get_detail( int $claim_id, int $admin_id ): ?array {
		global $wpdb;
		if ( ! IdentityService::can_manage( $admin_id ) ) {
			return null;
		}
		$c = $wpdb->get_row( $wpdb->prepare( "SELECT c.*, u.user_email, u.display_name FROM " . self::table() . " c LEFT JOIN {$wpdb->users} u ON u.ID = c.user_id WHERE c.id = %d", $claim_id ) );
		if ( ! $c ) {
			return null;
		}
		$candidate = $c->identity_id ? IdentityService::get( (int) $c->identity_id ) : null;
		AuditLogger::log( 'identity.claim_viewed', 'identity_claim', $claim_id, array(), $admin_id );
		return array(
			'claim'          => $c,
			'claimed_name'   => SensitiveDataService::decrypt_array( $c->name_enc, SensitiveDataService::CTX_CLAIM_NAME ),
			'candidate'      => $candidate,
			'candidate_name' => $candidate ? IdentityService::name( $candidate ) : null,
			'reports'        => $candidate ? count( IdentityService::get_reports( (int) $candidate->id ) ) : 0,
			'document'       => $c->document_id ? DocumentService::get_document( (int) $c->document_id ) : null,
		);
	}

	/**
	 * Approve a claim: link the account to the existing identity, or create the identity.
	 *
	 * @param int    $claim_id Claim.
	 * @param int    $admin_id Administrator.
	 * @param string $note     Internal note (optional).
	 * @return array{success: bool, error?: string, message: string}
	 */
	public static function approve( int $claim_id, int $admin_id, string $note = '' ): array {
		global $wpdb;
		if ( ! IdentityService::can_manage( $admin_id ) ) {
			return array( 'success' => false, 'error' => 'unauthorized', 'message' => __( 'You are not authorized to decide identity claims.', 'fraud-tenant-core' ) );
		}
		if ( ! ComplianceService::identity_ready() ) {
			return array( 'success' => false, 'error' => 'identity_matching_unavailable', 'message' => __( 'Tenant identity protection is not configured.', 'fraud-tenant-core' ) );
		}
		$ident_t = $wpdb->prefix . Constants::TABLE_TENANT_IDENTITIES;
		$wpdb->query( 'START TRANSACTION' );
		$claim = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d FOR UPDATE', $claim_id ) );
		if ( ! $claim || Constants::CLAIM_PENDING !== $claim->status || empty( $claim->token_hash ) ) {
			$wpdb->query( 'ROLLBACK' );
			return array( 'success' => false, 'error' => 'not_pending', 'message' => __( 'This claim is not pending.', 'fraud-tenant-core' ) );
		}
		$user_id = (int) $claim->user_id;
		if ( $admin_id === $user_id ) {
			$wpdb->query( 'ROLLBACK' );
			return array( 'success' => false, 'error' => 'self_review_forbidden', 'message' => __( 'You cannot decide your own claim.', 'fraud-tenant-core' ) );
		}
		$already = IdentityService::get_for_user( $user_id );
		if ( $already ) {
			$wpdb->query( 'ROLLBACK' );
			return array( 'success' => false, 'error' => 'already_verified', 'message' => __( 'This account already has a verified identity.', 'fraud-tenant-core' ) );
		}
		$identity = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$ident_t} WHERE token_hash = %s FOR UPDATE", $claim->token_hash ) );
		$now      = current_time( 'mysql', true );
		if ( $identity ) {
			if ( ! empty( $identity->user_id ) && (int) $identity->user_id !== $user_id ) {
				$wpdb->query( 'ROLLBACK' );
				return array( 'success' => false, 'error' => 'claimed_by_other', 'message' => __( 'This identity is already claimed by another account. Revoke that claim in Tenant Identity Management first if it is mistaken.', 'fraud-tenant-core' ) );
			}
			$wpdb->update( $ident_t, array( 'user_id' => $user_id, 'claimed_at' => $now, 'updated_at' => $now ), array( 'id' => (int) $identity->id ) );
			$identity_id = (int) $identity->id;
		} else {
			$name        = SensitiveDataService::decrypt_array( $claim->name_enc, SensitiveDataService::CTX_CLAIM_NAME );
			$created     = IdentityService::create_or_get( (string) $claim->token_hash, (string) $claim->ssn_last4, (string) ( $name['first'] ?? '' ), (string) ( $name['last'] ?? '' ), $user_id );
			$identity_id = (int) ( $created['id'] ?? 0 );
			if ( empty( $created['ok'] ) || $identity_id <= 0 ) {
				$wpdb->query( 'ROLLBACK' );
				return array( 'success' => false, 'error' => 'identity_write_failed', 'message' => __( 'The identity record could not be created.', 'fraud-tenant-core' ) );
			}
			// A concurrent approval may have created the identity first: verify who holds it.
			$check = IdentityService::get( $identity_id );
			if ( ! $check || (int) $check->user_id !== $user_id ) {
				$wpdb->query( 'ROLLBACK' );
				return array( 'success' => false, 'error' => 'claimed_by_other', 'message' => __( 'This identity is already claimed by another account.', 'fraud-tenant-core' ) );
			}
		}
		$wpdb->update(
			self::table(),
			array(
				'status'        => Constants::CLAIM_APPROVED,
				'identity_id'   => $identity_id,
				'token_hash'    => null, // Data minimization: the identity record now holds the token.
				'decision_note' => sanitize_textarea_field( $note ),
				'decided_by'    => $admin_id,
				'decided_at'    => $now,
				'updated_at'    => $now,
			),
			array( 'id' => $claim_id )
		);
		$wpdb->update( $wpdb->prefix . Constants::TABLE_USER_PROFILES, array( 'identity_verified' => 1, 'updated_at' => $now ), array( 'user_id' => $user_id ) );
		$wpdb->query( 'COMMIT' );

		AuditLogger::log( 'identity.claim_approved', 'identity_claim', $claim_id, array( 'identity_id' => $identity_id, 'user_id' => $user_id ), $admin_id );
		$visible = count( ReportVisibilityService::get_tenant_reports( $user_id ) );
		NotificationService::notify(
			$user_id,
			__( 'Identity verified', 'fraud-tenant-core' ),
			$visible
				/* translators: %d: number of records */
				? sprintf( _n( 'Your identity was verified. %d reviewed rental record is now available in your dashboard.', 'Your identity was verified. %d reviewed rental records are now available in your dashboard.', $visible, 'fraud-tenant-core' ), $visible )
				: __( 'Your identity was verified. Reviewed rental records associated with your identity will appear in your dashboard.', 'fraud-tenant-core' ),
			home_url( '/tenant-dashboard/?section=records' ),
			'identity',
			'account'
		);
		return array( 'success' => true, 'message' => __( 'Claim approved. The tenant account is now linked to the identity.', 'fraud-tenant-core' ) );
	}

	/**
	 * Reject a claim (the note is shown to the tenant; keep it factual and generic).
	 *
	 * @param int    $claim_id Claim.
	 * @param int    $admin_id Administrator.
	 * @param string $note     Note to the tenant (required).
	 * @return array{success: bool, error?: string, message: string}
	 */
	public static function reject( int $claim_id, int $admin_id, string $note ): array {
		global $wpdb;
		if ( ! IdentityService::can_manage( $admin_id ) ) {
			return array( 'success' => false, 'error' => 'unauthorized', 'message' => __( 'You are not authorized to decide identity claims.', 'fraud-tenant-core' ) );
		}
		$note = trim( sanitize_textarea_field( $note ) );
		if ( mb_strlen( $note ) < 5 ) {
			return array( 'success' => false, 'error' => 'reason_required', 'message' => __( 'Enter a reason for the tenant (at least 5 characters).', 'fraud-tenant-core' ) );
		}
		$claim = $wpdb->get_row( $wpdb->prepare( 'SELECT id, user_id, status FROM ' . self::table() . ' WHERE id = %d', $claim_id ) );
		if ( ! $claim || Constants::CLAIM_PENDING !== $claim->status ) {
			return array( 'success' => false, 'error' => 'not_pending', 'message' => __( 'This claim is not pending.', 'fraud-tenant-core' ) );
		}
		if ( $admin_id === (int) $claim->user_id ) {
			return array( 'success' => false, 'error' => 'self_review_forbidden', 'message' => __( 'You cannot decide your own claim.', 'fraud-tenant-core' ) );
		}
		$now = current_time( 'mysql', true );
		$wpdb->update(
			self::table(),
			array( 'status' => Constants::CLAIM_REJECTED, 'token_hash' => null, 'decision_note' => $note, 'decided_by' => $admin_id, 'decided_at' => $now, 'updated_at' => $now ),
			array( 'id' => $claim_id, 'status' => Constants::CLAIM_PENDING )
		);
		AuditLogger::log( 'identity.claim_rejected', 'identity_claim', $claim_id, array( 'user_id' => (int) $claim->user_id ), $admin_id );
		NotificationService::notify( (int) $claim->user_id, __( 'Identity verification not approved', 'fraud-tenant-core' ), __( 'Your identity verification request could not be approved. Open your dashboard to see the reason and submit a new request.', 'fraud-tenant-core' ), home_url( '/tenant-dashboard/?section=identity' ), 'identity', 'account' );
		return array( 'success' => true, 'message' => __( 'Claim rejected. The tenant was notified.', 'fraud-tenant-core' ) );
	}
}
