<?php
/**
 * Suite: tenant identity on incident reports + admin-released, identity-matched owner screening.
 * Runs on the isolated QA install only (synthetic people, synthetic SSNs).
 */
require __DIR__ . '/lib.php';

use FraudTenant\Services\ComplianceService;
use FraudTenant\Services\IncidentReviewService;
use FraudTenant\Services\IncidentService;
use FraudTenant\Services\ReportVisibilityService;
use FraudTenant\Services\TenantScreeningService;

global $wpdb;
$admin = (int) ( json_decode( (string) file_get_contents( 'C:/Users/Md Anikur Rahaman/ft_qa_secrets/qa_test_admin.json' ), true )['id'] ?? 0 );
if ( $admin <= 0 ) { fwrite( STDERR, "Run tools/ensure_test_admin.php first\n" ); exit( 2 ); }
$P     = $wpdb->prefix;

function qa_make_standard( int $uid ): void {
	global $wpdb;
	$plan = $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}ft_membership_plans WHERE plan_code = 'standard_monthly'" );
	$wpdb->insert( $wpdb->prefix . 'ft_subscriptions', array( 'user_id' => $uid, 'plan_id' => $plan, 'stripe_customer_id' => 'cus_qa_fixture', 'stripe_subscription_id' => 'sub_qa_' . t_uid(), 'status' => 'active', 'livemode' => 0, 'current_period_start' => gmdate( 'Y-m-d H:i:s' ), 'current_period_end' => gmdate( 'Y-m-d H:i:s', time() + 20 * 86400 ), 'cancel_at_period_end' => 0, 'created_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ) );
}
function qa_clear_rate( int $uid ): void {
	delete_transient( 'ft_scr_rate_' . $uid );
	delete_transient( 'ft_incident_rate_' . $uid );
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_%ft\\_inc%rate%'" );
}

// Start from a known gate state (QA only).
delete_option( ComplianceService::OPTION );

$owner_a = t_make_user( 'owner' ); // files the incident (Standard)
$owner_b = t_make_user( 'owner' ); // free searcher
$owner_c = t_make_user( 'owner' ); // standard searcher
$tenant  = t_make_user( 'tenant' );
qa_make_standard( $owner_a );
qa_make_standard( $owner_c );
$prop_a = t_make_property( $owner_a );
$prop_b = t_make_property( $owner_b );
$prop_c = t_make_property( $owner_c );
$prop_c_pending = t_make_property( $owner_c, 'pending_verification' );

// Unique synthetic SSN per run (valid area/group/serial) so runs never see each other's data.
$ssn_area   = (string) wp_rand( 100, 665 );
$ssn_group  = sprintf( '%02d', wp_rand( 10, 99 ) );
$ssn_serial = sprintf( '%04d', wp_rand( 1000, 9999 ) );
$ssn        = $ssn_area . '-' . $ssn_group . '-' . $ssn_serial;
$ssn_digits = $ssn_area . $ssn_group . $ssn_serial;
$ssn_other  = $ssn_area . '-' . $ssn_group . '-' . sprintf( '%04d', ( (int) $ssn_serial % 9998 ) + 1 );
$first = 'Jordan';
$last  = "O'Neil-Smith";

// ------------------------------------------------------------ 1. Gates
t_assert( ComplianceService::identity_capture_ready(), 'GATE-01', 'Identity capture ready on QA (key + encryption + data gate)' );
t_assert( ! ComplianceService::owner_search_ready(), 'GATE-02', 'Owner search disabled until BLK-03 attestation is recorded' );
$search_input = array( 'property_id' => $prop_c, 'purpose_reason' => 'rental_application', 'permissible_purpose_attestation' => '1', 'applicant_consent' => '1', 'applicant_first_name' => $first, 'applicant_last_name' => $last, 'applicant_ssn' => $ssn );
$r = TenantScreeningService::search_applicant( $owner_c, $search_input );
t_assert( 'screening_unavailable' === ( $r['error'] ?? '' ), 'GATE-03', 'Search refused while the gate is closed' );
t_assert( empty( ComplianceService::set_gate( 'owner_disclosure', true, $owner_a, str_repeat( 'x', 30 ) )['success'] ), 'GATE-04', 'Non-administrator cannot record an attestation' );
t_assert( empty( ComplianceService::set_gate( 'owner_disclosure', true, $admin, 'too short' )['success'] ), 'GATE-05', 'Attestation statement must be substantive (>= 20 chars)' );
$g = ComplianceService::set_gate( 'owner_disclosure', true, $admin, 'QA synthetic environment only — no real people or data.' );
t_assert( ! empty( $g['success'] ) && ComplianceService::owner_search_ready(), 'GATE-06', 'Administrator attestation enables owner search' );
t_assert( (bool) $wpdb->get_var( "SELECT id FROM {$P}ft_audit_log WHERE action = 'compliance_gate_enabled' ORDER BY id DESC LIMIT 1" ), 'GATE-07', 'Attestation is audited' );

// ------------------------------------------------------------ 2. Incident identifies the tenant
$base = array( 'property_id' => $prop_a, 'category' => 'unpaid_rent', 'incident_date' => gmdate( 'Y-m-d', time() - 40 * 86400 ), 'amount_involved' => '2400.00', 'factual_description' => 'Tenant left owing two months of rent (QA synthetic narrative).', 'owner_attestation' => '1' );
qa_clear_rate( $owner_a );
$e1 = IncidentService::record_incident_report( $owner_a, $base );
t_assert( 'invalid_tenant_name' === ( $e1['error'] ?? '' ), 'INC-01', 'Incident without tenant identity is rejected (not legacy context)' );
qa_clear_rate( $owner_a );
$e2 = IncidentService::record_incident_report( $owner_a, $base + array( 'tenant_first_name' => $first, 'tenant_last_name' => $last, 'tenant_ssn' => '000-12-3456', 'identity_attestation' => '1' ) );
t_assert( 'invalid_tenant_ssn' === ( $e2['error'] ?? '' ), 'INC-02', 'Implausible SSN (area 000) rejected' );
qa_clear_rate( $owner_a );
$e3 = IncidentService::record_incident_report( $owner_a, $base + array( 'tenant_first_name' => $first, 'tenant_last_name' => $last, 'tenant_ssn' => $ssn ) );
t_assert( 'missing_identity_attestation' === ( $e3['error'] ?? '' ), 'INC-03', 'Identity-source attestation required' );
qa_clear_rate( $owner_a );
$ok = IncidentService::record_incident_report( $owner_a, $base + array( 'tenant_first_name' => $first, 'tenant_last_name' => $last, 'tenant_ssn' => $ssn, 'identity_attestation' => '1' ) );
t_assert( ! empty( $ok['success'] ), 'INC-04', 'Incident with tenant identity accepted' . ( empty( $ok['success'] ) ? ' [' . ( $ok['message'] ?? '' ) . ']' : '' ) );
$rid = (int) ( $ok['incident_id'] ?? $wpdb->get_var( $wpdb->prepare( "SELECT MAX(id) FROM {$P}ft_incident_reports WHERE owner_id = %d", $owner_a ) ) );
$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$P}ft_incident_reports WHERE id = %d", $rid ) );
t_assert( $row && preg_match( '/^[a-f0-9]{64}$/', (string) $row->subject_token_hash ) && $ssn_serial === $row->subject_ssn_last4, 'INC-05', 'Stored: 64-hex HMAC token + last four only' );
t_assert( $row && str_starts_with( (string) $row->subject_name_enc, 'pii1:' ) && false === strpos( (string) $row->subject_name_enc, 'Jordan' ), 'INC-06', 'Tenant name stored encrypted (AES-256-GCM), not in plaintext' );
$disp = IncidentService::get_subject_display( $row );
t_assert( $first === $disp['first'] && $last === $disp['last'], 'INC-07', 'Authorized decryption returns the exact name' );
$leak = 0;
foreach ( array( 'ft_incident_reports', 'ft_audit_log', 'ft_screening_requests', 'ft_notifications' ) as $t ) {
	$cols = $wpdb->get_col( "SHOW COLUMNS FROM {$P}{$t}" );
	$conc = 'CONCAT_WS(\'|\',' . implode( ',', array_map( static fn( $c ) => "`{$c}`", $cols ) ) . ')';
	$leak += (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$P}{$t} WHERE {$conc} LIKE '%{$ssn_digits}%' OR {$conc} LIKE '%{$ssn}%'" );
}
$leak += (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_value LIKE '%{$ssn_digits}%' OR option_value LIKE '%{$ssn}%'" );
t_assert( 0 === $leak, 'INC-08', 'Full SSN appears nowhere in the database (incidents, audit, screening, notifications, options)' );

// ------------------------------------------------------------ 3. Admin review + release
$c0 = TenantScreeningService::search_applicant( $owner_c, $search_input );
$v0 = TenantScreeningService::get_search_result( $owner_c, (int) ( $c0['request_id'] ?? 0 ) );
t_assert( $v0 && 0 === $v0['count'], 'REL-01', 'Submitted (unreviewed) report is invisible to searching owners' );
t_assert( 'not_approved' === ( TenantScreeningService::set_screening_disclosure( $rid, true, $admin )['error'] ?? '' ), 'REL-02', 'Cannot release a report before admin approval' );
$dec = IncidentReviewService::decide_incident_report( $rid, $admin, 'approve', 'evidence_verified' );
t_assert( ! empty( $dec['success'] ), 'REL-03', 'Administrator approves the incident' );
qa_clear_rate( $owner_c );
$c1 = TenantScreeningService::search_applicant( $owner_c, $search_input );
t_assert( 0 === TenantScreeningService::get_search_result( $owner_c, (int) $c1['request_id'] )['count'], 'REL-04', 'Approved but NOT released report is still invisible (manual disclosure control)' );
t_assert( 'unauthorized' === ( TenantScreeningService::set_screening_disclosure( $rid, true, $owner_b )['error'] ?? '' ), 'REL-05', 'Owners cannot release reports' );
t_assert( 'unauthorized' === ( TenantScreeningService::set_screening_disclosure( $rid, true, $owner_a )['error'] ?? '' ), 'REL-06', 'The filing owner cannot release their own report' );
$rel = TenantScreeningService::set_screening_disclosure( $rid, true, $admin );
t_assert( ! empty( $rel['success'] ), 'REL-07', 'Administrator releases the approved report to owner screening' );

// ------------------------------------------------------------ 4. Owner searches
qa_clear_rate( $owner_c );
$s = TenantScreeningService::search_applicant( $owner_c, $search_input );
$v = TenantScreeningService::get_search_result( $owner_c, (int) $s['request_id'] );
t_assert( $v && 1 === $v['count'] && 'full' === $v['level'], 'SRC-01', 'Standard owner: name + SSN match returns 1 full record' );
$rec = $v['records'][0] ?? array();
t_assert( 'Unpaid Rent / Default' === ( $rec['category'] ?? '' ) && 2400.0 === ( $rec['amount'] ?? 0.0 ) && 'Austin, TX' === ( $rec['location'] ?? '' ), 'SRC-02', 'Full record shows category, amount, city/state' );
t_assert( ! isset( $rec['owner_id'] ) && false === strpos( wp_json_encode( $v ), get_userdata( $owner_a )->user_email ) && false === strpos( wp_json_encode( $v ), '100 Test St' ), 'SRC-03', 'Result exposes neither the filing owner nor the street address' );
t_assert( 'Jordan O\'Neil-Smith' === $v['applicant'] && $ssn_serial === $v['ssn_last4'], 'SRC-04', 'Result header shows the searched applicant and SSN last four only' );
qa_clear_rate( $owner_c );
$s2 = TenantScreeningService::search_applicant( $owner_c, array_merge( $search_input, array( 'applicant_last_name' => 'oneil smith' ) ) );
t_assert( 1 === TenantScreeningService::get_search_result( $owner_c, (int) $s2['request_id'] )['count'], 'SRC-05', 'Last-name match tolerates case/punctuation/spaces' );
qa_clear_rate( $owner_c );
$s3 = TenantScreeningService::search_applicant( $owner_c, array_merge( $search_input, array( 'applicant_last_name' => 'Different' ) ) );
t_assert( 0 === TenantScreeningService::get_search_result( $owner_c, (int) $s3['request_id'] )['count'], 'SRC-06', 'Right SSN + wrong last name -> no match (false-match safeguard)' );
qa_clear_rate( $owner_c );
$s4 = TenantScreeningService::search_applicant( $owner_c, array_merge( $search_input, array( 'applicant_ssn' => $ssn_other ) ) );
t_assert( 0 === TenantScreeningService::get_search_result( $owner_c, (int) $s4['request_id'] )['count'], 'SRC-07', 'Different SSN -> no match' );
qa_clear_rate( $owner_b );
$b = TenantScreeningService::search_applicant( $owner_b, array_merge( $search_input, array( 'property_id' => $prop_b ) ) );
$bv = TenantScreeningService::get_search_result( $owner_b, (int) $b['request_id'] );
t_assert( $bv && 1 === $bv['count'] && 'basic' === $bv['level'] && array() === $bv['records'], 'SRC-08', 'Free owner: sees "1 record found" but no details' );
t_assert( null === TenantScreeningService::get_search_result( $owner_b, (int) $s['request_id'] ), 'SRC-09', 'Owner B cannot open owner C\'s search result (IDOR)' );

qa_clear_rate( $owner_c );
t_assert( 'missing_attestation' === ( TenantScreeningService::search_applicant( $owner_c, array_merge( $search_input, array( 'applicant_consent' => '' ) ) )['error'] ?? '' ), 'SRC-10', 'Applicant consent certification required' );
qa_clear_rate( $owner_c );
t_assert( ! empty( TenantScreeningService::search_applicant( $owner_c, array_merge( $search_input, array( 'property_id' => $prop_c_pending ) ) )['error'] ), 'SRC-11', 'Unverified property cannot be used for screening' );
qa_clear_rate( $owner_c );
t_assert( ! empty( TenantScreeningService::search_applicant( $owner_c, array_merge( $search_input, array( 'property_id' => $prop_a ) ) )['error'] ), 'SRC-12', 'Another owner\'s property cannot be used (IDOR)' );
t_assert( 'unauthorized' === ( TenantScreeningService::search_applicant( $tenant, $search_input )['error'] ?? '' ), 'SRC-13', 'Tenants cannot run screening' );
t_assert( 'unauthorized' === ( TenantScreeningService::search_applicant( $admin, $search_input )['error'] ?? '' ), 'SRC-14', 'Administrators cannot run owner screening' );
$req = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$P}ft_screening_requests WHERE id = %d", $s['request_id'] ) );
t_assert( $req && $ssn_serial === $req->ssn_last_four && 64 === strlen( (string) $req->applicant_token_hash ) && str_starts_with( (string) $req->applicant_name_enc, 'pii1:' ) && 1 === (int) $req->match_found, 'SRC-15', 'Screening request recorded with token, last four, encrypted name, match flags' );
$aud = (string) $wpdb->get_var( $wpdb->prepare( "SELECT details_json FROM {$P}ft_audit_log WHERE action = 'tenant_screening_search' AND object_id = %d", $s['request_id'] ) );
t_assert( '' !== $aud && false === stripos( $aud, 'Jordan' ) && false === strpos( $aud, $ssn_serial ), 'SRC-16', 'Search audit contains no applicant name or SSN digits' );

// ------------------------------------------------------------ 5. Disputes, tenant transparency, withdrawal, rejection
$wpdb->insert( $P . 'ft_tenant_disputes', array( 'report_id' => $rid, 'tenant_id' => $tenant, 'dispute_reason' => 'already_paid', 'statement' => 'QA dispute', 'status' => 'pending', 'created_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ) );
t_assert( 'open' === ( TenantScreeningService::get_search_result( $owner_c, (int) $s['request_id'] )['records'][0]['dispute'] ?? '' ), 'DSP-01', 'Open tenant dispute is shown to the searching owner' );
$assoc = ReportVisibilityService::associate_tenant( $rid, $tenant, 'QA identity confirmed', '', $admin );
t_assert( ! empty( $assoc['success'] ), 'DSP-02', 'Administrator links the report to the tenant\'s account (manual association)' );
qa_clear_rate( $owner_c );
TenantScreeningService::search_applicant( $owner_c, $search_input );
t_assert( (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$P}ft_notifications WHERE user_id = %d AND title = 'Screening Inquiry'", $tenant ) ), 'DSP-03', 'Associated tenant is notified when an owner\'s search includes their record' );
TenantScreeningService::set_screening_disclosure( $rid, false, $admin );
qa_clear_rate( $owner_c );
$w = TenantScreeningService::search_applicant( $owner_c, $search_input );
t_assert( 0 === TenantScreeningService::get_search_result( $owner_c, (int) $w['request_id'] )['count'], 'DSP-04', 'Withdrawn report disappears from new searches' );
t_assert( 0 === TenantScreeningService::get_search_result( $owner_c, (int) $s['request_id'] )['count'], 'DSP-05', 'Withdrawal also hides it from previously saved results (re-checked at view time)' );
qa_clear_rate( $owner_a );
$r2 = IncidentService::record_incident_report( $owner_a, $base + array( 'tenant_first_name' => $first, 'tenant_last_name' => $last, 'tenant_ssn' => $ssn, 'identity_attestation' => '1' ) );
$rid2 = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(id) FROM {$P}ft_incident_reports WHERE owner_id = %d", $owner_a ) );
IncidentReviewService::decide_incident_report( $rid2, $admin, 'reject', array_key_first( (array) ( IncidentReviewService::get_incident_for_review( $rid2, $admin )['rejection_reasons'] ?? array( 'insufficient_evidence' => 1 ) ) ) );
$wpdb->update( $P . 'ft_incident_reports', array( 'screening_disclosure' => 1 ), array( 'id' => $rid2 ) ); // forced, simulating a bad DB edit
qa_clear_rate( $owner_c );
$x = TenantScreeningService::search_applicant( $owner_c, $search_input );
t_assert( 0 === TenantScreeningService::get_search_result( $owner_c, (int) $x['request_id'] )['count'], 'DSP-06', 'A rejected report never appears, even if its release flag is set' );

// ------------------------------------------------------------ 6. Real HTTP: owner search form -> dashboard result
TenantScreeningService::set_screening_disclosure( $rid, true, $admin );
qa_clear_rate( $owner_c );
$exp = time() + 3600;
$tok = WP_Session_Tokens::get_instance( $owner_c )->create( $exp );
$ck  = LOGGED_IN_COOKIE . '=' . rawurlencode( wp_generate_auth_cookie( $owner_c, $exp, 'logged_in', $tok ) );
$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $owner_c, $exp, 'logged_in', $tok );
wp_set_current_user( $owner_c );
$nonce = wp_create_nonce( 'ft_tenant_search_' . $owner_c );
$resp  = wp_remote_post( 'http://localhost/fraudtenant-qa/wp-admin/admin-post.php', array( 'redirection' => 0, 'timeout' => 60, 'headers' => array( 'Cookie' => $ck ), 'body' => array_merge( $search_input, array( 'action' => 'ft_owner_tenant_search', 'ft_tenant_search_nonce' => $nonce ) ) ) );
$loc   = (string) wp_remote_retrieve_header( $resp, 'location' );
t_assert( 302 === (int) wp_remote_retrieve_response_code( $resp ) && false !== strpos( $loc, 'screening_request=' ) && false === strpos( $loc, $ssn_serial ) && false === strpos( $loc, 'Jordan' ), 'HTTP-01', 'Form search redirects to the result; URL carries only a request id (no PII)' );
$page = wp_remote_retrieve_body( wp_remote_get( $loc, array( 'headers' => array( 'Cookie' => $ck ), 'timeout' => 60 ) ) );
t_assert( false !== strpos( $page, 'ft-result-record' ) && false !== strpos( $page, 'Unpaid Rent / Default' ) && false === strpos( $page, $ssn ) && false === strpos( $page, $ssn_digits ), 'HTTP-02', 'Owner dashboard renders the record card; SSN never rendered' );
$bad = wp_remote_post( 'http://localhost/fraudtenant-qa/wp-admin/admin-post.php', array( 'redirection' => 0, 'timeout' => 60, 'headers' => array( 'Cookie' => $ck ), 'body' => array_merge( $search_input, array( 'action' => 'ft_owner_tenant_search', 'ft_tenant_search_nonce' => 'bad' ) ) ) );
t_assert( 403 === (int) wp_remote_retrieve_response_code( $bad ), 'HTTP-03', 'Search with an invalid nonce is refused (403)' );

// ------------------------------------------------------------ 7. Revoking the attestation locks search again
ComplianceService::set_gate( 'owner_disclosure', false, $admin );
qa_clear_rate( $owner_c );
t_assert( 'screening_unavailable' === ( TenantScreeningService::search_applicant( $owner_c, $search_input )['error'] ?? '' ), 'GATE-08', 'Revoking the BLK-03 attestation immediately disables owner search' );
ComplianceService::set_gate( 'owner_disclosure', true, $admin, 'QA synthetic environment only — no real people or data.' );

t_summary( 'Identity Screening' );
