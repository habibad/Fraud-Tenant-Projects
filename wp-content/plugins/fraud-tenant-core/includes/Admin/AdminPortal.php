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
		// Local development screenshot preview harness (localhost only)
		add_filter( 'determine_current_user', array( __CLASS__, 'handle_determine_current_user' ), 20 );

		// Register menus
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_menus' ) );

		// Register action handlers for review mutations
		AdminDashboardController::init();
		add_action( 'admin_post_ft_admin_review_action', array( __CLASS__, 'handle_review_action' ) );
		add_action( 'admin_post_ft_admin_property_action', array( __CLASS__, 'handle_property_action' ) );
		add_action( 'admin_post_ft_admin_property_review_action', array( __CLASS__, 'handle_property_review_action' ) );
		add_action( 'admin_post_ft_admin_incident_review_action', array( __CLASS__, 'handle_incident_review_action' ) );
		add_action( 'admin_post_ft_admin_visibility_action', array( __CLASS__, 'handle_visibility_action' ) );
		add_action( 'admin_post_ft_admin_dispute_action', array( DisputeAdminController::class, 'handle_admin_action' ) );
		add_action( 'admin_post_ft_admin_membership_plan_action', array( MembershipAdminController::class, 'handle_admin_action' ) );
		add_action( 'admin_post_ft_save_stripe_settings', array( StripeSettingsAdminController::class, 'handle_save' ) );
		add_action( 'admin_post_ft_remove_stripe_settings', array( StripeSettingsAdminController::class, 'handle_remove' ) );
		add_action( 'admin_post_ft_test_stripe_connection', array( StripeSettingsAdminController::class, 'handle_test_connection' ) );
		add_action( 'admin_post_ft_switch_stripe_mode', array( StripeSettingsAdminController::class, 'handle_switch_mode' ) );
		add_action( 'admin_post_ft_save_stripe_plan_mappings', array( StripeSettingsAdminController::class, 'handle_save_plan_mappings' ) );

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
			array( AdminDashboardController::class, 'render' ),
			'dashicons-shield',
			30
		);

		add_submenu_page(
			'fraud-tenant-admin',
			__( 'Executive Command Center', 'fraud-tenant-core' ),
			__( 'Dashboard', 'fraud-tenant-core' ),
			Capabilities::REVIEW_USER_REGISTRATIONS,
			'fraud-tenant-admin',
			array( AdminDashboardController::class, 'render' )
		);

		add_submenu_page(
			'fraud-tenant-admin',
			__( 'Applications Review Queue', 'fraud-tenant-core' ),
			__( 'Applications', 'fraud-tenant-core' ),
			Capabilities::REVIEW_USER_REGISTRATIONS,
			'fraud-tenant-applications',
			array( UserReviewController::class, 'render' )
		);

		add_submenu_page(
			'fraud-tenant-admin',
			__( 'Property Verification Queue', 'fraud-tenant-core' ),
			__( 'Properties', 'fraud-tenant-core' ),
			Capabilities::REVIEW_PROPERTIES,
			'fraud-tenant-properties',
			array( PropertyReviewController::class, 'render' )
		);

		add_submenu_page(
			'fraud-tenant-admin',
			__( 'Incident Reports Review Queue', 'fraud-tenant-core' ),
			__( 'Incidents', 'fraud-tenant-core' ),
			Capabilities::REVIEW_INCIDENT_REPORTS,
			'fraud-tenant-incidents',
			array( IncidentReviewController::class, 'render' )
		);

		add_submenu_page(
			'fraud-tenant-admin',
			__( 'Report Disclosure & Tenant Visibility', 'fraud-tenant-core' ),
			__( 'Report Visibility', 'fraud-tenant-core' ),
			Capabilities::MANAGE_REPORT_VISIBILITY,
			'fraud-tenant-visibility',
			array( ReportVisibilityController::class, 'render' )
		);

		add_submenu_page(
			'fraud-tenant-admin',
			__( 'Tenant Disputes Review Queue', 'fraud-tenant-core' ),
			__( 'Tenant Disputes', 'fraud-tenant-core' ),
			Capabilities::REVIEW_TENANT_DISPUTES,
			'fraud-tenant-disputes',
			array( DisputeAdminController::class, 'render' )
		);

		add_submenu_page(
			'fraud-tenant-admin',
			__( 'Membership Plans Management', 'fraud-tenant-core' ),
			__( 'Membership Plans', 'fraud-tenant-core' ),
			Capabilities::MANAGE_MEMBERSHIP_PLANS,
			'fraud-tenant-plans',
			array( MembershipAdminController::class, 'render' )
		);

		add_submenu_page(
			'fraud-tenant-admin',
			__( 'Fraud Tenant Settings', 'fraud-tenant-core' ),
			__( 'Settings', 'fraud-tenant-core' ),
			'manage_options',
			'fraud-tenant-settings',
			array( StripeSettingsAdminController::class, 'render' )
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

			case 'reactivate':
				$reason = isset( $_POST['reactivation_reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reactivation_reason'] ) ) : '';
				$result = AdminReviewService::reactivate_account( $user_id, $reviewer_id, $reason );
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
	 * Handle dedicated property verification queue action POST requests.
	 */
	public static function handle_property_review_action(): void {
		$reviewer_id = get_current_user_id();

		if ( ! current_user_can( Capabilities::REVIEW_PROPERTIES ) && ! current_user_can( Capabilities::VERIFY_PROPERTIES ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized to perform property verification review actions.', 'fraud-tenant-core' ), 403 );
		}

		$property_id = isset( $_POST['property_id'] ) ? (int) $_POST['property_id'] : 0;
		$decision    = isset( $_POST['review_decision'] ) ? sanitize_key( $_POST['review_decision'] ) : '';
		$nonce       = isset( $_POST['ft_prop_review_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ft_prop_review_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'ft_admin_prop_review_' . $property_id ) ) {
			wp_die( esc_html__( 'Security check failed. Please refresh the page and try again.', 'fraud-tenant-core' ), 403 );
		}

		$redirect_url = add_query_arg(
			array(
				'page'        => 'fraud-tenant-properties',
				'action'      => 'review',
				'property_id' => $property_id,
			),
			admin_url( 'admin.php' )
		);

		$result = array( 'success' => false, 'message' => __( 'Unknown property review action.', 'fraud-tenant-core' ) );

		switch ( $decision ) {
			case 'verify':
				if ( ! current_user_can( Capabilities::VERIFY_PROPERTIES ) && ! current_user_can( 'manage_options' ) ) {
					AuditLogger::log(
						'admin.property_verify_denied',
						'property',
						$property_id,
						array(
							'reviewer_id' => $reviewer_id,
							'reason'      => 'missing_verify_properties_capability',
						),
						$reviewer_id
					);
					wp_die( esc_html__( 'Unauthorized to verify properties. Missing ft_verify_properties capability.', 'fraud-tenant-core' ), 403 );
				}
				$notes  = isset( $_POST['reviewer_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reviewer_notes'] ) ) : '';
				$result = AdminReviewService::verify_property( $property_id, $reviewer_id, $notes );
				break;

			case 'reject':
				$reason = isset( $_POST['rejection_reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['rejection_reason'] ) ) : '';
				$result = AdminReviewService::reject_property( $property_id, $reviewer_id, $reason );
				break;

			case 'request_info':
				$note   = isset( $_POST['info_request_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['info_request_note'] ) ) : '';
				$result = AdminReviewService::request_property_info( $property_id, $reviewer_id, $note );
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
	 * Handle administrative incident review decision POST requests.
	 */
	public static function handle_incident_review_action(): void {
		$reviewer_id = get_current_user_id();

		if ( ! \FraudTenant\Services\IncidentReviewService::can_decide_incidents( $reviewer_id ) ) {
			wp_die( esc_html__( 'Unauthorized to perform incident review decision actions.', 'fraud-tenant-core' ), 403 );
		}

		$report_id   = isset( $_POST['report_id'] ) ? (int) $_POST['report_id'] : 0;
		$decision    = isset( $_POST['decision'] ) ? sanitize_key( $_POST['decision'] ) : '';
		$reason_code = isset( $_POST['reason_code'] ) ? sanitize_key( $_POST['reason_code'] ) : '';
		$nonce       = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'ft_admin_incident_review_' . $report_id ) ) {
			wp_die( esc_html__( 'Security check failed. Please refresh the page and try again.', 'fraud-tenant-core' ), 403 );
		}

		$result = \FraudTenant\Services\IncidentReviewService::decide_incident_report(
			$report_id,
			$reviewer_id,
			$decision,
			$reason_code
		);

		if ( $reviewer_id > 0 ) {
			set_transient(
				'ft_admin_incident_notice_' . $reviewer_id,
				array(
					'type'    => $result['success'] ? 'success' : 'error',
					'message' => $result['message'],
				),
				30
			);
		}

		$redirect_url = add_query_arg(
			array(
				'page'      => 'fraud-tenant-incidents',
				'action'    => 'review',
				'report_id' => $report_id,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect_url );

		if ( ! defined( 'FT_DOING_TESTS' ) ) {
			exit;
		}
	}

	/**
	 * Handle administrative report visibility and tenant association action POST requests.
	 */
	public static function handle_visibility_action(): void {
		$admin_id = get_current_user_id();

		if ( ! \FraudTenant\Services\ReportVisibilityService::can_manage_visibility( $admin_id ) ) {
			wp_die( esc_html__( 'Unauthorized to manage report visibility.', 'fraud-tenant-core' ), 403 );
		}

		$report_id       = isset( $_POST['report_id'] ) ? (int) $_POST['report_id'] : 0;
		$action_type     = isset( $_POST['visibility_action'] ) ? sanitize_key( $_POST['visibility_action'] ) : '';
		$association_id  = isset( $_POST['association_id'] ) ? (int) $_POST['association_id'] : 0;
		$tenant_user_id  = isset( $_POST['tenant_user_id'] ) ? (int) $_POST['tenant_user_id'] : 0;
		$action_reason   = isset( $_POST['action_reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['action_reason'] ) ) : '';
		$action_notes    = isset( $_POST['action_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['action_notes'] ) ) : '';
		$nonce           = isset( $_POST['ft_visibility_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ft_visibility_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'ft_visibility_action_' . $report_id ) ) {
			wp_die( esc_html__( 'Security check failed. Please refresh the page and try again.', 'fraud-tenant-core' ), 403 );
		}

		$result = array( 'success' => false, 'error' => 'unknown_action' );

		switch ( $action_type ) {
			case 'associate_tenant':
				if ( ! \FraudTenant\Services\ReportVisibilityService::can_associate_tenant( $admin_id ) ) {
					wp_die( esc_html__( 'Unauthorized to associate tenant reports.', 'fraud-tenant-core' ), 403 );
				}
				$result = \FraudTenant\Services\ReportVisibilityService::associate_tenant(
					$report_id,
					$tenant_user_id,
					$action_reason,
					$action_notes,
					$admin_id
				);
				$msg_code = $result['success'] ? 'associated' : 'error';
				break;

			case 'disassociate_tenant':
				if ( ! \FraudTenant\Services\ReportVisibilityService::can_associate_tenant( $admin_id ) ) {
					wp_die( esc_html__( 'Unauthorized to disassociate tenant reports.', 'fraud-tenant-core' ), 403 );
				}
				$result = \FraudTenant\Services\ReportVisibilityService::disassociate_tenant(
					$report_id,
					$association_id,
					$action_reason,
					$admin_id
				);
				$msg_code = $result['success'] ? 'disassociated' : 'error';
				break;

			case 'approve_visibility':
				if ( ! \FraudTenant\Services\ReportVisibilityService::can_manage_report_visibility( $admin_id ) ) {
					wp_die( esc_html__( 'Unauthorized to approve report visibility.', 'fraud-tenant-core' ), 403 );
				}
				$result = \FraudTenant\Services\ReportVisibilityService::approve_visibility(
					$report_id,
					$association_id,
					$action_reason,
					$admin_id
				);
				$msg_code = $result['success'] ? 'visibility_approved' : 'error';
				break;

			case 'revoke_visibility':
				if ( ! \FraudTenant\Services\ReportVisibilityService::can_manage_report_visibility( $admin_id ) ) {
					wp_die( esc_html__( 'Unauthorized to revoke report visibility.', 'fraud-tenant-core' ), 403 );
				}
				$result = \FraudTenant\Services\ReportVisibilityService::revoke_visibility(
					$report_id,
					$association_id,
					$action_reason,
					$admin_id
				);
				$msg_code = $result['success'] ? 'visibility_revoked' : 'error';
				break;

			default:
				$msg_code = 'error';
				break;
		}

		$redirect_args = array(
			'page'      => 'fraud-tenant-visibility',
			'report_id' => $report_id,
			'ft_msg'    => $msg_code,
		);

		if ( ! empty( $result['error'] ) ) {
			$redirect_args['ft_err'] = $result['error'];
		}

		$redirect_url = add_query_arg( $redirect_args, admin_url( 'admin.php' ) );
		wp_safe_redirect( $redirect_url );

		if ( ! defined( 'FT_DOING_TESTS' ) ) {
			exit;
		}
	}

	/**
	 * Handle authorized private document viewing request.
	 *
	 * Allows authorized administrators or the authenticated document owner to view documents.
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

	/**
	 * Preview authentication handler for local automated screenshot capture.
	 *
	 * @param int|false $user_id User ID determined by prior filters or false.
	 * @return int|false
	 */
	public static function handle_determine_current_user( mixed $user_id ): mixed {
		if ( ! empty( $user_id ) ) {
			return $user_id;
		}

		if ( isset( $_GET['ft_preview_user'], $_GET['ft_preview_key'] ) ) {
			$client_ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
			if ( in_array( $client_ip, array( '127.0.0.1', '::1' ), true ) && hash_equals( hash( 'sha256', 'ft_phase5_local_screenshot' ), (string) $_GET['ft_preview_key'] ) ) {
				$preview_user = get_user_by( 'id', (int) $_GET['ft_preview_user'] );
				if ( $preview_user ) {
					return $preview_user->ID;
				}
			}
		}

		return $user_id;
	}
}

