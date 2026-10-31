<?php
/**
 * Append-Only Application-Level Audit Logger Service.
 *
 * @package FraudTenant\Services
 */

namespace FraudTenant\Services;

use FraudTenant\Utils\Constants;
use FraudTenant\Utils\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AuditLogger {

	/**
	 * Log a security, authentication, or operational event into the append-only audit log.
	 *
	 * Note: While this log is designed as an append-only application record, it is not
	 * claimed to be absolutely immutable against direct database administrator alterations.
	 *
	 * @param string      $action      Identifier for the action (e.g., 'auth.login', 'screening.request').
	 * @param string|null $object_type Type of domain entity involved (e.g., 'property', 'incident_report').
	 * @param int|null    $object_id   ID of the domain entity.
	 * @param array|null  $details     Contextual data. Sensitive fields will be automatically redacted.
	 * @param int|null    $user_id     Actor user ID. Defaults to current logged-in user.
	 * @return int|false Inserted log record ID or false on failure.
	 */
	public static function log(
		string $action,
		?string $object_type = null,
		?int $object_id = null,
		?array $details = null,
		?int $user_id = null
	): int|false {
		global $wpdb;

		if ( null === $user_id ) {
			$user_id = get_current_user_id() ?: null;
		}

		// Strictly redact all sensitive information before serialization
		$sanitized_details = null;
		if ( null !== $details ) {
			$redacted          = Sanitizer::redact_sensitive_data( $details );
			$sanitized_details = wp_json_encode( $redacted );
		}

		$ip_address = self::get_client_ip();
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 ) : null;
		$table      = $wpdb->prefix . Constants::TABLE_AUDIT_LOG;

		$inserted = $wpdb->insert(
			$table,
			array(
				'user_id'      => $user_id,
				'action'       => sanitize_key( str_replace( '.', '_', $action ) ),
				'object_type'  => $object_type ? sanitize_key( $object_type ) : null,
				'object_id'    => $object_id,
				'ip_address'   => $ip_address,
				'user_agent'   => $user_agent,
				'details_json' => $sanitized_details,
				'created_at'   => current_time( 'mysql' ),
			),
			array(
				'%d',
				'%s',
				'%s',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);

		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Extract sanitized client IP address.
	 *
	 * @return string|null IP address or null.
	 */
	protected static function get_client_ip(): ?string {
		$ip = null;
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$forwarded = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			$ip        = trim( $forwarded[0] );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		if ( $ip && filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return $ip;
		}

		return null;
	}
}

