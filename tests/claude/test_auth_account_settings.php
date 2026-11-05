<?php
/**
 * Suite: password recovery (cookie-bound reset authorization), password change, and verified
 * email change. Runs on the isolated QA install; OTP codes are read from the QA mail capture.
 */
require __DIR__ . '/lib.php';

use FraudTenant\Auth\AuthService;
use FraudTenant\Services\DashboardService;

global $wpdb;

// ---------------------------------------------------------------- Password reset
$uid   = t_make_user( 'owner', 'approved', 'OldPassw0rd!' );
$user  = get_userdata( $uid );
$email = $user->user_email;
$sess  = WP_Session_Tokens::get_instance( $uid );
$sess->create( time() + 3600 );
$sess->create( time() + 3600 );
t_assert( 2 === count( $sess->get_all() ), 'PWR-00', 'User has 2 active sessions before reset' );

$t0 = microtime( true );
delete_transient( 'ft_pw_reset_' . md5( '127.0.0.1' ) );
$r = AuthService::request_password_reset( $email );
t_assert( ! empty( $r['success'] ), 'PWR-01', 'Reset OTP requested' );
delete_transient( 'ft_pw_reset_' . md5( '127.0.0.1' ) );
$r_unknown = AuthService::request_password_reset( 'nobody-' . t_uid() . '@example.test' );
t_assert( ! empty( $r_unknown['success'] ) && false !== stripos( $r_unknown['message'], 'If an account exists' ), 'PWR-02', 'Unknown email gets a generic success (no enumeration)' );
$code = t_latest_code_for( $email, $t0 );
t_assert( (bool) $code, 'PWR-03', 'Reset OTP delivered to QA mail capture' );

$bad = AuthService::verify_password_reset_otp( $email, '000000' === $code ? '111111' : '000000' );
t_assert( empty( $bad['success'] ), 'PWR-04', 'Wrong reset OTP rejected' );

$ok = AuthService::verify_password_reset_otp( $email, (string) $code );
t_assert( ! empty( $ok['success'] ), 'PWR-05', 'Correct reset OTP accepted' );
t_assert( false === strpos( (string) $ok['redirect'], 'token' ) && false === strpos( (string) $ok['redirect'], (string) $ok['reset_token'] ), 'PWR-06', 'Redirect URL contains NO reset token (D9 fixed)' );
$raw = (string) $ok['reset_token'];
t_assert( $raw === ( $_COOKIE[ AuthService::RESET_COOKIE ] ?? '' ), 'PWR-07', 'Reset authorization is carried by the ft_pw_reset cookie' );
$leak = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s OR option_value = %s", '%' . $wpdb->esc_like( $raw ) . '%', $raw ) );
t_assert( 0 === $leak, 'PWR-08', 'Raw reset token is not stored anywhere in wp_options (only its SHA-256 hash)' );
t_assert( $uid === AuthService::resolve_reset_authorization( $raw ), 'PWR-09', 'Token hash resolves to the right user' );
t_assert( 0 === AuthService::resolve_reset_authorization( str_repeat( 'a', 64 ) ), 'PWR-10', 'Unknown token resolves to nobody' );

$short = AuthService::complete_password_reset( $raw, 'short', 'short' );
t_assert( empty( $short['success'] ) && 'password_too_short' === $short['error'], 'PWR-11', 'Short new password rejected (token not consumed)' );
$done = AuthService::complete_password_reset( $raw, 'NewPassw0rd!x', 'NewPassw0rd!x' );
t_assert( ! empty( $done['success'] ) && false !== strpos( (string) $done['redirect'], '/login/' ), 'PWR-12', 'Password reset completes and routes to Login' );
$replay = AuthService::complete_password_reset( $raw, 'Another1!Pass', 'Another1!Pass' );
t_assert( empty( $replay['success'] ) && 'invalid_or_expired_token' === $replay['error'], 'PWR-13', 'Replay of the consumed reset authorization is rejected' );
t_assert( is_wp_error( wp_authenticate( $user->user_login, 'OldPassw0rd!' ) ), 'PWR-14', 'Old password no longer works' );
t_assert( ! is_wp_error( wp_authenticate( $user->user_login, 'NewPassw0rd!x' ) ), 'PWR-15', 'New password works' );
t_assert( 0 === count( WP_Session_Tokens::get_instance( $uid )->get_all() ), 'PWR-16', 'All existing sessions destroyed after reset' );
$audit = (string) $wpdb->get_var( $wpdb->prepare( "SELECT details_json FROM {$wpdb->prefix}ft_audit_log WHERE action = 'user_password_reset' AND object_id = %d ORDER BY id DESC LIMIT 1", $uid ) );
t_assert( '' !== $audit && false === strpos( $audit, $raw ) && false === strpos( $audit, 'NewPassw0rd' ), 'PWR-17', 'Audit entry exists and contains neither token nor password' );

// Template no longer reads ?token=
$tpl = (string) file_get_contents( get_template_directory() . '/page-reset-password.php' );
t_assert( false === strpos( $tpl, "\$_GET['token']" ) && false === strpos( $tpl, 'name="reset_token"' ), 'PWR-18', 'Reset template neither reads ?token= nor renders the token into HTML' );

// ---------------------------------------------------------------- Password change (Account Settings)
$o2 = t_make_user( 'owner', 'approved', 'StartPassw0rd!' );
wp_set_current_user( $o2 );
$n  = wp_create_nonce( 'ft_update_profile_' . $o2 );
$r1 = DashboardService::update_safe_profile( $o2, array( 'ft_profile_nonce' => $n, 'account_section' => 'password', 'current_password' => 'wrong', 'new_password' => 'Brandnew1!pw', 'confirm_password' => 'Brandnew1!pw' ) );
t_assert( 'invalid_current_password' === ( $r1['error'] ?? '' ), 'PWC-01', 'Password change requires the correct current password' );
$r2 = DashboardService::update_safe_profile( $o2, array( 'ft_profile_nonce' => $n, 'account_section' => 'password', 'current_password' => 'StartPassw0rd!', 'new_password' => 'Brandnew1!pw', 'confirm_password' => 'Different1!pw' ) );
t_assert( 'password_mismatch' === ( $r2['error'] ?? '' ), 'PWC-02', 'Confirmation mismatch rejected' );
$r3 = DashboardService::update_safe_profile( $o2, array( 'ft_profile_nonce' => 'bad', 'account_section' => 'password', 'current_password' => 'StartPassw0rd!', 'new_password' => 'Brandnew1!pw', 'confirm_password' => 'Brandnew1!pw' ) );
t_assert( 'invalid_nonce' === ( $r3['error'] ?? '' ), 'PWC-03', 'Invalid nonce rejected (CSRF)' );
$r4 = DashboardService::update_safe_profile( $o2 + 999999, array( 'ft_profile_nonce' => $n ) );
t_assert( 'unauthorized' === ( $r4['error'] ?? '' ), 'PWC-04', 'Cannot update another user\'s profile (IDOR)' );
$r5 = DashboardService::update_safe_profile( $o2, array( 'ft_profile_nonce' => $n, 'account_section' => 'password', 'current_password' => 'StartPassw0rd!', 'new_password' => 'Brandnew1!pw', 'confirm_password' => 'Brandnew1!pw' ) );
t_assert( ! empty( $r5['success'] ) && ! is_wp_error( wp_authenticate( get_userdata( $o2 )->user_login, 'Brandnew1!pw' ) ), 'PWC-05', 'Valid password change succeeds and new password authenticates' );

// ---------------------------------------------------------------- Verified email change
$o3     = t_make_user( 'tenant', 'approved', 'MailPassw0rd!' );
$old    = get_userdata( $o3 )->user_email;
$new    = 'qa-new-' . t_uid() . '@example.test';
$other  = get_userdata( $o2 )->user_email;
wp_set_current_user( $o3 );
$n3 = wp_create_nonce( 'ft_update_profile_' . $o3 );
$e0 = DashboardService::update_safe_profile( $o3, array( 'ft_profile_nonce' => $n3, 'account_section' => 'email', 'new_email' => $new, 'current_password' => 'nope' ) );
t_assert( 'invalid_current_password' === ( $e0['error'] ?? '' ), 'EML-01', 'Email change requires current password' );
$e1 = DashboardService::update_safe_profile( $o3, array( 'ft_profile_nonce' => $n3, 'account_section' => 'email', 'new_email' => $other, 'current_password' => 'MailPassw0rd!' ) );
t_assert( 'email_exists' === ( $e1['error'] ?? '' ), 'EML-02', 'Email already used by another account is rejected' );
$t1 = microtime( true );
$e2 = DashboardService::update_safe_profile( $o3, array( 'ft_profile_nonce' => $n3, 'account_section' => 'email', 'new_email' => $new, 'current_password' => 'MailPassw0rd!' ) );
clean_user_cache( $o3 );
t_assert( ! empty( $e2['pending'] ), 'EML-03', 'Valid request creates a PENDING change' );
t_assert( $old === get_userdata( $o3 )->user_email, 'EML-04', 'Account email is NOT changed before verification (D10 fixed)' );
$code3 = t_latest_code_for( $new, $t1 );
t_assert( (bool) $code3, 'EML-05', 'Confirmation code sent to the NEW address' );
t_assert( null === t_latest_code_for( $old, $t1 ), 'EML-06', 'No code sent to the old address' );
$otp_row = $wpdb->get_row( $wpdb->prepare( "SELECT otp_hash FROM {$wpdb->prefix}ft_email_otps WHERE email = %s AND purpose = 'email_change' ORDER BY id DESC LIMIT 1", $new ) );
t_assert( $otp_row && $otp_row->otp_hash !== $code3 && false === strpos( (string) $otp_row->otp_hash, (string) $code3 ), 'EML-07', 'Only a hash of the code is stored' );
$e3 = DashboardService::update_safe_profile( $o3, array( 'ft_profile_nonce' => $n3, 'account_section' => 'email_confirm', 'email_code' => '000000' === $code3 ? '111111' : '000000' ) );
t_assert( empty( $e3['success'] ), 'EML-08', 'Wrong confirmation code rejected' );
// another user cannot confirm this pending change with the code
wp_set_current_user( $o2 );
$steal = DashboardService::confirm_email_change( $o2, (string) $code3 );
t_assert( empty( $steal['success'] ), 'EML-09', 'Another account cannot use the code (no pending change bound to it)' );
wp_set_current_user( $o3 );
$t2 = microtime( true );
$e4 = DashboardService::update_safe_profile( $o3, array( 'ft_profile_nonce' => $n3, 'account_section' => 'email_confirm', 'email_code' => (string) $code3 ) );
clean_user_cache( $o3 );
t_assert( ! empty( $e4['success'] ) && $new === get_userdata( $o3 )->user_email, 'EML-10', 'Correct code completes the change' );
t_assert( '' === (string) get_user_meta( $o3, DashboardService::META_PENDING_EMAIL, true ), 'EML-11', 'Pending change cleared' );
t_assert( t_mail_to( $old, $t2, 'email address was changed' ), 'EML-12', 'Security notice sent to the previous address' );
$e5 = DashboardService::confirm_email_change( $o3, (string) $code3 );
t_assert( empty( $e5['success'] ), 'EML-13', 'Code is single-use (replay rejected)' );
$ad = (string) $wpdb->get_var( $wpdb->prepare( "SELECT details_json FROM {$wpdb->prefix}ft_audit_log WHERE action = 'user_email_updated' AND object_id = %d ORDER BY id DESC LIMIT 1", $o3 ) );
t_assert( '' !== $ad && false === strpos( $ad, $new ) && false === strpos( $ad, (string) $code3 ), 'EML-14', 'Audit uses masked emails and never the code' );

t_summary( 'Auth & Account Settings' );
