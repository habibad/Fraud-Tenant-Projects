<?php
/**
 * QA cleanup (CLI): cancel still-active Stripe TEST subscriptions created for QA owners.
 * Touches only the QA install's Test-mode objects. Local QA rows are kept as history.
 */
require dirname( __DIR__ ) . '/lib.php';
global $wpdb;
$ids = $wpdb->get_col( "SELECT stripe_subscription_id FROM {$wpdb->prefix}ft_subscriptions WHERE livemode = 0 AND stripe_subscription_id NOT LIKE 'sub\_qa\_%' AND status IN ('active','trialing','past_due','incomplete','unpaid')" );
$n   = 0;
foreach ( $ids as $sid ) {
	$r = t_stripe( 'DELETE', 'subscriptions/' . $sid );
	if ( ! empty( $r['success'] ) ) {
		$n++;
	}
}
echo "Canceled {$n} of " . count( $ids ) . " QA Test subscriptions in Stripe.\n";
