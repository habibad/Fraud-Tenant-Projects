<?php
/**
 * Suite: Checkout concurrency (Phase 9.2.1 Stage B) using INDEPENDENT PHP processes and
 * independent DB connections, plus a real-HTTP race of the checkout REST endpoint against Stripe.
 */
require __DIR__ . '/lib.php';

use FraudTenant\Services\CheckoutLockService;
use FraudTenant\Services\CheckoutService;

global $wpdb;
$php    = 'C:/xampp/php/php.exe';
$worker = __DIR__ . '/tools/lock_worker.php';
$table  = $wpdb->prefix . 'ft_checkout_locks';

function run_workers( string $php, string $worker, int $owner, int $n, int $hold_ms ): array {
	$start = microtime( true ) + 4.0; // every process boots WP first, then all fire together
	$procs = array();
	for ( $i = 0; $i < $n; $i++ ) {
		$p = proc_open( array( $php, $worker, (string) $owner, sprintf( '%.6f', $start ), (string) $hold_ms ), array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
		$procs[] = array( $p, $pipes );
	}
	$out = array();
	foreach ( $procs as $pp ) {
		$o = stream_get_contents( $pp[1][1] );
		stream_get_contents( $pp[1][2] );
		proc_close( $pp[0] );
		$j = json_decode( trim( (string) $o ), true );
		if ( is_array( $j ) ) {
			$out[] = $j;
		}
	}
	return $out;
}

// 1. Simultaneous initial acquisition
$owner = t_make_user( 'owner' );
$res   = run_workers( $php, $worker, $owner, 12, 1500 );
$wins  = count( array_filter( $res, static fn( $r ) => $r['success'] ) );
$pids  = count( array_unique( array_column( $res, 'pid' ) ) );
t_assert( 12 === count( $res ) && 12 === $pids, 'CONC-01', "12 independent PHP processes completed ({$pids} distinct PIDs)" );
t_assert( 1 === $wins, 'CONC-02', "Exactly one process acquired the fresh lock (winners: {$wins})" );
$spread = max( array_column( $res, 't' ) ) - min( array_column( $res, 't' ) );
echo "    (completion spread across processes: " . round( $spread, 3 ) . "s)\n";

// 2. Simultaneous takeover of an EXPIRED lock
$wpdb->update( $table, array( 'expires_at' => gmdate( 'Y-m-d H:i:s', time() - 60 ), 'lock_token' => 'stale_token_for_qa' ), array( 'owner_id' => $owner ) );
$res2  = run_workers( $php, $worker, $owner, 12, 1500 );
$wins2 = count( array_filter( $res2, static fn( $r ) => $r['success'] ) );
t_assert( 1 === $wins2, 'CONC-03', "Exactly one process took over the expired lock (winners: {$wins2})" );
$tok_now = (string) $wpdb->get_var( $wpdb->prepare( "SELECT lock_token FROM {$table} WHERE owner_id = %d", $owner ) );
t_assert( 'stale_token_for_qa' !== $tok_now && 1 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE owner_id = %d", $owner ) ), 'CONC-04', 'Takeover replaced the stale token; still exactly one lock row (unique owner key)' );

// 3. Token-bound release
t_assert( false === CheckoutLockService::release_lock( $owner, 'not_the_token' ), 'CONC-05', 'Release with a wrong token is refused' );
t_assert( CheckoutLockService::is_locked( $owner ), 'CONC-06', 'Lock still held after wrong-token release attempt' );
t_assert( CheckoutLockService::release_lock( $owner, $tok_now ), 'CONC-07', 'Release with the owning token succeeds' );
$a = CheckoutLockService::acquire_lock( $owner, 'standard_monthly', 30 );
$b = CheckoutLockService::acquire_lock( $owner, 'standard_monthly', 30 );
t_assert( $a['success'] && ! $b['success'] && 429 === $b['status'], 'CONC-08', 'Held lock blocks a second acquirer with 429' );
CheckoutLockService::release_lock( $owner, $a['lock_token'] );

// 4. Lock clock: expiry stored in UTC regardless of site timezone (D7)
update_option( 'gmt_offset', 6 );
$c = CheckoutLockService::acquire_lock( $owner, 'standard_monthly', 30 );
$d = CheckoutLockService::acquire_lock( $owner, 'standard_monthly', 30 );
update_option( 'gmt_offset', 0 );
t_assert( $c['success'] && ! $d['success'], 'CONC-09', 'With site timezone UTC+6 the lock still excludes a second acquirer (D7 fixed)' );
CheckoutLockService::release_lock( $owner, $c['lock_token'] );

// 5. Real HTTP race: 8 concurrent checkout REST requests for one owner -> one Stripe session
$owner2  = t_make_user( 'owner' );
$expire  = time() + 3600;
$manager = WP_Session_Tokens::get_instance( $owner2 );
$token   = $manager->create( $expire );
$logged  = wp_generate_auth_cookie( $owner2, $expire, 'logged_in', $token );
$_COOKIE[ LOGGED_IN_COOKIE ] = $logged;
wp_set_current_user( $owner2 );
$nonce = wp_create_nonce( 'wp_rest' );
$url   = 'http://localhost/fraudtenant-qa/?rest_route=/fraud-tenant/v1/owner/membership/checkout';
$mh    = curl_multi_init();
$hs    = array();
for ( $i = 0; $i < 8; $i++ ) {
	$h = curl_init( $url );
	curl_setopt_array(
		$h,
		array(
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => http_build_query( array( 'plan_code' => 'standard_monthly' ) ),
			CURLOPT_HTTPHEADER     => array( 'X-WP-Nonce: ' . $nonce, 'Cookie: ' . LOGGED_IN_COOKIE . '=' . rawurlencode( $logged ) ),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 60,
		)
	);
	curl_multi_add_handle( $mh, $h );
	$hs[] = $h;
}
do {
	curl_multi_exec( $mh, $running );
	curl_multi_select( $mh );
} while ( $running > 0 );
$codes    = array();
$sessions = array();
foreach ( $hs as $h ) {
	$codes[] = (int) curl_getinfo( $h, CURLINFO_HTTP_CODE );
	$j       = json_decode( (string) curl_multi_getcontent( $h ), true );
	if ( ! empty( $j['data']['session_id'] ) ) {
		$sessions[] = $j['data']['session_id'];
	}
	curl_multi_remove_handle( $mh, $h );
}
echo '    HTTP codes: ' . implode( ',', $codes ) . "\n";
$distinct = array_values( array_unique( $sessions ) );
t_assert( count( $distinct ) === 1, 'CONC-10', '8 concurrent HTTP checkout requests produced exactly ONE distinct Stripe session (' . count( $distinct ) . ')' );
t_assert( 0 === count( array_filter( $codes, static fn( $c ) => ! in_array( $c, array( 200, 429 ), true ) ) ), 'CONC-11', 'Every concurrent request returned 200 (same session) or 429 (lock held)' );
wp_cache_flush();
$pending = get_user_meta( $owner2, CheckoutService::get_pending_session_meta_key( 'test' ), true );
t_assert( is_array( $pending ) && ( $pending['session_id'] ?? '' ) === ( $distinct[0] ?? 'x' ), 'CONC-12', 'Persisted attempt references that single session' );
$list = t_stripe( 'GET', 'checkout/sessions', array( 'customer' => (string) \FraudTenant\Services\StripeService::get_customer_id( $owner2, 'test' ), 'limit' => 20 ) );
t_assert( 1 === count( $list['data']['data'] ?? array() ), 'CONC-13', 'Stripe itself holds exactly one Checkout Session for this customer (' . count( $list['data']['data'] ?? array() ) . ')' );
$manager->destroy( $token );

t_summary( 'Checkout Concurrency' );
