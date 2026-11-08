<?php
/**
 * REST API Controller for Administrative Incident Review Workflow.
 *
 * Provides protected endpoints for authorized administrative reviewers to inspect
 * submitted incident reports, view review queues, review evidence, execute moderation
 * decisions (approve, reject, request clarification), and inspect audit history.
 *
 * @package FraudTenant\API
 */

namespace FraudTenant\API;

use FraudTenant\Auth\Capabilities;
use FraudTenant\Services\IncidentReviewService;
use FraudTenant\Services\IncidentService;
use FraudTenant\Utils\Constants;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IncidentReviewRestController extends BaseController {

	/**
	 * Register administrative incident review routes.
	 */
	public function register_routes(): void {
		// 1. Administrative review queue (GET)
		register_rest_route(
			$this->namespace,
			'/admin/incident-reports',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_review_queue' ),
					'permission_callback' => array( $this, 'check_review_permission' ),
					'args'                => array(
						'status'   => array(
							'required'          => false,
							'type'              => 'string',
							'default'           => 'all',
							'sanitize_callback' => 'sanitize_key',
						),
						'page'     => array(
							'required'          => false,
							'type'              => 'integer',
							'default'           => 1,
						),
						'per_page' => array(
							'required'          => false,
							'type'              => 'integer',
							'default'           => 20,
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

		// 2. Single incident report detail for review (GET)
		register_rest_route(
			$this->namespace,
			'/admin/incident-reports/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_incident_detail' ),
					'permission_callback' => array( $this, 'check_review_permission' ),
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

		// 3. Attached evidence metadata for an incident (GET)
		register_rest_route(
			$this->namespace,
			'/admin/incident-reports/(?P<id>[\d]+)/evidence',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_incident_evidence' ),
					'permission_callback' => array( $this, 'check_review_permission' ),
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

		// 4. Submit administrative review decision (POST)
		register_rest_route(
			$this->namespace,
			'/admin/incident-reports/(?P<id>[\d]+)/review',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'submit_review_decision' ),
					'permission_callback' => array( $this, 'check_decision_permission' ),
					'args'                => array(
						'id'          => array(
							'required'          => true,
							'type'              => 'integer',
							'validate_callback' => function ( $param ) {
								return is_numeric( $param ) && (int) $param > 0;
							},
						),
						'decision'    => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_key',
						),
						'reason_code' => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
			)
		);

		// 5. Review history trail for an incident (GET)
		register_rest_route(
			$this->namespace,
			'/admin/incident-reports/(?P<id>[\d]+)/history',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_incident_history' ),
					'permission_callback' => array( $this, 'check_review_permission' ),
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
	 * Permission callback for read-only administrative inspection routes.
	 *
	 * Enforces:
	 * - Authentication required (401).
	 * - BLK-08 Security Gate: Fail-closed in production (HTTP 423).
	 * - Reviewer capability ft_review_incidents or manage_options (403).
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return true|WP_Error
	 */
	public function check_review_permission( WP_REST_Request $request ) {
		$user_id = get_current_user_id();

		if ( $user_id <= 0 ) {
			return new WP_Error(
				'rest_not_logged_in',
				__( 'Authentication required to access incident review endpoints.', 'fraud-tenant-core' ),
				array( 'status' => 401 )
			);
		}

		// BLK-08 Security Gate: Fail closed in production mode
		if ( ! \FraudTenant\Services\ComplianceService::is_data_gate_open() ) {
			return new WP_Error(
				'incident_review_locked_blk08',
				__( 'Incident review endpoints are locked in production mode pending sensitive PII encryption infrastructure (BLK-08).', 'fraud-tenant-core' ),
				array( 'status' => 423 )
			);
		}

		if ( ! IncidentReviewService::can_review_incidents( $user_id ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to review incident reports.', 'fraud-tenant-core' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Permission callback for review decision mutation routes.
	 *
	 * Enforces:
	 * - Authentication required (401).
	 * - BLK-08 Security Gate: Fail-closed in production (HTTP 423).
	 * - Decision capability ft_decide_incidents (or ft_approve_incident_reports / manage_options) (403).
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return true|WP_Error
	 */
	public function check_decision_permission( WP_REST_Request $request ) {
		$user_id = get_current_user_id();

		if ( $user_id <= 0 ) {
			return new WP_Error(
				'rest_not_logged_in',
				__( 'Authentication required to submit review decisions.', 'fraud-tenant-core' ),
				array( 'status' => 401 )
			);
		}

		// BLK-08 Security Gate: Fail closed in production mode
		if ( ! \FraudTenant\Services\ComplianceService::is_data_gate_open() ) {
			return new WP_Error(
				'incident_review_locked_blk08',
				__( 'Incident review decisions are locked in production mode pending sensitive PII encryption infrastructure (BLK-08).', 'fraud-tenant-core' ),
				array( 'status' => 423 )
			);
		}

		if ( ! IncidentReviewService::can_decide_incidents( $user_id ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to decide the outcome of incident reports.', 'fraud-tenant-core' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Retrieve paginated administrative incident review queue.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response
	 */
	public function get_review_queue( WP_REST_Request $request ): WP_REST_Response {
		$reviewer_id = get_current_user_id();
		$params      = $request->get_params();

		$result = IncidentReviewService::get_review_queue( $params, $reviewer_id );

		if ( empty( $result['success'] ) ) {
			$status_code = ( 'unauthorized' === ( $result['error'] ?? '' ) ) ? 403 : 400;
			return new WP_REST_Response( $result, $status_code );
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Retrieve a single incident report record for authorized review inspection.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response
	 */
	public function get_incident_detail( WP_REST_Request $request ): WP_REST_Response {
		$reviewer_id = get_current_user_id();
		$report_id   = (int) $request['id'];

		$result = IncidentReviewService::get_incident_for_review( $report_id, $reviewer_id );

		if ( empty( $result['success'] ) ) {
			$status_code = ( 'report_not_found' === ( $result['error'] ?? '' ) ) ? 404 : 403;
			return new WP_REST_Response( $result, $status_code );
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Retrieve evidence documents associated with an incident report.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response
	 */
	public function get_incident_evidence( WP_REST_Request $request ): WP_REST_Response {
		$reviewer_id = get_current_user_id();
		$report_id   = (int) $request['id'];

		$detail = IncidentReviewService::get_incident_for_review( $report_id, $reviewer_id );
		if ( empty( $detail['success'] ) ) {
			$status_code = ( 'report_not_found' === ( $detail['error'] ?? '' ) ) ? 404 : 403;
			return new WP_REST_Response( $detail, $status_code );
		}

		$evidence = $detail['evidence_docs'] ?? array();

		return new WP_REST_Response(
			array(
				'success'       => true,
				'report_id'     => $report_id,
				'evidence_docs' => $evidence,
			),
			200
		);
	}

	/**
	 * Execute an administrative review decision (Approve, Reject, or Request Clarification).
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response
	 */
	public function submit_review_decision( WP_REST_Request $request ): WP_REST_Response {
		$reviewer_id = get_current_user_id();
		$report_id   = (int) $request['id'];
		$decision    = sanitize_key( (string) $request['decision'] );
		$reason_code = sanitize_key( (string) ( $request['reason_code'] ?? '' ) );

		$result = IncidentReviewService::decide_incident_report(
			$report_id,
			$reviewer_id,
			$decision,
			$reason_code
		);

		if ( empty( $result['success'] ) ) {
			$error = $result['error'] ?? '';
			$status_code = 400;

			if ( 'unauthorized' === $error ) {
				$status_code = 403;
			} elseif ( 'self_review_forbidden' === $error ) {
				$status_code = 403;
			} elseif ( 'report_not_found' === $error ) {
				$status_code = 404;
			} elseif ( 'conflict_already_decided' === $error ) {
				$status_code = 409;
			}

			return new WP_REST_Response( $result, $status_code );
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Retrieve review history decisions for an incident report.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response
	 */
	public function get_incident_history( WP_REST_Request $request ): WP_REST_Response {
		$reviewer_id = get_current_user_id();
		$report_id   = (int) $request['id'];

		$result = IncidentReviewService::get_incident_review_history( $report_id, $reviewer_id );

		if ( empty( $result['success'] ) ) {
			$status_code = ( 'unauthorized' === ( $result['error'] ?? '' ) ) ? 403 : 400;
			return new WP_REST_Response( $result, $status_code );
		}

		return new WP_REST_Response( $result, 200 );
	}
}

