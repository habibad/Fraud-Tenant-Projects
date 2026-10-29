<?php
/**
 * User Application Review Admin Controller.
 *
 * Renders the pending applications queue and applicant review detail interface.
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

class UserReviewController {

	/**
	 * Main entry point for the User Applications admin page.
	 */
	public static function render(): void {
		if ( ! current_user_can( Capabilities::REVIEW_USER_REGISTRATIONS ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access to Fraud Tenant review portal.', 'fraud-tenant-core' ), 403 );
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';

		if ( 'review' === $action ) {
			self::render_detail();
		} else {
			self::render_list();
		}
	}

	/**
	 * Render the Applications Queue List.
	 */
	protected static function render_list(): void {
		$current_status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
		$current_role   = isset( $_GET['role'] ) ? sanitize_key( $_GET['role'] ) : '';
		$search_query   = isset( $_GET['s'] ) ? sanitize_text_field( trim( $_GET['s'] ) ) : '';
		$paged          = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

		$counts = AdminReviewService::count_applications_by_status();
		$data   = AdminReviewService::get_applications(
			array(
				'status'   => $current_status,
				'role'     => $current_role,
				'search'   => $search_query,
				'paged'    => $paged,
				'per_page' => 20,
			)
		);

		$base_url = admin_url( 'admin.php?page=fraud-tenant-admin' );
		?>
		<div class="wrap ft-admin-wrap">
			<h1 class="wp-heading-inline">
				<span class="dashicons dashicons-shield" style="font-size:28px;width:28px;height:28px;vertical-align:middle;color:#0E9488;margin-right:6px;"></span>
				<?php esc_html_e( 'Fraud Tenant — Applications Review Queue', 'fraud-tenant-core' ); ?>
			</h1>
			<p class="description">
				<?php esc_html_e( 'Review, verify, and approve registration applications for Property Owners and Tenants in compliance with platform standards.', 'fraud-tenant-core' ); ?>
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
					<a href="<?php echo esc_url( add_query_arg( array( 'status' => Constants::STATUS_PENDING_APPROVAL ), $base_url ) ); ?>" class="<?php echo Constants::STATUS_PENDING_APPROVAL === $current_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Pending Review', 'fraud-tenant-core' ); ?> <span class="count">(<?php echo (int) $counts['pending_approval']; ?>)</span>
					</a> |
				</li>
				<li class="info_req">
					<a href="<?php echo esc_url( add_query_arg( array( 'status' => Constants::STATUS_INFO_REQUESTED ), $base_url ) ); ?>" class="<?php echo Constants::STATUS_INFO_REQUESTED === $current_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Info Requested', 'fraud-tenant-core' ); ?> <span class="count">(<?php echo (int) $counts['info_requested']; ?>)</span>
					</a> |
				</li>
				<li class="approved">
					<a href="<?php echo esc_url( add_query_arg( array( 'status' => Constants::STATUS_APPROVED ), $base_url ) ); ?>" class="<?php echo Constants::STATUS_APPROVED === $current_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Approved', 'fraud-tenant-core' ); ?> <span class="count">(<?php echo (int) $counts['approved']; ?>)</span>
					</a> |
				</li>
				<li class="rejected">
					<a href="<?php echo esc_url( add_query_arg( array( 'status' => Constants::STATUS_REJECTED ), $base_url ) ); ?>" class="<?php echo Constants::STATUS_REJECTED === $current_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Rejected', 'fraud-tenant-core' ); ?> <span class="count">(<?php echo (int) $counts['rejected']; ?>)</span>
					</a> |
				</li>
				<li class="suspended">
					<a href="<?php echo esc_url( add_query_arg( array( 'status' => Constants::STATUS_SUSPENDED ), $base_url ) ); ?>" class="<?php echo Constants::STATUS_SUSPENDED === $current_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Suspended', 'fraud-tenant-core' ); ?> <span class="count">(<?php echo (int) $counts['suspended']; ?>)</span>
					</a>
				</li>
			</ul>

			<!-- Search and Role Filter Bar -->
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="margin-bottom: 16px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
				<input type="hidden" name="page" value="fraud-tenant-admin" />
				<?php if ( ! empty( $current_status ) ) : ?>
					<input type="hidden" name="status" value="<?php echo esc_attr( $current_status ); ?>" />
				<?php endif; ?>

				<select name="role" id="filter-by-role">
					<option value=""><?php esc_html_e( 'All Account Types', 'fraud-tenant-core' ); ?></option>
					<option value="owner" <?php selected( $current_role, 'owner' ); ?>><?php esc_html_e( 'Property Owners', 'fraud-tenant-core' ); ?></option>
					<option value="tenant" <?php selected( $current_role, 'tenant' ); ?>><?php esc_html_e( 'Tenants', 'fraud-tenant-core' ); ?></option>
				</select>

				<input type="search" id="applicant-search-input" name="s" value="<?php echo esc_attr( $search_query ); ?>" placeholder="<?php esc_attr_e( 'Search by name, email, phone...', 'fraud-tenant-core' ); ?>" style="min-width: 260px;" />
				<input type="submit" class="button button-secondary" value="<?php esc_attr_e( 'Filter Queue', 'fraud-tenant-core' ); ?>" />

				<?php if ( ! empty( $current_role ) || ! empty( $search_query ) || ! empty( $current_status ) ) : ?>
					<a href="<?php echo esc_url( $base_url ); ?>" class="button button-link"><?php esc_html_e( 'Reset Filters', 'fraud-tenant-core' ); ?></a>
				<?php endif; ?>
			</form>

			<!-- Applications Table -->
			<table class="wp-list-table widefat fixed striped table-view-list">
				<thead>
					<tr>
						<th scope="col" style="width: 22%;"><?php esc_html_e( 'Applicant', 'fraud-tenant-core' ); ?></th>
						<th scope="col" style="width: 14%;"><?php esc_html_e( 'Role / Type', 'fraud-tenant-core' ); ?></th>
						<th scope="col" style="width: 16%;"><?php esc_html_e( 'Contact Info', 'fraud-tenant-core' ); ?></th>
						<th scope="col" style="width: 16%;"><?php esc_html_e( 'Submission Date', 'fraud-tenant-core' ); ?></th>
						<th scope="col" style="width: 14%;"><?php esc_html_e( 'Status', 'fraud-tenant-core' ); ?></th>
						<th scope="col" style="width: 18%; text-align: right;"><?php esc_html_e( 'Actions', 'fraud-tenant-core' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $data['items'] ) ) : ?>
						<tr>
							<td colspan="6" style="padding: 30px; text-align: center; color: #64748B;">
								<strong><?php esc_html_e( 'No applications found matching your criteria.', 'fraud-tenant-core' ); ?></strong>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $data['items'] as $item ) : ?>
							<?php
							$review_url = add_query_arg(
								array(
									'page'    => 'fraud-tenant-admin',
									'action'  => 'review',
									'user_id' => $item->user_id,
								),
								admin_url( 'admin.php' )
							);
							?>
							<tr>
								<td>
									<strong>
										<a href="<?php echo esc_url( $review_url ); ?>" style="color:#14263E; font-size:14px;">
											<?php echo esc_html( $item->display_name ?: $item->user_login ); ?>
										</a>
									</strong>
									<div style="font-size:12px; color:#64748B; margin-top:2px;">
										<?php echo esc_html( $item->user_email ); ?>
									</div>
								</td>
								<td>
									<?php if ( 'owner' === $item->account_type ) : ?>
										<span class="ft-badge ft-badge-owner"><?php esc_html_e( 'Property Owner', 'fraud-tenant-core' ); ?></span>
									<?php else : ?>
										<span class="ft-badge ft-badge-tenant"><?php esc_html_e( 'Tenant', 'fraud-tenant-core' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<div><?php echo esc_html( $item->phone ?: '—' ); ?></div>
									<div style="font-size:12px; color:#64748B;">
										<?php echo esc_html( trim( ( $item->city ?? '' ) . ( ! empty( $item->state ) ? ', ' . $item->state : '' ) ) ?: '—' ); ?>
									</div>
								</td>
								<td>
									<?php echo esc_html( gmdate( 'M j, Y g:i A', strtotime( $item->created_at ) ) ); ?>
								</td>
								<td>
									<?php echo self::get_status_badge_html( $item->approval_status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</td>
								<td style="text-align: right;">
									<a href="<?php echo esc_url( $review_url ); ?>" class="button button-primary button-small" style="background:#0E9488; border-color:#0E9488;">
										<?php esc_html_e( 'Review Application', 'fraud-tenant-core' ); ?> &rarr;
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<!-- Pagination -->
			<?php if ( $data['total_pages'] > 1 ) : ?>
				<div class="tablenav bottom" style="margin-top: 16px;">
					<div class="tablenav-pages">
						<span class="displaying-num"><?php printf( /* translators: %d: count */ esc_html__( '%d applications', 'fraud-tenant-core' ), (int) $data['total_items'] ); ?></span>
						<span class="pagination-links">
							<?php
							echo paginate_links( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
						</span>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
		self::render_admin_styles();
	}

	/**
	 * Render Applicant Detail & Review Page.
	 */
	protected static function render_detail(): void {
		$user_id = isset( $_GET['user_id'] ) ? (int) $_GET['user_id'] : 0;
		$detail  = AdminReviewService::get_application_detail( $user_id );

		if ( ! $detail ) {
			wp_die( esc_html__( 'Applicant not found.', 'fraud-tenant-core' ), 404 );
		}

		$user     = $detail['user'];
		$profile  = $detail['profile'];
		$property = $detail['property'];
		$document = $detail['document'];
		$history  = AdminReviewService::get_user_audit_history( $user_id, 15 );
		$back_url = admin_url( 'admin.php?page=fraud-tenant-admin' );

		?>
		<div class="wrap ft-admin-wrap">
			<p>
				<a href="<?php echo esc_url( $back_url ); ?>" class="button button-secondary">
					&larr; <?php esc_html_e( 'Back to Applications Queue', 'fraud-tenant-core' ); ?>
				</a>
			</p>

			<h1 class="wp-heading-inline">
				<?php printf( /* translators: %s: applicant display name */ esc_html__( 'Review Application: %s', 'fraud-tenant-core' ), esc_html( $user->display_name ) ); ?>
				<?php echo self::get_status_badge_html( $profile->approval_status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</h1>
			<hr class="wp-header-end">

			<?php self::render_flash_notices(); ?>

			<div class="ft-detail-grid">
				<!-- Left Column: Applicant Data Cards -->
				<div class="ft-detail-col ft-detail-main">
					
					<!-- Card 1: Core Account Details -->
					<div class="ft-admin-card">
						<div class="ft-card-header">
							<h2><?php esc_html_e( 'Account Profile Information', 'fraud-tenant-core' ); ?></h2>
						</div>
						<div class="ft-card-body">
							<table class="ft-info-table">
								<tr>
									<th><?php esc_html_e( 'Full Legal Name', 'fraud-tenant-core' ); ?></th>
									<td><strong><?php echo esc_html( $user->display_name ); ?></strong></td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Account Role', 'fraud-tenant-core' ); ?></th>
									<td>
										<?php if ( 'owner' === $profile->account_type ) : ?>
											<span class="ft-badge ft-badge-owner"><?php esc_html_e( 'Property Owner', 'fraud-tenant-core' ); ?></span>
										<?php else : ?>
											<span class="ft-badge ft-badge-tenant"><?php esc_html_e( 'Tenant', 'fraud-tenant-core' ); ?></span>
										<?php endif; ?>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Email Address', 'fraud-tenant-core' ); ?></th>
									<td>
										<a href="mailto:<?php echo esc_attr( $user->user_email ); ?>">
											<?php echo esc_html( $user->user_email ); ?>
										</a>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Phone Number', 'fraud-tenant-core' ); ?></th>
									<td><?php echo esc_html( $profile->phone ?: '—' ); ?></td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Registration Date', 'fraud-tenant-core' ); ?></th>
									<td><?php echo esc_html( gmdate( 'F j, Y g:i A', strtotime( $profile->created_at ) ) ); ?></td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Terms of Service Accepted', 'fraud-tenant-core' ); ?></th>
									<td><?php echo esc_html( ! empty( $profile->terms_accepted_at ) ? gmdate( 'F j, Y g:i A', strtotime( $profile->terms_accepted_at ) ) : '—' ); ?></td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Privacy Policy Acknowledged', 'fraud-tenant-core' ); ?></th>
									<td><?php echo esc_html( ! empty( $profile->privacy_accepted_at ) ? gmdate( 'F j, Y g:i A', strtotime( $profile->privacy_accepted_at ) ) : '—' ); ?></td>
								</tr>
							</table>
						</div>
					</div>

					<!-- Card 2: Role-Specific Details -->
					<?php if ( 'owner' === $profile->account_type ) : ?>
						<!-- Owner Property & Ownership Document Card -->
						<div class="ft-admin-card">
							<div class="ft-card-header">
								<h2><?php esc_html_e( 'Primary Rental Property & Ownership Proof', 'fraud-tenant-core' ); ?></h2>
							</div>
							<div class="ft-card-body">
								<?php if ( $property ) : ?>
									<table class="ft-info-table">
										<tr>
											<th><?php esc_html_e( 'Property Street Address', 'fraud-tenant-core' ); ?></th>
											<td><strong><?php echo esc_html( $property->address_street . ( $property->unit_number ? ' Apt ' . $property->unit_number : '' ) ); ?></strong></td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'City, State, ZIP', 'fraud-tenant-core' ); ?></th>
											<td><?php echo esc_html( "{$property->city}, {$property->state} {$property->postal_code}" ); ?></td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Property Type / Units', 'fraud-tenant-core' ); ?></th>
											<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $property->property_type ) ) . ' (' . $property->units_count . ' unit)' ); ?></td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Property Verification Status', 'fraud-tenant-core' ); ?></th>
											<td>
												<?php if ( 'verified' === $property->status || 'active' === $property->status ) : ?>
													<span class="ft-badge ft-badge-approved"><?php esc_html_e( 'Verified', 'fraud-tenant-core' ); ?></span>
												<?php elseif ( 'rejected' === $property->status ) : ?>
													<span class="ft-badge ft-badge-rejected"><?php esc_html_e( 'Verification Rejected', 'fraud-tenant-core' ); ?></span>
												<?php else : ?>
													<span class="ft-badge ft-badge-pending"><?php esc_html_e( 'Pending Verification', 'fraud-tenant-core' ); ?></span>
												<?php endif; ?>
											</td>
										</tr>
									</table>

									<!-- Attached Ownership Document Section -->
									<div class="ft-sub-section" style="margin-top: 20px; padding-top: 16px; border-top: 1px solid #E2E8F0;">
										<h3 style="margin-top:0; font-size: 15px; color:#14263E;">
											<span class="dashicons dashicons-media-document" style="color:#0E9488; vertical-align:text-bottom;"></span>
											<?php esc_html_e( 'Ownership Proof Document', 'fraud-tenant-core' ); ?>
										</h3>

										<?php if ( $document ) : ?>
											<?php
											$doc_url = add_query_arg(
												array(
													'action'      => 'ft_view_document',
													'document_id' => $document->id,
													'_wpnonce'    => wp_create_nonce( 'ft_view_doc_' . $document->id ),
												),
												admin_url( 'admin-post.php' )
											);
											?>
											<div class="ft-doc-card" style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:6px; padding:16px; display:flex; justify-content:space-between; align-items:center;">
												<div>
													<div style="font-weight:600; color:#14263E; font-size:14px;">
														<?php echo esc_html( $document->original_name ); ?>
													</div>
													<div style="font-size:12px; color:#64748B; margin-top:4px;">
														<?php printf( /* translators: 1: size in KB, 2: MIME, 3: date */ esc_html__( 'Size: %1$d KB | Format: %2$s | Uploaded: %3$s', 'fraud-tenant-core' ), round( $document->file_size / 1024 ), esc_html( $document->mime_type ), esc_html( gmdate( 'M j, Y', strtotime( $document->uploaded_at ) ) ) ); ?>
													</div>
													<div style="font-size:11px; color:#0E9488; margin-top:3px;">
														<span class="dashicons dashicons-lock" style="font-size:13px;width:13px;height:13px;vertical-align:middle;"></span>
														<?php esc_html_e( 'Encrypted/private server storage. Authenticated inspection only.', 'fraud-tenant-core' ); ?>
													</div>
												</div>
												<div>
													<a href="<?php echo esc_url( $doc_url ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary" style="background:#0E9488; border-color:#0E9488;">
														<span class="dashicons dashicons-visibility" style="vertical-align:text-bottom; margin-right:4px;"></span>
														<?php esc_html_e( 'View Protected Document', 'fraud-tenant-core' ); ?>
													</a>
												</div>
											</div>
										<?php else : ?>
											<div style="color:#DC2626; font-style:italic;">
												<?php esc_html_e( 'No ownership proof document attached to this property.', 'fraud-tenant-core' ); ?>
											</div>
										<?php endif; ?>
									</div>

									<!-- Separate Property Verification Decision Box -->
									<div class="ft-sub-section" style="margin-top: 20px; padding: 16px; background:#F1F5F9; border-radius:6px; border:1px solid #CBD5E1;">
										<h4 style="margin:0 0 8px 0; color:#14263E; font-size:14px;">
											<?php esc_html_e( 'Property Ownership Verification Decision', 'fraud-tenant-core' ); ?>
										</h4>
										<p style="font-size:12px; color:#475569; margin:0 0 12px 0;">
											<?php esc_html_e( 'Verify or reject this rental property separately from the owner account approval. Verifying confirms you inspected the deed/document. It does not automatically approve the user account.', 'fraud-tenant-core' ); ?>
										</p>

										<div style="display:flex; gap:10px; flex-wrap:wrap;">
											<!-- Verify Property Form -->
											<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
												<input type="hidden" name="action" value="ft_admin_property_action" />
												<input type="hidden" name="property_action" value="verify" />
												<input type="hidden" name="property_id" value="<?php echo (int) $property->id; ?>" />
												<input type="hidden" name="user_id" value="<?php echo (int) $user_id; ?>" />
												<?php wp_nonce_field( 'ft_admin_prop_verify_' . $property->id, 'ft_prop_nonce' ); ?>
												<button type="submit" class="button button-secondary" <?php disabled( 'verified' === $property->status || 'active' === $property->status ); ?> onclick="return confirm('Confirm property ownership verification?');">
													<span class="dashicons dashicons-yes-alt" style="color:#0E9488; vertical-align:text-bottom;"></span>
													<?php esc_html_e( 'Verify Property Ownership', 'fraud-tenant-core' ); ?>
												</button>
											</form>

											<!-- Reject Property Form -->
											<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
												<input type="hidden" name="action" value="ft_admin_property_action" />
												<input type="hidden" name="property_action" value="reject" />
												<input type="hidden" name="property_id" value="<?php echo (int) $property->id; ?>" />
												<input type="hidden" name="user_id" value="<?php echo (int) $user_id; ?>" />
												<?php wp_nonce_field( 'ft_admin_prop_verify_' . $property->id, 'ft_prop_nonce' ); ?>
												<button type="submit" class="button button-link-delete" <?php disabled( 'rejected' === $property->status ); ?> onclick="return confirm('Reject property ownership verification?');">
													<?php esc_html_e( 'Reject Property Ownership', 'fraud-tenant-core' ); ?>
												</button>
											</form>
										</div>
									</div>
								<?php else : ?>
									<p style="color:#64748B;"><?php esc_html_e( 'No primary property registered for this owner.', 'fraud-tenant-core' ); ?></p>
								<?php endif; ?>
							</div>
						</div>
					<?php else : ?>
						<!-- Tenant Residence & Rental History Card -->
						<div class="ft-admin-card">
							<div class="ft-card-header">
								<h2><?php esc_html_e( 'Tenant Residence & Rental Information', 'fraud-tenant-core' ); ?></h2>
							</div>
							<div class="ft-card-body">
								<table class="ft-info-table">
									<tr>
										<th><?php esc_html_e( 'Current Residence Address', 'fraud-tenant-core' ); ?></th>
										<td>
											<strong><?php echo esc_html( $profile->address_line1 ?: '—' ); ?></strong><br>
											<?php echo esc_html( "{$profile->city}, {$profile->state} {$profile->postal_code}" ); ?>
										</td>
									</tr>
									<tr>
										<th><?php esc_html_e( 'Previous Rental Address', 'fraud-tenant-core' ); ?></th>
										<td>
											<?php if ( ! empty( $profile->prev_address_line1 ) ) : ?>
												<?php echo esc_html( $profile->prev_address_line1 ); ?><br>
												<?php echo esc_html( "{$profile->prev_city}, {$profile->prev_state} {$profile->prev_postal_code}" ); ?>
											<?php else : ?>
												<span style="color:#64748B; font-style:italic;"><?php esc_html_e( 'None provided during registration.', 'fraud-tenant-core' ); ?></span>
											<?php endif; ?>
										</td>
									</tr>
									<tr>
										<th><?php esc_html_e( 'Identity Tokenized', 'fraud-tenant-core' ); ?></th>
										<td>
											<span class="ft-badge ft-badge-approved">
												<?php esc_html_e( 'Standard Verified (Zero DOB / Zero Raw SSN)', 'fraud-tenant-core' ); ?>
											</span>
										</td>
									</tr>
								</table>
							</div>
						</div>
					<?php endif; ?>

					<!-- Card 3: Audit Trail & Decision History -->
					<div class="ft-admin-card">
						<div class="ft-card-header">
							<h2><?php esc_html_e( 'Compliance Review & Audit History', 'fraud-tenant-core' ); ?></h2>
						</div>
						<div class="ft-card-body">
							<?php if ( empty( $history ) ) : ?>
								<p style="color:#64748B; font-style:italic;"><?php esc_html_e( 'No prior audit events recorded for this applicant.', 'fraud-tenant-core' ); ?></p>
							<?php else : ?>
								<div class="ft-timeline">
									<?php foreach ( $history as $evt ) : ?>
										<div class="ft-timeline-item" style="padding-bottom:12px; margin-bottom:12px; border-bottom:1px dashed #E2E8F0;">
											<div style="display:flex; justify-content:space-between; font-size:12px; color:#64748B;">
												<strong style="color:#14263E; font-size:13px;">
													<?php echo esc_html( ucwords( str_replace( array( '.', '_' ), ' ', $evt->action ) ) ); ?>
												</strong>
												<span><?php echo esc_html( gmdate( 'M j, Y g:i A', strtotime( $evt->created_at ) ) ); ?></span>
											</div>
											<div style="font-size:12px; color:#475569; margin-top:2px;">
												<?php printf( /* translators: %s: actor */ esc_html__( 'Actor: %s', 'fraud-tenant-core' ), esc_html( $evt->actor_name ?: 'System / Self' ) ); ?>
												<?php if ( ! empty( $evt->details_json ) ) : ?>
													<code style="display:block; margin-top:4px; font-size:11px; background:#F8FAFC; padding:4px 8px; border-radius:4px; word-break:break-all;">
														<?php echo esc_html( $evt->details_json ); ?>
													</code>
												<?php endif; ?>
											</div>
										</div>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<!-- Right Column: Administrative Review Actions -->
				<div class="ft-detail-col ft-detail-sidebar">

					<!-- Action Card: Status & Decision Panel -->
					<div class="ft-admin-card ft-action-card">
						<div class="ft-card-header" style="background:#14263E; color:#FFFFFF; border-radius:6px 6px 0 0; padding:12px 16px;">
							<h3 style="margin:0; font-size:15px; color:#FFFFFF;">
								<span class="dashicons dashicons-clipboard" style="vertical-align:text-bottom; color:#0E9488;"></span>
								<?php esc_html_e( 'Review Decision Actions', 'fraud-tenant-core' ); ?>
							</h3>
						</div>
						<div class="ft-card-body" style="padding: 16px;">
							<div style="margin-bottom:16px;">
								<div style="font-size:12px; color:#64748B; text-transform:uppercase; letter-spacing:0.5px;"><?php esc_html_e( 'Current Status', 'fraud-tenant-core' ); ?></div>
								<div style="margin-top:4px;"><?php echo self::get_status_badge_html( $profile->approval_status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
							</div>

							<?php if ( ! empty( $profile->rejection_reason ) ) : ?>
								<div style="margin-bottom:16px; padding:10px; background:#FEF3C7; border:1px solid #F59E0B; border-radius:4px; font-size:12px; color:#92400E;">
									<strong><?php esc_html_e( 'Latest Note / Request Reason:', 'fraud-tenant-core' ); ?></strong>
									<p style="margin:4px 0 0 0;"><?php echo esc_html( $profile->rejection_reason ); ?></p>
									<?php if ( ! empty( $detail['reviewer_name'] ) ) : ?>
										<div style="font-size:11px; margin-top:4px; color:#B45309;">
											<?php printf( /* translators: 1: name, 2: time */ esc_html__( 'By %1$s on %2$s', 'fraud-tenant-core' ), esc_html( $detail['reviewer_name'] ), esc_html( gmdate( 'M j, Y', strtotime( $profile->reviewed_at ) ) ) ); ?>
										</div>
									<?php endif; ?>
								</div>
							<?php endif; ?>

							<!-- ACTION 1: APPROVE APPLICATION -->
							<div class="ft-decision-block" style="padding-bottom:16px; margin-bottom:16px; border-bottom:1px solid #E2E8F0;">
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="ft_admin_review_action" />
									<input type="hidden" name="review_decision" value="approve" />
									<input type="hidden" name="user_id" value="<?php echo (int) $user_id; ?>" />
									<?php wp_nonce_field( 'ft_admin_review_' . $user_id, 'ft_review_nonce' ); ?>

									<p style="font-size:12px; color:#475569; margin:0 0 8px 0;">
										<?php esc_html_e( 'Approving activates platform sign-in access. Note: Property Owner account approval does not automatically verify attached rental properties.', 'fraud-tenant-core' ); ?>
									</p>

									<button type="submit" class="button button-primary button-large" style="width:100%; background:#0E9488; border-color:#0E9488; height:38px; font-weight:600;" <?php disabled( Constants::STATUS_APPROVED === $profile->approval_status ); ?> onclick="return confirm('Confirm approval of this applicant account?');">
										<span class="dashicons dashicons-yes" style="vertical-align:text-bottom;"></span>
										<?php esc_html_e( 'Approve Application', 'fraud-tenant-core' ); ?>
									</button>
								</form>
							</div>

							<!-- ACTION 2: REQUEST ADDITIONAL INFO -->
							<div class="ft-decision-block" style="padding-bottom:16px; margin-bottom:16px; border-bottom:1px solid #E2E8F0;">
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="ft_admin_review_action" />
									<input type="hidden" name="review_decision" value="request_info" />
									<input type="hidden" name="user_id" value="<?php echo (int) $user_id; ?>" />
									<?php wp_nonce_field( 'ft_admin_review_' . $user_id, 'ft_review_nonce' ); ?>

									<label for="info_request_note" style="display:block; font-size:12px; font-weight:600; color:#14263E; margin-bottom:4px;">
										<?php esc_html_e( 'Request Additional Information', 'fraud-tenant-core' ); ?> <span style="color:#DC2626;">*</span>
									</label>
									<textarea name="info_request_note" id="info_request_note" rows="3" style="width:100%; font-size:12px; margin-bottom:8px;" placeholder="<?php esc_attr_e( 'Specify the missing document, clarity required, or proof needed...', 'fraud-tenant-core' ); ?>" required></textarea>

									<button type="submit" class="button button-secondary" style="width:100%;" <?php disabled( Constants::STATUS_APPROVED === $profile->approval_status ); ?>>
										<span class="dashicons dashicons-email-alt" style="vertical-align:text-bottom;"></span>
										<?php esc_html_e( 'Send Information Request', 'fraud-tenant-core' ); ?>
									</button>
								</form>
							</div>

							<!-- ACTION 3: REJECT APPLICATION -->
							<div class="ft-decision-block" style="padding-bottom:16px; margin-bottom:16px; border-bottom:1px solid #E2E8F0;">
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="ft_admin_review_action" />
									<input type="hidden" name="review_decision" value="reject" />
									<input type="hidden" name="user_id" value="<?php echo (int) $user_id; ?>" />
									<?php wp_nonce_field( 'ft_admin_review_' . $user_id, 'ft_review_nonce' ); ?>

									<label for="rejection_reason" style="display:block; font-size:12px; font-weight:600; color:#DC2626; margin-bottom:4px;">
										<?php esc_html_e( 'Reject Application', 'fraud-tenant-core' ); ?> <span style="color:#DC2626;">*</span>
									</label>
									<textarea name="rejection_reason" id="rejection_reason" rows="3" style="width:100%; font-size:12px; margin-bottom:8px;" placeholder="<?php esc_attr_e( 'Mandatory rejection reason (shared with applicant)...', 'fraud-tenant-core' ); ?>" required></textarea>

									<button type="submit" class="button button-link-delete" style="width:100%; text-align:center; padding:6px 0;" <?php disabled( Constants::STATUS_REJECTED === $profile->approval_status ); ?> onclick="return confirm('Are you sure you want to REJECT this applicant? An email notice will be dispatched.');">
										<?php esc_html_e( 'Reject Application', 'fraud-tenant-core' ); ?>
									</button>
								</form>
							</div>

							<!-- ACTION 4: SUSPEND ACCOUNT (FOR APPROVED ACCOUNTS) -->
							<?php if ( Constants::STATUS_APPROVED === $profile->approval_status ) : ?>
								<div class="ft-decision-block">
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="action" value="ft_admin_review_action" />
										<input type="hidden" name="review_decision" value="suspend" />
										<input type="hidden" name="user_id" value="<?php echo (int) $user_id; ?>" />
										<?php wp_nonce_field( 'ft_admin_review_' . $user_id, 'ft_review_nonce' ); ?>

										<label for="suspension_reason" style="display:block; font-size:12px; font-weight:600; color:#B45309; margin-bottom:4px;">
											<?php esc_html_e( 'Suspend Active Account', 'fraud-tenant-core' ); ?>
										</label>
										<textarea name="suspension_reason" id="suspension_reason" rows="2" style="width:100%; font-size:12px; margin-bottom:8px;" placeholder="<?php esc_attr_e( 'Reason for account suspension...', 'fraud-tenant-core' ); ?>" required></textarea>

										<button type="submit" class="button button-secondary" style="width:100%; color:#B45309;" onclick="return confirm('Suspend this user account?');">
											<span class="dashicons dashicons-lock" style="vertical-align:text-bottom;"></span>
											<?php esc_html_e( 'Suspend Account Access', 'fraud-tenant-core' ); ?>
										</button>
									</form>
								</div>
							<?php endif; ?>
						</div>
					</div>

				</div>
			</div>
		</div>
		<?php
		self::render_admin_styles();
	}

	/**
	 * Get HTML badge for approval status.
	 *
	 * @param string $status Status code.
	 * @return string HTML badge.
	 */
	public static function get_status_badge_html( string $status ): string {
		switch ( $status ) {
			case Constants::STATUS_APPROVED:
				return '<span class="ft-badge ft-badge-approved">' . esc_html__( 'Approved', 'fraud-tenant-core' ) . '</span>';
			case Constants::STATUS_REJECTED:
				return '<span class="ft-badge ft-badge-rejected">' . esc_html__( 'Rejected', 'fraud-tenant-core' ) . '</span>';
			case Constants::STATUS_INFO_REQUESTED:
				return '<span class="ft-badge ft-badge-info-req">' . esc_html__( 'Info Requested', 'fraud-tenant-core' ) . '</span>';
			case Constants::STATUS_SUSPENDED:
				return '<span class="ft-badge ft-badge-suspended">' . esc_html__( 'Suspended', 'fraud-tenant-core' ) . '</span>';
			case Constants::STATUS_EMAIL_UNVERIFIED:
				return '<span class="ft-badge ft-badge-unverified">' . esc_html__( 'Email Unverified', 'fraud-tenant-core' ) . '</span>';
			case Constants::STATUS_PENDING_APPROVAL:
			default:
				return '<span class="ft-badge ft-badge-pending">' . esc_html__( 'Pending Review', 'fraud-tenant-core' ) . '</span>';
		}
	}

	/**
	 * Render flash notices from transient storage.
	 */
	protected static function render_flash_notices(): void {
		$key    = 'ft_admin_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( $notice && is_array( $notice ) ) {
			delete_transient( $key );
			$class = 'success' === $notice['type'] ? 'notice-success' : 'notice-error';
			?>
			<div class="notice <?php echo esc_attr( $class ); ?> is-dismissible" style="margin: 16px 0;">
				<p><strong><?php echo esc_html( $notice['message'] ); ?></strong></p>
			</div>
			<?php
		}
	}

	/**
	 * Render inline CSS matching the Fraud Tenant Design System.
	 */
	protected static function render_admin_styles(): void {
		?>
		<style>
			.ft-admin-wrap { max-width: 1200px; margin-top: 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; }
			.ft-badge { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; line-height: 1.4; }
			.ft-badge-owner { background: #E0F2FE; color: #0369A1; }
			.ft-badge-tenant { background: #F3E8FF; color: #7E22CE; }
			.ft-badge-pending { background: #FEF3C7; color: #B45309; }
			.ft-badge-info-req { background: #FFFBEB; color: #D97706; border: 1px solid #FCD34D; }
			.ft-badge-approved { background: #CCFBF1; color: #0F766E; }
			.ft-badge-rejected { background: #FEE2E2; color: #B91C1C; }
			.ft-badge-suspended { background: #F1F5F9; color: #475569; }
			.ft-badge-unverified { background: #F3F4F6; color: #6B7280; }
			
			.ft-detail-grid { display: flex; gap: 24px; margin-top: 20px; align-items: flex-start; }
			.ft-detail-main { flex: 1 1 65%; min-width: 0; }
			.ft-detail-sidebar { flex: 1 1 35%; min-width: 320px; }
			
			.ft-admin-card { background: #FFFFFF; border: 1px solid #CBD5E1; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 24px; }
			.ft-card-header { padding: 14px 20px; border-bottom: 1px solid #E2E8F0; background: #FAFBFD; border-radius: 6px 6px 0 0; }
			.ft-card-header h2 { margin: 0; font-size: 16px; color: #14263E; font-weight: 600; }
			.ft-card-body { padding: 20px; }
			
			.ft-info-table { width: 100%; border-collapse: collapse; }
			.ft-info-table th { width: 34%; text-align: left; padding: 10px 0; color: #64748B; font-size: 13px; font-weight: 500; border-bottom: 1px solid #F1F5F9; }
			.ft-info-table td { padding: 10px 0; color: #1E293B; font-size: 13px; border-bottom: 1px solid #F1F5F9; }
			
			@media (max-width: 900px) {
				.ft-detail-grid { flex-direction: column; }
				.ft-detail-sidebar { width: 100%; min-width: 100%; }
			}
		</style>
		<?php
	}
}

