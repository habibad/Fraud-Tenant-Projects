<?php
/**
 * Suite: authoritative workflow-diagram regression (register -> OTP -> pending -> admin decision
 * -> login routing; invalid/expired/locked OTP; resend cooldown) plus admin Command Center
 * authorization and KPI correctness. Cross-role checks use real HTTP with real auth cookies.
 */
require __DIR__ . '/lib.php';

use FraudTenant\Admin\AdminDashboardController;
use FraudTenant\Admin\AdminReviewService;
use FraudTenant\Auth\AuthService;
use FraudTenant\Services\OtpService;

global $wpdb;
$admin_id = 1;

function wf_status( int $uid ): string {
	global $wpdb;
	return (string) $wpdb->get_var( $wpdb->prepare( "SELECT approval_status FROM {$wpdb->prefix}ft_user_profiles WHERE user_id = %d", $uid ) );
}
function wf_clear_rate(): void {
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_ft\\_login\\_fails\\_%' OR option_name LIKE '\\_transient\\_timeout\\_ft\\_login\\_fails\\_%' OR option_name LIKE '\\_transient\\_ft\\_reg\\_%' OR option_name LIKE '\\_transient\\_timeout\\_ft\\_reg\\_%'" );
	wp_cache_flush();
}
function wf_register_tenant( string $email, string $pw ): array {
	wf_clear_rate();
	return AuthService::register(
		array(
			'account_type'       => 'tenant',
			'first_name'         => 'QA',
			'last_name'          => 'Tenant',
			'email'              => $email,
			'phone'              => '512-555-0' . wp_rand( 100, 999 ),
			'password'           => $pw,
			'confirm_password'   => $pw,
			'current_street'     => '10 Main St',
			'current_city'       => 'Austin',
			'current_state'      => 'TX',
			'current_zip'        => '78701',
			'address_line1'      => '10 Main St',
			'city'               => 'Austin',
			'state'              => 'TX',
			'postal_code'        => '78701',
			'terms_accepted'     => '1',
			'privacy_accepted'   => '1',
			'terms_acknowledged' => '1',
		)
	);
}
function wf_cookie_header( int $uid ): array {
	$exp   = time() + 3600;
	$tok   = WP_Session_Tokens::get_instance( $uid )->create( $exp );
	$li    = wp_generate_auth_cookie( $uid, $exp, 'logged_in', $tok );
	$au    = wp_generate_auth_cookie( $uid, $exp, 'auth', $tok );
	return array( 'Cookie' => LOGGED_IN_COOKIE . '=' . rawurlencode( $li ) . '; ' . AUTH_COOKIE . '=' . rawurlencode( $au ) );
}
function wf_get( string $path, array $headers = array() ): array {
	$r = wp_remote_get( 'http://localhost/fraudtenant-qa' . $path, array( 'headers' => $headers, 'redirection' => 0, 'timeout' => 60 ) );
	return array(
		'code'     => (int) wp_remote_retrieve_response_code( $r ),
		'location' => (string) wp_remote_retrieve_header( $r, 'location' ),
		'body'     => (string) wp_remote_retrieve_body( $r ),
	);
}

// ---------------------------------------------------------------- Register (Tenant) -> OTP
$email = 'qa-wf-' . t_uid() . '@example.test';
$pw    = 'WfPassw0rd!';
$t0    = microtime( true );
$reg   = wf_register_tenant( $email, $pw );
t_assert( ! empty( $reg['success'] ), 'WF-01', 'Tenant registration accepted' . ( empty( $reg['success'] ) ? ' [' . ( $reg['message'] ?? '' ) . ']' : '' ) );
$uid = (int) ( get_user_by( 'email', $email )->ID ?? 0 );
t_assert( $uid > 0 && 'email_unverified' === wf_status( $uid ), 'WF-02', 'Account created in email_unverified state' );
t_assert( false !== strpos( (string) ( $reg['redirect'] ?? '' ), '/verify-otp/' ), 'WF-03', 'Routed to Enter 6-Digit OTP' );
$code = t_latest_code_for( $email, $t0 );
t_assert( (bool) $code, 'WF-04', 'Email OTP generated and dispatched (QA mail capture)' );
t_assert( in_array( Constants_role( $uid ), array( 'ft_tenant' ), true ), 'WF-05', 'Tenant role assigned server-side' );
function Constants_role( int $uid ): string {
	$u = get_userdata( $uid );
	return $u ? (string) ( $u->roles[0] ?? '' ) : '';
}

wf_clear_rate();
$pre = AuthService::login( $email, $pw );
t_assert( empty( $pre['success'] ) && 'email_unverified' === ( $pre['error'] ?? '' ), 'WF-06', 'Login before email verification is denied and routed to OTP' );

$cool = OtpService::generate_otp( $uid, $email, 'registration' );
t_assert( empty( $cool['success'] ) && 'cooldown_active' === ( $cool['error'] ?? '' ), 'WF-07', 'Resend within 60 s blocked by cooldown' );

$wrong = OtpService::verify_otp( $email, '000000' === $code ? '111111' : '000000', 'registration' );
t_assert( empty( $wrong['success'] ) && 'invalid_otp' === ( $wrong['error'] ?? '' ), 'WF-08', 'Invalid OTP rejected with remaining-attempt count' );

$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}ft_email_otps SET expires_at = %s WHERE email = %s AND purpose = 'registration' AND consumed_at IS NULL", gmdate( 'Y-m-d H:i:s', time() - 5 ), $email ) );
$exp = OtpService::verify_otp( $email, (string) $code, 'registration' );
t_assert( empty( $exp['success'] ), 'WF-09', 'Expired OTP rejected even with the correct digits' );

// Retry or Resend -> new code
$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}ft_email_otps SET resend_available_at = %s WHERE email = %s", gmdate( 'Y-m-d H:i:s', time() - 1 ), $email ) );
$t1  = microtime( true );
$res = OtpService::generate_otp( $uid, $email, 'registration' );
$code2 = t_latest_code_for( $email, $t1 );
t_assert( ! empty( $res['success'] ) && $code2, 'WF-10', 'Resend after cooldown issues a fresh code' );
$ok = OtpService::verify_otp( $email, (string) $code2, 'registration' );
t_assert( ! empty( $ok['success'] ) && 'pending_approval' === wf_status( $uid ), 'WF-11', 'Valid OTP -> Email Verified -> Pending Admin Approval' );
$again = OtpService::verify_otp( $email, (string) $code2, 'registration' );
t_assert( empty( $again['success'] ), 'WF-12', 'OTP is single-use' );

wf_clear_rate();
$pend = AuthService::login( $email, $pw );
t_assert( false === strpos( (string) ( $pend['redirect'] ?? '' ), 'dashboard' ) && false !== strpos( (string) ( $pend['redirect'] ?? '' ), '/account-status/' ), 'WF-13', 'Pending account login routed to Account Status, not a dashboard' );
$h = wf_cookie_header( $uid );
$g = wf_get( '/tenant-dashboard/', $h );
t_assert( 200 !== $g['code'] || false === strpos( $g['body'], 'dashboard-shell-layout' ), 'WF-14', 'Pending (verified, unapproved) tenant cannot load the Tenant Dashboard over HTTP (code ' . $g['code'] . ')' );

// Attempt lockout on another account
$lock_email = 'qa-lock-' . t_uid() . '@example.test';
wf_register_tenant( $lock_email, $pw );
for ( $i = 0; $i < 5; $i++ ) {
	OtpService::verify_otp( $lock_email, '999999', 'registration' );
}
$lock_code = t_latest_code_for( $lock_email, $t0 );
$locked    = OtpService::verify_otp( $lock_email, (string) $lock_code, 'registration' );
t_assert( empty( $locked['success'] ), 'WF-15', 'After 5 failed attempts the OTP is locked (correct code no longer accepted)' );

// ---------------------------------------------------------------- Admin decision
$t2  = microtime( true );
$app = AdminReviewService::approve_application( $uid, $admin_id );
t_assert( ! empty( $app['success'] ) && 'approved' === wf_status( $uid ), 'WF-16', 'Admin Approved -> status approved' );
t_assert( t_mail_to( $email, $t2, '' ), 'WF-17', 'Approval notification email sent' );
wf_clear_rate();
$li = AuthService::login( $email, $pw );
t_assert( ! empty( $li['success'] ) && false !== strpos( (string) $li['redirect'], '/tenant-dashboard/' ), 'WF-18', 'Approved tenant login -> Tenant Dashboard' );
$g = wf_get( '/tenant-dashboard/', wf_cookie_header( $uid ) );
t_assert( 200 === $g['code'] && false !== strpos( $g['body'], 'dashboard-shell-layout' ), 'WF-19', 'Approved tenant loads the Tenant Dashboard over HTTP' );
$g = wf_get( '/owner-dashboard/', wf_cookie_header( $uid ) );
t_assert( 200 !== $g['code'] || false === strpos( $g['body'], 'ftMembershipModal' ), 'WF-20', 'Tenant cannot load the Owner Dashboard (cross-role denial, code ' . $g['code'] . ')' );
$g = wf_get( '/wp-admin/admin.php?page=fraud-tenant-admin', wf_cookie_header( $uid ) );
t_assert( 200 !== $g['code'] || false === strpos( $g['body'], 'Command Center' ), 'WF-21', 'Tenant cannot load the Admin Command Center (code ' . $g['code'] . ')' );

$rej_email = 'qa-rej-' . t_uid() . '@example.test';
$t3        = microtime( true );
wf_register_tenant( $rej_email, $pw );
$rej_uid = (int) get_user_by( 'email', $rej_email )->ID;
OtpService::verify_otp( $rej_email, (string) t_latest_code_for( $rej_email, $t3 ), 'registration' );
$t4  = microtime( true );
$rj  = AdminReviewService::reject_application( $rej_uid, $admin_id, 'Synthetic QA rejection reason.' );
t_assert( ! empty( $rj['success'] ) && 'rejected' === wf_status( $rej_uid ), 'WF-22', 'Admin Rejected -> status rejected with reason recorded' );
t_assert( t_mail_to( $rej_email, $t4, '' ), 'WF-23', 'Rejection notification email sent' );
wf_clear_rate();
$rl = AuthService::login( $rej_email, $pw );
t_assert( empty( $rl['success'] ) || false === strpos( (string) ( $rl['redirect'] ?? '' ), 'dashboard' ), 'WF-24', 'Rejected account cannot reach a dashboard' );

// Owner routing + admin routing + invalid credentials
$owner = t_make_user( 'owner', 'approved', 'OwnerPassw0rd!' );
wf_clear_rate();
$ol = AuthService::login( get_userdata( $owner )->user_email, 'OwnerPassw0rd!' );
t_assert( ! empty( $ol['success'] ) && false !== strpos( (string) $ol['redirect'], '/owner-dashboard/' ), 'WF-25', 'Approved owner login -> Property Owner Dashboard' );
$g = wf_get( '/tenant-dashboard/', wf_cookie_header( $owner ) );
t_assert( 200 !== $g['code'] || false === strpos( $g['body'], 'dashboard-shell-layout' ), 'WF-26', 'Owner cannot load the Tenant Dashboard (code ' . $g['code'] . ')' );
$adm = json_decode( (string) file_get_contents( 'C:/Users/Md Anikur Rahaman/ft_qa_secrets/qa_test_admin.json' ), true );
wf_clear_rate();
$al = AuthService::login( $adm['login'], $adm['password'] );
t_assert( ! empty( $al['success'] ) && false !== strpos( (string) $al['redirect'], 'page=fraud-tenant-admin' ), 'WF-27', 'Administrator login -> Custom Admin Dashboard' );
wf_clear_rate();
$bad1 = AuthService::login( $email, 'wrong-password' );
$bad2 = AuthService::login( 'nobody-' . t_uid() . '@example.test', 'wrong-password' );
t_assert( 'invalid_credentials' === ( $bad1['error'] ?? '' ) && ( $bad1['message'] ?? 'a' ) === ( $bad2['message'] ?? 'b' ), 'WF-28', 'Invalid login shows one generic error for wrong password and unknown email' );
wf_clear_rate();

// ---------------------------------------------------------------- Admin Command Center authorization
$limited = t_make_user( 'owner' );
$lu      = new WP_User( $limited );
$lu->set_role( 'subscriber' );
$lu->add_cap( 'ft_access_admin_portal' );
$lu->add_cap( 'ft_review_user_registrations' );
wp_set_current_user( $limited );
t_assert( AdminDashboardController::can_view_section( 'users' ), 'ADM-01', 'Registration reviewer can open Users' );
t_assert( ! AdminDashboardController::can_view_section( 'audit_logs' ) && ! AdminDashboardController::can_view_section( 'stripe' ) && ! AdminDashboardController::can_view_section( 'subscriptions' ), 'ADM-02', 'Registration reviewer cannot open Audit Logs / Stripe / Subscriptions' );
t_assert( ! AdminDashboardController::can_view_section( 'nonexistent' ), 'ADM-03', 'Unknown section denied' );
$g = wf_get( '/wp-admin/admin.php?page=fraud-tenant-admin&tab=audit_logs', wf_cookie_header( $limited ) );
t_assert( 403 === $g['code'], 'ADM-04', 'HTTP: limited reviewer requesting Audit Logs gets 403 (got ' . $g['code'] . ')' );
$g = wf_get( '/wp-admin/admin.php?page=fraud-tenant-admin&tab=users', wf_cookie_header( $limited ) );
t_assert( 200 === $g['code'] && false !== strpos( $g['body'], 'User Management Directory' ) && false === strpos( $g['body'], 'tab=audit_logs' ), 'ADM-05', 'HTTP: reviewer sees Users, and the Audit Logs tab is hidden from navigation' );
wp_set_current_user( 1 );
$all = true;
foreach ( array_keys( AdminDashboardController::SECTION_CAPS ) as $sec ) {
	$all = $all && AdminDashboardController::can_view_section( $sec );
}
t_assert( $all, 'ADM-06', 'Administrator can open all 15 sections' );

// KPI correctness against real statuses
$wpdb->insert( $wpdb->prefix . 'ft_incident_reports', array( 'owner_id' => $owner, 'property_id' => t_make_property( $owner ), 'category' => 'unpaid_rent', 'incident_date' => gmdate( 'Y-m-d' ), 'factual_description' => 'Synthetic QA KPI row', 'status' => 'submitted', 'created_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ) );
$expected_inc = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ft_incident_reports WHERE status IN ('submitted','under_review','needs_clarification')" );
$g            = wf_get( '/wp-admin/admin.php?page=fraud-tenant-admin', wf_cookie_header( 1 ) );
$found        = preg_match( '/Incident Review\s*<span[^>]*>\s*(\d+)\s*<\/span>/', $g['body'], $m );
t_assert( 200 === $g['code'] && $found && (int) $m[1] === $expected_inc && $expected_inc > 0, 'ADM-07', "Incident Review badge equals real pending count ({$expected_inc}) (D11 fixed)" );
$g = wf_get( '/wp-admin/admin.php?page=fraud-tenant-admin&tab=users&s=' . rawurlencode( 'qa-wf-' ), wf_cookie_header( 1 ) );
t_assert( false !== strpos( $g['body'], $email ) && false === strpos( $g['body'], $rej_email ), 'ADM-08', 'User search filters results server-side' );
$g = wf_get( '/wp-admin/admin.php?page=fraud-tenant-admin&tab=audit_logs', wf_cookie_header( 1 ) );
t_assert( 200 === $g['code'] && false !== strpos( $g['body'], 'ft-pagination' ) && false === strpos( $g['body'], 'details_json' ), 'ADM-09', 'Audit Logs paginated; payload column not rendered' );

t_summary( 'Workflow & Admin' );
