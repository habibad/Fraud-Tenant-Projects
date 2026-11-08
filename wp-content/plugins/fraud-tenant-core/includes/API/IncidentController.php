<?php
/**
 * REST API Controller for Property Owner Incident Reporting.
 *
 * Provides protected endpoints for authorized property owners to submit incident reports,
 * retrieve incident history, and inspect submitted report details.
 *
 * @package FraudTenant\API
 */

namespace FraudTenant\API;

use FraudTenant\Auth\AuthService;
use FraudTenant\Auth\Capabilities;
use FraudTenant\Services\IncidentService;
use FraudTenant\Utils\Constants;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IncidentController extends BaseController {

	/**
	 * Register routes for incident reports.
	 */
	public function register_routes(): void {
		// 1. Submit incident report (POST)
		register_rest_route(
			$this->namespace,
			'/owner/incident-reports',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_incident_report' ),
					'permission_callback' => array( $this, 'check_owner_submit_permission' ),
					'args'                => array(
						'property_id'         => array(
							'required'          => true,
							'type'              => 'integer',
							'validate_callback' => function ( $param ) {
								return is_numeric( $param ) && (int) $param > 0;
							},
						),
						'category'            => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_key',
						),
						'incident_date'       => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'amount_involved'     => array(
							'required'          => false,
							'default'           => 0.00,
						),
						'factual_description' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'owner_attestation'   => array(
							'required' => true,
						),
					),
				),
			)
		);

		// 2. List own incident reports (GET)
		register_rest_route(
			$this->namespace,
			'/owner/incident-reports',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_incident_reports' ),
					'permission_callback' => array( $this, 'check_owner_view_permission' ),
					'args'                => array(
						'limit'  => array(
							'required'          => false,
							'type'              => 'integer',
							'default'           => 20,
							'sanitize_callback' => 'absint',
						),
						'offset' => array(
							'required'          => false,
							'type'              => 'integer',
							'default'           => 0,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		// 3. Get single incident report details (GET)
		register_rest_route(
			$this->namespace,
			'/owner/incident-reports/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_single_incident_report' ),
					'permission_callback' => array( $this, 'check_owner_view_permission' ),
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
	 * Permission callback for submitting an incident report.
	 *
	 * Requires authenticated user with submit capabilities and approved account status.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return bool|WP_Error True if authorized, WP_Error otherwise.
	 */
	public function check_owner_submit_permission( WP_REST_Request $request ) {
		// Strict Security & Privacy Gate (BLK-08 Unresolved)
		// Evaluated prior to inspecting request payload or files.
		if ( ! \FraudTenant\Services\ComplianceService::is_data_gate_open() ) {
			return new WP_Error(
				'submission_locked_blk08',
				__( 'Incident submission is currently locked pending completion of sensitive PII encryption infrastructure (BLK-08). Non-production test mode is required for synthetic testing.', 'fraud-tenant-core' ),
				array( 'status' => 423 )
			);
		}

		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_not_logged_in',
				__( 'Authentication is required to submit incident reports.', 'fraud-tenant-core' ),
				array( 'status' => 401 )
			);
		}

		$user_id = get_current_user_id();

		if ( ! IncidentService::can_submit_incident( $user_id ) ) {
			if ( IncidentService::can_submit_incident( $user_id, false ) ) { // Only membership is missing.
				return new WP_Error(
					'rest_membership_required',
					__( 'Incident reporting requires an active Standard membership.', 'fraud-tenant-core' ),
					array( 'status' => 403 )
				);
			}
			return new WP_Error(
				'rest_forbidden_owner',
				__( 'You do not have permission to submit incident reports. An approved Property Owner account is required.', 'fraud-tenant-core' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Permission callback for viewing own incident reports.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return bool|WP_Error True if authorized, WP_Error otherwise.
	 */
	public function check_owner_view_permission( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_not_logged_in',
				__( 'Authentication is required to view incident reports.', 'fraud-tenant-core' ),
				array( 'status' => 401 )
			);
		}

		$user_id = get_current_user_id();

		if ( user_can( $user_id, 'manage_options' ) ||
			 user_can( $user_id, Capabilities::REVIEW_INCIDENT_REPORTS ) ) {
			return true;
		}

		if ( ! user_can( $user_id, Capabilities::VIEW_OWN_REPORTS ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to view incident reports.', 'fraud-tenant-core' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Handle POST /fraud-tenant/v1/owner/incident-reports.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function create_incident_report( WP_REST_Request $request ) {
		// Strict fail-closed verification before reading payload or files
		if ( ! \FraudTenant\Services\ComplianceService::is_data_gate_open() ) {
			return new WP_Error(
				'submission_locked_blk08',
				__( 'Incident submission is currently locked pending completion of sensitive PII encryption infrastructure (BLK-08). Non-production test mode is required for synthetic testing.', 'fraud-tenant-core' ),
				array( 'status' => 423 )
			);
		}

		$user_id = get_current_user_id();
		$params  = $request->get_params();

		// Handle file upload if sent via multipart form
		$files       = $request->get_file_params();
		$file_upload = isset( $files['incident_evidence'] ) ? $files['incident_evidence'] : null;

		$result = IncidentService::record_incident_report( $user_id, $params, $file_upload );

		if ( ! $result['success'] ) {
			$status_code = 400;
			if ( 'unauthorized' === ( $result['error'] ?? '' ) || 'plan_upgrade_required' === ( $result['error'] ?? '' ) ) {
				$status_code = 403;
			} elseif ( 'rate_limited' === ( $result['error'] ?? '' ) ) {
				$status_code = 429;
			} elseif ( 'submission_locked_blk08' === ( $result['error'] ?? '' ) ) {
				$status_code = 423; // Resource locked
			}

			return new WP_Error(
				$result['error'] ?? 'submission_failed',
				$result['message'] ?? __( 'Failed to submit incident report.', 'fraud-tenant-core' ),
				array( 'status' => $status_code )
			);
		}

		return new WP_REST_Response(
			array(
				'success'     => true,
				'incident_id' => $result['incident_id'],
				'reference'   => $result['reference'],
				'status'      => $result['status'],
				'disclosable' => $result['disclosable'],
				'message'     => $result['message'],
			),
			201
		);
	}

	/**
	 * Handle GET /fraud-tenant/v1/owner/incident-reports.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response Response object.
	 */
	public function get_incident_reports( WP_REST_Request $request ): WP_REST_Response {
		$user_id = get_current_user_id();
		$limit   = (int) $request->get_param( 'limit' ) ?: 20;
		$offset  = (int) $request->get_param( 'offset' ) ?: 0;

		$reports = IncidentService::get_owner_incident_reports(
			$user_id,
			array(
				'limit'  => $limit,
				'offset' => $offset,
			)
		);

		return new WP_REST_Response(
			array(
				'success' => true,
				'count'   => count( $reports ),
				'data'    => $reports,
			),
			200
		);
	}

	/**
	 * Handle GET /fraud-tenant/v1/owner/incident-reports/(?P<id>\d+).
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function get_single_incident_report( WP_REST_Request $request ) {
		$user_id   = get_current_user_id();
		$report_id = (int) $request->get_param( 'id' );

		$report = IncidentService::get_incident_report( $report_id, $user_id );

		if ( ! $report ) {
			return new WP_Error(
				'report_not_found',
				__( 'Incident report not found or access denied.', 'fraud-tenant-core' ),
				array( 'status' => 404 )
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $report,
			),
			200
		);
	}
}

