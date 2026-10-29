<?php
/**
 * Template Name: Owner Dashboard
 *
 * Dedicated authenticated SaaS application shell for approved Property Owners.
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
$current_user = DashboardService::enforce_access( Constants::ROLE_OWNER );
$owner_id     = $current_user->ID;

// 2. Fetch genuine database records (Zero mock data)
$data = DashboardService::get_owner_dashboard_data( $owner_id );

$user_profile = $data['profile'];
$first_name   = $current_user->first_name ?: ( explode( ' ', $current_user->display_name )[0] ?? 'Property Owner' );

get_header();
?>

<div class="dashboard-shell-layout" id="main-content">

	<!-- Left Navy Sidebar (Showcase Match) -->
	<aside class="dashboard-sidebar" id="ftDashboardSidebar">
		<div>
			<ul class="dashboard-nav-list" role="navigation" aria-label="<?php esc_attr_e( 'Owner Dashboard Navigation', 'fraud-tenant-theme' ); ?>">
				<li class="dashboard-nav-item is-active">
					<a href="<?php echo esc_url( home_url( '/owner-dashboard/' ) ); ?>">
						<svg class="dashboard-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
						<span><?php esc_html_e( 'Dashboard', 'fraud-tenant-theme' ); ?></span>
					</a>
				</li>
				<li class="dashboard-nav-item">
					<a href="#my-properties">
						<svg class="dashboard-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
						<span><?php esc_html_e( 'My Properties', 'fraud-tenant-theme' ); ?></span>
					</a>
				</li>
				<li class="dashboard-nav-item">
					<a href="#search-tenants">
						<svg class="dashboard-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
						<span><?php esc_html_e( 'Search Tenants', 'fraud-tenant-theme' ); ?></span>
					</a>
				</li>
				<li class="dashboard-nav-item">
					<a href="#my-reports">
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
						<button type="button" class="dash-panel-link" data-ft-modal-target="ftAddPropertyModal" style="background:none;border:none;cursor:pointer;">
							<?php esc_html_e( '+ Add Property', 'fraud-tenant-theme' ); ?>
						</button>
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
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $data['properties'] as $prop ) : ?>
										<tr>
											<td>
												<strong><?php echo esc_html( $prop->address_street . ( $prop->unit_number ? ' #' . $prop->unit_number : '' ) ); ?></strong>
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
												<?php elseif ( 'rejected' === $prop->status ) : ?>
													<span class="badge-dash badge-dash-rejected"><?php esc_html_e( 'Verification Rejected', 'fraud-tenant-theme' ); ?></span>
												<?php else : ?>
													<span class="badge-dash badge-dash-pending"><?php esc_html_e( 'Pending Property Verification', 'fraud-tenant-theme' ); ?></span>
												<?php endif; ?>
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

	</main>
</div>

<!-- ============================================================ -->
<!-- MODALS -->
<!-- ============================================================ -->

<!-- Modal 1: Add Property Notice (Phase 6 Workflow) -->
<div class="ft-modal-overlay" id="ftAddPropertyModal" role="dialog" aria-modal="true" aria-labelledby="ftAddPropTitle">
	<div class="ft-modal-box">
		<button type="button" class="ft-modal-close" aria-label="<?php esc_attr_e( 'Close', 'fraud-tenant-theme' ); ?>">&times;</button>
		<h3 class="ft-modal-title" id="ftAddPropTitle"><?php esc_html_e( 'Add Rental Property', 'fraud-tenant-theme' ); ?></h3>
		<p class="ft-modal-desc">
			<?php esc_html_e( 'Multi-property management and additional property registrations will be fully activated in Phase 6. Your initial property registered during account onboarding is displayed on your dashboard.', 'fraud-tenant-theme' ); ?>
		</p>
		<div style="background:#F8FAFC; border:1px solid #E2E8F0; padding:14px; border-radius:8px; font-size:13px; color:var(--dash-muted); margin-bottom:18px;">
			<strong style="color:var(--dash-navy); display:block; margin-bottom:4px;"><?php esc_html_e( 'Coming in Phase 6:', 'fraud-tenant-theme' ); ?></strong>
			&bull; <?php esc_html_e( 'Add single-family and multi-unit buildings', 'fraud-tenant-theme' ); ?><br>
			&bull; <?php esc_html_e( 'Direct deed and tax record verification upload', 'fraud-tenant-theme' ); ?><br>
			&bull; <?php esc_html_e( 'Property-level tenant association', 'fraud-tenant-theme' ); ?>
		</div>
		<button type="button" class="dashboard-btn-add" data-ft-modal-close style="width:100%; justify-content:center;">
			<?php esc_html_e( 'Understood', 'fraud-tenant-theme' ); ?>
		</button>
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
