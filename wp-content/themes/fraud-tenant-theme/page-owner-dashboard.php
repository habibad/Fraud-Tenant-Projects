<?php
/**
 * Template Name: Owner Dashboard
 *
 * Dedicated authenticated SaaS application shell for approved Property Owners.
 * Matches the approved design showcase layout with full multi-property management,
 * private ownership document uploads, and soft archival.
 *
 * @package FraudTenantTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use FraudTenant\Auth\AuthHandler;
use FraudTenant\Auth\AuthService;
use FraudTenant\Services\DashboardService;
use FraudTenant\Services\PropertyService;
use FraudTenant\Utils\Constants;

// 1. Enforce strict access control, role check, and lifecycle state
$current_user = DashboardService::enforce_access( Constants::ROLE_OWNER );
$owner_id     = $current_user->ID;

// 2. Resolve sub-views and routes
$current_tab  = sanitize_key( get_query_var( 'ft_tab', $_GET['tab'] ?? 'overview' ) );
$property_id  = (int) get_query_var( 'ft_property_id', $_GET['property_id'] ?? 0 );
$prop_subview = sanitize_key( $_GET['subview'] ?? 'active' );

// 3. Fetch genuine database records (Zero mock data)
$data = DashboardService::get_owner_dashboard_data( $owner_id );

$user_profile = $data['profile'];
$first_name   = $current_user->first_name ?: ( explode( ' ', $current_user->display_name )[0] ?? 'Property Owner' );

// If inspecting a specific property in the properties tab
$target_property = null;
$target_docs     = array();
if ( 'properties' === $current_tab && $property_id > 0 ) {
	$target_property = PropertyService::get_property( $property_id, $owner_id );
	if ( $target_property ) {
		$target_docs = PropertyService::get_property_documents( $property_id, $owner_id );
	}
}

get_header();
?>

<div class="dashboard-shell-layout" id="main-content">

	<!-- Left Navy Sidebar (Showcase Match) -->
	<aside class="dashboard-sidebar" id="ftDashboardSidebar">
		<div>
			<ul class="dashboard-nav-list" role="navigation" aria-label="<?php esc_attr_e( 'Owner Dashboard Navigation', 'fraud-tenant-theme' ); ?>">
				<li class="dashboard-nav-item <?php echo 'overview' === $current_tab ? 'is-active' : ''; ?>">
					<a href="<?php echo esc_url( home_url( '/owner-dashboard/' ) ); ?>">
						<svg class="dashboard-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
						<span><?php esc_html_e( 'Dashboard', 'fraud-tenant-theme' ); ?></span>
					</a>
				</li>
				<li class="dashboard-nav-item <?php echo 'properties' === $current_tab ? 'is-active' : ''; ?>">
					<a href="<?php echo esc_url( home_url( '/owner-dashboard/properties/' ) ); ?>">
						<svg class="dashboard-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
						<span><?php esc_html_e( 'My Properties', 'fraud-tenant-theme' ); ?></span>
					</a>
				</li>
				<li class="dashboard-nav-item">
					<a href="<?php echo esc_url( home_url( '/owner-dashboard/#search-tenants' ) ); ?>">
						<svg class="dashboard-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
						<span><?php esc_html_e( 'Search Tenants', 'fraud-tenant-theme' ); ?></span>
					</a>
				</li>
				<li class="dashboard-nav-item">
					<a href="<?php echo esc_url( home_url( '/owner-dashboard/#my-reports' ) ); ?>">
						<svg class="dashboard-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
						<span><?php esc_html_e( 'My Reports', 'fraud-tenant-theme' ); ?></span>
					</a>
				</li>
				<li class="dashboard-nav-item">
					<button type="button" data-ft-modal-target="ftMembershipModal">
						<svg class="dashboard-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 4l3 12h14l3-12-6 7-4-7-4 7-6-7zm3 16h14"></path></svg>
						<span><?php esc_html_e( 'Membership', 'fraud-tenant-theme' ); ?></span>
					</button>
				</li>
				<li class="dashboard-nav-item">
					<button type="button" data-ft-modal-target="ftNotificationsModal">
						<svg class="dashboard-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
						<span><?php esc_html_e( 'Notifications', 'fraud-tenant-theme' ); ?></span>
					</button>
				</li>
				<li class="dashboard-nav-item">
					<button type="button" data-ft-modal-target="ftProfileModal">
						<svg class="dashboard-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
						<span><?php esc_html_e( 'Profile Settings', 'fraud-tenant-theme' ); ?></span>
					</button>
				</li>
			</ul>
		</div>

		<div class="dashboard-sidebar-footer">
			<div class="dashboard-support-box">
				<strong><?php esc_html_e( 'Need Assistance?', 'fraud-tenant-theme' ); ?></strong>
				<p style="margin: 0 0 8px 0;"><?php esc_html_e( 'Our compliance team is here to assist verified property owners.', 'fraud-tenant-theme' ); ?></p>
				<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Contact Compliance →', 'fraud-tenant-theme' ); ?></a>
			</div>
		</div>
	</aside>

	<!-- Main Dashboard Application Area -->
	<main class="dashboard-main-area">

		<?php
		$flash_notice = AuthHandler::get_flash_notice();
		if ( $flash_notice ) :
			$alert_class = 'success' === $flash_notice['type'] ? 'auth-alert-success' : 'auth-alert-error';
			?>
			<div class="auth-alert <?php echo esc_attr( $alert_class ); ?>" role="alert" style="margin-bottom: 24px;">
				<span><?php echo esc_html( $flash_notice['message'] ); ?></span>
			</div>
		<?php endif; ?>

		<?php if ( 'properties' === $current_tab ) : ?>

			<!-- ============================================================ -->
			<!-- VIEW: PROPERTIES MANAGEMENT (TAB OR DETAIL)                  -->
			<!-- ============================================================ -->

			<?php if ( $property_id > 0 ) : ?>

				<?php if ( ! $target_property ) : ?>
					<div class="auth-alert auth-alert-error" style="margin-bottom:24px;">
						<span><?php esc_html_e( 'Property not found or you do not have permission to view it.', 'fraud-tenant-theme' ); ?></span>
					</div>
					<p><a href="<?php echo esc_url( home_url( '/owner-dashboard/properties/' ) ); ?>" class="dash-btn-sm dash-btn-sm-primary">&larr; <?php esc_html_e( 'Back to My Properties', 'fraud-tenant-theme' ); ?></a></p>
				<?php else : ?>

					<!-- Property Detail & Edit View -->
					<div style="margin-bottom: 20px;">
						<a href="<?php echo esc_url( home_url( '/owner-dashboard/properties/' ) ); ?>" class="dash-btn-sm dash-btn-sm-outline" style="margin-bottom:12px;">
							&larr; <?php esc_html_e( 'Back to All Properties', 'fraud-tenant-theme' ); ?>
						</a>
						<h1 style="font-size: 1.6rem; font-weight: 800; color: var(--dash-navy); margin: 6px 0 2px 0;">
							<?php echo esc_html( $target_property->title ?: $target_property->address_street ); ?>
						</h1>
						<p style="color: var(--dash-muted); margin: 0; font-size: 0.95rem;">
							<?php echo esc_html( $target_property->address_street . ( $target_property->unit_number ? ' #' . $target_property->unit_number : '' ) . ', ' . $target_property->city . ', ' . $target_property->state . ' ' . $target_property->postal_code ); ?>
						</p>
					</div>

					<div class="dashboard-grid-2col">

						<!-- Left Column: Status, Documents & Archival -->
						<div class="dash-grid-left">

							<!-- Status & Compliance Notice Card -->
							<div class="dash-panel">
								<div class="dash-panel-header">
									<h2 class="dash-panel-title"><?php esc_html_e( 'Ownership Verification Status', 'fraud-tenant-theme' ); ?></h2>
									<div>
										<?php if ( 'archived' === $target_property->status || ! empty( $target_property->archived_at ) ) : ?>
											<span class="badge-dash" style="background:#F1F5F9; color:#64748B;"><?php esc_html_e( 'Archived', 'fraud-tenant-theme' ); ?></span>
										<?php elseif ( 'verified' === $target_property->status ) : ?>
											<span class="badge-dash badge-dash-verified"><?php esc_html_e( 'Verified Ownership', 'fraud-tenant-theme' ); ?></span>
										<?php elseif ( 'info_requested' === $target_property->status ) : ?>
											<span class="badge-dash" style="background:#FEF3C7; color:#B45309;"><?php esc_html_e( 'Info Requested', 'fraud-tenant-theme' ); ?></span>
										<?php elseif ( 'rejected' === $target_property->status ) : ?>
											<span class="badge-dash badge-dash-rejected"><?php esc_html_e( 'Verification Rejected', 'fraud-tenant-theme' ); ?></span>
										<?php else : ?>
											<span class="badge-dash badge-dash-pending"><?php esc_html_e( 'Pending Verification', 'fraud-tenant-theme' ); ?></span>
										<?php endif; ?>
									</div>
								</div>

								<?php if ( ! empty( $target_property->admin_notes ) || ! empty( $target_property->rejection_reason ) ) : ?>
									<div style="background:#FFFBEB; border:1px solid #FCD34D; border-radius:8px; padding:14px; margin-bottom:16px;">
										<?php if ( ! empty( $target_property->rejection_reason ) ) : ?>
											<strong style="color:#B91C1C; display:block; margin-bottom:4px; font-size:13px;"><?php esc_html_e( 'Compliance Review Note (Rejection):', 'fraud-tenant-theme' ); ?></strong>
											<p style="margin:0 0 6px 0; font-size:13px; color:#1E293B;"><?php echo nl2br( esc_html( $target_property->rejection_reason ) ); ?></p>
										<?php endif; ?>
										<?php if ( ! empty( $target_property->admin_notes ) ) : ?>
											<strong style="color:#B45309; display:block; margin-bottom:4px; font-size:13px;"><?php esc_html_e( 'Compliance Request Note:', 'fraud-tenant-theme' ); ?></strong>
											<p style="margin:0; font-size:13px; color:#1E293B;"><?php echo nl2br( esc_html( $target_property->admin_notes ) ); ?></p>
										<?php endif; ?>
									</div>
								<?php endif; ?>

								<p style="font-size:13px; color:var(--dash-muted); margin:0;">
									<?php esc_html_e( 'Ownership documents undergo human administrative review. Validated properties allow authorized tenant screening under the FCRA and factual incident documentation.', 'fraud-tenant-theme' ); ?>
								</p>
							</div>

							<!-- Ownership Documents Panel -->
							<div class="dash-panel" style="margin-top:20px;">
								<div class="dash-panel-header">
									<h2 class="dash-panel-title"><?php esc_html_e( 'Ownership Documents', 'fraud-tenant-theme' ); ?></h2>
									<span style="font-size:12px; color:var(--dash-muted);">
										<?php printf( /* translators: %d: count */ esc_html__( '%d file(s) on record', 'fraud-tenant-theme' ), count( $target_docs ) ); ?>
									</span>
								</div>

								<?php if ( empty( $target_docs ) ) : ?>
									<div class="dash-empty-state" style="padding:20px 0;">
										<p style="color:var(--dash-muted); font-size:13px; margin:0;"><?php esc_html_e( 'No documents uploaded for this property yet.', 'fraud-tenant-theme' ); ?></p>
									</div>
								<?php else : ?>
									<table class="doc-history-table">
										<thead>
											<tr>
												<th><?php esc_html_e( 'Document', 'fraud-tenant-theme' ); ?></th>
												<th><?php esc_html_e( 'Uploaded', 'fraud-tenant-theme' ); ?></th>
												<th><?php esc_html_e( 'Status', 'fraud-tenant-theme' ); ?></th>
												<th style="text-align:right;"><?php esc_html_e( 'Action', 'fraud-tenant-theme' ); ?></th>
											</tr>
										</thead>
										<tbody>
											<?php foreach ( $target_docs as $d ) : ?>
												<?php
												$stream_url = wp_nonce_url(
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
														<div style="font-size:11px; color:var(--dash-muted);">
															<?php echo esc_html( size_format( (int) $d->file_size ) ); ?> &bull; <?php echo esc_html( $d->mime_type ); ?>
														</div>
													</td>
													<td>
														<?php echo esc_html( gmdate( 'M j, Y', strtotime( $d->uploaded_at ) ) ); ?>
													</td>
													<td>
														<?php if ( 'approved' === $d->review_status ) : ?>
															<span style="color:#0E9488; font-weight:700;">&check; <?php esc_html_e( 'Approved', 'fraud-tenant-theme' ); ?></span>
														<?php elseif ( 'rejected' === $d->review_status ) : ?>
															<span style="color:#E11D48; font-weight:700;">&times; <?php esc_html_e( 'Rejected', 'fraud-tenant-theme' ); ?></span>
														<?php else : ?>
															<span style="color:#D97706; font-weight:700;">&bull; <?php esc_html_e( 'Pending Review', 'fraud-tenant-theme' ); ?></span>
														<?php endif; ?>
													</td>
													<td style="text-align:right;">
														<a href="<?php echo esc_url( $stream_url ); ?>" class="dash-btn-sm dash-btn-sm-outline" target="_blank">
															<?php esc_html_e( 'Download', 'fraud-tenant-theme' ); ?>
														</a>
													</td>
												</tr>
											<?php endforeach; ?>
										</tbody>
									</table>
								<?php endif; ?>

								<!-- Upload Additional Document Form -->
								<div class="doc-upload-box">
									<h4 style="margin:0 0 6px 0; color:var(--dash-navy); font-size:14px;">
										<?php esc_html_e( 'Upload Supporting Document', 'fraud-tenant-theme' ); ?>
									</h4>
									<p style="font-size:12px; color:var(--dash-muted); margin:0 0 12px 0;">
										<?php esc_html_e( 'Provide a recorded deed, recent property tax receipt, or settlement statement (PDF, PNG, JPG, max 10MB). Prior uploads are preserved.', 'fraud-tenant-theme' ); ?>
									</p>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
										<input type="hidden" name="action" value="ft_upload_property_doc" />
										<input type="hidden" name="property_id" value="<?php echo (int) $target_property->id; ?>" />
										<input type="hidden" name="redirect_to" value="<?php echo esc_url( home_url( '/owner-dashboard/properties/?property_id=' . $target_property->id ) ); ?>" />
										<?php wp_nonce_field( 'ft_upload_doc_' . $target_property->id, 'ft_doc_upload_nonce' ); ?>

										<div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
											<input type="file" name="ownership_document" accept=".pdf,.png,.jpg,.jpeg" required style="font-size:12px; flex:1;" />
											<button type="submit" class="dash-btn-sm dash-btn-sm-primary">
												<?php esc_html_e( 'Upload Document', 'fraud-tenant-theme' ); ?>
											</button>
										</div>
									</form>
								</div>
							</div>

							<!-- Safe Archival Box -->
							<div class="dash-panel" style="margin-top:20px;">
								<h3 class="dash-panel-title" style="margin-bottom:8px; font-size:15px;">
									<?php esc_html_e( 'Property Lifecycle & Archival', 'fraud-tenant-theme' ); ?>
								</h3>

								<?php if ( 'archived' === $target_property->status || ! empty( $target_property->archived_at ) ) : ?>
									<p style="font-size:13px; color:var(--dash-muted); margin:0 0 12px 0;">
										<?php esc_html_e( 'This property is currently archived and hidden from your active portfolio. You can restore it to the verification queue at any time.', 'fraud-tenant-theme' ); ?>
									</p>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="action" value="ft_unarchive_property" />
										<input type="hidden" name="property_id" value="<?php echo (int) $target_property->id; ?>" />
										<input type="hidden" name="redirect_to" value="<?php echo esc_url( home_url( '/owner-dashboard/properties/?property_id=' . $target_property->id ) ); ?>" />
										<?php wp_nonce_field( 'ft_unarchive_prop_' . $target_property->id, 'ft_unarchive_nonce' ); ?>
										<button type="submit" class="dash-btn-sm dash-btn-sm-primary">
											<?php esc_html_e( 'Restore to Active Properties', 'fraud-tenant-theme' ); ?>
										</button>
									</form>
								<?php else : ?>
									<p style="font-size:13px; color:var(--dash-muted); margin:0 0 12px 0;">
										<?php esc_html_e( 'Archiving removes this property from your active screening options without permanently deleting historic records, incident files, or audit logs.', 'fraud-tenant-theme' ); ?>
									</p>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Are you sure you want to archive this property? All associated documents and history will be safely preserved.', 'fraud-tenant-theme' ) ); ?>');">
										<input type="hidden" name="action" value="ft_archive_property" />
										<input type="hidden" name="property_id" value="<?php echo (int) $target_property->id; ?>" />
										<input type="hidden" name="redirect_to" value="<?php echo esc_url( home_url( '/owner-dashboard/properties/' ) ); ?>" />
										<?php wp_nonce_field( 'ft_archive_prop_' . $target_property->id, 'ft_archive_nonce' ); ?>
										<button type="submit" class="dash-btn-sm dash-btn-sm-danger">
											<?php esc_html_e( 'Archive This Property', 'fraud-tenant-theme' ); ?>
										</button>
									</form>
								<?php endif; ?>
							</div>

						</div>

						<!-- Right Column: Edit Property Details -->
						<div class="dash-grid-right">
							<div class="dash-panel">
								<h2 class="dash-panel-title" style="margin-bottom:14px;">
									<?php esc_html_e( 'Edit Property Details', 'fraud-tenant-theme' ); ?>
								</h2>

								<?php if ( 'verified' === $target_property->status ) : ?>
									<div style="background:#FFFBEB; border:1px solid #FCD34D; border-radius:6px; padding:10px 12px; font-size:12px; color:#92400E; margin-bottom:16px;">
										<strong><?php esc_html_e( 'Important Notice:', 'fraud-tenant-theme' ); ?></strong>
										<?php esc_html_e( 'Modifying compliance-critical address information (Street, City, State, or ZIP) will automatically reset this verified property back to pending review.', 'fraud-tenant-theme' ); ?>
									</div>
								<?php endif; ?>

								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="ft_edit_property" />
									<input type="hidden" name="property_id" value="<?php echo (int) $target_property->id; ?>" />
									<input type="hidden" name="redirect_to" value="<?php echo esc_url( home_url( '/owner-dashboard/properties/?property_id=' . $target_property->id ) ); ?>" />
									<?php wp_nonce_field( 'ft_edit_property_' . $target_property->id, 'ft_property_nonce' ); ?>

									<div style="margin-bottom:12px;">
										<label for="edit_prop_title" style="display:block; font-size:12px; font-weight:700; color:var(--dash-navy); margin-bottom:4px;">
											<?php esc_html_e( 'Property Name or Label', 'fraud-tenant-theme' ); ?>
										</label>
										<input type="text" id="edit_prop_title" name="title" value="<?php echo esc_attr( $target_property->title ); ?>" class="search-ssn-input" style="height:40px; background:#FFFFFF;" />
									</div>

									<div style="margin-bottom:12px;">
										<label for="edit_prop_street" style="display:block; font-size:12px; font-weight:700; color:var(--dash-navy); margin-bottom:4px;">
											<?php esc_html_e( 'Street Address *', 'fraud-tenant-theme' ); ?>
										</label>
										<input type="text" id="edit_prop_street" name="address_street" value="<?php echo esc_attr( $target_property->address_street ); ?>" class="search-ssn-input" style="height:40px; background:#FFFFFF;" required />
									</div>

									<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:12px;">
										<div>
											<label for="edit_prop_unit" style="display:block; font-size:12px; font-weight:700; color:var(--dash-navy); margin-bottom:4px;">
												<?php esc_html_e( 'Unit / Apt #', 'fraud-tenant-theme' ); ?>
											</label>
											<input type="text" id="edit_prop_unit" name="unit_number" value="<?php echo esc_attr( $target_property->unit_number ?? '' ); ?>" class="search-ssn-input" style="height:40px; background:#FFFFFF;" />
										</div>
										<div>
											<label for="edit_prop_city" style="display:block; font-size:12px; font-weight:700; color:var(--dash-navy); margin-bottom:4px;">
												<?php esc_html_e( 'City *', 'fraud-tenant-theme' ); ?>
											</label>
											<input type="text" id="edit_prop_city" name="city" value="<?php echo esc_attr( $target_property->city ); ?>" class="search-ssn-input" style="height:40px; background:#FFFFFF;" required />
										</div>
									</div>

									<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:12px;">
										<div>
											<label for="edit_prop_state" style="display:block; font-size:12px; font-weight:700; color:var(--dash-navy); margin-bottom:4px;">
												<?php esc_html_e( 'State (2-letter) *', 'fraud-tenant-theme' ); ?>
											</label>
											<input type="text" id="edit_prop_state" name="state" value="<?php echo esc_attr( $target_property->state ); ?>" class="search-ssn-input" style="height:40px; background:#FFFFFF; text-transform:uppercase;" maxlength="2" required />
										</div>
										<div>
											<label for="edit_prop_zip" style="display:block; font-size:12px; font-weight:700; color:var(--dash-navy); margin-bottom:4px;">
												<?php esc_html_e( 'ZIP Code *', 'fraud-tenant-theme' ); ?>
											</label>
											<input type="text" id="edit_prop_zip" name="postal_code" value="<?php echo esc_attr( $target_property->postal_code ); ?>" class="search-ssn-input" style="height:40px; background:#FFFFFF;" required />
										</div>
									</div>

									<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:16px;">
										<div>
											<label for="edit_prop_type" style="display:block; font-size:12px; font-weight:700; color:var(--dash-navy); margin-bottom:4px;">
												<?php esc_html_e( 'Property Type', 'fraud-tenant-theme' ); ?>
											</label>
											<select id="edit_prop_type" name="property_type" class="search-ssn-input" style="height:40px; background:#FFFFFF;">
												<?php foreach ( PropertyService::ALLOWED_PROPERTY_TYPES as $type_k => $type_label ) : ?>
													<option value="<?php echo esc_attr( $type_k ); ?>" <?php selected( $target_property->property_type, $type_k ); ?>>
														<?php echo esc_html( $type_label ); ?>
													</option>
												<?php endforeach; ?>
											</select>
										</div>
										<div>
											<label for="edit_prop_units" style="display:block; font-size:12px; font-weight:700; color:var(--dash-navy); margin-bottom:4px;">
												<?php esc_html_e( 'Units Count', 'fraud-tenant-theme' ); ?>
											</label>
											<input type="number" id="edit_prop_units" name="units_count" value="<?php echo (int) $target_property->units_count; ?>" min="1" class="search-ssn-input" style="height:40px; background:#FFFFFF;" required />
										</div>
									</div>

									<button type="submit" class="dashboard-btn-add" style="width:100%; justify-content:center;">
										<?php esc_html_e( 'Save Property Changes', 'fraud-tenant-theme' ); ?>
									</button>
								</form>
							</div>
						</div>

					</div>

				<?php endif; ?>

			<?php else : ?>

				<!-- Properties Management List View -->
				<div class="dashboard-welcome-banner">
					<div class="dashboard-welcome-text">
						<h1><?php esc_html_e( 'My Rental Properties', 'fraud-tenant-theme' ); ?></h1>
						<p><?php esc_html_e( 'Manage your portfolio addresses, monitor ownership verification, and upload compliance documents.', 'fraud-tenant-theme' ); ?></p>
					</div>
					<div>
						<button type="button" class="dashboard-btn-add" data-ft-modal-target="ftAddPropertyModal">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
							<span><?php esc_html_e( 'Add Property', 'fraud-tenant-theme' ); ?></span>
						</button>
					</div>
				</div>

				<!-- Sub-Navigation Filter Tabs -->
				<div class="dash-subtabs-nav">
					<a href="<?php echo esc_url( home_url( '/owner-dashboard/properties/' ) ); ?>" class="dash-subtab-link <?php echo 'archived' !== $prop_subview ? 'is-active' : ''; ?>">
						<?php printf( /* translators: %d: count */ esc_html__( 'Active Properties (%d)', 'fraud-tenant-theme' ), (int) $data['properties_count'] ); ?>
					</a>
					<a href="<?php echo esc_url( add_query_arg( 'subview', 'archived', home_url( '/owner-dashboard/properties/' ) ) ); ?>" class="dash-subtab-link <?php echo 'archived' === $prop_subview ? 'is-active' : ''; ?>">
						<?php printf( /* translators: %d: count */ esc_html__( 'Archived (%d)', 'fraud-tenant-theme' ), (int) $data['archived_properties_count'] ); ?>
					</a>
				</div>

				<?php if ( 'archived' === $prop_subview ) : ?>

					<!-- Archived Properties View -->
					<div class="dash-panel">
						<div class="dash-panel-header">
							<h2 class="dash-panel-title"><?php esc_html_e( 'Archived Properties', 'fraud-tenant-theme' ); ?></h2>
						</div>

						<?php if ( empty( $data['archived_properties'] ) ) : ?>
							<div class="dash-empty-state">
								<div class="dash-empty-icon">
									<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
								</div>
								<h4><?php esc_html_e( 'No archived properties', 'fraud-tenant-theme' ); ?></h4>
								<p><?php esc_html_e( 'Properties you archive will appear here safely preserved with their document history.', 'fraud-tenant-theme' ); ?></p>
							</div>
						<?php else : ?>
							<div class="dash-table-wrapper">
								<table class="dash-table">
									<thead>
										<tr>
											<th><?php esc_html_e( 'Property Address', 'fraud-tenant-theme' ); ?></th>
											<th><?php esc_html_e( 'City / State', 'fraud-tenant-theme' ); ?></th>
											<th><?php esc_html_e( 'Archived On', 'fraud-tenant-theme' ); ?></th>
											<th style="text-align:right;"><?php esc_html_e( 'Action', 'fraud-tenant-theme' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $data['archived_properties'] as $ap ) : ?>
											<tr>
												<td>
													<strong><?php echo esc_html( $ap->title ?: $ap->address_street ); ?></strong>
													<div style="font-size:12px; color:var(--dash-muted);">
														<?php echo esc_html( $ap->address_street . ( $ap->unit_number ? ' #' . $ap->unit_number : '' ) ); ?>
													</div>
												</td>
												<td>
													<?php echo esc_html( $ap->city . ', ' . $ap->state . ' ' . $ap->postal_code ); ?>
												</td>
												<td>
													<?php echo esc_html( ! empty( $ap->archived_at ) ? gmdate( 'M j, Y', strtotime( $ap->archived_at ) ) : '—' ); ?>
												</td>
												<td style="text-align:right;">
													<div class="dash-action-btn-group" style="justify-content:flex-end;">
														<a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'properties', 'property_id' => $ap->id ), home_url( '/owner-dashboard/' ) ) ); ?>" class="dash-btn-sm dash-btn-sm-outline">
															<?php esc_html_e( 'View', 'fraud-tenant-theme' ); ?>
														</a>
														<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
															<input type="hidden" name="action" value="ft_unarchive_property" />
															<input type="hidden" name="property_id" value="<?php echo (int) $ap->id; ?>" />
															<input type="hidden" name="redirect_to" value="<?php echo esc_url( home_url( '/owner-dashboard/properties/?subview=archived' ) ); ?>" />
															<?php wp_nonce_field( 'ft_unarchive_prop_' . $ap->id, 'ft_unarchive_nonce' ); ?>
															<button type="submit" class="dash-btn-sm dash-btn-sm-primary">
																<?php esc_html_e( 'Restore', 'fraud-tenant-theme' ); ?>
															</button>
														</form>
													</div>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						<?php endif; ?>
					</div>

				<?php else : ?>

					<!-- Active Properties View -->
					<div class="dash-panel">
						<div class="dash-panel-header">
							<h2 class="dash-panel-title"><?php esc_html_e( 'Active Properties', 'fraud-tenant-theme' ); ?></h2>
						</div>

						<?php if ( empty( $data['properties'] ) ) : ?>
							<div class="dash-empty-state">
								<div class="dash-empty-icon">
									<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
								</div>
								<h4><?php esc_html_e( 'No active properties registered', 'fraud-tenant-theme' ); ?></h4>
								<p><?php esc_html_e( 'Register your rental property to enable applicant screening and incident documentation.', 'fraud-tenant-theme' ); ?></p>
								<button type="button" class="dashboard-btn-add" data-ft-modal-target="ftAddPropertyModal" style="margin-top:14px;">
									<span><?php esc_html_e( '+ Add Property', 'fraud-tenant-theme' ); ?></span>
								</button>
							</div>
						<?php else : ?>
							<div class="dash-table-wrapper">
								<table class="dash-table">
									<thead>
										<tr>
											<th><?php esc_html_e( 'Property Address & Label', 'fraud-tenant-theme' ); ?></th>
											<th><?php esc_html_e( 'Type / Units', 'fraud-tenant-theme' ); ?></th>
											<th><?php esc_html_e( 'Documents', 'fraud-tenant-theme' ); ?></th>
											<th><?php esc_html_e( 'Verification Status', 'fraud-tenant-theme' ); ?></th>
											<th style="text-align:right;"><?php esc_html_e( 'Actions', 'fraud-tenant-theme' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $data['properties'] as $prop ) : ?>
											<?php
											$manage_url = add_query_arg(
												array(
													'tab'         => 'properties',
													'property_id' => $prop->id,
												),
												home_url( '/owner-dashboard/' )
											);
											?>
											<tr>
												<td>
													<strong>
														<a href="<?php echo esc_url( $manage_url ); ?>" style="color:var(--dash-navy); text-decoration:none;">
															<?php echo esc_html( $prop->title ?: $prop->address_street ); ?>
														</a>
													</strong>
													<div style="font-size:12px; color:var(--dash-muted); margin-top:2px;">
														<?php echo esc_html( $prop->address_street . ( $prop->unit_number ? ' #' . $prop->unit_number : '' ) . ', ' . $prop->city . ', ' . $prop->state . ' ' . $prop->postal_code ); ?>
													</div>
												</td>
												<td>
													<?php echo esc_html( ucwords( str_replace( '_', ' ', $prop->property_type ) ) ); ?><br>
													<span style="font-size:12px; color:var(--dash-muted);"><?php printf( /* translators: %d: count */ esc_html__( '%d Unit(s)', 'fraud-tenant-theme' ), (int) $prop->units_count ); ?></span>
												</td>
												<td>
													<strong><?php echo (int) $prop->document_count; ?></strong> <?php esc_html_e( 'file(s)', 'fraud-tenant-theme' ); ?>
													<?php if ( $prop->document_count > 0 && ! empty( $prop->latest_doc_status ) ) : ?>
														<div style="font-size:11px; margin-top:2px;">
															<?php if ( 'approved' === $prop->latest_doc_status ) : ?>
																<span style="color:#0E9488; font-weight:600;">&check; <?php esc_html_e( 'Approved', 'fraud-tenant-theme' ); ?></span>
															<?php elseif ( 'rejected' === $prop->latest_doc_status ) : ?>
																<span style="color:#E11D48; font-weight:600;">&times; <?php esc_html_e( 'Rejected', 'fraud-tenant-theme' ); ?></span>
															<?php else : ?>
																<span style="color:#D97706; font-weight:600;">&bull; <?php esc_html_e( 'Under Review', 'fraud-tenant-theme' ); ?></span>
															<?php endif; ?>
														</div>
													<?php endif; ?>
												</td>
												<td>
													<?php if ( 'verified' === $prop->status ) : ?>
														<span class="badge-dash badge-dash-verified"><?php esc_html_e( 'Verified Ownership', 'fraud-tenant-theme' ); ?></span>
													<?php elseif ( 'info_requested' === $prop->status ) : ?>
														<span class="badge-dash" style="background:#FEF3C7; color:#B45309;"><?php esc_html_e( 'Info Requested', 'fraud-tenant-theme' ); ?></span>
													<?php elseif ( 'rejected' === $prop->status ) : ?>
														<span class="badge-dash badge-dash-rejected"><?php esc_html_e( 'Verification Rejected', 'fraud-tenant-theme' ); ?></span>
													<?php else : ?>
														<span class="badge-dash badge-dash-pending"><?php esc_html_e( 'Pending Verification', 'fraud-tenant-theme' ); ?></span>
													<?php endif; ?>
												</td>
												<td style="text-align:right;">
													<div class="dash-action-btn-group" style="justify-content:flex-end;">
														<a href="<?php echo esc_url( $manage_url ); ?>" class="dash-btn-sm dash-btn-sm-primary">
															<?php esc_html_e( 'Manage & Docs', 'fraud-tenant-theme' ); ?> &rarr;
														</a>
														<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;" onsubmit="return confirm('<?php echo esc_js( __( 'Are you sure you want to archive this property?', 'fraud-tenant-theme' ) ); ?>');">
															<input type="hidden" name="action" value="ft_archive_property" />
															<input type="hidden" name="property_id" value="<?php echo (int) $prop->id; ?>" />
															<input type="hidden" name="redirect_to" value="<?php echo esc_url( home_url( '/owner-dashboard/properties/' ) ); ?>" />
															<?php wp_nonce_field( 'ft_archive_prop_' . $prop->id, 'ft_archive_nonce' ); ?>
															<button type="submit" class="dash-btn-sm dash-btn-sm-danger" title="<?php esc_attr_e( 'Safe archive', 'fraud-tenant-theme' ); ?>">
																<?php esc_html_e( 'Archive', 'fraud-tenant-theme' ); ?>
															</button>
														</form>
													</div>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						<?php endif; ?>
					</div>

				<?php endif; ?>

			<?php endif; ?>

		<?php else : ?>

			<!-- ============================================================ -->
			<!-- VIEW: DASHBOARD OVERVIEW (SHOWCASE MATCH)                    -->
			<!-- ============================================================ -->

			<!-- Welcome Header Banner (Showcase Match) -->
			<div class="dashboard-welcome-banner">
				<div class="dashboard-welcome-text">
					<h1><?php printf( /* translators: %s: First name */ esc_html__( 'Welcome back, %s!', 'fraud-tenant-theme' ), esc_html( $first_name ) ); ?></h1>
					<p><?php esc_html_e( "Here's an overview of your account.", 'fraud-tenant-theme' ); ?></p>
				</div>
				<div>
					<button type="button" class="dashboard-btn-add" data-ft-modal-target="ftAddPropertyModal">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
						<span><?php esc_html_e( 'Add Property', 'fraud-tenant-theme' ); ?></span>
					</button>
				</div>
			</div>

			<!-- 4-Column Stat Metrics Row (Showcase Match) -->
			<div class="dashboard-metrics-row metrics-4col">
				<!-- Metric 1: My Properties -->
				<div class="metric-card">
					<div class="metric-data-side">
						<span class="metric-label"><?php esc_html_e( 'My Properties', 'fraud-tenant-theme' ); ?></span>
						<span class="metric-value"><?php echo (int) $data['properties_count']; ?></span>
						<span class="metric-tag">
							<?php
							if ( $data['verified_properties_count'] > 0 ) {
								printf( /* translators: %d: count */ esc_html__( '%d Verified', 'fraud-tenant-theme' ), (int) $data['verified_properties_count'] );
							} elseif ( $data['pending_properties_count'] > 0 ) {
								esc_html_e( 'Pending Property Verification', 'fraud-tenant-theme' );
							} else {
								esc_html_e( 'No Properties', 'fraud-tenant-theme' );
							}
							?>
						</span>
					</div>
					<div class="metric-icon-circle metric-icon-teal">
						<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
					</div>
				</div>

				<!-- Metric 2: Tenant Searches -->
				<div class="metric-card">
					<div class="metric-data-side">
						<span class="metric-label"><?php esc_html_e( 'Tenant Searches', 'fraud-tenant-theme' ); ?></span>
						<span class="metric-value"><?php echo (int) $data['searches_count']; ?></span>
						<span class="metric-tag" style="color:#0284C7;"><?php esc_html_e( 'Authorized Screening', 'fraud-tenant-theme' ); ?></span>
					</div>
					<div class="metric-icon-circle metric-icon-blue">
						<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
					</div>
				</div>

				<!-- Metric 3: My Reports -->
				<div class="metric-card">
					<div class="metric-data-side">
						<span class="metric-label"><?php esc_html_e( 'My Reports', 'fraud-tenant-theme' ); ?></span>
						<span class="metric-value"><?php echo (int) $data['reports_count']; ?></span>
						<span class="metric-tag" style="color:#D97706;"><?php esc_html_e( 'Incident Records', 'fraud-tenant-theme' ); ?></span>
					</div>
					<div class="metric-icon-circle metric-icon-amber">
						<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
					</div>
				</div>

				<!-- Metric 4: Membership Tier -->
				<div class="metric-card">
					<div class="metric-data-side">
						<span class="metric-label"><?php esc_html_e( 'Membership', 'fraud-tenant-theme' ); ?></span>
						<span class="metric-value" style="font-size: 1.45rem; padding-top: 4px;"><?php echo esc_html( $data['membership_plan_name'] ); ?></span>
						<a href="#membership" class="metric-upgrade-pill" data-ft-modal-target="ftMembershipModal"><?php esc_html_e( 'Upgrade', 'fraud-tenant-theme' ); ?></a>
					</div>
					<div class="metric-icon-circle metric-icon-gold">
						<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 4l3 12h14l3-12-6 7-4-7-4 7-6-7zm3 16h14"></path></svg>
					</div>
				</div>
			</div>

			<!-- Search Tenant Rental History Box (Showcase Match) -->
			<div class="search-tenants-card" id="search-tenants">
				<div class="search-tenants-header">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#14263E" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
					<h3><?php esc_html_e( 'Search Tenant Rental History', 'fraud-tenant-theme' ); ?></h3>
				</div>
				<p class="search-tenants-subtext"><?php esc_html_e( "Enter the applicant's Social Security Number (SSN) to find matching rental history (limited view for free users).", 'fraud-tenant-theme' ); ?></p>
				
				<div class="search-ssn-form" id="ftSsnSearchContainer">
					<div class="search-ssn-input-group">
						<div class="search-ssn-field-wrapper">
							<input type="text" class="search-ssn-input" id="ftSsnSearchInput" placeholder="XXX-XX-XXXX (Preview — Screening Engine in Phase 7)" disabled readonly aria-disabled="true" />
							<span class="search-ssn-lock">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
							</span>
						</div>
						<button type="button" class="search-ssn-btn" data-ft-modal-target="ftScreeningModal"><?php esc_html_e( 'Search Preview', 'fraud-tenant-theme' ); ?></button>
					</div>
					<p class="search-compliance-note">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
						<?php esc_html_e( 'Informational preview only. Authorized tenant screening will be available in Phase 7 with verified FCRA permissible purpose.', 'fraud-tenant-theme' ); ?>
					</p>
				</div>
			</div>

			<!-- Two Column Content Grid -->
			<div class="dashboard-grid-2col">
				
				<!-- Left Column (Primary Content) -->
				<div class="dash-grid-left">

					<!-- My Registered Properties Panel -->
					<div class="dash-panel" id="my-properties">
						<div class="dash-panel-header">
							<h2 class="dash-panel-title"><?php esc_html_e( 'My Properties', 'fraud-tenant-theme' ); ?></h2>
							<div style="display:flex; gap:12px; align-items:center;">
								<a href="<?php echo esc_url( home_url( '/owner-dashboard/properties/' ) ); ?>" class="dash-panel-link" style="text-decoration:none;">
									<?php esc_html_e( 'Manage All Properties →', 'fraud-tenant-theme' ); ?>
								</a>
								<button type="button" class="dashboard-btn-add" data-ft-modal-target="ftAddPropertyModal" style="padding:4px 10px; font-size:12px;">
									<?php esc_html_e( '+ Add', 'fraud-tenant-theme' ); ?>
								</button>
							</div>
						</div>

						<?php if ( empty( $data['properties'] ) ) : ?>
							<div class="dash-empty-state">
								<div class="dash-empty-icon">
									<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
								</div>
								<h4><?php esc_html_e( 'No properties registered yet', 'fraud-tenant-theme' ); ?></h4>
								<p><?php esc_html_e( 'Add your primary rental property to enable tenant screening and compliance tracking.', 'fraud-tenant-theme' ); ?></p>
							</div>
						<?php else : ?>
							<div class="dash-table-wrapper">
								<table class="dash-table">
									<thead>
										<tr>
											<th><?php esc_html_e( 'Property Address', 'fraud-tenant-theme' ); ?></th>
											<th><?php esc_html_e( 'City / State', 'fraud-tenant-theme' ); ?></th>
											<th><?php esc_html_e( 'Ownership Status', 'fraud-tenant-theme' ); ?></th>
											<th style="text-align:right;"><?php esc_html_e( 'Action', 'fraud-tenant-theme' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $data['properties'] as $prop ) : ?>
											<?php
											$manage_url = add_query_arg(
												array(
													'tab'         => 'properties',
													'property_id' => $prop->id,
												),
												home_url( '/owner-dashboard/' )
											);
											?>
											<tr>
												<td>
													<strong>
														<a href="<?php echo esc_url( $manage_url ); ?>" style="color:var(--dash-navy); text-decoration:none;">
															<?php echo esc_html( $prop->address_street . ( $prop->unit_number ? ' #' . $prop->unit_number : '' ) ); ?>
														</a>
													</strong>
													<div style="font-size:12px; color:var(--dash-muted); margin-top:2px;">
														<?php echo esc_html( ucwords( str_replace( '_', ' ', $prop->property_type ) ) ); ?> &bull; <?php printf( /* translators: %d: count */ esc_html__( '%d Unit(s)', 'fraud-tenant-theme' ), (int) $prop->units_count ); ?>
													</div>
												</td>
												<td>
													<?php echo esc_html( $prop->city . ', ' . $prop->state . ' ' . $prop->postal_code ); ?>
												</td>
												<td>
													<?php if ( 'verified' === $prop->status ) : ?>
														<span class="badge-dash badge-dash-verified"><?php esc_html_e( 'Verified Ownership', 'fraud-tenant-theme' ); ?></span>
													<?php elseif ( 'info_requested' === $prop->status ) : ?>
														<span class="badge-dash" style="background:#FEF3C7; color:#B45309;"><?php esc_html_e( 'Info Requested', 'fraud-tenant-theme' ); ?></span>
													<?php elseif ( 'rejected' === $prop->status ) : ?>
														<span class="badge-dash badge-dash-rejected"><?php esc_html_e( 'Verification Rejected', 'fraud-tenant-theme' ); ?></span>
													<?php else : ?>
														<span class="badge-dash badge-dash-pending"><?php esc_html_e( 'Pending Verification', 'fraud-tenant-theme' ); ?></span>
													<?php endif; ?>
												</td>
												<td style="text-align:right;">
													<a href="<?php echo esc_url( $manage_url ); ?>" class="dash-btn-sm dash-btn-sm-outline">
														<?php esc_html_e( 'Manage', 'fraud-tenant-theme' ); ?> &rarr;
													</a>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
							<div style="margin-top:16px; padding:12px 14px; background:#F8FAFC; border-radius:8px; border:1px solid #E2E8F0; font-size:12px; color:var(--dash-muted);">
								<strong style="color:var(--dash-navy);"><?php esc_html_e( 'Property Verification Notice:', 'fraud-tenant-theme' ); ?></strong>
								<?php esc_html_e( 'Account approval grants platform access, while individual property ownership is verified separately through compliance inspection of submitted deed and tax records.', 'fraud-tenant-theme' ); ?>
							</div>
						<?php endif; ?>
					</div>

					<!-- Recent Tenant Searches Panel (Showcase Match) -->
					<div class="dash-panel" id="recent-searches">
						<div class="dash-panel-header">
							<h2 class="dash-panel-title"><?php esc_html_e( 'Recent Searches', 'fraud-tenant-theme' ); ?></h2>
						</div>

						<?php if ( empty( $data['recent_searches'] ) ) : ?>
							<div class="dash-empty-state">
								<div class="dash-empty-icon">
									<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
								</div>
								<h4><?php esc_html_e( 'No recent tenant searches', 'fraud-tenant-theme' ); ?></h4>
								<p><?php esc_html_e( 'Authorized tenant screenings will appear here once executed with permissible purpose.', 'fraud-tenant-theme' ); ?></p>
							</div>
						<?php else : ?>
							<div class="dash-table-wrapper">
								<table class="dash-table">
									<thead>
										<tr>
											<th><?php esc_html_e( 'SSN (Last 4)', 'fraud-tenant-theme' ); ?></th>
											<th><?php esc_html_e( 'Search Date', 'fraud-tenant-theme' ); ?></th>
											<th><?php esc_html_e( 'Status', 'fraud-tenant-theme' ); ?></th>
											<th><?php esc_html_e( 'Action', 'fraud-tenant-theme' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $data['recent_searches'] as $search ) : ?>
											<tr>
												<td>&bull;&bull;&bull;&bull;-<?php echo esc_html( $search->ssn_last_four ); ?></td>
												<td><?php echo esc_html( gmdate( 'M j, Y', strtotime( $search->search_date ) ) ); ?></td>
												<td>
													<?php if ( $search->match_found ) : ?>
														<span style="color:#0E9488; font-weight:600;"><?php esc_html_e( 'Match Found', 'fraud-tenant-theme' ); ?></span>
													<?php else : ?>
														<span style="color:var(--dash-muted);"><?php esc_html_e( 'No Match', 'fraud-tenant-theme' ); ?></span>
													<?php endif; ?>
												</td>
												<td>
													<span style="color:var(--dash-muted); font-size:12px;"><?php esc_html_e( 'View (Locked)', 'fraud-tenant-theme' ); ?></span>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						<?php endif; ?>
					</div>

				</div>

				<!-- Right Column (Promo & Profile) -->
				<div class="dash-grid-right">

					<!-- Upgrade Your Membership Card (Showcase Match) -->
					<div class="dash-promo-card" id="membership">
						<div class="dash-promo-badge">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M2 4l3 12h14l3-12-6 7-4-7-4 7-6-7zm3 16h14"></path></svg>
							<span><?php esc_html_e( 'Premium Landlord Features', 'fraud-tenant-theme' ); ?></span>
						</div>
						<h3><?php esc_html_e( 'Upgrade Your Membership', 'fraud-tenant-theme' ); ?></h3>
						<p><?php esc_html_e( 'Unlock full report details, submit verified incidents, and access advanced screening tools.', 'fraud-tenant-theme' ); ?></p>
						
						<ul class="dash-promo-list">
							<li>
								<span class="dash-promo-check">&check;</span>
								<span><?php esc_html_e( 'View full tenant screening reports', 'fraud-tenant-theme' ); ?></span>
							</li>
							<li>
								<span class="dash-promo-check">&check;</span>
								<span><?php esc_html_e( 'Submit verified incident records', 'fraud-tenant-theme' ); ?></span>
							</li>
							<li>
								<span class="dash-promo-check">&check;</span>
								<span><?php esc_html_e( 'Manage multiple rental properties', 'fraud-tenant-theme' ); ?></span>
							</li>
						</ul>

						<button type="button" class="dash-promo-btn" data-ft-modal-target="ftMembershipModal">
							<span><?php esc_html_e( 'View Membership Plans', 'fraud-tenant-theme' ); ?></span>
							<span>&rarr;</span>
						</button>
					</div>

					<!-- Account Compliance & Profile Card -->
					<div class="dash-panel" style="margin-top:24px;">
						<div class="dash-panel-header">
							<h3 class="dash-panel-title"><?php esc_html_e( 'Account Details', 'fraud-tenant-theme' ); ?></h3>
							<button type="button" class="dash-panel-link" data-ft-modal-target="ftProfileModal" style="background:none;border:none;cursor:pointer;">
								<?php esc_html_e( 'Edit Phone', 'fraud-tenant-theme' ); ?>
							</button>
						</div>

						<div class="dash-detail-list">
							<div class="dash-detail-row">
								<span class="dash-detail-key"><?php esc_html_e( 'Legal Name', 'fraud-tenant-theme' ); ?></span>
								<span class="dash-detail-val"><?php echo esc_html( $current_user->display_name ); ?></span>
							</div>
							<div class="dash-detail-row">
								<span class="dash-detail-key"><?php esc_html_e( 'Email Address', 'fraud-tenant-theme' ); ?></span>
								<span class="dash-detail-val"><?php echo esc_html( $current_user->user_email ); ?></span>
							</div>
							<div class="dash-detail-row">
								<span class="dash-detail-key"><?php esc_html_e( 'Phone', 'fraud-tenant-theme' ); ?></span>
								<span class="dash-detail-val"><?php echo esc_html( $user_profile?->phone ?? '—' ); ?></span>
							</div>
							<div class="dash-detail-row">
								<span class="dash-detail-key"><?php esc_html_e( 'Account Status', 'fraud-tenant-theme' ); ?></span>
								<span class="dash-detail-val" style="color:#0E9488; font-weight:700;">
									&check; <?php esc_html_e( 'Verified & Approved', 'fraud-tenant-theme' ); ?>
								</span>
							</div>
						</div>
					</div>

				</div>
			</div>

		<?php endif; ?>

	</main>
</div>

<!-- ============================================================ -->
<!-- MODALS -->
<!-- ============================================================ -->

<!-- Modal 1: Add Rental Property Modal (Fully Functional Multi-Property Form) -->
<div class="ft-modal-overlay" id="ftAddPropertyModal" role="dialog" aria-modal="true" aria-labelledby="ftAddPropTitle">
	<div class="ft-modal-box" style="max-width: 620px;">
		<button type="button" class="ft-modal-close" aria-label="<?php esc_attr_e( 'Close', 'fraud-tenant-theme' ); ?>">&times;</button>
		<h3 class="ft-modal-title" id="ftAddPropTitle"><?php esc_html_e( 'Add Rental Property', 'fraud-tenant-theme' ); ?></h3>
		<p class="ft-modal-desc">
			<?php esc_html_e( 'Register a new property to enable tenant screening and incident documentation. Upload a recorded deed, tax record, or settlement statement to request ownership verification.', 'fraud-tenant-theme' ); ?>
		</p>
		
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
			<input type="hidden" name="action" value="ft_add_property" />
			<input type="hidden" name="redirect_to" value="<?php echo esc_url( home_url( '/owner-dashboard/properties/' ) ); ?>" />
			<?php wp_nonce_field( 'ft_add_property_action', 'ft_property_nonce' ); ?>

			<div style="margin-bottom:14px;">
				<label for="prop_add_title" style="display:block; font-size:12px; font-weight:700; color:var(--dash-navy); margin-bottom:4px;">
					<?php esc_html_e( 'Property Name or Label (Optional)', 'fraud-tenant-theme' ); ?>
				</label>
				<input type="text" id="prop_add_title" name="title" class="search-ssn-input" style="height:42px; background:#FFFFFF;" placeholder="<?php esc_attr_e( 'e.g. Sunset Apartments, Duplex Unit A/B', 'fraud-tenant-theme' ); ?>" />
			</div>

			<div style="display:grid; grid-template-columns: 2fr 1fr; gap:12px; margin-bottom:14px;">
				<div>
					<label for="prop_add_street" style="display:block; font-size:12px; font-weight:700; color:var(--dash-navy); margin-bottom:4px;">
						<?php esc_html_e( 'Street Address *', 'fraud-tenant-theme' ); ?>
					</label>
					<input type="text" id="prop_add_street" name="address_street" class="search-ssn-input" style="height:42px; background:#FFFFFF;" placeholder="123 Main Street" required />
				</div>
				<div>
					<label for="prop_add_unit" style="display:block; font-size:12px; font-weight:700; color:var(--dash-navy); margin-bottom:4px;">
						<?php esc_html_e( 'Unit / Apt #', 'fraud-tenant-theme' ); ?>
					</label>
					<input type="text" id="prop_add_unit" name="unit_number" class="search-ssn-input" style="height:42px; background:#FFFFFF;" placeholder="Apt 4B" />
				</div>
			</div>

			<div style="display:grid; grid-template-columns: 2fr 1fr 1fr; gap:12px; margin-bottom:14px;">
				<div>
					<label for="prop_add_city" style="display:block; font-size:12px; font-weight:700; color:var(--dash-navy); margin-bottom:4px;">
						<?php esc_html_e( 'City *', 'fraud-tenant-theme' ); ?>
					</label>
					<input type="text" id="prop_add_city" name="city" class="search-ssn-input" style="height:42px; background:#FFFFFF;" placeholder="Austin" required />
				</div>
				<div>
					<label for="prop_add_state" style="display:block; font-size:12px; font-weight:700; color:var(--dash-navy); margin-bottom:4px;">
						<?php esc_html_e( 'State (2-letter) *', 'fraud-tenant-theme' ); ?>
					</label>
					<input type="text" id="prop_add_state" name="state" class="search-ssn-input" style="height:42px; background:#FFFFFF; text-transform:uppercase;" placeholder="TX" maxlength="2" required />
				</div>
				<div>
					<label for="prop_add_zip" style="display:block; font-size:12px; font-weight:700; color:var(--dash-navy); margin-bottom:4px;">
						<?php esc_html_e( 'ZIP Code *', 'fraud-tenant-theme' ); ?>
					</label>
					<input type="text" id="prop_add_zip" name="postal_code" class="search-ssn-input" style="height:42px; background:#FFFFFF;" placeholder="78701" required />
				</div>
			</div>

			<div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:14px;">
				<div>
					<label for="prop_add_type" style="display:block; font-size:12px; font-weight:700; color:var(--dash-navy); margin-bottom:4px;">
						<?php esc_html_e( 'Property Type', 'fraud-tenant-theme' ); ?>
					</label>
					<select id="prop_add_type" name="property_type" class="search-ssn-input" style="height:42px; background:#FFFFFF;">
						<option value="single_family"><?php esc_html_e( 'Single Family', 'fraud-tenant-theme' ); ?></option>
						<option value="multi_family"><?php esc_html_e( 'Multi-Family', 'fraud-tenant-theme' ); ?></option>
						<option value="apartment"><?php esc_html_e( 'Apartment', 'fraud-tenant-theme' ); ?></option>
						<option value="condo"><?php esc_html_e( 'Condominium', 'fraud-tenant-theme' ); ?></option>
						<option value="townhouse"><?php esc_html_e( 'Townhouse', 'fraud-tenant-theme' ); ?></option>
						<option value="commercial"><?php esc_html_e( 'Commercial', 'fraud-tenant-theme' ); ?></option>
						<option value="other"><?php esc_html_e( 'Other Residential', 'fraud-tenant-theme' ); ?></option>
					</select>
				</div>
				<div>
					<label for="prop_add_units" style="display:block; font-size:12px; font-weight:700; color:var(--dash-navy); margin-bottom:4px;">
						<?php esc_html_e( 'Number of Units', 'fraud-tenant-theme' ); ?>
					</label>
					<input type="number" id="prop_add_units" name="units_count" class="search-ssn-input" style="height:42px; background:#FFFFFF;" min="1" value="1" required />
				</div>
			</div>

			<div style="margin-bottom:18px;">
				<label for="prop_add_doc" style="display:block; font-size:12px; font-weight:700; color:var(--dash-navy); margin-bottom:4px;">
					<?php esc_html_e( 'Ownership Verification Document (Optional)', 'fraud-tenant-theme' ); ?>
				</label>
				<input type="file" id="prop_add_doc" name="property_document" accept=".pdf,.png,.jpg,.jpeg" style="display:block; width:100%; font-size:13px; color:var(--dash-muted); padding:8px 0;" />
				<span style="font-size:11px; color:var(--dash-muted);">
					<?php esc_html_e( 'Accepted formats: PDF, PNG, JPG (Max 10 MB). Stored privately in protected storage.', 'fraud-tenant-theme' ); ?>
				</span>
			</div>

			<div style="display:flex; gap:12px;">
				<button type="button" class="dashboard-btn-add" data-ft-modal-close style="flex:1; justify-content:center; background:#F1F5F9; color:var(--dash-navy); border:1px solid #CBD5E1;">
					<?php esc_html_e( 'Cancel', 'fraud-tenant-theme' ); ?>
				</button>
				<button type="submit" class="dashboard-btn-add" style="flex:2; justify-content:center;">
					<?php esc_html_e( 'Add Property & Submit', 'fraud-tenant-theme' ); ?>
				</button>
			</div>
		</form>
	</div>
</div>

<!-- Modal 2: Tenant Screening Compliance (Phase 7 Workflow) -->
<div class="ft-modal-overlay" id="ftScreeningModal" role="dialog" aria-modal="true" aria-labelledby="ftScreeningTitle">
	<div class="ft-modal-box">
		<button type="button" class="ft-modal-close" aria-label="<?php esc_attr_e( 'Close', 'fraud-tenant-theme' ); ?>">&times;</button>
		<h3 class="ft-modal-title" id="ftScreeningTitle"><?php esc_html_e( 'Tenant Screening Notice', 'fraud-tenant-theme' ); ?></h3>
		<p class="ft-modal-desc">
			<?php esc_html_e( 'Tenant screening queries are governed by the Fair Credit Reporting Act (FCRA). The interactive screening engine with permissible purpose certification will be activated in Phase 7.', 'fraud-tenant-theme' ); ?>
		</p>
		<div style="background:#FFFBEB; border:1px solid #FCD34D; padding:14px; border-radius:8px; font-size:13px; color:#92400E; margin-bottom:18px;">
			<strong style="display:block; margin-bottom:4px;"><?php esc_html_e( 'Permissible Purpose Requirement:', 'fraud-tenant-theme' ); ?></strong>
			<?php esc_html_e( 'Screening may only be conducted with written rental application consent from the prospective applicant for legitimate tenant evaluation.', 'fraud-tenant-theme' ); ?>
		</div>
		<button type="button" class="dashboard-btn-add" data-ft-modal-close style="width:100%; justify-content:center;">
			<?php esc_html_e( 'Close Notice', 'fraud-tenant-theme' ); ?>
		</button>
	</div>
</div>

<!-- Modal 3: Membership Plans (Phase 9 Stripe Pricing) -->
<div class="ft-modal-overlay" id="ftMembershipModal" role="dialog" aria-modal="true" aria-labelledby="ftMemTitle">
	<div class="ft-modal-box" style="max-width: 620px;">
		<button type="button" class="ft-modal-close" aria-label="<?php esc_attr_e( 'Close', 'fraud-tenant-theme' ); ?>">&times;</button>
		<h3 class="ft-modal-title" id="ftMemTitle"><?php esc_html_e( 'Membership Plans & Pricing', 'fraud-tenant-theme' ); ?></h3>
		<p class="ft-modal-desc">
			<?php esc_html_e( 'Select the membership plan that best suits your rental portfolio. Direct Stripe payment checkout will be activated in Phase 9.', 'fraud-tenant-theme' ); ?>
		</p>
		<div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:20px;">
			<div style="border:1.5px solid #E2E8F0; border-radius:10px; padding:18px; text-align:center;">
				<h4 style="margin:0 0 6px 0; color:var(--dash-navy); font-size:16px;"><?php esc_html_e( 'Standard Monthly', 'fraud-tenant-theme' ); ?></h4>
				<div style="font-size:26px; font-weight:800; color:var(--dash-navy);">$29 <span style="font-size:13px; font-weight:500; color:var(--dash-muted);">/ month</span></div>
				<ul style="text-align:left; font-size:12px; color:var(--dash-muted); margin:12px 0 0 0; padding-left:18px; line-height:1.6;">
					<li><?php esc_html_e( 'Full screening reports', 'fraud-tenant-theme' ); ?></li>
					<li><?php esc_html_e( 'Incident reporting access', 'fraud-tenant-theme' ); ?></li>
					<li><?php esc_html_e( 'Cancel anytime', 'fraud-tenant-theme' ); ?></li>
				</ul>
			</div>
			<div style="border:2px solid var(--dash-teal); border-radius:10px; padding:18px; text-align:center; background:#F0FDFA; position:relative;">
				<div style="position:absolute; top:-10px; right:14px; background:var(--dash-teal); color:#FFFFFF; font-size:10px; font-weight:700; padding:2px 8px; border-radius:10px;">
					<?php esc_html_e( 'BEST VALUE', 'fraud-tenant-theme' ); ?>
				</div>
				<h4 style="margin:0 0 6px 0; color:var(--dash-navy); font-size:16px;"><?php esc_html_e( 'Standard Annual', 'fraud-tenant-theme' ); ?></h4>
				<div style="font-size:26px; font-weight:800; color:var(--dash-navy);">$290 <span style="font-size:13px; font-weight:500; color:var(--dash-muted);">/ year</span></div>
				<ul style="text-align:left; font-size:12px; color:var(--dash-muted); margin:12px 0 0 0; padding-left:18px; line-height:1.6;">
					<li><?php esc_html_e( '2 Months Free Included', 'fraud-tenant-theme' ); ?></li>
					<li><?php esc_html_e( 'Full screening reports', 'fraud-tenant-theme' ); ?></li>
					<li><?php esc_html_e( 'Priority compliance review', 'fraud-tenant-theme' ); ?></li>
				</ul>
			</div>
		</div>
		<button type="button" class="dashboard-btn-add" data-ft-modal-close style="width:100%; justify-content:center;">
			<?php esc_html_e( 'Close Preview', 'fraud-tenant-theme' ); ?>
		</button>
	</div>
</div>

<!-- Modal 4: Edit Profile Settings (Safe Phone Update) -->
<div class="ft-modal-overlay" id="ftProfileModal" role="dialog" aria-modal="true" aria-labelledby="ftProfTitle">
	<div class="ft-modal-box">
		<button type="button" class="ft-modal-close" aria-label="<?php esc_attr_e( 'Close', 'fraud-tenant-theme' ); ?>">&times;</button>
		<h3 class="ft-modal-title" id="ftProfTitle"><?php esc_html_e( 'Update Profile Contact', 'fraud-tenant-theme' ); ?></h3>
		<p class="ft-modal-desc">
			<?php esc_html_e( 'Update your verified phone number. Email address changes require identity compliance verification.', 'fraud-tenant-theme' ); ?>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ft_update_profile" />
			<input type="hidden" name="redirect_to" value="<?php echo esc_url( home_url( '/owner-dashboard/' ) ); ?>" />
			<?php wp_nonce_field( 'ft_update_profile_' . $owner_id, 'ft_profile_nonce' ); ?>

			<div style="margin-bottom:16px;">
				<label for="owner_phone_edit" style="display:block; font-size:12px; font-weight:600; color:var(--dash-navy); margin-bottom:6px;">
					<?php esc_html_e( 'Phone Number', 'fraud-tenant-theme' ); ?>
				</label>
				<input type="tel" id="owner_phone_edit" name="phone" value="<?php echo esc_attr( $user_profile?->phone ?? '' ); ?>" class="search-ssn-input" style="height:42px; background:#FFFFFF;" placeholder="(555) 000-0000" required />
			</div>

			<button type="submit" class="dashboard-btn-add" style="width:100%; justify-content:center;">
				<?php esc_html_e( 'Save Phone Number', 'fraud-tenant-theme' ); ?>
			</button>
		</form>
	</div>
</div>

<!-- Modal 5: Notifications Modal -->
<div class="ft-modal-overlay" id="ftNotificationsModal" role="dialog" aria-modal="true" aria-labelledby="ftNotifTitle">
	<div class="ft-modal-box">
		<button type="button" class="ft-modal-close" aria-label="<?php esc_attr_e( 'Close', 'fraud-tenant-theme' ); ?>">&times;</button>
		<h3 class="ft-modal-title" id="ftNotifTitle"><?php esc_html_e( 'Notifications', 'fraud-tenant-theme' ); ?></h3>
		<div style="padding:20px 0; text-align:center; color:var(--dash-muted); font-size:14px;">
			<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#CBD5E1" stroke-width="2" style="margin-bottom:8px;"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
			<p style="margin:0;"><?php esc_html_e( 'Your account has no unread alerts. You are up to date.', 'fraud-tenant-theme' ); ?></p>
		</div>
		<button type="button" class="dashboard-btn-add" data-ft-modal-close style="width:100%; justify-content:center;">
			<?php esc_html_e( 'Close', 'fraud-tenant-theme' ); ?>
		</button>
	</div>
</div>

<?php
get_footer();
