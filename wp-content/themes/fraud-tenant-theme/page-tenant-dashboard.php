<?php
/**
 * Template Name: Tenant Dashboard
 *
 * Tenant portal: identity verification, the tenant's own reviewed rental records, disputes,
 * notifications and account settings. A tenant only ever sees reports linked to the identity they
 * have verifiably claimed and that were approved and disclosed to them.
 *
 * @package FraudTenantTheme
 */

defined( 'ABSPATH' ) || exit;

use FraudTenant\Services\DashboardService;
use FraudTenant\Utils\Constants;

$user      = DashboardService::enforce_access( Constants::ROLE_TENANT );
$tenant_id = (int) $user->ID;
$sections  = array( 'overview', 'identity', 'records', 'disputes', 'submit-dispute', 'dispute-history', 'notifications', 'settings' );
$section   = ft_app_section( $sections );
$data      = DashboardService::get_tenant_dashboard_data( $tenant_id );

get_header(
	'app',
	array(
		'role_label' => __( 'Tenant Dashboard', 'fraud-tenant-theme' ),
		'base'       => 'tenant-dashboard',
		'section'    => $section,
		'unread'     => $data['unread_notifications_count'],
		'nav'        => array(
			__( 'My records', 'fraud-tenant-theme' ) => array(
				'overview' => array( __( 'Overview', 'fraud-tenant-theme' ), 'home' ),
				'identity' => array( __( 'Identity Verification', 'fraud-tenant-theme' ), 'id' ),
				'records'  => array( __( 'My Rental Records', 'fraud-tenant-theme' ), 'file', count( $data['records'] ) ),
			),
			__( 'Disputes', 'fraud-tenant-theme' )   => array(
				'disputes'        => array( __( 'My Disputes', 'fraud-tenant-theme' ), 'scale', count( $data['active_disputes'] ) ),
				'submit-dispute'  => array( __( 'Submit Dispute', 'fraud-tenant-theme' ), 'fileplus' ),
				'dispute-history' => array( __( 'Dispute History', 'fraud-tenant-theme' ), 'history' ),
			),
			__( 'Account', 'fraud-tenant-theme' )    => array(
				'notifications' => array( __( 'Notifications', 'fraud-tenant-theme' ), 'bell', $data['unread_notifications_count'] ),
				'settings'      => array( __( 'Profile & Settings', 'fraud-tenant-theme' ), 'user' ),
			),
		),
	)
);

$ctx = array( 'data' => $data, 'tenant_id' => $tenant_id, 'user' => $user, 'base' => 'tenant-dashboard' );
if ( in_array( $section, array( 'notifications', 'settings' ), true ) ) {
	get_template_part( 'template-parts/shared/' . $section, null, $ctx );
} else {
	get_template_part( 'template-parts/tenant/' . $section, null, $ctx );
}

get_footer( 'app' );
