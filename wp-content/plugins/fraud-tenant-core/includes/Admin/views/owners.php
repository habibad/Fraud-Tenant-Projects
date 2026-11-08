<?php
/**
 * Owner Management — property owners with plan, properties and report counts.
 *
 * @package FraudTenant\Admin
 */

defined( 'ABSPATH' ) || exit;

use FraudTenant\Admin\AdminConsole as C;
use FraudTenant\Auth\AuthService;
use FraudTenant\Services\MembershipService;

global $wpdb;
$p      = $wpdb->prefix;
$search = C::get( 'q' );
$page   = max( 1, absint( C::get( 'pg', '1' ) ) );
$per    = 25;
$args   = array( 'role' => 'ft_owner', 'number' => $per, 'offset' => ( $page - 1 ) * $per, 'orderby' => 'registered', 'order' => 'DESC', 'count_total' => true );
if ( '' !== $search ) {
	$args['search']         = '*' . $search . '*';
	$args['search_columns'] = array( 'user_email', 'display_name' );
}
$q = new \WP_User_Query( $args );

C::head( __( 'Owner Management', 'fraud-tenant-core' ), __( 'Property owners, their membership, properties and incident reports.', 'fraud-tenant-core' ) );
?>
<form method="get" class="ft-filters" action="<?php echo esc_url( C::in_app() ? home_url( '/admin-dashboard/' ) : admin_url( 'admin.php' ) ); ?>">
	<input type="hidden" name="<?php echo C::in_app() ? 'section' : 'page'; ?>" value="<?php echo esc_attr( C::in_app() ? 'owners' : 'fraud-tenant-owners' ); ?>">
	<input class="ft-input" type="search" name="q" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search name or email', 'fraud-tenant-core' ); ?>" aria-label="<?php esc_attr_e( 'Search owners', 'fraud-tenant-core' ); ?>">
	<button class="ft-btn ft-btn-secondary" type="submit"><?php esc_html_e( 'Search', 'fraud-tenant-core' ); ?></button>
</form>
<div class="ft-card"><div class="ft-table-wrap"><table class="ft-table">
	<thead><tr><th><?php esc_html_e( 'Owner', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Status', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Plan', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Active properties', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Verified', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Reports', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Searches', 'fraud-tenant-core' ); ?></th></tr></thead>
	<tbody>
	<?php foreach ( $q->get_results() as $u ) :
		$id    = (int) $u->ID;
		$props = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$p}ft_properties WHERE owner_id = %d AND archived_at IS NULL AND status <> 'archived'", $id ) );
		$ver   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$p}ft_properties WHERE owner_id = %d AND status = 'verified' AND archived_at IS NULL", $id ) );
		$reps  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$p}ft_incident_reports WHERE owner_id = %d", $id ) );
		$srch  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$p}ft_screening_requests WHERE owner_id = %d", $id ) );
		?>
		<tr>
			<td><a href="<?php echo esc_url( C::url( 'users', array( 'user_id' => $id ) ) ); ?>"><?php echo esc_html( $u->display_name ); ?></a><br><span class="ft-muted"><?php echo esc_html( $u->user_email ); ?></span></td>
			<td><?php echo C::badge( AuthService::get_user_account_status( $id ) ); // phpcs:ignore ?></td>
			<td><?php echo esc_html( MembershipService::get_owner_plan( $id )->name ); ?></td>
			<td><a href="<?php echo esc_url( C::url( 'properties', array( 'q' => $u->user_email ) ) ); ?>"><?php echo (int) $props; ?></a></td>
			<td><?php echo (int) $ver; ?></td>
			<td><a href="<?php echo esc_url( C::url( 'incidents', array( 'owner_id' => $id ) ) ); ?>"><?php echo (int) $reps; ?></a></td>
			<td><?php echo (int) $srch; ?></td>
		</tr>
	<?php endforeach; ?>
	<?php if ( ! $q->get_results() ) : ?><tr><td colspan="7"><?php C::empty_state( __( 'No property owners found.', 'fraud-tenant-core' ) ); ?></td></tr><?php endif; ?>
	</tbody>
</table></div></div>
<?php C::pagination( 'owners', $page, (int) $q->get_total(), $per, array( 'q' => $search ) ); ?>
