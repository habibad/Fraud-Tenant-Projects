<?php
/**
 * Tenant — Dispute History (decided disputes; kept even if a record was later disassociated).
 *
 * @package FraudTenantTheme
 */

defined( 'ABSPATH' ) || exit;

$d    = $args['data'];
$base = $args['base'];
$did  = absint( $_GET['dispute_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
if ( $did ) {
	get_template_part( 'template-parts/tenant/dispute-detail', null, $args + array( 'dispute_id' => $did ) );
	return;
}
?>
<header class="ft-page-head"><div><h1><?php esc_html_e( 'Dispute history', 'fraud-tenant-theme' ); ?></h1><p><?php esc_html_e( 'Every dispute you have filed and its decision.', 'fraud-tenant-theme' ); ?></p></div></header>
<?php if ( ! $d['dispute_history'] ) : ?>
	<?php ft_empty( __( 'No decided disputes yet.', 'fraud-tenant-theme' ) ); ?>
<?php else : ?>
	<div class="ft-card"><div class="ft-table-wrap"><table class="ft-table ft-stack">
		<thead><tr><th><?php esc_html_e( 'Dispute', 'fraud-tenant-theme' ); ?></th><th><?php esc_html_e( 'Record', 'fraud-tenant-theme' ); ?></th><th><?php esc_html_e( 'Decision', 'fraud-tenant-theme' ); ?></th><th><?php esc_html_e( 'Decided', 'fraud-tenant-theme' ); ?></th></tr></thead>
		<tbody><?php foreach ( $d['dispute_history'] as $x ) : ?>
			<tr>
				<td data-label="<?php esc_attr_e( 'Dispute', 'fraud-tenant-theme' ); ?>"><a href="<?php echo esc_url( ft_app_url( $base, 'dispute-history', array( 'dispute_id' => $x->id ) ) ); ?>"><?php echo esc_html( $x->reference ); ?></a><br><span class="ft-muted"><?php echo esc_html( $x->reason_label ); ?></span></td>
				<td data-label="<?php esc_attr_e( 'Record', 'fraud-tenant-theme' ); ?>"><?php echo esc_html( $x->report_reference ); ?><?php echo $x->report_visible ? '' : ' <span class="ft-muted">(' . esc_html__( 'no longer on your account', 'fraud-tenant-theme' ) . ')</span>'; ?></td>
				<td data-label="<?php esc_attr_e( 'Decision', 'fraud-tenant-theme' ); ?>"><?php echo ft_badge( (string) $x->status, $x->status_label ); // phpcs:ignore ?></td>
				<td data-label="<?php esc_attr_e( 'Decided', 'fraud-tenant-theme' ); ?>"><?php echo esc_html( $x->resolved_at_fmt ); ?></td>
			</tr>
		<?php endforeach; ?></tbody>
	</table></div></div>
<?php endif; ?>
