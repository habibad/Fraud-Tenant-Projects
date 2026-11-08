<?php
/**
 * Template Name: Admin Command Center
 *
 * Custom Admin Dashboard. Renders the same AdminConsole sections (and uses the same action handler
 * and services) as the native WordPress admin pages under Fraud Tenant.
 *
 * @package FraudTenantTheme
 */

defined( 'ABSPATH' ) || exit;

use FraudTenant\Admin\AdminConsole;
use FraudTenant\Services\DashboardService;
use FraudTenant\Services\NotificationService;

$user    = DashboardService::enforce_access( 'admin' );
$section = sanitize_key( (string) ( $_GET['section'] ?? 'overview' ) ); // phpcs:ignore WordPress.Security.NonceVerification
if ( ! isset( AdminConsole::SECTIONS[ $section ] ) ) {
	$section = 'overview';
}
if ( ! AdminConsole::can( $section ) ) {
	status_header( 403 );
}

$icons = array(
	'overview' => 'grid', 'users' => 'user', 'owners' => 'building', 'tenants' => 'user', 'accounts' => 'check',
	'properties' => 'building', 'incidents' => 'file', 'disputes' => 'scale', 'screening' => 'search',
	'identities' => 'id', 'exceptions' => 'shield', 'claims' => 'id', 'disclosure' => 'shield',
	'plans' => 'card', 'subscriptions' => 'card', 'stripe' => 'card',
	'notifications' => 'bell', 'audit' => 'history', 'settings' => 'grid', 'security' => 'shield',
);
global $wpdb;
$counts = array(
	'incidents'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ft_incident_reports WHERE status = 'submitted'" ),
	'exceptions' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ft_incident_reports WHERE identity_match = 'exception'" ),
	'claims'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ft_identity_claims WHERE status = 'pending'" ),
	'disputes'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ft_tenant_disputes WHERE status IN ('pending','under_review')" ),
	'properties' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ft_properties WHERE status IN ('pending_verification','info_requested') AND archived_at IS NULL" ),
);
$nav = array();
foreach ( AdminConsole::nav() as $group => $items ) {
	foreach ( $items as $key => $label ) {
		$nav[ $group ][ $key ] = array( $label, $icons[ $key ] ?? 'grid', $counts[ $key ] ?? 0 );
	}
}

get_header(
	'app',
	array(
		'role_label' => __( 'Admin Command Center', 'fraud-tenant-theme' ),
		'base'       => 'admin-dashboard',
		'section'    => $section,
		'nav'        => $nav,
		'unread'     => NotificationService::unread_count( (int) $user->ID ),
	)
);
?>
<div class="ft-console-links"><a href="<?php echo esc_url( add_query_arg( 'page', AdminConsole::SECTIONS[ $section ][3], admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Open this section in WordPress Admin', 'fraud-tenant-theme' ); ?> →</a></div>
<?php
AdminConsole::render_section( $section );
get_footer( 'app' );
