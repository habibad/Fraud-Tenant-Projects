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
		add_action( 'admin_post_nopriv_ft_verify_reset_otp', array( __CLASS__, 'handle_verify_reset_otp' ) );
		add_action( 'admin_post_ft_verify_reset_otp', array( __CLASS__, 'handle_verify_reset_otp' ) );
		add_action( 'admin_post_nopriv_ft_resend_reset_otp', array( __CLASS__, 'handle_resend_reset_otp' ) );
		add_action( 'admin_post_ft_resend_reset_otp', array( __CLASS__, 'handle_resend_reset_otp' ) );
		add_action( 'admin_post_nopriv_ft_set_new_password', array( __CLASS__, 'handle_set_new_password' ) );
		add_action( 'admin_post_ft_set_new_password', array( __CLASS__, 'handle_set_new_password' ) );
		add_action( 'admin_post_nopriv_ft_reset_password', array( __CLASS__, 'handle_reset_password' ) );
		add_action( 'admin_post_ft_reset_password', array( __CLASS__, 'handle_reset_password' ) );

		// Safe Profile Updates
		add_action( 'admin_post_ft_update_profile', array( __CLASS__, 'handle_update_profile' ) );
	}

	/**
	 * Handle Safe Profile Update POST.
	 */
	public static function handle_update_profile(): void {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_die( esc_html__( 'Unauthorized access.', 'fraud-tenant-core' ), 403 );
		}

		$result   = \FraudTenant\Services\DashboardService::update_safe_profile( $user_id, $_POST );
		$fallback = home_url( '/' );
		$redirect = isset( $_POST['redirect_to'] ) ? wp_validate_redirect( wp_sanitize_redirect( $_POST['redirect_to'] ), $fallback ) : $fallback;

		self::set_flash_notice( $result['success'] ? 'success' : 'error', $result['message'] );
		wp_safe_redirect( $redirect );
		if ( ! defined( 'FT_DOING_TESTS' ) ) {
			exit;
		}
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
			// Verified: sign in and go straight to the role dashboard.
			$target = home_url( '/login/' );
			if ( $result['user_id'] > 0 && Constants::STATUS_APPROVED === AuthService::get_user_account_status( (int) $result['user_id'] ) ) {
				wp_set_auth_cookie( $result['user_id'], false );
				$target = add_query_arg( 'welcome', '1', AuthService::dashboard_url( (int) $result['user_id'] ) );
			}
			self::set_flash_notice( 'success', $result['message'] );
			wp_safe_redirect( $target );
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
	 * Handle Forgot Password POST (Requests a 6-digit Reset OTP).
	 */
	public static function handle_forgot_password(): void {
		if ( ! isset( $_POST['ft_forgot_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ft_forgot_nonce'] ) ), 'ft_forgot_action' ) ) {
			wp_die( esc_html__( 'Security check failed. Please refresh the page and try again.', 'fraud-tenant-core' ), 403 );
		}

		$login_or_email = isset( $_POST['user_login'] ) ? sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) : '';
		$result         = AuthService::request_password_reset( $login_or_email );

		if ( $result['success'] ) {
			self::set_flash_notice( 'success', $result['message'] );
			$redirect = $result['redirect'] ?? add_query_arg( array( 'email' => urlencode( $login_or_email ), 'step' => 'otp' ), home_url( '/reset-password/' ) );
			wp_safe_redirect( $redirect );
		} else {
			self::set_flash_notice( 'error', $result['message'] );
			wp_safe_redirect( home_url( '/forgot-password/' ) );
		}

		if ( ! defined( 'FT_DOING_TESTS' ) ) {
			exit;
		}
	}

	/**
	 * Handle Reset OTP Verification POST.
	 */
	public static function handle_verify_reset_otp(): void {
		if ( ! isset( $_POST['ft_verify_reset_otp_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ft_verify_reset_otp_nonce'] ) ), 'ft_verify_reset_otp_action' ) ) {
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

		$result = AuthService::verify_password_reset_otp( $email, $otp );

		if ( $result['success'] ) {
			self::set_flash_notice( 'success', $result['message'] );
			wp_safe_redirect( $result['redirect'] );
		} else {
			self::set_flash_notice( 'error', $result['message'] );
			wp_safe_redirect( add_query_arg( array( 'email' => urlencode( $email ), 'step' => 'otp' ), home_url( '/reset-password/' ) ) );
		}

		if ( ! defined( 'FT_DOING_TESTS' ) ) {
			exit;
		}
	}

	/**
	 * Handle Resend Reset OTP POST.
	 */
	public static function handle_resend_reset_otp(): void {
		if ( ! isset( $_POST['ft_resend_reset_otp_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ft_resend_reset_otp_nonce'] ) ), 'ft_resend_reset_otp_action' ) ) {
			wp_die( esc_html__( 'Security check failed. Please refresh the page and try again.', 'fraud-tenant-core' ), 403 );
		}

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$user  = get_user_by( 'email', $email );

		if ( $user ) {
			$result = OtpService::generate_otp( $user->ID, $email, 'password_reset' );
			if ( $result['success'] ) {
				self::set_flash_notice( 'success', __( 'A new verification code has been dispatched to your email address.', 'fraud-tenant-core' ) );
			} else {
				self::set_flash_notice( 'error', $result['message'] );
			}
		} else {
			// Generic anti-enumeration response
			self::set_flash_notice( 'success', __( 'A new verification code has been dispatched to your email address.', 'fraud-tenant-core' ) );
		}

		wp_safe_redirect( add_query_arg( array( 'email' => urlencode( $email ), 'step' => 'otp' ), home_url( '/reset-password/' ) ) );
		if ( ! defined( 'FT_DOING_TESTS' ) ) {
			exit;
		}
	}

	/**
	 * Handle Set New Password POST (Using verified single-use reset token).
	 */
	public static function handle_set_new_password(): void {
		if ( ! isset( $_POST['ft_set_new_password_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ft_set_new_password_nonce'] ) ), 'ft_set_new_password_action' ) ) {
			wp_die( esc_html__( 'Security check failed. Please refresh the page and try again.', 'fraud-tenant-core' ), 403 );
		}

		// The reset authorization travels only in the HttpOnly cookie (never in URL/HTML).
		$token = AuthService::get_reset_cookie_token();
		if ( '' === $token && defined( 'FT_DOING_TESTS' ) && isset( $_POST['reset_token'] ) ) {
			$token = sanitize_text_field( wp_unslash( $_POST['reset_token'] ) ); // CLI test harness only.
		}
		$new_pw     = isset( $_POST['new_password'] ) ? (string) $_POST['new_password'] : '';
		$confirm_pw = isset( $_POST['confirm_password'] ) ? (string) $_POST['confirm_password'] : '';

		$result = AuthService::complete_password_reset( $token, $new_pw, $confirm_pw );

		if ( $result['success'] ) {
			self::set_flash_notice( 'success', $result['message'] );
			wp_safe_redirect( $result['redirect'] );
		} else {
			self::set_flash_notice( 'error', $result['message'] );
			if ( 'invalid_or_expired_token' === ( $result['error'] ?? '' ) ) {
				wp_safe_redirect( home_url( '/forgot-password/' ) );
			} else {
				wp_safe_redirect( add_query_arg( array( 'step' => 'new_password' ), home_url( '/reset-password/' ) ) );
			}
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
		set_transient(
			self::flash_key( 'notice' ),
			array(
				'type'       => $type,
				'message'    => $message,
				'error_code' => $error_code,
			),
			180
		);
	}

	/**
	 * Per-visitor flash storage key. Signed-in users are keyed by user ID; anonymous visitors by a
	 * random HttpOnly cookie. (Never by IP: users behind one NAT/proxy would see each other's data.)
	 *
	 * @param string $kind   notice|form.
	 * @param bool   $anon   Force the anonymous key.
	 * @return string
	 */
	protected static function flash_key( string $kind, bool $anon = false ): string {
		$uid = get_current_user_id();
		if ( $uid && ! $anon ) {
			return 'ft_flash_' . $kind . '_u' . $uid;
		}
		$tok = isset( $_COOKIE['ft_flash'] ) ? (string) $_COOKIE['ft_flash'] : '';
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $tok ) ) {
			$tok = bin2hex( random_bytes( 16 ) );
			if ( ! headers_sent() ) {
				setcookie( 'ft_flash', $tok, array( 'expires' => time() + 900, 'path' => COOKIEPATH ? COOKIEPATH : '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
			}
			$_COOKIE['ft_flash'] = $tok;
		}
		return 'ft_flash_' . $kind . '_a' . md5( $tok );
	}

	/**
	 * Read and consume a flash value (user key first, then this browser's anonymous key — covers
	 * messages set during the request that signed the user in).
	 *
	 * @param string $kind notice|form.
	 * @return mixed
	 */
	protected static function take_flash( string $kind ) {
		foreach ( array( false, true ) as $anon ) {
			if ( $anon && empty( $_COOKIE['ft_flash'] ) ) {
				continue;
			}
			$key = self::flash_key( $kind, $anon );
			$val = get_transient( $key );
			if ( false !== $val ) {
				delete_transient( $key );
				return $val;
			}
		}
		return null;
	}

	/**
	 * Get and consume flash notice.
	 *
	 * @return array|null Notice array or null.
	 */
	public static function get_flash_notice(): ?array {
		$val = self::take_flash( 'notice' );
		return $val ? (array) $val : null;
	}

	/**
	 * Store draft form data temporarily for repopulation on validation error.
	 *
	 * @param array $data Form data (sanitized, excluding passwords and SSNs).
	 */
	public static function set_flash_form_data( array $data ): void {
		unset( $data['password'], $data['confirm_password'], $data['tenant_ssn'], $data['applicant_ssn'], $data['ssn'] );
		set_transient( self::flash_key( 'form' ), $data, 180 );
	}

	/**
	 * Retrieve and consume draft form data.
	 *
	 * @return array Draft data or empty array.
	 */
	public static function get_flash_form_data(): array {
		$val = self::take_flash( 'form' );
		return is_array( $val ) ? $val : array();
	}
}

