<?php
/**
 * Owner — Add Property (plan quota enforced server-side; the form only reflects it).
 *
 * @package FraudTenantTheme
 */

defined( 'ABSPATH' ) || exit;

use FraudTenant\Services\PropertyService;

$d    = $args['data'];
$base = $args['base'];
$old  = ft_old_input();
?>
<header class="ft-page-head"><div><h1><?php esc_html_e( 'Add a property', 'fraud-tenant-theme' ); ?></h1><p><?php esc_html_e( 'Register a rental property you own or manage. Upload proof of ownership so our team can verify it — verification unlocks screening and incident reporting for that property.', 'fraud-tenant-theme' ); ?></p></div></header>
<?php if ( ! $d['can_add_property'] ) : ?>
	<div class="ft-lock">
		<?php echo ft_icon( 'building' ); // phpcs:ignore ?>
		<div>
			<strong><?php esc_html_e( 'Your plan\'s property limit is reached', 'fraud-tenant-theme' ); ?></strong>
			<p class="ft-muted"><?php esc_html_e( 'The Free plan includes 1 active property. Archive a property or upgrade to Standard for unlimited properties. Existing properties are never removed.', 'fraud-tenant-theme' ); ?></p>
			<a class="ft-btn ft-btn-primary" href="<?php echo esc_url( ft_app_url( $base, 'membership' ) ); ?>"><?php esc_html_e( 'View Standard plans', 'fraud-tenant-theme' ); ?></a>
		</div>
	</div>
	<?php
	return;
endif;
?>
<section class="ft-card">
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="ft-form">
		<?php ft_form_fields( 'ft_add_property', ft_app_url( $base, 'verification' ) ); ?>
		<?php wp_nonce_field( 'ft_add_property_action', 'ft_property_nonce' ); ?>
		<label class="ft-label" for="ft-ap-title"><?php esc_html_e( 'Property name (optional)', 'fraud-tenant-theme' ); ?></label>
		<input class="ft-input" id="ft-ap-title" name="title" value="<?php echo esc_attr( (string) ( $old['title'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. Maple Street Duplex', 'fraud-tenant-theme' ); ?>">
		<div class="ft-row">
			<div><label class="ft-label" for="ft-ap-street"><?php esc_html_e( 'Street address', 'fraud-tenant-theme' ); ?></label><input class="ft-input" id="ft-ap-street" name="address_street" required autocomplete="address-line1" value="<?php echo esc_attr( (string) ( $old['address_street'] ?? '' ) ); ?>"></div>
			<div><label class="ft-label" for="ft-ap-unit"><?php esc_html_e( 'Unit (optional)', 'fraud-tenant-theme' ); ?></label><input class="ft-input" id="ft-ap-unit" name="unit_number" autocomplete="address-line2" value="<?php echo esc_attr( (string) ( $old['unit_number'] ?? '' ) ); ?>"></div>
		</div>
		<div class="ft-row">
			<div><label class="ft-label" for="ft-ap-city"><?php esc_html_e( 'City', 'fraud-tenant-theme' ); ?></label><input class="ft-input" id="ft-ap-city" name="city" required autocomplete="address-level2" value="<?php echo esc_attr( (string) ( $old['city'] ?? '' ) ); ?>"></div>
			<div><label class="ft-label" for="ft-ap-state"><?php esc_html_e( 'State (2 letters)', 'fraud-tenant-theme' ); ?></label><input class="ft-input" id="ft-ap-state" name="state" required maxlength="2" pattern="[A-Za-z]{2}" autocomplete="address-level1" value="<?php echo esc_attr( (string) ( $old['state'] ?? '' ) ); ?>"></div>
			<div><label class="ft-label" for="ft-ap-zip"><?php esc_html_e( 'ZIP code', 'fraud-tenant-theme' ); ?></label><input class="ft-input" id="ft-ap-zip" name="postal_code" required inputmode="numeric" pattern="\d{5}(-\d{4})?" autocomplete="postal-code" value="<?php echo esc_attr( (string) ( $old['postal_code'] ?? '' ) ); ?>"></div>
		</div>
		<div class="ft-row">
			<div><label class="ft-label" for="ft-ap-type"><?php esc_html_e( 'Property type', 'fraud-tenant-theme' ); ?></label><select class="ft-select" id="ft-ap-type" name="property_type"><?php foreach ( PropertyService::ALLOWED_PROPERTY_TYPES as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></div>
			<div><label class="ft-label" for="ft-ap-units"><?php esc_html_e( 'Number of units', 'fraud-tenant-theme' ); ?></label><input class="ft-input" type="number" min="1" id="ft-ap-units" name="units_count" value="1"></div>
		</div>
		<label class="ft-label" for="ft-ap-doc"><?php esc_html_e( 'Proof of ownership (deed, tax record or management agreement — PDF, JPG or PNG, max 10 MB)', 'fraud-tenant-theme' ); ?></label>
		<input class="ft-input" type="file" id="ft-ap-doc" name="property_document" accept=".pdf,.png,.jpg,.jpeg">
		<p class="ft-help"><?php esc_html_e( 'Documents are stored privately and are only visible to you and the verification team.', 'fraud-tenant-theme' ); ?></p>
		<button class="ft-btn ft-btn-primary" type="submit"><?php esc_html_e( 'Add property', 'fraud-tenant-theme' ); ?></button>
	</form>
</section>
