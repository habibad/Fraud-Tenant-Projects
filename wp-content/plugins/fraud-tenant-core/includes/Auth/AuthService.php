<?php
/**
 * Authentication and Account Lifecycle Management Service.
 *
 * @package FraudTenant\Auth
 */

namespace FraudTenant\Auth;

use FraudTenant\Services\AuditLogger;
use FraudTenant\Services\DocumentService;
use FraudTenant\Services\OtpService;
use FraudTenant\Utils\Constants;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AuthService {

	public const MIN_PASSWORD_LENGTH = 8;
	public const MAX_LOGIN_ATTEMPTS  = 5;
	public const LOGIN_LOCKOUT_SECS  = 900; // 15 minutes

	/**
	 * Validate standard US phone number format.
	 *
	 * @param string $phone Phone number string.
	 * @return bool True if valid.
	 */
	public static function validate_phone( string $phone ): bool {
		$digits = preg_replace( '/\D/', '', $phone );
		if ( 10 === strlen( $digits ) ) {
			return true;
		}
		if ( 11 === strlen( $digits ) && '1' === $digits[0] ) {
			return true;
		}
		return false;
	}

	/**
	 * Validate 5-digit US ZIP code or 9-digit ZIP+4.
	 *
	 * @param string $zip ZIP code.
	 * @return bool True if valid.
	 */
	public static function validate_zip( string $zip ): bool {
		return (bool) preg_match( '/^\d{5}(-\d{4})?$/', trim( $zip ) );
	}

	/**
	 * Register a new user safely as ft_owner or ft_tenant.
	 *
	 * @param array $data  Input form data.
	 * @param array $files Uploaded $_FILES data.
	 * @return array Result array with status and redirect/notice information.
	 */
	public static function register( array $data, array $files = array() ): array {
		$account_type = isset( $data['account_type'] ) ? sanitize_key( $data['account_type'] ) : '';

		if ( 'owner' === $account_type ) {
			return self::register_owner( $data, $files );
		}

		if ( 'tenant' === $account_type ) {
			return self::register_tenant( $data );
		}

		return array(
			'success' => false,
			'error'   => 'invalid_account_type',
			'message' => __( 'Please select a valid account type (Property Owner or Tenant).', 'fraud-tenant-core' ),
		);
	}

	/**
	 * Register an Individual Property Owner account.
	 *
	 * @param array $data  Submitted form fields.
	 * @param array $files Uploaded $_FILES array.
	 * @return array Result array.
	 */
	public static function register_owner( array $data, array $files = array() ): array {
		// 1. Sanitize Inputs
		$first_name       = isset( $data['first_name'] ) ? sanitize_text_field( trim( $data['first_name'] ) ) : '';
		$last_name        = isset( $data['last_name'] ) ? sanitize_text_field( trim( $data['last_name'] ) ) : '';
		$email            = isset( $data['email'] ) ? sanitize_email( trim( $data['email'] ) ) : '';
		$phone            = isset( $data['phone'] ) ? sanitize_text_field( trim( $data['phone'] ) ) : '';
		$password         = isset( $data['password'] ) ? (string) $data['password'] : '';
		$confirm_pw       = isset( $data['confirm_password'] ) ? (string) $data['confirm_password'] : '';
		$property_street  = isset( $data['property_street'] ) ? sanitize_text_field( trim( $data['property_street'] ) ) : ( isset( $data['address_line1'] ) ? sanitize_text_field( trim( $data['address_line1'] ) ) : '' );
		$property_city    = isset( $data['property_city'] ) ? sanitize_text_field( trim( $data['property_city'] ) ) : ( isset( $data['city'] ) ? sanitize_text_field( trim( $data['city'] ) ) : '' );
		$property_state   = isset( $data['property_state'] ) ? sanitize_text_field( trim( $data['property_state'] ) ) : ( isset( $data['state'] ) ? sanitize_text_field( trim( $data['state'] ) ) : '' );
		$property_zip     = isset( $data['property_zip'] ) ? sanitize_text_field( trim( $data['property_zip'] ) ) : ( isset( $data['postal_code'] ) ? sanitize_text_field( trim( $data['postal_code'] ) ) : '' );
		$terms_accepted   = ! empty( $data['terms_accepted'] ) || ! empty( $data['terms_acknowledged'] );
		$privacy_accepted = ! empty( $data['privacy_accepted'] ) || ! empty( $data['terms_acknowledged'] );

		// 2. Validate Required Fields Server-Side
		if ( empty( $first_name ) || empty( $last_name ) ) {
			return array(
				'success' => false,
				'error'   => 'missing_name',
				'message' => __( 'Please enter both your first and last legal name.', 'fraud-tenant-core' ),
			);
		}

		if ( ! is_email( $email ) ) {
			return array(
				'success' => false,
				'error'   => 'invalid_email',
				'message' => __( 'Please enter a valid email address.', 'fraud-tenant-core' ),
			);
		}

		if ( empty( $phone ) || ! self::validate_phone( $phone ) ) {
			return array(
				'success' => false,
				'error'   => 'invalid_phone',
				'message' => __( 'Please enter a valid 10-digit phone number.', 'fraud-tenant-core' ),
			);
		}

		if ( strlen( $password ) < self::MIN_PASSWORD_LENGTH ) {
			return array(
				'success' => false,
				'error'   => 'weak_password',
				'message' => sprintf(
					/* translators: %d: minimum password length */
					__( 'Password must be at least %d characters long.', 'fraud-tenant-core' ),
					self::MIN_PASSWORD_LENGTH
				),
			);
		}

		if ( $password !== $confirm_pw ) {
			return array(
				'success' => false,
				'error'   => 'password_mismatch',
				'message' => __( 'The entered passwords do not match.', 'fraud-tenant-core' ),
			);
		}

		if ( empty( $property_street ) ) {
			return array(
				'success' => false,
				'error'   => 'missing_property_street',
				'message' => __( 'Please enter the property street address.', 'fraud-tenant-core' ),
			);
		}

		if ( empty( $property_city ) ) {
			return array(
				'success' => false,
				'error'   => 'missing_property_city',
				'message' => __( 'Please enter the property city.', 'fraud-tenant-core' ),
			);
		}

		if ( empty( $property_state ) ) {
			return array(
				'success' => false,
				'error'   => 'missing_property_state',
				'message' => __( 'Please enter the property state.', 'fraud-tenant-core' ),
			);
		}

		if ( empty( $property_zip ) || ! self::validate_zip( $property_zip ) ) {
			return array(
				'success' => false,
				'error'   => 'invalid_property_zip',
				'message' => __( 'Please enter a valid 5-digit US ZIP code for the property.', 'fraud-tenant-core' ),
			);
		}

		// Pre-flight file check for property ownership document
		$doc_file = $files['ownership_document'] ?? $files['document'] ?? null;
		if ( ! $doc_file || ! is_array( $doc_file ) || ( isset( $doc_file['error'] ) && UPLOAD_ERR_NO_FILE === $doc_file['error'] ) ) {
			return array(
				'success' => false,
				'error'   => 'missing_document',
				'message' => __( 'Please upload a property ownership verification document (Deed, Tax Assessment, or Utility Bill in PDF, PNG, or JPG format).', 'fraud-tenant-core' ),
			);
		}

		// Quick pre-flight validation on file extension and size before user creation
		$ext = strtolower( pathinfo( (string) ( $doc_file['name'] ?? '' ), PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, DocumentService::ALLOWED_EXTENSIONS, true ) ) {
			return array(
				'success' => false,
				'error'   => 'invalid_file_extension',
				'message' => __( 'Invalid document file type. Only PDF, PNG, and JPEG documents are accepted.', 'fraud-tenant-core' ),
			);
		}

		if ( (int) ( $doc_file['size'] ?? 0 ) > DocumentService::MAX_FILE_SIZE ) {
			return array(
				'success' => false,
				'error'   => 'file_size_limit_exceeded',
				'message' => sprintf(
					/* translators: %d: maximum file size in megabytes */
					__( 'Document size must be less than %d MB.', 'fraud-tenant-core' ),
					(int) ( DocumentService::MAX_FILE_SIZE / ( 1024 * 1024 ) )
				),
			);
		}

		if ( ! $terms_accepted ) {
			return array(
				'success' => false,
				'error'   => 'terms_unacknowledged',
				'message' => __( 'You must accept the Terms of Service to register as a Property Owner.', 'fraud-tenant-core' ),
			);
		}

		if ( ! $privacy_accepted ) {
			return array(
				'success' => false,
				'error'   => 'privacy_unacknowledged',
				'message' => __( 'You must acknowledge the Privacy Policy to register as a Property Owner.', 'fraud-tenant-core' ),
			);
		}

		// 3. Duplicate Email Anti-Enumeration Protection
		if ( email_exists( $email ) ) {
			$existing_user = get_user_by( 'email', $email );
			$status        = $existing_user ? self::get_user_account_status( $existing_user->ID ) : '';

			if ( Constants::STATUS_EMAIL_UNVERIFIED === $status && $existing_user ) {
				OtpService::generate_otp( $existing_user->ID, $email, 'registration' );
				return array(
					'success'  => true,
					'email'    => $email,
					'redirect' => add_query_arg( 'email', urlencode( $email ), home_url( '/verify-otp/' ) ),
					'message'  => __( 'A verification code has been dispatched to your email address.', 'fraud-tenant-core' ),
				);
			}

			return array(
				'success'  => true,
				'email'    => $email,
				'redirect' => add_query_arg( 'email', urlencode( $email ), home_url( '/verify-otp/' ) ),
				'message'  => __( 'Registration received. If eligible, a verification code has been dispatched.', 'fraud-tenant-core' ),
			);
		}

		// 4. Create Core WordPress User
		$username = self::generate_unique_username( $email );
		$user_id  = wp_insert_user(
			array(
				'user_login'   => $username,
				'user_email'   => $email,
				'user_pass'    => $password,
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'display_name' => trim( $first_name . ' ' . $last_name ),
				'role'         => Constants::ROLE_OWNER,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return array(
				'success' => false,
				'error'   => 'user_creation_failed',
				'message' => __( 'Could not complete registration. Please try again later.', 'fraud-tenant-core' ),
			);
		}

		// 5. Create Profile Record
		global $wpdb;
		$now = current_time( 'mysql', true );

		$profile_created = $wpdb->insert(
			$wpdb->prefix . Constants::TABLE_USER_PROFILES,
			array(
				'user_id'             => $user_id,
				'account_type'        => 'owner',
				'approval_status'     => Constants::STATUS_EMAIL_UNVERIFIED,
				'phone'               => $phone,
				'address_line1'       => $property_street,
				'city'                => $property_city,
				'state'               => $property_state,
				'postal_code'         => $property_zip,
				'terms_accepted_at'   => $now,
				'privacy_accepted_at' => $now,
				'identity_verified'   => 0,
				'created_at'          => $now,
				'updated_at'          => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( false === $profile_created ) {
			wp_delete_user( $user_id );
			return array(
				'success' => false,
				'error'   => 'profile_creation_failed',
				'message' => __( 'Failed to initialize account profile. Please try again.', 'fraud-tenant-core' ),
			);
		}

		// 6. Create Initial Property Record
		$property_title   = trim( $property_street . ', ' . $property_city . ', ' . $property_state );
		$property_created = $wpdb->insert(
			$wpdb->prefix . Constants::TABLE_PROPERTIES,
			array(
				'owner_id'       => $user_id,
				'title'          => $property_title,
				'address_street' => $property_street,
				'city'           => $property_city,
				'state'          => $property_state,
				'postal_code'    => $property_zip,
				'property_type'  => 'single_family',
				'units_count'    => 1,
				'status'         => 'pending_verification',
				'created_at'     => $now,
				'updated_at'     => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		$property_id = $property_created ? (int) $wpdb->insert_id : 0;

		// 7. Store Property Ownership Document in Private Protected Storage
		$doc_result = DocumentService::store_ownership_document( $user_id, $property_id, $doc_file );
		if ( ! $doc_result['success'] ) {
			// Roll back property, profile, and user
			if ( $property_id > 0 ) {
				$wpdb->delete( $wpdb->prefix . Constants::TABLE_PROPERTIES, array( 'id' => $property_id ), array( '%d' ) );
			}
			$wpdb->delete( $wpdb->prefix . Constants::TABLE_USER_PROFILES, array( 'user_id' => $user_id ), array( '%d' ) );
			wp_delete_user( $user_id );

			return array(
				'success' => false,
				'error'   => $doc_result['error'],
				'message' => $doc_result['message'],
			);
		}

		// 8. Dispatch Initial Email OTP
		$otp_result = OtpService::generate_otp( $user_id, $email, 'registration' );

		// 9. Audit Logging (Zero secrets or raw file paths logged)
		AuditLogger::log(
			'auth.owner_registered',
			'user',
			$user_id,
			array(
				'account_type' => 'owner',
				'email_hash'   => hash( 'sha256', $email ),
				'property_id'  => $property_id,
				'document_id'  => $doc_result['document_id'] ?? 0,
				'mail_sent'    => $otp_result['mail_sent'] ?? false,
			),
			$user_id
		);

		return array(
			'success'     => true,
			'user_id'     => $user_id,
			'email'       => $email,
			'property_id' => $property_id,
			'document_id' => $doc_result['document_id'] ?? 0,
			'redirect'    => add_query_arg( 'email', urlencode( $email ), home_url( '/verify-otp/' ) ),
			'mail_sent'   => $otp_result['mail_sent'] ?? false,
			'message'     => __( 'Registration successful! A 6-digit verification code has been dispatched to your email address.', 'fraud-tenant-core' ),
		);
	}

	/**
	 * Register an Individual Tenant account.
	 *
	 * @param array $data Submitted form fields.
	 * @return array Result array.
	 */
	public static function register_tenant( array $data ): array {
		// 1. Sanitize Inputs
		$first_name       = isset( $data['first_name'] ) ? sanitize_text_field( trim( $data['first_name'] ) ) : '';
		$last_name        = isset( $data['last_name'] ) ? sanitize_text_field( trim( $data['last_name'] ) ) : '';
		$email            = isset( $data['email'] ) ? sanitize_email( trim( $data['email'] ) ) : '';
		$phone            = isset( $data['phone'] ) ? sanitize_text_field( trim( $data['phone'] ) ) : '';
		$password         = isset( $data['password'] ) ? (string) $data['password'] : '';
		$confirm_pw       = isset( $data['confirm_password'] ) ? (string) $data['confirm_password'] : '';
		$current_street   = isset( $data['current_street'] ) ? sanitize_text_field( trim( $data['current_street'] ) ) : ( isset( $data['address_line1'] ) ? sanitize_text_field( trim( $data['address_line1'] ) ) : '' );
		$current_city     = isset( $data['current_city'] ) ? sanitize_text_field( trim( $data['current_city'] ) ) : ( isset( $data['city'] ) ? sanitize_text_field( trim( $data['city'] ) ) : '' );
		$current_state    = isset( $data['current_state'] ) ? sanitize_text_field( trim( $data['current_state'] ) ) : ( isset( $data['state'] ) ? sanitize_text_field( trim( $data['state'] ) ) : '' );
		$current_zip      = isset( $data['current_zip'] ) ? sanitize_text_field( trim( $data['current_zip'] ) ) : ( isset( $data['postal_code'] ) ? sanitize_text_field( trim( $data['postal_code'] ) ) : '' );
		$has_prev_address = ! empty( $data['has_previous_address'] );
		$prev_street      = isset( $data['prev_street'] ) ? sanitize_text_field( trim( $data['prev_street'] ) ) : '';
		$prev_city        = isset( $data['prev_city'] ) ? sanitize_text_field( trim( $data['prev_city'] ) ) : '';
		$prev_state       = isset( $data['prev_state'] ) ? sanitize_text_field( trim( $data['prev_state'] ) ) : '';
		$prev_zip         = isset( $data['prev_zip'] ) ? sanitize_text_field( trim( $data['prev_zip'] ) ) : '';
		$terms_accepted   = ! empty( $data['terms_accepted'] ) || ! empty( $data['terms_acknowledged'] );
		$privacy_accepted = ! empty( $data['privacy_accepted'] ) || ! empty( $data['terms_acknowledged'] );

		// 2. Validate Inputs Server-Side
		if ( empty( $first_name ) || empty( $last_name ) ) {
			return array(
				'success' => false,
				'error'   => 'missing_name',
				'message' => __( 'Please enter both your first and last legal name.', 'fraud-tenant-core' ),
			);
		}

		if ( ! is_email( $email ) ) {
			return array(
				'success' => false,
				'error'   => 'invalid_email',
				'message' => __( 'Please enter a valid email address.', 'fraud-tenant-core' ),
			);
		}

		if ( empty( $phone ) || ! self::validate_phone( $phone ) ) {
			return array(
				'success' => false,
				'error'   => 'invalid_phone',
				'message' => __( 'Please enter a valid 10-digit phone number.', 'fraud-tenant-core' ),
			);
		}

		if ( strlen( $password ) < self::MIN_PASSWORD_LENGTH ) {
			return array(
				'success' => false,
				'error'   => 'weak_password',
				'message' => sprintf(
					/* translators: %d: minimum password length */
					__( 'Password must be at least %d characters long.', 'fraud-tenant-core' ),
					self::MIN_PASSWORD_LENGTH
				),
			);
		}

		if ( $password !== $confirm_pw ) {
			return array(
				'success' => false,
				'error'   => 'password_mismatch',
				'message' => __( 'The entered passwords do not match.', 'fraud-tenant-core' ),
			);
		}

		if ( empty( $current_street ) ) {
			return array(
				'success' => false,
				'error'   => 'missing_current_street',
				'message' => __( 'Please enter your current residential street address.', 'fraud-tenant-core' ),
			);
		}

		if ( empty( $current_city ) ) {
			return array(
				'success' => false,
				'error'   => 'missing_current_city',
				'message' => __( 'Please enter your current city.', 'fraud-tenant-core' ),
			);
		}

		if ( empty( $current_state ) ) {
			return array(
				'success' => false,
				'error'   => 'missing_current_state',
				'message' => __( 'Please enter your current state.', 'fraud-tenant-core' ),
			);
		}

		if ( empty( $current_zip ) || ! self::validate_zip( $current_zip ) ) {
			return array(
				'success' => false,
				'error'   => 'invalid_current_zip',
				'message' => __( 'Please enter a valid 5-digit US ZIP code for your current residence.', 'fraud-tenant-core' ),
			);
		}

		// Conditional validation for previous rental address
		if ( $has_prev_address ) {
			if ( empty( $prev_street ) || empty( $prev_city ) || empty( $prev_state ) ) {
				return array(
					'success' => false,
					'error'   => 'incomplete_previous_address',
					'message' => __( 'Please complete all previous rental address fields or uncheck the previous rental address option.', 'fraud-tenant-core' ),
				);
			}

			if ( empty( $prev_zip ) || ! self::validate_zip( $prev_zip ) ) {
				return array(
					'success' => false,
					'error'   => 'invalid_previous_zip',
					'message' => __( 'Please enter a valid 5-digit US ZIP code for your previous rental address.', 'fraud-tenant-core' ),
				);
			}
		}

		if ( ! $terms_accepted ) {
			return array(
				'success' => false,
				'error'   => 'terms_unacknowledged',
				'message' => __( 'You must accept the Terms of Service to register as a Tenant.', 'fraud-tenant-core' ),
			);
		}

		if ( ! $privacy_accepted ) {
			return array(
				'success' => false,
				'error'   => 'privacy_unacknowledged',
				'message' => __( 'You must acknowledge the Privacy Policy to register as a Tenant.', 'fraud-tenant-core' ),
			);
		}

		// 3. Duplicate Email Anti-Enumeration Protection
		if ( email_exists( $email ) ) {
			$existing_user = get_user_by( 'email', $email );
			$status        = $existing_user ? self::get_user_account_status( $existing_user->ID ) : '';

			if ( Constants::STATUS_EMAIL_UNVERIFIED === $status && $existing_user ) {
				OtpService::generate_otp( $existing_user->ID, $email, 'registration' );
				return array(
					'success'  => true,
					'email'    => $email,
					'redirect' => add_query_arg( 'email', urlencode( $email ), home_url( '/verify-otp/' ) ),
					'message'  => __( 'A verification code has been dispatched to your email address.', 'fraud-tenant-core' ),
				);
			}

			return array(
				'success'  => true,
				'email'    => $email,
				'redirect' => add_query_arg( 'email', urlencode( $email ), home_url( '/verify-otp/' ) ),
				'message'  => __( 'Registration received. If eligible, a verification code has been dispatched.', 'fraud-tenant-core' ),
			);
		}

		// 4. Create Core WordPress User
		$username = self::generate_unique_username( $email );
		$user_id  = wp_insert_user(
			array(
				'user_login'   => $username,
				'user_email'   => $email,
				'user_pass'    => $password,
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'display_name' => trim( $first_name . ' ' . $last_name ),
				'role'         => Constants::ROLE_TENANT,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return array(
				'success' => false,
				'error'   => 'user_creation_failed',
				'message' => __( 'Could not complete registration. Please try again later.', 'fraud-tenant-core' ),
			);
		}

		// 5. Create Profile Record
		global $wpdb;
		$now = current_time( 'mysql', true );

		$profile_created = $wpdb->insert(
			$wpdb->prefix . Constants::TABLE_USER_PROFILES,
			array(
				'user_id'             => $user_id,
				'account_type'        => 'tenant',
				'approval_status'     => Constants::STATUS_EMAIL_UNVERIFIED,
				'phone'               => $phone,
				'address_line1'       => $current_street,
				'city'                => $current_city,
				'state'               => $current_state,
				'postal_code'         => $current_zip,
				'prev_address_line1'  => $has_prev_address ? $prev_street : null,
				'prev_city'           => $has_prev_address ? $prev_city : null,
				'prev_state'          => $has_prev_address ? $prev_state : null,
				'prev_postal_code'    => $has_prev_address ? $prev_zip : null,
				'terms_accepted_at'   => $now,
				'privacy_accepted_at' => $now,
				'identity_verified'   => 0,
				'created_at'          => $now,
				'updated_at'          => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( false === $profile_created ) {
			wp_delete_user( $user_id );
			return array(
				'success' => false,
				'error'   => 'profile_creation_failed',
				'message' => __( 'Failed to initialize account profile. Please try again.', 'fraud-tenant-core' ),
			);
		}

		// 6. Dispatch Initial Email OTP
		$otp_result = OtpService::generate_otp( $user_id, $email, 'registration' );

		// 7. Audit Logging
		AuditLogger::log(
			'auth.tenant_registered',
			'user',
			$user_id,
			array(
				'account_type'     => 'tenant',
				'email_hash'       => hash( 'sha256', $email ),
				'has_prev_address' => $has_prev_address,
				'mail_sent'        => $otp_result['mail_sent'] ?? false,
			),
			$user_id
		);

		return array(
			'success'   => true,
			'user_id'   => $user_id,
			'email'     => $email,
			'redirect'  => add_query_arg( 'email', urlencode( $email ), home_url( '/verify-otp/' ) ),
			'mail_sent' => $otp_result['mail_sent'] ?? false,
			'message'   => __( 'Registration successful! A 6-digit verification code has been dispatched to your email address.', 'fraud-tenant-core' ),
		);
	}

	/**
	 * Authenticate user with credentials and enforce account lifecycle status checks.
	 *
	 * @param string $login_input Username or email.
	 * @param string $password    User password.
	 * @param bool   $remember    Whether to persist login session.
	 * @return array Result array with status, user or error.
	 */
	public static function login( string $login_input, string $password, bool $remember = false ): array {
		$clean_login = trim( $login_input );

		// 1. IP Rate Limiting Check
		$ip          = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1' ) );
		$rate_key    = 'ft_login_fails_' . md5( $ip );
		$fail_count  = (int) get_transient( $rate_key );

		if ( $fail_count >= self::MAX_LOGIN_ATTEMPTS ) {
			return array(
				'success' => false,
				'error'   => 'rate_limited',
				'message' => __( 'Too many failed login attempts. Please wait 15 minutes before trying again.', 'fraud-tenant-core' ),
			);
		}

		if ( empty( $clean_login ) || empty( $password ) ) {
			return array(
				'success' => false,
				'error'   => 'empty_credentials',
				'message' => __( 'Please enter both your email address and password.', 'fraud-tenant-core' ),
			);
		}

		// 2. Authenticate through core WordPress APIs
		$user = wp_authenticate( $clean_login, $password );

		if ( is_wp_error( $user ) ) {
			// Increment fail counter
			set_transient( $rate_key, $fail_count + 1, self::LOGIN_LOCKOUT_SECS );
			return array(
				'success' => false,
				'error'   => 'invalid_credentials',
				'message' => __( 'Invalid email address or password.', 'fraud-tenant-core' ),
			);
		}

		// Reset fail counter on successful password verification
		delete_transient( $rate_key );

		// 3. Preserve Core Administrator Access
		if ( in_array( 'administrator', (array) $user->roles, true ) ) {
			wp_set_auth_cookie( $user->ID, $remember );
			return array(
				'success'  => true,
				'user_id'  => $user->ID,
				'role'     => 'administrator',
				'redirect' => admin_url(),
				'message'  => __( 'Welcome back, Administrator.', 'fraud-tenant-core' ),
			);
		}

		// 4. Enforce Account Status Lifecycle for Platform Roles
		$status = self::get_user_account_status( $user->ID );

		switch ( $status ) {
			case Constants::STATUS_EMAIL_UNVERIFIED:
				// Do NOT set auth cookie; redirect to verify OTP
				return array(
					'success'  => false,
					'error'    => 'email_unverified',
					'redirect' => add_query_arg( 'email', urlencode( $user->user_email ), home_url( '/verify-otp/' ) ),
					'message'  => __( 'Please verify your email address to continue.', 'fraud-tenant-core' ),
				);

			case Constants::STATUS_PENDING_APPROVAL:
				// User is verified but pending admin approval. Authenticate session, but route to holding page
				wp_set_auth_cookie( $user->ID, $remember );
				return array(
					'success'  => true,
					'status'   => Constants::STATUS_PENDING_APPROVAL,
					'redirect' => home_url( '/account-status/' ),
					'message'  => __( 'Your account is currently under administrative review.', 'fraud-tenant-core' ),
				);

			case Constants::STATUS_REJECTED:
				return array(
					'success' => false,
					'error'   => 'account_rejected',
					'message' => __( 'Your account application was not approved. Please contact support for assistance.', 'fraud-tenant-core' ),
				);

			case Constants::STATUS_SUSPENDED:
				return array(
					'success' => false,
					'error'   => 'account_suspended',
					'message' => __( 'Your account has been suspended. Please contact support for assistance.', 'fraud-tenant-core' ),
				);

			case Constants::STATUS_APPROVED:
				// Approved account: set auth cookie. Route to holding page / home until Phase 5 dashboards
				wp_set_auth_cookie( $user->ID, $remember );
				return array(
					'success'  => true,
					'status'   => Constants::STATUS_APPROVED,
					'redirect' => home_url( '/account-status/' ),
					'message'  => __( 'Login successful.', 'fraud-tenant-core' ),
				);

			default:
				// No application profile record found: preserve safe holding state
				wp_set_auth_cookie( $user->ID, $remember );
				return array(
					'success'  => true,
					'status'   => 'unknown',
					'redirect' => home_url( '/account-status/' ),
					'message'  => __( 'Login successful.', 'fraud-tenant-core' ),
				);
		}
	}

	/**
	 * Log out the current user safely.
	 */
	public static function logout(): void {
		wp_logout();
		wp_safe_redirect( add_query_arg( 'loggedout', '1', home_url( '/login/' ) ) );
		if ( ! defined( 'FT_DOING_TESTS' ) ) {
			exit;
		}
	}

	/**
	 * Request a password reset link using native WordPress password recovery.
	 *
	 * @param string $login_or_email Email address or login.
	 * @return array Generic response result.
	 */
	public static function request_password_reset( string $login_or_email ): array {
		$clean_input = trim( $login_or_email );

		// IP Rate Limiting (1 request per 60 seconds)
		$ip       = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1' ) );
		$rate_key = 'ft_pw_reset_' . md5( $ip );
		if ( get_transient( $rate_key ) ) {
			return array(
				'success' => false,
				'error'   => 'rate_limited',
				'message' => __( 'Please wait a moment before requesting another password reset.', 'fraud-tenant-core' ),
			);
		}
		set_transient( $rate_key, true, 60 );

		if ( empty( $clean_input ) ) {
			return array(
				'success' => false,
				'error'   => 'empty_input',
				'message' => __( 'Please enter your registered email address.', 'fraud-tenant-core' ),
			);
		}

		$user = get_user_by( 'email', $clean_input ) ?: get_user_by( 'login', $clean_input );

		// Anti-enumeration: If user not found, return generic success
		if ( ! $user ) {
			return array(
				'success' => true,
				'message' => __( 'If an account exists with that email, a password reset link has been dispatched.', 'fraud-tenant-core' ),
			);
		}

		// Generate secure one-time WordPress reset key
		$key = get_password_reset_key( $user );
		if ( is_wp_error( $key ) ) {
			return array(
				'success' => false,
				'error'   => 'key_generation_failed',
				'message' => __( 'Could not process password reset at this time.', 'fraud-tenant-core' ),
			);
		}

		$reset_url = add_query_arg(
			array(
				'key'   => $key,
				'login' => rawurlencode( $user->user_login ),
			),
			home_url( '/reset-password/' )
		);

		// Dispatch Email via wp_mail()
		$subject = __( '[Fraud Tenant] Password Reset Request', 'fraud-tenant-core' );
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		$body    = sprintf( __( 'Hello %s,', 'fraud-tenant-core' ), $user->display_name ) . "\n\n";
		$body   .= __( 'Someone requested a password reset for your Fraud Tenant account.', 'fraud-tenant-core' ) . "\n\n";
		$body   .= sprintf( __( 'To reset your password, visit the following link:%s', 'fraud-tenant-core' ), "\n" . $reset_url ) . "\n\n";
		$body   .= __( 'If you did not make this request, you can safely ignore this email.', 'fraud-tenant-core' ) . "\n\n";
		$body   .= "---\nFraud Tenant Platform\n";

		wp_mail( $user->user_email, $subject, $body, $headers );

		return array(
			'success' => true,
			'message' => __( 'If an account exists with that email, a password reset link has been dispatched.', 'fraud-tenant-core' ),
		);
	}

	/**
	 * Reset user password with verified reset key.
	 *
	 * @param string $key          WordPress reset key.
	 * @param string $login        User login.
	 * @param string $new_password New password.
	 * @param string $confirm_pw   Password confirmation.
	 * @return array
	 */
	public static function reset_password_with_key( string $key, string $login, string $new_password, string $confirm_pw ): array {
		if ( strlen( $new_password ) < self::MIN_PASSWORD_LENGTH ) {
			return array(
				'success' => false,
				'error'   => 'weak_password',
				'message' => sprintf(
					/* translators: %d: minimum length */
					__( 'New password must be at least %d characters long.', 'fraud-tenant-core' ),
					self::MIN_PASSWORD_LENGTH
				),
			);
		}

		if ( $new_password !== $confirm_pw ) {
			return array(
				'success' => false,
				'error'   => 'password_mismatch',
				'message' => __( 'The entered passwords do not match.', 'fraud-tenant-core' ),
			);
		}

		// Verify reset key through core WordPress check_password_reset_key()
		$user = check_password_reset_key( $key, $login );

		if ( is_wp_error( $user ) ) {
			return array(
				'success' => false,
				'error'   => 'invalid_key',
				'message' => __( 'This password reset link is invalid or has expired. Please request a new one.', 'fraud-tenant-core' ),
			);
		}

		// Update user password safely
		reset_password( $user, $new_password );

		AuditLogger::log(
			'auth.password_reset_success',
			'user',
			$user->ID,
			array( 'ip' => sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1' ) ) ),
			$user->ID
		);

		return array(
			'success'  => true,
			'redirect' => add_query_arg( 'pw_reset', 'success', home_url( '/login/' ) ),
			'message'  => __( 'Your password has been reset successfully. You may now sign in with your new password.', 'fraud-tenant-core' ),
		);
	}

	/**
	 * Get application approval status for a user.
	 *
	 * @param int $user_id WP User ID.
	 * @return string Status string or 'unknown'.
	 */
	public static function get_user_account_status( int $user_id ): string {
		global $wpdb;
		$table  = $wpdb->prefix . Constants::TABLE_USER_PROFILES;
		$status = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT approval_status FROM {$table} WHERE user_id = %d LIMIT 1",
				$user_id
			)
		);

		return $status ? (string) $status : 'unknown';
	}

	/**
	 * Retrieve full extended profile object for a user.
	 *
	 * @param int $user_id WP User ID.
	 * @return object|null
	 */
	public static function get_user_profile( int $user_id ): ?object {
		global $wpdb;
		$table   = $wpdb->prefix . Constants::TABLE_USER_PROFILES;
		$profile = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d LIMIT 1",
				$user_id
			)
		);

		return $profile ? (object) $profile : null;
	}

	/**
	 * Generate a unique WordPress username derived from an email address.
	 *
	 * @param string $email Input email address.
	 * @return string Sanitized unique username.
	 */
	protected static function generate_unique_username( string $email ): string {
		$parts = explode( '@', $email );
		$base  = sanitize_user( $parts[0], true );
		$base  = preg_replace( '/[^a-z0-9_]/i', '', $base );

		if ( empty( $base ) ) {
			$base = 'user';
		}

		$username = $base;
		$counter  = 1;

		while ( username_exists( $username ) ) {
			$username = $base . $counter;
			$counter++;
		}

		return $username;
	}
}
