<?php
/**
 * Owner — Overview (database-backed KPIs, getting-started steps, recent activity).
 *
 * @package FraudTenantTheme
 */

defined( 'ABSPATH' ) || exit;

$d     = $args['data'];
$m     = $d['membership'];
$base  = $args['base'];
$first = $args['user']->first_name ?: $args['user']->display_name;
$limit = $d['property_limit'];
$props = count( $d['properties'] );
$ver   = $d['properties_by_status']['verified'];
$paid  = ! empty( $m['has_active_subscription'] );
?>
<section class="ft-hero">
	<div>
		<?php /* translators: %s: first name */ ?>
		<h1><?php echo esc_html( sprintf( __( 'Welcome back, %s', 'fraud-tenant-theme' ), $first ) ); ?></h1>
		<p><?php esc_html_e( 'Review applicants\' rental history, record factual incidents, and manage your rental properties.', 'fraud-tenant-theme' ); ?></p>
	</div>
	<div class="ft-actions">
		<span class="ft-plan-pill"><?php echo esc_html( $m['plan_name'] ); ?></span>
		<a class="ft-btn ft-btn-primary" href="<?php echo esc_url( ft_app_url( $base, 'screening' ) ); ?>"><?php esc_html_e( 'Screen a tenant', 'fraud-tenant-theme' ); ?></a>
		<?php if ( ! $paid ) : ?><a class="ft-btn ft-btn-secondary" href="<?php echo esc_url( ft_app_url( $base, 'membership' ) ); ?>"><?php esc_html_e( 'Upgrade to Standard', 'fraud-tenant-theme' ); ?></a><?php endif; ?>
	</div>
</section>

<?php if ( $d['over_limit'] ) : ?>
	<div class="ft-alert is-warning"><?php esc_html_e( 'Your Free plan allows 1 active property. Your existing properties are kept, but you cannot add or restore properties until you are within the limit or upgrade.', 'fraud-tenant-theme' ); ?></div>
<?php endif; ?>
<?php if ( ! $d['identity_ready'] ) : ?>
	<div class="ft-alert is-info"><?php esc_html_e( 'Tenant screening and incident reporting are not yet available on this platform. Property management is available.', 'fraud-tenant-theme' ); ?></div>
<?php endif; ?>

<div class="ft-grid ft-grid-4">
	<a class="ft-kpi" href="<?php echo esc_url( ft_app_url( $base, 'properties' ) ); ?>">
		<span class="ft-kpi-label"><?php esc_html_e( 'Active properties', 'fraud-tenant-theme' ); ?></span>
		<strong class="ft-kpi-value"><?php echo (int) $props; ?></strong>
		<?php /* translators: %d: limit */ ?>
		<span class="ft-kpi-sub"><?php echo -1 === $limit ? esc_html__( 'Unlimited on your plan', 'fraud-tenant-theme' ) : esc_html( sprintf( __( 'Plan limit: %d', 'fraud-tenant-theme' ), $limit ) ); ?></span>
	</a>
	<a class="ft-kpi" href="<?php echo esc_url( ft_app_url( $base, 'verification' ) ); ?>">
		<span class="ft-kpi-label"><?php esc_html_e( 'Verified properties', 'fraud-tenant-theme' ); ?></span>
		<strong class="ft-kpi-value"><?php echo (int) $ver; ?></strong>
		<?php /* translators: %d: pending count */ ?>
		<span class="ft-kpi-sub"><?php echo esc_html( sprintf( __( '%d awaiting verification', 'fraud-tenant-theme' ), $d['properties_by_status']['pending_verification'] + $d['properties_by_status']['info_requested'] ) ); ?></span>
	</a>
	<a class="ft-kpi" href="<?php echo esc_url( ft_app_url( $base, 'screening' ) ); ?>">
		<span class="ft-kpi-label"><?php esc_html_e( 'Screening searches', 'fraud-tenant-theme' ); ?></span>
		<strong class="ft-kpi-value"><?php echo (int) $d['searches_30d']; ?></strong>
		<span class="ft-kpi-sub"><?php esc_html_e( 'Last 30 days', 'fraud-tenant-theme' ); ?></span>
	</a>
	<a class="ft-kpi" href="<?php echo esc_url( ft_app_url( $base, 'reports' ) ); ?>">
		<span class="ft-kpi-label"><?php esc_html_e( 'Incident reports', 'fraud-tenant-theme' ); ?></span>
		<strong class="ft-kpi-value"><?php echo (int) count( $d['reports'] ); ?></strong>
		<?php /* translators: 1: under review, 2: approved */ ?>
		<span class="ft-kpi-sub"><?php echo esc_html( sprintf( __( '%1$d under review · %2$d approved', 'fraud-tenant-theme' ), $d['report_counts']['submitted'] + $d['report_counts']['needs_clarification'], $d['report_counts']['approved'] ) ); ?></span>
	</a>
</div>

<section class="ft-card">
	<div class="ft-card-head"><h2><?php esc_html_e( 'Getting started', 'fraud-tenant-theme' ); ?></h2></div>
	<ol class="ft-steps">
		<li class="<?php echo $props ? 'is-done' : 'is-current'; ?>"><strong>1.</strong> <a href="<?php echo esc_url( ft_app_url( $base, 'add-property' ) ); ?>"><?php esc_html_e( 'Add a rental property', 'fraud-tenant-theme' ); ?></a></li>
		<li class="<?php echo $ver ? 'is-done' : ( $props ? 'is-current' : '' ); ?>"><strong>2.</strong> <a href="<?php echo esc_url( ft_app_url( $base, 'verification' ) ); ?>"><?php esc_html_e( 'Get ownership verified', 'fraud-tenant-theme' ); ?></a></li>
		<li class="<?php echo $d['searches_30d'] ? 'is-done' : ( $ver ? 'is-current' : '' ); ?>"><strong>3.</strong> <a href="<?php echo esc_url( ft_app_url( $base, 'screening' ) ); ?>"><?php esc_html_e( 'Screen applicants', 'fraud-tenant-theme' ); ?></a></li>
		<li class="<?php echo count( $d['reports'] ) ? 'is-done' : ( $paid && $ver ? 'is-current' : '' ); ?>"><strong>4.</strong> <a href="<?php echo esc_url( ft_app_url( $base, 'report' ) ); ?>"><?php esc_html_e( 'Report incidents (Standard)', 'fraud-tenant-theme' ); ?></a></li>
	</ol>
	<p class="ft-note"><?php esc_html_e( 'Fraud Tenant provides reviewed records only. It never scores or ranks tenants and never makes rental decisions — you decide.', 'fraud-tenant-theme' ); ?></p>
</section>

<div class="ft-grid ft-grid-2">
	<section class="ft-card">
		<div class="ft-card-head"><h2><?php esc_html_e( 'Recent incident reports', 'fraud-tenant-theme' ); ?></h2><a href="<?php echo esc_url( ft_app_url( $base, 'reports' ) ); ?>"><?php esc_html_e( 'View all', 'fraud-tenant-theme' ); ?></a></div>
		<?php if ( ! $d['reports'] ) : ft_empty( __( 'You have not submitted any incident reports.', 'fraud-tenant-theme' ) ); else : ?>
			<ul class="ft-list">
				<?php foreach ( array_slice( $d['reports'], 0, 5 ) as $r ) : ?>
					<li><a href="<?php echo esc_url( ft_app_url( $base, 'reports', array( 'report_id' => $r->id ) ) ); ?>"><?php echo esc_html( $r->reference . ' · ' . $r->category_label ); ?></a> <?php echo ft_badge( (string) $r->status ); // phpcs:ignore ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>
	<section class="ft-card">
		<div class="ft-card-head"><h2><?php esc_html_e( 'Recent screening searches', 'fraud-tenant-theme' ); ?></h2><a href="<?php echo esc_url( ft_app_url( $base, 'screening' ) ); ?>"><?php esc_html_e( 'View all', 'fraud-tenant-theme' ); ?></a></div>
		<?php if ( ! $d['search_history'] ) : ft_empty( __( 'No searches yet.', 'fraud-tenant-theme' ) ); else : ?>
			<ul class="ft-list">
				<?php foreach ( array_slice( $d['search_history'], 0, 5 ) as $s ) : ?>
					<li><a href="<?php echo esc_url( ft_app_url( $base, 'screening', array( 'screening_request' => $s['id'] ) ) ); ?>"><?php echo esc_html( $s['applicant'] ); ?></a> <?php echo $s['found'] ? ft_badge( 'warning', sprintf( /* translators: %d: count */ _n( '%d record', '%d records', $s['count'], 'fraud-tenant-theme' ), $s['count'] ) ) : ft_badge( 'no', __( 'No records', 'fraud-tenant-theme' ) ); // phpcs:ignore ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>
</div>
