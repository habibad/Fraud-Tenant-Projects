<?php
/**
 * Independent-process worker: waits for a shared start instant, then attempts to acquire the
 * owner's checkout lock through its own PHP process and its own DB connection.
 * Usage: php lock_worker.php <owner_id> <start_at_microtime> <hold_ms>
 */
require dirname( __DIR__ ) . '/lib.php';
$owner = (int) $argv[1];
$start = (float) $argv[2];
$hold  = (int) ( $argv[3] ?? 0 );
while ( microtime( true ) < $start ) {
	usleep( 200 );
}
$r = \FraudTenant\Services\CheckoutLockService::acquire_lock( $owner, 'standard_monthly', 30 );
if ( $r['success'] && $hold > 0 ) {
	usleep( $hold * 1000 );
}
echo wp_json_encode( array( 'pid' => getmypid(), 'success' => (bool) $r['success'], 'token' => $r['success'] ? substr( $r['lock_token'], 0, 8 ) : null, 't' => microtime( true ) ) ), "\n";
