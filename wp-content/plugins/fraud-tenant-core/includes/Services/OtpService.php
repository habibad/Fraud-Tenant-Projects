<?php
/**
 * Email OTP Generation and Verification Service.
 *
 * @package FraudTenant\Services
 */

namespace FraudTenant\Services;

use FraudTenant\Utils\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OtpService {

	public const OTP_EXPIRY_SECONDS = 600; // 10 minutes
	public const RESEND_COOLDOWN_SECONDS = 60; // 60 seconds
	public const MAX_ATTEMPTS = 5;

	/**
	 * Generate and dispatch a 6-digit email OTP.
	 *
	 * @param int         $user_id User ID.
	 * @param string      $email   Recipient email.
	 * @param string      $purpose Purpose tag ('registration', 'password_reset', etc.).
	 * @param string|null $ip      Client IP address.
	 * @return array Result array with status and error/success details.
	 */
	public static function generate_otp( int $user_id, string $email, string $purpose = 'registration', ?string $ip = null ): array {
		if ( ! SecurityService::is_otp_secret_configured() ) {
			return array(
				'success' => false,
				'error'   => 'secret_missing',
				'message' => __( 'Email verification is currently unavailable due to missing server configuration.', 'fraud-tenant-core' ),
			);
		}

		$clean_email = sanitize_email( $email );
		if ( ! is_email( $clean_email ) ) {
			return array(
				'success' => false,
				'error'   => 'invalid_email',
				'message' => __( 'Invalid email address provided.', 'fraud-tenant-core' ),
			);
		}

		global $wpdb;
		$table = $wpdb->prefix . Constants::TABLE_EMAIL_OTPS;
		$now   = current_time( 'mysql', true );

		// 1. Enforce Resend Cooldown
		$active = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, resend_available_at FROM {$table} WHERE email = %s AND purpose = %s AND consumed_at IS NULL AND expires_at > %s ORDER BY id DESC LIMIT 1",
				$clean_email,
				$purpose,
				$now
			)
		);

		if ( $active ) {
			$resend_time = strtotime( $active->resend_available_at );
			$current_ts  = time();
			if ( $resend_time > $current_ts ) {
				$remaining = $resend_time - $current_ts;
				return array(
					'success'           => false,
					'error'             => 'cooldown_active',
					'seconds_remaining' => $remaining,
					'message'           => sprintf(
						/* translators: %d: seconds remaining */
						__( 'Please wait %d seconds before requesting another verification code.', 'fraud-tenant-core' ),
						$remaining
					),
				);
			}
		}

		// Invalidate all prior unconsumed OTPs on new code generation (ensures single active code)
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET consumed_at = %s WHERE email = %s AND purpose = %s AND consumed_at IS NULL",
				$now,
				$clean_email,
				$purpose
			)
		);

		// 2. Cryptographically Secure 6-digit numeric OTP
		try {
			$raw_otp = (string) random_int( 100000, 999999 );
		} catch ( \Exception $e ) {
			return array(
				'success' => false,
				'error'   => 'rng_failure',
				'message' => __( 'Could not generate secure verification code.', 'fraud-tenant-core' ),
			);
		}

		// 3. Compute HMAC-SHA256 Hash
		$otp_hash = SecurityService::hash_otp( $raw_otp );
		if ( false === $otp_hash ) {
			return array(
				'success' => false,
				'error'   => 'hashing_failure',
				'message' => __( 'Failed to secure verification code.', 'fraud-tenant-core' ),
			);
		}

		// 4. Persistence
		$resend_available = gmdate( 'Y-m-d H:i:s', time() + self::RESEND_COOLDOWN_SECONDS );
		$expires_at       = gmdate( 'Y-m-d H:i:s', time() + self::OTP_EXPIRY_SECONDS );
		$client_ip        = $ip ? sanitize_text_field( $ip ) : sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1' ) );

		$inserted = $wpdb->insert(
			$table,
			array(
				'email'               => $clean_email,
				'user_id'             => $user_id,
				'otp_hash'            => $otp_hash,
				'purpose'             => sanitize_key( $purpose ),
				'attempts'            => 0,
				'max_attempts'        => self::MAX_ATTEMPTS,
				'resend_available_at' => $resend_available,
				'expires_at'          => $expires_at,
				'consumed_at'         => null,
				'ip_address'          => $client_ip,
				'created_at'          => $now,
			),
			array( '%s', '%d', '%s', '%s', '%d', '%d', '%s', '%s', null, '%s', '%s' )
		);

		if ( false === $inserted ) {
			return array(
				'success' => false,
				'error'   => 'db_error',
				'message' => __( 'Could not record verification code.', 'fraud-tenant-core' ),
			);
		}

		// 5. Dispatch Email via wp_mail()
		$mail_sent = self::dispatch_email( $clean_email, $raw_otp, $purpose );

		return array(
			'success'           => true,
			'mail_sent'         => $mail_sent,
			'resend_in_seconds' => self::RESEND_COOLDOWN_SECONDS,
			'expires_in_seconds'=> self::OTP_EXPIRY_SECONDS,
			'message'           => __( 'A 6-digit verification code has been sent to your email address.', 'fraud-tenant-core' ),
		);
	}

	/**
	 * Verify an OTP candidate.
	 *
	 * @param string $email   User email address.
	 * @param string $otp     Candidate 6-digit OTP.
	 * @param string $purpose Purpose tag.
	 * @return array Result array with status and transition flags.
	 */
	public static function verify_otp( string $email, string $otp, string $purpose = 'registration' ): array {
		if ( ! SecurityService::is_otp_secret_configured() ) {
			return array(
				'success' => false,
				'error'   => 'secret_missing',
				'message' => __( 'Verification cannot proceed due to server configuration.', 'fraud-tenant-core' ),
			);
		}

		$clean_email = sanitize_email( $email );
		$clean_otp   = trim( $otp );

		if ( empty( $clean_otp ) || strlen( $clean_otp ) !== 6 || ! ctype_digit( $clean_otp ) ) {
			return array(
				'success' => false,
				'error'   => 'malformed_otp',
				'message' => __( 'Please enter a valid 6-digit numeric verification code.', 'fraud-tenant-core' ),
			);
		}

		global $wpdb;
		$table = $wpdb->prefix . Constants::TABLE_EMAIL_OTPS;
		$now   = current_time( 'mysql', true );

		$record = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, user_id, otp_hash, attempts, max_attempts, expires_at, consumed_at FROM {$table} WHERE email = %s AND purpose = %s AND consumed_at IS NULL ORDER BY id DESC LIMIT 1",
				$clean_email,
				$purpose
			)
		);

		if ( ! $record ) {
			return array(
				'success' => false,
				'error'   => 'no_active_otp',
				'message' => __( 'No active verification code found. Please request a new code.', 'fraud-tenant-core' ),
			);
		}

		// Check expiration
		if ( strtotime( $record->expires_at ) < time() ) {
			// Mark expired as consumed to close record
			$wpdb->update( $table, array( 'consumed_at' => $now ), array( 'id' => $record->id ) );
			return array(
				'success' => false,
				'error'   => 'expired_otp',
				'message' => __( 'This verification code has expired. Please request a new code.', 'fraud-tenant-core' ),
			);
		}

		// Check attempt limits
		if ( (int) $record->attempts >= (int) $record->max_attempts ) {
			$wpdb->update( $table, array( 'consumed_at' => $now ), array( 'id' => $record->id ) );
			return array(
				'success' => false,
				'error'   => 'attempts_exceeded',
				'message' => __( 'Maximum verification attempts exceeded. Please request a new code.', 'fraud-tenant-core' ),
			);
		}

		// Constant-time HMAC comparison
		$is_valid = SecurityService::verify_otp( $clean_otp, $record->otp_hash );

		if ( ! $is_valid ) {
			$new_attempts = (int) $record->attempts + 1;
			$wpdb->update( $table, array( 'attempts' => $new_attempts ), array( 'id' => $record->id ) );

			$remaining = max( 0, (int) $record->max_attempts - $new_attempts );
			return array(
				'success'            => false,
				'error'              => 'invalid_otp',
				'remaining_attempts' => $remaining,
				'message'            => sprintf(
					/* translators: %d: remaining verification attempts */
					__( 'Incorrect verification code. %d attempts remaining.', 'fraud-tenant-core' ),
					$remaining
				),
			);
		}

		// Code is valid! Consume single-use OTP
		$wpdb->update( $table, array( 'consumed_at' => $now ), array( 'id' => $record->id ) );

		// Transition Account Status Lifecycle
		$user_id = (int) $record->user_id;
		if ( 'registration' === $purpose && $user_id > 0 ) {
			$profiles_table = $wpdb->prefix . Constants::TABLE_USER_PROFILES;
			$wpdb->update(
				$profiles_table,
				array(
					'approval_status' => Constants::STATUS_PENDING_APPROVAL,
					'updated_at'      => $now,
				),
				array(
					'user_id'         => $user_id,
					'approval_status' => Constants::STATUS_EMAIL_UNVERIFIED,
				)
			);
		}

		return array(
			'success' => true,
			'user_id' => $user_id,
			'message' => __( 'Email verified successfully. Your account is now pending review.', 'fraud-tenant-core' ),
		);
	}

	/**
	 * Check seconds remaining until resend is available.
	 *
	 * @param string $email   User email.
	 * @param string $purpose Purpose.
	 * @return int Seconds remaining (0 if cooldown has elapsed).
	 */
	public static function get_resend_cooldown_remaining( string $email, string $purpose = 'registration' ): int {
		global $wpdb;
		$table = $wpdb->prefix . Constants::TABLE_EMAIL_OTPS;
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT resend_available_at FROM {$table} WHERE email = %s AND purpose = %s AND consumed_at IS NULL ORDER BY id DESC LIMIT 1",
				sanitize_email( $email ),
				$purpose
			)
		);

		if ( ! $row ) {
			return 0;
		}

		$remaining = strtotime( $row->resend_available_at ) - time();
		return max( 0, $remaining );
	}

	/**
	 * Dispatch email containing the OTP code.
	 *
	 * @param string $email   Recipient.
	 * @param string $raw_otp Plaintext 6-digit code.
	 * @param string $purpose Purpose.
	 * @return bool True if dispatch succeeded.
	 */
	protected static function dispatch_email( string $email, string $raw_otp, string $purpose ): bool {
		$subject = __( '[Fraud Tenant] Your Verification Code', 'fraud-tenant-core' );
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		$body  = sprintf( __( 'Hello,', 'fraud-tenant-core' ) ) . "\n\n";
		$body .= sprintf(
			/* translators: %s: 6-digit OTP code */
			__( 'Your 6-digit verification code is: %s', 'fraud-tenant-core' ),
			$raw_otp
		) . "\n\n";
		$body .= __( 'This code will expire in 10 minutes. If you did not make this request, please disregard this email.', 'fraud-tenant-core' ) . "\n\n";
		$body .= "---\nFraud Tenant Platform — A Safer Rental Community\n";

		return wp_mail( $email, $subject, $body, $headers );
	}
}

