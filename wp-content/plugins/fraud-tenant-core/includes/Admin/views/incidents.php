<?php
/**
 * Incident Report Management — review queue, detail, decision (with explicit disclosure choices),
 * separately recorded corrections, and full review/disclosure history.
 *
 * @package FraudTenant\Admin
 */

defined( 'ABSPATH' ) || exit;

use FraudTenant\Admin\AdminConsole as C;
use FraudTenant\Services\IncidentReviewService;
use FraudTenant\Services\IncidentService;
use FraudTenant\Utils\Constants;

$uid = get_current_user_id();
$rid = absint( C::get( 'report_id' ) );

if ( $rid ) {
	$d = IncidentReviewService::get_incident_for_review( $rid, $uid );
	if ( empty( $d['success'] ) ) {
		echo '<div class="ft-alert is-error">' . esc_html( $d['message'] ?? __( 'Report unavailable.', 'fraud-tenant-core' ) ) . '</div>';
		return;
	}
	$r = $d['report'];
	C::head( $r->reference . ' · ' . $r->category_label, __( 'Incident report review', 'fraud-tenant-core' ) );
	echo '<p><a href="' . esc_url( C::url( 'incidents' ) ) . '">← ' . esc_html__( 'Review queue', 'fraud-tenant-core' ) . '</a></p>';
	?>
	<div class="ft-grid ft-grid-2">
		<section class="ft-card">
			<div class="ft-card-head"><h2><?php esc_html_e( 'Report', 'fraud-tenant-core' ); ?></h2><?php echo C::badge( (string) $r->status ); // phpcs:ignore ?></div>
			<dl class="ft-dl">
				<dt><?php esc_html_e( 'Category', 'fraud-tenant-core' ); ?></dt><dd><?php echo esc_html( $r->category_label ); ?></dd>
				<dt><?php esc_html_e( 'Incident date', 'fraud-tenant-core' ); ?></dt><dd><?php echo C::date( $r->incident_date ); // phpcs:ignore ?></dd>
				<dt><?php esc_html_e( 'Amount', 'fraud-tenant-core' ); ?></dt><dd><?php echo esc_html( $r->amount_formatted ); ?></dd>
				<dt><?php esc_html_e( 'Property', 'fraud-tenant-core' ); ?></dt><dd><?php echo esc_html( trim( $r->property_street . ( $r->property_unit ? ' #' . $r->property_unit : '' ) . ', ' . $r->property_city . ', ' . $r->property_state ) ); ?></dd>
				<dt><?php esc_html_e( 'Reporting owner', 'fraud-tenant-core' ); ?></dt><dd><a href="<?php echo esc_url( C::url( 'users', array( 'user_id' => (int) $r->owner_id ) ) ); ?>"><?php echo esc_html( $r->owner_name ); ?></a></dd>
				<dt><?php esc_html_e( 'Submitted', 'fraud-tenant-core' ); ?></dt><dd><?php echo C::date( $r->created_at, true ); // phpcs:ignore ?></dd>
			</dl>
			<h3><?php esc_html_e( 'Factual description', 'fraud-tenant-core' ); ?></h3>
			<p class="ft-prose"><?php echo nl2br( esc_html( (string) $r->factual_description ) ); ?></p>
			<h3><?php esc_html_e( 'Evidence', 'fraud-tenant-core' ); ?></h3>
			<?php if ( empty( $d['evidence_docs'] ) ) : C::empty_state( __( 'No evidence attached.', 'fraud-tenant-core' ) ); else : ?>
				<ul class="ft-list"><?php foreach ( $d['evidence_docs'] as $doc ) : ?><li><?php echo esc_html( $doc->original_name ); ?> <span class="ft-muted"><?php echo esc_html( $doc->file_size_fmt ); ?></span> <?php echo C::doc_link( (int) $doc->id, __( 'View', 'fraud-tenant-core' ) ); // phpcs:ignore ?></li><?php endforeach; ?></ul>
			<?php endif; ?>
		</section>
		<section class="ft-card">
			<div class="ft-card-head"><h2><?php esc_html_e( 'Tenant identity', 'fraud-tenant-core' ); ?></h2><?php echo C::badge( (string) ( $r->identity_match ?: 'unlinked' ), $r->identity_label ); // phpcs:ignore ?></div>
			<dl class="ft-dl">
				<dt><?php esc_html_e( 'Tenant (as linked / reported)', 'fraud-tenant-core' ); ?></dt><dd><?php echo esc_html( $r->subject['name'] ?: '—' ); ?> <?php echo $r->subject['last4'] ? '· SSN ••• •• ' . esc_html( $r->subject['last4'] ) : ''; ?></dd>
				<?php if ( $r->identity ) : ?>
					<dt><?php esc_html_e( 'Central identity', 'fraud-tenant-core' ); ?></dt><dd><a href="<?php echo esc_url( C::url( 'identities', array( 'identity_id' => (int) $r->identity->id ) ) ); ?>">#<?php echo (int) $r->identity->id; ?></a> · <?php echo $r->identity->user_id ? esc_html__( 'claimed by a verified tenant account', 'fraud-tenant-core' ) : esc_html__( 'unclaimed', 'fraud-tenant-core' ); ?></dd>
				<?php endif; ?>
				<?php if ( $r->candidate ) : ?>
					<dt><?php esc_html_e( 'Exception candidate', 'fraud-tenant-core' ); ?></dt><dd><a href="<?php echo esc_url( C::url( 'exceptions' ) ); ?>"><?php esc_html_e( 'Resolve in Identity Match Exceptions', 'fraud-tenant-core' ); ?></a></dd>
				<?php endif; ?>
				<dt><?php esc_html_e( 'Visible to subject tenant', 'fraud-tenant-core' ); ?></dt><dd><?php echo C::badge( $r->disclosable ? 'yes' : 'no', $r->disclosable ? __( 'Yes', 'fraud-tenant-core' ) : __( 'No', 'fraud-tenant-core' ) ); // phpcs:ignore ?></dd>
				<dt><?php esc_html_e( 'In owner screening results', 'fraud-tenant-core' ); ?></dt><dd><?php echo C::badge( $r->screening_disclosure ? 'yes' : 'no', $r->screening_disclosure ? __( 'Yes', 'fraud-tenant-core' ) : __( 'No', 'fraud-tenant-core' ) ); // phpcs:ignore ?></dd>
			</dl>
			<p><a class="ft-btn ft-btn-secondary ft-btn-sm" href="<?php echo esc_url( C::url( 'disclosure', array( 'report_id' => $rid ) ) ); ?>"><?php esc_html_e( 'Manage association & disclosure', 'fraud-tenant-core' ); ?></a></p>

			<?php if ( Constants::INCIDENT_STATUS_SUBMITTED === $r->status && IncidentReviewService::can_decide_incidents( $uid ) && ! $r->is_self_report ) : ?>
				<h3><?php esc_html_e( 'Review decision', 'fraud-tenant-core' ); ?></h3>
				<?php C::form( 'incident_decide', array( 'report_id' => $rid ) ); ?>
					<fieldset class="ft-fieldset">
						<legend class="ft-label"><?php esc_html_e( 'Decision', 'fraud-tenant-core' ); ?></legend>
						<label class="ft-check"><input type="radio" name="decision" value="approve" <?php disabled( Constants::IDENTITY_EXCEPTION === $r->identity_match ); ?> required> <?php esc_html_e( 'Approve', 'fraud-tenant-core' ); ?></label>
						<label class="ft-check"><input type="radio" name="decision" value="request_clarification"> <?php esc_html_e( 'Request clarification', 'fraud-tenant-core' ); ?></label>
						<label class="ft-check"><input type="radio" name="decision" value="reject"> <?php esc_html_e( 'Reject', 'fraud-tenant-core' ); ?></label>
						<?php if ( Constants::IDENTITY_EXCEPTION === $r->identity_match ) : ?><p class="ft-help"><?php esc_html_e( 'Approval is unavailable until the identity exception is resolved.', 'fraud-tenant-core' ); ?></p><?php endif; ?>
					</fieldset>
					<label class="ft-label" for="ft-rc"><?php esc_html_e( 'Reason code', 'fraud-tenant-core' ); ?></label>
					<select class="ft-select" id="ft-rc" name="reason_code" required>
						<optgroup label="<?php esc_attr_e( 'Approve', 'fraud-tenant-core' ); ?>"><?php foreach ( IncidentReviewService::APPROVAL_REASONS as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></option><?php endforeach; ?></optgroup>
						<optgroup label="<?php esc_attr_e( 'Clarification', 'fraud-tenant-core' ); ?>"><?php foreach ( IncidentReviewService::CLARIFICATION_REASONS as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></option><?php endforeach; ?></optgroup>
						<optgroup label="<?php esc_attr_e( 'Reject', 'fraud-tenant-core' ); ?>"><?php foreach ( IncidentReviewService::REJECTION_REASONS as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></option><?php endforeach; ?></optgroup>
					</select>
					<label class="ft-label" for="ft-fb"><?php esc_html_e( 'Note to the owner (optional)', 'fraud-tenant-core' ); ?></label>
					<textarea class="ft-textarea" id="ft-fb" name="feedback"></textarea>
					<fieldset class="ft-fieldset">
						<legend class="ft-label"><?php esc_html_e( 'Disclosure on approval', 'fraud-tenant-core' ); ?></legend>
						<label class="ft-check"><input type="checkbox" name="disclose_tenant" value="1" checked> <?php esc_html_e( 'Visible to the subject tenant (to review and dispute)', 'fraud-tenant-core' ); ?></label>
						<label class="ft-check"><input type="checkbox" name="disclose_screening" value="1" checked> <?php esc_html_e( 'Include in owner screening results', 'fraud-tenant-core' ); ?></label>
					</fieldset>
					<button class="ft-btn ft-btn-primary" type="submit"><?php esc_html_e( 'Record decision', 'fraud-tenant-core' ); ?></button>
				</form>
			<?php elseif ( $r->is_self_report ) : ?>
				<p class="ft-alert is-info"><?php esc_html_e( 'You submitted this report, so another administrator must review it.', 'fraud-tenant-core' ); ?></p>
			<?php endif; ?>
		</section>
	</div>

	<?php if ( IncidentReviewService::can_decide_incidents( $uid ) && ! $r->is_self_report ) : ?>
	<section class="ft-card" id="correct">
		<div class="ft-card-head"><h2><?php esc_html_e( 'Correct this report', 'fraud-tenant-core' ); ?></h2></div>
		<p class="ft-muted"><?php esc_html_e( 'A correction is a separate, recorded operation (e.g. after a dispute). Previous values are kept in the review history.', 'fraud-tenant-core' ); ?></p>
		<?php C::form( 'incident_correct', array( 'report_id' => $rid ) ); ?>
			<div class="ft-row">
				<div><label class="ft-label" for="ft-c-cat"><?php esc_html_e( 'Category', 'fraud-tenant-core' ); ?></label><select class="ft-select" id="ft-c-cat" name="category"><?php foreach ( IncidentService::ALLOWED_CATEGORIES as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $r->category, $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></div>
				<div><label class="ft-label" for="ft-c-date"><?php esc_html_e( 'Incident date', 'fraud-tenant-core' ); ?></label><input class="ft-input" type="date" id="ft-c-date" name="incident_date" value="<?php echo esc_attr( $r->incident_date ); ?>"></div>
				<div><label class="ft-label" for="ft-c-amt"><?php esc_html_e( 'Amount (USD)', 'fraud-tenant-core' ); ?></label><input class="ft-input" type="number" min="0" step="0.01" id="ft-c-amt" name="amount_involved" value="<?php echo esc_attr( number_format( (float) $r->amount_involved, 2, '.', '' ) ); ?>"></div>
				<div><label class="ft-label" for="ft-c-dsp"><?php esc_html_e( 'Related dispute ID (optional)', 'fraud-tenant-core' ); ?></label><input class="ft-input" type="number" min="0" id="ft-c-dsp" name="dispute_id" value="<?php echo esc_attr( C::get( 'dispute_id' ) ); ?>"></div>
			</div>
			<label class="ft-label" for="ft-c-desc"><?php esc_html_e( 'Factual description', 'fraud-tenant-core' ); ?></label>
			<textarea class="ft-textarea" id="ft-c-desc" name="factual_description" rows="4"><?php echo esc_textarea( (string) $r->factual_description ); ?></textarea>
			<label class="ft-label" for="ft-c-reason"><?php esc_html_e( 'Reason for the correction (shown to the owner)', 'fraud-tenant-core' ); ?></label>
			<textarea class="ft-textarea" id="ft-c-reason" name="reason" required minlength="5"></textarea>
			<button class="ft-btn ft-btn-secondary" type="submit"><?php esc_html_e( 'Save correction', 'fraud-tenant-core' ); ?></button>
		</form>
	</section>
	<?php endif; ?>

	<div class="ft-grid ft-grid-2">
		<section class="ft-card">
			<div class="ft-card-head"><h2><?php esc_html_e( 'Review history', 'fraud-tenant-core' ); ?></h2></div>
			<?php if ( empty( $d['review_history'] ) ) : C::empty_state( __( 'No decisions yet.', 'fraud-tenant-core' ) ); else : ?>
				<ol class="ft-timeline"><?php foreach ( $d['review_history'] as $h ) : ?>
					<li><strong><?php echo esc_html( ucwords( str_replace( '_', ' ', (string) $h->decision ) ) ); ?></strong> · <?php echo esc_html( (string) $h->reviewer_name ); ?> · <?php echo C::date( $h->created_at, true ); // phpcs:ignore ?><br><span class="ft-muted"><?php echo esc_html( (string) ( 'corrected' === $h->decision ? $h->feedback_to_owner : ( $h->internal_notes ?: $h->feedback_to_owner ) ) ); ?></span></li>
				<?php endforeach; ?></ol>
			<?php endif; ?>
		</section>
		<section class="ft-card">
			<div class="ft-card-head"><h2><?php esc_html_e( 'Identity & disclosure history', 'fraud-tenant-core' ); ?></h2></div>
			<?php if ( empty( $d['disclosure_history'] ) ) : C::empty_state( __( 'No identity or disclosure changes yet.', 'fraud-tenant-core' ) ); else : ?>
				<ol class="ft-timeline"><?php foreach ( $d['disclosure_history'] as $h ) : ?>
					<li><strong><?php echo esc_html( ucwords( str_replace( '_', ' ', (string) $h->action ) ) ); ?></strong> · <?php echo esc_html( (string) $h->actor ); ?> · <?php echo C::date( $h->created_at, true ); // phpcs:ignore ?><br><span class="ft-muted"><?php echo esc_html( (string) $h->reason ); ?></span></li>
				<?php endforeach; ?></ol>
			<?php endif; ?>
		</section>
	</div>
	<?php
	return;
}

$status = C::get( 'status', 'submitted' );
$page   = max( 1, absint( C::get( 'pg', '1' ) ) );
$q      = IncidentReviewService::get_review_queue( array( 'status' => $status, 'page' => $page, 'per_page' => 25, 'search' => C::get( 'q' ) ), $uid );
C::head( __( 'Incident Report Management', 'fraud-tenant-core' ), __( 'Submitted reports are allegations pending review. Nothing is disclosed until an administrator approves it and chooses who may see it.', 'fraud-tenant-core' ) );
if ( empty( $q['success'] ) ) {
	echo '<div class="ft-alert is-error">' . esc_html( $q['message'] ) . '</div>';
	return;
}
$c = $q['status_counts'];
C::pills(
	'incidents',
	'status',
	array(
		/* translators: %d: count */
		'submitted'           => sprintf( __( 'Submitted (%d)', 'fraud-tenant-core' ), $c['submitted'] ),
		/* translators: %d: count */
		'needs_clarification' => sprintf( __( 'Needs clarification (%d)', 'fraud-tenant-core' ), $c['needs_clarification'] ),
		/* translators: %d: count */
		'approved'            => sprintf( __( 'Approved (%d)', 'fraud-tenant-core' ), $c['approved'] ),
		/* translators: %d: count */
		'rejected'            => sprintf( __( 'Rejected (%d)', 'fraud-tenant-core' ), $c['rejected'] ),
		/* translators: %d: count */
		'all'                 => sprintf( __( 'All (%d)', 'fraud-tenant-core' ), $c['all'] ),
	),
	$status
);
$owner_filter = absint( C::get( 'owner_id' ) );
$items        = $owner_filter ? array_filter( $q['items'], static fn( $i ) => (int) $i->owner_id === $owner_filter ) : $q['items'];
?>
<div class="ft-card"><div class="ft-table-wrap"><table class="ft-table">
	<thead><tr><th><?php esc_html_e( 'Reference', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Category', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Owner', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Identity', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Status', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Submitted', 'fraud-tenant-core' ); ?></th></tr></thead>
	<tbody>
	<?php foreach ( $items as $i ) : ?>
		<tr>
			<td><a href="<?php echo esc_url( C::url( 'incidents', array( 'report_id' => (int) $i->id ) ) ); ?>"><?php echo esc_html( $i->reference ); ?></a></td>
			<td><?php echo esc_html( $i->category_label ); ?> <span class="ft-muted"><?php echo esc_html( $i->amount_formatted ); ?></span></td>
			<td><?php echo esc_html( (string) $i->owner_name ); ?></td>
			<td><?php echo C::badge( (string) ( $i->identity_match ?: 'unlinked' ), IncidentService::identity_state_label( $i->identity_match ) ); // phpcs:ignore ?></td>
			<td><?php echo C::badge( (string) $i->status ); // phpcs:ignore ?></td>
			<td><?php echo C::date( $i->created_at ); // phpcs:ignore ?></td>
		</tr>
	<?php endforeach; ?>
	<?php if ( ! $items ) : ?><tr><td colspan="6"><?php C::empty_state( __( 'No reports in this list.', 'fraud-tenant-core' ) ); ?></td></tr><?php endif; ?>
	</tbody>
</table></div></div>
<?php C::pagination( 'incidents', $page, (int) $q['total_items'], 25, array( 'status' => $status ) ); ?>
