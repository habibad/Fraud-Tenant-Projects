<?php
/**
 * Web Form and AJAX Request Handler for Incident Reporting Operations.
 *
 * Handles authenticated property owner incident report submissions, CSRF nonce verification,
 * file upload delegation, flash notices, and safe redirects.
 *
 * @package FraudTenant\Services
 */

namespace FraudTenant\Services;

use FraudTenant\Auth\AuthHandler;
use FraudTenant\Services\IncidentService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IncidentHandler {

	/**
	 * Register web POST and AJAX hooks for incident reporting actions.
	 */
	public static function register(): void {
		add_action( 'admin_post_ft_submit_incident', array( __CLASS__, 'handle_submit_incident' ) );
		add_action( 'wp_ajax_ft_submit_incident', array( __CLASS__, 'handle_ajax_submit_incident' ) );
	}

	/**
	 * Handle standard web POST submission for incident report.
	 */
	public static function handle_submit_incident(): void {
		// 0. Production Privacy & Evidence Gate (BLK-08 Unresolved)
		// Evaluated strictly before reading form values, narrative, or $_FILES.
		if ( ! \FraudTenant\Services\ComplianceService::is_data_gate_open() ) {
			wp_die(
				esc_html__( 'Incident submission is currently locked pending completion of sensitive PII encryption infrastructure (BLK-08). Non-production test mode is required for synthetic testing.', 'fraud-tenant-core' ),
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
		$nonce = isset( $_POST['ft_incident_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ft_incident_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ft_submit_incident_action' ) ) {
			wp_die( esc_html__( 'Security check failed. Please refresh the page and try again.', 'fraud-tenant-core' ), 403 );
		}

		// 2. Extract optional uploaded evidence file
		$file_upload = null;
		if ( isset( $_FILES['incident_evidence'] ) && is_array( $_FILES['incident_evidence'] ) ) {
			$file_upload = $_FILES['incident_evidence'];
		}

		// 3. Delegate to IncidentService
		$result = IncidentService::record_incident_report( $user_id, $_POST, $file_upload );

		// 4. Resolve Safe Redirect
		$fallback = home_url( '/owner-dashboard/#my-reports' );
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
	 * Handle asynchronous AJAX submission for incident report.
	 */
	public static function handle_ajax_submit_incident(): void {
		// 0. Production Privacy & Evidence Gate (BLK-08 Unresolved)
		// Evaluated strictly before reading form values, narrative, or $_FILES.
		if ( ! \FraudTenant\Services\ComplianceService::is_data_gate_open() ) {
			wp_send_json_error(
				array(
					'error'   => 'submission_locked_blk08',
					'message' => __( 'Incident submission is currently locked pending completion of sensitive PII encryption infrastructure (BLK-08). Non-production test mode is required for synthetic testing.', 'fraud-tenant-core' ),
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

		$nonce = isset( $_POST['ft_incident_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ft_incident_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ft_submit_incident_action' ) ) {
			wp_send_json_error(
				array(
					'error'   => 'invalid_nonce',
					'message' => __( 'Security check failed. Please refresh the page.', 'fraud-tenant-core' ),
				),
				403
			);
		}

		$user_id     = get_current_user_id();
		$file_upload = isset( $_FILES['incident_evidence'] ) && is_array( $_FILES['incident_evidence'] ) ? $_FILES['incident_evidence'] : null;
		$result      = IncidentService::record_incident_report( $user_id, $_POST, $file_upload );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			$status_code = 400;
			if ( 'unauthorized' === ( $result['error'] ?? '' ) ) {
				$status_code = 403;
			} elseif ( 'rate_limited' === ( $result['error'] ?? '' ) ) {
				$status_code = 429;
			} elseif ( 'submission_locked_blk08' === ( $result['error'] ?? '' ) ) {
				$status_code = 423; // Locked
			}

			wp_send_json_error( $result, $status_code );
		}
	}
}

