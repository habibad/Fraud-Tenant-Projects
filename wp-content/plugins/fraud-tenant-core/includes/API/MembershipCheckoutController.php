<?php
/**
 * REST API Controller for Membership Checkout and Stripe Billing Portal.
 *
 * Provides endpoints for approved property owners to initiate hosted Stripe
 * Checkout Sessions, manage billing via Stripe Customer Portal, and verify
 * checkout return states.
 *
 * @package FraudTenant\API
 */

namespace FraudTenant\API;

use FraudTenant\Auth\Capabilities;
use FraudTenant\Services\CheckoutService;
use FraudTenant\Services\MembershipService;
use FraudTenant\Utils\Constants;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MembershipCheckoutController extends BaseController {

	/**
	 * Register routes for this controller.
	 */
	public function register_routes(): void {
		// 1. Initiate Hosted Subscription Checkout Session (POST)
		register_rest_route(
			$this->namespace,
			'/owner/membership/checkout',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_checkout_session' ),
					'permission_callback' => array( $this, 'check_checkout_permission' ),
					'args'                => array(
						'plan_code' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_key',
							'validate_callback' => function( $param ) {
								return is_string( $param ) && '' !== trim( $param );
							},
						),
					),
				),
			)
		);

		// 2. Initiate Stripe Customer Portal Session (POST)
		register_rest_route(
			$this->namespace,
			'/owner/membership/portal',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_portal_session' ),
					'permission_callback' => array( $this, 'check_checkout_permission' ),
				),
			)
		);

		// 3. Verify Return Session Status (GET)
		register_rest_route(
			$this->namespace,
			'/owner/membership/session-status',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_session_status' ),
					'permission_callback' => array( $this, 'check_checkout_permission' ),
					'args'                => array(
						'session_id' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => function( $param ) {
								return is_string( $param ) && preg_match( '/^cs_[a-zA-Z0-9_]+$/', trim( $param ) );
							},
						),
					),
				),
			)
		);
	}

	/**
	 * Permission callback for owner membership checkout and portal actions.
	 *
	 * Enforcement:
	 * 1. User must be authenticated.
	 * 2. REST nonce must be valid.
	 * 3. User must be an approved, eligible owner (MembershipService::is_eligible_owner).
	 * 4. User must possess MANAGE_MEMBERSHIP capability.
	 * 5. Tenants and administrators are strictly rejected.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if authorized, WP_Error otherwise.
	 */
	public function check_checkout_permission( WP_REST_Request $request ): bool|WP_Error {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return new WP_Error(
				'rest_not_logged_in',
				__( 'Authentication is required to access membership checkout.', 'fraud-tenant-core' ),
				array( 'status' => 401 )
			);
		}

		// REST Nonce verification
		$nonce = $request->get_header( 'x_wp_nonce' );
		if ( empty( $nonce ) ) {
			$nonce = $request->get_param( '_wpnonce' );
		}

		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'rest_cookie_invalid_nonce',
				__( 'Cookie nonce is invalid or expired. Please refresh the page and try again.', 'fraud-tenant-core' ),
				array( 'status' => 403 )
			);
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new WP_Error(
				'rest_user_not_found',
				__( 'User account not found.', 'fraud-tenant-core' ),
				array( 'status' => 403 )
			);
		}

		$roles = (array) $user->roles;

		// Explicit role guards
		if ( in_array( Constants::ROLE_TENANT, $roles, true ) ) {
			return new WP_Error(
				'rest_forbidden_tenant',
				__( 'Tenant accounts are exempt from membership subscriptions.', 'fraud-tenant-core' ),
				array( 'status' => 403 )
			);
		}

		if ( in_array( 'administrator', $roles, true ) ) {
			return new WP_Error(
				'rest_forbidden_admin',
				__( 'Administrator accounts cannot subscribe to property owner membership plans.', 'fraud-tenant-core' ),
				array( 'status' => 403 )
			);
		}

		if ( ! current_user_can( Capabilities::MANAGE_MEMBERSHIP ) ) {
			return new WP_Error(
				'rest_forbidden_capability',
				__( 'You do not possess the required membership management capability.', 'fraud-tenant-core' ),
				array( 'status' => 403 )
			);
		}

		if ( ! MembershipService::is_eligible_owner( $user_id ) ) {
			return new WP_Error(
				'rest_forbidden_unapproved',
				__( 'Your account must be an approved property owner before purchasing membership subscriptions.', 'fraud-tenant-core' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Handle hosted checkout session creation.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_checkout_session( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id   = get_current_user_id();
		$plan_code = (string) $request->get_param( 'plan_code' );

		$result = CheckoutService::initiate_checkout( $user_id, $plan_code );

		if ( ! $result['success'] ) {
			$status = $result['status'] ?? 400;
			return $this->error_response(
				$result['code'] ?? 'checkout_failed',
				$result['error'] ?? __( 'Failed to create checkout session.', 'fraud-tenant-core' ),
				$status
			);
		}

		return $this->success_response(
			array(
				'checkout_url' => $result['checkout_url'],
				'session_id'   => $result['session_id'],
				'reused'       => ! empty( $result['reused'] ),
			),
			200
		);
	}

	/**
	 * Handle Stripe Customer Portal session creation.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_portal_session( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id = get_current_user_id();
		$result  = CheckoutService::initiate_customer_portal( $user_id );

		if ( ! $result['success'] ) {
			$status = $result['status'] ?? 400;
			return $this->error_response(
				$result['code'] ?? 'portal_failed',
				$result['error'] ?? __( 'Failed to create billing portal session.', 'fraud-tenant-core' ),
				$status
			);
		}

		return $this->success_response(
			array(
				'portal_url' => $result['portal_url'],
			),
			200
		);
	}

	/**
	 * Handle return session verification and status inspection.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_session_status( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id    = get_current_user_id();
		$session_id = (string) $request->get_param( 'session_id' );

		$result = CheckoutService::verify_checkout_return( $user_id, $session_id );

		if ( ! $result['success'] ) {
			$status = $result['http_status'] ?? 400;
			return $this->error_response(
				$result['code'] ?? 'session_status_failed',
				$result['error'] ?? __( 'Failed to inspect session status.', 'fraud-tenant-core' ),
				$status
			);
		}

		return $this->success_response(
			array(
				'status'    => $result['status'] ?? 'unknown',
				'message'   => $result['message'] ?? '',
				'plan_code' => $result['plan_code'] ?? '',
			),
			200
		);
	}
}

