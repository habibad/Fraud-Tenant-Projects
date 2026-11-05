<?php
/**
 * Shared harness for Claude completion QA suites.
 *
 * - Boots the ISOLATED QA installation (C:\xampp\htdocs\fraudtenant-qa, DB fraudtenant_qa)
 *   and refuses to run against any other database.
 * - Provides assertions with exact counts, synthetic fixture factories, captured-mail
 *   reader and Stripe Test-mode helpers.
 *
 * CLI only. Never web-accessible (tests/.htaccess denies all).
 */
if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}
// Buffer output so in-process logins (which set cookies) never hit "headers already sent".
ob_start();
register_shutdown_function( static function () {
	while ( ob_get_level() > 0 ) {
		ob_end_flush();
	}
} );
if ( ! defined( 'FT_ENFORCE_MEMBERSHIP_QUOTA' ) ) {
	define( 'FT_ENFORCE_MEMBERSHIP_QUOTA', true );
}
$_SERVER['HTTP_HOST']   = 'localhost';
$_SERVER['REQUEST_URI'] = '/fraudtenant-qa/';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require 'C:/xampp/htdocs/fraudtenant-qa/wp-load.php';

if ( DB_NAME !== 'fraudtenant_qa' || ! defined( 'FT_QA_INSTALL' ) ) {
	fwrite( STDERR, "ABORT: harness must run against the isolated QA database only.\n" );
	exit( 2 );
}

const FT_QA_MAIL_DIR = 'C:/Users/Md Anikur Rahaman/ft_qa_mail';

$GLOBALS['ft_t'] = array( 'pass' => 0, 'fail' => 0, 'failures' => array() );

function t_assert( bool $cond, string $id, string $desc ): bool {
	if ( $cond ) {
		$GLOBALS['ft_t']['pass']++;
		echo "[PASS] {$id}: {$desc}\n";
	} else {
		$GLOBALS['ft_t']['fail']++;
		$GLOBALS['ft_t']['failures'][] = "{$id}: {$desc}";
		echo "[FAIL] {$id}: {$desc}\n";
	}
	return $cond;
}

function t_summary( string $suite ): void {
	$t = $GLOBALS['ft_t'];
	echo "\n==== {$suite}: {$t['pass']} passed, {$t['fail']} failed, " . ( $t['pass'] + $t['fail'] ) . " assertions ====\n";
	foreach ( $t['failures'] as $f ) {
		echo "  FAILED -> {$f}\n";
	}
	exit( $t['fail'] > 0 ? 1 : 0 );
}

function t_uid(): string {
	return substr( bin2hex( random_bytes( 4 ) ), 0, 8 );
}

/**
 * Create a synthetic user with an extended profile.
 */
function t_make_user( string $type = 'owner', string $status = 'approved', string $password = 'QaPassw0rd!' ): int {
	global $wpdb;
	$u     = t_uid();
	$email = "qa-{$type}-{$u}@example.test";
	$id    = wp_insert_user(
		array(
			'user_login'   => "qa_{$type}_{$u}",
			'user_email'   => $email,
			'user_pass'    => $password,
			'first_name'   => 'QA',
			'last_name'    => ucfirst( $type ) . $u,
			'display_name' => 'QA ' . ucfirst( $type ) . ' ' . $u,
			'role'         => 'owner' === $type ? 'ft_owner' : 'ft_tenant',
		)
	);
	if ( is_wp_error( $id ) ) {
		throw new RuntimeException( $id->get_error_message() );
	}
	$now = current_time( 'mysql' );
	$wpdb->insert(
		$wpdb->prefix . 'ft_user_profiles',
		array(
			'user_id'         => $id,
			'account_type'    => $type,
			'approval_status' => $status,
			'phone'           => '555-010-' . wp_rand( 1000, 9999 ),
			'created_at'      => $now,
			'updated_at'      => $now,
		)
	);
	return (int) $id;
}

function t_make_property( int $owner_id, string $status = 'verified' ): int {
	global $wpdb;
	$now = current_time( 'mysql' );
	$wpdb->insert(
		$wpdb->prefix . 'ft_properties',
		array(
			'owner_id'       => $owner_id,
			'title'          => 'QA Property ' . t_uid(),
			'address_street' => '100 Test St',
			'city'           => 'Austin',
			'state'          => 'TX',
			'postal_code'    => '78701',
			'property_type'  => 'single_family',
			'units_count'    => 1,
			'status'         => $status,
			'created_at'     => $now,
			'updated_at'     => $now,
		)
	);
	return (int) $wpdb->insert_id;
}

/**
 * Latest captured OTP code sent to an address after a given time.
 */
function t_latest_code_for( string $email, float $after ): ?string {
	$best = null;
	$bt   = 0.0;
	foreach ( glob( FT_QA_MAIL_DIR . '/*.json' ) ?: array() as $f ) {
		if ( filemtime( $f ) + 1 < $after ) {
			continue;
		}
		$m = json_decode( (string) file_get_contents( $f ), true );
		if ( ! is_array( $m ) || strtolower( (string) $m['to'] ) !== strtolower( $email ) ) {
			continue;
		}
		$ts = (float) ( $m['ts'] ?? filemtime( $f ) );
		if ( $ts < $after ) {
			continue; // Microsecond-precise: never return a code sent before $after.
		}
		if ( preg_match( '/\b(\d{6})\b/', (string) $m['message'], $mm ) && $ts >= $bt ) {
			$best = $mm[1];
			$bt   = $ts;
		}
	}
	return $best;
}

function t_mail_to( string $email, float $after, string $subject_contains ): bool {
	foreach ( glob( FT_QA_MAIL_DIR . '/*.json' ) ?: array() as $f ) {
		if ( filemtime( $f ) + 1 < $after ) {
			continue;
		}
		$m = json_decode( (string) file_get_contents( $f ), true );
		if ( is_array( $m ) && strtolower( (string) $m['to'] ) === strtolower( $email ) && false !== stripos( (string) $m['subject'], $subject_contains ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Stripe Test-mode API call through the plugin's own client (QA credentials).
 */
function t_stripe( string $method, string $endpoint, array $data = array(), ?string $idem = null ): array {
	return \FraudTenant\Services\StripeService::request( $method, $endpoint, $data, $idem, 'test' );
}

/**
 * Wait (polling) until a callback returns truthy or timeout.
 */
function t_wait_until( callable $cb, int $timeout = 45 ) {
	$end = time() + $timeout;
	do {
		$v = $cb();
		if ( $v ) {
			return $v;
		}
		usleep( 750000 );
		wp_cache_flush();
	} while ( time() < $end );
	return $cb();
}

function t_local_sub( string $sub_id ): ?object {
	global $wpdb;
	wp_cache_flush();
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ft_subscriptions WHERE stripe_subscription_id = %s", $sub_id ) ) ?: null;
}

/**
 * Sign and POST a raw body to the QA webhook endpoint over real HTTP.
 */
function t_post_webhook( string $raw, ?string $secret, ?int $ts = null, ?string $header = null ): array {
	$ts     = $ts ?? time();
	$header = $header ?? ( 't=' . $ts . ',v1=' . hash_hmac( 'sha256', $ts . '.' . $raw, (string) $secret ) );
	$res    = wp_remote_post(
		'http://localhost/fraudtenant-qa/?rest_route=/fraud-tenant/v1/stripe/webhook',
		array(
			'headers' => array( 'Content-Type' => 'application/json', 'Stripe-Signature' => $header ),
			'body'    => $raw,
			'timeout' => 60,
		)
	);
	if ( is_wp_error( $res ) ) {
		return array( 'code' => 0, 'body' => array() );
	}
	return array( 'code' => (int) wp_remote_retrieve_response_code( $res ), 'body' => (array) json_decode( wp_remote_retrieve_body( $res ), true ) );
}

function t_qa_whsec(): string {
	return trim( (string) file_get_contents( 'C:/Users/Md Anikur Rahaman/ft_qa_secrets/qa_whsec_test.txt' ) );
}
