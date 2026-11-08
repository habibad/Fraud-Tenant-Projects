<?php
/**
 * Owner — My Incident Reports (list, detail, review feedback, clarification response).
 *
 * @package FraudTenantTheme
 */

defined( 'ABSPATH' ) || exit;

use FraudTenant\Services\IncidentService;
use FraudTenant\Utils\Constants;

$d        = $args['data'];
$base     = $args['base'];
$owner_id = $args['owner_id'];
$rid      = absint( $_GET['report_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification

if ( $rid ) :
	$r = IncidentService::get_incident_report( $rid, $owner_id );
	if ( ! $r || (int) $r->owner_id !== $owner_id ) {
		ft_empty( __( 'Report not found.', 'fraud-tenant-theme' ), ft_app_url( $base, 'reports' ), __( 'Back to reports', 'fraud-tenant-theme' ) );
		return;
	}
	$status_text = array(
		Constants::INCIDENT_STATUS_SUBMITTED           => __( 'Submitted — waiting for review. Not visible to anyone else.', 'fraud-tenant-theme' ),
		Constants::INCIDENT_STATUS_NEEDS_CLARIFICATION => __( 'Needs clarification — please respond below.', 'fraud-tenant-theme' ),
		Constants::INCIDENT_STATUS_APPROVED            => __( 'Approved after review.', 'fraud-tenant-theme' ),
		Constants::INCIDENT_STATUS_REJECTED            => __( 'Not approved. It is not shown to anyone.', 'fraud-tenant-theme' ),
	);
	?>
	<p><a href="<?php echo esc_url( ft_app_url( $base, 'reports' ) ); ?>">← <?php esc_html_e( 'All reports', 'fraud-tenant-theme' ); ?></a></p>
	<header class="ft-page-head"><div><h1><?php echo esc_html( $r->reference . ' · ' . $r->category_label ); ?></h1><p><?php echo esc_html( $status_text[ $r->status ] ?? '' ); ?></p></div><?php echo ft_badge( (string) $r->status ); // phpcs:ignore ?></header>
	<div class="ft-grid ft-grid-2">
		<section class="ft-card">
			<div class="ft-card-head"><h2><?php esc_html_e( 'Report', 'fraud-tenant-theme' ); ?></h2></div>
			<dl class="ft-dl">
				<dt><?php esc_html_e( 'Tenant', 'fraud-tenant-theme' ); ?></dt><dd><?php echo esc_html( $r->subject['name'] ?: '—' ); ?> <?php echo $r->subject['last4'] ? '· SSN ••• ' . esc_html( $r->subject['last4'] ) : ''; ?></dd>
				<dt><?php esc_html_e( 'Tenant identity', 'fraud-tenant-theme' ); ?></dt><dd><?php echo ft_badge( (string) ( $r->identity_match ?: 'unlinked' ), $r->identity_label ); // phpcs:ignore ?></dd>
				<dt><?php esc_html_e( 'Property', 'fraud-tenant-theme' ); ?></dt><dd><?php echo esc_html( trim( $r->property_street . ( $r->property_unit ? ' #' . $r->property_unit : '' ) . ', ' . $r->property_city . ', ' . $r->property_state ) ); ?></dd>
				<dt><?php esc_html_e( 'Incident date', 'fraud-tenant-theme' ); ?></dt><dd><?php echo ft_date( $r->incident_date ); // phpcs:ignore ?></dd>
				<dt><?php esc_html_e( 'Amount', 'fraud-tenant-theme' ); ?></dt><dd><?php echo esc_html( $r->amount_formatted ); ?></dd>
				<dt><?php esc_html_e( 'Submitted', 'fraud-tenant-theme' ); ?></dt><dd><?php echo ft_date( $r->created_at, true ); // phpcs:ignore ?></dd>
				<dt><?php esc_html_e( 'Tenant dispute', 'fraud-tenant-theme' ); ?></dt><dd><?php echo esc_html( $r->dispute_label ); ?></dd>
			</dl>
			<h3><?php esc_html_e( 'Factual description', 'fraud-tenant-theme' ); ?></h3>
			<p class="ft-prose"><?php echo nl2br( esc_html( (string) $r->factual_description ) ); ?></p>
			<h3><?php esc_html_e( 'Evidence', 'fraud-tenant-theme' ); ?></h3>
			<?php if ( ! $r->evidence_docs ) : ft_empty( __( 'No evidence attached.', 'fraud-tenant-theme' ) ); else : ?>
				<ul class="ft-list"><?php foreach ( $r->evidence_docs as $doc ) : ?><li><?php echo esc_html( $doc->original_name ); ?> <a class="ft-btn ft-btn-secondary ft-btn-sm" href="<?php echo esc_url( $doc->view_url ); ?>"><?php esc_html_e( 'View', 'fraud-tenant-theme' ); ?></a></li><?php endforeach; ?></ul>
			<?php endif; ?>
		</section>
		<section class="ft-card">
			<div class="ft-card-head"><h2><?php esc_html_e( 'Review history', 'fraud-tenant-theme' ); ?></h2></div>
			<?php if ( ! $r->review_feedback ) : ft_empty( __( 'No review decisions yet.', 'fraud-tenant-theme' ) ); else : ?>
				<ol class="ft-timeline"><?php foreach ( $r->review_feedback as $fb ) : ?>
					<li><strong><?php echo esc_html( 'owner_response' === $fb->decision ? __( 'Your clarification', 'fraud-tenant-theme' ) : ucwords( str_replace( '_', ' ', (string) $fb->decision ) ) ); ?></strong> · <?php echo ft_date( $fb->created_at, true ); // phpcs:ignore ?><?php if ( $fb->feedback_to_owner ) : ?><br><span class="ft-muted"><?php echo nl2br( esc_html( (string) $fb->feedback_to_owner ) ); ?></span><?php endif; ?></li>
				<?php endforeach; ?></ol>
			<?php endif; ?>
			<?php if ( Constants::INCIDENT_STATUS_NEEDS_CLARIFICATION === $r->status ) : ?>
				<h3><?php esc_html_e( 'Respond to the review team', 'fraud-tenant-theme' ); ?></h3>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="ft-form" autocomplete="off">
					<input type="hidden" name="action" value="ft_incident_clarify">
					<input type="hidden" name="report_id" value="<?php echo (int) $rid; ?>">
					<?php wp_nonce_field( 'ft_incident_clarify_' . $rid ); ?>
					<?php if ( Constants::IDENTITY_EXCEPTION === $r->identity_match ) : ?>
						<div class="ft-alert is-warning"><?php esc_html_e( 'The tenant name did not match our identity records for that SSN. Re-enter the tenant\'s legal name and SSN exactly as on the lease.', 'fraud-tenant-theme' ); ?></div>
						<div class="ft-row">
							<div><label class="ft-label" for="ft-cf"><?php esc_html_e( 'Legal first name', 'fraud-tenant-theme' ); ?></label><input class="ft-input" id="ft-cf" name="tenant_first_name" required></div>
							<div><label class="ft-label" for="ft-cl"><?php esc_html_e( 'Legal last name', 'fraud-tenant-theme' ); ?></label><input class="ft-input" id="ft-cl" name="tenant_last_name" required></div>
						</div>
						<label class="ft-label" for="ftClrSsn"><?php esc_html_e( 'Tenant SSN', 'fraud-tenant-theme' ); ?></label>
						<input class="ft-input" type="password" id="ftClrSsn" name="tenant_ssn" data-ft-ssn required inputmode="numeric" pattern="\d{3}-?\d{2}-?\d{4}">
					<?php endif; ?>
					<label class="ft-label" for="ft-cd"><?php esc_html_e( 'Factual description', 'fraud-tenant-theme' ); ?></label>
					<textarea class="ft-textarea" id="ft-cd" name="factual_description" rows="4" minlength="10" maxlength="5000"><?php echo esc_textarea( (string) $r->factual_description ); ?></textarea>
					<div class="ft-row">
						<div><label class="ft-label" for="ft-cdt"><?php esc_html_e( 'Incident date', 'fraud-tenant-theme' ); ?></label><input class="ft-input" type="date" id="ft-cdt" name="incident_date" value="<?php echo esc_attr( (string) $r->incident_date ); ?>"></div>
						<div><label class="ft-label" for="ft-ca"><?php esc_html_e( 'Amount (USD)', 'fraud-tenant-theme' ); ?></label><input class="ft-input" type="number" min="0" step="0.01" id="ft-ca" name="amount_involved" value="<?php echo esc_attr( number_format( (float) $r->amount_involved, 2, '.', '' ) ); ?>"></div>
					</div>
					<label class="ft-label" for="ft-cn"><?php esc_html_e( 'Your response', 'fraud-tenant-theme' ); ?></label>
					<textarea class="ft-textarea" id="ft-cn" name="response_note" required minlength="5"></textarea>
					<label class="ft-label" for="ft-ce"><?php esc_html_e( 'Additional evidence (optional)', 'fraud-tenant-theme' ); ?></label>
					<input class="ft-input" type="file" id="ft-ce" name="incident_evidence" accept=".pdf,.png,.jpg,.jpeg">
					<button class="ft-btn ft-btn-primary" type="submit"><?php esc_html_e( 'Send clarification', 'fraud-tenant-theme' ); ?></button>
				</form>
			<?php endif; ?>
		</section>
	</div>
	<?php
	return;
endif;

$filter  = sanitize_key( (string) ( $_GET['status'] ?? 'all' ) ); // phpcs:ignore WordPress.Security.NonceVerification
$reports = 'all' === $filter ? $d['reports'] : array_values( array_filter( $d['reports'], static fn( $r ) => $r->status === $filter ) );
?>
<header class="ft-page-head"><div><h1><?php esc_html_e( 'My incident reports', 'fraud-tenant-theme' ); ?></h1><p><?php esc_html_e( 'Track the review status of every report you have submitted.', 'fraud-tenant-theme' ); ?></p></div><?php if ( $d['can_submit_incident'] ) : ?><a class="ft-btn ft-btn-primary" href="<?php echo esc_url( ft_app_url( $base, 'report' ) ); ?>"><?php echo ft_icon( 'fileplus' ); // phpcs:ignore ?> <?php esc_html_e( 'New report', 'fraud-tenant-theme' ); ?></a><?php endif; ?></header>
<div class="ft-tabs">
	<?php
	$tabs = array(
		'all'                 => __( 'All', 'fraud-tenant-theme' ) . ' (' . count( $d['reports'] ) . ')',
		'submitted'           => __( 'Under review', 'fraud-tenant-theme' ) . ' (' . $d['report_counts']['submitted'] . ')',
		'needs_clarification' => __( 'Needs clarification', 'fraud-tenant-theme' ) . ' (' . $d['report_counts']['needs_clarification'] . ')',
		'approved'            => __( 'Approved', 'fraud-tenant-theme' ) . ' (' . $d['report_counts']['approved'] . ')',
		'rejected'            => __( 'Not approved', 'fraud-tenant-theme' ) . ' (' . $d['report_counts']['rejected'] . ')',
	);
	foreach ( $tabs as $k => $l ) {
		printf( '<a class="ft-tab %s" href="%s">%s</a>', $k === $filter ? 'is-active' : '', esc_url( ft_app_url( $base, 'reports', 'all' === $k ? array() : array( 'status' => $k ) ) ), esc_html( $l ) );
	}
	?>
</div>
<?php if ( ! $reports ) : ?>
	<?php ft_empty( __( 'No reports in this list.', 'fraud-tenant-theme' ) ); ?>
<?php else : ?>
	<div class="ft-card"><div class="ft-table-wrap"><table class="ft-table ft-stack">
		<thead><tr><th><?php esc_html_e( 'Reference', 'fraud-tenant-theme' ); ?></th><th><?php esc_html_e( 'Tenant', 'fraud-tenant-theme' ); ?></th><th><?php esc_html_e( 'Category', 'fraud-tenant-theme' ); ?></th><th><?php esc_html_e( 'Property', 'fraud-tenant-theme' ); ?></th><th><?php esc_html_e( 'Status', 'fraud-tenant-theme' ); ?></th><th><?php esc_html_e( 'Submitted', 'fraud-tenant-theme' ); ?></th></tr></thead>
		<tbody>
		<?php foreach ( $reports as $r ) : ?>
			<tr>
				<td data-label="<?php esc_attr_e( 'Reference', 'fraud-tenant-theme' ); ?>"><a href="<?php echo esc_url( ft_app_url( $base, 'reports', array( 'report_id' => $r->id ) ) ); ?>"><?php echo esc_html( $r->reference ); ?></a></td>
				<td data-label="<?php esc_attr_e( 'Tenant', 'fraud-tenant-theme' ); ?>"><?php echo esc_html( $r->subject['name'] ?: '—' ); ?></td>
				<td data-label="<?php esc_attr_e( 'Category', 'fraud-tenant-theme' ); ?>"><?php echo esc_html( $r->category_label ); ?></td>
				<td data-label="<?php esc_attr_e( 'Property', 'fraud-tenant-theme' ); ?>"><?php echo esc_html( (string) ( $r->property_title ?: $r->property_street ) ); ?></td>
				<td data-label="<?php esc_attr_e( 'Status', 'fraud-tenant-theme' ); ?>"><?php echo ft_badge( (string) $r->status ); // phpcs:ignore ?></td>
				<td data-label="<?php esc_attr_e( 'Submitted', 'fraud-tenant-theme' ); ?>"><?php echo ft_date( $r->created_at ); // phpcs:ignore ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table></div></div>
<?php endif; ?>
