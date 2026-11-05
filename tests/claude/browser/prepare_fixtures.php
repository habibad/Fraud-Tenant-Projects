<?php
/**
 * Prepare browser-journey fixtures in the QA install (synthetic data only).
 * Writes credentials to the private QA secrets directory (outside the web root).
 */
require dirname( __DIR__ ) . '/lib.php';
ob_end_clean();
$pw    = 'Br0wser!' . bin2hex( random_bytes( 4 ) );
$owner = t_make_user( 'owner', 'approved', $pw );
$u     = get_userdata( $owner );
// Clear login/registration rate limits so journeys are deterministic.
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_%ft\\_login\\_fails%' OR option_name LIKE '\\_transient\\_%ft\\_pw\\_reset\\_%' OR option_name LIKE '\\_transient\\_%ft\\_reg\\_%'" );
file_put_contents(
	'C:/Users/Md Anikur Rahaman/ft_qa_secrets/browser_fixtures.json',
	wp_json_encode(
		array(
			'owner_email'    => $u->user_email,
			'owner_password' => $pw,
			'owner_id'       => $owner,
			'tenant_email'   => 'qa-browser-tenant-' . t_uid() . '@example.test',
			'tenant_email2'  => 'qa-browser-tenant-new-' . t_uid() . '@example.test',
			'tenant_password'=> 'Tn!' . bin2hex( random_bytes( 5 ) ),
			'tenant_password2'=> 'Tn2!' . bin2hex( random_bytes( 5 ) ),
		)
	)
);
echo "fixtures ready (owner #{$owner})\n";
