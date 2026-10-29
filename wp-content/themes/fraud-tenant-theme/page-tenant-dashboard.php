<?php
/**
 * Template Name: Tenant Dashboard
 *
 * Dedicated authenticated SaaS application shell for approved Tenants.
 * Matches the approved design showcase layout.
 *
 * @package FraudTenantTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use FraudTenant\Auth\AuthHandler;
use FraudTenant\Auth\AuthService;
use FraudTenant\Services\DashboardService;
use FraudTenant\Utils\Constants;

// 1. Enforce strict access control, role check, and lifecycle state
$current_user = DashboardService::enforce_access( Constants::ROLE_TENANT );
$tenant_id    = $current_user->ID;

// 2. Fetch genuine database records (Zero mock data)
$data = DashboardService::get_tenant_dashboard_data( $tenant_id );

$user_profile = $data['profile'];
$first_name   = $current_user->first_name ?: ( explode( ' ', $current_user->display_name )[0] ?? 'Tenant' );

get_header();
?>

<div class="dashboard-shell-layout" id="main-content">

	<!-- Left Navy Sidebar (Showcase Match) -->
	<aside class="dashboard-sidebar" id="ftDashboardSidebar">
		<div>
			<ul class="dashboard-nav-list" role="navigation" aria-label="<?php esc_attr_e( 'Tenant Dashboard Navigation', 'fraud-tenant-theme' ); ?>">
				<li class="dashboard-nav-item is-active">
					<a href="<?php echo esc_url( home_url( '/tenant-dashboard/' ) ); ?>">
						<svg class="dashboard-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
						<span><?php esc_html_e( 'Dashboard', 'fraud-tenant-theme' ); ?></span>
					</a>
				</li>
				<li class="dashboard-nav-item">
					<a href="#my-records">
						<svg class="dashboard-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
						<span><?php esc_html_e( 'My Records', 'fraud-tenant-theme' ); ?></span>
					</a>
				</li>
				<li class="dashboard-nav-item">
					<a href="#my-disputes">
						<svg class="dashboard-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
						<span><?php esc_html_e( 'My Disputes', 'fraud-tenant-theme' ); ?></span>
					</a>
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
				<strong><?php esc_html_e( 'Know Your Rights', 'fraud-tenant-theme' ); ?></strong>
				<p style="margin: 0 0 8px 0;"><?php esc_html_e( 'Under FCRA regulations, tenants possess the right to dispute inaccurate records.', 'fraud-tenant-theme' ); ?></p>
				<a href="<?php echo esc_url( home_url( '/tenant-rights/' ) ); ?>"><?php esc_html_e( 'Tenant Rights Guide →', 'fraud-tenant-theme' ); ?></a>
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

		<!-- Welcome Header Banner (Showcase Match) -->
		<div class="dashboard-welcome-banner">
			<div class="dashboard-welcome-text">
				<h1><?php printf( /* translators: %s: First name */ esc_html__( 'Welcome back, %s!', 'fraud-tenant-theme' ), esc_html( $first_name ) ); ?></h1>
				<p><?php esc_html_e( 'Manage your rental information and account activity.', 'fraud-tenant-theme' ); ?></p>
			</div>
		</div>

		<!-- 3-Column Stat Metrics Row (Showcase Match) -->
		<div class="dashboard-metrics-row metrics-3col">
			<!-- Metric 1: My Records -->
			<div class="metric-card">
				<div class="metric-data-side">
					<span class="metric-label"><?php esc_html_e( 'My Records', 'fraud-tenant-theme' ); ?></span>
					<span class="metric-value"><?php echo (int) $data['records_count']; ?></span>
					<span class="metric-tag">
						<?php
						if ( 0 === $data['records_count'] ) {
							esc_html_e( 'No Disclosed Incidents', 'fraud-tenant-theme' );
						} else {
							printf( /* translators: %d: count */ esc_html__( '%d Disclosed Record(s)', 'fraud-tenant-theme' ), (int) $data['records_count'] );
						}
						?>
					</span>
				</div>
				<div class="metric-icon-circle metric-icon-blue">
					<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
				</div>
			</div>

			<!-- Metric 2: My Disputes -->
			<div class="metric-card">
				<div class="metric-data-side">
					<span class="metric-label"><?php esc_html_e( 'My Disputes', 'fraud-tenant-theme' ); ?></span>
					<span class="metric-value"><?php echo (int) $data['disputes_count']; ?></span>
					<span class="metric-tag" style="color:#0284C7;"><?php esc_html_e( 'Active Inquiries', 'fraud-tenant-theme' ); ?></span>
				</div>
				<div class="metric-icon-circle metric-icon-teal">
					<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
				</div>
			</div>

			<!-- Metric 3: Notifications -->
			<div class="metric-card">
				<div class="metric-data-side">
					<span class="metric-label"><?php esc_html_e( 'Notifications', 'fraud-tenant-theme' ); ?></span>
					<span class="metric-value"><?php echo (int) count( $data['notifications'] ); ?></span>
					<span class="metric-tag" style="color:#D97706;"><?php esc_html_e( 'Account Alerts', 'fraud-tenant-theme' ); ?></span>
				</div>
				<div class="metric-icon-circle metric-icon-amber">
					<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
				</div>
			</div>
		</div>

		<!-- Two Column Content Grid -->
		<div class="dashboard-grid-2col">
			
			<!-- Left Column (Primary Content) -->
			<div class="dash-grid-left">

				<!-- Recent Activity Timeline (Showcase Match) -->
				<div class="dash-panel" id="recent-activity">
					<div class="dash-panel-header">
						<h2 class="dash-panel-title"><?php esc_html_e( 'Recent Activity', 'fraud-tenant-theme' ); ?></h2>
					</div>

					<div class="dash-timeline">
						<?php if ( ! empty( $data['recent_activity'] ) ) : ?>
							<?php foreach ( $data['recent_activity'] as $act ) : ?>
								<div class="dash-timeline-item">
									<div class="dash-timeline-icon">
										<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0E9488" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
									</div>
									<div class="dash-timeline-content">
										<div class="dash-timeline-title">
											<?php echo esc_html( $act['title'] ?? ucwords( str_replace( array( '.', '_' ), ' ', is_array( $act ) ? $act['action'] : $act->action ) ) ); ?>
										</div>
										<div class="dash-timeline-time">
											<?php echo esc_html( gmdate( 'M j, Y - g:i A', strtotime( is_array( $act ) ? $act['created_at'] : $act->created_at ) ) ); ?>
										</div>
									</div>
								</div>
							<?php endforeach; ?>
						<?php else : ?>
							<div class="dash-empty-state" style="padding: 24px 16px;">
								<p style="color: #64748B; margin: 0; font-size: 0.875rem;"><?php esc_html_e( 'No recent activity recorded for your account.', 'fraud-tenant-theme' ); ?></p>
							</div>
						<?php endif; ?>
					</div>
				</div>

				<!-- Residential Residence Information Panel -->
				<div class="dash-panel" id="residence-history">
					<div class="dash-panel-header">
						<h2 class="dash-panel-title"><?php esc_html_e( 'Residential Information on File', 'fraud-tenant-theme' ); ?></h2>
					</div>

					<div class="dash-detail-list">
						<div class="dash-detail-row">
							<span class="dash-detail-key"><?php esc_html_e( 'Current Address on File', 'fraud-tenant-theme' ); ?></span>
							<span class="dash-detail-val">
								<?php
								if ( ! empty( $user_profile?->address_line1 ) ) {
									echo esc_html( $user_profile->address_line1 . ', ' . $user_profile->city . ', ' . $user_profile->state . ' ' . $user_profile->postal_code );
								} else {
									echo esc_html__( 'Not specified', 'fraud-tenant-theme' );
								}
								?>
							</span>
						</div>
						<div class="dash-detail-row">
							<span class="dash-detail-key"><?php esc_html_e( 'Previous Address Reported During Registration', 'fraud-tenant-theme' ); ?></span>
							<span class="dash-detail-val">
								<?php
								if ( ! empty( $user_profile?->prev_address_line1 ) ) {
									echo esc_html( $user_profile->prev_address_line1 . ', ' . $user_profile->prev_city . ', ' . $user_profile->prev_state . ' ' . $user_profile->prev_postal_code );
								} else {
									echo esc_html__( 'None reported during registration', 'fraud-tenant-theme' );
								}
								?>
							</span>
						</div>
						<div class="dash-detail-row">
							<span class="dash-detail-key"><?php esc_html_e( 'Identity Token Security', 'fraud-tenant-theme' ); ?></span>
							<span class="dash-detail-val" style="color:#0E9488;">
								&bull;&bull;&bull;&bull; <?php esc_html_e( 'Identity Matching Token Active (HMAC Protected)', 'fraud-tenant-theme' ); ?>
							</span>
						</div>
					</div>
				</div>

				<!-- My Rental Records Panel -->
				<div class="dash-panel" id="my-records">
					<div class="dash-panel-header">
						<h2 class="dash-panel-title"><?php esc_html_e( 'My Rental Records', 'fraud-tenant-theme' ); ?></h2>
					</div>

					<?php if ( empty( $data['records'] ) ) : ?>
						<div class="dash-empty-state">
							<div class="dash-empty-icon" style="color:#0E9488;">
								<svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
							</div>
							<h4><?php esc_html_e( 'No Disclosable Incident Records Available', 'fraud-tenant-theme' ); ?></h4>
							<p><?php esc_html_e( 'No disclosable incident records are currently available for your account. Please note: Available platform information may be incomplete and does not certify a completely clear rental history.', 'fraud-tenant-theme' ); ?></p>
						</div>
					<?php else : ?>
						<div class="dash-table-wrapper">
							<table class="dash-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Category', 'fraud-tenant-theme' ); ?></th>
										<th><?php esc_html_e( 'Incident Date', 'fraud-tenant-theme' ); ?></th>
										<th><?php esc_html_e( 'Disclosed Status', 'fraud-tenant-theme' ); ?></th>
										<th><?php esc_html_e( 'Action', 'fraud-tenant-theme' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $data['records'] as $rec ) : ?>
										<tr>
											<td><strong><?php echo esc_html( ucwords( str_replace( '_', ' ', $rec->category ) ) ); ?></strong></td>
											<td><?php echo esc_html( gmdate( 'M j, Y', strtotime( $rec->incident_date ) ) ); ?></td>
											<td><span class="badge-dash badge-dash-verified"><?php esc_html_e( 'Verified Record', 'fraud-tenant-theme' ); ?></span></td>
											<td>
												<button type="button" class="dash-panel-link" data-ft-modal-target="ftDisputeModal" style="background:none;border:none;cursor:pointer;">
													<?php esc_html_e( 'Dispute Record', 'fraud-tenant-theme' ); ?>
												</button>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</div>

			</div>

			<!-- Right Column (Rights & Profile) -->
			<div class="dash-grid-right">

				<!-- Your Rights Matter Card (Showcase Match) -->
				<div class="rights-card">
					<h3>
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
						<span><?php esc_html_e( 'Your Rights Matter', 'fraud-tenant-theme' ); ?></span>
					</h3>
					<p><?php esc_html_e( 'You can view your own records and request corrections if something appears incorrect.', 'fraud-tenant-theme' ); ?></p>
					
					<a href="#my-records" class="rights-card-btn">
						<span><?php esc_html_e( 'View My Records', 'fraud-tenant-theme' ); ?></span>
						<span>&rarr;</span>
					</a>
				</div>

				<!-- Dispute Information Card (FCRA Compliance) -->
				<div class="dash-panel">
					<div class="dash-panel-header">
						<h3 class="dash-panel-title" id="my-disputes"><?php esc_html_e( 'Dispute Resolution', 'fraud-tenant-theme' ); ?></h3>
					</div>

					<p style="font-size:13px; color:var(--dash-muted); line-height:1.5; margin:0 0 14px 0;">
						<?php esc_html_e( 'Under the Fair Credit Reporting Act (FCRA), tenants have the right to challenge inaccurate or incomplete information. The full interactive dispute submission portal is scheduled for Phase 8.', 'fraud-tenant-theme' ); ?>
					</p>

					<div style="background:#F8FAFC; border:1px solid #E2E8F0; padding:12px; border-radius:8px; font-size:12px; color:var(--dash-muted); margin-bottom:14px;">
						<strong style="color:var(--dash-navy); display:block; margin-bottom:4px;"><?php esc_html_e( 'Dispute Protection Process:', 'fraud-tenant-theme' ); ?></strong>
						&bull; <?php esc_html_e( 'Submit written clarification statement', 'fraud-tenant-theme' ); ?><br>
						&bull; <?php esc_html_e( 'Independent compliance investigation', 'fraud-tenant-theme' ); ?><br>
						&bull; <?php esc_html_e( '30-day resolution timeline under FCRA', 'fraud-tenant-theme' ); ?>
					</div>

					<button type="button" class="dash-promo-btn" data-ft-modal-target="ftDisputeModal" style="background-color:var(--dash-navy); padding:10px 14px; font-size:13px;">
						<span><?php esc_html_e( 'Learn About Disputes', 'fraud-tenant-theme' ); ?></span>
					</button>
				</div>

				<!-- Account & Contact Profile Card -->
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

	</main>
</div>

<!-- ============================================================ -->
<!-- MODALS -->
<!-- ============================================================ -->

<!-- Modal 1: Dispute Resolution Notice (Phase 8 Workflow) -->
<div class="ft-modal-overlay" id="ftDisputeModal" role="dialog" aria-modal="true" aria-labelledby="ftDisputeTitle">
	<div class="ft-modal-box">
		<button type="button" class="ft-modal-close" aria-label="<?php esc_attr_e( 'Close', 'fraud-tenant-theme' ); ?>">&times;</button>
		<h3 class="ft-modal-title" id="ftDisputeTitle"><?php esc_html_e( 'Tenant Dispute Process', 'fraud-tenant-theme' ); ?></h3>
		<p class="ft-modal-desc">
			<?php esc_html_e( 'The automated dispute submission portal and evidence upload workflow will be fully activated in Phase 8. If an active record is disclosed on your file, you may contact compliance support to lodge a dispute immediately.', 'fraud-tenant-theme' ); ?>
		</p>
		<div style="background:#F0FDF4; border:1px solid #BBF7D0; padding:14px; border-radius:8px; font-size:13px; color:#166534; margin-bottom:18px;">
			<strong style="display:block; margin-bottom:4px;"><?php esc_html_e( 'Your FCRA Rights:', 'fraud-tenant-theme' ); ?></strong>
			<?php esc_html_e( 'You have the right to request investigation of any reported incident. Landlords must provide documentation, and unverified allegations will be removed.', 'fraud-tenant-theme' ); ?>
		</div>
		<button type="button" class="dashboard-btn-add" data-ft-modal-close style="width:100%; justify-content:center;">
			<?php esc_html_e( 'Understood', 'fraud-tenant-theme' ); ?>
		</button>
	</div>
</div>

<!-- Modal 2: Edit Profile Settings (Safe Phone Update) -->
<div class="ft-modal-overlay" id="ftProfileModal" role="dialog" aria-modal="true" aria-labelledby="ftProfTitle">
	<div class="ft-modal-box">
		<button type="button" class="ft-modal-close" aria-label="<?php esc_attr_e( 'Close', 'fraud-tenant-theme' ); ?>">&times;</button>
		<h3 class="ft-modal-title" id="ftProfTitle"><?php esc_html_e( 'Update Profile Contact', 'fraud-tenant-theme' ); ?></h3>
		<p class="ft-modal-desc">
			<?php esc_html_e( 'Update your verified contact telephone number.', 'fraud-tenant-theme' ); ?>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ft_update_profile" />
			<input type="hidden" name="redirect_to" value="<?php echo esc_url( home_url( '/tenant-dashboard/' ) ); ?>" />
			<?php wp_nonce_field( 'ft_update_profile_' . $tenant_id, 'ft_profile_nonce' ); ?>

			<div style="margin-bottom:16px;">
				<label for="tenant_phone_edit" style="display:block; font-size:12px; font-weight:600; color:var(--dash-navy); margin-bottom:6px;">
					<?php esc_html_e( 'Phone Number', 'fraud-tenant-theme' ); ?>
				</label>
				<input type="tel" id="tenant_phone_edit" name="phone" value="<?php echo esc_attr( $user_profile?->phone ?? '' ); ?>" class="search-ssn-input" style="height:42px; background:#FFFFFF;" placeholder="(555) 000-0000" required />
			</div>

			<button type="submit" class="dashboard-btn-add" style="width:100%; justify-content:center;">
				<?php esc_html_e( 'Save Phone Number', 'fraud-tenant-theme' ); ?>
			</button>
		</form>
	</div>
</div>

<!-- Modal 3: Notifications Modal -->
<div class="ft-modal-overlay" id="ftNotificationsModal" role="dialog" aria-modal="true" aria-labelledby="ftNotifTitle">
	<div class="ft-modal-box">
		<button type="button" class="ft-modal-close" aria-label="<?php esc_attr_e( 'Close', 'fraud-tenant-theme' ); ?>">&times;</button>
		<h3 class="ft-modal-title" id="ftNotifTitle"><?php esc_html_e( 'Notifications', 'fraud-tenant-theme' ); ?></h3>
		<div style="padding:20px 0; text-align:center; color:var(--dash-muted); font-size:14px;">
			<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#CBD5E1" stroke-width="2" style="margin-bottom:8px;"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
			<p style="margin:0;"><?php esc_html_e( 'You have no unread notifications.', 'fraud-tenant-theme' ); ?></p>
		</div>
		<button type="button" class="dashboard-btn-add" data-ft-modal-close style="width:100%; justify-content:center;">
			<?php esc_html_e( 'Close', 'fraud-tenant-theme' ); ?>
		</button>
	</div>
</div>

<?php
get_footer();

