<?php
/**
 * Template Name: Owner Dashboard
 *
 * Property Owner app: properties, verification, tenant screening, incident reports, membership,
 * notifications and account settings. Every figure comes from the database; every action is
 * authorized server-side by the Fraud Tenant Core services.
 *
 * @package FraudTenantTheme
 */

defined( 'ABSPATH' ) || exit;

use FraudTenant\Services\DashboardService;
use FraudTenant\Utils\Constants;

$user     = DashboardService::enforce_access( Constants::ROLE_OWNER );
$owner_id = (int) $user->ID;
$sections = array( 'overview', 'properties', 'add-property', 'verification', 'screening', 'report', 'reports', 'membership', 'notifications', 'settings' );
$section  = ft_app_section( $sections );
$data     = DashboardService::get_owner_dashboard_data( $owner_id );

$pending_verification = $data['properties_by_status']['pending_verification'] + $data['properties_by_status']['info_requested'];
$clarify              = $data['report_counts']['needs_clarification'];

get_header(
	'app',
	array(
		'role_label' => __( 'Property Owner Dashboard', 'fraud-tenant-theme' ),
		'base'       => 'owner-dashboard',
		'section'    => $section,
		'unread'     => $data['unread_notifications_count'],
		'nav'        => array(
			__( 'Workspace', 'fraud-tenant-theme' ) => array(
				'overview'     => array( __( 'Overview', 'fraud-tenant-theme' ), 'home' ),
				'properties'   => array( __( 'My Properties', 'fraud-tenant-theme' ), 'building', count( $data['properties'] ) ),
				'add-property' => array( __( 'Add Property', 'fraud-tenant-theme' ), 'plus' ),
				'verification' => array( __( 'Verification Status', 'fraud-tenant-theme' ), 'check', $pending_verification ),
			),
			__( 'Tenants', 'fraud-tenant-theme' )   => array(
				'screening' => array( __( 'Tenant Screening', 'fraud-tenant-theme' ), 'search' ),
				'report'    => array( __( 'Submit Incident Report', 'fraud-tenant-theme' ), 'fileplus' ),
				'reports'   => array( __( 'My Incident Reports', 'fraud-tenant-theme' ), 'file', $clarify ),
			),
			__( 'Account', 'fraud-tenant-theme' )   => array(
				'membership'    => array( __( 'Membership & Billing', 'fraud-tenant-theme' ), 'card' ),
				'notifications' => array( __( 'Notifications', 'fraud-tenant-theme' ), 'bell', $data['unread_notifications_count'] ),
				'settings'      => array( __( 'Profile & Settings', 'fraud-tenant-theme' ), 'user' ),
			),
		),
	)
);

$ctx = array( 'data' => $data, 'owner_id' => $owner_id, 'user' => $user, 'base' => 'owner-dashboard' );
if ( in_array( $section, array( 'notifications', 'settings' ), true ) ) {
	get_template_part( 'template-parts/shared/' . $section, null, $ctx );
} else {
	get_template_part( 'template-parts/owner/' . $section, null, $ctx );
}

get_footer( 'app' );
