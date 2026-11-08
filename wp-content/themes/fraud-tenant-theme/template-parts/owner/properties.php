<?php
/**
 * Owner — My Properties (list, detail, edit, ownership documents, archive / restore).
 *
 * @package FraudTenantTheme
 */

defined( 'ABSPATH' ) || exit;

use FraudTenant\Services\PropertyService;
use FraudTenant\Utils\Constants;

$d        = $args['data'];
$base     = $args['base'];
$owner_id = $args['owner_id'];
$pid      = absint( $_GET['property_id'] ?? get_query_var( 'ft_property_id' ) ); // phpcs:ignore WordPress.Security.NonceVerification
$types    = PropertyService::ALLOWED_PROPERTY_TYPES;

if ( $pid ) :
	$p = PropertyService::get_property( $pid, $owner_id );
	if ( ! $p ) {
		ft_empty( __( 'Property not found.', 'fraud-tenant-theme' ), ft_app_url( $base, 'properties' ), __( 'Back to properties', 'fraud-tenant-theme' ) );
		return;
	}
	$docs     = PropertyService::get_property_documents( $pid, $owner_id );
	$archived = ! empty( $p->archived_at ) || Constants::PROP_STATUS_ARCHIVED === $p->status;
	$here     = ft_app_url( $base, 'properties', array( 'property_id' => $pid ) );
	?>
	<p><a href="<?php echo esc_url( ft_app_url( $base, 'properties' ) ); ?>">← <?php esc_html_e( 'All properties', 'fraud-tenant-theme' ); ?></a></p>
	<header class="ft-page-head"><div><h1><?php echo esc_html( $p->title ?: $p->address_street ); ?></h1><p><?php echo esc_html( trim( $p->address_street . ( $p->unit_number ? ' #' . $p->unit_number : '' ) . ', ' . $p->city . ', ' . $p->state . ' ' . $p->postal_code ) ); ?></p></div><?php echo ft_badge( $archived ? 'archived' : (string) $p->status ); // phpcs:ignore ?></header>
	<div class="ft-grid ft-grid-2">
		<section class="ft-card">
			<div class="ft-card-head"><h2><?php esc_html_e( 'Ownership verification', 'fraud-tenant-theme' ); ?></h2></div>
			<?php if ( Constants::PROP_STATUS_VERIFIED === $p->status ) : ?>
				<div class="ft-alert is-success"><?php esc_html_e( 'Verified. You can screen applicants and report incidents for this property.', 'fraud-tenant-theme' ); ?></div>
			<?php elseif ( in_array( $p->status, array( Constants::PROP_STATUS_REJECTED, Constants::PROP_STATUS_INFO_REQUESTED ), true ) && $p->rejection_reason ) : ?>
				<div class="ft-alert is-warning"><strong><?php esc_html_e( 'Message from the review team:', 'fraud-tenant-theme' ); ?></strong> <?php echo esc_html( (string) $p->rejection_reason ); ?></div>
			<?php elseif ( ! $archived ) : ?>
				<div class="ft-alert is-info"><?php esc_html_e( 'Pending review. Upload a deed, tax record or management agreement that shows your ownership.', 'fraud-tenant-theme' ); ?></div>
			<?php endif; ?>
			<h3><?php esc_html_e( 'Documents', 'fraud-tenant-theme' ); ?></h3>
			<?php if ( ! $docs ) : ft_empty( __( 'No ownership document uploaded yet.', 'fraud-tenant-theme' ) ); else : ?>
				<ul class="ft-list"><?php foreach ( $docs as $doc ) : ?>
					<li><?php echo esc_html( $doc->original_name ); ?> <?php echo ft_badge( (string) $doc->review_status ); // phpcs:ignore ?> <?php echo \FraudTenant\Admin\AdminConsole::doc_link( (int) $doc->id, __( 'View', 'fraud-tenant-theme' ) ); // phpcs:ignore ?></li>
				<?php endforeach; ?></ul>
			<?php endif; ?>
			<?php if ( ! $archived ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="ft-form">
					<?php ft_form_fields( 'ft_upload_property_doc', $here ); ?>
					<input type="hidden" name="property_id" value="<?php echo (int) $pid; ?>">
					<?php wp_nonce_field( 'ft_upload_doc_' . $pid, 'ft_doc_upload_nonce' ); ?>
					<label class="ft-label" for="ft-od"><?php esc_html_e( 'Upload ownership document (PDF, JPG or PNG, max 10 MB)', 'fraud-tenant-theme' ); ?></label>
					<input class="ft-input" type="file" id="ft-od" name="ownership_document" accept=".pdf,.png,.jpg,.jpeg" required>
					<button class="ft-btn ft-btn-secondary" type="submit"><?php esc_html_e( 'Upload document', 'fraud-tenant-theme' ); ?></button>
				</form>
			<?php endif; ?>
		</section>
		<section class="ft-card">
			<div class="ft-card-head"><h2><?php esc_html_e( 'Property details', 'fraud-tenant-theme' ); ?></h2></div>
			<?php if ( $archived ) : ?>
				<p class="ft-muted"><?php esc_html_e( 'This property is archived. Restore it to make changes.', 'fraud-tenant-theme' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ft-form">
					<?php ft_form_fields( 'ft_unarchive_property', $here ); ?>
					<input type="hidden" name="property_id" value="<?php echo (int) $pid; ?>">
					<?php wp_nonce_field( 'ft_unarchive_prop_' . $pid, 'ft_unarchive_nonce' ); ?>
					<button class="ft-btn ft-btn-primary" type="submit" <?php disabled( ! $d['can_add_property'] ); ?>><?php esc_html_e( 'Restore property', 'fraud-tenant-theme' ); ?></button>
					<?php if ( ! $d['can_add_property'] ) : ?><p class="ft-help"><?php esc_html_e( 'Your plan\'s active property limit is reached.', 'fraud-tenant-theme' ); ?></p><?php endif; ?>
				</form>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ft-form">
					<?php ft_form_fields( 'ft_edit_property', $here ); ?>
					<input type="hidden" name="property_id" value="<?php echo (int) $pid; ?>">
					<?php wp_nonce_field( 'ft_edit_property_' . $pid, 'ft_property_nonce' ); ?>
					<label class="ft-label" for="ft-pt"><?php esc_html_e( 'Property name', 'fraud-tenant-theme' ); ?></label>
					<input class="ft-input" id="ft-pt" name="title" value="<?php echo esc_attr( (string) $p->title ); ?>">
					<div class="ft-row">
						<div><label class="ft-label" for="ft-ps"><?php esc_html_e( 'Street address', 'fraud-tenant-theme' ); ?></label><input class="ft-input" id="ft-ps" name="address_street" value="<?php echo esc_attr( (string) $p->address_street ); ?>" required></div>
						<div><label class="ft-label" for="ft-pu"><?php esc_html_e( 'Unit', 'fraud-tenant-theme' ); ?></label><input class="ft-input" id="ft-pu" name="unit_number" value="<?php echo esc_attr( (string) $p->unit_number ); ?>"></div>
					</div>
					<div class="ft-row">
						<div><label class="ft-label" for="ft-pc"><?php esc_html_e( 'City', 'fraud-tenant-theme' ); ?></label><input class="ft-input" id="ft-pc" name="city" value="<?php echo esc_attr( (string) $p->city ); ?>" required></div>
						<div><label class="ft-label" for="ft-pst"><?php esc_html_e( 'State', 'fraud-tenant-theme' ); ?></label><input class="ft-input" id="ft-pst" name="state" maxlength="2" value="<?php echo esc_attr( (string) $p->state ); ?>" required></div>
						<div><label class="ft-label" for="ft-pz"><?php esc_html_e( 'ZIP', 'fraud-tenant-theme' ); ?></label><input class="ft-input" id="ft-pz" name="postal_code" value="<?php echo esc_attr( (string) $p->postal_code ); ?>" required></div>
					</div>
					<div class="ft-row">
						<div><label class="ft-label" for="ft-pty"><?php esc_html_e( 'Type', 'fraud-tenant-theme' ); ?></label><select class="ft-select" id="ft-pty" name="property_type"><?php foreach ( $types as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $p->property_type, $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></div>
						<div><label class="ft-label" for="ft-pn"><?php esc_html_e( 'Units', 'fraud-tenant-theme' ); ?></label><input class="ft-input" type="number" min="1" id="ft-pn" name="units_count" value="<?php echo (int) $p->units_count; ?>"></div>
					</div>
					<?php if ( Constants::PROP_STATUS_VERIFIED === $p->status ) : ?><p class="ft-help"><?php esc_html_e( 'Changing the address of a verified property sends it back for verification.', 'fraud-tenant-theme' ); ?></p><?php endif; ?>
					<button class="ft-btn ft-btn-primary" type="submit"><?php esc_html_e( 'Save changes', 'fraud-tenant-theme' ); ?></button>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ft-form" data-ft-confirm="<?php esc_attr_e( 'Archive this property? Its history is kept and you can restore it later.', 'fraud-tenant-theme' ); ?>">
					<?php ft_form_fields( 'ft_archive_property', ft_app_url( $base, 'properties' ) ); ?>
					<input type="hidden" name="property_id" value="<?php echo (int) $pid; ?>">
					<?php wp_nonce_field( 'ft_archive_prop_' . $pid, 'ft_archive_nonce' ); ?>
					<button class="ft-btn ft-btn-danger ft-btn-sm" type="submit"><?php esc_html_e( 'Archive property', 'fraud-tenant-theme' ); ?></button>
				</form>
			<?php endif; ?>
		</section>
	</div>
	<?php
	return;
endif;

$view  = sanitize_key( (string) ( $_GET['view'] ?? 'active' ) ); // phpcs:ignore WordPress.Security.NonceVerification
$list  = 'archived' === $view ? $d['archived_properties'] : $d['properties'];
$limit = $d['property_limit'];
?>
<header class="ft-page-head">
	<div><h1><?php esc_html_e( 'My Properties', 'fraud-tenant-theme' ); ?></h1>
	<?php /* translators: 1: active, 2: limit */ ?>
	<p><?php echo -1 === $limit ? esc_html( sprintf( __( '%d active properties · unlimited on your plan', 'fraud-tenant-theme' ), count( $d['properties'] ) ) ) : esc_html( sprintf( __( '%1$d of %2$d active properties on your plan', 'fraud-tenant-theme' ), count( $d['properties'] ), $limit ) ); ?></p></div>
	<?php if ( $d['can_add_property'] ) : ?>
		<a class="ft-btn ft-btn-primary" href="<?php echo esc_url( ft_app_url( $base, 'add-property' ) ); ?>"><?php echo ft_icon( 'plus' ); // phpcs:ignore ?> <?php esc_html_e( 'Add property', 'fraud-tenant-theme' ); ?></a>
	<?php else : ?>
		<a class="ft-btn ft-btn-secondary" href="<?php echo esc_url( ft_app_url( $base, 'membership' ) ); ?>"><?php esc_html_e( 'Upgrade for unlimited properties', 'fraud-tenant-theme' ); ?></a>
	<?php endif; ?>
</header>
<div class="ft-tabs">
	<a class="ft-tab <?php echo 'archived' !== $view ? 'is-active' : ''; ?>" href="<?php echo esc_url( ft_app_url( $base, 'properties' ) ); ?>"><?php echo esc_html( sprintf( /* translators: %d: count */ __( 'Active (%d)', 'fraud-tenant-theme' ), count( $d['properties'] ) ) ); ?></a>
	<a class="ft-tab <?php echo 'archived' === $view ? 'is-active' : ''; ?>" href="<?php echo esc_url( ft_app_url( $base, 'properties', array( 'view' => 'archived' ) ) ); ?>"><?php echo esc_html( sprintf( /* translators: %d: count */ __( 'Archived (%d)', 'fraud-tenant-theme' ), count( $d['archived_properties'] ) ) ); ?></a>
</div>
<?php if ( ! $list ) : ?>
	<?php ft_empty( 'archived' === $view ? __( 'No archived properties.', 'fraud-tenant-theme' ) : __( 'Add your first rental property to start screening applicants.', 'fraud-tenant-theme' ), 'archived' === $view ? '' : ft_app_url( $base, 'add-property' ), __( 'Add property', 'fraud-tenant-theme' ) ); ?>
<?php else : ?>
	<div class="ft-card"><div class="ft-table-wrap"><table class="ft-table ft-stack">
		<thead><tr><th><?php esc_html_e( 'Property', 'fraud-tenant-theme' ); ?></th><th><?php esc_html_e( 'Type', 'fraud-tenant-theme' ); ?></th><th><?php esc_html_e( 'Verification', 'fraud-tenant-theme' ); ?></th><th><?php esc_html_e( 'Added', 'fraud-tenant-theme' ); ?></th></tr></thead>
		<tbody>
		<?php foreach ( $list as $p ) : ?>
			<tr>
				<td data-label="<?php esc_attr_e( 'Property', 'fraud-tenant-theme' ); ?>"><a href="<?php echo esc_url( ft_app_url( $base, 'properties', array( 'property_id' => (int) $p->id ) ) ); ?>"><?php echo esc_html( $p->title ?: $p->address_street ); ?></a><br><span class="ft-muted"><?php echo esc_html( $p->city . ', ' . $p->state ); ?></span></td>
				<td data-label="<?php esc_attr_e( 'Type', 'fraud-tenant-theme' ); ?>"><?php echo esc_html( $types[ $p->property_type ] ?? $p->property_type ); ?></td>
				<td data-label="<?php esc_attr_e( 'Verification', 'fraud-tenant-theme' ); ?>"><?php echo ft_badge( 'archived' === $view ? 'archived' : (string) $p->status ); // phpcs:ignore ?></td>
				<td data-label="<?php esc_attr_e( 'Added', 'fraud-tenant-theme' ); ?>"><?php echo ft_date( $p->created_at ); // phpcs:ignore ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table></div></div>
<?php endif; ?>
