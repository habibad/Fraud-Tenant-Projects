<?php
/**
 * Tenant record endpoints: reports disclosed to the tenant through their verified identity.
 *
 * GET /tenant/disclosed-reports       the caller's visible records
 * GET /tenant/disclosed-reports/{id}  one record (404 when not visible to the caller — no enumeration)
 *
 * Administrative association/disclosure actions are not exposed over REST; they run through the
 * Admin Command Center action handler with per-action nonces and capabilities.
 *
 * @package FraudTenant\API
 */

namespace FraudTenant\API;

use FraudTenant\Services\ReportVisibilityService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ReportVisibilityRestController extends BaseController {

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/tenant/disclosed-reports',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_reports' ),
				'permission_callback' => array( $this, 'is_tenant' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/tenant/disclosed-reports/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_report' ),
				'permission_callback' => array( $this, 'is_tenant' ),
				'args'                => array( 'id' => array( 'validate_callback' => static fn( $v ) => is_numeric( $v ) ) ),
			)
		);
	}

	/**
	 * Permission: authenticated tenant.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function is_tenant( WP_REST_Request $request ): bool|WP_Error {
		if ( ! is_user_logged_in() ) {
			return $this->error_response( 'ft_unauthorized', __( 'Authentication is required.', 'fraud-tenant-core' ), 401 );
		}
		if ( ! in_array( 'ft_tenant', (array) wp_get_current_user()->roles, true ) ) {
			return $this->error_response( 'ft_forbidden', __( 'Only tenant accounts can access tenant records.', 'fraud-tenant-core' ), 403 );
		}
		return true;
	}

	/**
	 * Visible records.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function list_reports( WP_REST_Request $request ): WP_REST_Response {
		return $this->success_response( array_map( array( $this, 'shape' ), ReportVisibilityService::get_tenant_reports( get_current_user_id() ) ) );
	}

	/**
	 * One record.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_report( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$r = ReportVisibilityService::get_tenant_report( (int) $request['id'], get_current_user_id() );
		return $r ? $this->success_response( $this->shape( $r ) ) : $this->error_response( 'ft_not_found', __( 'Record not found.', 'fraud-tenant-core' ), 404 );
	}

	/**
	 * Tenant-safe fields only.
	 *
	 * @param object $r Report row.
	 * @return array
	 */
	protected function shape( object $r ): array {
		return array(
			'id'                  => (int) $r->id,
			'reference'           => $r->reference,
			'category'            => $r->category_label,
			'incident_date'       => $r->incident_date,
			'amount'              => (float) $r->amount_involved,
			'factual_description' => (string) $r->factual_description,
			'property'            => trim( (string) $r->property_title . ', ' . (string) $r->property_city . ', ' . (string) $r->property_state, ', ' ),
			'status'              => $r->status,
			'my_dispute_status'   => $r->my_dispute_status,
		);
	}
}
