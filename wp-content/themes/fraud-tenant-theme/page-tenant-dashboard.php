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
				<strong><?php esc_html_e( 'Factual Corrections', 'fraud-tenant-theme' ); ?></strong>
				<p style="margin: 0 0 8px 0;"><?php esc_html_e( 'Tenants may submit factual clarifications or disputes regarding reported records.', 'fraud-tenant-theme' ); ?></p>
				<a href="<?php echo esc_url( home_url( '/tenant-rights/' ) ); ?>"><?php esc_html_e( 'Dispute Guidelines →', 'fraud-tenant-theme' ); ?></a>
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
										<th><?php esc_html_e( 'Reference', 'fraud-tenant-theme' ); ?></th>
										<th><?php esc_html_e( 'Category', 'fraud-tenant-theme' ); ?></th>
										<th><?php esc_html_e( 'Property Address', 'fraud-tenant-theme' ); ?></th>
										<th><?php esc_html_e( 'Incident Date', 'fraud-tenant-theme' ); ?></th>
										<th><?php esc_html_e( 'Amount', 'fraud-tenant-theme' ); ?></th>
										<th><?php esc_html_e( 'Disclosed Status', 'fraud-tenant-theme' ); ?></th>
										<th><?php esc_html_e( 'Action', 'fraud-tenant-theme' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $data['records'] as $rec ) : ?>
										<?php
										$rec_ref   = $rec->reference ?? sprintf( 'INC-%06d', $rec->id );
										$rec_cat   = $rec->category_label ?? ucwords( str_replace( '_', ' ', $rec->category ) );
										$rec_amt   = $rec->amount_formatted ?? ( '$' . number_format( (float) $rec->amount_involved, 2 ) );
										$rec_prop  = ! empty( $rec->property_street ) ? ( $rec->property_street . ( ! empty( $rec->property_unit ) ? ' Unit ' . $rec->property_unit : '' ) . ', ' . $rec->property_city ) : ( ! empty( $rec->property_title ) ? $rec->property_title : __( 'Property #' . $rec->property_id, 'fraud-tenant-theme' ) );
										?>
										<tr>
											<td><strong style="color:var(--dash-navy); font-family:monospace;"><?php echo esc_html( $rec_ref ); ?></strong></td>
											<td><strong><?php echo esc_html( $rec_cat ); ?></strong></td>
											<td style="font-size:12px; color:#475569;"><?php echo esc_html( $rec_prop ); ?></td>
											<td><?php echo esc_html( gmdate( 'M j, Y', strtotime( $rec->incident_date ) ) ); ?></td>
											<td><strong style="color:#0E9488;"><?php echo esc_html( $rec_amt ); ?></strong></td>
											<td><span class="badge-dash badge-dash-verified"><?php esc_html_e( 'Approved & Disclosed', 'fraud-tenant-theme' ); ?></span></td>
											<td style="white-space:nowrap;">
												<button type="button" class="dash-panel-link" data-ft-modal-target="ftReportDetailModal-<?php echo (int) $rec->id; ?>" style="background:none;border:none;cursor:pointer;color:#0E9488;font-weight:600;margin-right:8px;">
													<?php esc_html_e( 'View Details', 'fraud-tenant-theme' ); ?>
												</button>
												<button type="button" class="dash-panel-link" data-ft-modal-target="ftDisputeSubmitModal-<?php echo (int) $rec->id; ?>" style="background:none;border:none;cursor:pointer;color:#64748B;">
													<?php esc_html_e( 'Dispute', 'fraud-tenant-theme' ); ?>
												</button>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</div>

				<!-- My Disputes Panel -->
				<div class="dash-panel" id="my-disputes" style="margin-top:24px;">
					<div class="dash-panel-header">
						<h2 class="dash-panel-title"><?php esc_html_e( 'My Disputes', 'fraud-tenant-theme' ); ?></h2>
					</div>

					<?php if ( empty( $data['disputes'] ) ) : ?>
						<div class="dash-empty-state">
							<div class="dash-empty-icon" style="color:#0E9488;">
								<svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
							</div>
							<h4><?php esc_html_e( 'No Disputes Submitted', 'fraud-tenant-theme' ); ?></h4>
							<p><?php esc_html_e( 'You have not submitted any disputes regarding your rental records. If you believe a record contains factual errors, you can dispute it directly from the record details.', 'fraud-tenant-theme' ); ?></p>
						</div>
					<?php else : ?>
						<div class="dash-table-wrapper">
							<table class="dash-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Dispute Ref', 'fraud-tenant-theme' ); ?></th>
										<th><?php esc_html_e( 'Report Ref', 'fraud-tenant-theme' ); ?></th>
										<th><?php esc_html_e( 'Reason', 'fraud-tenant-theme' ); ?></th>
										<th><?php esc_html_e( 'Status', 'fraud-tenant-theme' ); ?></th>
										<th><?php esc_html_e( 'Submitted', 'fraud-tenant-theme' ); ?></th>
										<th><?php esc_html_e( 'Action', 'fraud-tenant-theme' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $data['disputes'] as $dsp ) : ?>
										<tr>
											<td><strong style="color:var(--dash-navy); font-family:monospace;"><?php echo esc_html( $dsp->reference ); ?></strong></td>
											<td><strong style="color:#0E9488; font-family:monospace;"><?php echo esc_html( $dsp->report_reference ); ?></strong></td>
											<td><?php echo esc_html( $dsp->reason_label ); ?></td>
											<td><span class="badge-dash <?php echo esc_attr( $dsp->status_badge_class ); ?>"><?php echo esc_html( $dsp->status_label ); ?></span></td>
											<td><?php echo esc_html( $dsp->created_at_fmt ); ?></td>
											<td>
												<button type="button" class="dash-panel-link" data-ft-modal-target="ftDisputeDetailModal-<?php echo (int) $dsp->id; ?>" style="background:none;border:none;cursor:pointer;color:#0E9488;font-weight:600;">
													<?php esc_html_e( 'View Details', 'fraud-tenant-theme' ); ?>
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

				<!-- Dispute Information Card (Neutral Platform Review) -->
				<div class="dash-panel">
					<div class="dash-panel-header">
						<h3 class="dash-panel-title" id="my-disputes"><?php esc_html_e( 'Dispute Resolution', 'fraud-tenant-theme' ); ?></h3>
					</div>

					<p style="font-size:13px; color:var(--dash-muted); line-height:1.5; margin:0 0 14px 0;">
						<?php esc_html_e( 'You may submit a dispute regarding factual information in an associated report through the platform\'s dispute process. Submitted statements and documentation are reviewed by an authorized administrator.', 'fraud-tenant-theme' ); ?>
					</p>

					<div style="background:#F8FAFC; border:1px solid #E2E8F0; padding:12px; border-radius:8px; font-size:12px; color:var(--dash-muted); margin-bottom:14px;">
						<strong style="color:var(--dash-navy); display:block; margin-bottom:4px;"><?php esc_html_e( 'Dispute Review Process:', 'fraud-tenant-theme' ); ?></strong>
						&bull; <?php esc_html_e( 'Submit written clarification and evidence', 'fraud-tenant-theme' ); ?><br>
						&bull; <?php esc_html_e( 'Administrative review and verification', 'fraud-tenant-theme' ); ?><br>
						&bull; <?php esc_html_e( 'Direct notice of review determination', 'fraud-tenant-theme' ); ?>
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

<?php if ( ! empty( $data['records'] ) ) : ?>
	<?php foreach ( $data['records'] as $rec ) : ?>
		<?php
		$rec_ref  = $rec->reference ?? sprintf( 'INC-%06d', $rec->id );
		$rec_cat  = $rec->category_label ?? ucwords( str_replace( '_', ' ', $rec->category ) );
		$rec_amt  = $rec->amount_formatted ?? ( '$' . number_format( (float) $rec->amount_involved, 2 ) );
		$rec_prop = ! empty( $rec->property_street ) ? ( $rec->property_street . ( ! empty( $rec->property_unit ) ? ' Unit ' . $rec->property_unit : '' ) . ', ' . $rec->property_city . ', ' . $rec->property_state . ' ' . $rec->property_zip ) : ( $rec->property_title ?? '' );
		?>
		<div class="ft-modal-overlay" id="ftReportDetailModal-<?php echo (int) $rec->id; ?>" role="dialog" aria-modal="true" aria-labelledby="ftReportDetailTitle-<?php echo (int) $rec->id; ?>">
			<div class="ft-modal-box" style="max-width: 600px;">
				<button type="button" class="ft-modal-close" aria-label="<?php esc_attr_e( 'Close', 'fraud-tenant-theme' ); ?>">&times;</button>
				<div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
					<span class="badge-dash badge-dash-verified" style="font-size:12px;"><?php esc_html_e( 'Approved & Disclosed Record', 'fraud-tenant-theme' ); ?></span>
					<strong style="color:var(--dash-navy); font-family:monospace; font-size:14px;"><?php echo esc_html( $rec_ref ); ?></strong>
				</div>
				<h3 class="ft-modal-title" id="ftReportDetailTitle-<?php echo (int) $rec->id; ?>" style="margin-bottom:6px;">
					<?php echo esc_html( $rec_cat ); ?>
				</h3>
				<p style="color:#64748B; font-size:13px; margin:0 0 16px;">
					<?php printf( esc_html__( 'Incident Date: %s | Amount: %s', 'fraud-tenant-theme' ), esc_html( gmdate( 'F j, Y', strtotime( $rec->incident_date ) ) ), esc_html( $rec_amt ) ); ?>
				</p>

				<div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:14px; margin-bottom:16px; font-size:13px;">
					<strong style="color:var(--dash-navy); display:block; margin-bottom:4px;"><?php esc_html_e( 'Property Information:', 'fraud-tenant-theme' ); ?></strong>
					<span style="color:#475569;"><?php echo esc_html( $rec_prop ); ?></span>
				</div>

				<div style="margin-bottom:16px;">
					<strong style="color:var(--dash-navy); font-size:13px; display:block; margin-bottom:6px;"><?php esc_html_e( 'Factual Description Submitted by Landlord:', 'fraud-tenant-theme' ); ?></strong>
					<div style="background:#FFFFFF; border:1px solid #E2E8F0; border-radius:8px; padding:12px 14px; font-size:13px; color:#334155; line-height:1.6; max-height:160px; overflow-y:auto; white-space:pre-wrap;">
						<?php echo esc_html( $rec->factual_description ); ?>
					</div>
				</div>

				<?php if ( ! empty( $rec->evidence_docs ) ) : ?>
					<div style="margin-bottom:18px;">
						<strong style="color:var(--dash-navy); font-size:13px; display:block; margin-bottom:6px;">
							<?php esc_html_e( 'Attached Documentation & Evidence:', 'fraud-tenant-theme' ); ?>
						</strong>
						<ul style="margin:0; padding:0; list-style:none;">
							<?php foreach ( $rec->evidence_docs as $edoc ) : ?>
								<li style="padding:8px 10px; border:1px solid #E2E8F0; border-radius:6px; margin-bottom:6px; display:flex; align-items:center; justify-content:space-between; font-size:12px;">
									<span style="font-weight:600; color:#334155;"><?php echo esc_html( $edoc->original_name ); ?> <span style="font-weight:normal; color:#64748B;">(<?php echo esc_html( $edoc->file_size_fmt ); ?>)</span></span>
									<a href="<?php echo esc_url( $edoc->view_url ); ?>" target="_blank" class="dash-panel-link" style="font-weight:600; color:#0E9488;">
										<?php esc_html_e( 'View File', 'fraud-tenant-theme' ); ?> &rarr;
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<div style="display:flex; gap:10px; justify-content:flex-end; padding-top:12px; border-top:1px solid #E2E8F0;">
					<button type="button" class="button" data-ft-modal-close style="border-radius:6px; padding:8px 16px;">
						<?php esc_html_e( 'Close', 'fraud-tenant-theme' ); ?>
					</button>
					<button type="button" class="button" data-ft-modal-target="ftDisputeSubmitModal-<?php echo (int) $rec->id; ?>" style="background:#0E9488; color:#FFFFFF; border:none; border-radius:6px; padding:8px 16px; font-weight:600; cursor:pointer;">
						<?php esc_html_e( 'Dispute This Record', 'fraud-tenant-theme' ); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Modal: Dispute Submission for Record #<?php echo (int) $rec->id; ?> -->
		<div class="ft-modal-overlay" id="ftDisputeSubmitModal-<?php echo (int) $rec->id; ?>" role="dialog" aria-modal="true" aria-labelledby="ftDisputeSubmitTitle-<?php echo (int) $rec->id; ?>">
			<div class="ft-modal-box" style="max-width: 600px;">
				<button type="button" class="ft-modal-close" aria-label="<?php esc_attr_e( 'Close', 'fraud-tenant-theme' ); ?>">&times;</button>
				<div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
					<span class="badge-dash badge-dash-review" style="font-size:12px;"><?php esc_html_e( 'File Factual Dispute', 'fraud-tenant-theme' ); ?></span>
					<strong style="color:var(--dash-navy); font-family:monospace; font-size:14px;"><?php echo esc_html( $rec_ref ); ?></strong>
				</div>
				<h3 class="ft-modal-title" id="ftDisputeSubmitTitle-<?php echo (int) $rec->id; ?>" style="margin-bottom:6px;">
					<?php printf( esc_html__( 'Dispute Record: %s', 'fraud-tenant-theme' ), esc_html( $rec_cat ) ); ?>
				</h3>
				<p class="ft-modal-desc">
					<?php esc_html_e( 'Submit factual clarifications and optional supporting evidence. Submissions are reviewed by an authorized administrator.', 'fraud-tenant-theme' ); ?>
				</p>
				<div style="background:#F0FDF4; border:1px solid #BBF7D0; padding:12px; border-radius:8px; font-size:12px; color:#166534; margin-bottom:16px;">
					<strong><?php esc_html_e( 'Neutral Review Notice:', 'fraud-tenant-theme' ); ?></strong>
					<?php esc_html_e( 'All dispute submissions and evidence are reviewed individually by an authorized administrator. Submitting a dispute records your factual challenge and queued review determination.', 'fraud-tenant-theme' ); ?>
				</div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
					<input type="hidden" name="action" value="ft_submit_tenant_dispute" />
					<input type="hidden" name="report_id" value="<?php echo (int) $rec->id; ?>" />
					<input type="hidden" name="redirect_to" value="<?php echo esc_url( home_url( '/tenant-dashboard/#my-disputes' ) ); ?>" />
					<?php wp_nonce_field( 'ft_submit_dispute_' . $tenant_id, 'ft_dispute_nonce' ); ?>

					<div style="margin-bottom:16px;">
						<label for="dispute_reason_<?php echo (int) $rec->id; ?>" style="display:block; font-size:12px; font-weight:600; color:var(--dash-navy); margin-bottom:6px;">
							<?php esc_html_e( 'Dispute Reason Category', 'fraud-tenant-theme' ); ?> <span style="color:#DC2626;">*</span>
						</label>
						<select id="dispute_reason_<?php echo (int) $rec->id; ?>" name="dispute_reason" style="width:100%; height:40px; border-radius:6px; border:1px solid #CBD5E1; padding:0 10px; font-size:13px;" required>
							<option value=""><?php esc_html_e( '-- Select Reason Category --', 'fraud-tenant-theme' ); ?></option>
							<option value="inaccurate_record"><?php esc_html_e( 'Inaccurate Record', 'fraud-tenant-theme' ); ?></option>
							<option value="incorrect_amount"><?php esc_html_e( 'Incorrect Amount / Disputed Balance', 'fraud-tenant-theme' ); ?></option>
							<option value="identity_mistake"><?php esc_html_e( 'Mistaken Identity / Not Me', 'fraud-tenant-theme' ); ?></option>
							<option value="already_paid"><?php esc_html_e( 'Balance Already Paid / Satisfied', 'fraud-tenant-theme' ); ?></option>
							<option value="unauthorized_report"><?php esc_html_e( 'Unauthorized Report / Invalid Tenancy', 'fraud-tenant-theme' ); ?></option>
							<option value="other"><?php esc_html_e( 'Other Factual Inaccuracy', 'fraud-tenant-theme' ); ?></option>
						</select>
					</div>

					<div style="margin-bottom:16px;">
						<label for="statement_<?php echo (int) $rec->id; ?>" style="display:block; font-size:12px; font-weight:600; color:var(--dash-navy); margin-bottom:6px;">
							<?php esc_html_e( 'Factual Dispute Statement', 'fraud-tenant-theme' ); ?> <span style="color:#DC2626;">*</span>
						</label>
						<textarea id="statement_<?php echo (int) $rec->id; ?>" name="statement" rows="4" style="width:100%; border-radius:6px; border:1px solid #CBD5E1; padding:10px; font-size:13px;" placeholder="<?php esc_attr_e( 'Provide a clear factual explanation of the inaccuracy (min 10 characters)...', 'fraud-tenant-theme' ); ?>" minlength="10" maxlength="3000" required></textarea>
						<span style="font-size:11px; color:#64748B;"><?php esc_html_e( 'Minimum 10 characters, maximum 3,000 characters.', 'fraud-tenant-theme' ); ?></span>
					</div>

					<div style="margin-bottom:20px;">
						<label for="evidence_<?php echo (int) $rec->id; ?>" style="display:block; font-size:12px; font-weight:600; color:var(--dash-navy); margin-bottom:6px;">
							<?php esc_html_e( 'Supporting Evidence (Optional)', 'fraud-tenant-theme' ); ?>
						</label>
						<input type="file" id="evidence_<?php echo (int) $rec->id; ?>" name="dispute_evidence" accept=".pdf,.png,.jpg,.jpeg" style="width:100%; font-size:13px;" />
						<span style="font-size:11px; color:#64748B;"><?php esc_html_e( 'Accepted formats: PDF, PNG, JPG (max 10MB). Stored securely and privately.', 'fraud-tenant-theme' ); ?></span>
					</div>

					<div style="display:flex; gap:10px; justify-content:flex-end;">
						<button type="button" class="button" data-ft-modal-close style="border-radius:6px; padding:8px 16px;">
							<?php esc_html_e( 'Cancel', 'fraud-tenant-theme' ); ?>
						</button>
						<button type="submit" class="button" style="background:#0E9488; color:#FFFFFF; border:none; border-radius:6px; padding:8px 20px; font-weight:600; cursor:pointer;">
							<?php esc_html_e( 'Submit Dispute', 'fraud-tenant-theme' ); ?>
						</button>
					</div>
				</form>
			</div>
		</div>
	<?php endforeach; ?>
<?php endif; ?>

<?php if ( ! empty( $data['disputes'] ) ) : ?>
	<?php foreach ( $data['disputes'] as $dsp ) : ?>
		<!-- Modal: Dispute Details for Dispute #<?php echo (int) $dsp->id; ?> -->
		<div class="ft-modal-overlay" id="ftDisputeDetailModal-<?php echo (int) $dsp->id; ?>" role="dialog" aria-modal="true" aria-labelledby="ftDisputeDetailTitle-<?php echo (int) $dsp->id; ?>">
			<div class="ft-modal-box" style="max-width: 600px;">
				<button type="button" class="ft-modal-close" aria-label="<?php esc_attr_e( 'Close', 'fraud-tenant-theme' ); ?>">&times;</button>
				<div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
					<span class="badge-dash <?php echo esc_attr( $dsp->status_badge_class ); ?>"><?php echo esc_html( $dsp->status_label ); ?></span>
					<strong style="color:var(--dash-navy); font-family:monospace; font-size:14px;"><?php echo esc_html( $dsp->reference ); ?></strong>
				</div>
				<h3 class="ft-modal-title" id="ftDisputeDetailTitle-<?php echo (int) $dsp->id; ?>" style="margin-bottom:6px;">
					<?php printf( esc_html__( 'Dispute: %s', 'fraud-tenant-theme' ), esc_html( $dsp->reason_label ) ); ?>
				</h3>
				<p style="color:#64748B; font-size:13px; margin:0 0 16px;">
					<?php printf( esc_html__( 'Submitted: %s | Related Record: %s', 'fraud-tenant-theme' ), esc_html( $dsp->created_at_fmt ), esc_html( $dsp->report_reference ) ); ?>
				</p>

				<div style="margin-bottom:16px;">
					<strong style="color:var(--dash-navy); font-size:13px; display:block; margin-bottom:6px;"><?php esc_html_e( 'Your Submitted Dispute Statement:', 'fraud-tenant-theme' ); ?></strong>
					<div style="background:#FFFFFF; border:1px solid #E2E8F0; border-radius:8px; padding:12px 14px; font-size:13px; color:#334155; line-height:1.6; max-height:160px; overflow-y:auto; white-space:pre-wrap;">
						<?php echo esc_html( $dsp->statement ); ?>
					</div>
				</div>

				<?php if ( ! empty( $dsp->evidence_docs ) ) : ?>
					<div style="margin-bottom:18px;">
						<strong style="color:var(--dash-navy); font-size:13px; display:block; margin-bottom:6px;"><?php esc_html_e( 'Attached Documentation & Evidence:', 'fraud-tenant-theme' ); ?></strong>
						<ul style="margin:0; padding:0; list-style:none;">
							<?php foreach ( $dsp->evidence_docs as $edoc ) : ?>
								<li style="padding:8px 10px; border:1px solid #E2E8F0; border-radius:6px; margin-bottom:6px; display:flex; align-items:center; justify-content:space-between; font-size:12px; background:#F8FAFC;">
									<span style="font-weight:600; color:#334155;"><?php echo esc_html( $edoc->original_name ); ?> <span style="font-weight:normal; color:#64748B;">(<?php echo esc_html( $edoc->file_size_fmt ); ?>)</span></span>
									<a href="<?php echo esc_url( $edoc->view_url ); ?>" target="_blank" class="dash-panel-link" style="font-weight:600; color:#0E9488;">
										<?php esc_html_e( 'View File', 'fraud-tenant-theme' ); ?> &rarr;
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<?php if ( in_array( $dsp->status, array( 'resolved', 'rejected' ), true ) && ! empty( $dsp->resolution_notes ) ) : ?>
					<div style="background:#F8FAFC; border:1px solid #CBD5E1; border-radius:8px; padding:14px; margin-bottom:16px;">
						<strong style="color:var(--dash-navy); font-size:13px; display:block; margin-bottom:4px;">
							<?php esc_html_e( 'Administrative Determination Notes:', 'fraud-tenant-theme' ); ?>
						</strong>
						<p style="font-size:13px; color:#334155; margin:0; line-height:1.5; white-space:pre-wrap;">
							<?php echo esc_html( $dsp->resolution_notes ); ?>
						</p>
						<?php if ( ! empty( $dsp->resolved_at_fmt ) && '—' !== $dsp->resolved_at_fmt ) : ?>
							<span style="font-size:11px; color:#64748B; display:block; margin-top:6px;">
								<?php printf( esc_html__( 'Determination Date: %s', 'fraud-tenant-theme' ), esc_html( $dsp->resolved_at_fmt ) ); ?>
							</span>
						<?php endif; ?>
						<div style="margin-top:8px; padding-top:8px; border-top:1px solid #E2E8F0; font-size:11px; color:#64748B; line-height:1.4;">
							<?php esc_html_e( 'Notice: An administrator recorded an administrative determination. The original incident report remains unchanged by this dispute action.', 'fraud-tenant-theme' ); ?>
						</div>
					</div>
				<?php endif; ?>

				<div style="display:flex; justify-content:flex-end; padding-top:12px; border-top:1px solid #E2E8F0;">
					<button type="button" class="button" data-ft-modal-close style="border-radius:6px; padding:8px 16px;">
						<?php esc_html_e( 'Close', 'fraud-tenant-theme' ); ?>
					</button>
				</div>
			</div>
		</div>
	<?php endforeach; ?>
<?php endif; ?>

<!-- Modal 1: Dispute Resolution Notice (Neutral Review Notice) -->
<div class="ft-modal-overlay" id="ftDisputeModal" role="dialog" aria-modal="true" aria-labelledby="ftDisputeTitle">
	<div class="ft-modal-box">
		<button type="button" class="ft-modal-close" aria-label="<?php esc_attr_e( 'Close', 'fraud-tenant-theme' ); ?>">&times;</button>
		<h3 class="ft-modal-title" id="ftDisputeTitle"><?php esc_html_e( 'Tenant Dispute Process', 'fraud-tenant-theme' ); ?></h3>
		<p class="ft-modal-desc">
			<?php esc_html_e( 'You may submit a dispute regarding factual information in this report through the platform\'s dispute process. Your statement and supporting evidence will be reviewed by an authorized administrator. Submitting a dispute does not automatically change the report or guarantee a particular outcome.', 'fraud-tenant-theme' ); ?>
		</p>
		<div style="background:#F0FDF4; border:1px solid #BBF7D0; padding:14px; border-radius:8px; font-size:13px; color:#166534; margin-bottom:18px;">
			<strong style="display:block; margin-bottom:4px;"><?php esc_html_e( 'Administrative Review Process:', 'fraud-tenant-theme' ); ?></strong>
			<?php esc_html_e( 'All dispute submissions and supporting evidence are evaluated individually by an authorized administrator. Submitting a dispute does not guarantee record removal or modification.', 'fraud-tenant-theme' ); ?>
		</div>
		<button type="button" class="dashboard-btn-add" data-ft-modal-close style="width:100%; justify-content:center;">
			<?php esc_html_e( 'Understood', 'fraud-tenant-theme' ); ?>
		</button>
	</div>
</div>

<!-- Modal 2: Account & Profile Settings -->
<div class="ft-modal-overlay" id="ftProfileModal" role="dialog" aria-modal="true" aria-labelledby="ftProfTitle">
	<div class="ft-modal-box" style="max-width: 680px; width: 100%;">
		<button type="button" class="ft-modal-close" aria-label="<?php esc_attr_e( 'Close', 'fraud-tenant-theme' ); ?>">&times;</button>
		<h3 class="ft-modal-title" id="ftProfTitle"><?php esc_html_e( 'Account & Profile Settings', 'fraud-tenant-theme' ); ?></h3>
		<p class="ft-modal-desc">
			<?php esc_html_e( 'Manage your contact details, personal address information, password, and security.', 'fraud-tenant-theme' ); ?>
		</p>

		<!-- Sub-Navigation Tabs -->
		<div class="ft-settings-nav" style="display:flex; gap:8px; border-bottom:1px solid #E2E8F0; margin-bottom:20px; padding-bottom:10px; overflow-x:auto;">
			<button type="button" class="ft-settings-tab is-active" data-tab-target="ftTenantTabProfile" style="background:none; border:none; padding:8px 14px; font-weight:700; font-size:13px; color:var(--dash-teal); border-bottom:2px solid var(--dash-teal); cursor:pointer;">
				<?php esc_html_e( 'Profile Details', 'fraud-tenant-theme' ); ?>
			</button>
			<button type="button" class="ft-settings-tab" data-tab-target="ftTenantTabPassword" style="background:none; border:none; padding:8px 14px; font-weight:600; font-size:13px; color:#64748B; cursor:pointer;">
				<?php esc_html_e( 'Security & Password', 'fraud-tenant-theme' ); ?>
			</button>
			<button type="button" class="ft-settings-tab" data-tab-target="ftTenantTabEmail" style="background:none; border:none; padding:8px 14px; font-weight:600; font-size:13px; color:#64748B; cursor:pointer;">
				<?php esc_html_e( 'Email Address', 'fraud-tenant-theme' ); ?>
			</button>
			<button type="button" class="ft-settings-tab" data-tab-target="ftTenantTabStatus" style="background:none; border:none; padding:8px 14px; font-weight:600; font-size:13px; color:#64748B; cursor:pointer;">
				<?php esc_html_e( 'Account Status', 'fraud-tenant-theme' ); ?>
			</button>
		</div>

		<!-- Panel 1: Profile Details -->
		<div class="ft-settings-panel" id="ftTenantTabProfile">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ft_update_profile" />
				<input type="hidden" name="account_section" value="profile" />
				<input type="hidden" name="redirect_to" value="<?php echo esc_url( home_url( '/tenant-dashboard/' ) ); ?>" />
				<?php wp_nonce_field( 'ft_update_profile_' . $tenant_id, 'ft_profile_nonce' ); ?>

				<div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
					<div>
						<label for="tenant_first_name" style="display:block; font-size:12px; font-weight:600; color:var(--dash-navy); margin-bottom:6px;">
							<?php esc_html_e( 'First Name', 'fraud-tenant-theme' ); ?>
						</label>
						<input type="text" id="tenant_first_name" name="first_name" value="<?php echo esc_attr( $current_user->first_name ); ?>" class="search-ssn-input" style="height:40px; background:#FFFFFF;" required />
					</div>
					<div>
						<label for="tenant_last_name" style="display:block; font-size:12px; font-weight:600; color:var(--dash-navy); margin-bottom:6px;">
							<?php esc_html_e( 'Last Name', 'fraud-tenant-theme' ); ?>
						</label>
						<input type="text" id="tenant_last_name" name="last_name" value="<?php echo esc_attr( $current_user->last_name ); ?>" class="search-ssn-input" style="height:40px; background:#FFFFFF;" required />
					</div>
				</div>

				<div style="margin-bottom:14px;">
					<label for="tenant_phone_edit" style="display:block; font-size:12px; font-weight:600; color:var(--dash-navy); margin-bottom:6px;">
						<?php esc_html_e( 'Phone Number', 'fraud-tenant-theme' ); ?>
					</label>
					<input type="tel" id="tenant_phone_edit" name="phone" value="<?php echo esc_attr( $user_profile?->phone ?? '' ); ?>" class="search-ssn-input" style="height:40px; background:#FFFFFF;" placeholder="(555) 000-0000" required />
				</div>

				<div style="margin-bottom:14px;">
					<label for="tenant_addr1" style="display:block; font-size:12px; font-weight:600; color:var(--dash-navy); margin-bottom:6px;">
						<?php esc_html_e( 'Current Street Address', 'fraud-tenant-theme' ); ?>
					</label>
					<input type="text" id="tenant_addr1" name="address_line1" value="<?php echo esc_attr( $user_profile?->address_line1 ?? '' ); ?>" class="search-ssn-input" style="height:40px; background:#FFFFFF;" placeholder="456 Elm Ave" />
				</div>

				<div style="display:grid; grid-template-columns:2fr 1fr 1fr; gap:14px; margin-bottom:20px;">
					<div>
						<label for="tenant_city" style="display:block; font-size:12px; font-weight:600; color:var(--dash-navy); margin-bottom:6px;">
							<?php esc_html_e( 'City', 'fraud-tenant-theme' ); ?>
						</label>
						<input type="text" id="tenant_city" name="city" value="<?php echo esc_attr( $user_profile?->city ?? '' ); ?>" class="search-ssn-input" style="height:40px; background:#FFFFFF;" />
					</div>
					<div>
						<label for="tenant_state" style="display:block; font-size:12px; font-weight:600; color:var(--dash-navy); margin-bottom:6px;">
							<?php esc_html_e( 'State', 'fraud-tenant-theme' ); ?>
						</label>
						<input type="text" id="tenant_state" name="state" value="<?php echo esc_attr( $user_profile?->state ?? '' ); ?>" class="search-ssn-input" style="height:40px; background:#FFFFFF;" placeholder="TX" />
					</div>
					<div>
						<label for="tenant_zip" style="display:block; font-size:12px; font-weight:600; color:var(--dash-navy); margin-bottom:6px;">
							<?php esc_html_e( 'Postal Code', 'fraud-tenant-theme' ); ?>
						</label>
						<input type="text" id="tenant_zip" name="postal_code" value="<?php echo esc_attr( $user_profile?->postal_code ?? '' ); ?>" class="search-ssn-input" style="height:40px; background:#FFFFFF;" placeholder="75001" />
					</div>
				</div>

				<button type="submit" class="dashboard-btn-add" style="width:100%; justify-content:center;">
					<?php esc_html_e( 'Save Profile Details', 'fraud-tenant-theme' ); ?>
				</button>
			</form>
		</div>

		<!-- Panel 2: Security & Password -->
		<div class="ft-settings-panel" id="ftTenantTabPassword" style="display:none;">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ft_update_profile" />
				<input type="hidden" name="account_section" value="password" />
				<input type="hidden" name="redirect_to" value="<?php echo esc_url( home_url( '/tenant-dashboard/' ) ); ?>" />
				<?php wp_nonce_field( 'ft_update_profile_' . $tenant_id, 'ft_profile_nonce' ); ?>

				<div style="margin-bottom:14px;">
					<label for="tenant_curr_pw" style="display:block; font-size:12px; font-weight:600; color:var(--dash-navy); margin-bottom:6px;">
						<?php esc_html_e( 'Current Password', 'fraud-tenant-theme' ); ?> <span style="color:#EF4444;">*</span>
					</label>
					<input type="password" id="tenant_curr_pw" name="current_password" class="search-ssn-input" style="height:40px; background:#FFFFFF;" required />
				</div>

				<div style="margin-bottom:14px;">
					<label for="tenant_new_pw" style="display:block; font-size:12px; font-weight:600; color:var(--dash-navy); margin-bottom:6px;">
						<?php esc_html_e( 'New Password (minimum 8 characters)', 'fraud-tenant-theme' ); ?> <span style="color:#EF4444;">*</span>
					</label>
					<input type="password" id="tenant_new_pw" name="new_password" minlength="8" class="search-ssn-input" style="height:40px; background:#FFFFFF;" required />
				</div>

				<div style="margin-bottom:20px;">
					<label for="tenant_conf_pw" style="display:block; font-size:12px; font-weight:600; color:var(--dash-navy); margin-bottom:6px;">
						<?php esc_html_e( 'Confirm New Password', 'fraud-tenant-theme' ); ?> <span style="color:#EF4444;">*</span>
					</label>
					<input type="password" id="tenant_conf_pw" name="confirm_password" minlength="8" class="search-ssn-input" style="height:40px; background:#FFFFFF;" required />
				</div>

				<button type="submit" class="dashboard-btn-add" style="width:100%; justify-content:center;">
					<?php esc_html_e( 'Update Password', 'fraud-tenant-theme' ); ?>
				</button>
			</form>
		</div>

		<!-- Panel 3: Email Address -->
		<div class="ft-settings-panel" id="ftTenantTabEmail" style="display:none;">
			<?php get_template_part( 'template-parts/email-change-confirm', null, array( 'user_id' => $tenant_id, 'redirect' => home_url( '/tenant-dashboard/' ) ) ); ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ft_update_profile" />
				<input type="hidden" name="account_section" value="email" />
				<input type="hidden" name="redirect_to" value="<?php echo esc_url( home_url( '/tenant-dashboard/' ) ); ?>" />
				<?php wp_nonce_field( 'ft_update_profile_' . $tenant_id, 'ft_profile_nonce' ); ?>

				<div style="margin-bottom:14px;">
					<label style="display:block; font-size:12px; font-weight:600; color:var(--dash-navy); margin-bottom:6px;">
						<?php esc_html_e( 'Current Email Address', 'fraud-tenant-theme' ); ?>
					</label>
					<input type="email" value="<?php echo esc_attr( $current_user->user_email ); ?>" class="search-ssn-input" style="height:40px; background:#F8FAFC; color:#64748B;" disabled readonly />
				</div>

				<div style="margin-bottom:14px;">
					<label for="tenant_new_email" style="display:block; font-size:12px; font-weight:600; color:var(--dash-navy); margin-bottom:6px;">
						<?php esc_html_e( 'New Email Address', 'fraud-tenant-theme' ); ?> <span style="color:#EF4444;">*</span>
					</label>
					<input type="email" id="tenant_new_email" name="new_email" class="search-ssn-input" style="height:40px; background:#FFFFFF;" required />
				</div>

				<div style="margin-bottom:20px;">
					<label for="tenant_email_curr_pw" style="display:block; font-size:12px; font-weight:600; color:var(--dash-navy); margin-bottom:6px;">
						<?php esc_html_e( 'Current Password (to authorize change)', 'fraud-tenant-theme' ); ?> <span style="color:#EF4444;">*</span>
					</label>
					<input type="password" id="tenant_email_curr_pw" name="current_password" class="search-ssn-input" style="height:40px; background:#FFFFFF;" required />
				</div>

				<button type="submit" class="dashboard-btn-add" style="width:100%; justify-content:center;">
					<?php esc_html_e( 'Send Confirmation Code', 'fraud-tenant-theme' ); ?>
				</button>
			</form>
		</div>

		<!-- Panel 4: Account Status -->
		<div class="ft-settings-panel" id="ftTenantTabStatus" style="display:none;">
			<div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:10px; padding:18px; margin-bottom:20px;">
				<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
					<div>
						<div style="font-size:11px; text-transform:uppercase; font-weight:700; color:#64748B; letter-spacing:0.5px;"><?php esc_html_e( 'Account Type', 'fraud-tenant-theme' ); ?></div>
						<div style="font-size:16px; font-weight:800; color:var(--dash-navy);"><?php esc_html_e( 'Tenant Portal Account', 'fraud-tenant-theme' ); ?></div>
					</div>
					<span class="badge badge-success" style="padding:6px 12px; font-size:12px; font-weight:700;">
						<?php esc_html_e( 'Verified & Active', 'fraud-tenant-theme' ); ?>
					</span>
				</div>
				<div style="font-size:13px; color:#475569; line-height:1.6; border-top:1px solid #E2E8F0; padding-top:12px;">
					<p style="margin:0 0 6px 0;"><strong><?php esc_html_e( 'SSN Protection:', 'fraud-tenant-theme' ); ?></strong> <?php esc_html_e( 'Cryptographically tokenized with keyed HMAC-SHA256. Zero plaintext stored.', 'fraud-tenant-theme' ); ?></p>
					<p style="margin:0 0 6px 0;"><strong><?php esc_html_e( 'FCRA Rights:', 'fraud-tenant-theme' ); ?></strong> <?php esc_html_e( 'Full access to file disputes and submit corrections regarding disclosed incident reports.', 'fraud-tenant-theme' ); ?></p>
					<p style="margin:0; color:var(--dash-teal);"><strong><?php esc_html_e( 'Account Standing:', 'fraud-tenant-theme' ); ?></strong> <?php esc_html_e( 'Good standing. All privacy protections enforced.', 'fraud-tenant-theme' ); ?></p>
				</div>
			</div>

			<button type="button" class="dashboard-btn-secondary" data-ft-modal-close style="width:100%; justify-content:center;">
				<?php esc_html_e( 'Close Settings', 'fraud-tenant-theme' ); ?>
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

