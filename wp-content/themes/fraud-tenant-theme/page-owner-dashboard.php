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
use FraudTenant\Services\IncidentService;
use FraudTenant\Services\PropertyService;
use FraudTenant\Utils\Constants;

// 1. Enforce strict access control, role check, and lifecycle state
$current_user = DashboardService::enforce_access( Constants::ROLE_OWNER );
$owner_id     = $current_user->ID;

// 2. Resolve sub-views and routes
$current_tab  = sanitize_key( get_query_var( 'ft_tab', $_GET['tab'] ?? 'overview' ) );
$property_id  = (int) get_query_var( 'ft_property_id', $_GET['property_id'] ?? 0 );
$prop_subview = sanitize_key( $_GET['subview'] ?? 'active' );
$checkout_status     = sanitize_key( $_GET['checkout_status'] ?? '' );
$checkout_session_id = sanitize_text_field( $_GET['session_id'] ?? '' );

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

		<?php
		// Server-side verification of the checkout return; the URL alone never activates anything.
		$checkout_verified_active = false;
		if ( 'success' === $checkout_status && preg_match( '/^cs_[A-Za-z0-9_]+$/', $checkout_session_id ) ) {
			$ft_ret                   = \FraudTenant\Services\CheckoutService::verify_checkout_return( $owner_id, $checkout_session_id );
			$checkout_verified_active = ! empty( $ft_ret['success'] ) && 'active' === ( $ft_ret['status'] ?? '' );
		}
		?>
		<?php if ( 'success' === $checkout_status && $checkout_verified_active ) : ?>
			<div class="auth-alert auth-alert-success ft-checkout-banner-success" role="alert" style="margin-bottom: 24px;">
				<strong style="display:block; font-size:14px; margin-bottom:2px;"><?php esc_html_e( 'Membership Active', 'fraud-tenant-theme' ); ?></strong>
				<span style="font-size:13px;"><?php esc_html_e( 'Your payment and subscription were verified with Stripe. Standard features are now available.', 'fraud-tenant-theme' ); ?></span>
			</div>
		<?php elseif ( 'success' === $checkout_status ) : ?>
			<div class="auth-alert auth-alert-success ft-checkout-banner-success" role="alert" style="margin-bottom: 24px; display:flex; align-items:flex-start; gap:12px;">
				<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0; margin-top:2px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
				<div>
					<strong style="display:block; font-size:14px; margin-bottom:2px;"><?php esc_html_e( 'Payment Submitted — Pending Verification', 'fraud-tenant-theme' ); ?></strong>
					<span style="font-size:13px;"><?php esc_html_e( 'Payment submitted. Membership activation is pending server-side payment and subscription verification.', 'fraud-tenant-theme' ); ?></span>
				</div>
			</div>
		<?php elseif ( 'cancelled' === $checkout_status ) : ?>
			<div class="auth-alert ft-checkout-banner-cancelled" role="alert" style="margin-bottom: 24px; background:#FFFBEB; border:1px solid #F59E0B; color:#B45309; display:flex; align-items:flex-start; gap:12px;">
				<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0; margin-top:2px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
				<div>
					<strong style="display:block; font-size:14px; margin-bottom:2px;"><?php esc_html_e( 'Checkout Cancelled', 'fraud-tenant-theme' ); ?></strong>
					<span style="font-size:13px;"><?php esc_html_e( 'Checkout was cancelled. Your current membership plan remains unchanged.', 'fraud-tenant-theme' ); ?></span>
				</div>
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
						<a href="#membership" class="metric-upgrade-pill" data-ft-modal-target="ftMembershipModal"><?php echo \FraudTenant\Services\MembershipService::has_active_subscription( $owner_id ) ? esc_html__( 'Manage', 'fraud-tenant-theme' ) : esc_html__( 'Upgrade', 'fraud-tenant-theme' ); ?></a>
					</div>
					<div class="metric-icon-circle metric-icon-gold">
						<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 4l3 12h14l3-12-6 7-4-7-4 7-6-7zm3 16h14"></path></svg>
					</div>
				</div>
			</div>

			<!-- Identity-matched tenant screening -->
			<?php get_template_part( 'template-parts/tenant-screening-card', null, array( 'owner_id' => $owner_id ) ); ?>

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
											<th><?php esc_html_e( 'Applicant', 'fraud-tenant-theme' ); ?></th>
											<th><?php esc_html_e( 'SSN (Last 4)', 'fraud-tenant-theme' ); ?></th>
											<th><?php esc_html_e( 'Search Date', 'fraud-tenant-theme' ); ?></th>
											<th><?php esc_html_e( 'Status', 'fraud-tenant-theme' ); ?></th>
											<th><?php esc_html_e( 'Action', 'fraud-tenant-theme' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $data['recent_searches'] as $search ) : ?>
											<tr>
												<?php $ft_sn = \FraudTenant\Services\SensitiveDataService::decrypt_array( $search->applicant_name_enc ?? null ); ?>
												<td><?php echo esc_html( trim( ( $ft_sn['first'] ?? '' ) . ' ' . ( $ft_sn['last'] ?? '' ) ) ?: '—' ); ?></td>
												<td><?php echo '' !== (string) $search->ssn_last_four ? '&bull;&bull;&bull;&bull;-' . esc_html( $search->ssn_last_four ) : '—'; ?></td>
												<td><?php echo esc_html( mysql2date( 'M j, Y', get_date_from_gmt( $search->search_date ) ) ); ?></td>
												<td>
													<?php if ( $search->match_found ) : ?>
														<span style="color:#0E9488; font-weight:600;"><?php esc_html_e( 'Match Found', 'fraud-tenant-theme' ); ?></span>
													<?php else : ?>
														<span style="color:var(--dash-muted);"><?php esc_html_e( 'No Match', 'fraud-tenant-theme' ); ?></span>
													<?php endif; ?>
												</td>
												<td>
													<?php if ( ! empty( $search->applicant_token_hash ) ) : ?>
														<a href="<?php echo esc_url( add_query_arg( array( 'ft_tab' => 'screening', 'screening_request' => (int) $search->id ), home_url( '/owner-dashboard/' ) ) ); ?>" class="dash-panel-link"><?php esc_html_e( 'View result', 'fraud-tenant-theme' ); ?></a>
													<?php else : ?>
														<span style="color:var(--dash-muted); font-size:12px;"><?php esc_html_e( 'Request logged', 'fraud-tenant-theme' ); ?></span>
													<?php endif; ?>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						<?php endif; ?>
					</div>

				
					<!-- Incident Reports Panel (Phase 8.1 Showcase) -->
					<div class="dash-panel" id="my-reports" style="margin-top:24px;">
						<div class="dash-panel-header">
							<div>
								<h2 class="dash-panel-title"><?php esc_html_e( 'Incident Reports', 'fraud-tenant-theme' ); ?></h2>
								<div style="font-size:12px; color:var(--dash-muted); margin-top:2px;">
									<?php esc_html_e( 'Submit and track factual incident records regarding unpaid rent, property damage, or lease violations.', 'fraud-tenant-theme' ); ?>
								</div>
							</div>
							<?php if ( \FraudTenant\Services\ComplianceService::is_data_gate_open() ) : ?>
								<button type="button" class="dashboard-btn-add" data-ft-modal-target="ftIncidentModal" style="padding:4px 10px; font-size:12px;">
									<?php esc_html_e( '+ Submit Incident Report', 'fraud-tenant-theme' ); ?>
								</button>
							<?php else : ?>
								<button type="button" class="dashboard-btn-add" disabled style="padding:4px 10px; font-size:12px; opacity:0.6; cursor:not-allowed;" title="<?php esc_attr_e( 'Submission locked pending completion of sensitive PII encryption infrastructure (BLK-08).', 'fraud-tenant-theme' ); ?>">
									<?php esc_html_e( '+ Submit Incident Report (Locked)', 'fraud-tenant-theme' ); ?>
								</button>
							<?php endif; ?>
						</div>

						<?php if ( ! \FraudTenant\Services\ComplianceService::is_data_gate_open() ) : ?>
							<div style="margin-bottom:16px; padding:12px 14px; background:#FFFBEB; border:1px solid #FCD34D; border-radius:8px; font-size:12px; color:#92400E; line-height:1.4;">
								<strong style="color:#78350F;"><?php esc_html_e( 'Sensitive PII Encryption Gate Active (BLK-08):', 'fraud-tenant-theme' ); ?></strong>
								<?php esc_html_e( 'Real-data incident submission is currently locked in production pending finalization of application-level sensitive PII encryption infrastructure. Synthetic submissions are enabled in test mode.', 'fraud-tenant-theme' ); ?>
							</div>
						<?php else : ?>
							<div style="margin-bottom:14px; display:inline-flex; align-items:center; gap:6px; background:#ECFDF5; border:1px solid #A7F3D0; border-radius:6px; padding:4px 10px; font-size:12px; color:#065F46;">
								<span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#10B981;"></span>
								<strong><?php esc_html_e( 'Test Mode Active:', 'fraud-tenant-theme' ); ?></strong>
								<span><?php esc_html_e( 'Synthetic incident reporting enabled for pre-production evaluation.', 'fraud-tenant-theme' ); ?></span>
							</div>
						<?php endif; ?>

						<?php if ( empty( $data['incident_reports'] ) ) : ?>
							<div class="dash-empty-state">
								<div class="dash-empty-icon">
									<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
								</div>
								<h4><?php esc_html_e( 'No incident reports submitted yet', 'fraud-tenant-theme' ); ?></h4>
								<p><?php esc_html_e( 'Factual documentation submitted for lease violations, unpaid rent, or property damage will be displayed here following administrative review.', 'fraud-tenant-theme' ); ?></p>
							</div>
						<?php else : ?>
							<div class="dash-table-wrapper">
								<table class="dash-table">
									<thead>
										<tr>
											<th><?php esc_html_e( 'Reference', 'fraud-tenant-theme' ); ?></th>
											<th><?php esc_html_e( 'Property', 'fraud-tenant-theme' ); ?></th>
											<th><?php esc_html_e( 'Category', 'fraud-tenant-theme' ); ?></th>
											<th><?php esc_html_e( 'Incident Date', 'fraud-tenant-theme' ); ?></th>
											<th><?php esc_html_e( 'Amount', 'fraud-tenant-theme' ); ?></th>
											<th><?php esc_html_e( 'Status', 'fraud-tenant-theme' ); ?></th>
											<th style="text-align:right;"><?php esc_html_e( 'Disclosable', 'fraud-tenant-theme' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $data['incident_reports'] as $report ) : ?>
											<tr>
												<td>
													<strong style="color:var(--dash-navy); font-family:monospace; font-size:13px;">
														<?php echo esc_html( $report->reference ); ?>
													</strong>
												</td>
												<td>
													<div style="font-weight:600; color:var(--dash-navy);">
														<?php echo esc_html( $report->property_title ?: $report->property_street ); ?>
													</div>
													<div style="font-size:11px; color:var(--dash-muted);">
														<?php echo esc_html( $report->property_city . ', ' . $report->property_state ); ?>
													</div>
												</td>
												<td>
													<span style="font-size:12px; font-weight:500;">
														<?php echo esc_html( $report->category_label ); ?>
													</span>
												</td>
												<td>
													<span style="font-size:12px; color:var(--dash-navy);">
														<?php echo esc_html( gmdate( 'M j, Y', strtotime( $report->incident_date ) ) ); ?>
													</span>
												</td>
												<td>
													<span style="font-size:12px; font-weight:600; color:var(--dash-navy);">
														<?php echo esc_html( $report->amount_formatted ); ?>
													</span>
												</td>
												<td>
													<?php if ( 'approved' === $report->status ) : ?>
														<span class="badge-dash badge-dash-verified"><?php esc_html_e( 'Approved', 'fraud-tenant-theme' ); ?></span>
													<?php elseif ( 'rejected' === $report->status ) : ?>
														<span class="badge-dash badge-dash-rejected"><?php esc_html_e( 'Rejected', 'fraud-tenant-theme' ); ?></span>
													<?php else : ?>
														<span class="badge-dash badge-dash-pending"><?php esc_html_e( 'Submitted (Pending Review)', 'fraud-tenant-theme' ); ?></span>
													<?php endif; ?>
												</td>
												<td style="text-align:right;">
													<?php if ( ! empty( $report->disclosable ) ) : ?>
														<span style="color:#0E9488; font-weight:600; font-size:12px;"><?php esc_html_e( 'Disclosed', 'fraud-tenant-theme' ); ?></span>
													<?php else : ?>
														<span style="color:var(--dash-muted); font-size:12px;"><?php esc_html_e( 'Non-Disclosable', 'fraud-tenant-theme' ); ?></span>
													<?php endif; ?>
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
<!-- Modal 3: Membership Plans (Phase 9 Stripe Checkout) -->
<?php
$has_stripe_cust = ! empty( \FraudTenant\Services\StripeService::get_customer_id( $owner_id ) );
$has_active_sub  = \FraudTenant\Services\MembershipService::has_active_subscription( $owner_id );
$ft_membership   = \FraudTenant\Services\MembershipService::get_effective_membership_status( $owner_id );
$ft_stripe_mode  = \FraudTenant\Services\StripeCredentialService::get_active_mode();
$ft_sub_history  = \FraudTenant\Services\MembershipService::get_subscription_history( $owner_id, 5 );
$ft_billing      = \FraudTenant\Services\MembershipService::get_billing_history( $owner_id, 10 );
$ft_live_flag    = 'live' === $ft_stripe_mode ? 1 : 0;
$ft_latest_sub   = null;
foreach ( $ft_sub_history as $ft_h ) {
	if ( (int) $ft_h->livemode === $ft_live_flag ) {
		$ft_latest_sub = $ft_h;
		break;
	}
}
?>
<div class="ft-modal-overlay" id="ftMembershipModal" role="dialog" aria-modal="true" aria-labelledby="ftMemTitle"
	data-rest-url="<?php echo esc_url( rest_url( 'fraud-tenant/v1' ) ); ?>"
	data-rest-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">
	<div class="ft-modal-box" style="max-width: 620px;">
		<button type="button" class="ft-modal-close" aria-label="<?php esc_attr_e( 'Close', 'fraud-tenant-theme' ); ?>">&times;</button>
		<h3 class="ft-modal-title" id="ftMemTitle"><?php esc_html_e( 'Membership Plans & Pricing', 'fraud-tenant-theme' ); ?></h3>
		<p class="ft-modal-desc">
			<?php esc_html_e( 'Select the membership plan that best suits your rental portfolio to access full reports and incident reporting.', 'fraud-tenant-theme' ); ?>
		</p>

		<div id="ftCheckoutNotice" style="display:none; margin-bottom:16px;"></div>

		<div class="ft-membership-status" style="border:1px solid #E2E8F0; border-radius:10px; padding:14px 16px; margin-bottom:16px; font-size:13px; color:var(--dash-navy);">
			<div style="display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap;">
				<div>
					<span style="color:var(--dash-muted);"><?php esc_html_e( 'Current plan:', 'fraud-tenant-theme' ); ?></span>
					<strong data-ft-current-plan="<?php echo esc_attr( $ft_membership['plan_code'] ); ?>"><?php echo esc_html( $ft_membership['plan_name'] ); ?></strong>
				</div>
				<?php if ( 'test' === $ft_stripe_mode ) : ?>
					<span style="background:#FEF3C7; color:#92400E; font-size:11px; font-weight:700; padding:2px 8px; border-radius:10px;"><?php esc_html_e( 'BILLING TEST MODE', 'fraud-tenant-theme' ); ?></span>
				<?php endif; ?>
			</div>
			<div style="margin-top:6px; color:var(--dash-muted);">
				<?php
				$ft_limit = (int) $ft_membership['property_limit'];
				echo esc_html(
					sprintf(
						/* translators: %s: property limit */
						__( 'Active property limit: %s', 'fraud-tenant-theme' ),
						-1 === $ft_limit ? __( 'Unlimited', 'fraud-tenant-theme' ) : (string) $ft_limit
					)
				);
				?>
				&middot;
				<?php echo ! empty( $ft_membership['features']['incident_reporting'] ) ? esc_html__( 'Incident reporting included', 'fraud-tenant-theme' ) : esc_html__( 'Incident reporting requires Standard', 'fraud-tenant-theme' ); ?>
			</div>
			<?php if ( $has_active_sub && ! empty( $ft_membership['current_period_end'] ) ) : ?>
				<div style="margin-top:6px;">
					<?php if ( ! empty( $ft_membership['cancel_at_period_end'] ) ) : ?>
						<?php /* translators: %s: date */ ?>
						<span style="color:#B45309; font-weight:600;"><?php echo esc_html( sprintf( __( 'Cancels on %s — access continues until then.', 'fraud-tenant-theme' ), mysql2date( 'M j, Y', $ft_membership['current_period_end'] ) ) ); ?></span>
					<?php else : ?>
						<?php /* translators: %s: date */ ?>
						<?php echo esc_html( sprintf( __( 'Renews on %s', 'fraud-tenant-theme' ), mysql2date( 'M j, Y', $ft_membership['current_period_end'] ) ) ); ?>
					<?php endif; ?>
				</div>
			<?php elseif ( $ft_latest_sub && in_array( $ft_latest_sub->status, array( 'past_due', 'unpaid', 'incomplete' ), true ) ) : ?>
				<div style="margin-top:6px; color:#B91C1C; font-weight:600;">
					<?php esc_html_e( 'Your last membership payment did not complete. Standard features are paused — update your payment method in the billing portal.', 'fraud-tenant-theme' ); ?>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( $has_active_sub ) : ?>
			<div style="background:#F0FDFA; border:1.5px solid #0E9488; border-radius:8px; padding:14px; margin-bottom:18px; color:#14263E; font-size:13px;">
				<strong>&check; <?php esc_html_e( 'You have an active Standard subscription.', 'fraud-tenant-theme' ); ?></strong>
				<p style="margin:4px 0 0 0; color:var(--dash-muted);"><?php esc_html_e( 'Your account already has full feature entitlements. Use the billing portal below to update payment methods or view invoices.', 'fraud-tenant-theme' ); ?></p>
			</div>
		<?php endif; ?>

		<div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:20px;">
			<div style="border:1.5px solid #E2E8F0; border-radius:10px; padding:18px; text-align:center; display:flex; flex-direction:column; justify-content:space-between;">
				<div>
					<h4 style="margin:0 0 6px 0; color:var(--dash-navy); font-size:16px;"><?php esc_html_e( 'Standard Monthly', 'fraud-tenant-theme' ); ?></h4>
					<div style="font-size:26px; font-weight:800; color:var(--dash-navy);">$29 <span style="font-size:13px; font-weight:500; color:var(--dash-muted);">/ month</span></div>
					<ul style="text-align:left; font-size:12px; color:var(--dash-muted); margin:12px 0 0 0; padding-left:18px; line-height:1.6;">
						<li><?php esc_html_e( 'Full screening reports', 'fraud-tenant-theme' ); ?></li>
						<li><?php esc_html_e( 'Incident reporting access', 'fraud-tenant-theme' ); ?></li>
						<li><?php esc_html_e( 'Unlimited properties', 'fraud-tenant-theme' ); ?></li>
						<li><?php esc_html_e( 'Cancel anytime', 'fraud-tenant-theme' ); ?></li>
					</ul>
				</div>
				<?php if ( ! $has_active_sub ) : ?>
					<button type="button" class="dashboard-btn-add ft-checkout-btn" data-plan-code="standard_monthly" data-plan-id="2" style="width:100%; justify-content:center; margin-top:14px; font-size:13px;">
						<?php esc_html_e( 'Select Monthly Plan', 'fraud-tenant-theme' ); ?>
					</button>
				<?php endif; ?>
			</div>
			<div style="border:2px solid var(--dash-teal); border-radius:10px; padding:18px; text-align:center; background:#F0FDFA; position:relative; display:flex; flex-direction:column; justify-content:space-between;">
				<div style="position:absolute; top:-10px; right:14px; background:var(--dash-teal); color:#FFFFFF; font-size:10px; font-weight:700; padding:2px 8px; border-radius:10px;">
					<?php esc_html_e( 'BEST VALUE', 'fraud-tenant-theme' ); ?>
				</div>
				<div>
					<h4 style="margin:0 0 6px 0; color:var(--dash-navy); font-size:16px;"><?php esc_html_e( 'Standard Annual', 'fraud-tenant-theme' ); ?></h4>
					<div style="font-size:26px; font-weight:800; color:var(--dash-navy);">$290 <span style="font-size:13px; font-weight:500; color:var(--dash-muted);">/ year</span></div>
					<ul style="text-align:left; font-size:12px; color:var(--dash-muted); margin:12px 0 0 0; padding-left:18px; line-height:1.6;">
						<li><?php esc_html_e( '2 Months Free Included', 'fraud-tenant-theme' ); ?></li>
						<li><?php esc_html_e( 'Full screening reports', 'fraud-tenant-theme' ); ?></li>
						<li><?php esc_html_e( 'Incident reporting access', 'fraud-tenant-theme' ); ?></li>
						<li><?php esc_html_e( 'Priority compliance review', 'fraud-tenant-theme' ); ?></li>
					</ul>
				</div>
				<?php if ( ! $has_active_sub ) : ?>
					<button type="button" class="dashboard-btn-add ft-checkout-btn" data-plan-code="standard_yearly" data-plan-id="3" style="width:100%; justify-content:center; margin-top:14px; font-size:13px; background:var(--dash-teal);">
						<?php esc_html_e( 'Select Annual Plan', 'fraud-tenant-theme' ); ?>
					</button>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( $has_stripe_cust || $has_active_sub ) : ?>
			<div style="border-top:1px solid #E2E8F0; padding-top:14px; margin-top:14px; text-align:center;">
				<p style="font-size:12px; color:var(--dash-muted); margin:0 0 10px 0;">
					<?php esc_html_e( 'Manage payment methods, view past billing history, or update subscription settings.', 'fraud-tenant-theme' ); ?>
				</p>
				<button type="button" class="dashboard-btn-secondary ft-portal-btn" style="width:100%; justify-content:center; font-size:13px;">
					<?php esc_html_e( 'Open Stripe Billing Portal', 'fraud-tenant-theme' ); ?> &rarr;
				</button>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $ft_billing ) ) : ?>
			<div style="margin-top:16px;">
				<h4 style="margin:0 0 8px 0; font-size:13px; color:var(--dash-navy);"><?php esc_html_e( 'Payment History', 'fraud-tenant-theme' ); ?></h4>
				<table class="ft-billing-table" style="width:100%; font-size:12px; border-collapse:collapse;">
					<thead><tr style="text-align:left; color:var(--dash-muted);"><th style="padding:4px 0;"><?php esc_html_e( 'Date', 'fraud-tenant-theme' ); ?></th><th><?php esc_html_e( 'Amount', 'fraud-tenant-theme' ); ?></th><th><?php esc_html_e( 'Result', 'fraud-tenant-theme' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $ft_billing as $ft_b ) : ?>
						<tr style="border-top:1px solid #F1F5F9;">
							<td style="padding:6px 0;"><?php echo esc_html( mysql2date( 'M j, Y', $ft_b['date'] ) ); ?><?php if ( ! $ft_b['livemode'] ) : ?> <span style="color:#92400E;">(<?php esc_html_e( 'test', 'fraud-tenant-theme' ); ?>)</span><?php endif; ?></td>
							<td><?php echo esc_html( number_format( $ft_b['amount'] / 100, 2 ) . ' ' . $ft_b['currency'] ); ?></td>
							<td style="font-weight:600; color:<?php echo 'paid' === $ft_b['result'] ? '#047857' : '#B91C1C'; ?>;"><?php echo 'paid' === $ft_b['result'] ? esc_html__( 'Paid', 'fraud-tenant-theme' ) : esc_html__( 'Failed', 'fraud-tenant-theme' ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>

		<div style="margin-top:14px;">
			<button type="button" class="dashboard-btn-secondary" data-ft-modal-close style="width:100%; justify-content:center;">
				<?php esc_html_e( 'Close', 'fraud-tenant-theme' ); ?>
			</button>
		</div>
	</div>
</div>
<?php if ( 'screening' === $current_tab ) : ?>
<script>window.addEventListener('load',function(){var e=document.getElementById('ftScreeningResult')||document.getElementById('search-tenants');if(e){e.scrollIntoView({block:'start'});}});</script>
<?php endif; ?>
<?php if ( 'membership' === $current_tab || '' !== $checkout_status ) : ?>
<script>
window.addEventListener('load', function () { // after dashboard.js has bound modal handlers
	var trigger = document.querySelector('[data-ft-modal-target="ftMembershipModal"]');
	if (trigger) { trigger.click(); }
});
</script>
<?php endif; ?>
<!-- Modal 4: Account & Profile Settings -->
<div class="ft-modal-overlay" id="ftProfileModal" role="dialog" aria-modal="true" aria-labelledby="ftProfTitle">
	<div class="ft-modal-box" style="max-width: 680px; width: 100%;">
		<button type="button" class="ft-modal-close" aria-label="<?php esc_attr_e( 'Close', 'fraud-tenant-theme' ); ?>">&times;</button>
		<h3 class="ft-modal-title" id="ftProfTitle"><?php esc_html_e( 'Account & Profile Settings', 'fraud-tenant-theme' ); ?></h3>
		<p class="ft-modal-desc">
			<?php esc_html_e( 'Manage your contact details, personal information, password, and membership.', 'fraud-tenant-theme' ); ?>
		</p>

		<!-- Sub-Navigation Tabs -->
		<div class="ft-settings-nav" style="display:flex; gap:8px; border-bottom:1px solid #E2E8F0; margin-bottom:20px; padding-bottom:10px; overflow-x:auto;">
			<button type="button" class="ft-settings-tab is-active" data-tab-target="ftTabProfile" style="background:none; border:none; padding:8px 14px; font-weight:700; font-size:13px; color:var(--dash-teal); border-bottom:2px solid var(--dash-teal); cursor:pointer;">
				<?php esc_html_e( 'Profile Details', 'fraud-tenant-theme' ); ?>
			</button>
			<button type="button" class="ft-settings-tab" data-tab-target="ftTabPassword" style="background:none; border:none; padding:8px 14px; font-weight:600; font-size:13px; color:#64748B; cursor:pointer;">
				<?php esc_html_e( 'Security & Password', 'fraud-tenant-theme' ); ?>
			</button>
			<button type="button" class="ft-settings-tab" data-tab-target="ftTabEmail" style="background:none; border:none; padding:8px 14px; font-weight:600; font-size:13px; color:#64748B; cursor:pointer;">
				<?php esc_html_e( 'Email Address', 'fraud-tenant-theme' ); ?>
			</button>
			<button type="button" class="ft-settings-tab" data-tab-target="ftTabPlan" style="background:none; border:none; padding:8px 14px; font-weight:600; font-size:13px; color:#64748B; cursor:pointer;">
				<?php esc_html_e( 'Membership Plan', 'fraud-tenant-theme' ); ?>
			</button>
		</div>

		<!-- Panel 1: Profile Details -->
		<div class="ft-settings-panel" id="ftTabProfile">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ft_update_profile" />
				<input type="hidden" name="account_section" value="profile" />
				<input type="hidden" name="redirect_to" value="<?php echo esc_url( home_url( '/owner-dashboard/' ) ); ?>" />
				<?php wp_nonce_field( 'ft_update_profile_' . $owner_id, 'ft_profile_nonce' ); ?>

				<div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
					<div>
						<label for="owner_first_name" style="display:block; font-size:12px; font-weight:600; color:var(--dash-navy); margin-bottom:6px;">
							<?php esc_html_e( 'First Name', 'fraud-tenant-theme' ); ?>
						</label>
						<input type="text" id="owner_first_name" name="first_name" value="<?php echo esc_attr( $current_user->first_name ); ?>" class="search-ssn-input" style="height:40px; background:#FFFFFF;" required />
					</div>
					<div>
						<label for="owner_last_name" style="display:block; font-size:12px; font-weight:600; color:var(--dash-navy); margin-bottom:6px;">
							<?php esc_html_e( 'Last Name', 'fraud-tenant-theme' ); ?>
						</label>
						<input type="text" id="owner_last_name" name="last_name" value="<?php echo esc_attr( $current_user->last_name ); ?>" class="search-ssn-input" style="height:40px; background:#FFFFFF;" required />
					</div>
				</div>

				<div style="margin-bottom:14px;">
					<label for="owner_phone_edit" style="display:block; font-size:12px; font-weight:600; color:var(--dash-navy); margin-bottom:6px;">
						<?php esc_html_e( 'Phone Number', 'fraud-tenant-theme' ); ?>
					</label>
					<input type="tel" id="owner_phone_edit" name="phone" value="<?php echo esc_attr( $user_profile?->phone ?? '' ); ?>" class="search-ssn-input" style="height:40px; background:#FFFFFF;" placeholder="(555) 000-0000" required />
				</div>

				<div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
					<div>
						<label for="owner_biz_name" style="display:block; font-size:12px; font-weight:600; color:var(--dash-navy); margin-bottom:6px;">
							<?php esc_html_e( 'Business / Entity Name', 'fraud-tenant-theme' ); ?>
						</label>
						<input type="text" id="owner_biz_name" name="business_name" value="<?php echo esc_attr( $user_profile?->business_name ?? '' ); ?>" class="search-ssn-input" style="height:40px; background:#FFFFFF;" />
					</div>
					<div>
						<label for="owner_biz_reg" style="display:block; font-size:12px; font-weight:600; color:var(--dash-navy); margin-bottom:6px;">
							<?php esc_html_e( 'Business Reg. Number', 'fraud-tenant-theme' ); ?>
						</label>
						<input type="text" id="owner_biz_reg" name="business_reg_number" value="<?php echo esc_attr( $user_profile?->business_reg_number ?? '' ); ?>" class="search-ssn-input" style="height:40px; background:#FFFFFF;" />
					</div>
				</div>

				<div style="margin-bottom:14px;">
					<label for="owner_addr1" style="display:block; font-size:12px; font-weight:600; color:var(--dash-navy); margin-bottom:6px;">
						<?php esc_html_e( 'Street Address', 'fraud-tenant-theme' ); ?>
					</label>
					<input type="text" id="owner_addr1" name="address_line1" value="<?php echo esc_attr( $user_profile?->address_line1 ?? '' ); ?>" class="search-ssn-input" style="height:40px; background:#FFFFFF;" placeholder="123 Main St" />
				</div>

				<div style="display:grid; grid-template-columns:2fr 1fr 1fr; gap:14px; margin-bottom:20px;">
					<div>
						<label for="owner_city" style="display:block; font-size:12px; font-weight:600; color:var(--dash-navy); margin-bottom:6px;">
							<?php esc_html_e( 'City', 'fraud-tenant-theme' ); ?>
						</label>
						<input type="text" id="owner_city" name="city" value="<?php echo esc_attr( $user_profile?->city ?? '' ); ?>" class="search-ssn-input" style="height:40px; background:#FFFFFF;" />
					</div>
					<div>
						<label for="owner_state" style="display:block; font-size:12px; font-weight:600; color:var(--dash-navy); margin-bottom:6px;">
							<?php esc_html_e( 'State', 'fraud-tenant-theme' ); ?>
						</label>
						<input type="text" id="owner_state" name="state" value="<?php echo esc_attr( $user_profile?->state ?? '' ); ?>" class="search-ssn-input" style="height:40px; background:#FFFFFF;" placeholder="TX" />
					</div>
					<div>
						<label for="owner_zip" style="display:block; font-size:12px; font-weight:600; color:var(--dash-navy); margin-bottom:6px;">
							<?php esc_html_e( 'Postal Code', 'fraud-tenant-theme' ); ?>
						</label>
						<input type="text" id="owner_zip" name="postal_code" value="<?php echo esc_attr( $user_profile?->postal_code ?? '' ); ?>" class="search-ssn-input" style="height:40px; background:#FFFFFF;" placeholder="75001" />
					</div>
				</div>

				<button type="submit" class="dashboard-btn-add" style="width:100%; justify-content:center;">
					<?php esc_html_e( 'Save Profile Information', 'fraud-tenant-theme' ); ?>
				</button>
			</form>
		</div>

		<!-- Panel 2: Security & Password -->
		<div class="ft-settings-panel" id="ftTabPassword" style="display:none;">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ft_update_profile" />
				<input type="hidden" name="account_section" value="password" />
				<input type="hidden" name="redirect_to" value="<?php echo esc_url( home_url( '/owner-dashboard/' ) ); ?>" />
				<?php wp_nonce_field( 'ft_update_profile_' . $owner_id, 'ft_profile_nonce' ); ?>

				<div style="margin-bottom:14px;">
					<label for="owner_curr_pw" style="display:block; font-size:12px; font-weight:600; color:var(--dash-navy); margin-bottom:6px;">
						<?php esc_html_e( 'Current Password', 'fraud-tenant-theme' ); ?> <span style="color:#EF4444;">*</span>
					</label>
					<input type="password" id="owner_curr_pw" name="current_password" class="search-ssn-input" style="height:40px; background:#FFFFFF;" required />
				</div>

				<div style="margin-bottom:14px;">
					<label for="owner_new_pw" style="display:block; font-size:12px; font-weight:600; color:var(--dash-navy); margin-bottom:6px;">
						<?php esc_html_e( 'New Password (minimum 8 characters)', 'fraud-tenant-theme' ); ?> <span style="color:#EF4444;">*</span>
					</label>
					<input type="password" id="owner_new_pw" name="new_password" minlength="8" class="search-ssn-input" style="height:40px; background:#FFFFFF;" required />
				</div>

				<div style="margin-bottom:20px;">
					<label for="owner_conf_pw" style="display:block; font-size:12px; font-weight:600; color:var(--dash-navy); margin-bottom:6px;">
						<?php esc_html_e( 'Confirm New Password', 'fraud-tenant-theme' ); ?> <span style="color:#EF4444;">*</span>
					</label>
					<input type="password" id="owner_conf_pw" name="confirm_password" minlength="8" class="search-ssn-input" style="height:40px; background:#FFFFFF;" required />
				</div>

				<button type="submit" class="dashboard-btn-add" style="width:100%; justify-content:center;">
					<?php esc_html_e( 'Update Password', 'fraud-tenant-theme' ); ?>
				</button>
			</form>
		</div>

		<!-- Panel 3: Email Address -->
		<div class="ft-settings-panel" id="ftTabEmail" style="display:none;">
			<?php get_template_part( 'template-parts/email-change-confirm', null, array( 'user_id' => $owner_id, 'redirect' => home_url( '/owner-dashboard/' ) ) ); ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ft_update_profile" />
				<input type="hidden" name="account_section" value="email" />
				<input type="hidden" name="redirect_to" value="<?php echo esc_url( home_url( '/owner-dashboard/' ) ); ?>" />
				<?php wp_nonce_field( 'ft_update_profile_' . $owner_id, 'ft_profile_nonce' ); ?>

				<div style="margin-bottom:14px;">
					<label style="display:block; font-size:12px; font-weight:600; color:var(--dash-navy); margin-bottom:6px;">
						<?php esc_html_e( 'Current Email Address', 'fraud-tenant-theme' ); ?>
					</label>
					<input type="email" value="<?php echo esc_attr( $current_user->user_email ); ?>" class="search-ssn-input" style="height:40px; background:#F8FAFC; color:#64748B;" disabled readonly />
				</div>

				<div style="margin-bottom:14px;">
					<label for="owner_new_email" style="display:block; font-size:12px; font-weight:600; color:var(--dash-navy); margin-bottom:6px;">
						<?php esc_html_e( 'New Email Address', 'fraud-tenant-theme' ); ?> <span style="color:#EF4444;">*</span>
					</label>
					<input type="email" id="owner_new_email" name="new_email" class="search-ssn-input" style="height:40px; background:#FFFFFF;" required />
				</div>

				<div style="margin-bottom:20px;">
					<label for="owner_email_curr_pw" style="display:block; font-size:12px; font-weight:600; color:var(--dash-navy); margin-bottom:6px;">
						<?php esc_html_e( 'Current Password (to authorize change)', 'fraud-tenant-theme' ); ?> <span style="color:#EF4444;">*</span>
					</label>
					<input type="password" id="owner_email_curr_pw" name="current_password" class="search-ssn-input" style="height:40px; background:#FFFFFF;" required />
				</div>

				<button type="submit" class="dashboard-btn-add" style="width:100%; justify-content:center;">
					<?php esc_html_e( 'Send Confirmation Code', 'fraud-tenant-theme' ); ?>
				</button>
			</form>
		</div>

		<!-- Panel 4: Membership Plan -->
		<div class="ft-settings-panel" id="ftTabPlan" style="display:none;">
			<div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:10px; padding:18px; margin-bottom:20px;">
				<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
					<div>
						<div style="font-size:11px; text-transform:uppercase; font-weight:700; color:#64748B; letter-spacing:0.5px;"><?php esc_html_e( 'Current Tier', 'fraud-tenant-theme' ); ?></div>
						<div style="font-size:18px; font-weight:800; color:var(--dash-navy);"><?php echo esc_html( $data['membership_plan_name'] ); ?></div>
					</div>
					<span class="badge badge-success" style="padding:6px 12px; font-size:12px; font-weight:700;">
						<?php esc_html_e( 'Active', 'fraud-tenant-theme' ); ?>
					</span>
				</div>
				<div style="font-size:13px; color:#475569; line-height:1.6; border-top:1px solid #E2E8F0; padding-top:12px;">
					<?php if ( 'free' === $data['membership_plan_code'] ) : ?>
						<p style="margin:0 0 6px 0;"><strong><?php esc_html_e( 'Active Quotas:', 'fraud-tenant-theme' ); ?></strong> <?php esc_html_e( '1 Property Listing, Standard Screening.', 'fraud-tenant-theme' ); ?></p>
						<p style="margin:0; color:#DC2626;"><strong><?php esc_html_e( 'Locked Features:', 'fraud-tenant-theme' ); ?></strong> <?php esc_html_e( 'Incident Reporting & Multiple Properties require Standard Plan.', 'fraud-tenant-theme' ); ?></p>
					<?php else : ?>
						<p style="margin:0 0 6px 0;"><strong><?php esc_html_e( 'Features Active:', 'fraud-tenant-theme' ); ?></strong> <?php esc_html_e( 'Unlimited Property Listings, Incident Reporting & Dispute Management.', 'fraud-tenant-theme' ); ?></p>
						<p style="margin:0; color:var(--dash-teal);"><strong><?php esc_html_e( 'Status:', 'fraud-tenant-theme' ); ?></strong> <?php esc_html_e( 'Full PropTech Privileges Enabled.', 'fraud-tenant-theme' ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<button type="button" class="dashboard-btn-add" data-ft-modal-target="ftMembershipModal" data-ft-modal-close style="width:100%; justify-content:center;">
				<?php echo 'free' === $data['membership_plan_code'] ? esc_html__( 'Upgrade to Standard Membership', 'fraud-tenant-theme' ) : esc_html__( 'Manage Membership Plan', 'fraud-tenant-theme' ); ?>
			</button>
		</div>

		<script>
		(function(){
			var modal = document.getElementById('ftProfileModal');
			if (!modal) return;
			var tabs = modal.querySelectorAll('.ft-settings-tab');
			var panels = modal.querySelectorAll('.ft-settings-panel');
			tabs.forEach(function(tab){
				tab.addEventListener('click', function(e){
					e.preventDefault();
					var targetId = tab.getAttribute('data-tab-target');
					tabs.forEach(function(t){
						t.classList.remove('is-active');
						t.style.color = '#64748B';
						t.style.borderBottom = 'none';
						t.style.fontWeight = '600';
					});
					panels.forEach(function(p){ p.style.display = 'none'; });
					tab.classList.add('is-active');
					tab.style.color = 'var(--dash-teal)';
					tab.style.borderBottom = '2px solid var(--dash-teal)';
					tab.style.fontWeight = '700';
					var target = document.getElementById(targetId);
					if (target) target.style.display = 'block';
				});
			});
		})();
		</script>
	</div>
</div>

<!-- Modal 4.5: Submit Incident Report Modal (Phase 8.1 Workflow) -->
<div class="ft-modal-overlay" id="ftIncidentModal" role="dialog" aria-modal="true" aria-labelledby="ftIncidentTitle">
	<div class="ft-modal-box" style="max-width: 620px;">
		<button type="button" class="ft-modal-close" aria-label="<?php esc_attr_e( 'Close', 'fraud-tenant-theme' ); ?>">&times;</button>
		<h3 class="ft-modal-title" id="ftIncidentTitle"><?php esc_html_e( 'Submit Incident Report', 'fraud-tenant-theme' ); ?></h3>
		<p class="ft-modal-desc">
			<?php esc_html_e( 'Submit a factual incident report regarding unpaid rent, property damage, or lease violations for administrative review.', 'fraud-tenant-theme' ); ?>
		</p>

		<?php if ( ! \FraudTenant\Services\ComplianceService::is_data_gate_open() ) : ?>
			<div style="background:#FFFBEB; border:1px solid #FCD34D; border-radius:8px; padding:16px; margin-bottom:18px; color:#92400E; font-size:13px; line-height:1.5;">
				<strong style="display:block; margin-bottom:4px;"><?php esc_html_e( 'Sensitive PII Encryption Gate Active (BLK-08):', 'fraud-tenant-theme' ); ?></strong>
				<p style="margin:0;"><?php esc_html_e( 'Incident report submissions are temporarily locked in production pending finalization of application-level sensitive PII encryption infrastructure (BLK-08). Non-production test mode is required for synthetic testing.', 'fraud-tenant-theme' ); ?></p>
			</div>
			<button type="button" class="dashboard-btn-add" data-ft-modal-close style="width:100%; justify-content:center;">
				<?php esc_html_e( 'Close Notice', 'fraud-tenant-theme' ); ?>
			</button>
		<?php elseif ( ! IncidentService::can_submit_incident( $owner_id ) ) : ?>
			<div class="ft-incident-upgrade-gate" style="background:#F0FDFA; border:1px solid #0E9488; border-radius:8px; padding:16px; margin-bottom:18px; color:#14263E; font-size:13px; line-height:1.5;">
				<strong style="display:block; margin-bottom:4px;"><?php esc_html_e( 'Standard Membership Required', 'fraud-tenant-theme' ); ?></strong>
				<p style="margin:0;"><?php esc_html_e( 'Incident reporting is included with the Standard Monthly and Standard Annual plans. Your existing reports remain visible on your dashboard.', 'fraud-tenant-theme' ); ?></p>
			</div>
			<button type="button" class="dashboard-btn-add" data-ft-modal-target="ftMembershipModal" data-ft-modal-close style="width:100%; justify-content:center;">
				<?php esc_html_e( 'View Membership Plans', 'fraud-tenant-theme' ); ?>
			</button>
		<?php elseif ( empty( $data['eligible_properties'] ) ) : ?>
			<div style="background:#FFFBEB; border:1px solid #FCD34D; border-radius:8px; padding:16px; margin-bottom:18px; color:#92400E; font-size:13px; line-height:1.5;">
				<strong style="display:block; margin-bottom:4px;"><?php esc_html_e( 'Verified Property Required:', 'fraud-tenant-theme' ); ?></strong>
				<p style="margin:0;"><?php esc_html_e( 'Incident reports may only be submitted for active, verified rental properties. Please add a rental property and upload ownership documents for verification first.', 'fraud-tenant-theme' ); ?></p>
			</div>
			<div style="display:flex; gap:12px;">
				<a href="<?php echo esc_url( home_url( '/owner-dashboard/properties/' ) ); ?>" class="dashboard-btn-add" style="flex:1; justify-content:center; text-decoration:none;">
					<?php esc_html_e( 'Manage Properties', 'fraud-tenant-theme' ); ?>
				</a>
				<button type="button" class="dashboard-btn-add" data-ft-modal-close style="flex:1; justify-content:center; background:#F1F5F9; color:var(--dash-navy); border:1px solid #CBD5E1;">
					<?php esc_html_e( 'Close Notice', 'fraud-tenant-theme' ); ?>
				</button>
			</div>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="ft-incident-report-form">
				<?php wp_nonce_field( 'ft_submit_incident_action', 'ft_incident_nonce' ); ?>
				<input type="hidden" name="action" value="ft_submit_incident" />
				<input type="hidden" name="redirect_to" value="<?php echo esc_attr( home_url( '/owner-dashboard/#my-reports' ) ); ?>" />

				<div class="dash-field-group" style="margin-bottom:14px;">
					<label for="ftIncidentPropSelect" style="display:block; font-size:13px; font-weight:600; color:var(--dash-navy); margin-bottom:6px;">
						<?php esc_html_e( 'Associated Verified Property', 'fraud-tenant-theme' ); ?> *
					</label>
					<select name="property_id" id="ftIncidentPropSelect" required style="width:100%; padding:10px 12px; border:1px solid #CBD5E1; border-radius:6px; font-size:13px; color:var(--dash-navy); background:#FFFFFF;">
						<option value=""><?php esc_html_e( '-- Select Verified Property --', 'fraud-tenant-theme' ); ?></option>
						<?php foreach ( $data['eligible_properties'] as $ep ) : ?>
							<option value="<?php echo esc_attr( $ep->id ); ?>">
								<?php echo esc_html( ( $ep->title ? $ep->title . ' — ' : '' ) . $ep->address_street . ', ' . $ep->city . ' ' . $ep->state . ' ' . $ep->postal_code ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<?php get_template_part( 'template-parts/incident-tenant-identity' ); ?>

				<div style="display:grid; grid-template-columns: 1fr 1fr; gap:14px; margin-bottom:14px;">
					<div class="dash-field-group">
						<label for="ftIncidentCategory" style="display:block; font-size:13px; font-weight:600; color:var(--dash-navy); margin-bottom:6px;">
							<?php esc_html_e( 'Incident Category', 'fraud-tenant-theme' ); ?> *
						</label>
						<select name="category" id="ftIncidentCategory" required style="width:100%; padding:10px 12px; border:1px solid #CBD5E1; border-radius:6px; font-size:13px; color:var(--dash-navy); background:#FFFFFF;">
							<option value="unpaid_rent"><?php esc_html_e( 'Unpaid Rent / Default', 'fraud-tenant-theme' ); ?></option>
							<option value="property_damage"><?php esc_html_e( 'Property Damage', 'fraud-tenant-theme' ); ?></option>
							<option value="late_rent"><?php esc_html_e( 'Repeated Late Rent', 'fraud-tenant-theme' ); ?></option>
							<option value="lease_violation"><?php esc_html_e( 'Lease Violation', 'fraud-tenant-theme' ); ?></option>
							<option value="unauthorized_pet"><?php esc_html_e( 'Unauthorized Pet', 'fraud-tenant-theme' ); ?></option>
							<option value="noise_complaint"><?php esc_html_e( 'Noise / Nuisance Complaint', 'fraud-tenant-theme' ); ?></option>
							<option value="other_violation"><?php esc_html_e( 'Other Lease Violation', 'fraud-tenant-theme' ); ?></option>
						</select>
					</div>
					<div class="dash-field-group">
						<label for="ftIncidentDate" style="display:block; font-size:13px; font-weight:600; color:var(--dash-navy); margin-bottom:6px;">
							<?php esc_html_e( 'Incident Date', 'fraud-tenant-theme' ); ?> *
						</label>
						<input type="date" name="incident_date" id="ftIncidentDate" max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required style="width:100%; padding:9px 12px; border:1px solid #CBD5E1; border-radius:6px; font-size:13px; color:var(--dash-navy); background:#FFFFFF;" />
					</div>
				</div>

				<div class="dash-field-group" style="margin-bottom:14px;">
					<label for="ftIncidentAmount" style="display:block; font-size:13px; font-weight:600; color:var(--dash-navy); margin-bottom:6px;">
						<?php esc_html_e( 'Amount Involved ($ USD)', 'fraud-tenant-theme' ); ?>
					</label>
					<input type="number" name="amount_involved" id="ftIncidentAmount" step="0.01" min="0" value="0.00" style="width:100%; padding:9px 12px; border:1px solid #CBD5E1; border-radius:6px; font-size:13px; color:var(--dash-navy); background:#FFFFFF;" />
					<small style="color:var(--dash-muted); font-size:11px;"><?php esc_html_e( 'Enter 0.00 if non-monetary violation.', 'fraud-tenant-theme' ); ?></small>
				</div>

				<div class="dash-field-group" style="margin-bottom:14px;">
					<label for="ftIncidentDescription" style="display:block; font-size:13px; font-weight:600; color:var(--dash-navy); margin-bottom:6px;">
						<?php esc_html_e( 'Factual Description', 'fraud-tenant-theme' ); ?> *
					</label>
					<textarea name="factual_description" id="ftIncidentDescription" rows="4" required minlength="10" placeholder="<?php esc_attr_e( 'Provide an objective, factual chronological description of the incident without subjective or defamatory statements...', 'fraud-tenant-theme' ); ?>" style="width:100%; padding:10px 12px; border:1px solid #CBD5E1; border-radius:6px; font-size:13px; color:var(--dash-navy); background:#FFFFFF; line-height:1.5;"></textarea>
				</div>

				<div class="dash-field-group" style="margin-bottom:16px;">
					<label for="ftIncidentEvidence" style="display:block; font-size:13px; font-weight:600; color:var(--dash-navy); margin-bottom:6px;">
						<?php esc_html_e( 'Supporting Evidence Document (Optional)', 'fraud-tenant-theme' ); ?>
					</label>
					<input type="file" name="incident_evidence" id="ftIncidentEvidence" accept=".pdf,.png,.jpg,.jpeg" style="width:100%; font-size:12px; color:var(--dash-muted);" />
					<small style="display:block; color:var(--dash-muted); font-size:11px; margin-top:4px;">
						<?php esc_html_e( 'Accepted formats: PDF, PNG, JPG/JPEG (Max 10 MB). Stored securely in protected storage.', 'fraud-tenant-theme' ); ?>
					</small>
				</div>

				<div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:14px; margin-bottom:18px;">
					<label style="display:flex; align-items:flex-start; gap:10px; cursor:pointer; font-size:12px; color:var(--dash-navy); line-height:1.5;">
						<input type="checkbox" name="owner_attestation" value="1" required style="margin-top:3px;" />
						<span>
							<strong><?php esc_html_e( 'Owner Factual Attestation:', 'fraud-tenant-theme' ); ?></strong>
							<?php esc_html_e( 'I attest under penalty of platform suspension that the information provided is strictly factual, accurate to the best of my knowledge, and supported by documentation/records. I acknowledge that allegations undergo administrative review before any disclosure.', 'fraud-tenant-theme' ); ?>
							<br><small style="color:var(--dash-muted);"><?php esc_html_e( '(Attestation Version 1.0 — Recorded as an owner-provided statement)', 'fraud-tenant-theme' ); ?></small>
						</span>
					</label>
				</div>

				<div style="display:flex; gap:12px;">
					<button type="button" class="dashboard-btn-add" data-ft-modal-close style="flex:1; justify-content:center; background:#F1F5F9; color:var(--dash-navy); border:1px solid #CBD5E1;">
						<?php esc_html_e( 'Cancel', 'fraud-tenant-theme' ); ?>
					</button>
					<button type="submit" class="dashboard-btn-add" style="flex:2; justify-content:center;">
						<?php esc_html_e( 'Submit Incident Report', 'fraud-tenant-theme' ); ?>
					</button>
				</div>
			</form>
		<?php endif; ?>
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
