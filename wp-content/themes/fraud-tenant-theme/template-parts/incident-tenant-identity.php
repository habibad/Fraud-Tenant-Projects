<?php
/**
 * Incident form: identity of the tenant the incident is about.
 * Only an HMAC token, last four digits and an encrypted name are stored server-side.
 *
 * @package FraudTenantTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$ft_in = 'width:100%; padding:9px 12px; border:1px solid #CBD5E1; border-radius:6px; font-size:13px; color:var(--dash-navy); background:#FFFFFF;';
$ft_lb = 'display:block; font-size:13px; font-weight:600; color:var(--dash-navy); margin-bottom:6px;';
?>
<fieldset class="ft-incident-tenant" style="border:1px solid #E2E8F0; border-radius:8px; padding:14px; margin:0 0 14px;">
	<legend style="font-size:13px; font-weight:700; color:var(--dash-navy); padding:0 6px;"><?php esc_html_e( 'Tenant this report is about', 'fraud-tenant-theme' ); ?></legend>
	<div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
		<div>
			<label for="ftIncTenantFirst" style="<?php echo esc_attr( $ft_lb ); ?>"><?php esc_html_e( 'Legal first name', 'fraud-tenant-theme' ); ?> *</label>
			<input type="text" id="ftIncTenantFirst" name="tenant_first_name" required maxlength="100" autocomplete="off" style="<?php echo esc_attr( $ft_in ); ?>" />
		</div>
		<div>
			<label for="ftIncTenantLast" style="<?php echo esc_attr( $ft_lb ); ?>"><?php esc_html_e( 'Legal last name', 'fraud-tenant-theme' ); ?> *</label>
			<input type="text" id="ftIncTenantLast" name="tenant_last_name" required maxlength="100" autocomplete="off" style="<?php echo esc_attr( $ft_in ); ?>" />
		</div>
	</div>
	<div style="margin-bottom:10px;">
		<label for="ftIncTenantSsn" style="<?php echo esc_attr( $ft_lb ); ?>"><?php esc_html_e( 'Tenant SSN (from the rental application)', 'fraud-tenant-theme' ); ?> *</label>
		<input type="password" id="ftIncTenantSsn" name="tenant_ssn" required inputmode="numeric" pattern="\d{3}-?\d{2}-?\d{4}" maxlength="11" autocomplete="off" placeholder="123-45-6789" style="<?php echo esc_attr( $ft_in ); ?>" />
		<small style="display:block; font-size:11px; color:var(--dash-muted); margin-top:4px;"><?php esc_html_e( 'The full SSN is never stored. Only a one-way protected token and the last 4 digits are kept, so other owners can match this tenant only with the applicant\'s own SSN and consent.', 'fraud-tenant-theme' ); ?></small>
	</div>
	<label style="display:flex; gap:8px; align-items:flex-start; font-size:12px; color:var(--dash-navy);">
		<input type="checkbox" name="identity_attestation" value="1" required style="margin-top:2px;" />
		<span><?php esc_html_e( 'I confirm these identity details come from this tenant\'s own rental application or lease for the selected property.', 'fraud-tenant-theme' ); ?></span>
	</label>
</fieldset>
