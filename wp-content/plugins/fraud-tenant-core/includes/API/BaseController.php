<?php
/**
 * Abstract Base Controller for Fraud Tenant REST API.
 *
 * @package FraudTenant\API
 */

namespace FraudTenant\API;

use FraudTenant\Auth\Capabilities;
use FraudTenant\Utils\Constants;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class BaseController {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected string $namespace = Constants::REST_NAMESPACE;

	/**
	 * Register routes for this controller.
	 */
	abstract public function register_routes(): void;

	/**
	 * Standard success response wrapper.
	 *
	 * @param mixed $data Payload.
	 * @param int   $status HTTP status code.
	 * @return WP_REST_Response
	 */
	protected function success_response( mixed $data, int $status = 200 ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			$status
		);
	}

	/**
	 * Standard error response wrapper.
	 *
	 * @param string $code Error slug.
	 * @param string $message Readable message.
	 * @param int    $status HTTP status code.
	 * @param array  $details Optional additional non-sensitive details.
	 * @return WP_Error
	 */
	protected function error_response( string $code, string $message, int $status = 400, array $details = array() ): WP_Error {
		return new WP_Error(
			$code,
			$message,
			array(
				'status'  => $status,
				'details' => $details,
			)
		);
	}

	/**
	 * Permission callback: Must be logged in.
	 *
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public function check_authenticated( WP_REST_Request $request ): bool|WP_Error {
		if ( ! is_user_logged_in() ) {
			return $this->error_response( 'ft_unauthorized', __( 'Authentication is required.', 'fraud-tenant-core' ), 401 );
		}
		return true;
	}

	/**
	 * Permission callback: Must have administrative capabilities.
	 *
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public function check_admin_permission( WP_REST_Request $request ): bool|WP_Error {
		if ( ! is_user_logged_in() ) {
			return $this->error_response( 'ft_unauthorized', __( 'Authentication is required.', 'fraud-tenant-core' ), 401 );
		}
		if ( ! current_user_can( Capabilities::ACCESS_ADMIN_PORTAL ) && ! current_user_can( 'manage_options' ) ) {
			return $this->error_response( 'ft_forbidden', __( 'Access denied. Administrative capabilities required.', 'fraud-tenant-core' ), 403 );
		}
		return true;
	}

	/**
	 * Permission callback: Must be an authorized Property Owner.
	 *
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public function check_owner_permission( WP_REST_Request $request ): bool|WP_Error {
		if ( ! is_user_logged_in() ) {
			return $this->error_response( 'ft_unauthorized', __( 'Authentication is required.', 'fraud-tenant-core' ), 401 );
		}
		if ( ! current_user_can( Capabilities::ACCESS_OWNER_DASHBOARD ) && ! current_user_can( Capabilities::ACCESS_ADMIN_PORTAL ) ) {
			return $this->error_response( 'ft_forbidden', __( 'Access denied. Property Owner capabilities required.', 'fraud-tenant-core' ), 403 );
		}
		return true;
	}

	/**
	 * Permission callback: Must be an authorized Tenant.
	 *
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public function check_tenant_permission( WP_REST_Request $request ): bool|WP_Error {
		if ( ! is_user_logged_in() ) {
			return $this->error_response( 'ft_unauthorized', __( 'Authentication is required.', 'fraud-tenant-core' ), 401 );
		}
		if ( ! current_user_can( Capabilities::ACCESS_TENANT_DASHBOARD ) && ! current_user_can( Capabilities::ACCESS_ADMIN_PORTAL ) ) {
			return $this->error_response( 'ft_forbidden', __( 'Access denied. Tenant capabilities required.', 'fraud-tenant-core' ), 403 );
		}
		return true;
	}
}

