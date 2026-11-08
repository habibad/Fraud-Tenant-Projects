<?php
/**
 * Tenant Management — tenant accounts with identity verification, visible records and disputes.
 *
 * @package FraudTenant\Admin
 */

defined( 'ABSPATH' ) || exit;

use FraudTenant\Admin\AdminConsole as C;
use FraudTenant\Auth\AuthService;
use FraudTenant\Services\IdentityClaimService;
use FraudTenant\Services\IdentityService;
use FraudTenant\Services\ReportVisibilityService;

global $wpdb;
$search = C::get( 'q' );
$page   = max( 1, absint( C::get( 'pg', '1' ) ) );
$per    = 25;
$args   = array( 'role' => 'ft_tenant', 'number' => $per, 'offset' => ( $page - 1 ) * $per, 'orderby' => 'registered', 'order' => 'DESC', 'count_total' => true );
if ( '' !== $search ) {
	$args['search']         = '*' . $search . '*';
	$args['search_columns'] = array( 'user_email', 'display_name' );
}
$q = new \WP_User_Query( $args );

C::head( __( 'Tenant Management', 'fraud-tenant-core' ), __( 'Tenant accounts, identity verification state, visible records and disputes.', 'fraud-tenant-core' ) );
?>
<form method="get" class="ft-filters" action="<?php echo esc_url( C::in_app() ? home_url( '/admin-dashboard/' ) : admin_url( 'admin.php' ) ); ?>">
	<input type="hidden" name="<?php echo C::in_app() ? 'section' : 'page'; ?>" value="<?php echo esc_attr( C::in_app() ? 'tenants' : 'fraud-tenant-tenants' ); ?>">
	<input class="ft-input" type="search" name="q" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search name or email', 'fraud-tenant-core' ); ?>" aria-label="<?php esc_attr_e( 'Search tenants', 'fraud-tenant-core' ); ?>">
	<button class="ft-btn ft-btn-secondary" type="submit"><?php esc_html_e( 'Search', 'fraud-tenant-core' ); ?></button>
</form>
<div class="ft-card"><div class="ft-table-wrap"><table class="ft-table">
	<thead><tr><th><?php esc_html_e( 'Tenant', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Account', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Identity', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Visible records', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Disputes', 'fraud-tenant-core' ); ?></th></tr></thead>
	<tbody>
	<?php foreach ( $q->get_results() as $u ) :
		$id    = (int) $u->ID;
		$state = IdentityClaimService::get_state( $id );
		$idn   = $state['identity'];
		$disp  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}ft_tenant_disputes WHERE tenant_id = %d", $id ) );
		?>
		<tr>
			<td><a href="<?php echo esc_url( C::url( 'users', array( 'user_id' => $id ) ) ); ?>"><?php echo esc_html( $u->display_name ); ?></a><br><span class="ft-muted"><?php echo esc_html( $u->user_email ); ?></span></td>
			<td><?php echo C::badge( AuthService::get_user_account_status( $id ) ); // phpcs:ignore ?></td>
			<td>
				<?php if ( $idn ) : ?>
					<a href="<?php echo esc_url( C::url( 'identities', array( 'identity_id' => (int) $idn->id ) ) ); ?>">#<?php echo (int) $idn->id; ?></a> <?php echo C::badge( 'verified' ); // phpcs:ignore ?>
				<?php elseif ( 'pending' === $state['state'] ) : ?>
					<a href="<?php echo esc_url( C::url( 'claims', array( 'claim_id' => (int) $state['claim']->id ) ) ); ?>"><?php echo C::badge( 'pending', __( 'Claim pending', 'fraud-tenant-core' ) ); // phpcs:ignore ?></a>
				<?php else : ?>
					<?php echo C::badge( 'no', 'rejected' === $state['state'] ? __( 'Claim rejected', 'fraud-tenant-core' ) : __( 'Not verified', 'fraud-tenant-core' ) ); // phpcs:ignore ?>
				<?php endif; ?>
			</td>
			<td><?php echo (int) count( ReportVisibilityService::get_tenant_reports( $id ) ); ?></td>
			<td><a href="<?php echo esc_url( C::url( 'disputes', array( 'q' => $u->user_email ) ) ); ?>"><?php echo (int) $disp; ?></a></td>
		</tr>
	<?php endforeach; ?>
	<?php if ( ! $q->get_results() ) : ?><tr><td colspan="5"><?php C::empty_state( __( 'No tenants found.', 'fraud-tenant-core' ) ); ?></td></tr><?php endif; ?>
	</tbody>
</table></div></div>
<?php C::pagination( 'tenants', $page, (int) $q->get_total(), $per, array( 'q' => $search ) ); ?>
