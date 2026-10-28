<?php
/**
 * Sanitization and Redaction Utilities.
 *
 * @package FraudTenant\Utils
 */

namespace FraudTenant\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sanitizer {

	/**
	 * Keys that must be completely redacted from logs or audit records.
	 *
	 * @var array<string>
	 */
	protected static array $sensitive_keys = array(
		'password',
		'user_pass',
		'pass',
		'otp',
		'otp_code',
		'code',
		'token',
		'auth_token',
		'ssn',
		'social_security_number',
		'secret',
		'secret_key',
		'stripe_secret',
		'api_key',
		'webhook_secret',
		'cvv',
		'card_number',
		'card',
		'exp_month',
		'exp_year',
	);

	/**
	 * Redact sensitive fields recursively from data structures before saving/logging.
	 *
	 * @param mixed $data Arbitrary data structure.
	 * @return mixed Redacted data structure.
	 */
	public static function redact_sensitive_data( mixed $data ): mixed {
		if ( ! is_array( $data ) && ! is_object( $data ) ) {
			return $data;
		}

		$is_obj = is_object( $data );
		$array  = $is_obj ? (array) $data : $data;

		foreach ( $array as $key => $val ) {
			$clean_key = strtolower( (string) $key );
			if ( in_array( $clean_key, self::$sensitive_keys, true ) ) {
				$array[ $key ] = '[REDACTED]';
			} elseif ( is_array( $val ) || is_object( $val ) ) {
				$array[ $key ] = self::redact_sensitive_data( $val );
			}
		}

		return $is_obj ? (object) $array : $array;
	}

	/**
	 * Minimize payment event payloads for storage.
	 *
	 * Extracts only the necessary fields for event processing, idempotency, and troubleshooting.
	 * Explicitly discards unnecessary raw payload fields, personal addresses, customer emails,
	 * and cardholder metadata.
	 *
	 * @param array $event Raw event payload from payment gateway.
	 * @return array Minimized, privacy-preserving event summary.
	 */
	public static function minimize_payment_payload( array $event ): array {
		$minimized = array(
			'event_id'  => $event['id'] ?? null,
			'type'      => $event['type'] ?? null,
			'created'   => $event['created'] ?? null,
			'livemode'  => ! empty( $event['livemode'] ),
		);

		$data_obj = $event['data']['object'] ?? array();
		if ( ! empty( $data_obj ) && is_array( $data_obj ) ) {
			$minimized['object'] = array(
				'id'                   => $data_obj['id'] ?? null,
				'object_type'          => $data_obj['object'] ?? null,
				'customer_id'          => $data_obj['customer'] ?? null,
				'status'               => $data_obj['status'] ?? null,
				'current_period_start' => $data_obj['current_period_start'] ?? null,
				'current_period_end'   => $data_obj['current_period_end'] ?? null,
				'cancel_at_period_end' => $data_obj['cancel_at_period_end'] ?? null,
				'amount'               => $data_obj['amount'] ?? ( $data_obj['amount_paid'] ?? null ),
				'currency'             => $data_obj['currency'] ?? null,
				'subscription'         => $data_obj['subscription'] ?? null,
				'failure_code'         => $data_obj['failure_code'] ?? null,
				'failure_message'      => $data_obj['failure_message'] ?? null,
			);

			// Extract price/plan ID if present without retaining extraneous subscription objects
			if ( ! empty( $data_obj['items']['data'][0]['price']['id'] ) ) {
				$minimized['object']['price_id'] = $data_obj['items']['data'][0]['price']['id'];
			} elseif ( ! empty( $data_obj['lines']['data'][0]['price']['id'] ) ) {
				$minimized['object']['price_id'] = $data_obj['lines']['data'][0]['price']['id'];
			}
		}

		// Apply redaction safeguard across the minimized structure
		return self::redact_sensitive_data( $minimized );
	}

	/**
	 * Mask an SSN to display only the last 4 digits.
	 *
	 * @param string $ssn Raw or partial SSN.
	 * @return string Masked string like "••••-1234" or "••••-0000".
	 */
	public static function mask_ssn( string $ssn ): string {
		$digits = preg_replace( '/\D/', '', $ssn );
		if ( strlen( $digits ) < 4 ) {
			return '••••-0000';
		}
		$last_four = substr( $digits, -4 );
		return '••••-' . $last_four;
	}

	/**
	 * Extract only the last 4 digits of an SSN.
	 *
	 * @param string $ssn Raw SSN.
	 * @return string 4 digits.
	 */
	public static function extract_ssn_last_four( string $ssn ): string {
		$digits = preg_replace( '/\D/', '', $ssn );
		return substr( $digits, -4 );
	}
}
