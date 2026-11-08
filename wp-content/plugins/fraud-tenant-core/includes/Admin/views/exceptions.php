<?php
/**
 * Identity Match Exceptions — reports whose SSN matched an identity but whose name did not.
 * Fail closed: such reports are never linked automatically. Resolve by confirming the existing
 * identity (documented name variation) or returning the report to the owner for correction.
 *
 * @package FraudTenant\Admin
 */

defined( 'ABSPATH' ) || exit;

use FraudTenant\Admin\AdminConsole as C;
use FraudTenant\Services\ComplianceService;
use FraudTenant\Services\IdentityService;
use FraudTenant\Services\IncidentService;

C::head( __( 'Identity Match Exceptions', 'fraud-tenant-core' ), __( 'The SSN on these reports belongs to an existing identity, but the reported legal name differs. Nothing is linked or disclosed until you decide.', 'fraud-tenant-core' ) );
if ( ! ComplianceService::identity_ready() ) {
	echo '<div class="ft-alert is-warning">' . esc_html__( 'Identity data is locked until the match and encryption keys are configured.', 'fraud-tenant-core' ) . '</div>';
	return;
}
$rows = IdentityService::get_exceptions();
if ( ! $rows ) {
	C::empty_state( __( 'No open identity exceptions.', 'fraud-tenant-core' ) );
	return;
}
foreach ( $rows as $r ) :
	$cand = $r->candidate;
	$cn   = $cand ? IdentityService::name( $cand ) : array( 'first' => '', 'last' => '' );
	?>
	<section class="ft-card">
		<div class="ft-card-head">
			<h2><a href="<?php echo esc_url( C::url( 'incidents', array( 'report_id' => (int) $r->id ) ) ); ?>">INC-<?php echo esc_html( sprintf( '%06d', (int) $r->id ) ); ?></a> · <?php echo esc_html( IncidentService::ALLOWED_CATEGORIES[ $r->category ] ?? $r->category ); ?></h2>
			<?php echo C::badge( (string) $r->status ); // phpcs:ignore ?>
		</div>
		<div class="ft-compare">
			<div><span class="ft-label"><?php esc_html_e( 'Reported by owner', 'fraud-tenant-core' ); ?></span><strong><?php echo esc_html( trim( ( $r->reported_name['first'] ?? '' ) . ' ' . ( $r->reported_name['last'] ?? '' ) ) ); ?></strong><span class="ft-muted">SSN ••• <?php echo esc_html( (string) $r->subject_ssn_last4 ); ?> · <?php echo esc_html( (string) $r->owner_name ); ?></span></div>
			<div><span class="ft-label"><?php esc_html_e( 'Identity on file', 'fraud-tenant-core' ); ?></span><strong><?php echo esc_html( trim( $cn['first'] . ' ' . $cn['last'] ) ?: '—' ); ?></strong><span class="ft-muted"><?php echo $cand ? '#' . (int) $cand->id . ' · ' . ( $cand->user_id ? esc_html__( 'claimed', 'fraud-tenant-core' ) : esc_html__( 'unclaimed', 'fraud-tenant-core' ) ) : ''; ?></span></div>
		</div>
		<div class="ft-row">
			<?php C::form( 'exception_resolve', array( 'report_id' => (int) $r->id, 'resolution' => 'confirm' ) ); ?>
				<label class="ft-label" for="ft-ex-c-<?php echo (int) $r->id; ?>"><?php esc_html_e( 'Same person (documented name variation) — reason', 'fraud-tenant-core' ); ?></label>
				<textarea class="ft-textarea" id="ft-ex-c-<?php echo (int) $r->id; ?>" name="note" required minlength="5"></textarea>
				<button class="ft-btn ft-btn-primary" type="submit" <?php disabled( ! $cand ); ?>><?php esc_html_e( 'Confirm & link to identity', 'fraud-tenant-core' ); ?></button>
			</form>
			<?php if ( 'submitted' === $r->status ) : ?>
			<?php C::form( 'exception_resolve', array( 'report_id' => (int) $r->id, 'resolution' => 'clarify' ) ); ?>
				<label class="ft-label" for="ft-ex-q-<?php echo (int) $r->id; ?>"><?php esc_html_e( 'Ask the owner to correct the tenant details', 'fraud-tenant-core' ); ?></label>
				<textarea class="ft-textarea" id="ft-ex-q-<?php echo (int) $r->id; ?>" name="note" required minlength="5"></textarea>
				<button class="ft-btn ft-btn-secondary" type="submit"><?php esc_html_e( 'Return to owner', 'fraud-tenant-core' ); ?></button>
			</form>
			<?php else : ?>
				<p class="ft-muted"><?php esc_html_e( 'Waiting for the owner\'s correction.', 'fraud-tenant-core' ); ?></p>
			<?php endif; ?>
		</div>
	</section>
<?php endforeach; ?>
