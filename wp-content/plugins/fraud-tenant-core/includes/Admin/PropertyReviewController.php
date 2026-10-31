<?php
/**
 * Dedicated Property Review Admin Controller.
 *
 * Renders the property verification queue and property review detail interface.
 * Completely separate from user account approval.
 *
 * @package FraudTenant\Admin
 */

namespace FraudTenant\Admin;

use FraudTenant\Auth\Capabilities;
use FraudTenant\Services\DocumentService;
use FraudTenant\Utils\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PropertyReviewController {

	/**
	 * Main entry point for the Property Verification admin page.
	 */
	public static function render(): void {
		if ( ! current_user_can( Capabilities::REVIEW_PROPERTIES ) && ! current_user_can( Capabilities::VERIFY_PROPERTIES ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access to Fraud Tenant property verification portal.', 'fraud-tenant-core' ), 403 );
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';

		if ( 'review' === $action ) {
			self::render_detail();
		} else {
			self::render_list();
		}
	}

	/**
	 * Render the Property Verification Queue List.
	 */
	protected static function render_list(): void {
		$current_status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
		$search_query   = isset( $_GET['s'] ) ? sanitize_text_field( trim( $_GET['s'] ) ) : '';
		$paged          = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

		$counts = AdminReviewService::count_properties_by_status();
		$data   = AdminReviewService::get_properties_queue(
			array(
				'status'   => $current_status,
				'search'   => $search_query,
				'paged'    => $paged,
				'per_page' => 20,
			)
		);

		$base_url = admin_url( 'admin.php?page=fraud-tenant-properties' );
		?>
		<div class="wrap ft-admin-wrap">
			<h1 class="wp-heading-inline">
				<span class="dashicons dashicons-admin-home" style="font-size:28px;width:28px;height:28px;vertical-align:middle;color:#0E9488;margin-right:6px;"></span>
				<?php esc_html_e( 'Fraud Tenant — Property Verification Queue', 'fraud-tenant-core' ); ?>
			</h1>
			<p class="description">
				<?php esc_html_e( 'Inspect, review, and verify rental property ownership documents independently from user account approvals.', 'fraud-tenant-core' ); ?>
			</p>
			<hr class="wp-header-end">

			<?php self::render_flash_notices(); ?>

			<!-- Status Filter Tabs -->
			<ul class="subsubsub" style="margin: 14px 0 18px;">
				<li class="all">
					<a href="<?php echo esc_url( remove_query_arg( array( 'status', 'paged' ), $base_url ) ); ?>" class="<?php echo empty( $current_status ) ? 'current' : ''; ?>">
						<?php esc_html_e( 'All Active', 'fraud-tenant-core' ); ?> <span class="count">(<?php echo (int) $counts['all']; ?>)</span>
					</a> |
				</li>
				<li class="pending">
					<a href="<?php echo esc_url( add_query_arg( array( 'status' => Constants::PROP_STATUS_PENDING ), $base_url ) ); ?>" class="<?php echo Constants::PROP_STATUS_PENDING === $current_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Pending Verification', 'fraud-tenant-core' ); ?> <span class="count">(<?php echo (int) $counts['pending_verification']; ?>)</span>
					</a> |
				</li>
				<li class="info_req">
					<a href="<?php echo esc_url( add_query_arg( array( 'status' => Constants::PROP_STATUS_INFO_REQUESTED ), $base_url ) ); ?>" class="<?php echo Constants::PROP_STATUS_INFO_REQUESTED === $current_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Info Requested', 'fraud-tenant-core' ); ?> <span class="count">(<?php echo (int) $counts['info_requested']; ?>)</span>
					</a> |
				</li>
				<li class="verified">
					<a href="<?php echo esc_url( add_query_arg( array( 'status' => Constants::PROP_STATUS_VERIFIED ), $base_url ) ); ?>" class="<?php echo Constants::PROP_STATUS_VERIFIED === $current_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Verified', 'fraud-tenant-core' ); ?> <span class="count">(<?php echo (int) $counts['verified']; ?>)</span>
					</a> |
				</li>
				<li class="rejected">
					<a href="<?php echo esc_url( add_query_arg( array( 'status' => Constants::PROP_STATUS_REJECTED ), $base_url ) ); ?>" class="<?php echo Constants::PROP_STATUS_REJECTED === $current_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Rejected', 'fraud-tenant-core' ); ?> <span class="count">(<?php echo (int) $counts['rejected']; ?>)</span>
					</a> |
				</li>
				<li class="archived">
					<a href="<?php echo esc_url( add_query_arg( array( 'status' => Constants::PROP_STATUS_ARCHIVED ), $base_url ) ); ?>" class="<?php echo Constants::PROP_STATUS_ARCHIVED === $current_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Archived', 'fraud-tenant-core' ); ?> <span class="count">(<?php echo (int) $counts['archived']; ?>)</span>
					</a>
				</li>
			</ul>

			<!-- Search Bar -->
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="margin-bottom: 16px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
				<input type="hidden" name="page" value="fraud-tenant-properties" />
				<?php if ( ! empty( $current_status ) ) : ?>
					<input type="hidden" name="status" value="<?php echo esc_attr( $current_status ); ?>" />
				<?php endif; ?>

				<input type="search" id="property-search-input" name="s" value="<?php echo esc_attr( $search_query ); ?>" placeholder="<?php esc_attr_e( 'Search by address, city, ZIP, owner...', 'fraud-tenant-core' ); ?>" style="min-width: 280px;" />
				<input type="submit" class="button button-secondary" value="<?php esc_attr_e( 'Filter Properties', 'fraud-tenant-core' ); ?>" />

				<?php if ( ! empty( $search_query ) || ! empty( $current_status ) ) : ?>
					<a href="<?php echo esc_url( $base_url ); ?>" class="button button-link"><?php esc_html_e( 'Reset Filters', 'fraud-tenant-core' ); ?></a>
				<?php endif; ?>
			</form>

			<!-- Properties Table -->
			<table class="wp-list-table widefat fixed striped table-view-list">
				<thead>
					<tr>
						<th scope="col" style="width: 24%;"><?php esc_html_e( 'Property Address', 'fraud-tenant-core' ); ?></th>
						<th scope="col" style="width: 18%;"><?php esc_html_e( 'Property Owner', 'fraud-tenant-core' ); ?></th>
						<th scope="col" style="width: 14%;"><?php esc_html_e( 'Type / Units', 'fraud-tenant-core' ); ?></th>
						<th scope="col" style="width: 12%;"><?php esc_html_e( 'Documents', 'fraud-tenant-core' ); ?></th>
						<th scope="col" style="width: 14%;"><?php esc_html_e( 'Submitted Date', 'fraud-tenant-core' ); ?></th>
						<th scope="col" style="width: 12%;"><?php esc_html_e( 'Status', 'fraud-tenant-core' ); ?></th>
						<th scope="col" style="width: 14%; text-align: right;"><?php esc_html_e( 'Actions', 'fraud-tenant-core' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $data['items'] ) ) : ?>
						<tr>
							<td colspan="7" style="padding: 30px; text-align: center; color: #64748B;">
								<strong><?php esc_html_e( 'No properties found matching your criteria.', 'fraud-tenant-core' ); ?></strong>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $data['items'] as $item ) : ?>
							<?php
							$review_url = add_query_arg(
								array(
									'page'        => 'fraud-tenant-properties',
									'action'      => 'review',
									'property_id' => $item->id,
								),
								admin_url( 'admin.php' )
							);
							$user_url   = add_query_arg(
								array(
									'page'    => 'fraud-tenant-admin',
									'action'  => 'review',
									'user_id' => $item->owner_id,
								),
								admin_url( 'admin.php' )
							);
							?>
							<tr>
								<td>
									<strong>
										<a href="<?php echo esc_url( $review_url ); ?>" style="color:#14263E;">
											<?php echo esc_html( $item->title ?: $item->address_street ); ?>
										</a>
									</strong>
									<div style="font-size:12px; color:#64748B; margin-top:2px;">
										<?php echo esc_html( $item->address_street . ( $item->unit_number ? ' #' . $item->unit_number : '' ) . ', ' . $item->city . ', ' . $item->state . ' ' . $item->postal_code ); ?>
									</div>
								</td>
								<td>
									<strong><a href="<?php echo esc_url( $user_url ); ?>"><?php echo esc_html( $item->owner_name ); ?></a></strong>
									<div style="font-size:12px; color:#64748B;"><?php echo esc_html( $item->owner_email ); ?></div>
								</td>
								<td>
									<?php echo esc_html( ucwords( str_replace( '_', ' ', $item->property_type ) ) ); ?><br>
									<span style="font-size:12px; color:#64748B;"><?php printf( /* translators: %d: count */ esc_html__( '%d unit(s)', 'fraud-tenant-core' ), (int) $item->units_count ); ?></span>
								</td>
								<td>
									<strong><?php echo (int) $item->document_count; ?></strong> <?php esc_html_e( 'doc(s)', 'fraud-tenant-core' ); ?>
									<?php if ( $item->document_count > 0 && ! empty( $item->latest_doc_status ) ) : ?>
										<div style="font-size:11px; margin-top:2px;">
											<?php if ( 'approved' === $item->latest_doc_status ) : ?>
												<span style="color:#0E9488; font-weight:600;">&check; <?php esc_html_e( 'Approved', 'fraud-tenant-core' ); ?></span>
											<?php elseif ( 'rejected' === $item->latest_doc_status ) : ?>
												<span style="color:#E11D48; font-weight:600;">&times; <?php esc_html_e( 'Rejected', 'fraud-tenant-core' ); ?></span>
											<?php else : ?>
												<span style="color:#D97706; font-weight:600;">&bull; <?php esc_html_e( 'Pending Doc', 'fraud-tenant-core' ); ?></span>
											<?php endif; ?>
										</div>
									<?php endif; ?>
								</td>
								<td>
									<?php echo esc_html( gmdate( 'M j, Y', strtotime( $item->created_at ) ) ); ?><br>
									<span style="font-size:11px; color:#94A3B8;"><?php echo esc_html( gmdate( 'g:i a', strtotime( $item->created_at ) ) ); ?></span>
								</td>
								<td>
									<?php self::render_status_badge( $item->status, ! empty( $item->archived_at ) ); ?>
								</td>
								<td style="text-align: right;">
									<a href="<?php echo esc_url( $review_url ); ?>" class="button button-small button-primary" style="background:#0E9488; border-color:#0E9488;">
										<?php esc_html_e( 'Inspect & Verify', 'fraud-tenant-core' ); ?> &rarr;
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<!-- Pagination -->
			<?php if ( $data['total_pages'] > 1 ) : ?>
				<div class="tablenav bottom" style="margin-top: 14px;">
					<div class="tablenav-pages">
						<span class="displaying-num"><?php printf( /* translators: %s: number of items */ esc_html__( '%s properties', 'fraud-tenant-core' ), number_format_i18n( $data['total_items'] ) ); ?></span>
						<?php
						echo paginate_links(
							array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
								'total'     => $data['total_pages'],
								'current'   => $data['paged'],
							)
						);
						?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render Detailed Property Review and Decision Interface.
	 */
	protected static function render_detail(): void {
		$property_id = isset( $_GET['property_id'] ) ? (int) $_GET['property_id'] : ( isset( $_GET['id'] ) ? (int) $_GET['id'] : 0 );
		$detail      = AdminReviewService::get_property_review_detail( $property_id );

		if ( ! $detail ) {
			wp_die( esc_html__( 'Property record not found.', 'fraud-tenant-core' ), 404 );
		}

		$prop     = $detail['property'];
		$owner    = $detail['owner'];
		$profile  = $detail['profile'];
		$docs     = $detail['documents'];
		$logs     = $detail['audit_logs'];
		$back_url = admin_url( 'admin.php?page=fraud-tenant-properties' );
		?>
		<div class="wrap ft-admin-wrap" style="max-width: 1100px;">
			<p>
				<a href="<?php echo esc_url( $back_url ); ?>" class="button button-secondary">
					&larr; <?php esc_html_e( 'Back to Property Queue', 'fraud-tenant-core' ); ?>
				</a>
			</p>

			<h1 class="wp-heading-inline">
				<?php printf( /* translators: %s: property title */ esc_html__( 'Review Property: %s', 'fraud-tenant-core' ), esc_html( $prop->title ?: $prop->address_street ) ); ?>
			</h1>
			<hr class="wp-header-end">

			<?php self::render_flash_notices(); ?>

			<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-top: 20px;">

				<!-- Left Column: Details & Evidence Documents -->
				<div>

					<!-- Property Information Box -->
					<div class="postbox" style="padding: 18px 22px; background: #FFFFFF; border: 1px solid #CBD5E1; border-radius: 8px; margin-bottom: 24px;">
						<div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 1px solid #E2E8F0; padding-bottom: 12px; margin-bottom: 16px;">
							<h2 style="font-size: 16px; margin: 0; color: #14263E;">
								<?php esc_html_e( 'Property Information', 'fraud-tenant-core' ); ?>
							</h2>
							<?php self::render_status_badge( $prop->status, ! empty( $prop->archived_at ) ); ?>
						</div>

						<table class="form-table" role="presentation" style="margin: 0;">
							<tbody>
								<tr>
									<th style="width: 32%; color: #64748B; font-weight: 600;"><?php esc_html_e( 'Street Address', 'fraud-tenant-core' ); ?></th>
									<td><strong><?php echo esc_html( $prop->address_street . ( $prop->unit_number ? ' #' . $prop->unit_number : '' ) ); ?></strong></td>
								</tr>
								<tr>
									<th style="color: #64748B; font-weight: 600;"><?php esc_html_e( 'City, State, ZIP', 'fraud-tenant-core' ); ?></th>
									<td><?php echo esc_html( $prop->city . ', ' . $prop->state . ' ' . $prop->postal_code ); ?></td>
								</tr>
								<tr>
									<th style="color: #64748B; font-weight: 600;"><?php esc_html_e( 'Property Type', 'fraud-tenant-core' ); ?></th>
									<td><?php echo esc_html( ucwords( str_replace( '_', ' ', $prop->property_type ) ) ); ?></td>
								</tr>
								<tr>
									<th style="color: #64748B; font-weight: 600;"><?php esc_html_e( 'Total Units', 'fraud-tenant-core' ); ?></th>
									<td><?php echo (int) $prop->units_count; ?></td>
								</tr>
								<tr>
									<th style="color: #64748B; font-weight: 600;"><?php esc_html_e( 'Submitted On', 'fraud-tenant-core' ); ?></th>
									<td><?php echo esc_html( gmdate( 'F j, Y \a\t g:i a', strtotime( $prop->created_at ) ) ); ?></td>
								</tr>
								<?php if ( ! empty( $prop->archived_at ) ) : ?>
									<tr>
										<th style="color: #64748B; font-weight: 600;"><?php esc_html_e( 'Archived At', 'fraud-tenant-core' ); ?></th>
										<td><span style="color:#64748B;"><?php echo esc_html( gmdate( 'F j, Y \a\t g:i a', strtotime( $prop->archived_at ) ) ); ?></span></td>
									</tr>
								<?php endif; ?>
							</tbody>
						</table>
					</div>

					<!-- Submitted Ownership Documents -->
					<div class="postbox" style="padding: 18px 22px; background: #FFFFFF; border: 1px solid #CBD5E1; border-radius: 8px; margin-bottom: 24px;">
						<h2 style="font-size: 16px; margin: 0 0 14px 0; color: #14263E; border-bottom: 1px solid #E2E8F0; padding-bottom: 12px;">
							<?php esc_html_e( 'Ownership Proof & Deed Documents', 'fraud-tenant-core' ); ?>
							<span style="font-size: 13px; font-weight: normal; color: #64748B;">(<?php echo count( $docs ); ?>)</span>
						</h2>

						<?php if ( empty( $docs ) ) : ?>
							<div style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 20px; text-align: center; border-radius: 6px; color: #64748B;">
								<p style="margin: 0;"><?php esc_html_e( 'No ownership documents uploaded for this property yet.', 'fraud-tenant-core' ); ?></p>
							</div>
						<?php else : ?>
							<table class="wp-list-table widefat fixed striped">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Filename', 'fraud-tenant-core' ); ?></th>
										<th><?php esc_html_e( 'Size / MIME', 'fraud-tenant-core' ); ?></th>
										<th><?php esc_html_e( 'Uploaded', 'fraud-tenant-core' ); ?></th>
										<th><?php esc_html_e( 'Review Status', 'fraud-tenant-core' ); ?></th>
										<th style="text-align: right;"><?php esc_html_e( 'Action', 'fraud-tenant-core' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $docs as $d ) : ?>
										<?php
										$view_url = wp_nonce_url(
											add_query_arg(
												array(
													'action'      => 'ft_view_document',
													'document_id' => $d->id,
												),
												admin_url( 'admin-post.php' )
											),
											'ft_view_doc_' . $d->id
										);
										?>
										<tr>
											<td>
												<strong><?php echo esc_html( $d->original_name ); ?></strong>
											</td>
											<td>
												<?php echo esc_html( size_format( (int) $d->file_size ) ); ?><br>
												<span style="font-size: 11px; color: #64748B;"><?php echo esc_html( $d->mime_type ); ?></span>
											</td>
											<td>
												<?php echo esc_html( gmdate( 'M j, Y', strtotime( $d->uploaded_at ) ) ); ?>
											</td>
											<td>
												<?php if ( 'approved' === $d->review_status ) : ?>
													<span style="color:#0E9488; font-weight:700;">&check; <?php esc_html_e( 'Approved', 'fraud-tenant-core' ); ?></span>
												<?php elseif ( 'rejected' === $d->review_status ) : ?>
													<span style="color:#E11D48; font-weight:700;">&times; <?php esc_html_e( 'Rejected', 'fraud-tenant-core' ); ?></span>
												<?php else : ?>
													<span style="color:#D97706; font-weight:700;">&bull; <?php esc_html_e( 'Pending Review', 'fraud-tenant-core' ); ?></span>
												<?php endif; ?>
											</td>
											<td style="text-align: right;">
												<a href="<?php echo esc_url( $view_url ); ?>" class="button button-small" target="_blank" style="background: #F1F5F9; border-color: #CBD5E1; color: #0F172A;">
													<span class="dashicons dashicons-download" style="font-size: 15px; width: 15px; height: 15px; vertical-align: middle;"></span>
													<?php esc_html_e( 'Inspect Doc', 'fraud-tenant-core' ); ?>
												</a>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>
					</div>

					<!-- Previous Review Notes / Historical Continuity -->
					<?php if ( ! empty( $prop->admin_notes ) || ! empty( $prop->rejection_reason ) || ! empty( $prop->reviewed_by ) ) : ?>
						<div class="postbox" style="padding: 18px 22px; background: #FFFBEB; border: 1px solid #FCD34D; border-radius: 8px; margin-bottom: 24px;">
							<h3 style="font-size: 15px; margin: 0 0 10px 0; color: #92400E;">
								<?php esc_html_e( 'Previous Review Decision & Historical Notes', 'fraud-tenant-core' ); ?>
							</h3>
							<?php if ( ! empty( $detail['reviewer_name'] ) ) : ?>
								<p style="font-size: 12px; color: #78350F; margin: 0 0 8px 0;">
									<?php printf( /* translators: 1: reviewer name, 2: review date */ esc_html__( 'Reviewed by %1$s on %2$s', 'fraud-tenant-core' ), esc_html( $detail['reviewer_name'] ), esc_html( gmdate( 'M j, Y g:i a', strtotime( $prop->reviewed_at ) ) ) ); ?>
								</p>
							<?php endif; ?>

							<?php if ( ! empty( $prop->rejection_reason ) ) : ?>
								<div style="margin-top: 8px; padding: 10px; background: #FFFFFF; border-left: 3px solid #E11D48; border-radius: 4px;">
									<strong style="color: #E11D48;"><?php esc_html_e( 'Rejection Reason:', 'fraud-tenant-core' ); ?></strong>
									<p style="margin: 4px 0 0 0; font-size: 13px; color: #1E293B;"><?php echo nl2br( esc_html( $prop->rejection_reason ) ); ?></p>
								</div>
							<?php endif; ?>

							<?php if ( ! empty( $prop->admin_notes ) ) : ?>
								<div style="margin-top: 8px; padding: 10px; background: #FFFFFF; border-left: 3px solid #D97706; border-radius: 4px;">
									<strong style="color: #D97706;"><?php esc_html_e( 'Reviewer Notes / Requested Info:', 'fraud-tenant-core' ); ?></strong>
									<p style="margin: 4px 0 0 0; font-size: 13px; color: #1E293B;"><?php echo nl2br( esc_html( $prop->admin_notes ) ); ?></p>
								</div>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<!-- Property Audit Log History -->
					<div class="postbox" style="padding: 18px 22px; background: #FFFFFF; border: 1px solid #CBD5E1; border-radius: 8px;">
						<h3 style="font-size: 15px; margin: 0 0 12px 0; color: #14263E; border-bottom: 1px solid #E2E8F0; padding-bottom: 10px;">
							<?php esc_html_e( 'Append-Only Application-Level Audit History', 'fraud-tenant-core' ); ?>
						</h3>

						<?php if ( empty( $logs ) ) : ?>
							<p style="font-size: 13px; color: #64748B; margin: 0;"><?php esc_html_e( 'No recorded property audit history yet.', 'fraud-tenant-core' ); ?></p>
						<?php else : ?>
							<ul style="margin: 0; padding: 0; list-style: none;">
								<?php foreach ( $logs as $l ) : ?>
									<li style="padding: 8px 0; border-bottom: 1px solid #F1F5F9; font-size: 12px; display: flex; justify-content: space-between;">
										<div>
											<strong style="color: #0F172A;"><?php echo esc_html( $l->event_type ); ?></strong>
											<span style="color: #64748B;">— <?php echo esc_html( $l->actor_name ?: 'System' ); ?></span>
										</div>
										<span style="color: #94A3B8;"><?php echo esc_html( gmdate( 'M j, Y g:i a', strtotime( $l->created_at ) ) ); ?></span>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>

				</div>

				<!-- Right Column: Owner Profile & Administrative Decision Controls -->
				<div>

					<!-- Owner Info Box -->
					<div class="postbox" style="padding: 18px 20px; background: #FFFFFF; border: 1px solid #CBD5E1; border-radius: 8px; margin-bottom: 24px;">
						<h3 style="font-size: 15px; margin: 0 0 12px 0; color: #14263E; border-bottom: 1px solid #E2E8F0; padding-bottom: 10px;">
							<?php esc_html_e( 'Property Owner', 'fraud-tenant-core' ); ?>
						</h3>
						<?php if ( $owner ) : ?>
							<div style="margin-bottom: 8px;">
								<strong style="font-size: 14px; color: #0F172A;"><?php echo esc_html( $owner->display_name ); ?></strong>
							</div>
							<div style="font-size: 13px; color: #64748B; margin-bottom: 4px;">
								<?php echo esc_html( $owner->user_email ); ?>
							</div>
							<div style="font-size: 13px; color: #64748B; margin-bottom: 12px;">
								<?php echo esc_html( $profile?->phone ?? '—' ); ?>
							</div>
							<div style="padding: 8px 10px; background: #F8FAFC; border-radius: 6px; font-size: 12px; border: 1px solid #E2E8F0;">
								<strong><?php esc_html_e( 'Account Status:', 'fraud-tenant-core' ); ?></strong>
								<span style="font-weight: 700; color: <?php echo Constants::STATUS_APPROVED === $profile?->approval_status ? '#0E9488' : '#D97706'; ?>;">
									<?php echo esc_html( strtoupper( str_replace( '_', ' ', $profile?->approval_status ?? 'unknown' ) ) ); ?>
								</span>
							</div>
							<div style="margin-top: 10px;">
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=fraud-tenant-admin&action=review&user_id=' . $prop->owner_id ) ); ?>" class="button button-small" style="width: 100%; text-align: center;">
									<?php esc_html_e( 'View Owner Account Profile', 'fraud-tenant-core' ); ?> &rarr;
								</a>
							</div>
						<?php else : ?>
							<p style="color: #E11D48; margin: 0;"><?php esc_html_e( 'Owner account record missing.', 'fraud-tenant-core' ); ?></p>
						<?php endif; ?>
					</div>

					<!-- Administrative Decision Box -->
					<div class="postbox" style="padding: 20px; background: #FFFFFF; border: 2px solid #0E9488; border-radius: 8px;">
						<h3 style="font-size: 15px; margin: 0 0 14px 0; color: #14263E; border-bottom: 1px solid #E2E8F0; padding-bottom: 10px;">
							<?php esc_html_e( 'Verification Actions', 'fraud-tenant-core' ); ?>
						</h3>

						<!-- Action 1: Verify Ownership -->
						<?php if ( current_user_can( Capabilities::VERIFY_PROPERTIES ) || current_user_can( 'manage_options' ) ) : ?>
						<div style="margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid #E2E8F0;">
							<h4 style="margin: 0 0 6px 0; font-size: 13px; color: #0E9488; font-weight: 700;">
								&check; <?php esc_html_e( 'Verify Property Ownership', 'fraud-tenant-core' ); ?>
							</h4>
							<p style="font-size: 12px; color: #64748B; margin: 0 0 10px 0;">
								<?php esc_html_e( 'Approves property ownership based on verified title, deed, or tax documents.', 'fraud-tenant-core' ); ?>
							</p>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="ft_admin_property_review_action" />
								<input type="hidden" name="property_id" value="<?php echo (int) $prop->id; ?>" />
								<input type="hidden" name="review_decision" value="verify" />
								<?php wp_nonce_field( 'ft_admin_prop_review_' . $prop->id, 'ft_prop_review_nonce' ); ?>

								<div style="margin-bottom: 8px;">
									<input type="text" name="reviewer_notes" placeholder="<?php esc_attr_e( 'Optional verification notes...', 'fraud-tenant-core' ); ?>" class="widefat" style="font-size: 12px;" />
								</div>
								<button type="submit" class="button button-primary" style="background: #0E9488; border-color: #0E9488; width: 100%;">
									<?php esc_html_e( 'Verify Property Ownership', 'fraud-tenant-core' ); ?>
								</button>
							</form>
						</div>
						<?php else : ?>
						<div style="margin-bottom: 20px; padding: 12px; background: #F8FAFC; border-radius: 6px; border: 1px dashed #CBD5E1;">
							<p style="font-size: 12px; color: #64748B; margin: 0; line-height: 1.4;">
								<strong style="color: #14263E;"><?php esc_html_e( 'Review-Only Access', 'fraud-tenant-core' ); ?>:</strong>
								<?php esc_html_e( 'Your account has property review permissions (read, inspect deeds & request info). Executing the final property verification decision requires the Verify Properties capability.', 'fraud-tenant-core' ); ?>
							</p>
						</div>
						<?php endif; ?>

						<!-- Action 2: Request Additional Info -->
						<div style="margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid #E2E8F0;">
							<h4 style="margin: 0 0 6px 0; font-size: 13px; color: #D97706; font-weight: 700;">
								&quest; <?php esc_html_e( 'Request Additional Information', 'fraud-tenant-core' ); ?>
							</h4>
							<p style="font-size: 12px; color: #64748B; margin: 0 0 10px 0;">
								<?php esc_html_e( 'Ask the owner for clearer documents, recorded deeds, or missing schedule details.', 'fraud-tenant-core' ); ?>
							</p>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="ft_admin_property_review_action" />
								<input type="hidden" name="property_id" value="<?php echo (int) $prop->id; ?>" />
								<input type="hidden" name="review_decision" value="request_info" />
								<?php wp_nonce_field( 'ft_admin_prop_review_' . $prop->id, 'ft_prop_review_nonce' ); ?>

								<div style="margin-bottom: 8px;">
									<textarea name="info_request_note" rows="3" class="widefat" placeholder="<?php esc_attr_e( 'Specify requested documents or details (minimum 10 characters)...', 'fraud-tenant-core' ); ?>" required style="font-size: 12px;"></textarea>
								</div>
								<button type="submit" class="button button-secondary" style="color: #D97706; border-color: #D97706; width: 100%;">
									<?php esc_html_e( 'Send Information Request', 'fraud-tenant-core' ); ?>
								</button>
							</form>
						</div>

						<!-- Action 3: Reject Verification -->
						<div>
							<h4 style="margin: 0 0 6px 0; font-size: 13px; color: #E11D48; font-weight: 700;">
								&times; <?php esc_html_e( 'Reject Property Verification', 'fraud-tenant-core' ); ?>
							</h4>
							<p style="font-size: 12px; color: #64748B; margin: 0 0 10px 0;">
								<?php esc_html_e( 'Reject ownership verification if deeds are invalid or cannot be verified.', 'fraud-tenant-core' ); ?>
							</p>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Are you sure you want to reject this property verification?', 'fraud-tenant-core' ) ); ?>');">
								<input type="hidden" name="action" value="ft_admin_property_review_action" />
								<input type="hidden" name="property_id" value="<?php echo (int) $prop->id; ?>" />
								<input type="hidden" name="review_decision" value="reject" />
								<?php wp_nonce_field( 'ft_admin_prop_review_' . $prop->id, 'ft_prop_review_nonce' ); ?>

								<div style="margin-bottom: 8px;">
									<textarea name="rejection_reason" rows="3" class="widefat" placeholder="<?php esc_attr_e( 'Provide clear rejection reason (minimum 10 characters)...', 'fraud-tenant-core' ); ?>" required style="font-size: 12px;"></textarea>
								</div>
								<button type="submit" class="button button-link-delete" style="color: #E11D48; width: 100%; text-align: center; border: 1px solid #E11D48; padding: 6px; border-radius: 4px; text-decoration: none;">
									<?php esc_html_e( 'Reject Verification', 'fraud-tenant-core' ); ?>
								</button>
							</form>
						</div>

					</div>

				</div>

			</div>
		</div>
		<?php
	}

	/**
	 * Render Status Badge HTML.
	 *
	 * @param string $status      Status key.
	 * @param bool   $is_archived Whether property is soft-archived.
	 */
	protected static function render_status_badge( string $status, bool $is_archived = false ): void {
		if ( $is_archived || 'archived' === $status ) {
			echo '<span style="display:inline-block; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; background: #F1F5F9; color: #64748B;">' . esc_html__( 'Archived', 'fraud-tenant-core' ) . '</span>';
			return;
		}

		switch ( $status ) {
			case Constants::PROP_STATUS_VERIFIED:
				echo '<span style="display:inline-block; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; background: #CCFBF1; color: #0F766E;">' . esc_html__( 'Verified', 'fraud-tenant-core' ) . '</span>';
				break;

			case Constants::PROP_STATUS_INFO_REQUESTED:
				echo '<span style="display:inline-block; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; background: #FEF3C7; color: #B45309;">' . esc_html__( 'Info Requested', 'fraud-tenant-core' ) . '</span>';
				break;

			case Constants::PROP_STATUS_REJECTED:
				echo '<span style="display:inline-block; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; background: #FFE4E6; color: #BE123C;">' . esc_html__( 'Rejected', 'fraud-tenant-core' ) . '</span>';
				break;

			case Constants::PROP_STATUS_PENDING:
			default:
				echo '<span style="display:inline-block; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; background: #E0F2FE; color: #0369A1;">' . esc_html__( 'Pending Review', 'fraud-tenant-core' ) . '</span>';
				break;
		}
	}

	/**
	 * Display transient flash notice if present.
	 */
	protected static function render_flash_notices(): void {
		$user_id = get_current_user_id();
		$notice  = get_transient( 'ft_admin_notice_' . $user_id );
		if ( $notice && is_array( $notice ) ) {
			delete_transient( 'ft_admin_notice_' . $user_id );
			$class = 'success' === $notice['type'] ? 'notice-success' : 'notice-error';
			?>
			<div class="notice <?php echo esc_attr( $class ); ?> is-dismissible" style="margin-top: 12px; margin-bottom: 16px;">
				<p><strong><?php echo esc_html( $notice['message'] ); ?></strong></p>
			</div>
			<?php
		}
	}
}
