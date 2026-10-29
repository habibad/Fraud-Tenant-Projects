<?php
/**
 * Main Administrative Portal Orchestrator.
 *
 * Registers admin menus, routes, and handles administrative action POST requests.
 *
 * @package FraudTenant\Admin
 */

namespace FraudTenant\Admin;

use FraudTenant\Auth\Capabilities;
use FraudTenant\Services\DocumentService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminPortal {

	/**
	 * Initialize the Admin Portal.
	 */
	public static function init(): void {
		// Register menus
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_menus' ) );

		// Register action handlers for review mutations
		add_action( 'admin_post_ft_admin_review_action', array( __CLASS__, 'handle_review_action' ) );
		add_action( 'admin_post_ft_admin_property_action', array( __CLASS__, 'handle_property_action' ) );

		// Register secure document streaming route
		add_action( 'admin_post_ft_view_document', array( __CLASS__, 'handle_view_document' ) );
	}

	/**
	 * Register Fraud Tenant admin menus.
	 */
	public static function register_admin_menus(): void {
		add_menu_page(
			__( 'Fraud Tenant', 'fraud-tenant-core' ),
			__( 'Fraud Tenant', 'fraud-tenant-core' ),
			Capabilities::REVIEW_USER_REGISTRATIONS,
			'fraud-tenant-admin',
			array( UserReviewController::class, 'render' ),
			'dashicons-shield',
			30
		);

		add_submenu_page(
			'fraud-tenant-admin',
			__( 'Applications Review Queue', 'fraud-tenant-core' ),
			__( 'Applications', 'fraud-tenant-core' ),
			Capabilities::REVIEW_USER_REGISTRATIONS,
			'fraud-tenant-admin',
			array( UserReviewController::class, 'render' )
		);
	}

	/**
	 * Handle applicant review action POST requests.
	 */
	public static function handle_review_action(): void {
		$reviewer_id = get_current_user_id();

		if ( ! current_user_can( Capabilities::REVIEW_USER_REGISTRATIONS ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized to perform administrative review actions.', 'fraud-tenant-core' ), 403 );
		}

		$user_id  = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
		$decision = isset( $_POST['review_decision'] ) ? sanitize_key( $_POST['review_decision'] ) : '';
		$nonce    = isset( $_POST['ft_review_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ft_review_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'ft_admin_review_' . $user_id ) ) {
			wp_die( esc_html__( 'Security check failed. Please refresh the page and try again.', 'fraud-tenant-core' ), 403 );
		}

		$redirect_url = add_query_arg(
			array(
				'page'    => 'fraud-tenant-admin',
				'action'  => 'review',
				'user_id' => $user_id,
			),
			admin_url( 'admin.php' )
		);

		$result = array( 'success' => false, 'message' => __( 'Unknown review action.', 'fraud-tenant-core' ) );

		switch ( $decision ) {
			case 'approve':
				$result = AdminReviewService::approve_application( $user_id, $reviewer_id );
				break;

			case 'reject':
				$reason = isset( $_POST['rejection_reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['rejection_reason'] ) ) : '';
				$result = AdminReviewService::reject_application( $user_id, $reviewer_id, $reason );
				break;

			case 'request_info':
				$note   = isset( $_POST['info_request_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['info_request_note'] ) ) : '';
				$result = AdminReviewService::request_additional_info( $user_id, $reviewer_id, $note );
				break;

			case 'suspend':
				$reason = isset( $_POST['suspension_reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['suspension_reason'] ) ) : '';
				$result = AdminReviewService::suspend_account( $user_id, $reviewer_id, $reason );
				break;
		}

		self::set_flash_notice(
			$result['success'] ? 'success' : 'error',
			$result['message']
		);

		wp_safe_redirect( $redirect_url );

		if ( ! defined( 'FT_DOING_TESTS' ) ) {
			exit;
		}
	}

	/**
	 * Handle separate property verification POST requests.
	 */
	public static function handle_property_action(): void {
		$reviewer_id = get_current_user_id();

		if ( ! current_user_can( Capabilities::REVIEW_USER_REGISTRATIONS ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized to perform property verification actions.', 'fraud-tenant-core' ), 403 );
		}

		$property_id     = isset( $_POST['property_id'] ) ? (int) $_POST['property_id'] : 0;
		$user_id         = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
		$property_action = isset( $_POST['property_action'] ) ? sanitize_key( $_POST['property_action'] ) : '';
		$nonce           = isset( $_POST['ft_prop_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ft_prop_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'ft_admin_prop_verify_' . $property_id ) ) {
			wp_die( esc_html__( 'Security check failed. Please refresh the page and try again.', 'fraud-tenant-core' ), 403 );
		}

		$redirect_url = add_query_arg(
			array(
				'page'    => 'fraud-tenant-admin',
				'action'  => 'review',
				'user_id' => $user_id,
			),
			admin_url( 'admin.php' )
		);

		if ( 'verify' === $property_action ) {
			$result = AdminReviewService::verify_property( $property_id, $reviewer_id );
		} elseif ( 'reject' === $property_action ) {
			$result = AdminReviewService::reject_property( $property_id, $reviewer_id );
		} else {
			$result = array( 'success' => false, 'message' => __( 'Invalid property action.', 'fraud-tenant-core' ) );
		}

		self::set_flash_notice(
			$result['success'] ? 'success' : 'error',
			$result['message']
		);

		wp_safe_redirect( $redirect_url );

		if ( ! defined( 'FT_DOING_TESTS' ) ) {
			exit;
		}
	}

	/**
	 * Handle authorized private document viewing request.
	 */
	public static function handle_view_document(): void {
		$reviewer_id = get_current_user_id();

		if ( ! current_user_can( Capabilities::REVIEW_USER_REGISTRATIONS ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized to view protected documents.', 'fraud-tenant-core' ), 403 );
		}

		$document_id = isset( $_GET['document_id'] ) ? (int) $_GET['document_id'] : 0;
		$nonce       = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'ft_view_doc_' . $document_id ) ) {
			wp_die( esc_html__( 'Security verification failed for document request.', 'fraud-tenant-core' ), 403 );
		}

		DocumentService::stream_protected_document( $document_id, $reviewer_id );
	}

	/**
	 * Set flash notice for the current admin user.
	 *
	 * @param string $type    'success' or 'error'.
	 * @param string $message Notice message.
	 */
	protected static function set_flash_notice( string $type, string $message ): void {
		$user_id = get_current_user_id();
		if ( $user_id > 0 ) {
			set_transient(
				'ft_admin_notice_' . $user_id,
				array(
					'type'    => $type,
					'message' => $message,
				),
				30
			);
		}
	}
}

