<?php
/**
 * Template Name: Account Status Page
 *
 * @package FraudTenantTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use FraudTenant\Auth\AuthService;
use FraudTenant\Utils\Constants;

$is_logged_in = is_user_logged_in();
$current_user = $is_logged_in ? wp_get_current_user() : null;
$user_id      = $current_user ? $current_user->ID : 0;

$profile = null;
$status  = 'unknown';

if ( $user_id > 0 && class_exists( AuthService::class ) ) {
	$status  = AuthService::get_user_account_status( $user_id );
	$profile = AuthService::get_user_profile( $user_id );
}

get_header();
?>

<div class="auth-page-wrapper">
	<div class="auth-container auth-status-container">
		<div class="auth-card auth-status-card">
			<?php if ( ! $is_logged_in ) : ?>
				<div class="text-center">
					<div class="otp-icon-wrapper">
						<div class="otp-icon-circle">
							<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#0E9488" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
						</div>
					</div>
					<h1 class="auth-title"><?php esc_html_e( 'Account Status', 'fraud-tenant-theme' ); ?></h1>
					<p class="auth-subtitle"><?php esc_html_e( 'Please sign in to view your application status and membership details.', 'fraud-tenant-theme' ); ?></p>
					<div style="margin-top: 24px;">
						<a href="<?php echo esc_url( home_url( '/login/' ) ); ?>" class="btn btn-primary"><?php esc_html_e( 'Sign In to Your Account', 'fraud-tenant-theme' ); ?></a>
					</div>
				</div>
			<?php else : ?>
				<div class="status-card-header text-center">
					<?php if ( isset( $_GET['verified'] ) && '1' === $_GET['verified'] ) : ?>
						<div class="auth-alert auth-alert-success" role="alert" style="margin-bottom: 24px;">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
							<span><?php esc_html_e( 'Email verification successful! Your account is now in the administrative review queue.', 'fraud-tenant-theme' ); ?></span>
						</div>
					<?php endif; ?>

					<!-- Status Badge Icon -->
					<div class="otp-icon-wrapper">
						<?php if ( Constants::STATUS_APPROVED === $status ) : ?>
							<div class="otp-icon-circle status-icon-approved">
								<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#0E9488" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
							</div>
						<?php elseif ( Constants::STATUS_REJECTED === $status || Constants::STATUS_SUSPENDED === $status ) : ?>
							<div class="otp-icon-circle status-icon-rejected">
								<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
							</div>
						<?php else : ?>
							<div class="otp-icon-circle status-icon-pending">
								<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
							</div>
						<?php endif; ?>
					</div>

					<h1 class="auth-title">
						<?php if ( Constants::STATUS_APPROVED === $status ) : ?>
							<?php esc_html_e( 'Account Approved', 'fraud-tenant-theme' ); ?>
						<?php elseif ( Constants::STATUS_REJECTED === $status ) : ?>
							<?php esc_html_e( 'Application Not Approved', 'fraud-tenant-theme' ); ?>
						<?php elseif ( Constants::STATUS_SUSPENDED === $status ) : ?>
							<?php esc_html_e( 'Account Suspended', 'fraud-tenant-theme' ); ?>
						<?php else : ?>
							<?php esc_html_e( 'Account Under Review', 'fraud-tenant-theme' ); ?>
						<?php endif; ?>
					</h1>

					<p class="auth-subtitle">
						<?php if ( Constants::STATUS_APPROVED === $status ) : ?>
							<?php esc_html_e( 'Your account has been fully verified and approved. Platform dashboards are launching in Phase 5.', 'fraud-tenant-theme' ); ?>
						<?php elseif ( Constants::STATUS_REJECTED === $status ) : ?>
							<?php esc_html_e( 'Your registration application was not approved during administrative compliance check. Please reach out to support if you have questions.', 'fraud-tenant-theme' ); ?>
						<?php elseif ( Constants::STATUS_SUSPENDED === $status ) : ?>
							<?php esc_html_e( 'Your account access has been suspended. Please contact customer support.', 'fraud-tenant-theme' ); ?>
						<?php else : ?>
							<?php esc_html_e( 'Thank you for verifying your email. Your application has been received and is waiting for compliance review.', 'fraud-tenant-theme' ); ?>
						<?php endif; ?>
					</p>
				</div>

				<!-- Step Progress Indicator (Showcase Design) -->
				<div class="status-steps-progress">
					<div class="status-step is-complete">
						<div class="status-step-circle">1</div>
						<div class="status-step-label"><?php esc_html_e( 'Register', 'fraud-tenant-theme' ); ?></div>
					</div>
					<div class="status-step-connector is-complete"></div>
					<div class="status-step <?php echo in_array( $status, array( Constants::STATUS_PENDING_APPROVAL, Constants::STATUS_APPROVED ), true ) ? 'is-complete' : 'is-active'; ?>">
						<div class="status-step-circle">2</div>
						<div class="status-step-label"><?php esc_html_e( 'Get Verified', 'fraud-tenant-theme' ); ?></div>
					</div>
					<div class="status-step-connector <?php echo Constants::STATUS_APPROVED === $status ? 'is-complete' : ''; ?>"></div>
					<div class="status-step <?php echo Constants::STATUS_APPROVED === $status ? 'is-complete' : 'is-pending'; ?>">
						<div class="status-step-circle">3</div>
						<div class="status-step-label"><?php esc_html_e( 'Admin Approval', 'fraud-tenant-theme' ); ?></div>
					</div>
					<div class="status-step-connector"></div>
					<div class="status-step is-locked">
						<div class="status-step-circle">4</div>
						<div class="status-step-label"><?php esc_html_e( 'Access Features', 'fraud-tenant-theme' ); ?></div>
					</div>
				</div>

				<!-- User & Account Details Card -->
				<div class="status-details-box">
					<h3 class="status-details-heading"><?php esc_html_e( 'Account Details', 'fraud-tenant-theme' ); ?></h3>
					<div class="status-details-grid">
						<div class="status-detail-item">
							<span class="detail-label"><?php esc_html_e( 'Full Name', 'fraud-tenant-theme' ); ?></span>
							<span class="detail-value"><?php echo esc_html( $current_user->display_name ); ?></span>
						</div>
						<div class="status-detail-item">
							<span class="detail-label"><?php esc_html_e( 'Email Address', 'fraud-tenant-theme' ); ?></span>
							<span class="detail-value"><?php echo esc_html( $current_user->user_email ); ?></span>
						</div>
						<div class="status-detail-item">
							<span class="detail-label"><?php esc_html_e( 'Account Role', 'fraud-tenant-theme' ); ?></span>
							<span class="detail-value">
								<?php
								$is_owner  = in_array( Constants::ROLE_OWNER, (array) $current_user->roles, true );
								$is_tenant = in_array( Constants::ROLE_TENANT, (array) $current_user->roles, true );
								if ( $is_owner ) {
									echo esc_html__( 'Individual Property Owner', 'fraud-tenant-theme' );
								} elseif ( $is_tenant ) {
									echo esc_html__( 'Tenant', 'fraud-tenant-theme' );
								} elseif ( in_array( 'administrator', (array) $current_user->roles, true ) ) {
									echo esc_html__( 'Administrator', 'fraud-tenant-theme' );
								} else {
									echo esc_html( ucfirst( $profile->account_type ?? 'User' ) );
								}
								?>
							</span>
						</div>
						<div class="status-detail-item">
							<span class="detail-label"><?php esc_html_e( 'Current Status', 'fraud-tenant-theme' ); ?></span>
							<span class="detail-value">
								<span class="badge status-badge-<?php echo esc_attr( $status ); ?>">
									<?php echo esc_html( ucwords( str_replace( '_', ' ', $status ) ) ); ?>
								</span>
							</span>
						</div>

						<?php if ( ! empty( $profile->phone ) ) : ?>
							<div class="status-detail-item">
								<span class="detail-label"><?php esc_html_e( 'Phone Number', 'fraud-tenant-theme' ); ?></span>
								<span class="detail-value"><?php echo esc_html( $profile->phone ); ?></span>
							</div>
						<?php endif; ?>

						<?php if ( $is_owner ) : ?>
							<?php
							global $wpdb;
							$prop_table     = $wpdb->prefix . Constants::TABLE_PROPERTIES;
							$owner_property = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$prop_table} WHERE owner_id = %d ORDER BY id DESC LIMIT 1", $user_id ) );
							$doc_table      = $wpdb->prefix . Constants::TABLE_DOCUMENTS;
							$owner_document = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$doc_table} WHERE user_id = %d AND object_type = 'property_ownership' ORDER BY id DESC LIMIT 1", $user_id ) );
							?>
							<?php if ( $owner_property ) : ?>
								<div class="status-detail-item">
									<span class="detail-label"><?php esc_html_e( 'Primary Property Address', 'fraud-tenant-theme' ); ?></span>
									<span class="detail-value">
										<?php echo esc_html( $owner_property->address_street . ', ' . $owner_property->city . ', ' . $owner_property->state . ' ' . $owner_property->postal_code ); ?>
									</span>
								</div>
							<?php endif; ?>
							<?php if ( $owner_document ) : ?>
								<div class="status-detail-item">
									<span class="detail-label"><?php esc_html_e( 'Ownership Proof Document', 'fraud-tenant-theme' ); ?></span>
									<span class="detail-value document-status-attached">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0E9488" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
										<?php echo esc_html( $owner_document->original_name ); ?>
										<small style="display:block; color:var(--text-muted); font-size:12px; margin-top:2px;"><?php esc_html_e( 'Protected Private Storage (Pending Compliance Review)', 'fraud-tenant-theme' ); ?></small>
									</span>
								</div>
							<?php endif; ?>
						<?php elseif ( $is_tenant && ! empty( $profile->address_line1 ) ) : ?>
							<div class="status-detail-item">
								<span class="detail-label"><?php esc_html_e( 'Current Residential Address', 'fraud-tenant-theme' ); ?></span>
								<span class="detail-value">
									<?php echo esc_html( $profile->address_line1 . ', ' . $profile->city . ', ' . $profile->state . ' ' . $profile->postal_code ); ?>
								</span>
							</div>
							<?php if ( ! empty( $profile->prev_address_line1 ) ) : ?>
								<div class="status-detail-item">
									<span class="detail-label"><?php esc_html_e( 'Previous Rental Address', 'fraud-tenant-theme' ); ?></span>
									<span class="detail-value">
										<?php echo esc_html( $profile->prev_address_line1 . ', ' . $profile->prev_city . ', ' . $profile->prev_state . ' ' . $profile->prev_postal_code ); ?>
									</span>
								</div>
							<?php endif; ?>
						<?php endif; ?>
					</div>
				</div>

				<!-- Actions Toolbar -->
				<div class="status-actions-row">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="btn btn-outline"><?php esc_html_e( 'Return to Homepage', 'fraud-tenant-theme' ); ?></a>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ft_logout' ), 'ft_logout_action' ) ); ?>" class="btn btn-secondary"><?php esc_html_e( 'Sign Out', 'fraud-tenant-theme' ); ?></a>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<?php
get_footer();

