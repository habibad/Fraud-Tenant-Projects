<?php
/**
 * Property Management — ownership verification queue and all properties.
 * Verification gates property-dependent actions (screening, incident reports), never dashboard access.
 *
 * @package FraudTenant\Admin
 */

defined( 'ABSPATH' ) || exit;

use FraudTenant\Admin\AdminConsole as C;
use FraudTenant\Admin\AdminReviewService;
use FraudTenant\Utils\Constants;

$pid = absint( C::get( 'property_id' ) );

if ( $pid ) {
	$d = AdminReviewService::get_property_review_detail( $pid );
	if ( ! $d ) {
		C::empty_state( __( 'Property not found.', 'fraud-tenant-core' ) );
		return;
	}
	$prop = $d['property'];
	C::head( (string) ( $prop->title ?: $prop->address_street ), __( 'Property detail and ownership verification', 'fraud-tenant-core' ) );
	echo '<p><a href="' . esc_url( C::url( 'properties' ) ) . '">← ' . esc_html__( 'All properties', 'fraud-tenant-core' ) . '</a></p>';
	?>
	<div class="ft-grid ft-grid-2">
		<section class="ft-card">
			<div class="ft-card-head"><h2><?php esc_html_e( 'Property', 'fraud-tenant-core' ); ?></h2><?php echo C::badge( (string) $prop->status ); // phpcs:ignore ?></div>
			<dl class="ft-dl">
				<dt><?php esc_html_e( 'Address', 'fraud-tenant-core' ); ?></dt><dd><?php echo esc_html( trim( $prop->address_street . ( $prop->unit_number ? ' #' . $prop->unit_number : '' ) . ', ' . $prop->city . ', ' . $prop->state . ' ' . $prop->postal_code ) ); ?></dd>
				<dt><?php esc_html_e( 'Type / units', 'fraud-tenant-core' ); ?></dt><dd><?php echo esc_html( ucwords( str_replace( '_', ' ', (string) $prop->property_type ) ) . ' · ' . (int) $prop->units_count ); ?></dd>
				<dt><?php esc_html_e( 'Owner', 'fraud-tenant-core' ); ?></dt><dd><a href="<?php echo esc_url( C::url( 'users', array( 'user_id' => (int) $prop->owner_id ) ) ); ?>"><?php echo esc_html( $d['owner']->display_name ?? '#' . (int) $prop->owner_id ); ?></a></dd>
				<dt><?php esc_html_e( 'Submitted', 'fraud-tenant-core' ); ?></dt><dd><?php echo C::date( $prop->created_at ); // phpcs:ignore ?></dd>
				<?php if ( ! empty( $prop->rejection_reason ) ) : ?><dt><?php esc_html_e( 'Last note to owner', 'fraud-tenant-core' ); ?></dt><dd><?php echo esc_html( $prop->rejection_reason ); ?></dd><?php endif; ?>
			</dl>
			<h3><?php esc_html_e( 'Ownership documents', 'fraud-tenant-core' ); ?></h3>
			<?php if ( empty( $d['documents'] ) ) : C::empty_state( __( 'No ownership document uploaded yet.', 'fraud-tenant-core' ) ); else : ?>
				<ul class="ft-list">
					<?php foreach ( $d['documents'] as $doc ) : ?>
						<li><?php echo esc_html( $doc->original_name ); ?> <?php echo C::badge( (string) $doc->review_status ); // phpcs:ignore ?> <?php echo C::doc_link( (int) $doc->id, __( 'View', 'fraud-tenant-core' ) ); // phpcs:ignore ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
		<section class="ft-card">
			<div class="ft-card-head"><h2><?php esc_html_e( 'Verification decision', 'fraud-tenant-core' ); ?></h2></div>
			<?php if ( Constants::PROP_STATUS_ARCHIVED === $prop->status || ! empty( $prop->archived_at ) ) : ?>
				<p class="ft-muted"><?php esc_html_e( 'This property is archived by its owner.', 'fraud-tenant-core' ); ?></p>
			<?php else : ?>
				<?php if ( Constants::PROP_STATUS_VERIFIED !== $prop->status ) : ?>
					<?php C::form( 'property_verify', array( 'property_id' => $pid ) ); ?>
						<label class="ft-label" for="ft-pv-notes"><?php esc_html_e( 'Internal verification notes (optional)', 'fraud-tenant-core' ); ?></label>
						<textarea class="ft-textarea" id="ft-pv-notes" name="notes"></textarea>
						<button class="ft-btn ft-btn-primary" type="submit"><?php esc_html_e( 'Verify ownership', 'fraud-tenant-core' ); ?></button>
					</form>
					<?php C::form( 'property_info', array( 'property_id' => $pid ) ); ?>
						<label class="ft-label" for="ft-pi-note"><?php esc_html_e( 'Request more information (shown to the owner)', 'fraud-tenant-core' ); ?></label>
						<textarea class="ft-textarea" id="ft-pi-note" name="note" required minlength="10"></textarea>
						<button class="ft-btn ft-btn-secondary" type="submit"><?php esc_html_e( 'Request information', 'fraud-tenant-core' ); ?></button>
					</form>
				<?php endif; ?>
				<?php C::form( 'property_reject', array( 'property_id' => $pid ) ); ?>
					<label class="ft-label" for="ft-pr-reason"><?php esc_html_e( 'Rejection reason (shown to the owner)', 'fraud-tenant-core' ); ?></label>
					<textarea class="ft-textarea" id="ft-pr-reason" name="reason" required minlength="10"></textarea>
					<button class="ft-btn ft-btn-danger" type="submit"><?php echo Constants::PROP_STATUS_VERIFIED === $prop->status ? esc_html__( 'Revoke verification', 'fraud-tenant-core' ) : esc_html__( 'Reject', 'fraud-tenant-core' ); ?></button>
				</form>
			<?php endif; ?>
		</section>
	</div>
	<?php
	return;
}

$status = C::get( 'status', '' );
$search = C::get( 'q' );
$page   = max( 1, absint( C::get( 'pg', '1' ) ) );
$queue  = AdminReviewService::get_properties_queue( array( 'status' => $status, 'search' => $search, 'paged' => $page, 'per_page' => 25 ) );
$counts = AdminReviewService::count_properties_by_status();

C::head( __( 'Property Management', 'fraud-tenant-core' ), __( 'Verify property ownership. Verification is required for screening and incident reporting on that property.', 'fraud-tenant-core' ) );
C::pills(
	'properties',
	'status',
	array(
		''                     => __( 'All active', 'fraud-tenant-core' ),
		/* translators: %d: count */
		'pending_verification' => sprintf( __( 'Pending (%d)', 'fraud-tenant-core' ), (int) ( $counts['pending_verification'] ?? 0 ) ),
		/* translators: %d: count */
		'info_requested'       => sprintf( __( 'Info requested (%d)', 'fraud-tenant-core' ), (int) ( $counts['info_requested'] ?? 0 ) ),
		/* translators: %d: count */
		'verified'             => sprintf( __( 'Verified (%d)', 'fraud-tenant-core' ), (int) ( $counts['verified'] ?? 0 ) ),
		/* translators: %d: count */
		'rejected'             => sprintf( __( 'Rejected (%d)', 'fraud-tenant-core' ), (int) ( $counts['rejected'] ?? 0 ) ),
		'archived'             => __( 'Archived', 'fraud-tenant-core' ),
	),
	$status,
	array( 'q' => $search )
);
?>
<form method="get" class="ft-filters" action="<?php echo esc_url( C::in_app() ? home_url( '/admin-dashboard/' ) : admin_url( 'admin.php' ) ); ?>">
	<input type="hidden" name="<?php echo C::in_app() ? 'section' : 'page'; ?>" value="<?php echo esc_attr( C::in_app() ? 'properties' : 'fraud-tenant-properties' ); ?>">
	<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">
	<input class="ft-input" type="search" name="q" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Address, city, ZIP or owner', 'fraud-tenant-core' ); ?>" aria-label="<?php esc_attr_e( 'Search properties', 'fraud-tenant-core' ); ?>">
	<button class="ft-btn ft-btn-secondary" type="submit"><?php esc_html_e( 'Search', 'fraud-tenant-core' ); ?></button>
</form>
<div class="ft-card"><div class="ft-table-wrap"><table class="ft-table">
	<thead><tr><th><?php esc_html_e( 'Property', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Owner', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Status', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Documents', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Submitted', 'fraud-tenant-core' ); ?></th></tr></thead>
	<tbody>
	<?php foreach ( $queue['items'] as $r ) : ?>
		<tr>
			<td><a href="<?php echo esc_url( C::url( 'properties', array( 'property_id' => (int) $r->id ) ) ); ?>"><?php echo esc_html( $r->title ?: $r->address_street ); ?></a><br><span class="ft-muted"><?php echo esc_html( $r->city . ', ' . $r->state . ' ' . $r->postal_code ); ?></span></td>
			<td><?php echo esc_html( $r->owner_name ); ?></td>
			<td><?php echo C::badge( (string) $r->status ); // phpcs:ignore ?></td>
			<td><?php echo (int) $r->document_count; ?></td>
			<td><?php echo C::date( $r->created_at ); // phpcs:ignore ?></td>
		</tr>
	<?php endforeach; ?>
	<?php if ( ! $queue['items'] ) : ?><tr><td colspan="5"><?php C::empty_state( __( 'No properties in this list.', 'fraud-tenant-core' ) ); ?></td></tr><?php endif; ?>
	</tbody>
</table></div></div>
<?php C::pagination( 'properties', $page, (int) $queue['total_items'], 25, array( 'status' => $status, 'q' => $search ) ); ?>
