<?php
/**
 * Tenant — one dispute (the tenant's own; report details only while the report is still theirs).
 *
 * @package FraudTenantTheme
 */

defined( 'ABSPATH' ) || exit;

use FraudTenant\Services\DisputeService;

$base = $args['base'];
$det  = DisputeService::get_dispute_detail( (int) $args['dispute_id'], (int) $args['tenant_id'] );
if ( ! $det || (int) $det['dispute']->tenant_id !== (int) $args['tenant_id'] ) {
	ft_empty( __( 'Dispute not found.', 'fraud-tenant-theme' ), ft_app_url( $base, 'disputes' ), __( 'Back to disputes', 'fraud-tenant-theme' ) );
	return;
}
$x = $det['dispute'];
?>
<p><a href="<?php echo esc_url( ft_app_url( $base, in_array( $x->status, array( 'pending', 'under_review' ), true ) ? 'disputes' : 'dispute-history' ) ); ?>">← <?php esc_html_e( 'Back', 'fraud-tenant-theme' ); ?></a></p>
<header class="ft-page-head"><div><h1><?php echo esc_html( $x->reference . ' · ' . $x->reason_label ); ?></h1><p><?php echo esc_html__( 'Filed', 'fraud-tenant-theme' ) . ' ' . esc_html( $x->created_at_fmt ); ?></p></div><?php echo ft_badge( (string) $x->status, $x->status_label ); // phpcs:ignore ?></header>
<div class="ft-grid ft-grid-2">
	<section class="ft-card">
		<div class="ft-card-head"><h2><?php esc_html_e( 'Your statement', 'fraud-tenant-theme' ); ?></h2></div>
		<p class="ft-prose"><?php echo nl2br( esc_html( (string) $x->statement ) ); ?></p>
		<h3><?php esc_html_e( 'Your evidence', 'fraud-tenant-theme' ); ?></h3>
		<?php if ( ! $det['evidence_docs'] ) : ft_empty( __( 'No evidence attached.', 'fraud-tenant-theme' ) ); else : ?>
			<ul class="ft-list"><?php foreach ( $det['evidence_docs'] as $doc ) : ?><li><?php echo esc_html( $doc->original_name ); ?> <?php echo \FraudTenant\Admin\AdminConsole::doc_link( (int) $doc->id, __( 'View', 'fraud-tenant-theme' ) ); // phpcs:ignore ?></li><?php endforeach; ?></ul>
		<?php endif; ?>
	</section>
	<section class="ft-card">
		<div class="ft-card-head"><h2><?php esc_html_e( 'Decision', 'fraud-tenant-theme' ); ?></h2></div>
		<?php if ( in_array( $x->status, array( 'pending', 'under_review' ), true ) ) : ?>
			<p class="ft-muted"><?php esc_html_e( 'Our team is reviewing your dispute. You will be notified when a decision is recorded.', 'fraud-tenant-theme' ); ?></p>
		<?php else : ?>
			<p><?php echo esc_html__( 'Decided', 'fraud-tenant-theme' ) . ' ' . esc_html( $x->resolved_at_fmt ); ?></p>
			<p class="ft-prose"><?php echo nl2br( esc_html( (string) $x->resolution_notes ) ); ?></p>
		<?php endif; ?>
		<h3><?php esc_html_e( 'Disputed record', 'fraud-tenant-theme' ); ?></h3>
		<?php if ( ! empty( $det['report']['category_label'] ) ) : ?>
			<p><a href="<?php echo esc_url( ft_app_url( $base, 'records', array( 'report_id' => (int) $det['report']['id'] ) ) ); ?>"><?php echo esc_html( $det['report']['reference'] . ' · ' . $det['report']['category_label'] ); ?></a></p>
		<?php else : ?>
			<p class="ft-muted"><?php echo esc_html( sprintf( /* translators: %s: reference */ __( 'Report %s is no longer associated with your identity.', 'fraud-tenant-theme' ), $x->report_reference ) ); ?></p>
		<?php endif; ?>
	</section>
</div>
