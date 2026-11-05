<?php
/**
 * REST API Controller for Tenant Disputes.
 *
 * Provides endpoints for tenant dispute submission, dispute inspection,
 * administrative dispute review queue, and dispute determination.
 *
 * @package FraudTenant\API
 */

namespace FraudTenant\API;

use FraudTenant\Auth\Capabilities;
use FraudTenant\Services\DisputeService;
use FraudTenant\Services\IncidentService;
use FraudTenant\Utils\Constants;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DisputeRestController extends BaseController {

	/**
	 * Register routes for this controller.
	 */
	public function register_routes(): void {
		// 1. Tenant Submit Dispute (POST)
		register_rest_route(
			$this->namespace,
			'/tenant/disputes',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'submit_dispute' ),
					'permission_callback' => array( $this, 'check_tenant_permission' ),
					'args'                => array(
						'report_id'      => array(
							'required'          => true,
							'type'              => 'integer',
							'validate_callback' => function ( $param ) {
								return is_numeric( $param ) && (int) $param > 0;
							},
						),
						'dispute_reason' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_key',
						),
						'statement'      => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_tenant_disputes' ),
					'permission_callback' => array( $this, 'check_tenant_permission' ),
				),
			)
		);

		// 2. Tenant Dispute Detail (GET)
		register_rest_route(
			$this->namespace,
			'/tenant/disputes/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_tenant_dispute_detail' ),
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

		// 3. Admin Disputes Queue (GET)
		register_rest_route(
			$this->namespace,
			'/admin/disputes',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_admin_disputes_queue' ),
					'permission_callback' => array( $this, 'check_admin_review_permission' ),
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

		// 4. Admin Dispute Detail (GET)
		register_rest_route(
			$this->namespace,
			'/admin/disputes/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_admin_dispute_detail' ),
					'permission_callback' => array( $this, 'check_admin_review_permission' ),
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

		// 5. Admin Mark Under Review (POST)
		register_rest_route(
			$this->namespace,
			'/admin/disputes/(?P<id>[\d]+)/review',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'review_dispute' ),
					'permission_callback' => array( $this, 'check_admin_review_permission' ),
					'args'                => array(
						'id'    => array(
							'required'          => true,
							'type'              => 'integer',
							'validate_callback' => function ( $param ) {
								return is_numeric( $param ) && (int) $param > 0;
							},
						),
						'notes' => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
					),
				),
			)
		);

		// 6. Admin Resolve/Reject Dispute (POST)
		register_rest_route(
			$this->namespace,
			'/admin/disputes/(?P<id>[\d]+)/resolve',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'resolve_dispute' ),
					'permission_callback' => array( $this, 'check_admin_resolve_permission' ),
					'args'                => array(
						'id'                => array(
							'required'          => true,
							'type'              => 'integer',
							'validate_callback' => function ( $param ) {
								return is_numeric( $param ) && (int) $param > 0;
							},
						),
						'resolution_action' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_key',
						),
						'resolution_notes'  => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
					),
				),
			)
		);
	}

	/**
	 * Check permission for tenant dispute operations.
	 */
	public function check_tenant_permission( WP_REST_Request $request ): bool|WP_Error {
		// Strict Security & Privacy Gate (BLK-08 Unresolved)
		if ( ! \FraudTenant\Services\ComplianceService::is_data_gate_open() ) {
			return new WP_Error(
				'dispute_locked_blk08',
				__( 'Tenant dispute endpoints are locked in production mode pending sensitive PII encryption infrastructure (BLK-08).', 'fraud-tenant-core' ),
				array( 'status' => 423 )
			);
		}

		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return new WP_Error(
				'rest_not_logged_in',
				__( 'You must be logged in to access tenant disputes.', 'fraud-tenant-core' ),
				array( 'status' => 401 )
			);
		}

		if ( ! user_can( $user_id, Capabilities::SUBMIT_TENANT_DISPUTE ) &&
		     ! user_can( $user_id, Capabilities::VIEW_OWN_DISPUTES ) &&
		     ! user_can( $user_id, 'ft_tenant' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to perform tenant dispute actions.', 'fraud-tenant-core' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Check permission for admin dispute review operations.
	 */
	public function check_admin_review_permission( WP_REST_Request $request ): bool|WP_Error {
		// Strict Security & Privacy Gate (BLK-08 Unresolved)
		if ( ! \FraudTenant\Services\ComplianceService::is_data_gate_open() ) {
			return new WP_Error(
				'dispute_review_locked_blk08',
				__( 'Dispute review endpoints are locked in production mode pending sensitive PII encryption infrastructure (BLK-08).', 'fraud-tenant-core' ),
				array( 'status' => 423 )
			);
		}

		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return new WP_Error(
				'rest_not_logged_in',
				__( 'Authentication required.', 'fraud-tenant-core' ),
				array( 'status' => 401 )
			);
		}

		if ( ! DisputeService::can_review_disputes( $user_id ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to review tenant disputes.', 'fraud-tenant-core' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Check permission for admin dispute resolve operations.
	 */
	public function check_admin_resolve_permission( WP_REST_Request $request ): bool|WP_Error {
		// Strict Security & Privacy Gate (BLK-08 Unresolved)
		if ( ! \FraudTenant\Services\ComplianceService::is_data_gate_open() ) {
			return new WP_Error(
				'dispute_review_locked_blk08',
				__( 'Dispute resolution endpoints are locked in production mode pending sensitive PII encryption infrastructure (BLK-08).', 'fraud-tenant-core' ),
				array( 'status' => 423 )
			);
		}

		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return new WP_Error(
				'rest_not_logged_in',
				__( 'Authentication required.', 'fraud-tenant-core' ),
				array( 'status' => 401 )
			);
		}

		if ( ! DisputeService::can_resolve_disputes( $user_id ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to resolve tenant disputes.', 'fraud-tenant-core' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Handle tenant dispute submission.
	 */
	public function submit_dispute( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id   = get_current_user_id();
		$report_id = (int) $request->get_param( 'report_id' );
		$reason    = (string) $request->get_param( 'dispute_reason' );
		$statement = (string) $request->get_param( 'statement' );

		$result = DisputeService::submit_dispute( $report_id, $user_id, $reason, $statement, null );

		if ( ! $result['success'] ) {
			$status_code = 400;
			if ( in_array( $result['error'] ?? '', array( 'unauthorized_role', 'not_associated_or_not_visible' ), true ) ) {
				$status_code = 403;
			} elseif ( 'submission_locked_blk08' === ( $result['error'] ?? '' ) ) {
				$status_code = 423;
			}

			return new WP_Error(
				$result['error'] ?? 'dispute_error',
				$result['message'],
				array( 'status' => $status_code )
			);
		}

		return $this->success_response( $result, 201 );
	}

	/**
	 * Handle tenant list disputes request.
	 */
	public function get_tenant_disputes( WP_REST_Request $request ): WP_REST_Response {
		$user_id  = get_current_user_id();
		$disputes = DisputeService::get_tenant_disputes( $user_id );

		return $this->success_response(
			array(
				'disputes' => $disputes,
				'count'    => count( $disputes ),
			)
		);
	}

	/**
	 * Handle tenant dispute detail request.
	 */
	public function get_tenant_dispute_detail( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id    = get_current_user_id();
		$dispute_id = (int) $request->get_param( 'id' );

		$detail = DisputeService::get_dispute_detail( $dispute_id, $user_id );

		if ( ! $detail ) {
			return new WP_Error(
				'dispute_not_found',
				__( 'Dispute record not found or inaccessible.', 'fraud-tenant-core' ),
				array( 'status' => 404 )
			);
		}

		return $this->success_response( $detail );
	}

	/**
	 * Handle admin disputes queue request.
	 */
	public function get_admin_disputes_queue( WP_REST_Request $request ): WP_REST_Response {
		$admin_id = get_current_user_id();
		$args     = array(
			'status'   => $request->get_param( 'status' ),
			'search'   => $request->get_param( 'search' ),
			'page'     => (int) $request->get_param( 'page' ),
			'per_page' => (int) $request->get_param( 'per_page' ),
		);

		$queue  = DisputeService::get_admin_disputes_queue( $args, $admin_id );
		$counts = DisputeService::get_queue_status_counts();

		return $this->success_response(
			array(
				'items'  => $queue['items'],
				'total'  => $queue['total'],
				'pages'  => $queue['pages'],
				'page'   => $args['page'],
				'counts' => $counts,
			)
		);
	}

	/**
	 * Handle admin dispute detail request.
	 */
	public function get_admin_dispute_detail( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$admin_id   = get_current_user_id();
		$dispute_id = (int) $request->get_param( 'id' );

		$detail = DisputeService::get_dispute_detail( $dispute_id, $admin_id );

		if ( ! $detail ) {
			return new WP_Error(
				'dispute_not_found',
				__( 'Dispute record not found or inaccessible.', 'fraud-tenant-core' ),
				array( 'status' => 404 )
			);
		}

		return $this->success_response( $detail );
	}

	/**
	 * Handle admin transition dispute to under review.
	 */
	public function review_dispute( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$admin_id   = get_current_user_id();
		$dispute_id = (int) $request->get_param( 'id' );
		$notes      = (string) $request->get_param( 'notes' );

		$result = DisputeService::review_dispute( $dispute_id, $admin_id, $notes );

		if ( ! $result['success'] ) {
			$status_code = 400;
			if ( 'unauthorized' === ( $result['error'] ?? '' ) ) {
				$status_code = 403;
			} elseif ( 'not_found' === ( $result['error'] ?? '' ) ) {
				$status_code = 404;
			}

			return new WP_Error(
				$result['error'] ?? 'review_error',
				$result['message'],
				array( 'status' => $status_code )
			);
		}

		return $this->success_response( $result );
	}

	/**
	 * Handle admin resolve dispute.
	 */
	public function resolve_dispute( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$admin_id   = get_current_user_id();
		$dispute_id = (int) $request->get_param( 'id' );
		$action     = (string) $request->get_param( 'resolution_action' );
		$notes      = (string) $request->get_param( 'resolution_notes' );

		$result = DisputeService::resolve_dispute( $dispute_id, $admin_id, $action, $notes );

		if ( ! $result['success'] ) {
			$status_code = 400;
			if ( 'unauthorized' === ( $result['error'] ?? '' ) ) {
				$status_code = 403;
			} elseif ( 'not_found' === ( $result['error'] ?? '' ) ) {
				$status_code = 404;
			}

			return new WP_Error(
				$result['error'] ?? 'resolve_error',
				$result['message'],
				array( 'status' => $status_code )
			);
		}

		return $this->success_response( $result );
	}
}

