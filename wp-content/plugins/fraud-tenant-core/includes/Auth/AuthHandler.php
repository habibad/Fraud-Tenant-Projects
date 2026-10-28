<?php
/**
 * Web Form Request Handler for Authentication Operations.
 *
 * @package FraudTenant\Auth
 */

namespace FraudTenant\Auth;

use FraudTenant\Services\OtpService;
use FraudTenant\Utils\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AuthHandler {

	/**
	 * Register web post hooks.
	 */
	public static function register(): void {
		// Registration
		add_action( 'admin_post_nopriv_ft_register', array( __CLASS__, 'handle_register' ) );
		add_action( 'admin_post_ft_register', array( __CLASS__, 'handle_register' ) );

		// OTP Verification & Resend
		add_action( 'admin_post_nopriv_ft_verify_otp', array( __CLASS__, 'handle_verify_otp' ) );
		add_action( 'admin_post_ft_verify_otp', array( __CLASS__, 'handle_verify_otp' ) );
		add_action( 'admin_post_nopriv_ft_resend_otp', array( __CLASS__, 'handle_resend_otp' ) );
		add_action( 'admin_post_ft_resend_otp', array( __CLASS__, 'handle_resend_otp' ) );

		// Login & Logout
		add_action( 'admin_post_nopriv_ft_login', array( __CLASS__, 'handle_login' ) );
		add_action( 'admin_post_ft_login', array( __CLASS__, 'handle_login' ) );
		add_action( 'admin_post_ft_logout', array( __CLASS__, 'handle_logout' ) );

		// Password Recovery
		add_action( 'admin_post_nopriv_ft_forgot_password', array( __CLASS__, 'handle_forgot_password' ) );
		add_action( 'admin_post_ft_forgot_password', array( __CLASS__, 'handle_forgot_password' ) );
		add_action( 'admin_post_nopriv_ft_reset_password', array( __CLASS__, 'handle_reset_password' ) );
		add_action( 'admin_post_ft_reset_password', array( __CLASS__, 'handle_reset_password' ) );
	}

	/**
	 * Handle Registration POST.
	 */
	public static function handle_register(): void {
		if ( ! isset( $_POST['ft_register_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ft_register_nonce'] ) ), 'ft_register_action' ) ) {
			wp_die( esc_html__( 'Security check failed. Please refresh the registration page and try again.', 'fraud-tenant-core' ), 403 );
		}

		$role   = isset( $_POST['account_type'] ) && 'tenant' === sanitize_key( $_POST['account_type'] ) ? 'tenant' : 'owner';
		$result = AuthService::register( $_POST, $_FILES );

		if ( $result['success'] ) {
			wp_safe_redirect( $result['redirect'] );
		} else {
			$preserved_data = $_POST;
			unset( $preserved_data['password'], $preserved_data['confirm_password'], $preserved_data['ft_register_nonce'] );
			self::set_flash_form_data( $preserved_data );
			self::set_flash_notice( 'error', $result['message'], $result['error'] ?? '' );
			wp_safe_redirect( add_query_arg( 'role', $role, home_url( '/register/' ) ) );
		}

		if ( ! defined( 'FT_DOING_TESTS' ) ) {
			exit;
		}
	}

	/**
	 * Handle OTP Verification POST.
	 */
	public static function handle_verify_otp(): void {
		if ( ! isset( $_POST['ft_otp_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ft_otp_nonce'] ) ), 'ft_otp_action' ) ) {
			wp_die( esc_html__( 'Security check failed. Please refresh the page and try again.', 'fraud-tenant-core' ), 403 );
		}

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

		// Assemble 6-digit OTP from individual boxes or full input
		$otp = '';
		if ( isset( $_POST['otp_code'] ) && ! empty( $_POST['otp_code'] ) ) {
			$otp = sanitize_text_field( wp_unslash( $_POST['otp_code'] ) );
		} else {
			for ( $i = 1; $i <= 6; $i++ ) {
				$digit = isset( $_POST[ 'otp_' . $i ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'otp_' . $i ] ) ) : '';
				$otp  .= $digit;
			}
		}

		$result = OtpService::verify_otp( $email, $otp, 'registration' );

		if ( $result['success'] ) {
			// Auto sign-in to the safe pending state
			if ( $result['user_id'] > 0 ) {
				wp_set_auth_cookie( $result['user_id'], false );
			}
			wp_safe_redirect( add_query_arg( 'verified', '1', home_url( '/account-status/' ) ) );
		} else {
			self::set_flash_notice( 'error', $result['message'] );
			wp_safe_redirect( add_query_arg( 'email', urlencode( $email ), home_url( '/verify-otp/' ) ) );
		}

		if ( ! defined( 'FT_DOING_TESTS' ) ) {
			exit;
		}
	}

	/**
	 * Handle OTP Resend POST.
	 */
	public static function handle_resend_otp(): void {
		if ( ! isset( $_POST['ft_resend_otp_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ft_resend_otp_nonce'] ) ), 'ft_resend_otp_action' ) ) {
			wp_die( esc_html__( 'Security check failed. Please refresh the page and try again.', 'fraud-tenant-core' ), 403 );
		}

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$user  = get_user_by( 'email', $email );

		if ( $user ) {
			$result = OtpService::generate_otp( $user->ID, $email, 'registration' );
			if ( $result['success'] ) {
				self::set_flash_notice( 'success', __( 'A new verification code has been dispatched to your email address.', 'fraud-tenant-core' ) );
			} else {
				self::set_flash_notice( 'error', $result['message'] );
			}
		} else {
			// Generic anti-enumeration response
			self::set_flash_notice( 'success', __( 'A new verification code has been dispatched to your email address.', 'fraud-tenant-core' ) );
		}

		wp_safe_redirect( add_query_arg( 'email', urlencode( $email ), home_url( '/verify-otp/' ) ) );
		if ( ! defined( 'FT_DOING_TESTS' ) ) {
			exit;
		}
	}

	/**
	 * Handle Login POST.
	 */
	public static function handle_login(): void {
		if ( ! isset( $_POST['ft_login_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ft_login_nonce'] ) ), 'ft_login_action' ) ) {
			wp_die( esc_html__( 'Security check failed. Please refresh the login page and try again.', 'fraud-tenant-core' ), 403 );
		}

		$login_input = isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( $_POST['log'] ) ) : '';
		$password    = isset( $_POST['pwd'] ) ? (string) $_POST['pwd'] : '';
		$remember    = ! empty( $_POST['rememberme'] );

		$result = AuthService::login( $login_input, $password, $remember );

		if ( $result['success'] ) {
			wp_safe_redirect( $result['redirect'] );
		} else {
			self::set_flash_notice( 'error', $result['message'] );
			if ( isset( $result['redirect'] ) ) {
				wp_safe_redirect( $result['redirect'] );
			} else {
				wp_safe_redirect( home_url( '/login/' ) );
			}
		}

		if ( ! defined( 'FT_DOING_TESTS' ) ) {
			exit;
		}
	}

	/**
	 * Handle Logout GET/POST.
	 */
	public static function handle_logout(): void {
		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'ft_logout_action' ) ) {
			wp_die( esc_html__( 'Security check failed on logout.', 'fraud-tenant-core' ), 403 );
		}

		AuthService::logout();
	}

	/**
	 * Handle Forgot Password POST.
	 */
	public static function handle_forgot_password(): void {
		if ( ! isset( $_POST['ft_forgot_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ft_forgot_nonce'] ) ), 'ft_forgot_action' ) ) {
			wp_die( esc_html__( 'Security check failed. Please refresh the page and try again.', 'fraud-tenant-core' ), 403 );
		}

		$login_or_email = isset( $_POST['user_login'] ) ? sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) : '';
		$result         = AuthService::request_password_reset( $login_or_email );

		if ( $result['success'] ) {
			self::set_flash_notice( 'success', $result['message'] );
			wp_safe_redirect( add_query_arg( 'sent', '1', home_url( '/forgot-password/' ) ) );
		} else {
			self::set_flash_notice( 'error', $result['message'] );
			wp_safe_redirect( home_url( '/forgot-password/' ) );
		}

		if ( ! defined( 'FT_DOING_TESTS' ) ) {
			exit;
		}
	}

	/**
	 * Handle Reset Password POST.
	 */
	public static function handle_reset_password(): void {
		if ( ! isset( $_POST['ft_reset_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ft_reset_nonce'] ) ), 'ft_reset_action' ) ) {
			wp_die( esc_html__( 'Security check failed. Please refresh the page and try again.', 'fraud-tenant-core' ), 403 );
		}

		$key         = isset( $_POST['rp_key'] ) ? sanitize_text_field( wp_unslash( $_POST['rp_key'] ) ) : '';
		$login       = isset( $_POST['rp_login'] ) ? sanitize_text_field( wp_unslash( $_POST['rp_login'] ) ) : '';
		$new_pw      = isset( $_POST['new_password'] ) ? (string) $_POST['new_password'] : '';
		$confirm_pw  = isset( $_POST['confirm_password'] ) ? (string) $_POST['confirm_password'] : '';

		$result = AuthService::reset_password_with_key( $key, $login, $new_pw, $confirm_pw );

		if ( $result['success'] ) {
			self::set_flash_notice( 'success', $result['message'] );
			wp_safe_redirect( $result['redirect'] );
		} else {
			self::set_flash_notice( 'error', $result['message'] );
			$redir = add_query_arg(
				array(
					'key'   => $key,
					'login' => rawurlencode( $login ),
				),
				home_url( '/reset-password/' )
			);
			wp_safe_redirect( $redir );
		}

		if ( ! defined( 'FT_DOING_TESTS' ) ) {
			exit;
		}
	}

	/**
	 * Set a temporary flash notice stored in a short-lived transient.
	 *
	 * @param string $type       'success' or 'error'.
	 * @param string $message    Notice text.
	 * @param string $error_code Optional error machine code.
	 */
	public static function set_flash_notice( string $type, string $message, string $error_code = '' ): void {
		$ip  = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1' ) );
		$key = 'ft_auth_notice_' . md5( $ip );
		set_transient(
			$key,
			array(
				'type'       => $type,
				'message'    => $message,
				'error_code' => $error_code,
			),
			180
		);
	}

	/**
	 * Get and consume flash notice.
	 *
	 * @return array|null Notice array or null.
	 */
	public static function get_flash_notice(): ?array {
		$ip  = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1' ) );
		$key = 'ft_auth_notice_' . md5( $ip );
		$val = get_transient( $key );
		if ( $val ) {
			delete_transient( $key );
			return (array) $val;
		}
		return null;
	}

	/**
	 * Store draft form data temporarily for repopulation on validation error.
	 *
	 * @param array $data Form data (sanitized, excluding passwords).
	 */
	public static function set_flash_form_data( array $data ): void {
		$ip  = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1' ) );
		$key = 'ft_auth_form_' . md5( $ip );
		set_transient( $key, $data, 180 );
	}

	/**
	 * Retrieve and consume draft form data.
	 *
	 * @return array Draft data or empty array.
	 */
	public static function get_flash_form_data(): array {
		$ip  = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1' ) );
		$key = 'ft_auth_form_' . md5( $ip );
		$val = get_transient( $key );
		if ( $val && is_array( $val ) ) {
			delete_transient( $key );
			return $val;
		}
		return array();
	}
}

