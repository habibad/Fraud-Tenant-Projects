<?php
/**
 * Application-level field encryption for sensitive tenant PII (BLK-08 application layer).
 *
 * AES-256-GCM (authenticated) with a key derived via HKDF-SHA256 from the WordPress secret
 * keys/salts, using a PII-specific context string so it is independent from the Stripe
 * credential key. Ciphertext format: "pii1:" . base64( iv(12) | tag(16) | ciphertext ).
 *
 * Plaintext SSNs are NEVER encrypted or stored: SSNs are reduced to an HMAC match token
 * (SecurityService) plus last four digits. This service protects names and similar fields.
 *
 * @package FraudTenant\Services
 */

namespace FraudTenant\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SensitiveDataService {

	public const PREFIX = 'pii1:';
	public const CIPHER = 'aes-256-gcm';

	/**
	 * Whether encryption is available (OpenSSL GCM + non-default salts).
	 *
	 * @return bool
	 */
	public static function is_available(): bool {
		return function_exists( 'openssl_encrypt' )
			&& in_array( self::CIPHER, openssl_get_cipher_methods(), true )
			&& null !== self::key();
	}

	/**
	 * Derive the 32-byte PII key, or null when salts are missing/default.
	 *
	 * @return string|null
	 */
	protected static function key(): ?string {
		$parts = array();
		foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY' ) as $c ) {
			if ( ! defined( $c ) ) {
				return null;
			}
			$v = (string) constant( $c );
			if ( '' === $v || false !== stripos( $v, 'put your unique phrase here' ) ) {
				return null;
			}
			$parts[] = $v;
		}
		$salt = defined( 'AUTH_SALT' ) ? (string) AUTH_SALT : '';
		return hash_hkdf( 'sha256', implode( '|', $parts ), 32, 'fraud-tenant-pii-v1', $salt );
	}

	/**
	 * Encrypt a string. Returns null on failure (callers must fail closed).
	 *
	 * @param string $plaintext Plaintext.
	 * @return string|null
	 */
	public static function encrypt( string $plaintext ): ?string {
		$key = self::key();
		if ( null === $key ) {
			return null;
		}
		$iv  = random_bytes( 12 );
		$tag = '';
		$ct  = openssl_encrypt( $plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16 );
		if ( false === $ct ) {
			return null;
		}
		return self::PREFIX . base64_encode( $iv . $tag . $ct );
	}

	/**
	 * Decrypt a value produced by encrypt(). Returns null when tampered/invalid.
	 *
	 * @param string|null $stored Stored value.
	 * @return string|null
	 */
	public static function decrypt( ?string $stored ): ?string {
		if ( null === $stored || ! str_starts_with( $stored, self::PREFIX ) ) {
			return null;
		}
		$key = self::key();
		$raw = base64_decode( substr( $stored, strlen( self::PREFIX ) ), true );
		if ( null === $key || false === $raw || strlen( $raw ) < 29 ) {
			return null;
		}
		$pt = openssl_decrypt( substr( $raw, 28 ), self::CIPHER, $key, OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ) );
		return false === $pt ? null : $pt;
	}

	/**
	 * Encrypt a small associative array as JSON.
	 *
	 * @param array $data Data.
	 * @return string|null
	 */
	public static function encrypt_array( array $data ): ?string {
		return self::encrypt( (string) wp_json_encode( $data ) );
	}

	/**
	 * Decrypt to an associative array (empty array on failure).
	 *
	 * @param string|null $stored Stored value.
	 * @return array
	 */
	public static function decrypt_array( ?string $stored ): array {
		$pt = self::decrypt( $stored );
		$d  = null === $pt ? null : json_decode( $pt, true );
		return is_array( $d ) ? $d : array();
	}

	/**
	 * Normalize a person-name fragment for comparison (case/space/punctuation insensitive).
	 *
	 * @param string $name Name.
	 * @return string
	 */
	public static function normalize_name( string $name ): string {
		$name = remove_accents( strtolower( trim( $name ) ) );
		return (string) preg_replace( '/[^a-z]/', '', $name );
	}
}
