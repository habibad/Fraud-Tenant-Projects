<?php
/**
 * Owner — Submit Incident Report (Standard members; enforced server-side by IncidentService).
 *
 * @package FraudTenantTheme
 */

defined( 'ABSPATH' ) || exit;

use FraudTenant\Services\IncidentService;

$d     = $args['data'];
$base  = $args['base'];
$old   = ft_old_input();
$props = array_values( array_filter( $d['properties'], static fn( $p ) => 'verified' === $p->status ) );
?>
<header class="ft-page-head"><div><h1><?php esc_html_e( 'Submit an incident report', 'fraud-tenant-theme' ); ?></h1><p><?php esc_html_e( 'Record a factual rental incident. Reports are reviewed by our team before anyone can see them, and the tenant can dispute them. Do not include opinions, accusations or unrelated personal information.', 'fraud-tenant-theme' ); ?></p></div></header>

<?php if ( ! $d['identity_ready'] ) : ?>
	<div class="ft-alert is-info"><?php esc_html_e( 'Incident reporting is not available on this platform yet.', 'fraud-tenant-theme' ); ?></div>
	<?php return; ?>
<?php endif; ?>
<?php if ( ! $d['can_submit_incident'] ) : ?>
	<div class="ft-lock">
		<?php echo ft_icon( 'fileplus' ); // phpcs:ignore ?>
		<div><strong><?php esc_html_e( 'Incident reporting is a Standard membership feature', 'fraud-tenant-theme' ); ?></strong>
			<p class="ft-muted"><?php esc_html_e( 'Upgrade to Standard ($29/month or $290/year) to submit incident reports and see full screening details.', 'fraud-tenant-theme' ); ?></p>
			<a class="ft-btn ft-btn-primary" href="<?php echo esc_url( ft_app_url( $base, 'membership' ) ); ?>"><?php esc_html_e( 'Upgrade to Standard', 'fraud-tenant-theme' ); ?></a></div>
	</div>
	<?php return; ?>
<?php endif; ?>
<?php if ( ! $props ) : ?>
	<div class="ft-alert is-warning"><?php esc_html_e( 'Reports must relate to one of your verified properties.', 'fraud-tenant-theme' ); ?> <a href="<?php echo esc_url( ft_app_url( $base, 'verification' ) ); ?>"><?php esc_html_e( 'Check verification status', 'fraud-tenant-theme' ); ?></a></div>
	<?php return; ?>
<?php endif; ?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="ft-form" autocomplete="off">
	<input type="hidden" name="action" value="ft_submit_incident">
	<?php wp_nonce_field( 'ft_submit_incident_action', 'ft_incident_nonce' ); ?>
	<div class="ft-grid ft-grid-2">
		<section class="ft-card">
			<div class="ft-card-head"><h2><?php esc_html_e( '1. Tenant', 'fraud-tenant-theme' ); ?></h2></div>
			<p class="ft-help"><?php esc_html_e( 'Use the tenant\'s legal name and SSN exactly as on their rental application or lease. They are used only to match the report to the correct tenant.', 'fraud-tenant-theme' ); ?></p>
			<div class="ft-row">
				<div><label class="ft-label" for="ftIncFirst"><?php esc_html_e( 'Legal first name', 'fraud-tenant-theme' ); ?></label><input class="ft-input" id="ftIncFirst" name="tenant_first_name" required maxlength="100" value="<?php echo esc_attr( (string) ( $old['tenant_first_name'] ?? '' ) ); ?>"></div>
				<div><label class="ft-label" for="ftIncLast"><?php esc_html_e( 'Legal last name', 'fraud-tenant-theme' ); ?></label><input class="ft-input" id="ftIncLast" name="tenant_last_name" required maxlength="100" value="<?php echo esc_attr( (string) ( $old['tenant_last_name'] ?? '' ) ); ?>"></div>
			</div>
			<label class="ft-label" for="ftIncSsn"><?php esc_html_e( 'Tenant Social Security Number', 'fraud-tenant-theme' ); ?></label>
			<div class="ft-filters" style="margin:0"><input class="ft-input" type="password" id="ftIncSsn" name="tenant_ssn" data-ft-ssn required inputmode="numeric" pattern="\d{3}-?\d{2}-?\d{4}" placeholder="123-45-6789" aria-describedby="ftIncSsnHelp"><button type="button" class="ft-btn ft-btn-ghost ft-btn-sm" data-ft-ssn-toggle="ftIncSsn" data-show="<?php esc_attr_e( 'Show', 'fraud-tenant-theme' ); ?>" data-hide="<?php esc_attr_e( 'Hide', 'fraud-tenant-theme' ); ?>" aria-pressed="false"><?php esc_html_e( 'Show', 'fraud-tenant-theme' ); ?></button></div>
			<p class="ft-help" id="ftIncSsnHelp"><?php esc_html_e( 'Never stored: the SSN becomes a keyed match token on our server. Only the last 4 digits are kept.', 'fraud-tenant-theme' ); ?></p>
			<label class="ft-check"><input type="checkbox" name="identity_attestation" value="1" required> <span><?php esc_html_e( 'These identity details come from the tenant\'s own rental application or lease.', 'fraud-tenant-theme' ); ?></span></label>
		</section>
		<section class="ft-card">
			<div class="ft-card-head"><h2><?php esc_html_e( '2. Incident', 'fraud-tenant-theme' ); ?></h2></div>
			<label class="ft-label" for="ft-ip"><?php esc_html_e( 'Property', 'fraud-tenant-theme' ); ?></label>
			<select class="ft-select" id="ft-ip" name="property_id" required><?php foreach ( $props as $p ) : ?><option value="<?php echo (int) $p->id; ?>" <?php selected( (int) ( $old['property_id'] ?? 0 ), (int) $p->id ); ?>><?php echo esc_html( ( $p->title ?: $p->address_street ) . ' — ' . $p->city . ', ' . $p->state ); ?></option><?php endforeach; ?></select>
			<div class="ft-row">
				<div><label class="ft-label" for="ft-ic"><?php esc_html_e( 'Category', 'fraud-tenant-theme' ); ?></label><select class="ft-select" id="ft-ic" name="category" required><?php foreach ( IncidentService::ALLOWED_CATEGORIES as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( (string) ( $old['category'] ?? '' ), $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></div>
				<div><label class="ft-label" for="ft-id"><?php esc_html_e( 'Incident date', 'fraud-tenant-theme' ); ?></label><input class="ft-input" type="date" id="ft-id" name="incident_date" required max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" value="<?php echo esc_attr( (string) ( $old['incident_date'] ?? '' ) ); ?>"></div>
				<div><label class="ft-label" for="ft-ia"><?php esc_html_e( 'Amount, if applicable (USD)', 'fraud-tenant-theme' ); ?></label><input class="ft-input" type="number" min="0" step="0.01" id="ft-ia" name="amount_involved" value="<?php echo esc_attr( (string) ( $old['amount_involved'] ?? '' ) ); ?>"></div>
			</div>
			<label class="ft-label" for="ft-idesc"><?php esc_html_e( 'Factual description', 'fraud-tenant-theme' ); ?></label>
			<textarea class="ft-textarea" id="ft-idesc" name="factual_description" required minlength="10" maxlength="5000" rows="5" placeholder="<?php esc_attr_e( 'What happened, when, and what the lease or ledger shows. Facts only.', 'fraud-tenant-theme' ); ?>"><?php echo esc_textarea( (string) ( $old['factual_description'] ?? '' ) ); ?></textarea>
			<label class="ft-label" for="ft-iev"><?php esc_html_e( 'Supporting evidence (optional — PDF, JPG or PNG, max 10 MB)', 'fraud-tenant-theme' ); ?></label>
			<input class="ft-input" type="file" id="ft-iev" name="incident_evidence" accept=".pdf,.png,.jpg,.jpeg">
			<p class="ft-help"><?php esc_html_e( 'Evidence is visible only to you and the review team.', 'fraud-tenant-theme' ); ?></p>
		</section>
	</div>
	<section class="ft-card">
		<label class="ft-check"><input type="checkbox" name="owner_attestation" value="1" required> <span><?php esc_html_e( 'I attest that this report is factual and accurate to the best of my knowledge, and I understand false reports may lead to account termination.', 'fraud-tenant-theme' ); ?></span></label>
		<button class="ft-btn ft-btn-primary" type="submit"><?php esc_html_e( 'Submit for review', 'fraud-tenant-theme' ); ?></button>
	</section>
</form>
