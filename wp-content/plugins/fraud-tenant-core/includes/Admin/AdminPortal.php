<?php
/**
 * Administrative portal bootstrap.
 *
 * - AdminConsole: the Admin Command Center (custom dashboard + equivalent wp-admin menus).
 * - Stripe settings / membership plan handlers (existing, reused by both interfaces).
 * - Authorized streaming route for protected documents (all roles; permission checked per document).
 *
 * @package FraudTenant\Admin
 */

namespace FraudTenant\Admin;

use FraudTenant\Services\DocumentService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminPortal {

	/**
	 * Initialize the Admin Portal.
	 */
	public static function init(): void {
		AdminConsole::init();

		add_action( 'admin_post_ft_admin_membership_plan_action', array( MembershipAdminController::class, 'handle_admin_action' ) );
		add_action( 'admin_post_ft_save_stripe_settings', array( StripeSettingsAdminController::class, 'handle_save' ) );
		add_action( 'admin_post_ft_remove_stripe_settings', array( StripeSettingsAdminController::class, 'handle_remove' ) );
		add_action( 'admin_post_ft_test_stripe_connection', array( StripeSettingsAdminController::class, 'handle_test_connection' ) );
		add_action( 'admin_post_ft_switch_stripe_mode', array( StripeSettingsAdminController::class, 'handle_switch_mode' ) );
		add_action( 'admin_post_ft_save_stripe_plan_mappings', array( StripeSettingsAdminController::class, 'handle_save_plan_mappings' ) );

		// Secure document streaming route (nonce + per-document authorization in DocumentService).
		add_action( 'admin_post_ft_view_document', array( __CLASS__, 'handle_view_document' ) );
	}

	/**
	 * Stream a protected document to an authorized user.
	 */
	public static function handle_view_document(): void {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Unauthorized access. Authentication required.', 'fraud-tenant-core' ), 403 );
		}

		$user_id     = get_current_user_id();
		$document_id = isset( $_GET['document_id'] ) ? (int) $_GET['document_id'] : 0;
		$nonce       = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'ft_view_doc_' . $document_id ) ) {
			wp_die( esc_html__( 'Security verification failed for document request.', 'fraud-tenant-core' ), 403 );
		}

		DocumentService::stream_protected_document( $document_id, $user_id );
	}
}
