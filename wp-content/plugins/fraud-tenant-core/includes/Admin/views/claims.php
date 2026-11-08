<?php
/**
 * Tenant Account Claims — verify that a tenant account really belongs to a central identity.
 *
 * @package FraudTenant\Admin
 */

defined( 'ABSPATH' ) || exit;

use FraudTenant\Admin\AdminConsole as C;
use FraudTenant\Services\ComplianceService;
use FraudTenant\Services\IdentityClaimService;

C::head( __( 'Tenant Account Claims', 'fraud-tenant-core' ), __( 'A claim links a tenant account to its identity record. Compare the claimed details with the uploaded document before deciding.', 'fraud-tenant-core' ) );
if ( ! ComplianceService::identity_ready() ) {
	echo '<div class="ft-alert is-warning">' . esc_html__( 'Identity data is locked until the match and encryption keys are configured.', 'fraud-tenant-core' ) . '</div>';
	return;
}
$uid = get_current_user_id();
$cid = absint( C::get( 'claim_id' ) );
$labels = array(
	IdentityClaimService::RESULT_MATCH            => __( 'Name + SSN match an existing identity', 'fraud-tenant-core' ),
	IdentityClaimService::RESULT_NAME_MISMATCH    => __( 'SSN matches an identity, name differs', 'fraud-tenant-core' ),
	IdentityClaimService::RESULT_NO_IDENTITY      => __( 'No identity on file yet (approval creates it)', 'fraud-tenant-core' ),
	IdentityClaimService::RESULT_CLAIMED_BY_OTHER => __( 'Identity already claimed by another account', 'fraud-tenant-core' ),
);

if ( $cid ) {
	$d = IdentityClaimService::get_detail( $cid, $uid );
	if ( ! $d ) {
		C::empty_state( __( 'Claim not found.', 'fraud-tenant-core' ) );
		return;
	}
	$c = $d['claim'];
	echo '<p><a href="' . esc_url( C::url( 'claims' ) ) . '">← ' . esc_html__( 'All claims', 'fraud-tenant-core' ) . '</a></p>';
	?>
	<div class="ft-grid ft-grid-2">
		<section class="ft-card">
			<div class="ft-card-head"><h2>IDC-<?php echo esc_html( sprintf( '%06d', (int) $c->id ) ); ?></h2><?php echo C::badge( (string) $c->status ); // phpcs:ignore ?></div>
			<div class="ft-compare">
				<div><span class="ft-label"><?php esc_html_e( 'Claimed by tenant', 'fraud-tenant-core' ); ?></span><strong><?php echo esc_html( trim( ( $d['claimed_name']['first'] ?? '' ) . ' ' . ( $d['claimed_name']['last'] ?? '' ) ) ); ?></strong><span class="ft-muted">SSN ••• <?php echo esc_html( (string) $c->ssn_last4 ); ?></span><span class="ft-muted"><a href="<?php echo esc_url( C::url( 'users', array( 'user_id' => (int) $c->user_id ) ) ); ?>"><?php echo esc_html( (string) $c->display_name ); ?></a> · <?php echo esc_html( (string) $c->user_email ); ?></span></div>
				<div><span class="ft-label"><?php esc_html_e( 'Identity on file', 'fraud-tenant-core' ); ?></span>
					<?php if ( $d['candidate'] ) : ?>
						<strong><?php echo esc_html( trim( $d['candidate_name']['first'] . ' ' . $d['candidate_name']['last'] ) ); ?></strong>
						<span class="ft-muted"><a href="<?php echo esc_url( C::url( 'identities', array( 'identity_id' => (int) $d['candidate']->id ) ) ); ?>">#<?php echo (int) $d['candidate']->id; ?></a> · <?php echo (int) $d['reports']; ?> <?php esc_html_e( 'linked reports', 'fraud-tenant-core' ); ?></span>
					<?php else : ?>
						<strong>—</strong><span class="ft-muted"><?php esc_html_e( 'No identity exists for this SSN yet.', 'fraud-tenant-core' ); ?></span>
					<?php endif; ?>
				</div>
			</div>
			<p><?php echo C::badge( (string) $c->match_result, $labels[ $c->match_result ] ?? $c->match_result ); // phpcs:ignore ?></p>
			<p><?php echo $d['document'] ? C::doc_link( (int) $d['document']->id, __( 'Open verification document', 'fraud-tenant-core' ) ) : esc_html__( 'No document', 'fraud-tenant-core' ); // phpcs:ignore ?></p>
			<p class="ft-muted"><?php echo esc_html__( 'Submitted', 'fraud-tenant-core' ) . ' ' . C::date( $c->created_at, true ); // phpcs:ignore ?></p>
			<?php if ( 'pending' !== $c->status ) : ?>
				<p><?php echo esc_html__( 'Decided', 'fraud-tenant-core' ) . ' ' . C::date( $c->decided_at, true ); // phpcs:ignore ?> — <?php echo esc_html( (string) $c->decision_note ); ?></p>
			<?php endif; ?>
		</section>
		<?php if ( 'pending' === $c->status ) : ?>
		<section class="ft-card">
			<div class="ft-card-head"><h2><?php esc_html_e( 'Decision', 'fraud-tenant-core' ); ?></h2></div>
			<?php C::form( 'claim_approve', array( 'claim_id' => (int) $c->id ) ); ?>
				<label class="ft-label" for="ft-ca"><?php esc_html_e( 'Verification note (internal)', 'fraud-tenant-core' ); ?></label>
				<textarea class="ft-textarea" id="ft-ca" name="note"></textarea>
				<button class="ft-btn ft-btn-primary" type="submit" <?php disabled( IdentityClaimService::RESULT_CLAIMED_BY_OTHER === $c->match_result ); ?>><?php esc_html_e( 'Approve — identity verified', 'fraud-tenant-core' ); ?></button>
				<?php if ( IdentityClaimService::RESULT_NAME_MISMATCH === $c->match_result ) : ?><p class="ft-help"><?php esc_html_e( 'Approve only if the document proves the name difference (e.g. legal name change).', 'fraud-tenant-core' ); ?></p><?php endif; ?>
			</form>
			<?php C::form( 'claim_reject', array( 'claim_id' => (int) $c->id ) ); ?>
				<label class="ft-label" for="ft-cr"><?php esc_html_e( 'Reason shown to the tenant', 'fraud-tenant-core' ); ?></label>
				<textarea class="ft-textarea" id="ft-cr" name="note" required minlength="5"><?php esc_html_e( 'We could not verify your identity from the document provided. Please submit a clear, current government ID or a lease in your name.', 'fraud-tenant-core' ); ?></textarea>
				<button class="ft-btn ft-btn-danger" type="submit"><?php esc_html_e( 'Reject claim', 'fraud-tenant-core' ); ?></button>
			</form>
		</section>
		<?php endif; ?>
	</div>
	<?php
	return;
}

$status = C::get( 'status', 'pending' );
$rows   = IdentityClaimService::get_queue( in_array( $status, array( 'pending', 'approved', 'rejected', 'all' ), true ) ? $status : 'pending' );
C::pills( 'claims', 'status', array( 'pending' => __( 'Pending', 'fraud-tenant-core' ), 'approved' => __( 'Approved', 'fraud-tenant-core' ), 'rejected' => __( 'Rejected', 'fraud-tenant-core' ), 'all' => __( 'All', 'fraud-tenant-core' ) ), $status );
?>
<div class="ft-card"><div class="ft-table-wrap"><table class="ft-table">
	<thead><tr><th><?php esc_html_e( 'Claim', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Tenant account', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'SSN', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Match', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Status', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Submitted', 'fraud-tenant-core' ); ?></th></tr></thead>
	<tbody>
	<?php foreach ( $rows as $c ) : ?>
		<tr>
			<td><a href="<?php echo esc_url( C::url( 'claims', array( 'claim_id' => (int) $c->id ) ) ); ?>">IDC-<?php echo esc_html( sprintf( '%06d', (int) $c->id ) ); ?></a></td>
			<td><?php echo esc_html( (string) $c->display_name ); ?><br><span class="ft-muted"><?php echo esc_html( (string) $c->user_email ); ?></span></td>
			<td>••• <?php echo esc_html( (string) $c->ssn_last4 ); ?></td>
			<td><?php echo C::badge( (string) $c->match_result, $labels[ $c->match_result ] ?? $c->match_result ); // phpcs:ignore ?></td>
			<td><?php echo C::badge( (string) $c->status ); // phpcs:ignore ?></td>
			<td><?php echo C::date( $c->created_at ); // phpcs:ignore ?></td>
		</tr>
	<?php endforeach; ?>
	<?php if ( ! $rows ) : ?><tr><td colspan="6"><?php C::empty_state( __( 'No claims in this list.', 'fraud-tenant-core' ) ); ?></td></tr><?php endif; ?>
	</tbody>
</table></div></div>
