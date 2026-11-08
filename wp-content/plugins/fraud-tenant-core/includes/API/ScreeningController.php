<?php
/**
 * Owner tenant screening REST endpoints (identity-based search; same service as the dashboard form).
 *
 * POST /owner/screening            search (name + SSN; SSN never returned or logged)
 * GET  /owner/screening            own search history
 * GET  /owner/screening/{id}       own result — plan level enforced server-side on every read
 * GET  /owner/verified-properties  properties eligible for screening
 *
 * @package FraudTenant\API
 */

namespace FraudTenant\API;

use FraudTenant\Services\ScreeningService;
use FraudTenant\Services\TenantScreeningService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ScreeningController extends BaseController {

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/owner/screening',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'search' ),
					'permission_callback' => array( $this, 'can_screen' ),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'history' ),
					'permission_callback' => array( $this, 'can_screen' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/owner/screening/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'result' ),
				'permission_callback' => array( $this, 'can_screen' ),
				'args'                => array( 'id' => array( 'validate_callback' => static fn( $v ) => is_numeric( $v ) ) ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/owner/verified-properties',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'verified_properties' ),
				'permission_callback' => array( $this, 'can_screen' ),
			)
		);
	}

	/**
	 * Permission: authenticated, email-verified property owner allowed to screen.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function can_screen( WP_REST_Request $request ): bool|WP_Error {
		if ( ! is_user_logged_in() ) {
			return $this->error_response( 'ft_unauthorized', __( 'Authentication is required.', 'fraud-tenant-core' ), 401 );
		}
		$uid  = get_current_user_id();
		$user = get_userdata( $uid );
		if ( ! $user || ! in_array( 'ft_owner', (array) $user->roles, true ) || ! ScreeningService::can_initiate_screening( $uid ) ) {
			return $this->error_response( 'ft_forbidden', __( 'Only verified property owners can use tenant screening.', 'fraud-tenant-core' ), 403 );
		}
		return true;
	}

	/**
	 * Run a search.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function search( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$res = TenantScreeningService::search_applicant( get_current_user_id(), (array) $request->get_json_params() + (array) $request->get_body_params() );
		if ( empty( $res['success'] ) ) {
			return $this->error_response( 'ft_' . ( $res['error'] ?? 'screening_failed' ), (string) $res['message'], 'rate_limited' === ( $res['error'] ?? '' ) ? 429 : 400 );
		}
		return $this->success_response( TenantScreeningService::get_search_result( get_current_user_id(), (int) $res['request_id'] ), 201 );
	}

	/**
	 * Search history.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function history( WP_REST_Request $request ): WP_REST_Response {
		return $this->success_response( TenantScreeningService::get_owner_search_history( get_current_user_id(), 50 ) );
	}

	/**
	 * One result (404 when not the caller's).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function result( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$r = TenantScreeningService::get_search_result( get_current_user_id(), (int) $request['id'] );
		return $r ? $this->success_response( $r ) : $this->error_response( 'ft_not_found', __( 'Screening result not found.', 'fraud-tenant-core' ), 404 );
	}

	/**
	 * Verified properties.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function verified_properties( WP_REST_Request $request ): WP_REST_Response {
		return $this->success_response( ScreeningService::get_owner_verified_properties( get_current_user_id() ) );
	}
}
