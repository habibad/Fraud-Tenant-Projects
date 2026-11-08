<?php
/**
 * Owner — Property Verification Status (what each property needs before screening/reporting).
 *
 * @package FraudTenantTheme
 */

defined( 'ABSPATH' ) || exit;

use FraudTenant\Utils\Constants;

$d    = $args['data'];
$base = $args['base'];
$next = array(
	Constants::PROP_STATUS_PENDING        => __( 'Under review. Make sure an ownership document is uploaded.', 'fraud-tenant-theme' ),
	Constants::PROP_STATUS_INFO_REQUESTED => __( 'The review team needs more information — see their message and upload the requested document.', 'fraud-tenant-theme' ),
	Constants::PROP_STATUS_REJECTED       => __( 'Not verified. Read the reason and upload a valid ownership document.', 'fraud-tenant-theme' ),
	Constants::PROP_STATUS_VERIFIED       => __( 'Verified — screening and incident reporting are available.', 'fraud-tenant-theme' ),
);
?>
<header class="ft-page-head"><div><h1><?php esc_html_e( 'Property verification status', 'fraud-tenant-theme' ); ?></h1><p><?php esc_html_e( 'Ownership verification protects tenants from misuse: you can only screen applicants or report incidents for properties we have verified. Your dashboard is available regardless.', 'fraud-tenant-theme' ); ?></p></div></header>
<?php if ( ! $d['properties'] ) : ?>
	<?php ft_empty( __( 'You have no active properties yet.', 'fraud-tenant-theme' ), ft_app_url( $base, 'add-property' ), __( 'Add property', 'fraud-tenant-theme' ) ); ?>
<?php else : ?>
	<?php foreach ( $d['properties'] as $p ) : ?>
		<article class="ft-record">
			<div class="ft-record-head">
				<strong><a href="<?php echo esc_url( ft_app_url( $base, 'properties', array( 'property_id' => (int) $p->id ) ) ); ?>"><?php echo esc_html( $p->title ?: $p->address_street ); ?></a></strong>
				<?php echo ft_badge( (string) $p->status ); // phpcs:ignore ?>
			</div>
			<div class="ft-meta"><span><?php echo esc_html( $p->city . ', ' . $p->state . ' ' . $p->postal_code ); ?></span><span><?php echo esc_html__( 'Submitted', 'fraud-tenant-theme' ) . ' ' . ft_date( $p->created_at ); // phpcs:ignore ?></span></div>
			<p class="ft-prose"><?php echo esc_html( $next[ $p->status ] ?? '' ); ?></p>
			<?php if ( ! empty( $p->rejection_reason ) && Constants::PROP_STATUS_VERIFIED !== $p->status ) : ?>
				<div class="ft-alert is-warning"><strong><?php esc_html_e( 'Message from the review team:', 'fraud-tenant-theme' ); ?></strong> <?php echo esc_html( (string) $p->rejection_reason ); ?></div>
			<?php endif; ?>
			<?php if ( Constants::PROP_STATUS_VERIFIED !== $p->status ) : ?>
				<a class="ft-btn ft-btn-secondary ft-btn-sm" href="<?php echo esc_url( ft_app_url( $base, 'properties', array( 'property_id' => (int) $p->id ) ) ); ?>"><?php esc_html_e( 'Upload ownership document', 'fraud-tenant-theme' ); ?></a>
			<?php endif; ?>
		</article>
	<?php endforeach; ?>
<?php endif; ?>
