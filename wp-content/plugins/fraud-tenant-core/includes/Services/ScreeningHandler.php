<?php
/**
 * Web Form and AJAX Request Handler for Screening Operations.
 *
 * Handles authenticated property owner screening requests, CSRF nonce verification,
 * flash notices, and safe redirects.
 *
 * @package FraudTenant\Services
 */

namespace FraudTenant\Services;

use FraudTenant\Auth\AuthHandler;
use FraudTenant\Services\ScreeningService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ScreeningHandler {

	/**
	 * Register web POST and AJAX hooks for screening actions.
	 */
	public static function register(): void {
		add_action( 'admin_post_ft_initiate_screening', array( __CLASS__, 'handle_initiate_screening' ) );
		add_action( 'wp_ajax_ft_initiate_screening', array( __CLASS__, 'handle_ajax_initiate_screening' ) );
	}

	/**
	 * Handle standard web POST submission for tenant screening request.
	 */
	public static function handle_initiate_screening(): void {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( home_url( '/login/' ) );
			if ( ! defined( 'FT_DOING_TESTS' ) ) {
				exit;
			}
			return;
		}

		$user_id = get_current_user_id();

		// 1. Nonce CSRF Verification
		$nonce = isset( $_POST['ft_screening_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ft_screening_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ft_initiate_screening_action' ) ) {
			wp_die( esc_html__( 'Security check failed. Please refresh the page and try again.', 'fraud-tenant-core' ), 403 );
		}

		// 2. Delegate to ScreeningService
		$result = ScreeningService::record_screening_request( $user_id, $_POST );

		// 3. Resolve Safe Redirect
		$fallback = home_url( '/owner-dashboard/#recent-searches' );
		$redirect = isset( $_POST['redirect_to'] ) ? wp_validate_redirect( wp_sanitize_redirect( $_POST['redirect_to'] ), $fallback ) : $fallback;

		if ( $result['success'] ) {
			AuthHandler::set_flash_notice( 'success', $result['message'] );
		} else {
			AuthHandler::set_flash_notice( 'error', $result['message'] );
		}

		wp_safe_redirect( $redirect );
		if ( ! defined( 'FT_DOING_TESTS' ) ) {
			exit;
		}
	}

	/**
	 * Handle asynchronous AJAX submission for tenant screening request.
	 */
	public static function handle_ajax_initiate_screening(): void {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				array(
					'error'   => 'unauthenticated',
					'message' => __( 'Authentication is required.', 'fraud-tenant-core' ),
				),
				401
			);
		}

		$nonce = isset( $_POST['ft_screening_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ft_screening_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ft_initiate_screening_action' ) ) {
			wp_send_json_error(
				array(
					'error'   => 'invalid_nonce',
					'message' => __( 'Security check failed. Please refresh the page.', 'fraud-tenant-core' ),
				),
				403
			);
		}

		$user_id = get_current_user_id();
		$result  = ScreeningService::record_screening_request( $user_id, $_POST );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			$status_code = 'unauthorized' === ( $result['error'] ?? '' ) ? 403 : 400;
			if ( 'rate_limited' === ( $result['error'] ?? '' ) ) {
				$status_code = 429;
			}
			wp_send_json_error( $result, $status_code );
		}
	}
}

