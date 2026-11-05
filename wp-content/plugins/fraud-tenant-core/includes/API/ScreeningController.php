<?php
/**
 * REST API Controller for Property Owner Screening Requests.
 *
 * Provides protected endpoints for authorized property owners to submit screening requests,
 * retrieve verified properties, and inspect request history.
 *
 * @package FraudTenant\API
 */

namespace FraudTenant\API;

use FraudTenant\Auth\AuthService;
use FraudTenant\Auth\Capabilities;
use FraudTenant\Services\ScreeningService;
use FraudTenant\Utils\Constants;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ScreeningController extends BaseController {

	/**
	 * Register routes for screening requests.
	 */
	public function register_routes(): void {
		// 1. Submit screening request (POST)
		register_rest_route(
			$this->namespace,
			'/owner/tenant-requests',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_request' ),
					'permission_callback' => array( $this, 'check_owner_screening_permission' ),
					'args'                => array(
						'property_id'                     => array(
							'required'          => true,
							'type'              => 'integer',
							'validate_callback' => function ( $param ) {
								return is_numeric( $param ) && (int) $param > 0;
							},
						),
						'purpose_reason'                  => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_key',
						),
						'permissible_purpose_attestation' => array(
							'required' => true,
						),
					),
				),
			)
		);

		// 2. List own screening requests (GET)
		register_rest_route(
			$this->namespace,
			'/owner/tenant-requests',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_requests' ),
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

		// 3. Get single screening request details (GET)
		register_rest_route(
			$this->namespace,
			'/owner/tenant-requests/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_single_request' ),
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

		// 4. List verified properties eligible for screening (GET)
		register_rest_route(
			$this->namespace,
			'/owner/verified-properties',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_verified_properties' ),
					'permission_callback' => array( $this, 'check_owner_screening_permission' ),
				),
			)
		);
	}

	/**
	 * Permission callback: Must be an authenticated, approved Property Owner with initiate capability.
	 *
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public function check_owner_screening_permission( WP_REST_Request $request ): bool|WP_Error {
		if ( ! is_user_logged_in() ) {
			return $this->error_response( 'ft_unauthorized', __( 'Authentication is required.', 'fraud-tenant-core' ), 401 );
		}

		$user_id = get_current_user_id();

		// Check capability and approval status via ScreeningService
		if ( ! ScreeningService::can_initiate_screening( $user_id ) ) {
			return $this->error_response(
				'ft_forbidden',
				__( 'Access denied. You do not have permission to initiate tenant screening requests.', 'fraud-tenant-core' ),
				403
			);
		}

		return true;
	}

	/**
	 * Permission callback: Must be an authenticated, approved Property Owner with view capability.
	 *
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public function check_owner_view_permission( WP_REST_Request $request ): bool|WP_Error {
		if ( ! is_user_logged_in() ) {
			return $this->error_response( 'ft_unauthorized', __( 'Authentication is required.', 'fraud-tenant-core' ), 401 );
		}

		$user_id = get_current_user_id();

		if ( ! ScreeningService::can_view_screening_reports( $user_id ) ) {
			return $this->error_response(
				'ft_forbidden',
				__( 'Access denied. You do not have permission to view screening records.', 'fraud-tenant-core' ),
				403
			);
		}

		return true;
	}

	/**
	 * Handle POST /owner/tenant-requests.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_request( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$owner_id = get_current_user_id();
		$params   = $request->get_params();

		$result = ScreeningService::record_screening_request( $owner_id, $params );

		if ( ! $result['success'] ) {
			$status_code = 400;
			if ( 'unauthorized' === ( $result['error'] ?? '' ) ) {
				$status_code = 403;
			} elseif ( 'rate_limited' === ( $result['error'] ?? '' ) ) {
				$status_code = 429;
			}
			return $this->error_response( $result['error'], $result['message'], $status_code );
		}

		return $this->success_response( $result['data'], 201 );
	}

	/**
	 * Handle GET /owner/tenant-requests.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_requests( WP_REST_Request $request ): WP_REST_Response {
		$owner_id = get_current_user_id();
		$limit    = (int) $request->get_param( 'limit' ) ?: 20;
		$offset   = (int) $request->get_param( 'offset' ) ?: 0;

		$results = ScreeningService::get_owner_screening_requests(
			$owner_id,
			array(
				'limit'  => $limit,
				'offset' => $offset,
			)
		);

		return $this->success_response(
			array(
				'items'  => $results,
				'count'  => count( $results ),
				'limit'  => $limit,
				'offset' => $offset,
			),
			200
		);
	}

	/**
	 * Handle GET /owner/tenant-requests/{id}.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_single_request( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$owner_id   = get_current_user_id();
		$request_id = (int) $request->get_param( 'id' );

		$item = ScreeningService::get_screening_request( $request_id, $owner_id );
		if ( ! $item ) {
			return $this->error_response(
				'ft_not_found_or_forbidden',
				__( 'Screening request not found or access denied.', 'fraud-tenant-core' ),
				404
			);
		}

		return $this->success_response( $item, 200 );
	}

	/**
	 * Handle GET /owner/verified-properties.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_verified_properties( WP_REST_Request $request ): WP_REST_Response {
		$owner_id   = get_current_user_id();
		$properties = ScreeningService::get_owner_verified_properties( $owner_id );

		return $this->success_response( $properties, 200 );
	}
}

