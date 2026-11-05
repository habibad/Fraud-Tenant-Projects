<?php
/**
 * Fixtures for the identity-screening browser journey (QA install, synthetic people only).
 * Owner A (Standard) files; Owner C (Standard) and Owner B (Free) search.
 */
require dirname( __DIR__ ) . '/lib.php';
ob_end_clean();
global $wpdb;

function fx_standard( int $uid ): void {
	global $wpdb;
	$plan = $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}ft_membership_plans WHERE plan_code = 'standard_monthly'" );
	$wpdb->insert( $wpdb->prefix . 'ft_subscriptions', array( 'user_id' => $uid, 'plan_id' => $plan, 'stripe_customer_id' => 'cus_qa_fixture', 'stripe_subscription_id' => 'sub_qa_' . t_uid(), 'status' => 'active', 'livemode' => 0, 'current_period_start' => gmdate( 'Y-m-d H:i:s' ), 'current_period_end' => gmdate( 'Y-m-d H:i:s', time() + 20 * 86400 ), 'cancel_at_period_end' => 0, 'created_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ) );
}

$pw = 'Idj!' . bin2hex( random_bytes( 5 ) );
$a  = t_make_user( 'owner', 'approved', $pw );
$b  = t_make_user( 'owner', 'approved', $pw );
$c  = t_make_user( 'owner', 'approved', $pw );
fx_standard( $a );
fx_standard( $c );
t_make_property( $a );
t_make_property( $b );
t_make_property( $c );

if ( ! \FraudTenant\Services\ComplianceService::owner_search_ready() ) {
	$admin = (int) json_decode( (string) file_get_contents( 'C:/Users/Md Anikur Rahaman/ft_qa_secrets/qa_test_admin.json' ), true )['id'];
	\FraudTenant\Services\ComplianceService::set_gate( 'owner_disclosure', true, $admin, 'QA synthetic environment only — no real people or data.' );
}
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_%ft\\_login\\_fails%'" );

$area = (string) wp_rand( 100, 665 );
file_put_contents(
	'C:/Users/Md Anikur Rahaman/ft_qa_secrets/identity_fixtures.json',
	wp_json_encode(
		array(
			'password' => $pw,
			'owner_a'  => get_userdata( $a )->user_email,
			'owner_b'  => get_userdata( $b )->user_email,
			'owner_c'  => get_userdata( $c )->user_email,
			'first'    => 'Morgan',
			'last'     => 'Delacroix',
			'ssn'      => $area . '-' . sprintf( '%02d', wp_rand( 10, 99 ) ) . '-' . sprintf( '%04d', wp_rand( 1000, 9999 ) ),
		)
	)
);
echo "identity fixtures ready\n";
