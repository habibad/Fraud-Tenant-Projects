<?php
/**
 * Stripe API Client and Service Integration.
 *
 * Provides authenticated communication with Stripe API endpoints for customer
 * management, price verification, hosted Checkout Session creation, and
 * Customer Portal sessions with full dual-mode support (Test vs. Live).
 *
 * @package FraudTenant\Services
 */

namespace FraudTenant\Services;

use FraudTenant\Utils\Constants;
use FraudTenant\Utils\Sanitizer;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class StripeService {

	/**
	 * Stripe API Base URL.
	 */
	public const API_BASE = 'https://api.stripe.com/v1';

	/**
	 * Configured Stripe API Version.
	 */
	public const API_VERSION = '2024-06-20';

	/**
	 * Request timeout in seconds.
	 */
	public const REQUEST_TIMEOUT = 20;

	/**
	 * User metadata keys storing Stripe Customer IDs by mode.
	 */
	public const USER_META_CUSTOMER_ID      = '_ft_stripe_customer_id'; // Legacy fallback
	public const USER_META_CUSTOMER_ID_TEST = '_ft_stripe_customer_id_test';
	public const USER_META_CUSTOMER_ID_LIVE = '_ft_stripe_customer_id_live';

	/**
	 * Retrieve metadata key for customer ID based on operational mode.
	 *
	 * @param string|null $mode Optional mode ('test' or 'live'). Defaults to active mode.
	 * @return string Meta key name.
	 */
	public static function get_customer_id_meta_key( ?string $mode = null ): string {
		$target_mode = $mode ? strtolower( trim( $mode ) ) : StripeCredentialService::get_active_mode();
		return Constants::STRIPE_MODE_LIVE === $target_mode
			? self::USER_META_CUSTOMER_ID_LIVE
			: self::USER_META_CUSTOMER_ID_TEST;
	}

	/**
	 * Retrieve stored Stripe Customer ID for a given user and mode.
	 *
	 * @param int         $user_id User ID.
	 * @param string|null $mode    Optional mode. Defaults to active mode.
	 * @return string|null Customer ID or null.
	 */
	public static function get_customer_id( int $user_id, ?string $mode = null ): ?string {
		if ( $user_id <= 0 ) {
			return null;
		}

		$target_mode = $mode ? strtolower( trim( $mode ) ) : StripeCredentialService::get_active_mode();
		$meta_key    = self::get_customer_id_meta_key( $target_mode );
		$val         = get_user_meta( $user_id, $meta_key, true );

		if ( ! empty( $val ) && is_string( $val ) ) {
			return trim( $val );
		}

		// Fallback for test mode to legacy key
		if ( Constants::STRIPE_MODE_TEST === $target_mode ) {
			$legacy = get_user_meta( $user_id, self::USER_META_CUSTOMER_ID, true );
			if ( ! empty( $legacy ) && is_string( $legacy ) ) {
				return trim( $legacy );
			}
		}

		return null;
	}

	/**
	 * Store Stripe Customer ID for a given user and mode.
	 *
	 * @param int         $user_id     User ID.
	 * @param string      $customer_id Stripe customer ID.
	 * @param string|null $mode        Optional mode. Defaults to active mode.
	 */
	public static function set_customer_id( int $user_id, string $customer_id, ?string $mode = null ): void {
		if ( $user_id <= 0 ) {
			return;
		}

		$target_mode = $mode ? strtolower( trim( $mode ) ) : StripeCredentialService::get_active_mode();
		$meta_key    = self::get_customer_id_meta_key( $target_mode );
		$cleaned     = sanitize_text_field( trim( $customer_id ) );

		update_user_meta( $user_id, $meta_key, $cleaned );

		if ( Constants::STRIPE_MODE_TEST === $target_mode ) {
			update_user_meta( $user_id, self::USER_META_CUSTOMER_ID, $cleaned );
		}
	}

	/**
	 * Delete Stripe Customer ID for a given user and mode.
	 *
	 * @param int         $user_id User ID.
	 * @param string|null $mode    Optional mode. Defaults to active mode.
	 */
	public static function delete_customer_id( int $user_id, ?string $mode = null ): void {
		if ( $user_id <= 0 ) {
			return;
		}

		$target_mode = $mode ? strtolower( trim( $mode ) ) : StripeCredentialService::get_active_mode();
		$meta_key    = self::get_customer_id_meta_key( $target_mode );

		delete_user_meta( $user_id, $meta_key );

		if ( Constants::STRIPE_MODE_TEST === $target_mode ) {
			delete_user_meta( $user_id, self::USER_META_CUSTOMER_ID );
		}
	}

	/**
	 * Retrieve configured Stripe Secret Key for active or specified mode.
	 *
	 * @param string|null $mode Optional mode ('test' or 'live').
	 * @return string|null Secret key or null.
	 */
	public static function get_secret_key( ?string $mode = null ): ?string {
		$target_mode = $mode ? strtolower( trim( $mode ) ) : StripeCredentialService::get_active_mode();

		// 1. Primary: Secure encrypted credentials
		$stored_key = StripeCredentialService::get_secret_key( $target_mode );
		if ( ! empty( $stored_key ) ) {
			return $stored_key;
		}

		// In production, fallback to constants/env is strictly disabled to prevent dashboard bypass
		if ( StripeCredentialService::is_production() ) {
			return null;
		}

		// 2. Fallback for test mode only in non-production environments
		if ( Constants::STRIPE_MODE_TEST === $target_mode ) {
			if ( defined( 'STRIPE_SECRET_KEY' ) && is_string( constant( 'STRIPE_SECRET_KEY' ) ) && '' !== trim( constant( 'STRIPE_SECRET_KEY' ) ) ) {
				return trim( constant( 'STRIPE_SECRET_KEY' ) );
			}
			$env_key = getenv( 'STRIPE_SECRET_KEY' );
			if ( is_string( $env_key ) && '' !== trim( $env_key ) ) {
				return trim( $env_key );
			}
		}

		return null;
	}

	/**
	 * Retrieve configured Stripe Publishable Key for active or specified mode.
	 *
	 * @param string|null $mode Optional mode ('test' or 'live').
	 * @return string|null Publishable key or null.
	 */
	public static function get_publishable_key( ?string $mode = null ): ?string {
		$target_mode = $mode ? strtolower( trim( $mode ) ) : StripeCredentialService::get_active_mode();

		// 1. Primary: Secure encrypted credentials
		$stored_key = StripeCredentialService::get_publishable_key( $target_mode );
		if ( ! empty( $stored_key ) ) {
			return $stored_key;
		}

		// In production, fallback to constants/env is strictly disabled to prevent dashboard bypass
		if ( StripeCredentialService::is_production() ) {
			return null;
		}

		// 2. Fallback for test mode only
		if ( Constants::STRIPE_MODE_TEST === $target_mode ) {
			if ( defined( 'STRIPE_PUBLISHABLE_KEY' ) && is_string( constant( 'STRIPE_PUBLISHABLE_KEY' ) ) && '' !== trim( constant( 'STRIPE_PUBLISHABLE_KEY' ) ) ) {
				return trim( constant( 'STRIPE_PUBLISHABLE_KEY' ) );
			}
			$env_key = getenv( 'STRIPE_PUBLISHABLE_KEY' );
			if ( is_string( $env_key ) && '' !== trim( $env_key ) ) {
				return trim( $env_key );
			}
		}

		return null;
	}

	/**
	 * Retrieve configured Stripe Webhook Signing Secret for active or specified mode.
	 *
	 * @param string|null $mode Optional mode ('test' or 'live').
	 * @return string|null Webhook secret or null.
	 */
	public static function get_webhook_secret( ?string $mode = null ): ?string {
		$target_mode = $mode ? strtolower( trim( $mode ) ) : StripeCredentialService::get_active_mode();

		$stored_secret = StripeCredentialService::get_webhook_secret( $target_mode );
		if ( ! empty( $stored_secret ) ) {
			return $stored_secret;
		}

		if ( StripeCredentialService::is_production() ) {
			return null;
		}

		if ( Constants::STRIPE_MODE_TEST === $target_mode ) {
			if ( defined( 'STRIPE_WEBHOOK_SECRET' ) && is_string( constant( 'STRIPE_WEBHOOK_SECRET' ) ) && '' !== trim( constant( 'STRIPE_WEBHOOK_SECRET' ) ) ) {
				return trim( constant( 'STRIPE_WEBHOOK_SECRET' ) );
			}
			$env_secret = getenv( 'STRIPE_WEBHOOK_SECRET' );
			if ( is_string( $env_secret ) && '' !== trim( $env_secret ) ) {
				return trim( $env_secret );
			}
		}

		return null;
	}

	/**
	 * Determine current environment type.
	 *
	 * @return string Environment slug.
	 */
	public static function get_environment(): string {
		return StripeCredentialService::get_environment();
	}

	/**
	 * Retrieve mode-appropriate Stripe Price ID for a plan.
	 *
	 * @param object      $plan Plan database row object.
	 * @param string|null $mode Optional mode.
	 * @return string|null Price ID.
	 */
	public static function get_plan_price_id( object $plan, ?string $mode = null ): ?string {
		$target_mode = $mode ? strtolower( trim( $mode ) ) : StripeCredentialService::get_active_mode();

		if ( Constants::STRIPE_MODE_LIVE === $target_mode ) {
			return ! empty( $plan->stripe_live_price_id ) ? trim( (string) $plan->stripe_live_price_id ) : null;
		}

		if ( ! empty( $plan->stripe_test_price_id ) ) {
			return trim( (string) $plan->stripe_test_price_id );
		}

		return ! empty( $plan->stripe_price_id ) ? trim( (string) $plan->stripe_price_id ) : null;
	}

	/**
	 * Retrieve mode-appropriate Stripe Product ID for a plan.
	 *
	 * @param object      $plan Plan database row object.
	 * @param string|null $mode Optional mode.
	 * @return string|null Product ID.
	 */
	public static function get_plan_product_id( object $plan, ?string $mode = null ): ?string {
		$target_mode = $mode ? strtolower( trim( $mode ) ) : StripeCredentialService::get_active_mode();

		if ( Constants::STRIPE_MODE_LIVE === $target_mode ) {
			return ! empty( $plan->stripe_live_product_id ) ? trim( (string) $plan->stripe_live_product_id ) : null;
		}

		if ( ! empty( $plan->stripe_test_product_id ) ) {
			return trim( (string) $plan->stripe_test_product_id );
		}

		return ! empty( $plan->stripe_product_id ) ? trim( (string) $plan->stripe_product_id ) : null;
	}

	/**
	 * Validate Stripe API credentials against mode policy.
	 *
	 * @param string|null $mode Optional mode to validate. Defaults to active mode.
	 * @return array{valid: bool, error?: string, code?: string}
	 */
	public static function validate_credentials( ?string $mode = null ): array {
		$target_mode = $mode ? strtolower( trim( $mode ) ) : StripeCredentialService::get_active_mode();
		$key         = self::get_secret_key( $target_mode );

		if ( empty( $key ) ) {
			return array(
				'valid' => false,
				'error' => sprintf(
					/* translators: %s: mode name */
					__( 'Stripe secret key is not configured for %s mode.', 'fraud-tenant-core' ),
					ucfirst( $target_mode )
				),
				'code'  => 'stripe_config_missing',
			);
		}

		$check = StripeCredentialService::validate_key_format( 'secret_key', $key, $target_mode );
		if ( ! $check['valid'] ) {
			return array(
				'valid' => false,
				'error' => $check['error'] ?? __( 'Invalid Stripe secret key format.', 'fraud-tenant-core' ),
				'code'  => 'stripe_invalid_key_format',
			);
		}

		return array( 'valid' => true );
	}

	/**
	 * Perform an authenticated HTTP request to the Stripe API.
	 *
	 * Uses WordPress HTTP API (wp_remote_request). Never logs authorization headers,
	 * secrets, or raw payment data.
	 *
	 * @param string      $method          HTTP method ('GET', 'POST', 'DELETE').
	 * @param string      $endpoint        API endpoint relative to /v1.
	 * @param array       $data            Payload or query arguments.
	 * @param string|null $idempotency_key Optional idempotency key.
	 * @param string|null $mode            Optional operational mode.
	 * @return array{success: bool, data?: array, error?: string, code?: string, status?: int}
	 */
	public static function request(
		string $method,
		string $endpoint,
		array $data = array(),
		?string $idempotency_key = null,
		?string $mode = null
	): array {
		$target_mode = $mode ? strtolower( trim( $mode ) ) : StripeCredentialService::get_active_mode();
		$cred_check  = self::validate_credentials( $target_mode );

		if ( ! $cred_check['valid'] ) {
			return array(
				'success' => false,
				'error'   => $cred_check['error'] ?? 'Stripe credentials invalid',
				'code'    => $cred_check['code'] ?? 'stripe_credentials_invalid',
				'status'  => 500,
			);
		}

		$key     = self::get_secret_key( $target_mode );
		$method  = strtoupper( $method );
		$url     = self::API_BASE . '/' . ltrim( $endpoint, '/' );
		$headers = array(
			'Authorization'  => 'Bearer ' . $key,
			'Stripe-Version' => self::API_VERSION,
			'User-Agent'     => 'FraudTenant/' . Constants::VERSION . ' WordPress/' . ( $GLOBALS['wp_version'] ?? '6.0' ),
		);

		if ( ! empty( $idempotency_key ) ) {
			$headers['Idempotency-Key'] = sanitize_text_field( $idempotency_key );
		}

		$args = array(
			'method'      => $method,
			'timeout'     => self::REQUEST_TIMEOUT,
			'sslverify'   => true,
			'redirection' => 0,
			'httpversion' => '1.1',
			'blocking'    => true,
			'headers'     => $headers,
		);

		if ( 'GET' === $method ) {
			if ( ! empty( $data ) ) {
				$url = add_query_arg( $data, $url );
			}
		} else {
			$args['body'] = http_build_query( $data );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			AuditLogger::log(
				'stripe_api_request_failed',
				'stripe_api',
				null,
				array(
					'endpoint' => sanitize_text_field( $endpoint ),
					'method'   => $method,
					'mode'     => $target_mode,
					'error'    => sanitize_text_field( $response->get_error_message() ),
				)
			);

			return array(
				'success' => false,
				'error'   => $response->get_error_message(),
				'code'    => 'stripe_transport_error',
				'status'  => 500,
			);
		}

		$http_code = (int) wp_remote_retrieve_response_code( $response );
		$raw_body  = wp_remote_retrieve_body( $response );
		$body      = json_decode( $raw_body, true );

		if ( ! is_array( $body ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Invalid response returned by Stripe API.', 'fraud-tenant-core' ),
				'code'    => 'stripe_invalid_response',
				'status'  => $http_code,
			);
		}

		if ( $http_code < 200 || $http_code >= 300 ) {
			$error_obj       = $body['error'] ?? array();
			$sanitized_error = isset( $error_obj['message'] ) ? sanitize_text_field( (string) $error_obj['message'] ) : __( 'Stripe API error occurred.', 'fraud-tenant-core' );
			$error_code      = isset( $error_obj['code'] ) ? sanitize_text_field( (string) $error_obj['code'] ) : 'stripe_api_error';

			AuditLogger::log(
				'stripe_api_error_response',
				'stripe_api',
				null,
				array(
					'endpoint'   => sanitize_text_field( $endpoint ),
					'method'     => $method,
					'mode'       => $target_mode,
					'status'     => $http_code,
					'error_code' => $error_code,
					'message'    => $sanitized_error,
				)
			);

			return array(
				'success' => false,
				'error'   => $sanitized_error,
				'code'    => sanitize_key( $error_code ),
				'status'  => $http_code,
			);
		}

		return array(
			'success' => true,
			'data'    => $body,
			'status'  => $http_code,
		);
	}

	/**
	 * Create a new Stripe Customer for a WordPress user.
	 *
	 * @param int         $user_id         WordPress User ID.
	 * @param array       $metadata        Optional additional metadata.
	 * @param string|null $idempotency_key Optional custom idempotency key.
	 * @param string|null $mode            Optional mode.
	 * @return array{success: bool, customer_id?: string, data?: array, error?: string, code?: string}
	 */
	public static function create_customer(
		int $user_id,
		array $metadata = array(),
		?string $idempotency_key = null,
		?string $mode = null
	): array {
		$target_mode = $mode ? strtolower( trim( $mode ) ) : StripeCredentialService::get_active_mode();
		$user        = get_userdata( $user_id );

		if ( ! $user ) {
			return array(
				'success' => false,
				'error'   => __( 'Invalid user ID.', 'fraud-tenant-core' ),
				'code'    => 'invalid_user',
			);
		}

		if ( empty( $idempotency_key ) ) {
			$salt            = defined( 'AUTH_KEY' ) ? constant( 'AUTH_KEY' ) : 'ft_cust_fallback';
			// Site-scoped so two installations sharing salts/Stripe account never collide.
			$idempotency_key = 'ft_cust_' . $target_mode . '_' . $user_id . '_' . substr( hash( 'sha256', $salt . '|' . home_url() . '|' . $user_id . '|' . $target_mode ), 0, 16 );
		}

		$payload = array(
			'email'    => sanitize_email( $user->user_email ),
			'name'     => sanitize_text_field( $user->display_name ),
			'metadata' => array_merge(
				array(
					'wp_user_id'  => (string) $user_id,
					'environment' => self::get_environment(),
					'mode'        => $target_mode,
				),
				$metadata
			),
		);

		$res = self::request( 'POST', 'customers', $payload, $idempotency_key, $target_mode );

		if ( ! $res['success'] || empty( $res['data']['id'] ) ) {
			return array(
				'success' => false,
				'error'   => $res['error'] ?? __( 'Failed creating Stripe customer.', 'fraud-tenant-core' ),
				'code'    => $res['code'] ?? 'customer_creation_failed',
			);
		}

		$customer_id = (string) $res['data']['id'];
		self::set_customer_id( $user_id, $customer_id, $target_mode );

		AuditLogger::log(
			'stripe_customer_created',
			'stripe_customer',
			$user_id,
			array(
				'customer_id' => $customer_id,
				'mode'        => $target_mode,
			),
			$user_id
		);

		return array(
			'success'     => true,
			'customer_id' => $customer_id,
			'data'        => $res['data'],
		);
	}

	/**
	 * Retrieve a Stripe Customer object by Customer ID.
	 *
	 * @param string      $customer_id Stripe customer ID (cus_...).
	 * @param string|null $mode        Optional mode.
	 * @return array{success: bool, data?: array, deleted?: bool, error?: string, code?: string}
	 */
	public static function get_customer( string $customer_id, ?string $mode = null ): array {
		$target_mode = $mode ? strtolower( trim( $mode ) ) : StripeCredentialService::get_active_mode();
		$customer_id = sanitize_text_field( trim( $customer_id ) );

		if ( empty( $customer_id ) || ! preg_match( '/^cus_[a-zA-Z0-9_]+$/', $customer_id ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Invalid Stripe customer ID format.', 'fraud-tenant-core' ),
				'code'    => 'invalid_customer_id',
			);
		}

		$res = self::request( 'GET', 'customers/' . $customer_id, array(), null, $target_mode );
		if ( ! $res['success'] || empty( $res['data'] ) ) {
			return array(
				'success' => false,
				'error'   => $res['error'] ?? __( 'Failed retrieving Stripe customer.', 'fraud-tenant-core' ),
				'code'    => $res['code'] ?? 'customer_not_found',
			);
		}

		$is_deleted = ! empty( $res['data']['deleted'] );

		return array(
			'success' => true,
			'data'    => $res['data'],
			'deleted' => $is_deleted,
		);
	}

	/**
	 * Retrieve or create a Stripe Customer for an approved Property Owner in active or specified mode.
	 *
	 * @param int         $user_id WordPress User ID.
	 * @param string|null $mode    Optional mode.
	 * @return array{success: bool, customer_id?: string, error?: string, code?: string}
	 */
	public static function get_or_create_customer( int $user_id, ?string $mode = null ): array {
		if ( $user_id <= 0 ) {
			return array(
				'success' => false,
				'error'   => __( 'Invalid user ID.', 'fraud-tenant-core' ),
				'code'    => 'invalid_user',
			);
		}

		$target_mode = $mode ? strtolower( trim( $mode ) ) : StripeCredentialService::get_active_mode();
		$existing_id = self::get_customer_id( $user_id, $target_mode );

		if ( ! empty( $existing_id ) ) {
			$cust_check = self::get_customer( $existing_id, $target_mode );
			$mode_ok    = $cust_check['success'] && ( ! empty( $cust_check['data']['livemode'] ) ) === ( Constants::STRIPE_MODE_LIVE === $target_mode );
			if ( $cust_check['success'] && empty( $cust_check['deleted'] ) && $mode_ok ) {
				if ( ! empty( $cust_check['data']['metadata']['wp_user_id'] ) ) {
					$meta_uid = (int) $cust_check['data']['metadata']['wp_user_id'];
					if ( $meta_uid !== $user_id ) {
						return array(
							'success' => false,
							'error'   => __( 'Customer ownership mismatch detected.', 'fraud-tenant-core' ),
							'code'    => 'customer_ownership_mismatch',
						);
					}
				}
				return array(
					'success'     => true,
					'customer_id' => $existing_id,
				);
			}
			self::delete_customer_id( $user_id, $target_mode );
			// A previously mapped customer was deleted/invalid: use a fresh idempotency key so
			// Stripe does not replay the earlier creation response for the deleted customer.
			return self::create_customer( $user_id, array(), 'ft_cust_' . $target_mode . '_' . $user_id . '_' . bin2hex( random_bytes( 8 ) ), $target_mode );
		}

		return self::create_customer( $user_id, array(), null, $target_mode );
	}

	/**
	 * Retrieve a Stripe Price object from Stripe API.
	 *
	 * @param string      $price_id Stripe Price ID (price_...).
	 * @param string|null $mode     Optional mode.
	 * @return array{success: bool, data?: array, error?: string, code?: string}
	 */
	public static function get_price( string $price_id, ?string $mode = null ): array {
		$target_mode = $mode ? strtolower( trim( $mode ) ) : StripeCredentialService::get_active_mode();
		$price_id    = sanitize_text_field( trim( $price_id ) );

		if ( empty( $price_id ) || ! preg_match( '/^price_[a-zA-Z0-9_]+$/', $price_id ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Invalid Stripe Price ID format.', 'fraud-tenant-core' ),
				'code'    => 'invalid_price_id',
			);
		}

		return self::request( 'GET', 'prices/' . $price_id, array(), null, $target_mode );
	}

	/**
	 * Validate a Stripe Price object against authoritative database plan.
	 *
	 * @param object      $plan         Database plan row object.
	 * @param array       $stripe_price Stripe Price object data.
	 * @param string|null $mode         Optional mode.
	 * @return array{valid: bool, error?: string, code?: string}
	 */
	public static function validate_price_against_plan( object $plan, array $stripe_price, ?string $mode = null ): array {
		$target_mode = $mode ? strtolower( trim( $mode ) ) : StripeCredentialService::get_active_mode();

		if ( empty( $stripe_price['active'] ) ) {
			return array(
				'valid' => false,
				'error' => __( 'The configured Stripe Price is inactive in Stripe.', 'fraud-tenant-core' ),
				'code'  => 'price_inactive',
			);
		}

		$expected_cents = (int) round( ( (float) $plan->price_amount ) * 100 );
		$actual_cents   = isset( $stripe_price['unit_amount'] ) ? (int) $stripe_price['unit_amount'] : -1;
		if ( $expected_cents !== $actual_cents ) {
			return array(
				'valid' => false,
				'error' => sprintf(
					/* translators: 1: expected price in cents, 2: actual price in cents */
					__( 'Price amount mismatch: database requires %1$d cents, but Stripe price is %2$d cents.', 'fraud-tenant-core' ),
					$expected_cents,
					$actual_cents
				),
				'code'  => 'price_amount_mismatch',
			);
		}

		$expected_currency = strtolower( trim( (string) $plan->currency ) );
		$actual_currency   = isset( $stripe_price['currency'] ) ? strtolower( trim( (string) $stripe_price['currency'] ) ) : '';
		if ( $expected_currency !== $actual_currency ) {
			return array(
				'valid' => false,
				'error' => sprintf(
					/* translators: 1: expected currency, 2: actual currency */
					__( 'Currency mismatch: database requires %1$s, but Stripe price is %2$s.', 'fraud-tenant-core' ),
					$expected_currency,
					$actual_currency
				),
				'code'  => 'currency_mismatch',
			);
		}

		if ( empty( $stripe_price['recurring'] ) || ! is_array( $stripe_price['recurring'] ) ) {
			return array(
				'valid' => false,
				'error' => __( 'The configured Stripe Price is not a recurring subscription price.', 'fraud-tenant-core' ),
				'code'  => 'price_not_recurring',
			);
		}

		$interval_map = array(
			'monthly' => 'month',
			'yearly'  => 'year',
		);

		$expected_interval = $interval_map[ $plan->billing_interval ] ?? '';
		$actual_interval   = $stripe_price['recurring']['interval'] ?? '';

		if ( empty( $expected_interval ) || $expected_interval !== $actual_interval ) {
			return array(
				'valid' => false,
				'error' => sprintf(
					/* translators: 1: expected interval, 2: actual interval */
					__( 'Billing interval mismatch: database requires %1$s, but Stripe price is %2$s.', 'fraud-tenant-core' ),
					$expected_interval,
					$actual_interval
				),
				'code'  => 'interval_mismatch',
			);
		}

		$actual_interval_count = isset( $stripe_price['recurring']['interval_count'] ) ? (int) $stripe_price['recurring']['interval_count'] : 0;
		if ( 1 !== $actual_interval_count ) {
			return array(
				'valid' => false,
				'error' => sprintf(
					/* translators: %d: actual interval count */
					__( 'Interval count mismatch: expected 1, received %d.', 'fraud-tenant-core' ),
					$actual_interval_count
				),
				'code'  => 'interval_count_mismatch',
			);
		}

		$expected_product = self::get_plan_product_id( $plan, $target_mode );
		if ( ! empty( $expected_product ) ) {
			$actual_product = is_string( $stripe_price['product'] ?? null ) ? trim( (string) $stripe_price['product'] ) : '';
			if ( ! empty( $actual_product ) && $expected_product !== $actual_product ) {
				return array(
					'valid' => false,
					'error' => sprintf(
						/* translators: 1: expected product, 2: actual product */
						__( 'Product ID mismatch: plan specifies %1$s, but Stripe price is associated with %2$s.', 'fraud-tenant-core' ),
						$expected_product,
						$actual_product
					),
					'code'  => 'product_mismatch',
				);
			}
		}

		return array( 'valid' => true );
	}

	/**
	 * Create a hosted Stripe Checkout Session for subscription checkout.
	 *
	 * @param int         $user_id         WordPress User ID.
	 * @param object      $plan            Authoritative database plan object.
	 * @param string      $success_url     Server-generated return success URL.
	 * @param string      $cancel_url      Server-generated return cancellation URL.
	 * @param string|null $idempotency_key Optional idempotency key override.
	 * @param string|null $attempt_id     Optional logical attempt ID.
	 * @param string|null $mode           Optional mode override.
	 * @return array{success: bool, session_id?: string, checkout_url?: string, data?: array, error?: string, code?: string}
	 */
	public static function create_checkout_session(
		int $user_id,
		object $plan,
		string $success_url,
		string $cancel_url,
		?string $idempotency_key = null,
		?string $attempt_id = null,
		?string $mode = null
	): array {
		$target_mode = $mode ? strtolower( trim( $mode ) ) : StripeCredentialService::get_active_mode();

		// 1. Get or create customer for this mode
		$cust_res = self::get_or_create_customer( $user_id, $target_mode );
		if ( ! $cust_res['success'] || empty( $cust_res['customer_id'] ) ) {
			return array(
				'success' => false,
				'error'   => $cust_res['error'] ?? __( 'Could not establish Stripe customer.', 'fraud-tenant-core' ),
				'code'    => $cust_res['code'] ?? 'customer_error',
			);
		}

		$customer_id = $cust_res['customer_id'];

		// 2. Validate Price ID exists on plan for this mode
		$price_id = self::get_plan_price_id( $plan, $target_mode );
		if ( empty( $price_id ) ) {
			return array(
				'success' => false,
				'error'   => sprintf(
					/* translators: %s: mode name */
					__( 'This membership plan is not yet configured with a Stripe Price ID for %s mode.', 'fraud-tenant-core' ),
					ucfirst( $target_mode )
				),
				'code'    => 'plan_missing_price_id',
			);
		}

		// 3. Retrieve and validate Price from Stripe
		$price_res = self::get_price( $price_id, $target_mode );
		if ( ! $price_res['success'] || empty( $price_res['data'] ) ) {
			return array(
				'success' => false,
				'error'   => $price_res['error'] ?? __( 'Could not retrieve price from Stripe.', 'fraud-tenant-core' ),
				'code'    => $price_res['code'] ?? 'price_fetch_failed',
			);
		}

		$val = self::validate_price_against_plan( $plan, $price_res['data'], $target_mode );
		if ( ! $val['valid'] ) {
			return array(
				'success' => false,
				'error'   => $val['error'] ?? __( 'Price validation failed.', 'fraud-tenant-core' ),
				'code'    => $val['code'] ?? 'price_validation_failed',
			);
		}

		// 4. Stable logical per-attempt idempotency key
		if ( empty( $idempotency_key ) ) {
			$attempt         = ! empty( $attempt_id ) ? sanitize_key( $attempt_id ) : bin2hex( random_bytes( 12 ) );
			$idempotency_key = 'ft_chk_' . $target_mode . '_' . $user_id . '_' . $plan->plan_code . '_' . $attempt;
		}

		// 5. Construct Checkout payload
		$payload = array(
			'mode'                    => 'subscription',
			'customer'                => $customer_id,
			'client_reference_id'     => (string) $user_id,
			'success_url'             => $success_url,
			'cancel_url'              => $cancel_url,
			'line_items'              => array(
				array(
					'price'    => $price_id,
					'quantity' => 1,
				),
			),
			'metadata'                => array(
				'wp_user_id'  => (string) $user_id,
				'plan_code'   => (string) $plan->plan_code,
				'plan_id'     => (string) $plan->id,
				'environment' => self::get_environment(),
				'mode'        => $target_mode,
			),
			'subscription_data'       => array(
				'metadata' => array(
					'wp_user_id'  => (string) $user_id,
					'plan_code'   => (string) $plan->plan_code,
					'plan_id'     => (string) $plan->id,
					'environment' => self::get_environment(),
					'mode'        => $target_mode,
				),
			),
		);

		$res = self::request( 'POST', 'checkout/sessions', $payload, $idempotency_key, $target_mode );

		if ( ! $res['success'] || empty( $res['data']['id'] ) || empty( $res['data']['url'] ) ) {
			return array(
				'success' => false,
				'error'   => $res['error'] ?? __( 'Failed creating Stripe Checkout Session.', 'fraud-tenant-core' ),
				'code'    => $res['code'] ?? 'checkout_session_failed',
			);
		}

		if ( ( ! empty( $res['data']['livemode'] ) ) !== ( Constants::STRIPE_MODE_LIVE === $target_mode ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Stripe mode mismatch on created Checkout Session.', 'fraud-tenant-core' ),
				'code'    => 'checkout_mode_mismatch',
			);
		}

		$session_id   = (string) $res['data']['id'];
		$checkout_url = (string) $res['data']['url'];

		AuditLogger::log(
			'stripe_checkout_session_created',
			'checkout_session',
			$user_id,
			array(
				'session_id'  => $session_id,
				'plan_code'   => $plan->plan_code,
				'customer_id' => $customer_id,
				'mode'        => $target_mode,
			),
			$user_id
		);

		return array(
			'success'      => true,
			'session_id'   => $session_id,
			'checkout_url' => $checkout_url,
			'data'         => $res['data'],
		);
	}

	/**
	 * Retrieve a Checkout Session from Stripe.
	 *
	 * @param string      $session_id Stripe Checkout Session ID (cs_...).
	 * @param string|null $mode       Optional mode.
	 * @return array{success: bool, data?: array, error?: string, code?: string}
	 */
	public static function get_checkout_session( string $session_id, ?string $mode = null ): array {
		$target_mode = $mode ? strtolower( trim( $mode ) ) : StripeCredentialService::get_active_mode();
		$session_id  = sanitize_text_field( trim( $session_id ) );

		if ( empty( $session_id ) || ! preg_match( '/^cs_[a-zA-Z0-9_]+$/', $session_id ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Invalid Checkout Session ID format.', 'fraud-tenant-core' ),
				'code'    => 'invalid_session_id',
			);
		}

		return self::request( 'GET', 'checkout/sessions/' . $session_id, array(), null, $target_mode );
	}

	/**
	 * Retrieve a Subscription from Stripe (authoritative remote state).
	 *
	 * The request is pinned to self::API_VERSION, so the response shape (root-level
	 * current_period_start/current_period_end) is stable regardless of the API version
	 * configured on the webhook endpoint that delivered the triggering event.
	 *
	 * @param string      $subscription_id Stripe Subscription ID (sub_...).
	 * @param string|null $mode            Optional mode.
	 * @return array{success: bool, data?: array, error?: string, code?: string}
	 */
	public static function get_subscription( string $subscription_id, ?string $mode = null ): array {
		$target_mode     = $mode ? strtolower( trim( $mode ) ) : StripeCredentialService::get_active_mode();
		$subscription_id = sanitize_text_field( trim( $subscription_id ) );

		if ( empty( $subscription_id ) || ! preg_match( '/^sub_[a-zA-Z0-9_]+$/', $subscription_id ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Invalid Stripe Subscription ID format.', 'fraud-tenant-core' ),
				'code'    => 'invalid_subscription_id',
			);
		}

		return self::request( 'GET', 'subscriptions/' . $subscription_id, array(), null, $target_mode );
	}

	/**
	 * Create a Stripe Customer Portal Session for billing management.
	 *
	 * @param int         $user_id    WordPress User ID.
	 * @param string      $return_url Fixed server-generated return URL.
	 * @param string|null $mode       Optional mode.
	 * @return array{success: bool, portal_url?: string, data?: array, error?: string, code?: string}
	 */
	public static function create_customer_portal_session( int $user_id, string $return_url, ?string $mode = null ): array {
		$target_mode = $mode ? strtolower( trim( $mode ) ) : StripeCredentialService::get_active_mode();
		$customer_id = self::get_customer_id( $user_id, $target_mode );

		if ( empty( $customer_id ) ) {
			return array(
				'success' => false,
				'error'   => __( 'No active Stripe billing customer found for this account. Please subscribe to a membership plan first.', 'fraud-tenant-core' ),
				'code'    => 'no_stripe_customer',
			);
		}

		$payload = array(
			'customer'   => $customer_id,
			'return_url' => $return_url,
		);

		$res = self::request( 'POST', 'billing_portal/sessions', $payload, null, $target_mode );

		// Mode isolation: the portal session must belong to the requested Stripe mode.
		if ( $res['success'] && ( ! empty( $res['data']['livemode'] ) ) !== ( Constants::STRIPE_MODE_LIVE === $target_mode ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Stripe mode mismatch while creating the billing portal session.', 'fraud-tenant-core' ),
				'code'    => 'portal_mode_mismatch',
			);
		}

		if ( ! $res['success'] || empty( $res['data']['url'] ) ) {
			return array(
				'success' => false,
				'error'   => $res['error'] ?? __( 'Failed creating Customer Portal session.', 'fraud-tenant-core' ),
				'code'    => $res['code'] ?? 'portal_session_failed',
			);
		}

		AuditLogger::log(
			'stripe_customer_portal_session_created',
			'customer_portal',
			$user_id,
			array(
				'customer_id' => $customer_id,
				'mode'        => $target_mode,
			),
			$user_id
		);

		return array(
			'success'    => true,
			'portal_url' => (string) $res['data']['url'],
			'data'       => $res['data'],
		);
	}

	/**
	 * Perform a server-side Stripe connection test using configured credentials for a mode.
	 *
	 * Requests the read-only GET /v1/balance endpoint to verify API key validity
	 * without creating customers, checkout sessions, subscriptions, or payments.
	 * Enforces SSL certificate verification and a bounded timeout.
	 *
	 * @param string|null $mode Optional mode ('test' or 'live'). Defaults to active mode.
	 * @return array{success: bool, message: string, livemode?: bool, mode?: string, error?: string, code?: string}
	 */
	public static function test_connection( ?string $mode = null ): array {
		$target_mode = $mode ? strtolower( trim( $mode ) ) : StripeCredentialService::get_active_mode();
		$cred_check  = self::validate_credentials( $target_mode );

		if ( ! $cred_check['valid'] ) {
			return array(
				'success' => false,
				'message' => $cred_check['error'] ?? __( 'Stripe credentials invalid.', 'fraud-tenant-core' ),
				'error'   => $cred_check['error'] ?? __( 'Stripe credentials invalid.', 'fraud-tenant-core' ),
				'code'    => $cred_check['code'] ?? 'stripe_credentials_invalid',
				'mode'    => $target_mode,
			);
		}

		$res = self::request( 'GET', 'balance', array(), null, $target_mode );

		if ( ! $res['success'] ) {
			return array(
				'success' => false,
				'message' => $res['error'] ?? __( 'Connection test failed. Unable to authenticate with Stripe.', 'fraud-tenant-core' ),
				'error'   => $res['error'] ?? __( 'Connection test failed.', 'fraud-tenant-core' ),
				'code'    => $res['code'] ?? 'stripe_connection_failed',
				'mode'    => $target_mode,
			);
		}

		$livemode = ! empty( $res['data']['livemode'] );

		return array(
			'success'  => true,
			'message'  => sprintf(
				/* translators: 1: mode name ('Test' or 'Live'), 2: actual Stripe livemode status */
				__( 'Stripe connection test successful! Authentication verified in %1$s mode (Stripe livemode: %2$s).', 'fraud-tenant-core' ),
				ucfirst( $target_mode ),
				$livemode ? 'true' : 'false'
			),
			'livemode' => $livemode,
			'mode'     => $target_mode,
		);
	}
}
