<?php
/**
 * Web Form and AJAX Request Handler for Tenant Dispute Operations.
 *
 * Handles authenticated tenant dispute submissions, CSRF nonce verification,
 * file upload delegation, flash notices, and safe redirects.
 *
 * @package FraudTenant\Services
 */

namespace FraudTenant\Services;

use FraudTenant\Auth\AuthHandler;
use FraudTenant\Auth\Capabilities;
use FraudTenant\Services\DisputeService;
use FraudTenant\Services\IncidentService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DisputeHandler {

	/**
	 * Register web POST and AJAX hooks for dispute actions.
	 */
	public static function register(): void {
		add_action( 'admin_post_ft_submit_tenant_dispute', array( __CLASS__, 'handle_submit_dispute' ) );
		add_action( 'wp_ajax_ft_submit_tenant_dispute', array( __CLASS__, 'handle_ajax_submit_dispute' ) );
	}

	/**
	 * Handle standard web POST submission for tenant dispute.
	 */
	public static function handle_submit_dispute(): void {
		// 0. Production Privacy & Evidence Gate (BLK-08 Unresolved)
		if ( ! \FraudTenant\Services\ComplianceService::is_data_gate_open() ) {
			wp_die(
				esc_html__( 'Dispute submission is currently locked pending completion of sensitive PII encryption infrastructure (BLK-08). Non-production test mode is required for synthetic testing.', 'fraud-tenant-core' ),
				esc_html__( 'Locked', 'fraud-tenant-core' ),
				array( 'response' => 423 )
			);
		}

		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( home_url( '/login/' ) );
			if ( ! defined( 'FT_DOING_TESTS' ) ) {
				exit;
			}
			return;
		}

		$user_id = get_current_user_id();

		// 1. Nonce CSRF Verification
		$nonce = isset( $_POST['ft_dispute_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ft_dispute_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ft_submit_dispute_' . $user_id ) && ! wp_verify_nonce( $nonce, 'ft_submit_tenant_dispute' ) ) {
			wp_die( esc_html__( 'Security check failed. Please refresh the page and try again.', 'fraud-tenant-core' ), 403 );
		}

		$report_id = isset( $_POST['report_id'] ) ? (int) $_POST['report_id'] : 0;
		$reason    = isset( $_POST['dispute_reason'] ) ? sanitize_key( $_POST['dispute_reason'] ) : '';
		$statement = isset( $_POST['statement'] ) ? sanitize_textarea_field( wp_unslash( $_POST['statement'] ) ) : '';

		// 2. Extract optional uploaded evidence file
		$file_upload = null;
		if ( isset( $_FILES['dispute_evidence'] ) && is_array( $_FILES['dispute_evidence'] ) && ! empty( $_FILES['dispute_evidence']['name'] ) ) {
			$file_upload = $_FILES['dispute_evidence'];
		}

		// 3. Delegate to DisputeService
		$result = DisputeService::submit_dispute( $report_id, $user_id, $reason, $statement, $file_upload );

		// 4. Resolve Safe Redirect
		$fallback = home_url( '/tenant-dashboard/#my-disputes' );
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
	 * Handle asynchronous AJAX submission for tenant dispute.
	 */
	public static function handle_ajax_submit_dispute(): void {
		// 0. Production Privacy & Evidence Gate (BLK-08 Unresolved)
		if ( ! \FraudTenant\Services\ComplianceService::is_data_gate_open() ) {
			wp_send_json_error(
				array(
					'error'   => 'submission_locked_blk08',
					'message' => __( 'Dispute submission is currently locked pending completion of sensitive PII encryption infrastructure (BLK-08). Non-production test mode is required for synthetic testing.', 'fraud-tenant-core' ),
				),
				423
			);
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				array(
					'error'   => 'unauthenticated',
					'message' => __( 'Authentication is required.', 'fraud-tenant-core' ),
				),
				401
			);
		}

		$user_id = get_current_user_id();
		$nonce   = isset( $_POST['ft_dispute_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ft_dispute_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'ft_submit_dispute_' . $user_id ) && ! wp_verify_nonce( $nonce, 'ft_submit_tenant_dispute' ) ) {
			wp_send_json_error(
				array(
					'error'   => 'invalid_nonce',
					'message' => __( 'Security check failed. Please refresh the page.', 'fraud-tenant-core' ),
				),
				403
			);
		}

		$report_id   = isset( $_POST['report_id'] ) ? (int) $_POST['report_id'] : 0;
		$reason      = isset( $_POST['dispute_reason'] ) ? sanitize_key( $_POST['dispute_reason'] ) : '';
		$statement   = isset( $_POST['statement'] ) ? sanitize_textarea_field( wp_unslash( $_POST['statement'] ) ) : '';
		$file_upload = isset( $_FILES['dispute_evidence'] ) && is_array( $_FILES['dispute_evidence'] ) && ! empty( $_FILES['dispute_evidence']['name'] ) ? $_FILES['dispute_evidence'] : null;

		$result = DisputeService::submit_dispute( $report_id, $user_id, $reason, $statement, $file_upload );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			$status_code = 400;
			if ( in_array( $result['error'] ?? '', array( 'unauthorized_role', 'not_associated_or_not_visible' ), true ) ) {
				$status_code = 403;
			} elseif ( 'submission_locked_blk08' === ( $result['error'] ?? '' ) ) {
				$status_code = 423;
			}

			wp_send_json_error( $result, $status_code );
		}
	}
}

