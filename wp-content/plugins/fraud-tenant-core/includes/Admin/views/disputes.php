<?php
/**
 * Tenant Dispute Management — queue, statement & evidence review, internal notes, decisions,
 * decision history, and a link to the separately recorded report correction.
 *
 * @package FraudTenant\Admin
 */

defined( 'ABSPATH' ) || exit;

use FraudTenant\Admin\AdminConsole as C;
use FraudTenant\Services\DisputeService;

$uid = get_current_user_id();
$did = absint( C::get( 'dispute_id' ) );

if ( $did ) {
	$d = DisputeService::get_dispute_detail( $did, $uid );
	if ( ! $d ) {
		C::empty_state( __( 'Dispute not found.', 'fraud-tenant-core' ) );
		return;
	}
	$x    = $d['dispute'];
	$open = in_array( $x->status, array( 'pending', 'under_review' ), true );
	C::head( $x->reference . ' · ' . $x->reason_label, __( 'Tenant dispute', 'fraud-tenant-core' ) );
	echo '<p><a href="' . esc_url( C::url( 'disputes' ) ) . '">← ' . esc_html__( 'All disputes', 'fraud-tenant-core' ) . '</a></p>';
	?>
	<div class="ft-grid ft-grid-2">
		<section class="ft-card">
			<div class="ft-card-head"><h2><?php esc_html_e( 'Tenant statement', 'fraud-tenant-core' ); ?></h2><?php echo C::badge( (string) $x->status, $x->status_label ); // phpcs:ignore ?></div>
			<dl class="ft-dl">
				<dt><?php esc_html_e( 'Tenant', 'fraud-tenant-core' ); ?></dt><dd><a href="<?php echo esc_url( C::url( 'users', array( 'user_id' => (int) $x->tenant_id ) ) ); ?>"><?php echo esc_html( $d['tenant']['display_name'] ); ?></a></dd>
				<dt><?php esc_html_e( 'Reason', 'fraud-tenant-core' ); ?></dt><dd><?php echo esc_html( $x->reason_label ); ?></dd>
				<dt><?php esc_html_e( 'Filed', 'fraud-tenant-core' ); ?></dt><dd><?php echo C::date( $x->created_at, true ); // phpcs:ignore ?></dd>
			</dl>
			<p class="ft-prose"><?php echo nl2br( esc_html( (string) $x->statement ) ); ?></p>
			<h3><?php esc_html_e( 'Counter-evidence', 'fraud-tenant-core' ); ?></h3>
			<?php if ( empty( $d['evidence_docs'] ) ) : C::empty_state( __( 'No evidence attached.', 'fraud-tenant-core' ) ); else : ?>
				<ul class="ft-list"><?php foreach ( $d['evidence_docs'] as $doc ) : ?><li><?php echo esc_html( $doc->original_name ); ?> <?php echo C::doc_link( (int) $doc->id, __( 'View', 'fraud-tenant-core' ) ); // phpcs:ignore ?></li><?php endforeach; ?></ul>
			<?php endif; ?>
		</section>
		<section class="ft-card">
			<div class="ft-card-head"><h2><?php esc_html_e( 'Disputed report', 'fraud-tenant-core' ); ?></h2></div>
			<?php if ( ! empty( $d['report']['category_label'] ) ) : $rep = $d['report']; ?>
				<dl class="ft-dl">
					<dt><?php esc_html_e( 'Report', 'fraud-tenant-core' ); ?></dt><dd><a href="<?php echo esc_url( C::url( 'incidents', array( 'report_id' => (int) $rep['id'] ) ) ); ?>"><?php echo esc_html( $rep['reference'] ); ?></a></dd>
					<dt><?php esc_html_e( 'Category', 'fraud-tenant-core' ); ?></dt><dd><?php echo esc_html( $rep['category_label'] ); ?></dd>
					<dt><?php esc_html_e( 'Date / amount', 'fraud-tenant-core' ); ?></dt><dd><?php echo C::date( $rep['incident_date'] ) . ' · ' . esc_html( $rep['amount_formatted'] ); // phpcs:ignore ?></dd>
				</dl>
				<p class="ft-prose"><?php echo nl2br( esc_html( (string) $rep['factual_description'] ) ); ?></p>
				<p><a class="ft-btn ft-btn-secondary ft-btn-sm" href="<?php echo esc_url( C::url( 'incidents', array( 'report_id' => (int) $rep['id'], 'dispute_id' => $did ) ) . '#correct' ); ?>"><?php esc_html_e( 'Correct the report (separate recorded operation)', 'fraud-tenant-core' ); ?></a>
				<a class="ft-btn ft-btn-ghost ft-btn-sm" href="<?php echo esc_url( C::url( 'disclosure', array( 'report_id' => (int) $rep['id'] ) ) ); ?>"><?php esc_html_e( 'Association & disclosure', 'fraud-tenant-core' ); ?></a></p>
			<?php else : ?>
				<p class="ft-muted"><?php esc_html_e( 'The report no longer exists.', 'fraud-tenant-core' ); ?></p>
			<?php endif; ?>
		</section>
	</div>
	<div class="ft-grid ft-grid-2">
		<section class="ft-card">
			<div class="ft-card-head"><h2><?php esc_html_e( 'Decision', 'fraud-tenant-core' ); ?></h2></div>
			<?php if ( $open ) : ?>
				<?php if ( 'pending' === $x->status ) : ?>
					<?php C::form( 'dispute_review', array( 'dispute_id' => $did ) ); ?>
						<label class="ft-label" for="ft-dn"><?php esc_html_e( 'Internal note (administrators only)', 'fraud-tenant-core' ); ?></label>
						<textarea class="ft-textarea" id="ft-dn" name="internal_notes"></textarea>
						<button class="ft-btn ft-btn-secondary" type="submit"><?php esc_html_e( 'Start review', 'fraud-tenant-core' ); ?></button>
					</form>
				<?php endif; ?>
				<?php C::form( 'dispute_resolve', array( 'dispute_id' => $did ) ); ?>
					<fieldset class="ft-fieldset"><legend class="ft-label"><?php esc_html_e( 'Determination', 'fraud-tenant-core' ); ?></legend>
						<label class="ft-check"><input type="radio" name="resolution_action" value="resolve_upheld" required> <?php esc_html_e( 'Uphold — the tenant\'s challenge is valid', 'fraud-tenant-core' ); ?></label>
						<label class="ft-check"><input type="radio" name="resolution_action" value="resolve_corrected"> <?php esc_html_e( 'Corrected — a correction is warranted (make it separately)', 'fraud-tenant-core' ); ?></label>
						<label class="ft-check"><input type="radio" name="resolution_action" value="resolve_rejected"> <?php esc_html_e( 'Reject — the report stands', 'fraud-tenant-core' ); ?></label>
					</fieldset>
					<label class="ft-label" for="ft-dr"><?php esc_html_e( 'Explanation shown to the tenant', 'fraud-tenant-core' ); ?></label>
					<textarea class="ft-textarea" id="ft-dr" name="resolution_notes" required minlength="5"></textarea>
					<label class="ft-label" for="ft-di"><?php esc_html_e( 'Internal note (optional)', 'fraud-tenant-core' ); ?></label>
					<textarea class="ft-textarea" id="ft-di" name="internal_notes"></textarea>
					<button class="ft-btn ft-btn-primary" type="submit"><?php esc_html_e( 'Record determination', 'fraud-tenant-core' ); ?></button>
					<p class="ft-help"><?php esc_html_e( 'A determination never edits the report by itself. Use "Correct the report" to change facts; it is recorded separately.', 'fraud-tenant-core' ); ?></p>
				</form>
			<?php else : ?>
				<p><?php echo C::badge( (string) $x->status, $x->status_label ); // phpcs:ignore ?> · <?php echo esc_html( ucwords( str_replace( array( 'resolve_', '_' ), array( '', ' ' ), (string) ( $x->resolution_action ?? '' ) ) ) ); ?> · <?php echo C::date( $x->resolved_at, true ); // phpcs:ignore ?></p>
				<p class="ft-prose"><?php echo nl2br( esc_html( (string) $x->resolution_notes ) ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $x->internal_notes ) ) : ?>
				<h3><?php esc_html_e( 'Internal notes', 'fraud-tenant-core' ); ?></h3>
				<p class="ft-prose ft-muted"><?php echo nl2br( esc_html( (string) $x->internal_notes ) ); ?></p>
			<?php endif; ?>
		</section>
		<section class="ft-card">
			<div class="ft-card-head"><h2><?php esc_html_e( 'Decision history', 'fraud-tenant-core' ); ?></h2></div>
			<ol class="ft-timeline"><?php foreach ( DisputeService::get_decision_history( $did ) as $h ) : ?><li><strong class="ft-mono"><?php echo esc_html( (string) $h->action ); ?></strong> · <?php echo esc_html( (string) $h->actor ); ?> · <?php echo C::date( $h->created_at, true ); // phpcs:ignore ?></li><?php endforeach; ?></ol>
		</section>
	</div>
	<?php
	return;
}

$status = C::get( 'status', 'all' );
$search = C::get( 'q' );
$page   = max( 1, absint( C::get( 'pg', '1' ) ) );
$q      = DisputeService::get_admin_disputes_queue( array( 'status' => $status, 'search' => $search, 'page' => $page, 'per_page' => 25 ), $uid );
$counts = DisputeService::get_queue_status_counts();
C::head( __( 'Tenant Disputes', 'fraud-tenant-core' ), __( 'Tenants dispute reviewed records associated with their verified identity.', 'fraud-tenant-core' ) );
C::pills(
	'disputes',
	'status',
	array(
		/* translators: %d: count */
		'all'          => sprintf( __( 'All (%d)', 'fraud-tenant-core' ), $counts['all'] ),
		/* translators: %d: count */
		'pending'      => sprintf( __( 'Pending (%d)', 'fraud-tenant-core' ), $counts['pending'] ),
		/* translators: %d: count */
		'under_review' => sprintf( __( 'Under review (%d)', 'fraud-tenant-core' ), $counts['under_review'] ),
		/* translators: %d: count */
		'resolved'     => sprintf( __( 'Resolved (%d)', 'fraud-tenant-core' ), $counts['resolved'] ),
		/* translators: %d: count */
		'rejected'     => sprintf( __( 'Rejected (%d)', 'fraud-tenant-core' ), $counts['rejected'] ),
	),
	$status,
	array( 'q' => $search )
);
?>
<div class="ft-card"><div class="ft-table-wrap"><table class="ft-table">
	<thead><tr><th><?php esc_html_e( 'Dispute', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Report', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Tenant', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Reason', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Evidence', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Status', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Filed', 'fraud-tenant-core' ); ?></th></tr></thead>
	<tbody>
	<?php foreach ( $q['items'] as $x ) : ?>
		<tr>
			<td><a href="<?php echo esc_url( C::url( 'disputes', array( 'dispute_id' => (int) $x->id ) ) ); ?>"><?php echo esc_html( $x->reference ); ?></a></td>
			<td><?php echo esc_html( $x->report_reference ); ?></td>
			<td><?php echo esc_html( (string) $x->tenant_name ); ?></td>
			<td><?php echo esc_html( $x->reason_label ); ?></td>
			<td><?php echo (int) $x->evidence_count; ?></td>
			<td><?php echo C::badge( (string) $x->status, $x->status_label ); // phpcs:ignore ?></td>
			<td><?php echo C::date( $x->created_at ); // phpcs:ignore ?></td>
		</tr>
	<?php endforeach; ?>
	<?php if ( ! $q['items'] ) : ?><tr><td colspan="7"><?php C::empty_state( __( 'No disputes in this list.', 'fraud-tenant-core' ) ); ?></td></tr><?php endif; ?>
	</tbody>
</table></div></div>
<?php C::pagination( 'disputes', $page, (int) $q['total'], 25, array( 'status' => $status, 'q' => $search ) ); ?>
