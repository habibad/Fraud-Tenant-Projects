<?php
/**
 * Tenant — Submit Dispute (only for the tenant's own visible records).
 *
 * @package FraudTenantTheme
 */

defined( 'ABSPATH' ) || exit;

use FraudTenant\Services\DisputeService;

$d        = $args['data'];
$base     = $args['base'];
$old      = ft_old_input();
$selected = absint( $_GET['report_id'] ?? ( $old['report_id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification
$eligible = array_values( array_filter( $d['records'], static fn( $r ) => ! in_array( (string) $r->my_dispute_status, array( 'pending', 'under_review' ), true ) ) );
?>
<header class="ft-page-head"><div><h1><?php esc_html_e( 'Submit a dispute', 'fraud-tenant-theme' ); ?></h1><p><?php esc_html_e( 'Explain clearly what is inaccurate. Add documents that support your explanation, such as receipts or a signed move-out inspection.', 'fraud-tenant-theme' ); ?></p></div></header>
<?php if ( 'verified' !== $d['claim']['state'] ) : ?>
	<?php ft_empty( __( 'Verify your identity first to see and dispute your records.', 'fraud-tenant-theme' ), ft_app_url( $base, 'identity' ), __( 'Verify identity', 'fraud-tenant-theme' ) ); ?>
<?php elseif ( ! $eligible ) : ?>
	<?php ft_empty( __( 'There are no records you can dispute right now.', 'fraud-tenant-theme' ) ); ?>
<?php else : ?>
	<section class="ft-card">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="ft-form">
			<input type="hidden" name="action" value="ft_submit_tenant_dispute">
			<?php wp_nonce_field( 'ft_submit_dispute_' . (int) $args['tenant_id'], 'ft_dispute_nonce' ); ?>
			<label class="ft-label" for="ft-dr"><?php esc_html_e( 'Record', 'fraud-tenant-theme' ); ?></label>
			<select class="ft-select" id="ft-dr" name="report_id" required><?php foreach ( $eligible as $r ) : ?><option value="<?php echo (int) $r->id; ?>" <?php selected( $selected, (int) $r->id ); ?>><?php echo esc_html( $r->reference . ' · ' . $r->category_label . ' · ' . wp_strip_all_tags( ft_date( $r->incident_date ) ) ); ?></option><?php endforeach; ?></select>
			<label class="ft-label" for="ft-drs"><?php esc_html_e( 'Reason', 'fraud-tenant-theme' ); ?></label>
			<select class="ft-select" id="ft-drs" name="dispute_reason" required><?php foreach ( DisputeService::get_allowed_reasons() as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( (string) ( $old['dispute_reason'] ?? '' ), $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select>
			<label class="ft-label" for="ft-dst"><?php esc_html_e( 'Your explanation (10–3,000 characters)', 'fraud-tenant-theme' ); ?></label>
			<textarea class="ft-textarea" id="ft-dst" name="statement" required minlength="10" maxlength="3000" rows="6"><?php echo esc_textarea( (string) ( $old['statement'] ?? '' ) ); ?></textarea>
			<label class="ft-label" for="ft-dev"><?php esc_html_e( 'Counter-evidence (optional — PDF, JPG or PNG, max 10 MB)', 'fraud-tenant-theme' ); ?></label>
			<input class="ft-input" type="file" id="ft-dev" name="dispute_evidence" accept=".pdf,.png,.jpg,.jpeg">
			<p class="ft-help"><?php esc_html_e( 'Your statement and evidence are seen only by the review team. Owners who screen you only see that a dispute exists and its outcome.', 'fraud-tenant-theme' ); ?></p>
			<button class="ft-btn ft-btn-primary" type="submit"><?php esc_html_e( 'Submit dispute', 'fraud-tenant-theme' ); ?></button>
		</form>
	</section>
<?php endif; ?>
