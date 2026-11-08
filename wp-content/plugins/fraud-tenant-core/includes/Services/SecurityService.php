<?php
/**
 * Security, Tokenization, and Authorization Service.
 *
 * @package FraudTenant\Services
 */

namespace FraudTenant\Services;

use FraudTenant\Utils\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SecurityService {

	/**
	 * Compute keyed HMAC-SHA256 token for SSN matching.
	 *
	 * Note: Plaintext SSNs must never be persisted in databases or written to application logs.
	 * The match key (FT_MATCH_SECRET_KEY) must be securely configured in wp-config.php or environment.
	 * Fails closed if the secret key is missing or invalid.
	 *
	 * Enforces strict pre-normalization ASCII regex validation (\A[0-9]{3}-[0-9]{2}-[0-9]{4}\z or \A[0-9]{9}\z)
	 * rejecting malformed strings with non-ASCII or extra characters prior to hashing.
	 *
	 * @param string $ssn Raw or formatted 9-digit SSN.
	 * @return string|false 64-character hex hash or false if match key is missing/invalid or SSN format is malformed.
	 */
	public static function generate_ssn_match_token( string $ssn ): string|false {
		$secret_key = self::get_match_secret_key();
		if ( empty( $secret_key ) ) {
			return false; // Fail closed if key is missing or empty
		}

		// Strict pre-normalization ASCII format validation
		if ( ! self::validate_ssn_format( $ssn ) ) {
			return false;
		}

		// Normalize: strip hyphens to yield exactly 9 ASCII digits
		$normalized = str_replace( '-', '', trim( $ssn ) );
		if ( strlen( $normalized ) !== 9 ) {
			return false;
		}

		return hash_hmac( 'sha256', $normalized, $secret_key );
	}

	/**
	 * Validate that an SSN input candidate strictly conforms to ASCII-only SSN formatting rules.
	 *
	 * Checks against whole-string anchored regular expressions using ASCII digit character classes [0-9]:
	 * - Formatted: \A[0-9]{3}-[0-9]{2}-[0-9]{4}\z
	 * - Raw 9-digit: \A[0-9]{9}\z
	 *
	 * Note: Confirms structural format only; does not verify SSA issuance or deceased status.
	 *
	 * @param string $ssn Candidate SSN string.
	 * @return bool True if structurally valid ASCII SSN format, false otherwise.
	 */
	public static function validate_ssn_format( string $ssn ): bool {
		$candidate = trim( $ssn );
		if ( '' === $candidate ) {
			return false;
		}

		// Enforce strict whole-string anchored regex with ASCII [0-9]
		if ( 1 === preg_match( '/\A[0-9]{3}-[0-9]{2}-[0-9]{4}\z/', $candidate ) ) {
			return true;
		}

		if ( 1 === preg_match( '/\A[0-9]{9}\z/', $candidate ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Retrieve the secret key used for HMAC SSN tokenization.
	 * Kept strictly separate from the OTP secret key.
	 *
	 * @return string|null Secret key or null if unconfigured.
	 */
	public static function get_match_secret_key(): ?string {
		if ( defined( 'FT_MATCH_SECRET_KEY' ) && ! empty( constant( 'FT_MATCH_SECRET_KEY' ) ) ) {
			return (string) constant( 'FT_MATCH_SECRET_KEY' );
		}

		$env_key = getenv( 'FT_MATCH_SECRET_KEY' );
		if ( false !== $env_key && ! empty( $env_key ) ) {
			return (string) $env_key;
		}

		return null;
	}

	/**
	 * Check whether the SSN match key is configured.
	 *
	 * @return bool
	 */
	public static function is_match_key_configured(): bool {
		return ! empty( self::get_match_secret_key() );
	}

	/**
	 * Retrieve the dedicated server-side secret key used for HMAC OTP hashing.
	 * Must be defined outside database and source control (wp-config.php or environment).
	 * Strictly independent from FT_MATCH_SECRET_KEY.
	 *
	 * @return string|null Secret key or null if unconfigured.
	 */
	public static function get_otp_secret_key(): ?string {
		if ( defined( 'FT_OTP_SECRET_KEY' ) && ! empty( constant( 'FT_OTP_SECRET_KEY' ) ) ) {
			return (string) constant( 'FT_OTP_SECRET_KEY' );
		}

		$env_key = getenv( 'FT_OTP_SECRET_KEY' );
		if ( false !== $env_key && ! empty( $env_key ) ) {
			return (string) $env_key;
		}

		return null;
	}

	/**
	 * Check whether the dedicated OTP secret key is configured.
	 *
	 * @return bool
	 */
	public static function is_otp_secret_configured(): bool {
		return ! empty( self::get_otp_secret_key() );
	}

	/**
	 * Hash an OTP code securely with HMAC-SHA256 using the dedicated server secret key.
	 *
	 * Fails closed if the secret is missing or invalid.
	 *
	 * @param string $otp 6-digit numeric OTP.
	 * @return string|false 64-character hex hash or false on missing secret or invalid input.
	 */
	public static function hash_otp( string $otp ): string|false {
		$secret = self::get_otp_secret_key();
		if ( empty( $secret ) ) {
			return false; // Fail closed: missing or invalid secret
		}

		$clean_otp = trim( $otp );
		if ( '' === $clean_otp ) {
			return false;
		}

		return hash_hmac( 'sha256', $clean_otp, $secret );
	}

	/**
	 * Verify an OTP candidate against its stored HMAC hash using constant-time comparison.
	 *
	 * Fails closed if the secret is unconfigured, candidate is empty, or stored hash is empty.
	 *
	 * @param string $otp         Candidate OTP code.
	 * @param string $stored_hash Stored HMAC-SHA256 hash.
	 * @return bool True if valid, false otherwise.
	 */
	public static function verify_otp( string $otp, string $stored_hash ): bool {
		if ( empty( $stored_hash ) || empty( trim( $otp ) ) ) {
			return false; // Fail closed
		}

		$candidate_hash = self::hash_otp( $otp );
		if ( false === $candidate_hash ) {
			return false; // Fail closed: secret missing or invalid
		}

		return hash_equals( $stored_hash, $candidate_hash );
	}
}
