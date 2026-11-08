<?php
/**
 * User Management — all accounts (search, role/status filters, detail with account actions).
 *
 * @package FraudTenant\Admin
 */

defined( 'ABSPATH' ) || exit;

use FraudTenant\Admin\AdminConsole as C;
use FraudTenant\Admin\AdminReviewService;
use FraudTenant\Auth\AuthService;
use FraudTenant\Services\IdentityService;
use FraudTenant\Services\MembershipService;
use FraudTenant\Services\StripeCredentialService;
use FraudTenant\Utils\Constants;

global $wpdb;
$detail_id = absint( C::get( 'user_id' ) );

if ( $detail_id ) {
	$u = get_userdata( $detail_id );
	if ( ! $u ) {
		C::empty_state( __( 'User not found.', 'fraud-tenant-core' ) );
		return;
	}
	$profile  = AuthService::get_user_profile( $detail_id );
	$status   = AuthService::get_user_account_status( $detail_id );
	$is_owner = in_array( Constants::ROLE_OWNER, (array) $u->roles, true );
	$is_ten   = in_array( Constants::ROLE_TENANT, (array) $u->roles, true );
	C::head( $u->display_name, __( 'Account detail', 'fraud-tenant-core' ) );
	echo '<p><a href="' . esc_url( C::url( 'users' ) ) . '">← ' . esc_html__( 'All users', 'fraud-tenant-core' ) . '</a></p>';
	?>
	<div class="ft-grid ft-grid-2">
		<section class="ft-card">
			<div class="ft-card-head"><h2><?php esc_html_e( 'Account', 'fraud-tenant-core' ); ?></h2><?php echo C::badge( $status ); // phpcs:ignore ?></div>
			<dl class="ft-dl">
				<dt><?php esc_html_e( 'Email', 'fraud-tenant-core' ); ?></dt><dd><?php echo esc_html( $u->user_email ); ?></dd>
				<dt><?php esc_html_e( 'Role', 'fraud-tenant-core' ); ?></dt><dd><?php echo esc_html( implode( ', ', array_map( static fn( $r ) => wp_roles()->roles[ $r ]['name'] ?? $r, (array) $u->roles ) ) ); ?></dd>
				<dt><?php esc_html_e( 'Phone', 'fraud-tenant-core' ); ?></dt><dd><?php echo esc_html( $profile->phone ?? '—' ); ?></dd>
				<?php if ( $is_owner && ! empty( $profile->business_name ) ) : ?>
					<dt><?php esc_html_e( 'Business', 'fraud-tenant-core' ); ?></dt><dd><?php echo esc_html( $profile->business_name ); ?></dd>
				<?php endif; ?>
				<dt><?php esc_html_e( 'Registered', 'fraud-tenant-core' ); ?></dt><dd><?php echo C::date( $u->user_registered ); // phpcs:ignore ?></dd>
				<?php if ( $is_owner ) : $m = MembershipService::get_effective_membership_status( $detail_id ); ?>
					<dt><?php esc_html_e( 'Membership', 'fraud-tenant-core' ); ?></dt><dd><?php echo esc_html( $m['plan_name'] ); ?><?php echo $m['current_period_end'] ? ' · ' . esc_html__( 'renews/ends', 'fraud-tenant-core' ) . ' ' . C::date( $m['current_period_end'] ) : ''; // phpcs:ignore ?></dd>
					<dt><?php esc_html_e( 'Billing QA account', 'fraud-tenant-core' ); ?></dt><dd><?php echo StripeCredentialService::is_billing_qa_account( $detail_id ) ? esc_html__( 'Yes', 'fraud-tenant-core' ) : esc_html__( 'No', 'fraud-tenant-core' ); ?></dd>
				<?php endif; ?>
				<?php if ( $is_ten ) : $idn = IdentityService::get_for_user( $detail_id ); ?>
					<dt><?php esc_html_e( 'Identity', 'fraud-tenant-core' ); ?></dt><dd><?php echo $idn ? '<a href="' . esc_url( C::url( 'identities', array( 'identity_id' => (int) $idn->id ) ) ) . '">#' . (int) $idn->id . '</a> ' . C::badge( 'verified' ) : C::badge( 'no', __( 'Not verified', 'fraud-tenant-core' ) ); // phpcs:ignore ?></dd>
				<?php endif; ?>
			</dl>
		</section>
		<section class="ft-card">
			<div class="ft-card-head"><h2><?php esc_html_e( 'Account actions', 'fraud-tenant-core' ); ?></h2></div>
			<?php if ( C::can( 'accounts' ) ) : ?>
				<?php if ( Constants::STATUS_SUSPENDED === $status ) : ?>
					<?php C::form( 'user_reactivate', array( 'user_id' => $detail_id ) ); ?>
						<label class="ft-label" for="ft-react-reason"><?php esc_html_e( 'Reason', 'fraud-tenant-core' ); ?></label>
						<textarea class="ft-textarea" id="ft-react-reason" name="reason" required minlength="5"></textarea>
						<button class="ft-btn ft-btn-primary" type="submit"><?php esc_html_e( 'Reactivate account', 'fraud-tenant-core' ); ?></button>
					</form>
				<?php elseif ( ! in_array( 'administrator', (array) $u->roles, true ) ) : ?>
					<?php C::form( 'user_suspend', array( 'user_id' => $detail_id ) ); ?>
						<label class="ft-label" for="ft-susp-reason"><?php esc_html_e( 'Suspension reason (shown to the user)', 'fraud-tenant-core' ); ?></label>
						<textarea class="ft-textarea" id="ft-susp-reason" name="reason" required minlength="5"></textarea>
						<button class="ft-btn ft-btn-danger" type="submit"><?php esc_html_e( 'Suspend account', 'fraud-tenant-core' ); ?></button>
					</form>
				<?php endif; ?>
				<?php if ( Constants::STATUS_EMAIL_UNVERIFIED === $status ) : ?>
					<?php C::form( 'user_resend_otp', array( 'user_id' => $detail_id ) ); ?>
						<button class="ft-btn ft-btn-secondary" type="submit"><?php esc_html_e( 'Resend verification code', 'fraud-tenant-core' ); ?></button>
					</form>
				<?php endif; ?>
			<?php endif; ?>
			<?php if ( $is_owner && current_user_can( 'manage_options' ) ) : $qa = StripeCredentialService::is_billing_qa_account( $detail_id ); ?>
				<?php C::form( 'billing_qa_flag', array( 'user_id' => $detail_id, 'enable' => $qa ? '' : '1' ) ); ?>
					<button class="ft-btn ft-btn-ghost" type="submit"><?php echo $qa ? esc_html__( 'Remove billing QA flag', 'fraud-tenant-core' ) : esc_html__( 'Mark as billing QA account', 'fraud-tenant-core' ); ?></button>
					<p class="ft-help"><?php esc_html_e( 'QA accounts may use Stripe Test mode while a production site runs in Test mode.', 'fraud-tenant-core' ); ?></p>
				</form>
			<?php endif; ?>
		</section>
	</div>
	<section class="ft-card">
		<div class="ft-card-head"><h2><?php esc_html_e( 'Recent account activity', 'fraud-tenant-core' ); ?></h2></div>
		<?php $hist = AdminReviewService::get_user_audit_history( $detail_id, 15 ); ?>
		<?php if ( ! $hist ) : C::empty_state( __( 'No activity recorded.', 'fraud-tenant-core' ) ); else : ?>
			<div class="ft-table-wrap"><table class="ft-table"><thead><tr><th><?php esc_html_e( 'When', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Event', 'fraud-tenant-core' ); ?></th></tr></thead><tbody>
			<?php foreach ( $hist as $h ) : ?>
				<tr><td><?php echo C::date( $h->created_at ?? '', true ); // phpcs:ignore ?></td><td class="ft-mono"><?php echo esc_html( $h->action ?? '' ); ?></td></tr>
			<?php endforeach; ?>
			</tbody></table></div>
		<?php endif; ?>
	</section>
	<?php
	return;
}

$search = C::get( 'q' );
$role   = C::get( 'role', 'all' );
$status = C::get( 'status', 'all' );
$page   = max( 1, absint( C::get( 'pg', '1' ) ) );
$per    = 25;
$args   = array( 'number' => $per, 'offset' => ( $page - 1 ) * $per, 'orderby' => 'registered', 'order' => 'DESC', 'count_total' => true );
if ( in_array( $role, array( 'ft_owner', 'ft_tenant', 'administrator' ), true ) ) {
	$args['role'] = $role;
}
if ( '' !== $search ) {
	$args['search']         = '*' . $search . '*';
	$args['search_columns'] = array( 'user_email', 'display_name', 'user_login' );
}
if ( 'all' !== $status ) {
	$ids             = $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM {$wpdb->prefix}ft_user_profiles WHERE approval_status = %s", $status ) );
	$args['include'] = $ids ? array_map( 'intval', $ids ) : array( 0 );
}
$q = new \WP_User_Query( $args );

C::head( __( 'User Management', 'fraud-tenant-core' ), __( 'All platform accounts. Open an account to suspend, reactivate or resend verification.', 'fraud-tenant-core' ) );
?>
<form method="get" class="ft-filters" action="<?php echo esc_url( C::in_app() ? home_url( '/admin-dashboard/' ) : admin_url( 'admin.php' ) ); ?>">
	<input type="hidden" name="<?php echo C::in_app() ? 'section' : 'page'; ?>" value="<?php echo esc_attr( C::in_app() ? 'users' : 'fraud-tenant-users' ); ?>">
	<input class="ft-input" type="search" name="q" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search name or email', 'fraud-tenant-core' ); ?>" aria-label="<?php esc_attr_e( 'Search users', 'fraud-tenant-core' ); ?>">
	<select class="ft-select" name="role" aria-label="<?php esc_attr_e( 'Role', 'fraud-tenant-core' ); ?>">
		<?php foreach ( array( 'all' => __( 'All roles', 'fraud-tenant-core' ), 'ft_owner' => __( 'Property owners', 'fraud-tenant-core' ), 'ft_tenant' => __( 'Tenants', 'fraud-tenant-core' ), 'administrator' => __( 'Administrators', 'fraud-tenant-core' ) ) as $k => $l ) : ?>
			<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $role, $k ); ?>><?php echo esc_html( $l ); ?></option>
		<?php endforeach; ?>
	</select>
	<select class="ft-select" name="status" aria-label="<?php esc_attr_e( 'Status', 'fraud-tenant-core' ); ?>">
		<?php foreach ( array( 'all' => __( 'All statuses', 'fraud-tenant-core' ), 'approved' => __( 'Active', 'fraud-tenant-core' ), 'email_unverified' => __( 'Email unverified', 'fraud-tenant-core' ), 'suspended' => __( 'Suspended', 'fraud-tenant-core' ), 'rejected' => __( 'Rejected (legacy)', 'fraud-tenant-core' ) ) as $k => $l ) : ?>
			<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $status, $k ); ?>><?php echo esc_html( $l ); ?></option>
		<?php endforeach; ?>
	</select>
	<button class="ft-btn ft-btn-secondary" type="submit"><?php esc_html_e( 'Filter', 'fraud-tenant-core' ); ?></button>
</form>
<div class="ft-card"><div class="ft-table-wrap"><table class="ft-table">
	<thead><tr><th><?php esc_html_e( 'Name', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Email', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Role', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Status', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Registered', 'fraud-tenant-core' ); ?></th></tr></thead>
	<tbody>
	<?php foreach ( $q->get_results() as $u ) : ?>
		<tr>
			<td><a href="<?php echo esc_url( C::url( 'users', array( 'user_id' => $u->ID ) ) ); ?>"><?php echo esc_html( $u->display_name ); ?></a></td>
			<td><?php echo esc_html( $u->user_email ); ?></td>
			<td><?php echo esc_html( implode( ', ', array_map( static fn( $r ) => wp_roles()->roles[ $r ]['name'] ?? $r, (array) $u->roles ) ) ); ?></td>
			<td><?php echo C::badge( AuthService::get_user_account_status( $u->ID ) ); // phpcs:ignore ?></td>
			<td><?php echo C::date( $u->user_registered ); // phpcs:ignore ?></td>
		</tr>
	<?php endforeach; ?>
	<?php if ( ! $q->get_results() ) : ?><tr><td colspan="5"><?php C::empty_state( __( 'No users match these filters.', 'fraud-tenant-core' ) ); ?></td></tr><?php endif; ?>
	</tbody>
</table></div></div>
<?php C::pagination( 'users', $page, (int) $q->get_total(), $per, array( 'q' => $search, 'role' => $role, 'status' => $status ) ); ?>
