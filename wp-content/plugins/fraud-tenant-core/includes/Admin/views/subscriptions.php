<?php
/**
 * Subscriptions & Billing — owner subscriptions (mode/status filters, verified Stripe re-sync)
 * and the webhook event log (sanitized, no payload contents).
 *
 * @package FraudTenant\Admin
 */

defined( 'ABSPATH' ) || exit;

use FraudTenant\Admin\AdminConsole as C;

global $wpdb;
$p      = $wpdb->prefix;
$mode   = C::get( 'mode', 'all' );
$status = C::get( 'status', 'all' );
$page   = max( 1, absint( C::get( 'pg', '1' ) ) );
$where  = array( '1=1' );
$params = array();
if ( in_array( $mode, array( 'test', 'live' ), true ) ) {
	$where[]  = 's.livemode = %d';
	$params[] = 'live' === $mode ? 1 : 0;
}
if ( 'all' !== $status ) {
	$where[]  = 's.status = %s';
	$params[] = sanitize_key( $status );
}
$w     = implode( ' AND ', $where );
$total = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$p}ft_subscriptions s WHERE {$w}", $params ) ) : $wpdb->get_var( "SELECT COUNT(*) FROM {$p}ft_subscriptions s WHERE {$w}" ) );
$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT s.*, pl.name AS plan_name, u.display_name, u.user_email FROM {$p}ft_subscriptions s LEFT JOIN {$p}ft_membership_plans pl ON pl.id = s.plan_id LEFT JOIN {$wpdb->users} u ON u.ID = s.user_id WHERE {$w} ORDER BY s.id DESC LIMIT %d OFFSET %d", array_merge( $params, array( 25, ( $page - 1 ) * 25 ) ) ) ) ?: array();
$events = $wpdb->get_results( "SELECT event_type, livemode, status, attempt_count, error_message, received_at, user_id FROM {$p}ft_payment_events ORDER BY id DESC LIMIT 25" ) ?: array();

C::head( __( 'Subscriptions & Billing', 'fraud-tenant-core' ), __( 'Subscription state is set only by verified Stripe webhooks or a verified re-sync — never by the checkout success page.', 'fraud-tenant-core' ) );
C::pills( 'subscriptions', 'mode', array( 'all' => __( 'All modes', 'fraud-tenant-core' ), 'test' => __( 'Test', 'fraud-tenant-core' ), 'live' => __( 'Live', 'fraud-tenant-core' ) ), $mode, array( 'status' => $status ) );
C::pills( 'subscriptions', 'status', array( 'all' => __( 'All statuses', 'fraud-tenant-core' ), 'active' => __( 'Active', 'fraud-tenant-core' ), 'past_due' => __( 'Past due', 'fraud-tenant-core' ), 'canceled' => __( 'Canceled', 'fraud-tenant-core' ), 'incomplete' => __( 'Incomplete', 'fraud-tenant-core' ) ), $status, array( 'mode' => $mode ) );
?>
<div class="ft-card"><div class="ft-table-wrap"><table class="ft-table">
	<thead><tr><th><?php esc_html_e( 'Owner', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Plan', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Status', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Mode', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Period ends', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Actions', 'fraud-tenant-core' ); ?></th></tr></thead>
	<tbody>
	<?php foreach ( $rows as $s ) : ?>
		<tr>
			<td><a href="<?php echo esc_url( C::url( 'users', array( 'user_id' => (int) $s->user_id ) ) ); ?>"><?php echo esc_html( (string) $s->display_name ); ?></a><br><span class="ft-muted"><?php echo esc_html( (string) $s->user_email ); ?></span></td>
			<td><?php echo esc_html( (string) $s->plan_name ); ?></td>
			<td><?php echo C::badge( (string) $s->status ); // phpcs:ignore ?><?php echo $s->cancel_at_period_end ? ' ' . C::badge( 'pending', __( 'Cancels at period end', 'fraud-tenant-core' ) ) : ''; // phpcs:ignore ?></td>
			<td><?php echo $s->livemode ? C::badge( 'active', 'Live' ) : C::badge( 'info', 'Test' ); // phpcs:ignore ?></td>
			<td><?php echo C::date( $s->current_period_end ); // phpcs:ignore ?></td>
			<td><?php if ( preg_match( '/^sub_[A-Za-z0-9]+$/', (string) $s->stripe_subscription_id ) ) : ?><?php C::form( 'subscription_sync', array( 'subscription_id' => (int) $s->id ), 'style="display:inline"' ); ?><button class="ft-btn ft-btn-secondary ft-btn-sm" type="submit"><?php esc_html_e( 'Sync from Stripe', 'fraud-tenant-core' ); ?></button></form><?php endif; ?></td>
		</tr>
	<?php endforeach; ?>
	<?php if ( ! $rows ) : ?><tr><td colspan="6"><?php C::empty_state( __( 'No subscriptions in this list.', 'fraud-tenant-core' ) ); ?></td></tr><?php endif; ?>
	</tbody>
</table></div></div>
<?php C::pagination( 'subscriptions', $page, $total, 25, array( 'mode' => $mode, 'status' => $status ) ); ?>

<section class="ft-card">
	<div class="ft-card-head"><h2><?php esc_html_e( 'Recent Stripe webhook events', 'fraud-tenant-core' ); ?></h2></div>
	<div class="ft-table-wrap"><table class="ft-table">
		<thead><tr><th><?php esc_html_e( 'Event', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Mode', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Status', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Attempts', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Received', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Error', 'fraud-tenant-core' ); ?></th></tr></thead>
		<tbody>
		<?php foreach ( $events as $e ) : ?>
			<tr><td class="ft-mono"><?php echo esc_html( (string) $e->event_type ); ?></td><td><?php echo $e->livemode ? 'Live' : 'Test'; ?></td><td><?php echo C::badge( (string) $e->status ); // phpcs:ignore ?></td><td><?php echo (int) $e->attempt_count; ?></td><td><?php echo C::date( $e->received_at, true ); // phpcs:ignore ?></td><td class="ft-muted"><?php echo esc_html( wp_trim_words( (string) $e->error_message, 12 ) ); ?></td></tr>
		<?php endforeach; ?>
		<?php if ( ! $events ) : ?><tr><td colspan="6"><?php C::empty_state( __( 'No webhook events received yet.', 'fraud-tenant-core' ) ); ?></td></tr><?php endif; ?>
		</tbody>
	</table></div>
</section>
