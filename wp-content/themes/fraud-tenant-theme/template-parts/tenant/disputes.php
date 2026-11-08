<?php
/**
 * Tenant — My Disputes (open disputes; dispute detail for any of the tenant's own disputes).
 *
 * @package FraudTenantTheme
 */

defined( 'ABSPATH' ) || exit;

use FraudTenant\Services\DisputeService;

$d    = $args['data'];
$base = $args['base'];
$did  = absint( $_GET['dispute_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification

if ( $did ) {
	get_template_part( 'template-parts/tenant/dispute-detail', null, $args + array( 'dispute_id' => $did ) );
	return;
}
?>
<header class="ft-page-head"><div><h1><?php esc_html_e( 'My disputes', 'fraud-tenant-theme' ); ?></h1><p><?php esc_html_e( 'Disputes that are waiting for or under review.', 'fraud-tenant-theme' ); ?></p></div><a class="ft-btn ft-btn-primary" href="<?php echo esc_url( ft_app_url( $base, 'submit-dispute' ) ); ?>"><?php esc_html_e( 'Submit a dispute', 'fraud-tenant-theme' ); ?></a></header>
<?php if ( ! $d['active_disputes'] ) : ?>
	<?php ft_empty( __( 'You have no open disputes.', 'fraud-tenant-theme' ) ); ?>
<?php else : ?>
	<?php foreach ( $d['active_disputes'] as $x ) : ?>
		<article class="ft-record">
			<div class="ft-record-head"><strong><a href="<?php echo esc_url( ft_app_url( $base, 'disputes', array( 'dispute_id' => $x->id ) ) ); ?>"><?php echo esc_html( $x->reference . ' · ' . $x->reason_label ); ?></a></strong><?php echo ft_badge( (string) $x->status, $x->status_label ); // phpcs:ignore ?></div>
			<div class="ft-meta"><span><?php echo esc_html( $x->report_reference ); ?></span><span><?php echo esc_html__( 'Filed', 'fraud-tenant-theme' ) . ' ' . esc_html( $x->created_at_fmt ); ?></span><span><?php echo esc_html( sprintf( /* translators: %d: count */ _n( '%d evidence file', '%d evidence files', (int) $x->evidence_count, 'fraud-tenant-theme' ), (int) $x->evidence_count ) ); ?></span></div>
		</article>
	<?php endforeach; ?>
<?php endif; ?>
