<?php
/**
 * Owner dashboard: identity-matched tenant screening (entry point, result, history).
 *
 * Expects $args: owner_id (int).
 *
 * @package FraudTenantTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use FraudTenant\Services\ComplianceService;
use FraudTenant\Services\MembershipService;
use FraudTenant\Services\ScreeningService;
use FraudTenant\Services\TenantScreeningService;

$ft_owner   = (int) ( $args['owner_id'] ?? 0 );
$ft_ready   = ComplianceService::owner_search_ready();
$ft_full    = MembershipService::can_access_feature( $ft_owner, 'full_reports' );
$ft_req_id  = isset( $_GET['screening_request'] ) ? absint( $_GET['screening_request'] ) : 0;
$ft_result  = $ft_req_id ? TenantScreeningService::get_search_result( $ft_owner, $ft_req_id ) : null;
$ft_props   = ScreeningService::get_owner_verified_properties( $ft_owner );
?>
<div class="search-tenants-card" id="search-tenants">
	<div class="search-tenants-header">
		<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#14263E" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
		<h3><?php esc_html_e( 'Search Tenant Rental History', 'fraud-tenant-theme' ); ?></h3>
	</div>
	<p class="search-tenants-subtext">
		<?php
		echo $ft_full
			? esc_html__( 'Search an applicant by legal name and SSN (with their written consent). Standard plan: full summaries of reviewed records.', 'fraud-tenant-theme' )
			: esc_html__( 'Search an applicant by legal name and SSN (with their written consent). Free plan shows whether reviewed records exist; upgrade to Standard to see record details.', 'fraud-tenant-theme' );
		?>
	</p>

	<?php if ( ! $ft_ready ) : ?>
		<div class="ft-screening-unavailable" style="background:#FFFBEB; border:1px solid #FCD34D; border-radius:8px; padding:12px 14px; font-size:13px; color:#92400E;">
			<?php esc_html_e( 'Identity-based screening is not enabled on this platform yet. It opens once the platform administrator completes the required security and legal approvals.', 'fraud-tenant-theme' ); ?>
		</div>
	<?php elseif ( empty( $ft_props ) ) : ?>
		<div style="background:#FFFBEB; border:1px solid #FCD34D; border-radius:8px; padding:12px 14px; font-size:13px; color:#92400E;">
			<?php esc_html_e( 'You need at least one verified property before you can screen applicants for it.', 'fraud-tenant-theme' ); ?>
			<a href="<?php echo esc_url( home_url( '/owner-dashboard/properties/' ) ); ?>"><?php esc_html_e( 'Manage properties →', 'fraud-tenant-theme' ); ?></a>
		</div>
	<?php else : ?>
		<button type="button" class="search-ssn-btn ft-open-screening" data-ft-modal-target="ftScreeningModal"><?php esc_html_e( 'Search an Applicant', 'fraud-tenant-theme' ); ?></button>
	<?php endif; ?>

	<?php if ( $ft_result ) : ?>
		<div class="ft-screening-result" id="ftScreeningResult" style="margin-top:18px; border:1px solid <?php echo $ft_result['count'] ? '#F59E0B' : '#10B981'; ?>; border-radius:10px; padding:16px; background:<?php echo $ft_result['count'] ? '#FFFBEB' : '#ECFDF5'; ?>;">
			<div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:8px;">
				<strong style="color:#14263E;">
					<?php echo esc_html( sprintf( __( 'Result for %1$s (SSN ending %2$s)', 'fraud-tenant-theme' ), $ft_result['applicant'], $ft_result['ssn_last4'] ) ); ?>
				</strong>
				<span style="font-size:12px; color:#64748B;"><?php echo esc_html( mysql2date( 'M j, Y g:i a', get_date_from_gmt( $ft_result['searched_at'] ) ) . ' · ' . $ft_result['purpose'] ); ?></span>
			</div>
			<?php if ( 0 === $ft_result['count'] ) : ?>
				<p class="ft-result-none" style="margin:8px 0 0; font-size:13px; color:#065F46;"><?php esc_html_e( 'No reviewed incident records were found for this applicant on Fraud Tenant. This is not a guarantee about the applicant; it only reflects records reviewed and released on this platform.', 'fraud-tenant-theme' ); ?></p>
			<?php elseif ( 'basic' === $ft_result['level'] ) : ?>
				<p class="ft-result-basic" style="margin:8px 0 0; font-size:13px; color:#92400E;">
					<?php echo esc_html( sprintf( _n( '%d reviewed incident record was found for this applicant.', '%d reviewed incident records were found for this applicant.', $ft_result['count'], 'fraud-tenant-theme' ), $ft_result['count'] ) ); ?>
					<?php esc_html_e( 'Upgrade to Standard to view the record details.', 'fraud-tenant-theme' ); ?>
				</p>
				<button type="button" class="dashboard-btn-add" data-ft-modal-target="ftMembershipModal" style="margin-top:10px;"><?php esc_html_e( 'View Plans', 'fraud-tenant-theme' ); ?></button>
			<?php else : ?>
				<p style="margin:8px 0 10px; font-size:13px; color:#92400E;"><?php echo esc_html( sprintf( _n( '%d reviewed incident record found.', '%d reviewed incident records found.', $ft_result['count'], 'fraud-tenant-theme' ), $ft_result['count'] ) ); ?></p>
				<?php foreach ( $ft_result['records'] as $ft_rec ) : ?>
					<div class="ft-result-record" style="background:#FFFFFF; border:1px solid #E2E8F0; border-radius:8px; padding:12px; margin-bottom:8px; font-size:13px; color:#14263E;">
						<div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:6px;">
							<strong><?php echo esc_html( $ft_rec['category'] ); ?></strong>
							<span><?php echo esc_html( $ft_rec['month'] . ( $ft_rec['location'] ? ' · ' . $ft_rec['location'] : '' ) ); ?></span>
						</div>
						<?php if ( $ft_rec['amount'] > 0 ) : ?>
							<div style="margin-top:4px;"><?php echo esc_html( sprintf( __( 'Amount involved: $%s', 'fraud-tenant-theme' ), number_format( $ft_rec['amount'], 2 ) ) ); ?></div>
						<?php endif; ?>
						<p style="margin:6px 0; color:#475569;"><?php echo esc_html( $ft_rec['narrative'] ); ?></p>
						<div style="font-size:11px; color:#64748B;">
							<?php echo esc_html( sprintf( __( 'Reported %s · Reviewed and approved by Fraud Tenant', 'fraud-tenant-theme' ), $ft_rec['reported'] ) ); ?>
							<?php if ( 'open' === $ft_rec['dispute'] ) : ?>
								· <strong style="color:#B91C1C;"><?php esc_html_e( 'Disputed by the tenant — under review', 'fraud-tenant-theme' ); ?></strong>
							<?php elseif ( 'closed' === $ft_rec['dispute'] ) : ?>
								· <?php esc_html_e( 'A tenant dispute was reviewed', 'fraud-tenant-theme' ); ?>
							<?php endif; ?>
							<?php if ( $ft_rec['own_report'] ) : ?>
								· <em><?php esc_html_e( 'Filed by you', 'fraud-tenant-theme' ); ?></em>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
			<p style="margin:10px 0 0; font-size:11px; color:#64748B;"><?php esc_html_e( 'Records are owner-reported incidents reviewed by Fraud Tenant administrators, not findings of guilt. Do not make a decision based solely on this information; if you take adverse action, follow the notice obligations that apply to you.', 'fraud-tenant-theme' ); ?></p>
		</div>
	<?php endif; ?>

</div>

<?php if ( $ft_ready && ! empty( $ft_props ) ) : ?>
<div class="ft-modal-overlay" id="ftScreeningModal" role="dialog" aria-modal="true" aria-labelledby="ftScreeningTitle">
	<div class="ft-modal-box" style="max-width:560px;">
		<button type="button" class="ft-modal-close" aria-label="<?php esc_attr_e( 'Close', 'fraud-tenant-theme' ); ?>">&times;</button>
		<h3 class="ft-modal-title" id="ftScreeningTitle"><?php esc_html_e( 'Screen a Rental Applicant', 'fraud-tenant-theme' ); ?></h3>
		<p class="ft-modal-desc"><?php esc_html_e( 'Use the details exactly as written on the applicant\'s signed rental application. The SSN is checked against protected tokens and is never stored in full.', 'fraud-tenant-theme' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ft-tenant-search-form" autocomplete="off">
			<input type="hidden" name="action" value="ft_owner_tenant_search" />
			<?php wp_nonce_field( 'ft_tenant_search_' . $ft_owner, 'ft_tenant_search_nonce' ); ?>
			<?php $ft_in = 'width:100%; padding:9px 12px; border:1px solid #CBD5E1; border-radius:6px; font-size:13px; background:#FFFFFF;'; ?>
			<label for="ftScrProperty" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;"><?php esc_html_e( 'Property being applied for', 'fraud-tenant-theme' ); ?> *</label>
			<select id="ftScrProperty" name="property_id" required style="<?php echo esc_attr( $ft_in ); ?> margin-bottom:12px;">
				<?php foreach ( $ft_props as $ft_p ) : ?>
					<option value="<?php echo esc_attr( (string) $ft_p->id ); ?>"><?php echo esc_html( ( $ft_p->title ? $ft_p->title . ' — ' : '' ) . $ft_p->city . ', ' . $ft_p->state ); ?></option>
				<?php endforeach; ?>
			</select>
			<div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
				<div>
					<label for="ftScrFirst" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;"><?php esc_html_e( 'Applicant first name', 'fraud-tenant-theme' ); ?> *</label>
					<input type="text" id="ftScrFirst" name="applicant_first_name" required maxlength="100" style="<?php echo esc_attr( $ft_in ); ?>" />
				</div>
				<div>
					<label for="ftScrLast" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;"><?php esc_html_e( 'Applicant last name', 'fraud-tenant-theme' ); ?> *</label>
					<input type="text" id="ftScrLast" name="applicant_last_name" required maxlength="100" style="<?php echo esc_attr( $ft_in ); ?>" />
				</div>
			</div>
			<label for="ftScrSsn" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;"><?php esc_html_e( 'Applicant SSN', 'fraud-tenant-theme' ); ?> *</label>
			<input type="password" id="ftScrSsn" name="applicant_ssn" required inputmode="numeric" pattern="\d{3}-?\d{2}-?\d{4}" maxlength="11" placeholder="123-45-6789" style="<?php echo esc_attr( $ft_in ); ?> margin-bottom:12px;" />
			<label for="ftScrPurpose" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;"><?php esc_html_e( 'Permissible purpose', 'fraud-tenant-theme' ); ?> *</label>
			<select id="ftScrPurpose" name="purpose_reason" required style="<?php echo esc_attr( $ft_in ); ?> margin-bottom:12px;">
				<?php foreach ( ScreeningService::ALLOWED_PURPOSES as $ft_k => $ft_l ) : ?>
					<option value="<?php echo esc_attr( $ft_k ); ?>"><?php echo esc_html( $ft_l ); ?></option>
				<?php endforeach; ?>
			</select>
			<label style="display:flex; gap:8px; font-size:12px; margin-bottom:8px;"><input type="checkbox" name="applicant_consent" value="1" required /> <span><?php esc_html_e( 'I have the applicant\'s signed written consent to this screening.', 'fraud-tenant-theme' ); ?></span></label>
			<label style="display:flex; gap:8px; font-size:12px; margin-bottom:14px;"><input type="checkbox" name="permissible_purpose_attestation" value="1" required /> <span><?php esc_html_e( 'I certify this search is only for evaluating this applicant\'s tenancy at the selected property.', 'fraud-tenant-theme' ); ?></span></label>
			<button type="submit" class="dashboard-btn-add" style="width:100%; justify-content:center;"><?php esc_html_e( 'Run Screening', 'fraud-tenant-theme' ); ?></button>
		</form>
	</div>
</div>
<?php endif; ?>
