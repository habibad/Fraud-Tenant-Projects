<?php
/**
 * Owner — Membership & Billing. Hosted Stripe Checkout / Customer Portal; the plan becomes active
 * only after Stripe confirms it (verified return or signed webhook), never because of a URL.
 *
 * @package FraudTenantTheme
 */

defined( 'ABSPATH' ) || exit;

use FraudTenant\Services\CheckoutService;
use FraudTenant\Services\MembershipService;
use FraudTenant\Services\StripeCredentialService;
use FraudTenant\Services\StripeService;

$owner_id = $args['owner_id'];
$m        = $args['data']['membership'];
$status   = sanitize_key( (string) ( $_GET['checkout_status'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
$session  = sanitize_text_field( (string) ( $_GET['session_id'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification

if ( 'success' === $status && preg_match( '/^cs_[A-Za-z0-9_]+$/', $session ) ) {
	$ret = CheckoutService::verify_checkout_return( $owner_id, $session );
	if ( ! empty( $ret['success'] ) && 'active' === ( $ret['status'] ?? '' ) ) {
		echo '<div class="ft-alert is-success" role="status"><strong>' . esc_html__( 'Membership active.', 'fraud-tenant-theme' ) . '</strong> ' . esc_html__( 'Your payment was confirmed with Stripe. Standard features are now available.', 'fraud-tenant-theme' ) . '</div>';
		$m = MembershipService::get_effective_membership_status( $owner_id );
	} else {
		echo '<div class="ft-alert is-info" role="status">' . esc_html__( 'Your payment is being confirmed with Stripe. This page will show your new plan as soon as Stripe confirms it.', 'fraud-tenant-theme' ) . '</div>';
	}
} elseif ( 'cancelled' === $status ) {
	echo '<div class="ft-alert is-info" role="status">' . esc_html__( 'Checkout was cancelled. No charge was made.', 'fraud-tenant-theme' ) . '</div>';
}

$paid      = ! empty( $m['has_active_subscription'] );
$mode      = StripeCredentialService::get_active_mode();
$customer  = StripeService::get_customer_id( $owner_id, $mode );
$history   = MembershipService::get_billing_history( $owner_id, 25 );
$plans     = array();
foreach ( MembershipService::get_plans( true ) as $pl ) {
	$plans[ $pl->plan_code ] = $pl;
}
$limit_txt = -1 === (int) $m['property_limit'] ? __( 'Unlimited properties', 'fraud-tenant-theme' ) : sprintf( /* translators: %d: limit */ _n( '%d active property', '%d active properties', (int) $m['property_limit'], 'fraud-tenant-theme' ), (int) $m['property_limit'] );
?>
<header class="ft-page-head"><div><h1><?php esc_html_e( 'Membership & billing', 'fraud-tenant-theme' ); ?></h1><p><?php esc_html_e( 'Payments are processed securely by Stripe. Fraud Tenant never sees or stores your card details.', 'fraud-tenant-theme' ); ?></p></div><?php if ( 'test' === $mode ) : ?><?php echo ft_badge( 'warning', __( 'Stripe test mode', 'fraud-tenant-theme' ) ); // phpcs:ignore ?><?php endif; ?></header>

<div data-ft-billing data-rest-url="<?php echo esc_url( rest_url( 'fraud-tenant/v1' ) ); ?>" data-rest-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">
	<div class="ft-alert is-error" data-ft-billing-notice hidden role="alert"></div>

	<section class="ft-card is-current">
		<div class="ft-card-head"><h2><?php esc_html_e( 'Current plan', 'fraud-tenant-theme' ); ?></h2><?php echo ft_badge( $paid ? 'active' : 'neutral', $paid ? __( 'Active', 'fraud-tenant-theme' ) : __( 'Free', 'fraud-tenant-theme' ) ); // phpcs:ignore ?></div>
		<dl class="ft-dl">
			<dt><?php esc_html_e( 'Plan', 'fraud-tenant-theme' ); ?></dt><dd><strong><?php echo esc_html( $m['plan_name'] ); ?></strong></dd>
			<dt><?php esc_html_e( 'Includes', 'fraud-tenant-theme' ); ?></dt><dd><?php echo esc_html( $limit_txt ); ?> · <?php echo ! empty( $m['features']['full_reports'] ) ? esc_html__( 'Full screening details', 'fraud-tenant-theme' ) : esc_html__( 'Limited screening results', 'fraud-tenant-theme' ); ?> · <?php echo ! empty( $m['features']['incident_reporting'] ) ? esc_html__( 'Incident reporting', 'fraud-tenant-theme' ) : esc_html__( 'No incident reporting', 'fraud-tenant-theme' ); ?></dd>
			<?php if ( $paid ) : ?>
				<dt><?php echo $m['cancel_at_period_end'] ? esc_html__( 'Access ends', 'fraud-tenant-theme' ) : esc_html__( 'Renews', 'fraud-tenant-theme' ); ?></dt><dd><?php echo ft_date( $m['current_period_end'] ); // phpcs:ignore ?><?php if ( $m['cancel_at_period_end'] ) : ?> <?php echo ft_badge( 'pending', __( 'Cancels at period end', 'fraud-tenant-theme' ) ); // phpcs:ignore ?><?php endif; ?></dd>
			<?php endif; ?>
		</dl>
		<?php if ( $customer ) : ?>
			<button type="button" class="ft-btn ft-btn-secondary" data-ft-portal data-busy="<?php esc_attr_e( 'Opening Stripe…', 'fraud-tenant-theme' ); ?>"><?php esc_html_e( 'Manage billing in Stripe', 'fraud-tenant-theme' ); ?></button>
			<p class="ft-help"><?php esc_html_e( 'Update your card, download invoices, switch interval or cancel. Cancelling keeps Standard features until the end of the paid period; your properties and reports are never deleted.', 'fraud-tenant-theme' ); ?></p>
		<?php endif; ?>
	</section>

	<?php if ( ! $paid ) : ?>
	<div class="ft-grid ft-grid-3">
		<section class="ft-card ft-price">
			<h2><?php esc_html_e( 'Free', 'fraud-tenant-theme' ); ?></h2>
			<div class="ft-price-amount">$0 <small>/ <?php esc_html_e( 'forever', 'fraud-tenant-theme' ); ?></small></div>
			<ul><li><?php esc_html_e( '1 active property', 'fraud-tenant-theme' ); ?></li><li><?php esc_html_e( 'Tenant search: whether reviewed records exist', 'fraud-tenant-theme' ); ?></li><li><?php esc_html_e( 'No incident reporting', 'fraud-tenant-theme' ); ?></li></ul>
			<span class="ft-badge is-neutral"><?php esc_html_e( 'Your current plan', 'fraud-tenant-theme' ); ?></span>
		</section>
		<?php foreach ( array( 'standard_monthly' => __( '/ month', 'fraud-tenant-theme' ), 'standard_yearly' => __( '/ year', 'fraud-tenant-theme' ) ) as $code => $per ) : if ( empty( $plans[ $code ] ) ) { continue; } $pl = $plans[ $code ]; ?>
			<section class="ft-card ft-price">
				<h2><?php echo esc_html( $pl->name ); ?></h2>
				<div class="ft-price-amount">$<?php echo esc_html( number_format_i18n( (float) $pl->price_amount, 0 ) ); ?> <small><?php echo esc_html( $per ); ?></small></div>
				<ul><li><?php esc_html_e( 'Unlimited properties', 'fraud-tenant-theme' ); ?></li><li><?php esc_html_e( 'Full reviewed screening details', 'fraud-tenant-theme' ); ?></li><li><?php esc_html_e( 'Submit incident reports', 'fraud-tenant-theme' ); ?></li></ul>
				<button type="button" class="ft-btn ft-btn-primary ft-btn-block" data-ft-checkout="<?php echo esc_attr( $code ); ?>" data-busy="<?php esc_attr_e( 'Connecting to Stripe…', 'fraud-tenant-theme' ); ?>"><?php esc_html_e( 'Upgrade', 'fraud-tenant-theme' ); ?></button>
			</section>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>
</div>

<section class="ft-card">
	<div class="ft-card-head"><h2><?php esc_html_e( 'Billing history', 'fraud-tenant-theme' ); ?></h2></div>
	<?php if ( ! $history ) : ft_empty( __( 'No invoices yet.', 'fraud-tenant-theme' ) ); else : ?>
		<div class="ft-table-wrap"><table class="ft-table ft-stack">
			<thead><tr><th><?php esc_html_e( 'Date', 'fraud-tenant-theme' ); ?></th><th><?php esc_html_e( 'Result', 'fraud-tenant-theme' ); ?></th><th><?php esc_html_e( 'Amount', 'fraud-tenant-theme' ); ?></th></tr></thead>
			<tbody><?php foreach ( $history as $h ) : ?>
				<tr><td data-label="<?php esc_attr_e( 'Date', 'fraud-tenant-theme' ); ?>"><?php echo ft_date( $h['date'] ); // phpcs:ignore ?></td><td data-label="<?php esc_attr_e( 'Result', 'fraud-tenant-theme' ); ?>"><?php echo ft_badge( 'paid' === $h['result'] ? 'success' : 'danger', 'paid' === $h['result'] ? __( 'Paid', 'fraud-tenant-theme' ) : __( 'Payment failed', 'fraud-tenant-theme' ) ); // phpcs:ignore ?></td><td data-label="<?php esc_attr_e( 'Amount', 'fraud-tenant-theme' ); ?>"><?php echo esc_html( number_format_i18n( $h['amount'] / 100, 2 ) . ' ' . $h['currency'] ); ?></td></tr>
			<?php endforeach; ?></tbody>
		</table></div>
	<?php endif; ?>
</section>
