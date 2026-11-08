<?php
/**
 * Admin Command Center — Overview (database-backed KPIs and work queues).
 *
 * @package FraudTenant\Admin
 */

defined( 'ABSPATH' ) || exit;

use FraudTenant\Admin\AdminConsole as C;
use FraudTenant\Services\ComplianceService;
use FraudTenant\Services\StripeCredentialService;

global $wpdb;
$p   = $wpdb->prefix;
$cnt = static fn( string $sql ): int => (int) $wpdb->get_var( $sql );

$owners      = $cnt( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = '{$p}capabilities' AND meta_value LIKE '%\"ft_owner\"%'" );
$tenants     = $cnt( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = '{$p}capabilities' AND meta_value LIKE '%\"ft_tenant\"%'" );
$unverified  = $cnt( "SELECT COUNT(*) FROM {$p}ft_user_profiles WHERE approval_status = 'email_unverified'" );
$suspended   = $cnt( "SELECT COUNT(*) FROM {$p}ft_user_profiles WHERE approval_status = 'suspended'" );
$props_pend  = $cnt( "SELECT COUNT(*) FROM {$p}ft_properties WHERE status IN ('pending_verification','info_requested') AND archived_at IS NULL" );
$inc_sub     = $cnt( "SELECT COUNT(*) FROM {$p}ft_incident_reports WHERE status = 'submitted'" );
$inc_clar    = $cnt( "SELECT COUNT(*) FROM {$p}ft_incident_reports WHERE status = 'needs_clarification'" );
$inc_appr    = $cnt( "SELECT COUNT(*) FROM {$p}ft_incident_reports WHERE status = 'approved'" );
$exceptions  = $cnt( "SELECT COUNT(*) FROM {$p}ft_incident_reports WHERE identity_match = 'exception'" );
$claims      = $cnt( "SELECT COUNT(*) FROM {$p}ft_identity_claims WHERE status = 'pending'" );
$identities  = $cnt( "SELECT COUNT(*) FROM {$p}ft_tenant_identities" );
$claimed     = $cnt( "SELECT COUNT(*) FROM {$p}ft_tenant_identities WHERE user_id IS NOT NULL" );
$disputes    = $cnt( "SELECT COUNT(*) FROM {$p}ft_tenant_disputes WHERE status IN ('pending','under_review')" );
$mode        = StripeCredentialService::get_active_mode();
$livemode    = 'live' === $mode ? 1 : 0;
$subs        = $wpdb->get_results( $wpdb->prepare( "SELECT pl.billing_interval, pl.price_amount, COUNT(*) AS n FROM {$p}ft_subscriptions s INNER JOIN {$p}ft_membership_plans pl ON pl.id = s.plan_id WHERE s.status = 'active' AND s.livemode = %d AND s.current_period_end > UTC_TIMESTAMP() GROUP BY pl.id", $livemode ) ) ?: array();
$active_subs = 0;
$mrr         = 0.0;
foreach ( $subs as $s ) {
	$active_subs += (int) $s->n;
	$mrr         += (int) $s->n * ( 'yearly' === $s->billing_interval ? (float) $s->price_amount / 12 : (float) $s->price_amount );
}
$searches = $cnt( "SELECT COUNT(*) FROM {$p}ft_screening_requests WHERE search_date > UTC_TIMESTAMP() - INTERVAL 30 DAY" );
$gate     = ComplianceService::status();

C::head( __( 'Command Center', 'fraud-tenant-core' ), __( 'Live operational picture of the Fraud Tenant platform. Every figure comes from the database.', 'fraud-tenant-core' ) );

$kpis = array(
	array( __( 'Property owners', 'fraud-tenant-core' ), $owners, 'owners', '' ),
	array( __( 'Tenants', 'fraud-tenant-core' ), $tenants, 'tenants', '' ),
	/* translators: %d: count */
	array( __( 'Unverified accounts', 'fraud-tenant-core' ), $unverified, 'accounts', sprintf( __( '%d suspended', 'fraud-tenant-core' ), $suspended ) ),
	array( __( 'Properties awaiting verification', 'fraud-tenant-core' ), $props_pend, 'properties', '' ),
	/* translators: %d: count */
	array( __( 'Reports awaiting review', 'fraud-tenant-core' ), $inc_sub, 'incidents', sprintf( __( '%d need clarification', 'fraud-tenant-core' ), $inc_clar ) ),
	array( __( 'Approved reports', 'fraud-tenant-core' ), $inc_appr, 'disclosure', '' ),
	array( __( 'Identity exceptions', 'fraud-tenant-core' ), $exceptions, 'exceptions', '' ),
	array( __( 'Pending account claims', 'fraud-tenant-core' ), $claims, 'claims', '' ),
	/* translators: %d: count */
	array( __( 'Tenant identities', 'fraud-tenant-core' ), $identities, 'identities', sprintf( __( '%d claimed by an account', 'fraud-tenant-core' ), $claimed ) ),
	array( __( 'Open disputes', 'fraud-tenant-core' ), $disputes, 'disputes', '' ),
	/* translators: %s: mode */
	array( __( 'Active subscriptions', 'fraud-tenant-core' ), $active_subs, 'subscriptions', sprintf( __( '%s mode', 'fraud-tenant-core' ), ucfirst( $mode ) ) ),
	array( __( 'Screening searches (30 days)', 'fraud-tenant-core' ), $searches, 'screening', '' ),
);
?>
<div class="ft-grid ft-grid-4">
	<?php foreach ( $kpis as $k ) : ?>
		<a class="ft-kpi" href="<?php echo esc_url( C::url( $k[2] ) ); ?>">
			<span class="ft-kpi-label"><?php echo esc_html( $k[0] ); ?></span>
			<strong class="ft-kpi-value"><?php echo esc_html( number_format_i18n( $k[1] ) ); ?></strong>
			<?php if ( $k[3] ) : ?><span class="ft-kpi-sub"><?php echo esc_html( $k[3] ); ?></span><?php endif; ?>
		</a>
	<?php endforeach; ?>
	<div class="ft-kpi">
		<span class="ft-kpi-label"><?php esc_html_e( 'Monthly recurring revenue', 'fraud-tenant-core' ); ?></span>
		<strong class="ft-kpi-value">$<?php echo esc_html( number_format_i18n( $mrr, 2 ) ); ?></strong>
		<span class="ft-kpi-sub"><?php esc_html_e( 'Active subscriptions in the current Stripe mode', 'fraud-tenant-core' ); ?></span>
	</div>
</div>

<div class="ft-grid ft-grid-2">
	<section class="ft-card">
		<div class="ft-card-head"><h2><?php esc_html_e( 'Work queues', 'fraud-tenant-core' ); ?></h2></div>
		<ul class="ft-list">
			<li><a href="<?php echo esc_url( C::url( 'incidents', array( 'status' => 'submitted' ) ) ); ?>"><?php esc_html_e( 'Review submitted incident reports', 'fraud-tenant-core' ); ?></a> <?php echo C::badge( 'submitted', (string) $inc_sub ); // phpcs:ignore ?></li>
			<li><a href="<?php echo esc_url( C::url( 'exceptions' ) ); ?>"><?php esc_html_e( 'Resolve identity match exceptions', 'fraud-tenant-core' ); ?></a> <?php echo C::badge( $exceptions ? 'exception' : 'no', (string) $exceptions ); // phpcs:ignore ?></li>
			<li><a href="<?php echo esc_url( C::url( 'claims' ) ); ?>"><?php esc_html_e( 'Decide tenant identity claims', 'fraud-tenant-core' ); ?></a> <?php echo C::badge( $claims ? 'pending' : 'no', (string) $claims ); // phpcs:ignore ?></li>
			<li><a href="<?php echo esc_url( C::url( 'disputes' ) ); ?>"><?php esc_html_e( 'Review tenant disputes', 'fraud-tenant-core' ); ?></a> <?php echo C::badge( $disputes ? 'pending' : 'no', (string) $disputes ); // phpcs:ignore ?></li>
			<li><a href="<?php echo esc_url( C::url( 'properties', array( 'status' => 'pending_verification' ) ) ); ?>"><?php esc_html_e( 'Verify property ownership', 'fraud-tenant-core' ); ?></a> <?php echo C::badge( $props_pend ? 'pending' : 'no', (string) $props_pend ); // phpcs:ignore ?></li>
		</ul>
	</section>
	<section class="ft-card">
		<div class="ft-card-head"><h2><?php esc_html_e( 'Security & compliance gates', 'fraud-tenant-core' ); ?></h2><a href="<?php echo esc_url( C::url( 'security' ) ); ?>"><?php esc_html_e( 'Details', 'fraud-tenant-core' ); ?></a></div>
		<ul class="ft-list">
			<li><?php esc_html_e( 'SSN match key (BLK-04)', 'fraud-tenant-core' ); ?> <?php echo C::badge( $gate['match_key_ready'] ? 'active' : 'failed', $gate['match_key_ready'] ? __( 'Configured', 'fraud-tenant-core' ) : __( 'Missing — matching disabled', 'fraud-tenant-core' ) ); // phpcs:ignore ?></li>
			<li><?php esc_html_e( 'Field encryption key (BLK-08)', 'fraud-tenant-core' ); ?> <?php echo C::badge( $gate['encryption']['available'] ? 'active' : 'failed', $gate['encryption']['available'] ? __( 'Verified', 'fraud-tenant-core' ) : __( 'Missing — identity data locked', 'fraud-tenant-core' ) ); // phpcs:ignore ?></li>
			<li><?php esc_html_e( 'Legal release sign-off (BLK-03)', 'fraud-tenant-core' ); ?> <?php echo C::badge( ! $gate['legal_release_required'] || ! empty( $gate['owner_disclosure']['enabled'] ) ? 'active' : 'pending', $gate['legal_release_required'] ? ( ! empty( $gate['owner_disclosure']['enabled'] ) ? __( 'Recorded', 'fraud-tenant-core' ) : __( 'Required before production screening', 'fraud-tenant-core' ) ) : __( 'Not required outside production', 'fraud-tenant-core' ) ); // phpcs:ignore ?></li>
			<li><?php esc_html_e( 'Owner screening', 'fraud-tenant-core' ); ?> <?php echo C::badge( $gate['owner_search_ready'] ? 'active' : 'failed', $gate['owner_search_ready'] ? __( 'Available', 'fraud-tenant-core' ) : __( 'Unavailable (fail closed)', 'fraud-tenant-core' ) ); // phpcs:ignore ?></li>
		</ul>
		<p class="ft-muted"><?php esc_html_e( 'The platform provides reviewed records only. It never scores, ranks, blacklists or decides on tenants; property owners make their own rental decisions.', 'fraud-tenant-core' ); ?></p>
	</section>
</div>
