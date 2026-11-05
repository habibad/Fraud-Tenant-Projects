<?php
/**
 * Compliance gates for identity-based screening and incident data (BLK-03 / BLK-04 / BLK-08).
 *
 * Technical prerequisites are verified in code; legal/infrastructure prerequisites that software
 * cannot verify are recorded as explicit, audited administrator attestations:
 *
 * - BLK-04 (technical, verified): FT_MATCH_SECRET_KEY configured (>= 32 chars) for HMAC SSN tokens.
 * - BLK-08 (application layer verified + infrastructure attested): AES-256-GCM field encryption
 *   available; an administrator attests that database/disk and backup encryption are in place.
 * - BLK-03 (legal, attested): an administrator attests that qualified counsel approved disclosure
 *   of reviewed incident records to screening owners.
 *
 * Attestations never claim legal compliance on the platform's behalf; they record who accepted
 * responsibility and when. Every change is audited. Non-production test mode (FT_INCIDENT_TEST_MODE
 * in local/development/staging) continues to open the data gate for synthetic QA only.
 *
 * @package FraudTenant\Services
 */

namespace FraudTenant\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ComplianceService {

	public const OPTION = 'ft_compliance_gates';

	public const GATE_PII_PROTECTION  = 'pii_protection';   // BLK-08 infrastructure attestation.
	public const GATE_OWNER_DISCLOSURE = 'owner_disclosure'; // BLK-03 legal attestation.

	public const MIN_STATEMENT_LENGTH = 20;

	/**
	 * Stored gate records.
	 *
	 * @return array<string, array>
	 */
	public static function get_gates(): array {
		if ( ! function_exists( 'get_option' ) ) {
			return array(); // Outside WordPress: no attestations exist (fail closed).
		}
		$g = get_option( self::OPTION, array() );
		return is_array( $g ) ? $g : array();
	}

	/**
	 * Whether a specific attestation gate is enabled.
	 *
	 * @param string $gate Gate key.
	 * @return bool
	 */
	public static function is_gate_enabled( string $gate ): bool {
		$g = self::get_gates();
		return ! empty( $g[ $gate ]['enabled'] ) && ! empty( $g[ $gate ]['by'] );
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
	 * BLK-08 data gate: incident data handling allowed (synthetic test mode, or production with
	 * application encryption available AND administrator infrastructure attestation).
	 *
	 * @return bool
	 */
	public static function is_data_gate_open(): bool {
		if ( IncidentService::is_test_mode_enabled() ) {
			return true;
		}
		return SensitiveDataService::is_available() && self::is_gate_enabled( self::GATE_PII_PROTECTION );
	}

	/**
	 * Tenant identity capture on incident reports is possible.
	 *
	 * @return bool
	 */
	public static function identity_capture_ready(): bool {
		return self::match_key_ready() && SensitiveDataService::is_available() && self::is_data_gate_open();
	}

	/**
	 * Owners may receive matched, admin-approved records in screening results.
	 *
	 * @return bool
	 */
	public static function owner_search_ready(): bool {
		return self::identity_capture_ready() && self::is_gate_enabled( self::GATE_OWNER_DISCLOSURE );
	}

	/**
	 * Enable or disable an attestation gate (administrators only; audited).
	 *
	 * @param string $gate      Gate key.
	 * @param bool   $enabled   Enable?
	 * @param int    $admin_id  Acting administrator.
	 * @param string $statement Attestation statement/reference (required when enabling).
	 * @return array{success: bool, message: string}
	 */
	public static function set_gate( string $gate, bool $enabled, int $admin_id, string $statement = '' ): array {
		if ( ! in_array( $gate, array( self::GATE_PII_PROTECTION, self::GATE_OWNER_DISCLOSURE ), true ) ) {
			return array( 'success' => false, 'message' => __( 'Unknown compliance gate.', 'fraud-tenant-core' ) );
		}
		if ( ! user_can( $admin_id, 'manage_options' ) ) {
			return array( 'success' => false, 'message' => __( 'Only administrators can change compliance gates.', 'fraud-tenant-core' ) );
		}
		$statement = sanitize_textarea_field( $statement );
		if ( $enabled && mb_strlen( $statement ) < self::MIN_STATEMENT_LENGTH ) {
			return array( 'success' => false, 'message' => __( 'Enter an attestation statement or approval reference (at least 20 characters).', 'fraud-tenant-core' ) );
		}
		if ( $enabled && self::GATE_OWNER_DISCLOSURE === $gate && ! self::identity_capture_ready() ) {
			return array( 'success' => false, 'message' => __( 'Owner disclosure cannot be enabled until the match key, encryption and data-protection gate are ready.', 'fraud-tenant-core' ) );
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
		return array( 'success' => true, 'message' => __( 'Compliance gate updated.', 'fraud-tenant-core' ) );
	}

	/**
	 * Readiness summary for the admin UI.
	 *
	 * @return array<string, mixed>
	 */
	public static function status(): array {
		$g = self::get_gates();
		return array(
			'match_key_ready'       => self::match_key_ready(),
			'encryption_available'  => SensitiveDataService::is_available(),
			'test_mode'             => IncidentService::is_test_mode_enabled(),
			'pii_protection'        => $g[ self::GATE_PII_PROTECTION ] ?? array(),
			'owner_disclosure'      => $g[ self::GATE_OWNER_DISCLOSURE ] ?? array(),
			'data_gate_open'        => self::is_data_gate_open(),
			'identity_capture_ready'=> self::identity_capture_ready(),
			'owner_search_ready'    => self::owner_search_ready(),
		);
	}
}
