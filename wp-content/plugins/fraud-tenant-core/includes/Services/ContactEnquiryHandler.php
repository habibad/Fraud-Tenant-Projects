<?php
/**
 * Contact Enquiry Service Handler.
 *
 * Handles public contact form submissions with strict validation, CSRF nonces,
 * honeypot spam protection, spoof-resistant IP rate limiting, and multi-field SSN pattern detection.
 *
 * @package FraudTenant\Services
 */

namespace FraudTenant\Services;

class ContactEnquiryHandler {

	/**
	 * Maximum allowed lengths for input fields.
	 */
	const MAX_NAME_LENGTH    = 100;
	const MAX_EMAIL_LENGTH   = 100;
	const MAX_SUBJECT_LENGTH = 150;
	const MAX_MESSAGE_LENGTH = 3000;
	const MIN_MESSAGE_LENGTH = 10;
	const RATE_LIMIT_SECONDS = 60;

	/**
	 * Regex pattern to detect SSN formats (with dashes, spaces, or contiguous 9 digits).
	 * Matches: 000-00-0000, 000 00 0000, 000000000.
	 * Excludes: Numbers starting with 000, 666, or 900-999 per standard SSN assignment rules.
	 */
	const SSN_REGEX = '/\b(?!000|666|9\d{2})\d{3}[-\s]?(?!00)\d{2}[-\s]?(?!0000)\d{4}\b/';

	/**
	 * Register hooks for contact form processing.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_post_ft_submit_contact_enquiry', array( __CLASS__, 'handle_submission' ) );
		add_action( 'admin_post_nopriv_ft_submit_contact_enquiry', array( __CLASS__, 'handle_submission' ) );
	}

	/**
	 * Process contact enquiry submission.
	 *
	 * @return void
	 */
	public static function handle_submission() {
		// 1. Verify CSRF Nonce (supports both ft_contact_enquiry_nonce and ft_contact_nonce).
		$nonce_verified = false;
		if ( isset( $_POST['ft_contact_enquiry_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ft_contact_enquiry_nonce'] ) ), 'ft_contact_enquiry_action' ) ) {
			$nonce_verified = true;
		} elseif ( isset( $_POST['ft_contact_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ft_contact_nonce'] ) ), 'ft_contact_action' ) ) {
			$nonce_verified = true;
		}

		if ( ! $nonce_verified ) {
			wp_die(
				'<h1>' . esc_html__( 'Security Verification Failed', 'fraud-tenant-core' ) . '</h1>' .
				'<p>' . esc_html__( 'Your submission could not be verified. Please reload the contact page and try again.', 'fraud-tenant-core' ) . '</p>',
				esc_html__( 'Forbidden', 'fraud-tenant-core' ),
				array( 'response' => 403 )
			);
		}

		$contact_url = wp_get_referer() ?: home_url( '/contact/' );

		// 2. Anti-Spam Honeypot Check.
		if ( ! empty( $_POST['ft_enquiry_hp'] ) ) {
			// Silent redirect - do not notify bot of detection.
			wp_safe_redirect( add_query_arg( 'ft_notice', 'success', $contact_url ) );
			if ( ! defined( 'FT_DOING_TESTS' ) ) {
				exit;
			}
			return;
		}

		// 3. Spoof-Resistant Client IP Retrieval (Ignore spoofable X-Forwarded-For headers).
		$client_ip = self::get_client_ip();

		// 4. IP-Based Rate Limiting.
		$rate_limit_key = 'ft_contact_limit_' . md5( $client_ip );
		if ( get_transient( $rate_limit_key ) ) {
			wp_safe_redirect( add_query_arg( 'ft_notice', 'rate_limit', $contact_url ) );
			if ( ! defined( 'FT_DOING_TESTS' ) ) {
				exit;
			}
			return;
		}

		// 5. Extract and sanitize raw input fields.
		$raw_name    = isset( $_POST['enquiry_name'] ) ? sanitize_text_field( wp_unslash( $_POST['enquiry_name'] ) ) : '';
		$raw_email   = isset( $_POST['enquiry_email'] ) ? sanitize_email( wp_unslash( $_POST['enquiry_email'] ) ) : '';
		$raw_subject = isset( $_POST['enquiry_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['enquiry_subject'] ) ) : '';
		$raw_message = isset( $_POST['enquiry_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['enquiry_message'] ) ) : '';

		// 6. Required Fields & Length Boundaries.
		if (
			empty( $raw_name ) ||
			empty( $raw_email ) ||
			empty( $raw_subject ) ||
			empty( $raw_message ) ||
			! is_email( $raw_email ) ||
			mb_strlen( $raw_name ) > self::MAX_NAME_LENGTH ||
			mb_strlen( $raw_email ) > self::MAX_EMAIL_LENGTH ||
			mb_strlen( $raw_subject ) > self::MAX_SUBJECT_LENGTH ||
			mb_strlen( $raw_message ) < self::MIN_MESSAGE_LENGTH ||
			mb_strlen( $raw_message ) > self::MAX_MESSAGE_LENGTH
		) {
			wp_safe_redirect( add_query_arg( 'ft_notice', 'invalid_fields', $contact_url ) );
			if ( ! defined( 'FT_DOING_TESTS' ) ) {
				exit;
			}
			return;
		}

		// 7. Multi-Field SSN Pattern Detection (Privacy Protection across Name, Subject, and Message).
		if (
			self::contains_ssn_pattern( $raw_name ) ||
			self::contains_ssn_pattern( $raw_subject ) ||
			self::contains_ssn_pattern( $raw_message )
		) {
			// Reject immediately. Do NOT log raw contents or sensitive candidates.
			// Return a generic security notice without echoing any user input.
			wp_safe_redirect( add_query_arg( 'ft_notice', 'ssn_detected', $contact_url ) );
			if ( ! defined( 'FT_DOING_TESTS' ) ) {
				exit;
			}
			return;
		}

		// 8. Set Rate Limit Transient (60 seconds).
		set_transient( $rate_limit_key, time(), self::RATE_LIMIT_SECONDS );

		// 9. Dispatch Email via wp_mail().
		$admin_email   = get_option( 'admin_email' );
		$email_subject = sprintf( '[Fraud Tenant Enquiry] %s', $raw_subject );
		$email_headers = array(
			'Content-Type: text/plain; charset=UTF-8',
			sprintf( 'Reply-To: %s <%s>', $raw_name, $raw_email ),
		);

		$email_body = sprintf(
			"New Contact Enquiry via Fraud Tenant Platform\n\n" .
			"Name: %s\n" .
			"Email: %s\n" .
			"Subject: %s\n" .
			"Submitted: %s UTC\n\n" .
			"Message:\n%s\n\n" .
			"---\nThis message was sent from the Fraud Tenant public contact form.\n" .
			"Client IP: %s\n",
			$raw_name,
			$raw_email,
			$raw_subject,
			gmdate( 'Y-m-d H:i:s' ),
			$raw_message,
			$client_ip
		);

		$mail_sent = wp_mail( $admin_email, $email_subject, $email_body, $email_headers );

		// 10. Redirect with appropriate notice flag.
		if ( $mail_sent ) {
			wp_safe_redirect( add_query_arg( 'ft_notice', 'success', $contact_url ) );
		} else {
			// If wp_mail fails (e.g., local mail transport unconfigured), inform gracefully.
			wp_safe_redirect( add_query_arg( 'ft_notice', 'mail_failed', $contact_url ) );
		}

		if ( ! defined( 'FT_DOING_TESTS' ) ) {
			exit;
		}
	}

	/**
	 * Check whether a text string contains a candidate SSN pattern.
	 *
	 * Limitations:
	 * Regex detects standard 9-digit US SSN numeric patterns (e.g. 123-45-6789, 123 45 6789, 123456789).
	 * It does not detect spelled-out numbers, words, or encrypted formats. It serves as an automated
	 * defense-in-depth safeguard to prevent accidental PII leakage in email inboxes.
	 *
	 * @param string $text Input text to analyze.
	 * @return bool True if an SSN pattern is detected, false otherwise.
	 */
	public static function contains_ssn_pattern( $text ) {
		if ( empty( $text ) || ! is_string( $text ) ) {
			return false;
		}

		// Check primary regex pattern.
		if ( preg_match( self::SSN_REGEX, $text ) ) {
			return true;
		}

		// Secondary check: contiguous 9 digits that meet valid SSN area/group/serial criteria.
		if ( preg_match( '/\b\d{9}\b/', $text, $matches ) ) {
			$digits = $matches[0];
			$area   = (int) substr( $digits, 0, 3 );
			$group  = (int) substr( $digits, 3, 2 );
			$serial = (int) substr( $digits, 5, 4 );

			if ( $area > 0 && $area !== 666 && $area < 900 && $group > 0 && $serial > 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get trustworthy client IP address directly from REMOTE_ADDR.
	 *
	 * Never trusts HTTP_X_FORWARDED_FOR or HTTP_CLIENT_IP as those headers
	 * can be freely spoofed by attackers to bypass rate limits or pollute records.
	 *
	 * @return string Client IP address.
	 */
	public static function get_client_ip() {
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}
		return '127.0.0.1';
	}
}
