<?php
/**
 * Tenant dispute submission handler. Nonce for CSRF; visibility, duplicates and validation are
 * enforced by DisputeService (the report must be visible to this tenant through their identity).
 *
 * @package FraudTenant\Services
 */

namespace FraudTenant\Services;

use FraudTenant\Auth\AuthHandler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DisputeHandler {

	/**
	 * Register handlers.
	 */
	public static function register(): void {
		add_action( 'admin_post_ft_submit_tenant_dispute', array( __CLASS__, 'handle_submit_dispute' ) );
	}

	/**
	 * Submit a dispute.
	 */
	public static function handle_submit_dispute(): void {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_safe_redirect( home_url( '/login/' ) );
			exit;
		}
		$nonce = isset( $_POST['ft_dispute_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ft_dispute_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ft_submit_dispute_' . $user_id ) ) {
			wp_die( esc_html__( 'Security check failed. Please refresh the page and try again.', 'fraud-tenant-core' ), 403 );
		}
		$report_id = isset( $_POST['report_id'] ) ? absint( $_POST['report_id'] ) : 0;
		$reason    = isset( $_POST['dispute_reason'] ) ? sanitize_key( $_POST['dispute_reason'] ) : '';
		$statement = isset( $_POST['statement'] ) ? (string) wp_unslash( $_POST['statement'] ) : '';
		$file      = isset( $_FILES['dispute_evidence'] ) && is_array( $_FILES['dispute_evidence'] ) && ! empty( $_FILES['dispute_evidence']['name'] ) ? $_FILES['dispute_evidence'] : null;

		$result = DisputeService::submit_dispute( $report_id, $user_id, $reason, $statement, $file );
		AuthHandler::set_flash_notice( $result['success'] ? 'success' : 'error', $result['message'] );
		if ( $result['success'] ) {
			wp_safe_redirect( add_query_arg( array( 'section' => 'disputes', 'dispute_id' => (int) $result['dispute_id'] ), home_url( '/tenant-dashboard/' ) ) );
		} else {
			AuthHandler::set_flash_form_data( array( 'report_id' => $report_id, 'dispute_reason' => $reason, 'statement' => sanitize_textarea_field( $statement ) ) );
			wp_safe_redirect( add_query_arg( array( 'section' => 'submit-dispute', 'report_id' => $report_id ), home_url( '/tenant-dashboard/' ) ) );
		}
		exit;
	}
}
