<?php
/**
 * Field-level encryption for sensitive tenant identity data (names) at rest.
 *
 * Key management:
 * - The data key is a dedicated secret, FT_DATA_ENCRYPTION_KEY (32 random bytes, base64 or hex),
 *   provisioned outside the database (wp-config.php / environment / secret manager). It is
 *   deliberately NOT derived from WordPress salts, so routine salt rotation can never make stored
 *   identity data unreadable.
 * - Every ciphertext carries a key id (kid): "pii2:<kid>:" . base64( iv(12) | tag(16) | ciphertext ).
 *   Rotation: set the new key as FT_DATA_ENCRYPTION_KEY, list old keys in
 *   FT_DATA_ENCRYPTION_PREVIOUS_KEYS (comma separated), then run reencrypt_all().
 * - AES-256-GCM (authenticated). A per-field context string is bound as AAD so a ciphertext copied
 *   into a different field fails authentication.
 * - Legacy "pii1:" values (1.8.0, salt-derived key) remain readable but are never written.
 *
 * Plaintext SSNs are NEVER encrypted or stored: an SSN is reduced to a keyed HMAC match token
 * (SecurityService) plus its last four digits.
 *
 * @package FraudTenant\Services
 */

namespace FraudTenant\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SensitiveDataService {

	public const PREFIX        = 'pii2:';
	public const LEGACY_PREFIX = 'pii1:';
	public const CIPHER        = 'aes-256-gcm';

	/** Field contexts (bound as AAD). */
	public const CTX_IDENTITY_NAME   = 'identity_name';
	public const CTX_CLAIM_NAME      = 'claim_name';
	public const CTX_SUBJECT_NAME    = 'incident_subject_name';
	public const CTX_APPLICANT_NAME  = 'screening_applicant_name';

	/** @var array<string,string>|null kid => raw key */
	protected static ?array $keyring = null;
	protected static ?bool $self_test = null;

	/**
	 * Parse a configured key value into 32 raw bytes, or null when unusable.
	 *
	 * @param string $value Configured value.
	 * @return string|null
	 */
	protected static function parse_key( string $value ): ?string {
		$value = trim( $value );
		if ( '' === $value ) {
			return null;
		}
		if ( 64 === strlen( $value ) && ctype_xdigit( $value ) ) {
			return hex2bin( $value );
		}
		$b64 = base64_decode( $value, true );
		if ( false !== $b64 && 32 === strlen( $b64 ) ) {
			return $b64;
		}
		// Long passphrase-style secrets are accepted and stretched to 32 bytes.
		return strlen( $value ) >= 32 ? hash_hkdf( 'sha256', $value, 32, 'fraud-tenant-pii-v2' ) : null;
	}

	/**
	 * Key id for a raw key (non-reversible, safe to store and display).
	 *
	 * @param string $raw Raw key.
	 * @return string
	 */
	protected static function kid( string $raw ): string {
		return substr( hash( 'sha256', 'ft-kid|' . $raw ), 0, 8 );
	}

	/**
	 * Configured value of a secret (constant first, then environment).
	 *
	 * @param string $name Constant / env name.
	 * @return string
	 */
	protected static function config( string $name ): string {
		if ( defined( $name ) ) {
			return (string) constant( $name );
		}
		$env = getenv( $name );
		return false === $env ? '' : (string) $env;
	}

	/**
	 * Current key + previous keys, keyed by kid. The current key is first.
	 *
	 * @return array<string,string>
	 */
	protected static function keyring(): array {
		if ( null !== self::$keyring ) {
			return self::$keyring;
		}
		$ring    = array();
		$current = self::parse_key( self::config( 'FT_DATA_ENCRYPTION_KEY' ) );
		if ( null !== $current ) {
			$ring[ self::kid( $current ) ] = $current;
		}
		foreach ( array_filter( array_map( 'trim', explode( ',', self::config( 'FT_DATA_ENCRYPTION_PREVIOUS_KEYS' ) ) ) ) as $prev ) {
			$raw = self::parse_key( $prev );
			if ( null !== $raw && ! isset( $ring[ self::kid( $raw ) ] ) ) {
				$ring[ self::kid( $raw ) ] = $raw;
			}
		}
		self::$keyring = $ring;
		return $ring;
	}

	/**
	 * Current (encrypting) key id, or null when no data key is configured.
	 *
	 * @return string|null
	 */
	public static function current_kid(): ?string {
		$ring = self::keyring();
		if ( empty( $ring ) || null === self::parse_key( self::config( 'FT_DATA_ENCRYPTION_KEY' ) ) ) {
			return null;
		}
		return (string) array_key_first( $ring );
	}

	/**
	 * Encryption is available: OpenSSL GCM, a valid dedicated data key, and a passing self-test.
	 *
	 * @return bool
	 */
	public static function is_available(): bool {
		if ( null !== self::$self_test ) {
			return self::$self_test;
		}
		if ( ! function_exists( 'openssl_encrypt' ) || ! in_array( self::CIPHER, openssl_get_cipher_methods(), true ) || null === self::current_kid() ) {
			return self::$self_test = false;
		}
		$probe = 'ft-self-test-' . bin2hex( random_bytes( 4 ) );
		$ct    = self::encrypt_raw( $probe, 'self_test' );
		return self::$self_test = ( null !== $ct && self::decrypt( $ct, 'self_test' ) === $probe );
	}

	/**
	 * Admin-safe status (never includes key material).
	 *
	 * @return array<string,mixed>
	 */
	public static function status(): array {
		$ring = self::keyring();
		return array(
			'available'      => self::is_available(),
			'key_configured' => null !== self::current_kid(),
			'current_kid'    => self::current_kid(),
			'previous_keys'  => max( 0, count( $ring ) - ( null !== self::current_kid() ? 1 : 0 ) ),
			'cipher'         => self::CIPHER,
		);
	}

	/**
	 * Reset cached key state (tests / after configuration changes).
	 */
	public static function reset_cache(): void {
		self::$keyring   = null;
		self::$self_test = null;
	}

	/**
	 * Encrypt without the availability check (used by the self-test).
	 *
	 * @param string $plaintext Plaintext.
	 * @param string $context   Field context (AAD).
	 * @return string|null
	 */
	protected static function encrypt_raw( string $plaintext, string $context ): ?string {
		$kid = self::current_kid();
		if ( null === $kid ) {
			return null;
		}
		$key = self::keyring()[ $kid ];
		$iv  = random_bytes( 12 );
		$tag = '';
		$ct  = openssl_encrypt( $plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, 'ft|' . $context, 16 );
		if ( false === $ct ) {
			return null;
		}
		return self::PREFIX . $kid . ':' . base64_encode( $iv . $tag . $ct );
	}

	/**
	 * Encrypt a string with the current key. Returns null on failure (callers must fail closed).
	 *
	 * @param string $plaintext Plaintext.
	 * @param string $context   Field context bound as AAD.
	 * @return string|null
	 */
	public static function encrypt( string $plaintext, string $context = 'generic' ): ?string {
		return self::is_available() ? self::encrypt_raw( $plaintext, $context ) : null;
	}

	/**
	 * Decrypt a stored value. Returns null when missing, tampered, or the key is unavailable.
	 *
	 * @param string|null $stored  Stored value.
	 * @param string      $context Field context used at encryption time.
	 * @return string|null
	 */
	public static function decrypt( ?string $stored, string $context = 'generic' ): ?string {
		if ( null === $stored || '' === $stored ) {
			return null;
		}
		if ( str_starts_with( $stored, self::PREFIX ) ) {
			$parts = explode( ':', $stored, 3 );
			if ( 3 !== count( $parts ) ) {
				return null;
			}
			$ring = self::keyring();
			$key  = $ring[ $parts[1] ] ?? null;
			$raw  = base64_decode( $parts[2], true );
			if ( null === $key || false === $raw || strlen( $raw ) < 29 ) {
				return null;
			}
			$pt = openssl_decrypt( substr( $raw, 28 ), self::CIPHER, $key, OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ), 'ft|' . $context );
			return false === $pt ? null : $pt;
		}
		if ( str_starts_with( $stored, self::LEGACY_PREFIX ) ) {
			return self::decrypt_legacy( $stored );
		}
		return null;
	}

	/**
	 * Read-only support for 1.8.0 "pii1:" values (salt-derived key, no AAD).
	 *
	 * @param string $stored Stored value.
	 * @return string|null
	 */
	protected static function decrypt_legacy( string $stored ): ?string {
		$parts = array();
		foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY' ) as $c ) {
			if ( ! defined( $c ) || '' === (string) constant( $c ) ) {
				return null;
			}
			$parts[] = (string) constant( $c );
		}
		$key = hash_hkdf( 'sha256', implode( '|', $parts ), 32, 'fraud-tenant-pii-v1', defined( 'AUTH_SALT' ) ? (string) AUTH_SALT : '' );
		$raw = base64_decode( substr( $stored, strlen( self::LEGACY_PREFIX ) ), true );
		if ( false === $raw || strlen( $raw ) < 29 ) {
			return null;
		}
		$pt = openssl_decrypt( substr( $raw, 28 ), self::CIPHER, $key, OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ) );
		return false === $pt ? null : $pt;
	}

	/**
	 * Encrypt a small associative array as JSON.
	 *
	 * @param array  $data    Data.
	 * @param string $context Field context.
	 * @return string|null
	 */
	public static function encrypt_array( array $data, string $context = 'generic' ): ?string {
		return self::encrypt( (string) wp_json_encode( $data ), $context );
	}

	/**
	 * Decrypt to an associative array (empty array on failure).
	 *
	 * @param string|null $stored  Stored value.
	 * @param string      $context Field context.
	 * @return array
	 */
	public static function decrypt_array( ?string $stored, string $context = 'generic' ): array {
		$pt = self::decrypt( $stored, $context );
		$d  = null === $pt ? null : json_decode( $pt, true );
		return is_array( $d ) ? $d : array();
	}

	/**
	 * Whether a stored value should be re-encrypted with the current key.
	 *
	 * @param string|null $stored Stored value.
	 * @return bool
	 */
	public static function needs_reencrypt( ?string $stored ): bool {
		if ( null === $stored || '' === $stored ) {
			return false;
		}
		$kid = self::current_kid();
		return null !== $kid && ! str_starts_with( $stored, self::PREFIX . $kid . ':' );
	}

	/**
	 * Re-encrypt every protected column with the current key (key rotation / legacy upgrade).
	 * Rows that cannot be decrypted with any configured key are left untouched and counted.
	 *
	 * @return array{updated: int, failed: int}
	 */
	public static function reencrypt_all(): array {
		global $wpdb;
		$out = array( 'updated' => 0, 'failed' => 0 );
		if ( ! self::is_available() ) {
			return $out;
		}
		$targets = array(
			array( $wpdb->prefix . 'ft_tenant_identities', 'name_enc', self::CTX_IDENTITY_NAME ),
			array( $wpdb->prefix . 'ft_identity_claims', 'name_enc', self::CTX_CLAIM_NAME ),
			array( $wpdb->prefix . 'ft_incident_reports', 'subject_name_enc', self::CTX_SUBJECT_NAME ),
			array( $wpdb->prefix . 'ft_screening_requests', 'applicant_name_enc', self::CTX_APPLICANT_NAME ),
		);
		foreach ( $targets as list( $table, $col, $ctx ) ) {
			if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
				continue;
			}
			$rows = $wpdb->get_results( "SELECT id, {$col} AS v FROM {$table} WHERE {$col} IS NOT NULL AND {$col} <> ''" ) ?: array();
			foreach ( $rows as $r ) {
				if ( ! self::needs_reencrypt( $r->v ) ) {
					continue;
				}
				$pt = self::decrypt( $r->v, $ctx );
				$ct = null === $pt ? null : self::encrypt( $pt, $ctx );
				if ( null === $ct ) {
					$out['failed']++;
					continue;
				}
				$wpdb->update( $table, array( $col => $ct ), array( 'id' => (int) $r->id ) );
				$out['updated']++;
			}
		}
		AuditLogger::log( 'security.data_reencrypted', 'system', null, $out );
		return $out;
	}

	/**
	 * Normalize a person-name fragment for comparison (case/space/punctuation/accent insensitive).
	 *
	 * @param string $name Name.
	 * @return string
	 */
	public static function normalize_name( string $name ): string {
		$name = remove_accents( strtolower( trim( $name ) ) );
		return (string) preg_replace( '/[^a-z]/', '', $name );
	}
}
