<?php
/**
 * Security & compliance gates for tenant identity data (BLK-03 / BLK-04 / BLK-08).
 *
 * Technical gates are verified in code and FAIL CLOSED:
 * - BLK-04 match key: FT_MATCH_SECRET_KEY configured (>= 32 chars) for keyed HMAC SSN tokens.
 * - BLK-08 field encryption: FT_DATA_ENCRYPTION_KEY configured and an AES-256-GCM self-test passes.
 *   Identity capture, matching, claims, review and disclosure require BOTH (identity_ready()).
 *
 * External release gates are documented, not "certified" by software:
 * - BLK-03 legal review: in a PRODUCTION environment, disclosure of reviewed records to other
 *   screening owners additionally requires an administrator to record the reference of the
 *   qualified counsel's written approval. The record is an audited sign-off, NOT a statement of
 *   legal compliance. Non-production environments (local/development/staging) do not require it,
 *   so development and synthetic QA can proceed.
 * - Backup encryption and key custody are infrastructure duties listed on the admin status page.
 *
 * @package FraudTenant\Services
 */

namespace FraudTenant\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ComplianceService {

	public const OPTION = 'ft_compliance_gates';

	/** Legal release sign-off key (BLK-03). */
	public const GATE_OWNER_DISCLOSURE = 'owner_disclosure';

	public const MIN_STATEMENT_LENGTH = 20;

	/**
	 * Stored gate records.
	 *
	 * @return array<string, array>
	 */
	public static function get_gates(): array {
		if ( ! function_exists( 'get_option' ) ) {
			return array();
		}
		$g = get_option( self::OPTION, array() );
		return is_array( $g ) ? $g : array();
	}

	/**
	 * Whether the environment is production. Uses the WP_ENVIRONMENT_TYPE constant directly
	 * (no filters); an undefined or unknown value is treated as production (fail closed).
	 *
	 * @return bool
	 */
	public static function is_production(): bool {
		$env = defined( 'WP_ENVIRONMENT_TYPE' ) ? strtolower( trim( (string) constant( 'WP_ENVIRONMENT_TYPE' ) ) ) : 'production';
		return ! in_array( $env, array( 'local', 'development', 'staging' ), true );
	}

	/**
	 * BLK-04: HMAC match key configured with adequate length.
	 *
	 * @return bool
	 */
	public static function match_key_ready(): bool {
		$k = SecurityService::get_match_secret_key();
		return is_string( $k ) && strlen( $k ) >= 32;
	}

	/**
	 * BLK-08 (application layer): dedicated data key present and encryption self-test passing.
	 *
	 * @return bool
	 */
	public static function encryption_ready(): bool {
		return SensitiveDataService::is_available();
	}

	/**
	 * Identity data may be captured, matched, reviewed and disclosed.
	 *
	 * @return bool
	 */
	public static function identity_ready(): bool {
		return self::match_key_ready() && self::encryption_ready();
	}

	/**
	 * Incident / dispute data handling gate (kept for existing callers). Same as identity_ready().
	 *
	 * @return bool
	 */
	public static function is_data_gate_open(): bool {
		return self::identity_ready();
	}

	/**
	 * Tenant identity capture on incident reports is possible.
	 *
	 * @return bool
	 */
	public static function identity_capture_ready(): bool {
		return self::identity_ready();
	}

	/**
	 * BLK-03 legal release sign-off recorded by an administrator.
	 *
	 * @return bool
	 */
	public static function legal_release_recorded(): bool {
		$g = self::get_gates();
		return ! empty( $g[ self::GATE_OWNER_DISCLOSURE ]['enabled'] ) && ! empty( $g[ self::GATE_OWNER_DISCLOSURE ]['by'] );
	}

	/**
	 * Whether the legal release sign-off is required in this environment.
	 *
	 * @return bool
	 */
	public static function legal_release_required(): bool {
		return self::is_production();
	}

	/**
	 * Owners may search identities and receive disclosed records in screening results.
	 *
	 * @return bool
	 */
	public static function owner_search_ready(): bool {
		return self::identity_ready() && ( ! self::legal_release_required() || self::legal_release_recorded() );
	}

	/**
	 * Record or withdraw the legal release sign-off (administrators only; audited).
	 *
	 * @param string $gate      Gate key (only GATE_OWNER_DISCLOSURE).
	 * @param bool   $enabled   Record (true) or withdraw (false).
	 * @param int    $admin_id  Acting administrator.
	 * @param string $statement Counsel approval reference (required when recording).
	 * @return array{success: bool, message: string}
	 */
	public static function set_gate( string $gate, bool $enabled, int $admin_id, string $statement = '' ): array {
		if ( self::GATE_OWNER_DISCLOSURE !== $gate ) {
			return array( 'success' => false, 'message' => __( 'Unknown release gate.', 'fraud-tenant-core' ) );
		}
		if ( ! user_can( $admin_id, 'manage_options' ) ) {
			return array( 'success' => false, 'message' => __( 'Only administrators can change release gates.', 'fraud-tenant-core' ) );
		}
		$statement = sanitize_textarea_field( $statement );
		if ( $enabled && mb_strlen( $statement ) < self::MIN_STATEMENT_LENGTH ) {
			return array( 'success' => false, 'message' => __( 'Enter the reference of the written legal approval (at least 20 characters).', 'fraud-tenant-core' ) );
		}
		if ( $enabled && ! self::identity_ready() ) {
			return array( 'success' => false, 'message' => __( 'The legal release cannot be recorded until the match key and data encryption key are configured and verified.', 'fraud-tenant-core' ) );
		}
		$gates          = self::get_gates();
		$gates[ $gate ] = array(
			'enabled'   => $enabled,
			'by'        => $admin_id,
			'at'        => current_time( 'mysql', true ),
			'statement' => $enabled ? $statement : '',
		);
		update_option( self::OPTION, $gates, false );
		AuditLogger::log( 'compliance_gate_' . ( $enabled ? 'enabled' : 'disabled' ), 'compliance_gate', null, array( 'gate' => $gate ), $admin_id );
		return array( 'success' => true, 'message' => $enabled ? __( 'Legal release sign-off recorded.', 'fraud-tenant-core' ) : __( 'Legal release sign-off withdrawn.', 'fraud-tenant-core' ) );
	}

	/**
	 * Readiness summary for the admin Security & Compliance page.
	 *
	 * @return array<string, mixed>
	 */
	public static function status(): array {
		$g = self::get_gates();
		return array(
			'environment'            => defined( 'WP_ENVIRONMENT_TYPE' ) ? (string) WP_ENVIRONMENT_TYPE : 'production (default)',
			'is_production'          => self::is_production(),
			'match_key_ready'        => self::match_key_ready(),
			'encryption'             => SensitiveDataService::status(),
			'identity_ready'         => self::identity_ready(),
			'legal_release_required' => self::legal_release_required(),
			'owner_disclosure'       => $g[ self::GATE_OWNER_DISCLOSURE ] ?? array(),
			'owner_search_ready'     => self::owner_search_ready(),
			'https'                  => is_ssl() || 0 === strpos( (string) home_url(), 'https://' ),
		);
	}
}
