<?php
/**
 * Owner — Tenant Screening (identity search; free = limited result, Standard = reviewed details).
 *
 * @package FraudTenantTheme
 */

defined( 'ABSPATH' ) || exit;

use FraudTenant\Services\ScreeningService;
use FraudTenant\Services\TenantScreeningService;

$d        = $args['data'];
$base     = $args['base'];
$owner_id = $args['owner_id'];
$req_id   = absint( $_GET['screening_request'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
$result   = $req_id ? TenantScreeningService::get_search_result( $owner_id, $req_id ) : null;
?>
<header class="ft-page-head"><div><h1><?php esc_html_e( 'Tenant screening', 'fraud-tenant-theme' ); ?></h1><p><?php esc_html_e( 'Search an applicant\'s reviewed rental incident history by legal name and SSN. Records are factual reports reviewed by our team — you make the rental decision.', 'fraud-tenant-theme' ); ?></p></div></header>

<?php if ( $result ) : ?>
	<section class="ft-card" aria-live="polite">
		<div class="ft-card-head">
			<h2><?php echo esc_html( $result['reference'] . ' · ' . $result['applicant'] ); ?> <span class="ft-muted">SSN ••• <?php echo esc_html( $result['ssn_last4'] ); ?></span></h2>
			<?php echo 'full' === $result['level'] ? ft_badge( 'success', __( 'Standard — full details', 'fraud-tenant-theme' ) ) : ft_badge( 'neutral', __( 'Free — limited result', 'fraud-tenant-theme' ) ); // phpcs:ignore ?>
		</div>
		<p class="ft-meta"><span><?php echo esc_html( $result['purpose'] ); ?></span><span><?php echo ft_date( $result['searched_at'], true ); // phpcs:ignore ?></span></p>
		<?php if ( 0 === $result['count'] ) : ?>
			<div class="ft-alert is-info"><?php esc_html_e( 'No reviewed incident records were found for this applicant. This does not certify a clean rental history.', 'fraud-tenant-theme' ); ?></div>
		<?php elseif ( 'basic' === $result['level'] ) : ?>
			<div class="ft-alert is-warning"><?php echo esc_html( sprintf( /* translators: %d: count */ _n( '%d reviewed incident record is available for this applicant.', '%d reviewed incident records are available for this applicant.', $result['count'], 'fraud-tenant-theme' ), $result['count'] ) ); ?></div>
			<div class="ft-lock">
				<?php echo ft_icon( 'shield' ); // phpcs:ignore ?>
				<div><strong><?php esc_html_e( 'Report details are available on Standard', 'fraud-tenant-theme' ); ?></strong>
					<p class="ft-muted"><?php esc_html_e( 'Upgrade to see each record\'s category, date, amount, factual summary, review status and any tenant dispute.', 'fraud-tenant-theme' ); ?></p>
					<a class="ft-btn ft-btn-primary" href="<?php echo esc_url( ft_app_url( $base, 'membership' ) ); ?>"><?php esc_html_e( 'Upgrade to Standard', 'fraud-tenant-theme' ); ?></a></div>
			</div>
		<?php else : ?>
			<?php foreach ( $result['records'] as $rec ) : ?>
				<article class="ft-record">
					<div class="ft-record-head"><strong><?php echo esc_html( $rec['category'] ); ?></strong><?php echo ft_badge( 'none' === $rec['dispute_state'] ? 'neutral' : ( in_array( $rec['dispute_state'], array( 'pending', 'under_review' ), true ) ? 'warning' : 'info' ), $rec['dispute'] ); // phpcs:ignore ?></div>
					<div class="ft-meta"><span><?php echo esc_html( $rec['incident_date'] ); ?></span><span><?php echo esc_html( ft_money( $rec['amount'] ) ); ?></span><span><?php echo esc_html( $rec['location'] ); ?></span><span><?php echo esc_html( $rec['reference'] ); ?></span><?php if ( $rec['own_report'] ) : ?><span><?php esc_html_e( 'Your report', 'fraud-tenant-theme' ); ?></span><?php endif; ?></div>
					<p class="ft-prose"><?php echo nl2br( esc_html( $rec['narrative'] ) ); ?></p>
					<p class="ft-note"><?php echo esc_html( $rec['review_status'] ); ?></p>
				</article>
			<?php endforeach; ?>
		<?php endif; ?>
		<p class="ft-note"><?php esc_html_e( 'Records are owner-submitted reports reviewed by our team, and may be disputed by the tenant. Fraud Tenant does not score, rank or recommend applicants. Use this information only for the stated permissible purpose and make your own decision.', 'fraud-tenant-theme' ); ?></p>
		<a class="ft-btn ft-btn-secondary ft-btn-sm" href="<?php echo esc_url( ft_app_url( $base, 'screening' ) ); ?>"><?php esc_html_e( 'New search', 'fraud-tenant-theme' ); ?></a>
	</section>
<?php elseif ( $req_id ) : ?>
	<div class="ft-alert is-error"><?php esc_html_e( 'Screening result not found.', 'fraud-tenant-theme' ); ?></div>
<?php endif; ?>

<?php if ( ! $result ) : ?>
<div class="ft-grid ft-grid-2">
	<section class="ft-card">
		<div class="ft-card-head"><h2><?php esc_html_e( 'Search an applicant', 'fraud-tenant-theme' ); ?></h2><?php echo $d['full_reports'] ? ft_badge( 'success', __( 'Standard', 'fraud-tenant-theme' ) ) : ft_badge( 'neutral', __( 'Free: limited result', 'fraud-tenant-theme' ) ); // phpcs:ignore ?></div>
		<?php if ( ! $d['screening_ready'] ) : ?>
			<div class="ft-alert is-info"><?php esc_html_e( 'Tenant screening is not available on this platform yet.', 'fraud-tenant-theme' ); ?></div>
		<?php elseif ( ! $d['verified_properties'] ) : ?>
			<div class="ft-alert is-warning"><?php esc_html_e( 'Screening must be linked to one of your verified properties.', 'fraud-tenant-theme' ); ?> <a href="<?php echo esc_url( ft_app_url( $base, 'verification' ) ); ?>"><?php esc_html_e( 'Check verification status', 'fraud-tenant-theme' ); ?></a></div>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ft-form" autocomplete="off">
				<input type="hidden" name="action" value="ft_owner_tenant_search">
				<?php wp_nonce_field( 'ft_tenant_search_' . $owner_id, 'ft_tenant_search_nonce' ); ?>
				<label class="ft-label" for="ft-sp"><?php esc_html_e( 'Property the applicant applied for', 'fraud-tenant-theme' ); ?></label>
				<select class="ft-select" id="ft-sp" name="property_id" required><?php foreach ( $d['verified_properties'] as $vp ) : ?><option value="<?php echo (int) $vp->id; ?>"><?php echo esc_html( ( $vp->title ?: $vp->address_street ) . ' — ' . $vp->city . ', ' . $vp->state ); ?></option><?php endforeach; ?></select>
				<label class="ft-label" for="ft-spu"><?php esc_html_e( 'Screening purpose', 'fraud-tenant-theme' ); ?></label>
				<select class="ft-select" id="ft-spu" name="purpose_reason" required><?php foreach ( ScreeningService::ALLOWED_PURPOSES as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select>
				<div class="ft-row">
					<div><label class="ft-label" for="ftScrFirst"><?php esc_html_e( 'Legal first name', 'fraud-tenant-theme' ); ?></label><input class="ft-input" id="ftScrFirst" name="applicant_first_name" required maxlength="100"></div>
					<div><label class="ft-label" for="ftScrLast"><?php esc_html_e( 'Legal last name', 'fraud-tenant-theme' ); ?></label><input class="ft-input" id="ftScrLast" name="applicant_last_name" required maxlength="100"></div>
				</div>
				<label class="ft-label" for="ftScrSsn"><?php esc_html_e( 'Social Security Number', 'fraud-tenant-theme' ); ?></label>
				<div class="ft-filters" style="margin:0"><input class="ft-input" type="password" id="ftScrSsn" name="applicant_ssn" data-ft-ssn required inputmode="numeric" pattern="\d{3}-?\d{2}-?\d{4}" placeholder="123-45-6789" aria-describedby="ftScrSsnHelp"><button type="button" class="ft-btn ft-btn-ghost ft-btn-sm" data-ft-ssn-toggle="ftScrSsn" data-show="<?php esc_attr_e( 'Show', 'fraud-tenant-theme' ); ?>" data-hide="<?php esc_attr_e( 'Hide', 'fraud-tenant-theme' ); ?>" aria-pressed="false"><?php esc_html_e( 'Show', 'fraud-tenant-theme' ); ?></button></div>
				<p class="ft-help" id="ftScrSsnHelp"><?php esc_html_e( 'The SSN is converted to a keyed token on our server and is never stored, logged or shown again. Only the last 4 digits are kept in your history.', 'fraud-tenant-theme' ); ?></p>
				<label class="ft-check"><input type="checkbox" name="permissible_purpose_attestation" value="1" required> <span><?php esc_html_e( 'I certify this search is for the selected permissible purpose in connection with a rental of this property.', 'fraud-tenant-theme' ); ?></span></label>
				<label class="ft-check"><input type="checkbox" name="applicant_consent" value="1" required> <span><?php esc_html_e( 'I hold the applicant\'s written consent to this screening.', 'fraud-tenant-theme' ); ?></span></label>
				<button class="ft-btn ft-btn-primary" type="submit"><?php echo ft_icon( 'search' ); // phpcs:ignore ?> <?php esc_html_e( 'Search records', 'fraud-tenant-theme' ); ?></button>
			</form>
		<?php endif; ?>
	</section>
	<section class="ft-card">
		<div class="ft-card-head"><h2><?php esc_html_e( 'What you will see', 'fraud-tenant-theme' ); ?></h2></div>
		<ul class="ft-list">
			<li><span><strong><?php esc_html_e( 'Free', 'fraud-tenant-theme' ); ?></strong> — <?php esc_html_e( 'whether reviewed records exist, and how many.', 'fraud-tenant-theme' ); ?></span></li>
			<li><span><strong><?php esc_html_e( 'Standard', 'fraud-tenant-theme' ); ?></strong> — <?php esc_html_e( 'each record\'s category, date, amount, location (city/state), factual summary, review status and dispute status.', 'fraud-tenant-theme' ); ?></span></li>
			<li><span><?php esc_html_e( 'Never shown: evidence files, the reporting owner, street addresses, or the tenant\'s dispute statements.', 'fraud-tenant-theme' ); ?></span></li>
		</ul>
	</section>
</div>
<?php endif; ?>

<section class="ft-card">
	<div class="ft-card-head"><h2><?php esc_html_e( 'Search history', 'fraud-tenant-theme' ); ?></h2></div>
	<?php if ( ! $d['search_history'] ) : ft_empty( __( 'No searches yet.', 'fraud-tenant-theme' ) ); else : ?>
		<div class="ft-table-wrap"><table class="ft-table ft-stack">
			<thead><tr><th><?php esc_html_e( 'Applicant', 'fraud-tenant-theme' ); ?></th><th><?php esc_html_e( 'Purpose', 'fraud-tenant-theme' ); ?></th><th><?php esc_html_e( 'Result', 'fraud-tenant-theme' ); ?></th><th><?php esc_html_e( 'Date', 'fraud-tenant-theme' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $d['search_history'] as $s ) : ?>
				<tr>
					<td data-label="<?php esc_attr_e( 'Applicant', 'fraud-tenant-theme' ); ?>"><a href="<?php echo esc_url( ft_app_url( $base, 'screening', array( 'screening_request' => $s['id'] ) ) ); ?>"><?php echo esc_html( $s['applicant'] ); ?></a> <span class="ft-muted">••• <?php echo esc_html( $s['last4'] ); ?></span></td>
					<td data-label="<?php esc_attr_e( 'Purpose', 'fraud-tenant-theme' ); ?>"><?php echo esc_html( $s['purpose'] ); ?></td>
					<td data-label="<?php esc_attr_e( 'Result', 'fraud-tenant-theme' ); ?>"><?php echo $s['found'] ? ft_badge( 'warning', sprintf( /* translators: %d: count */ _n( '%d record', '%d records', $s['count'], 'fraud-tenant-theme' ), $s['count'] ) ) : ft_badge( 'neutral', __( 'No records', 'fraud-tenant-theme' ) ); // phpcs:ignore ?></td>
					<td data-label="<?php esc_attr_e( 'Date', 'fraud-tenant-theme' ); ?>"><?php echo ft_date( $s['date'], true ); // phpcs:ignore ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table></div>
	<?php endif; ?>
</section>
