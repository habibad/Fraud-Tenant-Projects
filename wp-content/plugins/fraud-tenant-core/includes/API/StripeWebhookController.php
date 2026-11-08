<?php
/**
 * REST API Controller for Inbound Stripe Webhooks.
 *
 * Exposes /fraud-tenant/v1/stripe/webhook endpoint for automated Stripe event ingestion.
 *
 * @package FraudTenant\API
 */

namespace FraudTenant\API;

use FraudTenant\Services\StripeWebhookService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class StripeWebhookController extends BaseController {

	/**
	 * Register routes for this controller.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/stripe/webhook',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_webhook' ),
					'permission_callback' => '__return_true', // Public webhook callback verified via HMAC-SHA256 signature
				),
			)
		);
	}

	/**
	 * Handle inbound Stripe Webhook POST request.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response Response.
	 */
	public function handle_webhook( WP_REST_Request $request ): WP_REST_Response {
		return StripeWebhookService::handle_webhook( $request );
	}
}

