<?php
/**
 * REST API Controller for Report Visibility and Tenant Association.
 *
 * Provides endpoints for administrative association, disclosure approval/revocation,
 * and tenant-facing disclosed report queries.
 *
 * @package FraudTenant\API
 */

namespace FraudTenant\API;

use FraudTenant\Auth\Capabilities;
use FraudTenant\Services\IncidentService;
use FraudTenant\Services\ReportVisibilityService;
use FraudTenant\Utils\Constants;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ReportVisibilityRestController extends BaseController {

	/**
	 * Register routes for this controller.
	 */
	public function register_routes(): void {
		// 1. Administrative Visibility Queue (GET)
		register_rest_route(
			$this->namespace,
			'/admin/report-visibility',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_visibility_queue' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => array(
						'status'   => array(
							'required'          => false,
							'type'              => 'string',
							'default'           => 'all',
							'sanitize_callback' => 'sanitize_key',
						),
						'page'     => array(
							'required' => false,
							'type'     => 'integer',
							'default'  => 1,
						),
						'per_page' => array(
							'required' => false,
							'type'     => 'integer',
							'default'  => 20,
						),
						'search'   => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		// 2. Administrative Report Visibility Detail (GET)
		register_rest_route(
			$this->namespace,
			'/admin/report-visibility/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_report_detail' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'type'              => 'integer',
							'validate_callback' => function ( $param ) {
								return is_numeric( $param ) && (int) $param > 0;
							},
						),
					),
				),
			)
		);

		// 3. Associate Tenant (POST)
		register_rest_route(
			$this->namespace,
			'/admin/report-visibility/(?P<id>[\d]+)/associate',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'associate_tenant' ),
					'permission_callback' => array( $this, 'check_associate_permission' ),
					'args'                => array(
						'id'             => array(
							'required' => true,
							'type'     => 'integer',
						),
						'tenant_user_id' => array(
							'required' => true,
							'type'     => 'integer',
						),
						'reason'         => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'notes'          => array(
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
					),
				),
			)
		);

		// 4. Disassociate Tenant (POST)
		register_rest_route(
			$this->namespace,
			'/admin/report-visibility/(?P<id>[\d]+)/disassociate',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'disassociate_tenant' ),
					'permission_callback' => array( $this, 'check_associate_permission' ),
					'args'                => array(
						'id'             => array(
							'required' => true,
							'type'     => 'integer',
						),
						'association_id' => array(
							'required' => true,
							'type'     => 'integer',
						),
						'reason'         => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
					),
				),
			)
		);

		// 5. Approve Visibility (POST)
		register_rest_route(
			$this->namespace,
			'/admin/report-visibility/(?P<id>[\d]+)/approve',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'approve_visibility' ),
					'permission_callback' => array( $this, 'check_visibility_permission' ),
					'args'                => array(
						'id'             => array(
							'required' => true,
							'type'     => 'integer',
						),
						'association_id' => array(
							'required' => true,
							'type'     => 'integer',
						),
						'reason'         => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
					),
				),
			)
		);

		// 6. Revoke Visibility (POST)
		register_rest_route(
			$this->namespace,
			'/admin/report-visibility/(?P<id>[\d]+)/revoke',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'revoke_visibility' ),
					'permission_callback' => array( $this, 'check_visibility_permission' ),
					'args'                => array(
						'id'             => array(
							'required' => true,
							'type'     => 'integer',
						),
						'association_id' => array(
							'required' => true,
							'type'     => 'integer',
						),
						'reason'         => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
					),
				),
			)
		);

		// 7. Tenant: Get Disclosed Reports (GET)
		register_rest_route(
			$this->namespace,
			'/tenant/disclosed-reports',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_tenant_reports' ),
					'permission_callback' => array( $this, 'check_tenant_permission' ),
				),
			)
		);

		// 8. Tenant: Get Single Disclosed Report Detail (GET)
		register_rest_route(
			$this->namespace,
			'/tenant/disclosed-reports/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_tenant_report_detail' ),
					'permission_callback' => array( $this, 'check_tenant_permission' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'type'              => 'integer',
							'validate_callback' => function ( $param ) {
								return is_numeric( $param ) && (int) $param > 0;
							},
						),
					),
				),
			)
		);
	}

	/**
	 * Permission callback for admin visibility routes.
	 *
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public function check_admin_permission( WP_REST_Request $request ): bool|WP_Error {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_not_logged_in',
				__( 'You must be logged in to access this endpoint.', 'fraud-tenant-core' ),
				array( 'status' => 401 )
			);
		}

		if ( ! \FraudTenant\Services\ComplianceService::is_data_gate_open() ) {
			return new WP_Error(
				'visibility_locked_blk08',
				__( 'Report visibility management is locked in production mode pending sensitive PII encryption infrastructure (BLK-08).', 'fraud-tenant-core' ),
				array( 'status' => 423 )
			);
		}

		$user_id = get_current_user_id();
		if ( ! ReportVisibilityService::can_manage_visibility( $user_id ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to manage report visibility.', 'fraud-tenant-core' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Permission callback for tenant association routes.
	 *
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public function check_associate_permission( WP_REST_Request $request ): bool|WP_Error {
		$admin_check = $this->check_admin_permission( $request );
		if ( is_wp_error( $admin_check ) ) {
			return $admin_check;
		}

		$user_id = get_current_user_id();
		if ( ! ReportVisibilityService::can_associate_tenant( $user_id ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to associate reports with tenant accounts.', 'fraud-tenant-core' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Permission callback for report visibility disclosure routes.
	 *
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public function check_visibility_permission( WP_REST_Request $request ): bool|WP_Error {
		$admin_check = $this->check_admin_permission( $request );
		if ( is_wp_error( $admin_check ) ) {
			return $admin_check;
		}

		$user_id = get_current_user_id();
		if ( ! ReportVisibilityService::can_manage_report_visibility( $user_id ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to manage report visibility disclosure.', 'fraud-tenant-core' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Permission callback for tenant-facing routes.
	 *
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public function check_tenant_permission( WP_REST_Request $request ): bool|WP_Error {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_not_logged_in',
				__( 'You must be logged in to access this endpoint.', 'fraud-tenant-core' ),
				array( 'status' => 401 )
			);
		}

		$user = wp_get_current_user();
		if ( ! in_array( 'ft_tenant', (array) $user->roles, true ) && ! user_can( $user, Capabilities::ACCESS_TENANT_DASHBOARD ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access tenant records.', 'fraud-tenant-core' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * GET /admin/report-visibility
	 */
	public function get_visibility_queue( WP_REST_Request $request ): WP_REST_Response {
		$admin_id = get_current_user_id();
		$args     = array(
			'status'   => $request->get_param( 'status' ),
			'page'     => (int) $request->get_param( 'page' ),
			'per_page' => (int) $request->get_param( 'per_page' ),
			'search'   => $request->get_param( 'search' ),
		);

		$result = ReportVisibilityService::get_visibility_queue( $args, $admin_id );
		if ( empty( $result['success'] ) ) {
			return $this->error_response( $result['error'] ?? 'queue_error', $result['message'] ?? 'Error fetching queue.', 400 );
		}

		return $this->success_response( $result );
	}

	/**
	 * GET /admin/report-visibility/{id}
	 */
	public function get_report_detail( WP_REST_Request $request ): WP_REST_Response {
		$admin_id  = get_current_user_id();
		$report_id = (int) $request->get_param( 'id' );

		$result = ReportVisibilityService::get_report_visibility_detail( $report_id, $admin_id );
		if ( empty( $result['success'] ) ) {
			return $this->error_response( $result['error'] ?? 'not_found', $result['message'] ?? 'Report not found.', 404 );
		}

		return $this->success_response( $result );
	}

	/**
	 * POST /admin/report-visibility/{id}/associate
	 */
	public function associate_tenant( WP_REST_Request $request ): WP_REST_Response {
		$admin_id       = get_current_user_id();
		$report_id      = (int) $request->get_param( 'id' );
		$tenant_user_id = (int) $request->get_param( 'tenant_user_id' );
		$reason         = (string) $request->get_param( 'reason' );
		$notes          = (string) $request->get_param( 'notes' );

		$result = ReportVisibilityService::associate_tenant( $report_id, $tenant_user_id, $reason, $notes, $admin_id );
		if ( empty( $result['success'] ) ) {
			$status_code = ( 'already_associated' === ( $result['error'] ?? '' ) ) ? 409 : 400;
			return $this->error_response( $result['error'] ?? 'associate_error', $result['message'] ?? 'Could not associate tenant.', $status_code );
		}

		return $this->success_response( $result, 201 );
	}

	/**
	 * POST /admin/report-visibility/{id}/disassociate
	 */
	public function disassociate_tenant( WP_REST_Request $request ): WP_REST_Response {
		$admin_id       = get_current_user_id();
		$report_id      = (int) $request->get_param( 'id' );
		$association_id = (int) $request->get_param( 'association_id' );
		$reason         = (string) $request->get_param( 'reason' );

		$result = ReportVisibilityService::disassociate_tenant( $report_id, $association_id, $reason, $admin_id );
		if ( empty( $result['success'] ) ) {
			return $this->error_response( $result['error'] ?? 'disassociate_error', $result['message'] ?? 'Could not disassociate tenant.', 400 );
		}

		return $this->success_response( $result, 200 );
	}

	/**
	 * POST /admin/report-visibility/{id}/approve
	 */
	public function approve_visibility( WP_REST_Request $request ): WP_REST_Response {
		$admin_id       = get_current_user_id();
		$report_id      = (int) $request->get_param( 'id' );
		$association_id = (int) $request->get_param( 'association_id' );
		$reason         = (string) $request->get_param( 'reason' );

		$result = ReportVisibilityService::approve_visibility( $report_id, $association_id, $reason, $admin_id );
		if ( empty( $result['success'] ) ) {
			$status_code = ( 'report_not_approved' === ( $result['error'] ?? '' ) ) ? 409 : 400;
			return $this->error_response( $result['error'] ?? 'approve_error', $result['message'] ?? 'Could not approve visibility.', $status_code );
		}

		return $this->success_response( $result, 200 );
	}

	/**
	 * POST /admin/report-visibility/{id}/revoke
	 */
	public function revoke_visibility( WP_REST_Request $request ): WP_REST_Response {
		$admin_id       = get_current_user_id();
		$report_id      = (int) $request->get_param( 'id' );
		$association_id = (int) $request->get_param( 'association_id' );
		$reason         = (string) $request->get_param( 'reason' );

		$result = ReportVisibilityService::revoke_visibility( $report_id, $association_id, $reason, $admin_id );
		if ( empty( $result['success'] ) ) {
			return $this->error_response( $result['error'] ?? 'revoke_error', $result['message'] ?? 'Could not revoke visibility.', 400 );
		}

		return $this->success_response( $result, 200 );
	}

	/**
	 * GET /tenant/disclosed-reports
	 */
	public function get_tenant_reports( WP_REST_Request $request ): WP_REST_Response {
		$tenant_user_id = get_current_user_id();
		$reports        = ReportVisibilityService::get_tenant_disclosed_reports( $tenant_user_id );

		return $this->success_response(
			array(
				'reports' => $reports,
				'count'   => count( $reports ),
			)
		);
	}

	/**
	 * GET /tenant/disclosed-reports/{id}
	 */
	public function get_tenant_report_detail( WP_REST_Request $request ): WP_REST_Response {
		$tenant_user_id = get_current_user_id();
		$report_id      = (int) $request->get_param( 'id' );

		$result = ReportVisibilityService::get_tenant_report_detail( $report_id, $tenant_user_id );
		if ( empty( $result['success'] ) ) {
			return $this->error_response( 'not_found', __( 'Report record not found or inaccessible.', 'fraud-tenant-core' ), 404 );
		}

		return $this->success_response( $result );
	}
}
