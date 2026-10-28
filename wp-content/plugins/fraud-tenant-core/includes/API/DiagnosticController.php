<?php
/**
 * Safe Diagnostic and Health REST Controller.
 *
 * @package FraudTenant\API
 */

namespace FraudTenant\API;

use FraudTenant\Services\SecurityService;
use FraudTenant\Utils\Constants;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DiagnosticController extends BaseController {

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		// 1. Safe Public Health Check (Reveals zero secrets, credentials, or PII)
		register_rest_route(
			$this->namespace,
			'/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_system_status' ),
				'permission_callback' => '__return_true',
			)
		);

		// 2. Protected Authentication Test Endpoint (Demonstrates permission callback)
		register_rest_route(
			$this->namespace,
			'/auth-check',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_auth_check' ),
				'permission_callback' => array( $this, 'check_authenticated' ),
			)
		);
	}

	/**
	 * Return safe operational status.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_system_status( WP_REST_Request $request ): WP_REST_Response {
		$db_version = get_option( Constants::DB_VERSION_OPTION, 'not_installed' );

		return $this->success_response(
			array(
				'status'             => 'operational',
				'plugin'             => 'fraud-tenant-core',
				'api_version'        => Constants::VERSION,
				'schema_version'     => $db_version,
				'ssn_matching_ready' => SecurityService::is_match_key_configured(),
				'otp_hashing_ready'  => SecurityService::is_otp_secret_configured(),
				'timestamp'          => gmdate( 'c' ),
			)
		);
	}

	/**
	 * Authenticated echo endpoint.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_auth_check( WP_REST_Request $request ): WP_REST_Response {
		$user_id = get_current_user_id();
		return $this->success_response(
			array(
				'authenticated' => true,
				'user_id'       => $user_id,
			)
		);
	}
}
