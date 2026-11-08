<?php
/**
 * Tenant — My Rental Records and Report Details (only the tenant's own disclosed records).
 *
 * @package FraudTenantTheme
 */

defined( 'ABSPATH' ) || exit;

use FraudTenant\Services\ReportVisibilityService;

$d    = $args['data'];
$base = $args['base'];
$rid  = absint( $_GET['report_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification

if ( $rid ) :
	$r = ReportVisibilityService::get_tenant_report( $rid, $args['tenant_id'] );
	if ( ! $r ) {
		ft_empty( __( 'Record not found.', 'fraud-tenant-theme' ), ft_app_url( $base, 'records' ), __( 'Back to records', 'fraud-tenant-theme' ) );
		return;
	}
	$open = in_array( (string) $r->my_dispute_status, array( 'pending', 'under_review' ), true );
	?>
	<p><a href="<?php echo esc_url( ft_app_url( $base, 'records' ) ); ?>">← <?php esc_html_e( 'All records', 'fraud-tenant-theme' ); ?></a></p>
	<header class="ft-page-head"><div><h1><?php echo esc_html( $r->reference . ' · ' . $r->category_label ); ?></h1><p><?php esc_html_e( 'Reviewed and approved by the Fraud Tenant review team.', 'fraud-tenant-theme' ); ?></p></div><?php echo ft_badge( 'approved', __( 'Reviewed', 'fraud-tenant-theme' ) ); // phpcs:ignore ?></header>
	<div class="ft-grid ft-grid-2">
		<section class="ft-card">
			<div class="ft-card-head"><h2><?php esc_html_e( 'Report details', 'fraud-tenant-theme' ); ?></h2></div>
			<dl class="ft-dl">
				<dt><?php esc_html_e( 'Category', 'fraud-tenant-theme' ); ?></dt><dd><?php echo esc_html( $r->category_label ); ?></dd>
				<dt><?php esc_html_e( 'Incident date', 'fraud-tenant-theme' ); ?></dt><dd><?php echo ft_date( $r->incident_date ); // phpcs:ignore ?></dd>
				<dt><?php esc_html_e( 'Amount', 'fraud-tenant-theme' ); ?></dt><dd><?php echo esc_html( $r->amount_formatted ); ?></dd>
				<dt><?php esc_html_e( 'Property', 'fraud-tenant-theme' ); ?></dt><dd><?php echo esc_html( trim( (string) $r->property_title . ( $r->property_unit ? ' #' . $r->property_unit : '' ) . ', ' . $r->property_city . ', ' . $r->property_state, ', ' ) ); ?></dd>
				<dt><?php esc_html_e( 'Reported', 'fraud-tenant-theme' ); ?></dt><dd><?php echo ft_date( $r->created_at ); // phpcs:ignore ?></dd>
			</dl>
			<h3><?php esc_html_e( 'Factual description from the property owner', 'fraud-tenant-theme' ); ?></h3>
			<p class="ft-prose"><?php echo nl2br( esc_html( (string) $r->factual_description ) ); ?></p>
		</section>
		<section class="ft-card">
			<div class="ft-card-head"><h2><?php esc_html_e( 'Dispute', 'fraud-tenant-theme' ); ?></h2></div>
			<?php if ( $r->my_dispute_id ) : ?>
				<p><?php echo ft_badge( (string) $r->my_dispute_status ); // phpcs:ignore ?> <a href="<?php echo esc_url( ft_app_url( $base, $open ? 'disputes' : 'dispute-history', array( 'dispute_id' => (int) $r->my_dispute_id ) ) ); ?>"><?php esc_html_e( 'View your dispute', 'fraud-tenant-theme' ); ?></a></p>
			<?php endif; ?>
			<?php if ( ! $open ) : ?>
				<p class="ft-muted"><?php esc_html_e( 'If this record is inaccurate, wrongly attributed to you, or already resolved, you can dispute it. Our team reviews every dispute.', 'fraud-tenant-theme' ); ?></p>
				<a class="ft-btn ft-btn-primary" href="<?php echo esc_url( ft_app_url( $base, 'submit-dispute', array( 'report_id' => $rid ) ) ); ?>"><?php esc_html_e( 'Dispute this record', 'fraud-tenant-theme' ); ?></a>
			<?php else : ?>
				<p class="ft-muted"><?php esc_html_e( 'Your dispute is being reviewed. Owners who screen you can see that this record is disputed.', 'fraud-tenant-theme' ); ?></p>
			<?php endif; ?>
		</section>
	</div>
	<?php
	return;
endif;
?>
<header class="ft-page-head"><div><h1><?php esc_html_e( 'My rental records', 'fraud-tenant-theme' ); ?></h1><p><?php esc_html_e( 'Reviewed incident reports associated with your verified identity.', 'fraud-tenant-theme' ); ?></p></div></header>
<?php if ( 'verified' !== $d['claim']['state'] ) : ?>
	<?php ft_empty( __( 'Verify your identity to see records associated with you.', 'fraud-tenant-theme' ), ft_app_url( $base, 'identity' ), __( 'Verify identity', 'fraud-tenant-theme' ) ); ?>
<?php elseif ( ! $d['records'] ) : ?>
	<?php ft_empty( __( 'No reviewed records are associated with your identity. This does not certify a complete rental history.', 'fraud-tenant-theme' ) ); ?>
<?php else : ?>
	<?php foreach ( $d['records'] as $r ) : ?>
		<article class="ft-record">
			<div class="ft-record-head"><strong><a href="<?php echo esc_url( ft_app_url( $base, 'records', array( 'report_id' => $r->id ) ) ); ?>"><?php echo esc_html( $r->category_label ); ?></a></strong><?php echo $r->my_dispute_status ? ft_badge( (string) $r->my_dispute_status, __( 'Disputed', 'fraud-tenant-theme' ) . ': ' . ucwords( str_replace( '_', ' ', (string) $r->my_dispute_status ) ) ) : ft_badge( 'approved', __( 'Reviewed', 'fraud-tenant-theme' ) ); // phpcs:ignore ?></div>
			<div class="ft-meta"><span><?php echo esc_html( $r->reference ); ?></span><span><?php echo ft_date( $r->incident_date ); // phpcs:ignore ?></span><span><?php echo esc_html( $r->amount_formatted ); ?></span><span><?php echo esc_html( trim( (string) $r->property_city . ', ' . (string) $r->property_state, ', ' ) ); ?></span></div>
			<a class="ft-btn ft-btn-secondary ft-btn-sm" href="<?php echo esc_url( ft_app_url( $base, 'records', array( 'report_id' => $r->id ) ) ); ?>"><?php esc_html_e( 'View details', 'fraud-tenant-theme' ); ?></a>
		</article>
	<?php endforeach; ?>
<?php endif; ?>
