<?php
/**
 * Suite: Stripe Test-mode billing, webhooks, subscription lifecycle and membership enforcement.
 *
 * Runs against the isolated QA install. Uses GENUINE Stripe Test-mode objects (customers,
 * payment methods, subscriptions, checkout sessions) and GENUINE webhook delivery through the
 * Stripe CLI listener (stripe listen --forward-to QA). Negative signature cases use locally
 * signed fixtures over real HTTP.
 */
require __DIR__ . '/lib.php';

use FraudTenant\Services\CheckoutService;
use FraudTenant\Services\IncidentService;
use FraudTenant\Services\MembershipService;
use FraudTenant\Services\PropertyService;
use FraudTenant\Services\StripeCredentialService;
use FraudTenant\Services\StripeService;
use FraudTenant\Services\StripeWebhookService;
use FraudTenant\Utils\Constants;

global $wpdb;
echo "== Billing & Membership suite (QA DB: " . DB_NAME . ", Stripe mode: " . StripeCredentialService::get_active_mode() . ") ==\n";

$cleanup_subs = array();
$monthly      = MembershipService::get_plan_by_code( 'standard_monthly' );
$price_m      = StripeService::get_plan_price_id( $monthly, 'test' );

// ---------------------------------------------------------------- 1. Free-plan baseline
$owner_a  = t_make_user( 'owner' );
$owner_b  = t_make_user( 'owner' );
$tenant_t = t_make_user( 'tenant' );
$pending  = t_make_user( 'owner', 'pending_approval' );

t_assert( 'free' === MembershipService::get_owner_plan( $owner_a )->plan_code, 'MEM-01', 'New approved owner is on the Free plan' );
t_assert( 1 === MembershipService::get_property_limit( $owner_a ), 'MEM-02', 'Free property limit is 1' );
t_assert( false === MembershipService::can_access_feature( $owner_a, 'incident_reporting' ), 'MEM-03', 'Free owner has no incident_reporting entitlement' );
t_assert( false === IncidentService::can_submit_incident( $owner_a ), 'MEM-04', 'Free owner cannot submit incidents (D2 fix: gate on by default)' );
t_assert( false === MembershipService::is_eligible_owner( $tenant_t ), 'MEM-05', 'Tenant is never an eligible paid owner' );

$p1 = PropertyService::create_property( $owner_a, array( 'address_street' => '1 QA Way', 'city' => 'Austin', 'state' => 'TX', 'postal_code' => '78701', 'title' => 'QA One' ) );
t_assert( ! empty( $p1['success'] ), 'MEM-06', 'Free owner can create first active property' );
$p2 = PropertyService::create_property( $owner_a, array( 'address_street' => '2 QA Way', 'city' => 'Austin', 'state' => 'TX', 'postal_code' => '78701', 'title' => 'QA Two' ) );
t_assert( empty( $p2['success'] ) && 'property_quota_exceeded' === ( $p2['error'] ?? '' ), 'MEM-07', 'Free owner second active property rejected server-side (property_quota_exceeded)' );

// REST-level enforcement (direct API calls, not UI)
wp_set_current_user( $owner_a );
$wpdb->update( $wpdb->prefix . 'ft_properties', array( 'status' => 'verified' ), array( 'id' => (int) ( $p1['property_id'] ?? 0 ) ) );
$req = new WP_REST_Request( 'POST', '/fraud-tenant/v1/owner/incident-reports' );
$req->set_param( '_wpnonce', wp_create_nonce( 'wp_rest' ) );
$req->set_param( 'property_id', (int) ( $p1['property_id'] ?? 0 ) );
$req->set_param( 'category', 'unpaid_rent' );
$req->set_param( 'incident_date', gmdate( 'Y-m-d', time() - 7 * 86400 ) );
$req->set_param( 'amount_involved', '1200.00' );
$req->set_param( 'factual_description', 'Synthetic QA description of an unpaid rent period for entitlement testing only.' );
$req->set_param( 'owner_attestation', true );
$res = rest_do_request( $req );
t_assert( 403 === $res->get_status(), 'MEM-08', 'REST incident submission (valid payload, verified property) by Free owner -> 403 from the entitlement gate (got ' . $res->get_status() . ')' );
t_assert( 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}ft_incident_reports WHERE owner_id = %d", $owner_a ) ), 'MEM-09', 'No incident row created for Free owner' );

wp_set_current_user( $tenant_t );
$req = new WP_REST_Request( 'POST', '/fraud-tenant/v1/owner/membership/checkout' );
$req->set_param( '_wpnonce', wp_create_nonce( 'wp_rest' ) );
$req->set_param( 'plan_code', 'standard_monthly' );
t_assert( 403 === rest_do_request( $req )->get_status(), 'MEM-10', 'REST checkout by tenant -> 403' );
wp_set_current_user( $owner_a );
$req = new WP_REST_Request( 'POST', '/fraud-tenant/v1/owner/membership/checkout' );
$req->set_param( 'plan_code', 'standard_monthly' );
t_assert( 403 === rest_do_request( $req )->get_status(), 'MEM-11', 'REST checkout without nonce -> 403' );
wp_set_current_user( 0 );
t_assert( 401 === rest_do_request( $req )->get_status(), 'MEM-12', 'REST checkout unauthenticated -> 401' );
t_assert( 403 === ( CheckoutService::initiate_checkout( $pending, 'standard_monthly' )['status'] ?? 0 ), 'MEM-13', 'Unapproved owner cannot initiate checkout' );
t_assert( 400 === ( CheckoutService::initiate_checkout( $owner_a, 'free' )['status'] ?? 0 ), 'MEM-14', 'Free plan is not purchasable' );
t_assert( 400 === ( CheckoutService::initiate_checkout( $owner_a, 'enterprise_x' )['status'] ?? 0 ), 'MEM-15', 'Unknown plan code rejected' );

// ---------------------------------------------------------------- 2. Genuine Checkout Sessions & idempotency
$owner_d = t_make_user( 'owner' );
$c1      = CheckoutService::initiate_checkout( $owner_d, 'standard_monthly' );
t_assert( ! empty( $c1['success'] ) && str_starts_with( (string) ( $c1['checkout_url'] ?? '' ), 'https://checkout.stripe.com/' ), 'CHK-01', 'Genuine Test Checkout Session created (checkout.stripe.com URL)' );
$s1 = StripeService::get_checkout_session( (string) ( $c1['session_id'] ?? '' ), 'test' );
t_assert( $s1['success'] && false === (bool) $s1['data']['livemode'] && 'open' === $s1['data']['status'], 'CHK-02', 'Remote session is open and livemode=false' );
t_assert( (string) $owner_d === (string) ( $s1['data']['client_reference_id'] ?? '' ) && 'standard_monthly' === ( $s1['data']['metadata']['plan_code'] ?? '' ), 'CHK-03', 'Session bound to owner and plan' );
t_assert( 2900 === (int) ( $s1['data']['amount_total'] ?? 0 ) && 'usd' === ( $s1['data']['currency'] ?? '' ), 'CHK-04', 'Session amount is $29.00 USD' );
$c2 = CheckoutService::initiate_checkout( $owner_d, 'standard_monthly' );
t_assert( ! empty( $c2['reused'] ) && $c2['session_id'] === $c1['session_id'], 'CHK-05', 'Repeat request for same plan reuses the verified open session' );
$c3 = CheckoutService::initiate_checkout( $owner_d, 'standard_yearly' );
t_assert( ! empty( $c3['success'] ) && empty( $c3['reused'] ) && $c3['session_id'] !== $c1['session_id'], 'CHK-06', 'Different plan starts a new logical attempt/session' );
$s3 = StripeService::get_checkout_session( (string) $c3['session_id'], 'test' );
t_assert( 29000 === (int) ( $s3['data']['amount_total'] ?? 0 ), 'CHK-07', 'Yearly session amount is $290.00' );

// Expired remote session is never reused
t_stripe( 'POST', 'checkout/sessions/' . $c3['session_id'] . '/expire' );
$c4 = CheckoutService::initiate_checkout( $owner_d, 'standard_yearly' );
t_assert( ! empty( $c4['success'] ) && empty( $c4['reused'] ) && $c4['session_id'] !== $c3['session_id'], 'CHK-08', 'Remotely expired session is not reused; new session created' );

// Retry of the same logical attempt (unknown outcome) reuses its idempotency key
$att = 'att_' . bin2hex( random_bytes( 16 ) );
update_user_meta( $owner_d, CheckoutService::get_pending_session_meta_key( 'test' ), array( 'session_id' => '', 'plan_code' => 'standard_monthly', 'attempt_id' => $att, 'mode' => 'test', 'created_at' => time() ) );
$c5 = CheckoutService::initiate_checkout( $owner_d, 'standard_monthly' );
t_assert( ( $c5['attempt_id'] ?? '' ) === $att, 'CHK-09', 'Retry of an unresolved attempt reuses the persisted attempt id' );
update_user_meta( $owner_d, CheckoutService::get_pending_session_meta_key( 'test' ), array( 'session_id' => '', 'plan_code' => 'standard_monthly', 'attempt_id' => $att, 'mode' => 'test', 'created_at' => time() ) );
$c6 = CheckoutService::initiate_checkout( $owner_d, 'standard_monthly' );
t_assert( ( $c6['session_id'] ?? 'x' ) === ( $c5['session_id'] ?? 'y' ), 'CHK-10', 'Same attempt id -> Stripe idempotency returns the identical session' );
$c7 = CheckoutService::initiate_checkout( $owner_b, 'standard_monthly' );
t_assert( ( $c7['attempt_id'] ?? '' ) !== $att && ! empty( $c7['session_id'] ), 'CHK-11', 'A genuinely new purchase attempt gets a new attempt id' );

// Foreign session cannot be inspected by another owner
$v = CheckoutService::verify_checkout_return( $owner_b, (string) $c1['session_id'] );
t_assert( empty( $v['success'] ) && 'foreign_session_rejected' === ( $v['code'] ?? '' ), 'CHK-12', 'Owner B cannot verify/claim owner D\'s checkout session' );

// ---------------------------------------------------------------- 3. Genuine subscription + genuine webhook delivery (Stripe CLI)
$cust = StripeService::get_or_create_customer( $owner_a, 'test' );
t_assert( ! empty( $cust['success'] ) && str_starts_with( (string) $cust['customer_id'], 'cus_' ), 'SUB-01', 'Owner A has a mode-specific Test customer' );
$cus_a = (string) $cust['customer_id'];
$pm    = t_stripe( 'POST', 'payment_methods/pm_card_visa/attach', array( 'customer' => $cus_a ) );
t_stripe( 'POST', 'customers/' . $cus_a, array( 'invoice_settings' => array( 'default_payment_method' => $pm['data']['id'] ?? '' ) ) );
$sub = t_stripe(
	'POST',
	'subscriptions',
	array(
		'customer' => $cus_a,
		'items'    => array( array( 'price' => $price_m ) ),
		'metadata' => array( 'wp_user_id' => (string) $owner_a, 'plan_code' => 'standard_monthly' ),
	)
);
$sub_a = (string) ( $sub['data']['id'] ?? '' );
$cleanup_subs[] = $sub_a;
t_assert( 'active' === ( $sub['data']['status'] ?? '' ), 'SUB-02', 'Genuine Test subscription created and paid in Stripe (status active)' );
t_assert( 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}ft_subscriptions WHERE user_id = %d AND status = 'active' AND stripe_subscription_id <> %s", $owner_a, $sub_a ) ), 'SUB-03', 'Owner A had no other active subscription; any entitlement can only come from the synced Stripe subscription' );

$row = t_wait_until( static function () use ( $sub_a ) {
	$r = t_local_sub( $sub_a );
	return ( $r && 'active' === $r->status ) ? $r : null;
} );
t_assert( (bool) $row, 'SUB-04', 'Genuine Stripe webhook (via CLI) synchronized the subscription to active' );
t_assert( $row && 0 === (int) $row->livemode && $cus_a === $row->stripe_customer_id && (int) $row->plan_id === (int) $monthly->id, 'SUB-05', 'Local record: livemode=0, correct customer and plan' );
t_assert( $row && ! empty( $row->current_period_end ) && strtotime( $row->current_period_end ) > time() + 20 * 86400, 'SUB-06', 'Period end comes from Stripe (about 1 month ahead), not fabricated' );
t_assert( MembershipService::has_active_subscription( $owner_a ), 'SUB-07', 'Owner A now has an active Standard entitlement' );
t_assert( -1 === MembershipService::get_property_limit( $owner_a ), 'SUB-08', 'Standard property limit is unlimited (-1)' );
t_assert( IncidentService::can_submit_incident( $owner_a ), 'SUB-09', 'Standard owner may submit incidents' );
$p3 = PropertyService::create_property( $owner_a, array( 'address_street' => '3 QA Way', 'city' => 'Austin', 'state' => 'TX', 'postal_code' => '78701', 'title' => 'QA Three' ) );
t_assert( ! empty( $p3['success'] ), 'SUB-10', 'Standard owner can create additional active properties' );

$processed = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ft_payment_events WHERE status = 'processed'" );
t_assert( $processed >= 2, 'SUB-11', "Genuine events recorded as processed ({$processed})" );
$pii = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ft_payment_events WHERE payload_json LIKE '%@example.test%'" );
t_assert( 0 === $pii, 'SUB-12', 'Stored webhook payloads are minimized (no customer emails)' );

$paid = t_wait_until( static function () use ( $owner_a ) {
	return count( MembershipService::get_billing_history( $owner_a ) ) > 0;
} );
$hist = MembershipService::get_billing_history( $owner_a );
t_assert( $paid && 'paid' === ( $hist[0]['result'] ?? '' ) && 2900 === ( $hist[0]['amount'] ?? 0 ), 'SUB-13', 'Billing history shows the genuine $29.00 paid invoice' );

// ---------------------------------------------------------------- 4. Webhook security (signed fixtures over real HTTP)
$secret = t_qa_whsec();
$fake   = wp_json_encode( array( 'id' => 'evt_qa_' . t_uid(), 'object' => 'event', 'type' => 'customer.subscription.updated', 'livemode' => false, 'created' => time(), 'data' => array( 'object' => array( 'id' => $sub_a, 'object' => 'subscription', 'status' => 'canceled' ) ) ) );
t_assert( 400 === t_post_webhook( $fake, 'whsec_wrong' )['code'], 'WHK-01', 'Invalid signature rejected (400)' );
t_assert( 400 === t_post_webhook( $fake, $secret, time() - 600 )['code'], 'WHK-02', 'Stale timestamp (>300s) rejected (400)' );
t_assert( 400 === t_post_webhook( $fake, null, null, '' )['code'], 'WHK-03', 'Missing Stripe-Signature rejected (400)' );
t_assert( 400 === t_post_webhook( $fake, null, null, 't=' . time() )['code'], 'WHK-04', 'Header without v1 rejected (400)' );
$ts    = time();
$multi = 't=' . $ts . ',v1=' . str_repeat( 'a', 64 ) . ',v1=' . hash_hmac( 'sha256', $ts . '.' . $fake, $secret );
$r     = t_post_webhook( $fake, null, $ts, $multi );
t_assert( 200 === $r['code'], 'WHK-05', 'Multiple v1 signatures: valid one accepted' );
t_assert( 'active' === t_local_sub( $sub_a )->status, 'WHK-06', 'Forged "canceled" payload ignored: state re-fetched from Stripe stays active' );
$live_evt = wp_json_encode( array( 'id' => 'evt_qa_' . t_uid(), 'object' => 'event', 'type' => 'customer.subscription.updated', 'livemode' => true, 'created' => time(), 'data' => array( 'object' => array( 'id' => $sub_a ) ) ) );
t_assert( 400 === t_post_webhook( $live_evt, $secret )['code'], 'WHK-07', 'livemode=true event signed with Test secret rejected (no Live secret configured -> fail closed)' );
$tampered = wp_json_encode( array( 'id' => 'evt_qa_' . t_uid(), 'object' => 'event', 'type' => 'ping.qa', 'livemode' => false, 'created' => time(), 'data' => array( 'object' => array() ) ) );
$hdr      = 't=' . time() . ',v1=' . hash_hmac( 'sha256', time() . '.' . $tampered, $secret );
t_assert( 400 === t_post_webhook( $tampered . ' ', null, null, $hdr )['code'], 'WHK-08', 'Body altered after signing rejected (raw-body verification)' );

// Duplicate genuine event -> already_processed
$evt_id = (string) $wpdb->get_var( "SELECT event_id FROM {$wpdb->prefix}ft_payment_events WHERE status = 'processed' AND event_id NOT LIKE 'evt_qa_%' ORDER BY id ASC LIMIT 1" );
$genuine = t_stripe( 'GET', 'events/' . $evt_id );
$raw     = wp_json_encode( $genuine['data'] ?? array() );
$dup     = t_post_webhook( $raw, $secret );
t_assert( 200 === $dup['code'] && 'already_processed' === ( $dup['body']['status'] ?? '' ), 'WHK-09', 'Duplicate delivery of a genuine event is idempotent (already_processed)' );

// Stale 'processing' reclaim & in-progress conflict
$stale_id = 'evt_qa_stale_' . t_uid();
$wpdb->insert( $wpdb->prefix . 'ft_payment_events', array( 'event_id' => $stale_id, 'event_type' => 'ping.qa', 'livemode' => 0, 'status' => 'processing', 'attempt_count' => 1, 'received_at' => gmdate( 'Y-m-d H:i:s', time() - 900 ), 'locked_at' => gmdate( 'Y-m-d H:i:s', time() - 900 ), 'processed_at' => gmdate( 'Y-m-d H:i:s', time() - 900 ) ) );
$r = t_post_webhook( wp_json_encode( array( 'id' => $stale_id, 'object' => 'event', 'type' => 'ping.qa', 'livemode' => false, 'created' => time(), 'data' => array( 'object' => array() ) ) ), $secret );
t_assert( 200 === $r['code'] && 'processed' === $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$wpdb->prefix}ft_payment_events WHERE event_id = %s", $stale_id ) ), 'WHK-10', 'Event stuck in processing >5 min is reclaimed and completed' );
$busy_id = 'evt_qa_busy_' . t_uid();
$wpdb->insert( $wpdb->prefix . 'ft_payment_events', array( 'event_id' => $busy_id, 'event_type' => 'ping.qa', 'livemode' => 0, 'status' => 'processing', 'attempt_count' => 1, 'received_at' => current_time( 'mysql' ), 'locked_at' => current_time( 'mysql' ), 'processed_at' => current_time( 'mysql' ) ) );
$r = t_post_webhook( wp_json_encode( array( 'id' => $busy_id, 'object' => 'event', 'type' => 'ping.qa', 'livemode' => false, 'created' => time(), 'data' => array( 'object' => array() ) ) ), $secret );
t_assert( 409 === $r['code'], 'WHK-11', 'Event currently processing elsewhere returns 409 (Stripe retries later)' );

// ---------------------------------------------------------------- 5. Mode isolation
update_option( Constants::OPTION_STRIPE_ACTIVE_MODE, 'live' );
wp_cache_flush();
t_assert( false === MembershipService::has_active_subscription( $owner_a ), 'ISO-01', 'With Live mode active, Test-mode subscription grants NO entitlement' );
t_assert( 1 === MembershipService::get_property_limit( $owner_a ), 'ISO-02', 'Live mode: Test subscriber falls back to Free limits' );
update_option( Constants::OPTION_STRIPE_ACTIVE_MODE, 'test' );
wp_cache_flush();
t_assert( MembershipService::has_active_subscription( $owner_a ), 'ISO-03', 'Back in Test mode, Test subscription state is intact' );
t_assert( null === StripeService::get_customer_id( $owner_a, 'live' ), 'ISO-04', 'No Live customer mapping was created for the Test customer' );

// ---------------------------------------------------------------- 6. Cross-owner attack: subscription metadata points at B but customer is A's
$rogue = t_stripe( 'POST', 'subscriptions', array( 'customer' => $cus_a, 'items' => array( array( 'price' => $price_m ) ), 'metadata' => array( 'wp_user_id' => (string) $owner_b ) ) );
$rogue_id = (string) ( $rogue['data']['id'] ?? '' );
$cleanup_subs[] = $rogue_id;
$failed = t_wait_until( static function () use ( $wpdb, $rogue_id ) {
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}ft_payment_events WHERE status = 'failed' AND payload_json LIKE %s", '%' . $wpdb->esc_like( $rogue_id ) . '%' ) );
}, 40 );
t_assert( $failed > 0, 'XOWN-01', 'Webhook for a subscription whose customer is not the claimed owner\'s is FAILED (fail closed)' );
t_assert( false === MembershipService::has_active_subscription( $owner_b ), 'XOWN-02', 'Owner B gains no entitlement from owner A\'s customer' );
t_assert( null === t_local_sub( $rogue_id ), 'XOWN-03', 'No local subscription row was written for the rogue subscription' );
t_stripe( 'DELETE', 'subscriptions/' . $rogue_id );

// ---------------------------------------------------------------- 7. Cancellation at period end, then immediate cancellation
t_stripe( 'POST', 'subscriptions/' . $sub_a, array( 'cancel_at_period_end' => 'true' ) );
$c = t_wait_until( static function () use ( $sub_a ) {
	$r = t_local_sub( $sub_a );
	return ( $r && 1 === (int) $r->cancel_at_period_end ) ? $r : null;
} );
t_assert( (bool) $c, 'LIFE-01', 'cancel_at_period_end synchronized via genuine webhook' );
t_assert( MembershipService::has_active_subscription( $owner_a ), 'LIFE-02', 'Access retained until verified period end' );
t_stripe( 'DELETE', 'subscriptions/' . $sub_a );
$d = t_wait_until( static function () use ( $sub_a ) {
	$r = t_local_sub( $sub_a );
	return ( $r && 'canceled' === $r->status ) ? $r : null;
} );
t_assert( (bool) $d, 'LIFE-03', 'customer.subscription.deleted synchronized: status canceled' );
t_assert( false === MembershipService::has_active_subscription( $owner_a ), 'LIFE-04', 'Canceled subscription revokes Standard entitlement' );
t_assert( 1 === MembershipService::get_property_limit( $owner_a ) && false === IncidentService::can_submit_incident( $owner_a ), 'LIFE-05', 'Downgraded owner: Free limit and no incident reporting' );
t_assert( 2 === PropertyService::count_active_properties( $owner_a ), 'LIFE-06', 'Downgrade does not delete/archive existing properties (2 remain active)' );
$p4 = PropertyService::create_property( $owner_a, array( 'address_street' => '4 QA Way', 'city' => 'Austin', 'state' => 'TX', 'postal_code' => '78701', 'title' => 'QA Four' ) );
t_assert( empty( $p4['success'] ), 'LIFE-07', 'Downgraded owner over quota cannot add more properties' );
t_assert( 1 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}ft_subscriptions WHERE stripe_subscription_id = %s", $sub_a ) ), 'LIFE-08', 'Subscription history row preserved (not deleted)' );

// Out-of-order / stale payload: a signed "active" update for the canceled sub cannot resurrect it
$ooo = wp_json_encode( array( 'id' => 'evt_qa_ooo_' . t_uid(), 'object' => 'event', 'type' => 'customer.subscription.updated', 'livemode' => false, 'created' => time() - 3600, 'data' => array( 'object' => array( 'id' => $sub_a, 'object' => 'subscription', 'status' => 'active', 'current_period_end' => time() + 9999999 ) ) ) );
t_post_webhook( $ooo, $secret );
t_assert( 'canceled' === t_local_sub( $sub_a )->status && ! MembershipService::has_active_subscription( $owner_a ), 'LIFE-09', 'Out-of-order stale "active" event does not resurrect a canceled subscription' );

// ---------------------------------------------------------------- 8. Payment failure: never grants entitlement
$owner_c = t_make_user( 'owner' );
$cus_c   = (string) StripeService::get_or_create_customer( $owner_c, 'test' )['customer_id'];
$pmf     = t_stripe( 'POST', 'payment_methods/pm_card_chargeCustomerFail/attach', array( 'customer' => $cus_c ) );
$subf    = t_stripe( 'POST', 'subscriptions', array( 'customer' => $cus_c, 'items' => array( array( 'price' => $price_m ) ), 'default_payment_method' => $pmf['data']['id'] ?? '', 'payment_behavior' => 'allow_incomplete', 'metadata' => array( 'wp_user_id' => (string) $owner_c ) ) );
$sub_c   = (string) ( $subf['data']['id'] ?? '' );
$cleanup_subs[] = $sub_c;
t_assert( 'incomplete' === ( $subf['data']['status'] ?? '' ), 'FAIL-01', 'Genuine declined first payment -> Stripe status incomplete' );
$rc = t_wait_until( static function () use ( $sub_c ) {
	return t_local_sub( $sub_c );
} );
t_assert( $rc && 'incomplete' === $rc->status, 'FAIL-02', 'Webhook synchronized incomplete status' );
t_assert( false === MembershipService::has_active_subscription( $owner_c ), 'FAIL-03', 'Incomplete subscription grants no entitlement' );
$failed_hist = t_wait_until( static function () use ( $owner_c ) {
	foreach ( MembershipService::get_billing_history( $owner_c ) as $h ) {
		if ( 'failed' === $h['result'] ) {
			return true;
		}
	}
	return false;
} );
t_assert( (bool) $failed_hist, 'FAIL-04', 'invoice.payment_failed recorded in owner billing history' );
$dup_chk = CheckoutService::initiate_checkout( $owner_c, 'standard_monthly' );
t_assert( ! empty( $dup_chk['success'] ), 'FAIL-05', 'Owner with a failed/incomplete payment can start a fresh checkout' );

// ---------------------------------------------------------------- 9. Active subscriber: duplicate checkout blocked; Customer Portal
$owner_e = t_make_user( 'owner' );
$cus_e   = (string) StripeService::get_or_create_customer( $owner_e, 'test' )['customer_id'];
$pme     = t_stripe( 'POST', 'payment_methods/pm_card_visa/attach', array( 'customer' => $cus_e ) );
$sube    = t_stripe( 'POST', 'subscriptions', array( 'customer' => $cus_e, 'items' => array( array( 'price' => StripeService::get_plan_price_id( MembershipService::get_plan_by_code( 'standard_yearly' ), 'test' ) ) ), 'default_payment_method' => $pme['data']['id'] ?? '', 'metadata' => array( 'wp_user_id' => (string) $owner_e ) ) );
$sub_e   = (string) ( $sube['data']['id'] ?? '' );
$cleanup_subs[] = $sub_e;
t_wait_until( static function () use ( $owner_e ) {
	return MembershipService::has_active_subscription( $owner_e );
} );
t_assert( 'standard_yearly' === MembershipService::get_owner_plan( $owner_e )->plan_code, 'YR-01', 'Genuine yearly subscription maps to standard_yearly' );
t_assert( 409 === ( CheckoutService::initiate_checkout( $owner_e, 'standard_monthly' )['status'] ?? 0 ), 'YR-02', 'Active subscriber cannot start a duplicate checkout (409)' );
$portal = CheckoutService::initiate_customer_portal( $owner_e );
t_assert( ! empty( $portal['success'] ) && str_contains( (string) ( $portal['portal_url'] ?? '' ), 'billing.stripe.com' ), 'PORTAL-01', 'Customer Portal session created for owner\'s own Test customer' . ( empty( $portal['success'] ) ? ' [Stripe said: ' . ( $portal['error'] ?? '' ) . ']' : '' ) );
t_assert( empty( CheckoutService::initiate_customer_portal( $tenant_t )['success'] ), 'PORTAL-02', 'Tenant cannot open a billing portal' );
$owner_f = t_make_user( 'owner' );
t_assert( 'no_stripe_customer' === ( CheckoutService::initiate_customer_portal( $owner_f )['code'] ?? '' ), 'PORTAL-03', 'Owner without a Stripe customer gets a clear portal error' );

// Admin manual sync uses the same verified path
$e_row = t_local_sub( $sub_e );
$synced = StripeWebhookService::sync_subscription( $sub_e, 'test', 'qa.manual' );
t_assert( 'active' === $synced['status'] && $owner_e === $synced['user_id'], 'SYNC-01', 'Manual sync re-verifies owner/customer/price and returns active' );

// ---------------------------------------------------------------- 10. Missed-webhook safety net (hourly reconciliation)
// $sub_a is genuinely canceled in Stripe (LIFE-03). Simulate a missed deletion webhook.
$wpdb->update( $wpdb->prefix . 'ft_subscriptions', array( 'status' => 'active', 'updated_at' => gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 8 * HOUR_IN_SECONDS ) ), array( 'stripe_subscription_id' => $sub_a ) );
wp_cache_flush();
$rc = \FraudTenant\Services\TenantScreeningService::reconcile_subscriptions( 200 );
t_assert( 'canceled' === t_local_sub( $sub_a )->status, 'RECON-01', 'Reconciliation corrects a subscription whose cancellation webhook was missed' );
t_assert( false === MembershipService::has_active_subscription( $owner_a ), 'RECON-02', 'Access is revoked after reconciliation' );
t_assert( $rc['checked'] >= 1 && $rc['changed'] >= 1, 'RECON-03', "Reconciliation report: checked {$rc['checked']}, changed {$rc['changed']}, failed {$rc['failed']}" );
$fresh = t_local_sub( $sub_e );
$rc2   = \FraudTenant\Services\TenantScreeningService::reconcile_subscriptions( 200 );
t_assert( 'active' === t_local_sub( $sub_e )->status && $fresh->updated_at === t_local_sub( $sub_e )->updated_at, 'RECON-04', 'Recently synced active subscriptions are left untouched' );
t_assert( (bool) wp_next_scheduled( 'ft_reconcile_subscriptions' ), 'RECON-05', 'Hourly reconciliation job is scheduled' );

// ---------------------------------------------------------------- cleanup (Stripe Test objects only; QA DB is disposable)
foreach ( array_unique( array_filter( $cleanup_subs ) ) as $sid ) {
	t_stripe( 'DELETE', 'subscriptions/' . $sid );
}
t_summary( 'Billing & Membership' );
