<?php
/**
 * Report Association & Disclosure — exception-handling identity links and per-audience disclosure.
 * Most reports link automatically on submission; this area corrects mistakes and controls who sees
 * an approved report (the subject tenant / screening owners). All changes are recorded.
 *
 * @package FraudTenant\Admin
 */

defined( 'ABSPATH' ) || exit;

use FraudTenant\Admin\AdminConsole as C;
use FraudTenant\Services\ComplianceService;
use FraudTenant\Services\IdentityService;
use FraudTenant\Services\IncidentReviewService;
use FraudTenant\Services\IncidentService;
use FraudTenant\Services\ReportVisibilityService;

C::head( __( 'Report Association & Disclosure', 'fraud-tenant-core' ), __( 'Reports link to tenant identities automatically. Use this area for exceptions: correct or revoke a link, and choose who may see an approved report.', 'fraud-tenant-core' ) );
if ( ! ComplianceService::identity_ready() ) {
	echo '<div class="ft-alert is-warning">' . esc_html__( 'Identity data is locked until the match and encryption keys are configured.', 'fraud-tenant-core' ) . '</div>';
	return;
}
$uid = get_current_user_id();
$rid = absint( C::get( 'report_id' ) );

if ( $rid ) {
	$d = IncidentReviewService::get_incident_for_review( $rid, $uid );
	if ( empty( $d['success'] ) ) {
		echo '<div class="ft-alert is-error">' . esc_html( $d['message'] ?? '' ) . '</div>';
		return;
	}
	$r   = $d['report'];
	$own = (int) $r->owner_id === $uid;
	echo '<p><a href="' . esc_url( C::url( 'disclosure' ) ) . '">← ' . esc_html__( 'All reports', 'fraud-tenant-core' ) . '</a> · <a href="' . esc_url( C::url( 'incidents', array( 'report_id' => $rid ) ) ) . '">' . esc_html__( 'Open review', 'fraud-tenant-core' ) . '</a></p>';
	?>
	<div class="ft-grid ft-grid-2">
		<section class="ft-card">
			<div class="ft-card-head"><h2><?php echo esc_html( $r->reference . ' · ' . $r->category_label ); ?></h2><?php echo C::badge( (string) $r->status ); // phpcs:ignore ?></div>
			<dl class="ft-dl">
				<dt><?php esc_html_e( 'Identity link', 'fraud-tenant-core' ); ?></dt><dd><?php echo C::badge( (string) ( $r->identity_match ?: 'unlinked' ), $r->identity_label ); // phpcs:ignore ?> <?php echo $r->identity ? '<a href="' . esc_url( C::url( 'identities', array( 'identity_id' => (int) $r->identity->id ) ) ) . '">#' . (int) $r->identity->id . '</a>' : ''; ?></dd>
				<dt><?php esc_html_e( 'Tenant', 'fraud-tenant-core' ); ?></dt><dd><?php echo esc_html( $r->subject['name'] ?: '—' ); ?> <?php echo $r->subject['last4'] ? '· ••• ' . esc_html( $r->subject['last4'] ) : ''; ?></dd>
			</dl>
			<?php if ( $own ) : ?>
				<p class="ft-alert is-info"><?php esc_html_e( 'You submitted this report; another administrator must manage its disclosure.', 'fraud-tenant-core' ); ?></p>
			<?php else : ?>
				<?php if ( $r->identity ) : ?>
					<?php C::form( 'identity_unlink', array( 'report_id' => $rid ) ); ?>
						<label class="ft-label" for="ft-ul"><?php esc_html_e( 'Remove a mistaken identity link (reason)', 'fraud-tenant-core' ); ?></label>
						<textarea class="ft-textarea" id="ft-ul" name="reason" required minlength="5"></textarea>
						<button class="ft-btn ft-btn-danger" type="submit"><?php esc_html_e( 'Revoke association', 'fraud-tenant-core' ); ?></button>
						<p class="ft-help"><?php esc_html_e( 'The linked tenant loses access immediately; their own dispute history is kept.', 'fraud-tenant-core' ); ?></p>
					</form>
				<?php endif; ?>
				<details class="ft-details" <?php echo $r->identity ? '' : 'open'; ?>>
					<summary class="ft-btn ft-btn-secondary ft-btn-sm"><?php echo $r->identity ? esc_html__( 'Correct the association…', 'fraud-tenant-core' ) : esc_html__( 'Link to a tenant identity…', 'fraud-tenant-core' ); ?></summary>
					<?php C::form( 'identity_link_ssn', array( 'report_id' => $rid ) ); ?>
						<p class="ft-help"><?php esc_html_e( 'Enter the tenant\'s legal name and SSN from the lease. The same deterministic matching policy applies; the SSN is never stored.', 'fraud-tenant-core' ); ?></p>
						<div class="ft-row">
							<div><label class="ft-label" for="ft-lf"><?php esc_html_e( 'Legal first name', 'fraud-tenant-core' ); ?></label><input class="ft-input" id="ft-lf" name="first_name" required autocomplete="off"></div>
							<div><label class="ft-label" for="ft-ll"><?php esc_html_e( 'Legal last name', 'fraud-tenant-core' ); ?></label><input class="ft-input" id="ft-ll" name="last_name" required autocomplete="off"></div>
							<div><label class="ft-label" for="ft-ls"><?php esc_html_e( 'SSN', 'fraud-tenant-core' ); ?></label><input class="ft-input" id="ft-ls" name="ssn" type="password" inputmode="numeric" autocomplete="off" required pattern="\d{3}-?\d{2}-?\d{4}"></div>
						</div>
						<label class="ft-label" for="ft-lr"><?php esc_html_e( 'Reason', 'fraud-tenant-core' ); ?></label>
						<textarea class="ft-textarea" id="ft-lr" name="reason" required minlength="5"></textarea>
						<button class="ft-btn ft-btn-primary" type="submit"><?php esc_html_e( 'Link by legal name + SSN', 'fraud-tenant-core' ); ?></button>
					</form>
					<?php C::form( 'identity_link', array( 'report_id' => $rid ) ); ?>
						<div class="ft-row">
							<div><label class="ft-label" for="ft-li"><?php esc_html_e( 'Or identity ID', 'fraud-tenant-core' ); ?></label><input class="ft-input" id="ft-li" name="identity_id" type="number" min="1" required></div>
							<div><label class="ft-label" for="ft-lir"><?php esc_html_e( 'Reason', 'fraud-tenant-core' ); ?></label><input class="ft-input" id="ft-lir" name="reason" required minlength="5"></div>
						</div>
						<button class="ft-btn ft-btn-secondary" type="submit"><?php esc_html_e( 'Link to identity ID', 'fraud-tenant-core' ); ?></button>
					</form>
				</details>
			<?php endif; ?>
		</section>
		<section class="ft-card">
			<div class="ft-card-head"><h2><?php esc_html_e( 'Disclosure', 'fraud-tenant-core' ); ?></h2></div>
			<?php
			$audiences = array(
				'tenant'    => array( __( 'Subject tenant', 'fraud-tenant-core' ), (int) $r->disclosable, __( 'The verified tenant linked to this identity can view and dispute the report.', 'fraud-tenant-core' ) ),
				'screening' => array( __( 'Owner screening results', 'fraud-tenant-core' ), (int) $r->screening_disclosure, __( 'Other owners who search this identity see it (free: count only; Standard: factual summary).', 'fraud-tenant-core' ) ),
			);
			foreach ( $audiences as $aud => $a ) :
				?>
				<div class="ft-disclosure">
					<div><strong><?php echo esc_html( $a[0] ); ?></strong> <?php echo C::badge( $a[1] ? 'visible' : 'hidden', $a[1] ? __( 'Visible', 'fraud-tenant-core' ) : __( 'Hidden', 'fraud-tenant-core' ) ); // phpcs:ignore ?><p class="ft-help"><?php echo esc_html( $a[2] ); ?></p></div>
					<?php if ( ! $own ) : ?>
						<?php C::form( 'disclosure_set', array( 'report_id' => $rid, 'audience' => $aud, 'enable' => $a[1] ? '0' : '1' ) ); ?>
							<input class="ft-input" name="reason" required minlength="5" placeholder="<?php esc_attr_e( 'Reason', 'fraud-tenant-core' ); ?>" aria-label="<?php esc_attr_e( 'Reason', 'fraud-tenant-core' ); ?>">
							<button class="ft-btn <?php echo $a[1] ? 'ft-btn-secondary' : 'ft-btn-primary'; ?> ft-btn-sm" type="submit" <?php disabled( ! $a[1] && ( 'approved' !== $r->status || ! $r->identity ) ); ?>><?php echo $a[1] ? esc_html__( 'Withhold', 'fraud-tenant-core' ) : esc_html__( 'Disclose', 'fraud-tenant-core' ); ?></button>
						</form>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
			<?php if ( 'approved' !== $r->status || ! $r->identity ) : ?><p class="ft-help"><?php esc_html_e( 'Disclosure requires an approved report linked to the correct identity.', 'fraud-tenant-core' ); ?></p><?php endif; ?>
			<h3><?php esc_html_e( 'History', 'fraud-tenant-core' ); ?></h3>
			<?php if ( empty( $d['disclosure_history'] ) ) : C::empty_state( __( 'No changes recorded.', 'fraud-tenant-core' ) ); else : ?>
				<ol class="ft-timeline"><?php foreach ( $d['disclosure_history'] as $h ) : ?><li><strong><?php echo esc_html( ucwords( str_replace( '_', ' ', (string) $h->action ) ) ); ?></strong> · <?php echo esc_html( (string) $h->actor ); ?> · <?php echo C::date( $h->created_at, true ); // phpcs:ignore ?><br><span class="ft-muted"><?php echo esc_html( (string) $h->reason ); ?></span></li><?php endforeach; ?></ol>
			<?php endif; ?>
		</section>
	</div>
	<?php
	return;
}

$state  = C::get( 'state', 'all' );
$page   = max( 1, absint( C::get( 'pg', '1' ) ) );
$queue  = ReportVisibilityService::get_admin_queue( array( 'state' => $state, 'page' => $page, 'per_page' => 25 ) );
$counts = ReportVisibilityService::get_status_counts();
C::pills(
	'disclosure',
	'state',
	array(
		'all'               => __( 'All', 'fraud-tenant-core' ),
		/* translators: %d: count */
		'linked'            => sprintf( __( 'Linked (%d)', 'fraud-tenant-core' ), $counts['linked'] ),
		/* translators: %d: count */
		'unlinked'          => sprintf( __( 'Not linked (%d)', 'fraud-tenant-core' ), $counts['unlinked'] ),
		/* translators: %d: count */
		'exception'         => sprintf( __( 'Exceptions (%d)', 'fraud-tenant-core' ), $counts['exception'] ),
		/* translators: %d: count */
		'tenant_visible'    => sprintf( __( 'Visible to tenant (%d)', 'fraud-tenant-core' ), $counts['tenant_visible'] ),
		/* translators: %d: count */
		'screening_visible' => sprintf( __( 'In screening (%d)', 'fraud-tenant-core' ), $counts['screening_visible'] ),
	),
	$state
);
?>
<div class="ft-card"><div class="ft-table-wrap"><table class="ft-table">
	<thead><tr><th><?php esc_html_e( 'Report', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Status', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Identity', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Tenant account', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Tenant', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Screening', 'fraud-tenant-core' ); ?></th></tr></thead>
	<tbody>
	<?php foreach ( $queue['items'] as $i ) : ?>
		<tr>
			<td><a href="<?php echo esc_url( C::url( 'disclosure', array( 'report_id' => (int) $i->id ) ) ); ?>"><?php echo esc_html( $i->reference ); ?></a><br><span class="ft-muted"><?php echo esc_html( $i->category_label ); ?></span></td>
			<td><?php echo C::badge( (string) $i->status ); // phpcs:ignore ?></td>
			<td><?php echo C::badge( (string) ( $i->identity_match ?: 'unlinked' ), IncidentService::identity_state_label( $i->identity_match ) ); // phpcs:ignore ?></td>
			<td><?php echo $i->tenant_user_id ? C::badge( 'verified', __( 'Claimed', 'fraud-tenant-core' ) ) : '<span class="ft-muted">' . esc_html__( 'None yet', 'fraud-tenant-core' ) . '</span>'; // phpcs:ignore ?></td>
			<td><?php echo C::badge( $i->disclosable ? 'visible' : 'hidden' ); // phpcs:ignore ?></td>
			<td><?php echo C::badge( $i->screening_disclosure ? 'visible' : 'hidden' ); // phpcs:ignore ?></td>
		</tr>
	<?php endforeach; ?>
	<?php if ( ! $queue['items'] ) : ?><tr><td colspan="6"><?php C::empty_state( __( 'No reports in this list.', 'fraud-tenant-core' ) ); ?></td></tr><?php endif; ?>
	</tbody>
</table></div></div>
<?php C::pagination( 'disclosure', $page, (int) $queue['total'], 25, array( 'state' => $state ) ); ?>
