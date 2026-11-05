<?php
/**
 * QA tool (CLI only): obtain the genuine Stripe CLI webhook signing secret for the QA
 * installation's Test-mode account and store it (encrypted) in the QA Stripe settings.
 * Secrets are passed to the CLI through the environment and are never printed.
 *
 * Usage: php stripe_cli_setup.php [secret|listen]
 */
if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}
$_SERVER['HTTP_HOST']   = 'localhost';
$_SERVER['REQUEST_URI'] = '/fraudtenant-qa/';
require 'C:/xampp/htdocs/fraudtenant-qa/wp-load.php';
if ( DB_NAME !== 'fraudtenant_qa' ) {
	fwrite( STDERR, "Refusing: not the QA database.\n" );
	exit( 1 );
}

$cli  = 'C:\\Users\\Md Anikur Rahaman\\AppData\\Roaming\\npm\\stripe.cmd';
$key  = \FraudTenant\Services\StripeCredentialService::get_secret_key( 'test' );
$dir  = 'C:/Users/Md Anikur Rahaman/ft_qa_secrets';
$mode = $argv[1] ?? 'secret';
if ( empty( $key ) || ! str_starts_with( $key, 'sk_test_' ) && ! str_starts_with( $key, 'rk_test_' ) ) {
	fwrite( STDERR, "No Test-mode key configured in QA.\n" );
	exit( 1 );
}
$env = array_merge( getenv(), array( 'STRIPE_API_KEY' => $key ) );

if ( 'secret' === $mode ) {
	$proc = proc_open( array( $cli, 'listen', '--print-secret' ), array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes, null, $env );
	$out  = stream_get_contents( $pipes[1] );
	stream_get_contents( $pipes[2] );
	proc_close( $proc );
	if ( ! preg_match( '/whsec_[A-Za-z0-9]+/', (string) $out, $m ) ) {
		fwrite( STDERR, "Could not obtain CLI signing secret.\n" );
		exit( 1 );
	}
	$r = \FraudTenant\Services\StripeCredentialService::save_credentials( array( 'webhook_secret' => $m[0] ), 1, 'test' );
	file_put_contents( $dir . '/qa_whsec_test.txt', $m[0] );
	echo $r['success'] ? "QA Test webhook secret set from Stripe CLI (value not shown).\n" : "Save failed.\n";
	exit( $r['success'] ? 0 : 1 );
}

if ( 'listen' === $mode ) {
	$events = 'checkout.session.completed,checkout.session.async_payment_succeeded,checkout.session.async_payment_failed,checkout.session.expired,customer.subscription.created,customer.subscription.updated,customer.subscription.deleted,invoice.paid,invoice.payment_succeeded,invoice.payment_failed';
	$log    = $dir . '/stripe_listen.log';
	$proc   = proc_open(
		array( $cli, 'listen', '--events', $events, '--forward-to', 'http://localhost/fraudtenant-qa/?rest_route=/fraud-tenant/v1/stripe/webhook' ),
		array( 1 => array( 'file', $log, 'a' ), 2 => array( 'file', $log, 'a' ) ),
		$pipes,
		null,
		$env
	);
	echo "stripe listen started (log outside web root).\n";
	// Keep this PHP process as the parent so the listener lives until killed.
	while ( true ) {
		$st = proc_get_status( $proc );
		if ( ! $st['running'] ) {
			echo "listener exited\n";
			exit( 1 );
		}
		sleep( 5 );
	}
}
