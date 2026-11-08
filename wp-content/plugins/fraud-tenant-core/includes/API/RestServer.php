<?php
/**
 * REST API Server Coordinator.
 *
 * @package FraudTenant\API
 */

namespace FraudTenant\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RestServer {

	/**
	 * Initialize REST routes.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register all controller routes.
	 */
	public static function register_routes(): void {
		$controllers = array(
			new DiagnosticController(),
			new ScreeningController(),
			new IncidentController(),
			new IncidentReviewRestController(),
			new ReportVisibilityRestController(),
			new DisputeRestController(),
			new MembershipCheckoutController(),
			new StripeWebhookController(),
		);

		foreach ( $controllers as $controller ) {
			$controller->register_routes();
		}
	}
}

