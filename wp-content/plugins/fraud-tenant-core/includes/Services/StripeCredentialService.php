<?php
/**
 * Dedicated Secure Stripe Credential Management Service.
 *
 * Implements authenticated server-side encryption for Stripe API keys and secrets
 * using AES-256-GCM with HKDF key derivation from WordPress secret salts.
 *
 * Supports independent switchable dual environments:
 * - Test Mode (Sandbox)
 * - Live Mode (Production)
 *
 * Stores ciphertext, IV nonce, and authentication tag in non-autoloaded options:
 * - ft_stripe_credentials_test
 * - ft_stripe_credentials_live
 *
 * Zero plaintext leakage to JS, REST, reports, or application logs.
 *
 * @package FraudTenant\Services
 */

namespace FraudTenant\Services;

use FraudTenant\Utils\Constants;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class StripeCredentialService {

	/**
	 * Encryption format / version identifier.
	 */
	public const ENCRYPTION_VERSION = 1;

	/**
	 * Symmetric cipher algorithm.
	 */
	public const CIPHER_ALGO = 'aes-256-gcm';

	/**
	 * Nonce (IV) length in bytes for AES-256-GCM (96 bits).
	 */
	public const IV_LENGTH = 12;

	/**
	 * Authentication tag length in bytes for AES-256-GCM (128 bits).
	 */
	public const TAG_LENGTH = 16;

	/**
	 * Required WordPress security salt constants.
	 */
	public const REQUIRED_SALTS = array(
		'AUTH_KEY',
		'SECURE_AUTH_KEY',
		'LOGGED_IN_KEY',
		'AUTH_SALT',
		'SECURE_AUTH_SALT',
		'LOGGED_IN_SALT',
	);

	/**
	 * Default placeholder string from wp-config-sample.php.
	 */
	public const DEFAULT_SALT_PLACEHOLDER = 'put your unique phrase here';

	/**
	 * Verify OpenSSL extension and required cipher support.
	 *
	 * @return array{supported: bool, error?: string}
	 */
	public static function validate_crypto_support(): array {
		if ( ! extension_loaded( 'openssl' ) ) {
			return array(
				'supported' => false,
				'error'     => __( 'The OpenSSL PHP extension is required for secure credential storage.', 'fraud-tenant-core' ),
			);
		}

		if ( ! function_exists( 'openssl_encrypt' ) || ! function_exists( 'openssl_decrypt' ) ) {
			return array(
				'supported' => false,
				'error'     => __( 'OpenSSL encryption functions are unavailable on this server.', 'fraud-tenant-core' ),
			);
		}

		$ciphers = openssl_get_cipher_methods();
		if ( ! in_array( self::CIPHER_ALGO, $ciphers, true ) ) {
			return array(
				'supported' => false,
				'error'     => sprintf(
					/* translators: %s: cipher algorithm name */
					__( 'The %s cipher is not supported by the installed OpenSSL library.', 'fraud-tenant-core' ),
					self::CIPHER_ALGO
				),
			);
		}

		return array( 'supported' => true );
	}

	/**
	 * Validate that required WordPress salts exist, are strings, and not default placeholders.
	 *
	 * @return array{valid: bool, error?: string}
	 */
	public static function validate_salts(): array {
		foreach ( self::REQUIRED_SALTS as $salt_name ) {
			if ( ! defined( $salt_name ) ) {
				return array(
					'valid' => false,
					'error' => sprintf(
						/* translators: %s: salt constant name */
						__( 'Required WordPress salt %s is not defined in wp-config.php.', 'fraud-tenant-core' ),
						$salt_name
					),
				);
			}

			$val = constant( $salt_name );
			if ( ! is_string( $val ) || '' === trim( $val ) ) {
				return array(
					'valid' => false,
					'error' => sprintf(
						/* translators: %s: salt constant name */
						__( 'WordPress salt %s is empty or invalid.', 'fraud-tenant-core' ),
						$salt_name
					),
				);
			}

			if ( self::DEFAULT_SALT_PLACEHOLDER === trim( $val ) ) {
				return array(
					'valid' => false,
					'error' => sprintf(
						/* translators: %s: salt constant name */
						__( 'WordPress salt %s contains the insecure default placeholder. Please generate unique keys in wp-config.php.', 'fraud-tenant-core' ),
						$salt_name
					),
				);
			}

			if ( strlen( trim( $val ) ) < 16 ) {
				return array(
					'valid' => false,
					'error' => sprintf(
						/* translators: %s: salt constant name */
						__( 'WordPress salt %s is too short for cryptographic security (must be at least 16 characters).', 'fraud-tenant-core' ),
						$salt_name
					),
				);
			}
		}

		return array( 'valid' => true );
	}

	/**
	 * Derive a 256-bit (32-byte) symmetric encryption key via domain-separated HKDF.
	 *
	 * @return string|null 32-byte binary key or null on validation failure.
	 */
	public static function derive_encryption_key(): ?string {
		$salt_check = self::validate_salts();
		if ( ! $salt_check['valid'] ) {
			return null;
		}

		// Input keying material (IKM)
		$ikm = constant( 'AUTH_KEY' ) . '|' . constant( 'SECURE_AUTH_KEY' ) . '|' . constant( 'LOGGED_IN_KEY' );

		// Salt for HKDF
		$salt = constant( 'AUTH_SALT' ) . '|' . constant( 'SECURE_AUTH_SALT' ) . '|' . constant( 'LOGGED_IN_SALT' );

		// Domain-separated info string
		$info = 'fraud_tenant_stripe_credential_encryption_v' . self::ENCRYPTION_VERSION;

		if ( function_exists( 'hash_hkdf' ) ) {
			return hash_hkdf( 'sha256', $ikm, 32, $info, $salt );
		}

		// RFC 5869 fallback using HMAC-SHA256
		$prk = hash_hmac( 'sha256', $ikm, $salt, true );
		$okm = hash_hmac( 'sha256', $info . chr( 1 ), $prk, true );
		return substr( $okm, 0, 32 );
	}

	/**
	 * Determine the current environment type.
	 *
	 * Allowed environments: 'local', 'development', 'staging', 'production'.
	 * If unrecognized or missing, returns 'invalid' (fails closed).
	 *
	 * @return string
	 */
	public static function get_environment(): string {
		$env = null;

		if ( function_exists( 'wp_get_environment_type' ) ) {
			$env = wp_get_environment_type();
		} elseif ( defined( 'WP_ENVIRONMENT_TYPE' ) && is_string( constant( 'WP_ENVIRONMENT_TYPE' ) ) ) {
			$env = constant( 'WP_ENVIRONMENT_TYPE' );
		}

		if ( ! is_string( $env ) || '' === trim( $env ) ) {
			$env = 'production';
		}

		$env     = strtolower( trim( $env ) );
		$allowed = array( 'local', 'development', 'staging', 'production' );

		return in_array( $env, $allowed, true ) ? $env : 'invalid';
	}

	/**
	 * Check if current environment is production.
	 *
	 * @return bool
	 */
	public static function is_production(): bool {
		return 'production' === self::get_environment();
	}

	/**
	 * Retrieve the active Stripe operational mode ('test' or 'live').
	 *
	 * Policy:
	 * 1. If explicit active mode is stored in ft_stripe_active_mode ('test' or 'live'), use it.
	 * 2. Otherwise default:
	 *    - 'production' environment defaults to 'live'.
	 *    - Non-production ('local', 'development', 'staging') defaults to 'test'.
	 *
	 * @return string 'test' or 'live'.
	 */
	public static function get_active_mode(): string {
		$stored_mode = get_option( Constants::OPTION_STRIPE_ACTIVE_MODE );
		if ( is_string( $stored_mode ) ) {
			$stored_mode = strtolower( trim( $stored_mode ) );
			if ( in_array( $stored_mode, array( Constants::STRIPE_MODE_TEST, Constants::STRIPE_MODE_LIVE ), true ) ) {
				return $stored_mode;
			}
		}

		return self::is_production() ? Constants::STRIPE_MODE_LIVE : Constants::STRIPE_MODE_TEST;
	}

	/**
	 * Set the active Stripe operational mode ('test' or 'live').
	 *
	 * @param string $mode     'test' or 'live'.
	 * @param int    $admin_id User ID of administrator making the change.
	 * @return array{success: bool, message: string, error?: string}
	 */
	public static function set_active_mode( string $mode, int $admin_id ): array {
		if ( $admin_id <= 0 || ! user_can( $admin_id, 'manage_options' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Unauthorized. Administrator capability is required to switch Stripe modes.', 'fraud-tenant-core' ),
				'message' => __( 'Unauthorized access.', 'fraud-tenant-core' ),
			);
		}

		$mode = strtolower( trim( $mode ) );
		if ( ! in_array( $mode, array( Constants::STRIPE_MODE_TEST, Constants::STRIPE_MODE_LIVE ), true ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Invalid Stripe mode. Expected "test" or "live".', 'fraud-tenant-core' ),
				'message' => __( 'Invalid mode selected.', 'fraud-tenant-core' ),
			);
		}

		$env = self::get_environment();
		if ( 'invalid' === $env ) {
			return array(
				'success' => false,
				'error'   => __( 'Invalid environment configuration. Refusing mode switch.', 'fraud-tenant-core' ),
				'message' => __( 'Invalid environment.', 'fraud-tenant-core' ),
			);
		}

		$prev_mode = self::get_active_mode();
		update_option( Constants::OPTION_STRIPE_ACTIVE_MODE, $mode );

		$is_qa_prod = ( self::is_production() && Constants::STRIPE_MODE_TEST === $mode );

		AuditLogger::log(
			'stripe_mode_switched',
			'stripe_config',
			null,
			array(
				'environment'   => $env,
				'previous_mode' => $prev_mode,
				'new_mode'      => $mode,
				'qa_restricted' => $is_qa_prod,
			),
			$admin_id
		);

		$message = sprintf(
			/* translators: %s: active mode name */
			__( 'Stripe operational mode switched to %s.', 'fraud-tenant-core' ),
			strtoupper( $mode )
		);

		if ( $is_qa_prod ) {
			$message .= ' ' . __( 'Notice: Test mode on production restricts subscription billing to admin QA accounts.', 'fraud-tenant-core' );
		}

		return array(
			'success' => true,
			'message' => $message,
		);
	}

	/**
	 * Check if test mode is currently active (or if specified mode is test).
	 *
	 * @param string|null $mode Optional explicit mode to check.
	 * @return bool
	 */
	public static function is_test_mode( ?string $mode = null ): bool {
		$target_mode = $mode ? strtolower( trim( $mode ) ) : self::get_active_mode();
		return Constants::STRIPE_MODE_TEST === $target_mode;
	}

	/**
	 * Check if live mode is currently active (or if specified mode is live).
	 *
	 * @param string|null $mode Optional explicit mode to check.
	 * @return bool
	 */
	public static function is_live_mode( ?string $mode = null ): bool {
		$target_mode = $mode ? strtolower( trim( $mode ) ) : self::get_active_mode();
		return Constants::STRIPE_MODE_LIVE === $target_mode;
	}

	/**
	 * Check if test mode is active on a production environment (QA restriction active).
	 *
	 * @return bool
	 */
	public static function is_qa_mode_restricted(): bool {
		return self::is_production() && self::is_test_mode();
	}

	/**
	 * User meta flag marking an owner account as an administrator-controlled billing QA account.
	 */
	public const META_BILLING_QA_ACCOUNT = '_ft_billing_qa_account';

	/**
	 * Whether a user has been designated by an administrator as a billing QA account.
	 *
	 * QA accounts are the only accounts permitted to run Test-mode checkout, and the only
	 * accounts that receive entitlements from Test-mode subscriptions, while a production
	 * installation is operating in Test mode.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function is_billing_qa_account( int $user_id ): bool {
		return $user_id > 0 && '1' === (string) get_user_meta( $user_id, self::META_BILLING_QA_ACCOUNT, true );
	}

	/**
	 * Designate or clear a billing QA account (administrator action, audited).
	 *
	 * @param int  $user_id  Target user ID.
	 * @param bool $enabled  Whether to enable the QA flag.
	 * @param int  $admin_id Acting administrator ID.
	 * @return bool
	 */
	public static function set_billing_qa_account( int $user_id, bool $enabled, int $admin_id ): bool {
		if ( $user_id <= 0 || ! user_can( $admin_id, 'manage_options' ) ) {
			return false;
		}
		if ( $enabled ) {
			update_user_meta( $user_id, self::META_BILLING_QA_ACCOUNT, '1' );
		} else {
			delete_user_meta( $user_id, self::META_BILLING_QA_ACCOUNT );
		}
		AuditLogger::log( 'billing_qa_account_' . ( $enabled ? 'enabled' : 'disabled' ), 'user', $user_id, array(), $admin_id );
		return true;
	}

	/**
	 * Determine which Stripe livemode value may grant entitlements to a given user right now.
	 *
	 * - Live mode active: only livemode=1 subscriptions grant entitlements.
	 * - Test mode active on non-production: only livemode=0 subscriptions grant entitlements.
	 * - Test mode active on production: livemode=0 subscriptions grant entitlements ONLY to
	 *   administrator-designated billing QA accounts; everyone else receives none (null).
	 *
	 * @param int $user_id User ID.
	 * @return int|null 1, 0, or null when no subscription may grant entitlements.
	 */
	public static function entitlement_livemode_for_user( int $user_id ): ?int {
		if ( self::is_live_mode() ) {
			return 1;
		}
		if ( self::is_production() && ! self::is_billing_qa_account( $user_id ) ) {
			return null;
		}
		return 0;
	}

	/**
	 * Validate a key value against mode policy.
	 *
	 * Rules:
	 * - Test Mode:
	 *   - Secret key must start with 'sk_test_' or 'rk_test_'. Live keys strictly rejected.
	 *   - Publishable key must start with 'pk_test_'. Live keys strictly rejected.
	 *   - Webhook secret must start with 'whsec_'.
	 * - Live Mode:
	 *   - Secret key must start with 'sk_live_' or 'rk_live_'. Test keys strictly rejected.
	 *   - Publishable key must start with 'pk_live_'. Test keys strictly rejected.
	 *   - Webhook secret must start with 'whsec_'.
	 *
	 * @param string $key_type 'secret_key', 'publishable_key', or 'webhook_secret'.
	 * @param string $key_val  Credential value.
	 * @param string $mode     'test' or 'live' (defaults to active mode).
	 * @return array{valid: bool, error?: string}
	 */
	public static function validate_key_format( string $key_type, string $key_val, string $mode = '' ): array {
		$mode = empty( $mode ) ? self::get_active_mode() : strtolower( trim( $mode ) );

		$key_val = trim( $key_val );
		if ( '' === $key_val ) {
			return array( 'valid' => true ); // Empty is valid as "unchanged" in updates
		}

		$is_test = Constants::STRIPE_MODE_TEST === $mode;

		switch ( $key_type ) {
			case 'secret_key':
				if ( $is_test ) {
					if ( str_starts_with( $key_val, 'sk_live_' ) || str_starts_with( $key_val, 'rk_live_' ) ) {
						return array(
							'valid' => false,
							'error' => __( 'Live Stripe secret keys are strictly prohibited in Test Mode.', 'fraud-tenant-core' ),
						);
					}
					if ( ! str_starts_with( $key_val, 'sk_test_' ) && ! str_starts_with( $key_val, 'rk_test_' ) ) {
						return array(
							'valid' => false,
							'error' => __( 'Invalid Stripe secret key format for Test Mode. Expected sk_test_... or rk_test_...', 'fraud-tenant-core' ),
						);
					}
				} else {
					if ( str_starts_with( $key_val, 'sk_test_' ) || str_starts_with( $key_val, 'rk_test_' ) ) {
						return array(
							'valid' => false,
							'error' => __( 'Test Stripe secret keys are strictly prohibited in Live Mode.', 'fraud-tenant-core' ),
						);
					}
					if ( ! str_starts_with( $key_val, 'sk_live_' ) && ! str_starts_with( $key_val, 'rk_live_' ) ) {
						return array(
							'valid' => false,
							'error' => __( 'Invalid Stripe secret key format for Live Mode. Expected sk_live_... or rk_live_...', 'fraud-tenant-core' ),
						);
					}
				}
				if ( strlen( $key_val ) < 24 ) {
					return array(
						'valid' => false,
						'error' => __( 'Stripe secret key length is insufficient.', 'fraud-tenant-core' ),
					);
				}
				break;

			case 'publishable_key':
				if ( $is_test ) {
					if ( str_starts_with( $key_val, 'pk_live_' ) ) {
						return array(
							'valid' => false,
							'error' => __( 'Live Stripe publishable keys are strictly prohibited in Test Mode.', 'fraud-tenant-core' ),
						);
					}
					if ( ! str_starts_with( $key_val, 'pk_test_' ) ) {
						return array(
							'valid' => false,
							'error' => __( 'Invalid Stripe publishable key format for Test Mode. Expected pk_test_...', 'fraud-tenant-core' ),
						);
					}
				} else {
					if ( str_starts_with( $key_val, 'pk_test_' ) ) {
						return array(
							'valid' => false,
							'error' => __( 'Test Stripe publishable keys are strictly prohibited in Live Mode.', 'fraud-tenant-core' ),
						);
					}
					if ( ! str_starts_with( $key_val, 'pk_live_' ) ) {
						return array(
							'valid' => false,
							'error' => __( 'Invalid Stripe publishable key format for Live Mode. Expected pk_live_...', 'fraud-tenant-core' ),
						);
					}
				}
				if ( strlen( $key_val ) < 24 ) {
					return array(
						'valid' => false,
						'error' => __( 'Stripe publishable key length is insufficient.', 'fraud-tenant-core' ),
					);
				}
				break;

			case 'webhook_secret':
				if ( ! str_starts_with( $key_val, 'whsec_' ) ) {
					return array(
						'valid' => false,
						'error' => __( 'Invalid Stripe webhook signing secret format. Expected whsec_...', 'fraud-tenant-core' ),
					);
				}
				if ( strlen( $key_val ) < 20 ) {
					return array(
						'valid' => false,
						'error' => __( 'Stripe webhook signing secret length is insufficient.', 'fraud-tenant-core' ),
					);
				}
				break;

			default:
				return array(
					'valid' => false,
					'error' => __( 'Unrecognized credential type.', 'fraud-tenant-core' ),
				);
		}

		return array( 'valid' => true );
	}

	/**
	 * Securely mask key value for administrative display without exposing plaintext secrets.
	 *
	 * @param string|null $key  Key value.
	 * @param string      $type 'secret', 'publishable', 'webhook'.
	 * @return string Masked representation or empty string.
	 */
	public static function mask_key( ?string $key, string $type = 'secret' ): string {
		if ( empty( $key ) || ! is_string( $key ) ) {
			return '';
		}

		$key = trim( $key );
		$len = strlen( $key );
		if ( $len < 10 ) {
			return '••••••••';
		}

		// Keep prefix and last 4 characters
		$prefix_len = 0;
		if ( str_starts_with( $key, 'sk_test_' ) || str_starts_with( $key, 'sk_live_' ) || str_starts_with( $key, 'rk_test_' ) || str_starts_with( $key, 'rk_live_' ) ) {
			$prefix_len = 8;
		} elseif ( str_starts_with( $key, 'pk_test_' ) || str_starts_with( $key, 'pk_live_' ) ) {
			$prefix_len = 8;
		} elseif ( str_starts_with( $key, 'whsec_' ) ) {
			$prefix_len = 6;
		}

		$prefix = substr( $key, 0, $prefix_len );
		$suffix = substr( $key, -4 );

		return $prefix . '••••••••••••••••' . $suffix;
	}

	/**
	 * Encrypt credentials using AES-256-GCM.
	 *
	 * Generates a fresh cryptographically secure 12-byte IV for every encryption call.
	 *
	 * @param array  $credentials Associative array of credentials to encrypt.
	 * @param string $mode        Operational mode ('test' or 'live').
	 * @return array{success: bool, payload?: array, error?: string}
	 */
	public static function encrypt( array $credentials, string $mode = '' ): array {
		$crypto_check = self::validate_crypto_support();
		if ( ! $crypto_check['supported'] ) {
			return array(
				'success' => false,
				'error'   => $crypto_check['error'] ?? __( 'Cryptographic functionality unavailable.', 'fraud-tenant-core' ),
			);
		}

		$key = self::derive_encryption_key();
		if ( empty( $key ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Cannot derive encryption key: WordPress secret salts are invalid or missing.', 'fraud-tenant-core' ),
			);
		}

		try {
			$iv = random_bytes( self::IV_LENGTH );
		} catch ( \Exception $e ) {
			return array(
				'success' => false,
				'error'   => __( 'Failed to generate secure random nonce for encryption.', 'fraud-tenant-core' ),
			);
		}

		$plaintext = wp_json_encode( $credentials );
		$tag       = '';

		$ciphertext = openssl_encrypt(
			$plaintext,
			self::CIPHER_ALGO,
			$key,
			OPENSSL_RAW_DATA,
			$iv,
			$tag,
			'',
			self::TAG_LENGTH
		);

		if ( false === $ciphertext ) {
			return array(
				'success' => false,
				'error'   => __( 'OpenSSL encryption operation failed.', 'fraud-tenant-core' ),
			);
		}

		$payload = array(
			'version'     => self::ENCRYPTION_VERSION,
			'cipher'      => self::CIPHER_ALGO,
			'iv'          => base64_encode( $iv ),
			'tag'         => base64_encode( $tag ),
			'ciphertext'  => base64_encode( $ciphertext ),
			'mode'        => $mode ?: self::get_active_mode(),
			'environment' => self::get_environment(),
			'created_at'  => time(),
			'updated_at'  => time(),
		);

		return array(
			'success' => true,
			'payload' => $payload,
		);
	}

	/**
	 * Decrypt stored credential payload using AES-256-GCM.
	 *
	 * Enforces authentication tag verification. Handles decryption failure safely.
	 *
	 * @param mixed $stored Raw stored option value.
	 * @return array{success: bool, credentials?: array, error?: string}
	 */
	public static function decrypt( mixed $stored ): array {
		if ( empty( $stored ) || ! is_array( $stored ) ) {
			return array(
				'success'     => true,
				'credentials' => array(
					'secret_key'      => null,
					'publishable_key' => null,
					'webhook_secret'  => null,
				),
			);
		}

		$crypto_check = self::validate_crypto_support();
		if ( ! $crypto_check['supported'] ) {
			return array(
				'success' => false,
				'error'   => $crypto_check['error'] ?? __( 'Cryptographic functionality unavailable.', 'fraud-tenant-core' ),
			);
		}

		// Validate payload structure
		if (
			empty( $stored['version'] ) ||
			(int) $stored['version'] !== self::ENCRYPTION_VERSION ||
			empty( $stored['cipher'] ) ||
			$stored['cipher'] !== self::CIPHER_ALGO ||
			empty( $stored['iv'] ) ||
			empty( $stored['tag'] ) ||
			empty( $stored['ciphertext'] )
		) {
			return array(
				'success' => false,
				'error'   => __( 'Stored credential payload has an invalid format or unrecognized encryption version.', 'fraud-tenant-core' ),
			);
		}

		$iv         = base64_decode( (string) $stored['iv'], true );
		$tag        = base64_decode( (string) $stored['tag'], true );
		$ciphertext = base64_decode( (string) $stored['ciphertext'], true );

		if ( false === $iv || false === $tag || false === $ciphertext ) {
			return array(
				'success' => false,
				'error'   => __( 'Failed to decode base64 ciphertext components.', 'fraud-tenant-core' ),
			);
		}

		if ( strlen( $iv ) !== self::IV_LENGTH || strlen( $tag ) !== self::TAG_LENGTH ) {
			return array(
				'success' => false,
				'error'   => __( 'Corrupted encryption nonce or authentication tag length.', 'fraud-tenant-core' ),
			);
		}

		$key = self::derive_encryption_key();
		if ( empty( $key ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Cannot derive decryption key: WordPress secret salts are invalid or missing.', 'fraud-tenant-core' ),
			);
		}

		$plaintext = openssl_decrypt(
			$ciphertext,
			self::CIPHER_ALGO,
			$key,
			OPENSSL_RAW_DATA,
			$iv,
			$tag
		);

		if ( false === $plaintext ) {
			return array(
				'success' => false,
				'error'   => __( 'Decryption failed: authentication tag verification failed. Credentials may have been altered or WordPress salts were changed in wp-config.php.', 'fraud-tenant-core' ),
			);
		}

		$decoded = json_decode( $plaintext, true );
		if ( ! is_array( $decoded ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Decrypted payload is not valid JSON.', 'fraud-tenant-core' ),
			);
		}

		return array(
			'success'     => true,
			'credentials' => array(
				'secret_key'      => ! empty( $decoded['secret_key'] ) ? (string) $decoded['secret_key'] : null,
				'publishable_key' => ! empty( $decoded['publishable_key'] ) ? (string) $decoded['publishable_key'] : null,
				'webhook_secret'  => ! empty( $decoded['webhook_secret'] ) ? (string) $decoded['webhook_secret'] : null,
			),
		);
	}

	/**
	 * Retrieve option name for specified operational mode.
	 *
	 * @param string $mode 'test' or 'live'.
	 * @return string Option name constant.
	 */
	protected static function get_option_name_for_mode( string $mode ): string {
		return Constants::STRIPE_MODE_LIVE === $mode
			? Constants::OPTION_STRIPE_CREDENTIALS_LIVE
			: Constants::OPTION_STRIPE_CREDENTIALS_TEST;
	}

	/**
	 * Retrieve decrypted credentials for a given mode (or active mode).
	 *
	 * Automatically migrates legacy single-mode storage on first read.
	 *
	 * @param string|null $mode Optional mode ('test' or 'live'). Defaults to active mode.
	 * @return array{secret_key: ?string, publishable_key: ?string, webhook_secret: ?string}
	 */
	public static function get_credentials( ?string $mode = null ): array {
		$target_mode = $mode ? strtolower( trim( $mode ) ) : self::get_active_mode();
		$option_name = self::get_option_name_for_mode( $target_mode );

		$stored = get_option( $option_name );

		// Legacy storage fallback and automatic migration
		if ( empty( $stored ) ) {
			$legacy = get_option( Constants::OPTION_STRIPE_CREDENTIALS );
			if ( ! empty( $legacy ) ) {
				$dec = self::decrypt( $legacy );
				if ( $dec['success'] && ! empty( $dec['credentials']['secret_key'] ) ) {
					$key = $dec['credentials']['secret_key'];
					$is_test_key = str_starts_with( $key, 'sk_test_' ) || str_starts_with( $key, 'rk_test_' );
					$is_live_key = str_starts_with( $key, 'sk_live_' ) || str_starts_with( $key, 'rk_live_' );

					if ( ( $is_test_key && Constants::STRIPE_MODE_TEST === $target_mode ) ||
					     ( $is_live_key && Constants::STRIPE_MODE_LIVE === $target_mode ) ) {
						// Auto-migrate legacy payload into mode-specific option
						update_option( $option_name, $legacy, false );
						return $dec['credentials'];
					}
				}
			}
		}

		$res = self::decrypt( $stored );
		if ( $res['success'] && ! empty( $res['credentials'] ) ) {
			return $res['credentials'];
		}

		return array(
			'secret_key'      => null,
			'publishable_key' => null,
			'webhook_secret'  => null,
		);
	}

	/**
	 * Retrieve the decrypted Secret API Key for active or specified mode.
	 *
	 * @param string|null $mode Optional mode ('test' or 'live'). Defaults to active mode.
	 * @return string|null
	 */
	public static function get_secret_key( ?string $mode = null ): ?string {
		$creds = self::get_credentials( $mode );
		return $creds['secret_key'] ?? null;
	}

	/**
	 * Retrieve the decrypted Publishable API Key for active or specified mode.
	 *
	 * @param string|null $mode Optional mode ('test' or 'live'). Defaults to active mode.
	 * @return string|null
	 */
	public static function get_publishable_key( ?string $mode = null ): ?string {
		$creds = self::get_credentials( $mode );
		return $creds['publishable_key'] ?? null;
	}

	/**
	 * Retrieve the decrypted Webhook Signing Secret for active or specified mode.
	 *
	 * @param string|null $mode Optional mode ('test' or 'live'). Defaults to active mode.
	 * @return string|null
	 */
	public static function get_webhook_secret( ?string $mode = null ): ?string {
		$creds = self::get_credentials( $mode );
		return $creds['webhook_secret'] ?? null;
	}

	/**
	 * Retrieve sanitized status information for UI without exposing secrets.
	 *
	 * @param string|null $mode Optional mode ('test' or 'live'). Defaults to active mode.
	 * @return array
	 */
	public static function get_status( ?string $mode = null ): array {
		$target_mode = $mode ? strtolower( trim( $mode ) ) : self::get_active_mode();
		$option_name = self::get_option_name_for_mode( $target_mode );
		$stored      = get_option( $option_name );
		$env         = self::get_environment();

		// Check legacy if empty
		if ( empty( $stored ) ) {
			$legacy = get_option( Constants::OPTION_STRIPE_CREDENTIALS );
			if ( ! empty( $legacy ) ) {
				$dec = self::decrypt( $legacy );
				if ( $dec['success'] && ! empty( $dec['credentials']['secret_key'] ) ) {
					$key         = $dec['credentials']['secret_key'];
					$is_test_key = str_starts_with( $key, 'sk_test_' ) || str_starts_with( $key, 'rk_test_' );
					$is_live_key = str_starts_with( $key, 'sk_live_' ) || str_starts_with( $key, 'rk_live_' );

					if ( ( $is_test_key && Constants::STRIPE_MODE_TEST === $target_mode ) ||
					     ( $is_live_key && Constants::STRIPE_MODE_LIVE === $target_mode ) ) {
						$stored = $legacy;
					}
				}
			}
		}

		$crypto_check = self::validate_crypto_support();
		$salts_check  = self::validate_salts();
		$is_ssl       = is_ssl();

		if ( empty( $stored ) ) {
			return array(
				'is_configured'        => false,
				'has_secret_key'       => false,
				'has_publishable_key'  => false,
				'has_webhook_secret'   => false,
				'masked_secret'        => '',
				'masked_publishable'   => '',
				'masked_webhook'       => '',
				'environment'          => $env,
				'mode'                 => $target_mode,
				'is_active_mode'       => ( $target_mode === self::get_active_mode() ),
				'updated_at'           => null,
				'crypto_supported'     => $crypto_check['supported'],
				'salts_valid'          => $salts_check['valid'],
				'ssl_active'           => $is_ssl,
				'error'                => null,
			);
		}

		$decrypt_res = self::decrypt( $stored );

		if ( ! $decrypt_res['success'] ) {
			return array(
				'is_configured'        => true,
				'has_secret_key'       => false,
				'has_publishable_key'  => false,
				'has_webhook_secret'   => false,
				'masked_secret'        => '',
				'masked_publishable'   => '',
				'masked_webhook'       => '',
				'environment'          => $env,
				'mode'                 => $target_mode,
				'is_active_mode'       => ( $target_mode === self::get_active_mode() ),
				'updated_at'           => is_array( $stored ) ? ( $stored['updated_at'] ?? null ) : null,
				'crypto_supported'     => $crypto_check['supported'],
				'salts_valid'          => $salts_check['valid'],
				'ssl_active'           => $is_ssl,
				'error'                => $decrypt_res['error'] ?? __( 'Failed to decrypt credentials.', 'fraud-tenant-core' ),
			);
		}

		$creds = $decrypt_res['credentials'] ?? array();

		return array(
			'is_configured'        => ! empty( $creds['secret_key'] ),
			'has_secret_key'       => ! empty( $creds['secret_key'] ),
			'has_publishable_key'  => ! empty( $creds['publishable_key'] ),
			'has_webhook_secret'   => ! empty( $creds['webhook_secret'] ),
			'masked_secret'        => self::mask_key( $creds['secret_key'] ?? '', 'secret' ),
			'masked_publishable'   => self::mask_key( $creds['publishable_key'] ?? '', 'publishable' ),
			'masked_webhook'       => self::mask_key( $creds['webhook_secret'] ?? '', 'webhook' ),
			'environment'          => $env,
			'mode'                 => $target_mode,
			'is_active_mode'       => ( $target_mode === self::get_active_mode() ),
			'updated_at'           => is_array( $stored ) ? ( $stored['updated_at'] ?? null ) : null,
			'crypto_supported'     => $crypto_check['supported'],
			'salts_valid'          => $salts_check['valid'],
			'ssl_active'           => $is_ssl,
			'error'                => null,
		);
	}

	/**
	 * Retrieve full dual-mode status covering both Test and Live environments.
	 *
	 * @return array
	 */
	public static function get_full_dual_status(): array {
		$active_mode  = self::get_active_mode();
		$env          = self::get_environment();
		$crypto_check = self::validate_crypto_support();
		$salts_check  = self::validate_salts();
		$is_ssl       = is_ssl();

		return array(
			'active_mode'      => $active_mode,
			'environment'      => $env,
			'is_production'    => self::is_production(),
			'qa_restricted'    => self::is_qa_mode_restricted(),
			'crypto_supported' => $crypto_check['supported'],
			'salts_valid'      => $salts_check['valid'],
			'ssl_active'       => $is_ssl,
			'test'             => self::get_status( Constants::STRIPE_MODE_TEST ),
			'live'             => self::get_status( Constants::STRIPE_MODE_LIVE ),
		);
	}

	/**
	 * Save updated Stripe credentials for a specific mode.
	 *
	 * Rules:
	 * - Only administrators with 'manage_options' may call this method.
	 * - In production environments, HTTPS (is_ssl()) is strictly enforced for live credentials.
	 * - Blank fields preserve existing saved credentials for that mode.
	 * - Format validation is enforced per target mode.
	 * - Never writes plaintext to the database. Autoloading is disabled ('no').
	 * - Audit log records actor, timestamp, mode, and configuration action (NO SECRETS LOGGED).
	 *
	 * @param array  $input    Associative array containing optional 'secret_key', 'publishable_key', 'webhook_secret', 'mode'.
	 * @param int    $admin_id User ID of the administrator making the change.
	 * @param string $mode     Optional mode parameter ('test' or 'live').
	 * @return array{success: bool, message: string, error?: string}
	 */
	public static function save_credentials( array $input, int $admin_id, string $mode = '' ): array {
		// 1. Authorization check
		if ( $admin_id <= 0 || ! user_can( $admin_id, 'manage_options' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Unauthorized. Administrator capability is required to manage Stripe credentials.', 'fraud-tenant-core' ),
				'message' => __( 'Unauthorized access.', 'fraud-tenant-core' ),
			);
		}

		// 2. Resolve target mode
		$target_mode = ! empty( $mode )
			? strtolower( trim( $mode ) )
			: ( ! empty( $input['mode'] ) ? strtolower( trim( (string) $input['mode'] ) ) : self::get_active_mode() );

		if ( ! in_array( $target_mode, array( Constants::STRIPE_MODE_TEST, Constants::STRIPE_MODE_LIVE ), true ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Invalid Stripe mode specified.', 'fraud-tenant-core' ),
				'message' => __( 'Invalid mode.', 'fraud-tenant-core' ),
			);
		}

		// 3. HTTPS enforcement in production for live mode
		if ( self::is_production() && Constants::STRIPE_MODE_LIVE === $target_mode && ! is_ssl() ) {
			return array(
				'success' => false,
				'error'   => __( 'HTTPS is strictly required for Stripe Live credential management in production environment.', 'fraud-tenant-core' ),
				'message' => __( 'HTTPS required.', 'fraud-tenant-core' ),
			);
		}

		// 4. Environment check
		$env = self::get_environment();
		if ( 'invalid' === $env ) {
			return array(
				'success' => false,
				'error'   => __( 'Invalid environment configuration. Refusing to save credentials.', 'fraud-tenant-core' ),
				'message' => __( 'Invalid environment.', 'fraud-tenant-core' ),
			);
		}

		// 5. Crypto support and salts check
		$crypto_check = self::validate_crypto_support();
		if ( ! $crypto_check['supported'] ) {
			return array(
				'success' => false,
				'error'   => $crypto_check['error'] ?? __( 'Cryptographic functions unavailable.', 'fraud-tenant-core' ),
				'message' => __( 'Cryptographic support failure.', 'fraud-tenant-core' ),
			);
		}

		$salts_check = self::validate_salts();
		if ( ! $salts_check['valid'] ) {
			return array(
				'success' => false,
				'error'   => $salts_check['error'] ?? __( 'WordPress salts are invalid.', 'fraud-tenant-core' ),
				'message' => __( 'WordPress salt validation failure.', 'fraud-tenant-core' ),
			);
		}

		// 6. Retrieve existing saved credentials for this mode to support preservation of blank fields
		$existing = self::get_credentials( $target_mode );

		$merged = array(
			'secret_key'      => $existing['secret_key'] ?? null,
			'publishable_key' => $existing['publishable_key'] ?? null,
			'webhook_secret'  => $existing['webhook_secret'] ?? null,
		);

		// 7. Process and validate input fields
		foreach ( array( 'secret_key', 'publishable_key', 'webhook_secret' ) as $field ) {
			if ( array_key_exists( $field, $input ) ) {
				$val = trim( (string) $input[ $field ] );
				if ( '' !== $val ) {
					$validation = self::validate_key_format( $field, $val, $target_mode );
					if ( ! $validation['valid'] ) {
						return array(
							'success' => false,
							'error'   => $validation['error'] ?? __( 'Invalid key format.', 'fraud-tenant-core' ),
							'message' => $validation['error'] ?? __( 'Invalid key format.', 'fraud-tenant-core' ),
						);
					}
					$merged[ $field ] = $val;
				}
				// If empty string, $merged[$field] remains $existing[$field] (preservation rule)
			}
		}

		// 8. Ensure at least one credential exists
		if ( empty( $merged['secret_key'] ) && empty( $merged['publishable_key'] ) && empty( $merged['webhook_secret'] ) ) {
			return array(
				'success' => false,
				'error'   => __( 'No credentials provided to save. If you wish to remove credentials, use the Remove action.', 'fraud-tenant-core' ),
				'message' => __( 'No credentials provided.', 'fraud-tenant-core' ),
			);
		}

		// 9. Encrypt credentials
		$enc_res = self::encrypt( $merged, $target_mode );
		if ( ! $enc_res['success'] || empty( $enc_res['payload'] ) ) {
			return array(
				'success' => false,
				'error'   => $enc_res['error'] ?? __( 'Encryption failed.', 'fraud-tenant-core' ),
				'message' => __( 'Encryption failed.', 'fraud-tenant-core' ),
			);
		}

		// 10. Store only ciphertext in dedicated mode-specific option with autoload disabled
		$option_name = self::get_option_name_for_mode( $target_mode );
		if ( false === get_option( $option_name ) ) {
			add_option( $option_name, $enc_res['payload'], '', 'no' );
		} else {
			update_option( $option_name, $enc_res['payload'], false );
		}

		// Also update legacy option if active mode matches for backward compatibility
		if ( $target_mode === self::get_active_mode() ) {
			update_option( Constants::OPTION_STRIPE_CREDENTIALS, $enc_res['payload'], false );
		}

		// 11. Audit event (NO SECRETS LOGGED)
		AuditLogger::log(
			'stripe_credentials_updated',
			'stripe_config',
			null,
			array(
				'environment'         => $env,
				'mode'                => $target_mode,
				'action'              => 'save',
				'has_secret_key'      => ! empty( $merged['secret_key'] ),
				'has_publishable_key' => ! empty( $merged['publishable_key'] ),
				'has_webhook_secret'  => ! empty( $merged['webhook_secret'] ),
			),
			$admin_id
		);

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: %s: mode name ('Test' or 'Live') */
				__( 'Stripe %s credentials successfully encrypted and saved.', 'fraud-tenant-core' ),
				ucfirst( $target_mode )
			),
		);
	}

	/**
	 * Remove saved Stripe credentials for a given mode or all modes.
	 *
	 * @param int    $admin_id User ID of the administrator removing credentials.
	 * @param string $mode     'test', 'live', or 'all' (defaults to active mode).
	 * @return array{success: bool, message: string}
	 */
	public static function remove_credentials( int $admin_id, string $mode = '' ): array {
		if ( $admin_id <= 0 || ! user_can( $admin_id, 'manage_options' ) ) {
			return array(
				'success' => false,
				'message' => __( 'Unauthorized. Administrator capability is required.', 'fraud-tenant-core' ),
			);
		}

		$target_mode = ! empty( $mode ) ? strtolower( trim( $mode ) ) : self::get_active_mode();

		if ( 'all' === $target_mode ) {
			delete_option( Constants::OPTION_STRIPE_CREDENTIALS_TEST );
			delete_option( Constants::OPTION_STRIPE_CREDENTIALS_LIVE );
			delete_option( Constants::OPTION_STRIPE_CREDENTIALS );
		} elseif ( Constants::STRIPE_MODE_LIVE === $target_mode ) {
			delete_option( Constants::OPTION_STRIPE_CREDENTIALS_LIVE );
			if ( Constants::STRIPE_MODE_LIVE === self::get_active_mode() ) {
				delete_option( Constants::OPTION_STRIPE_CREDENTIALS );
			}
		} else {
			delete_option( Constants::OPTION_STRIPE_CREDENTIALS_TEST );
			if ( Constants::STRIPE_MODE_TEST === self::get_active_mode() ) {
				delete_option( Constants::OPTION_STRIPE_CREDENTIALS );
			}
		}

		AuditLogger::log(
			'stripe_credentials_removed',
			'stripe_config',
			null,
			array(
				'environment' => self::get_environment(),
				'mode'        => $target_mode,
				'action'      => 'remove',
			),
			$admin_id
		);

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: %s: mode name */
				__( 'Stripe %s credentials successfully removed.', 'fraud-tenant-core' ),
				ucfirst( $target_mode )
			),
		);
	}
}
