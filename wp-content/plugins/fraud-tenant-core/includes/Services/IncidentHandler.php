<?php
/**
 * Owner incident report request handlers (submission + clarification response).
 * Nonce for CSRF; authorization, membership and validation are enforced by IncidentService.
 *
 * @package FraudTenant\Services
 */

namespace FraudTenant\Services;

use FraudTenant\Auth\AuthHandler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IncidentHandler {

	/**
	 * Register handlers.
	 */
	public static function register(): void {
		add_action( 'admin_post_ft_submit_incident', array( __CLASS__, 'handle_submit_incident' ) );
		add_action( 'admin_post_ft_incident_clarify', array( __CLASS__, 'handle_clarify' ) );
	}

	/**
	 * New incident report.
	 */
	public static function handle_submit_incident(): void {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_safe_redirect( home_url( '/login/' ) );
			exit;
		}
		$nonce = isset( $_POST['ft_incident_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ft_incident_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ft_submit_incident_action' ) ) {
			wp_die( esc_html__( 'Security check failed. Please refresh the page and try again.', 'fraud-tenant-core' ), 403 );
		}
		$file   = isset( $_FILES['incident_evidence'] ) && is_array( $_FILES['incident_evidence'] ) ? $_FILES['incident_evidence'] : null;
		$result = IncidentService::record_incident_report( $user_id, wp_unslash( $_POST ), $file );
		unset( $_POST['tenant_ssn'] );

		if ( $result['success'] ) {
			AuthHandler::set_flash_notice( 'success', $result['message'] );
			wp_safe_redirect( add_query_arg( array( 'section' => 'reports', 'report_id' => (int) $result['incident_id'] ), home_url( '/owner-dashboard/' ) ) );
		} else {
			$keep = array_intersect_key( wp_unslash( $_POST ), array_flip( array( 'property_id', 'category', 'incident_date', 'amount_involved', 'factual_description', 'tenant_first_name', 'tenant_last_name' ) ) );
			AuthHandler::set_flash_form_data( $keep ); // The SSN is never echoed back.
			AuthHandler::set_flash_notice( 'error', $result['message'] );
			wp_safe_redirect( add_query_arg( 'section', 'report', home_url( '/owner-dashboard/' ) ) );
		}
		exit;
	}

	/**
	 * Owner response to a clarification request.
	 */
	public static function handle_clarify(): void {
		$user_id   = get_current_user_id();
		$report_id = isset( $_POST['report_id'] ) ? absint( $_POST['report_id'] ) : 0;
		if ( ! $user_id || ! check_admin_referer( 'ft_incident_clarify_' . $report_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'fraud-tenant-core' ), 403 );
		}
		$file   = isset( $_FILES['incident_evidence'] ) && is_array( $_FILES['incident_evidence'] ) ? $_FILES['incident_evidence'] : null;
		$result = IncidentService::respond_to_clarification( $report_id, $user_id, wp_unslash( $_POST ), $file );
		unset( $_POST['tenant_ssn'] );
		AuthHandler::set_flash_notice( $result['success'] ? 'success' : 'error', $result['message'] );
		wp_safe_redirect( add_query_arg( array( 'section' => 'reports', 'report_id' => $report_id ), home_url( '/owner-dashboard/' ) ) );
		exit;
	}
}
