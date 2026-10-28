<?php
/**
 * Template Name: Register Page
 *
 * @package FraudTenantTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Redirect logged in users
if ( is_user_logged_in() ) {
	if ( current_user_can( 'administrator' ) ) {
		wp_safe_redirect( admin_url() );
	} else {
		wp_safe_redirect( home_url( '/account-status/' ) );
	}
	exit;
}

use FraudTenant\Auth\AuthHandler;

$flash_notice = class_exists( AuthHandler::class ) ? AuthHandler::get_flash_notice() : null;
$preselected_role = isset( $_GET['role'] ) && in_array( $_GET['role'], array( 'owner', 'tenant' ), true ) ? sanitize_key( $_GET['role'] ) : 'owner';

get_header();
?>

<div class="auth-page-wrapper">
	<div class="auth-container auth-register-container">
		<div class="auth-card auth-register-card">
			<div class="auth-card-header text-center">
				<h1 class="auth-title"><?php esc_html_e( 'Create Your Account', 'fraud-tenant-theme' ); ?></h1>
				<p class="auth-subtitle"><?php esc_html_e( 'Join as a Property Owner or Tenant', 'fraud-tenant-theme' ); ?></p>
			</div>

			<?php if ( $flash_notice ) : ?>
				<div class="auth-alert auth-alert-<?php echo esc_attr( $flash_notice['type'] === 'success' ? 'success' : 'error' ); ?>" role="alert">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
					<span><?php echo esc_html( $flash_notice['message'] ); ?></span>
				</div>
			<?php endif; ?>

			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="auth-form" novalidate id="ft-register-form">
				<input type="hidden" name="action" value="ft_register">
				<?php wp_nonce_field( 'ft_register_action', 'ft_register_nonce' ); ?>

				<!-- Step 1: Role Selection Cards (Showcase Design) -->
				<div class="form-group">
					<label class="form-label"><?php esc_html_e( 'Select Account Type', 'fraud-tenant-theme' ); ?></label>
					<div class="role-selector-grid" role="radiogroup" aria-label="<?php esc_attr_e( 'Account Type', 'fraud-tenant-theme' ); ?>">
						<!-- Property Owner Card -->
						<label class="role-card <?php echo 'owner' === $preselected_role ? 'is-active' : ''; ?>">
							<input type="radio" name="account_type" value="owner" class="role-radio" <?php checked( $preselected_role, 'owner' ); ?>>
							<div class="role-card-inner">
								<div class="role-icon">
									<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
								</div>
								<div class="role-details">
									<div class="role-name"><?php esc_html_e( 'Property Owner', 'fraud-tenant-theme' ); ?></div>
									<div class="role-desc"><?php esc_html_e( 'Manage properties, submit incidents, & access reports', 'fraud-tenant-theme' ); ?></div>
								</div>
								<div class="role-check" aria-hidden="true">
									<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
								</div>
							</div>
						</label>

						<!-- Tenant Card -->
						<label class="role-card <?php echo 'tenant' === $preselected_role ? 'is-active' : ''; ?>">
							<input type="radio" name="account_type" value="tenant" class="role-radio" <?php checked( $preselected_role, 'tenant' ); ?>>
							<div class="role-card-inner">
								<div class="role-icon">
									<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
								</div>
								<div class="role-details">
									<div class="role-name"><?php esc_html_e( 'Tenant', 'fraud-tenant-theme' ); ?></div>
									<div class="role-desc"><?php esc_html_e( 'View your records, request corrections, & manage disputes', 'fraud-tenant-theme' ); ?></div>
								</div>
								<div class="role-check" aria-hidden="true">
									<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
								</div>
							</div>
						</label>
					</div>
				</div>

				<!-- Step 2: Personal Profile Details -->
				<div class="form-row form-row-2col">
					<div class="form-group">
						<label for="reg-first-name" class="form-label"><?php esc_html_e( 'First Name', 'fraud-tenant-theme' ); ?> <span class="required">*</span></label>
						<input type="text" id="reg-first-name" name="first_name" class="form-input" placeholder="e.g. John" required autocomplete="given-name">
					</div>
					<div class="form-group">
						<label for="reg-last-name" class="form-label"><?php esc_html_e( 'Last Name', 'fraud-tenant-theme' ); ?> <span class="required">*</span></label>
						<input type="text" id="reg-last-name" name="last_name" class="form-input" placeholder="e.g. Smith" required autocomplete="family-name">
					</div>
				</div>

				<div class="form-group">
					<label for="reg-email" class="form-label"><?php esc_html_e( 'Email Address', 'fraud-tenant-theme' ); ?> <span class="required">*</span></label>
					<div class="input-with-icon">
						<input type="email" id="reg-email" name="email" class="form-input" placeholder="you@example.com" required autocomplete="email">
						<span class="field-icon" aria-hidden="true">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path></svg>
						</span>
					</div>
					<span class="form-help"><?php esc_html_e( 'We will send a 6-digit verification code to this address.', 'fraud-tenant-theme' ); ?></span>
				</div>

				<div class="form-row form-row-2col">
					<div class="form-group">
						<label for="reg-password" class="form-label"><?php esc_html_e( 'Password', 'fraud-tenant-theme' ); ?> <span class="required">*</span></label>
						<div class="input-with-icon">
							<input type="password" id="reg-password" name="password" class="form-input" placeholder="<?php esc_attr_e( 'Min 8 characters', 'fraud-tenant-theme' ); ?>" required minlength="8" autocomplete="new-password">
							<button type="button" class="btn-toggle-pw" aria-label="<?php esc_attr_e( 'Toggle password visibility', 'fraud-tenant-theme' ); ?>" data-target="reg-password">
								<svg class="icon-eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
								<svg class="icon-eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
							</button>
						</div>
					</div>
					<div class="form-group">
						<label for="reg-confirm-password" class="form-label"><?php esc_html_e( 'Confirm Password', 'fraud-tenant-theme' ); ?> <span class="required">*</span></label>
						<div class="input-with-icon">
							<input type="password" id="reg-confirm-password" name="confirm_password" class="form-input" placeholder="<?php esc_attr_e( 'Repeat password', 'fraud-tenant-theme' ); ?>" required minlength="8" autocomplete="new-password">
							<button type="button" class="btn-toggle-pw" aria-label="<?php esc_attr_e( 'Toggle password visibility', 'fraud-tenant-theme' ); ?>" data-target="reg-confirm-password">
								<svg class="icon-eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
								<svg class="icon-eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
							</button>
						</div>
					</div>
				</div>

				<div class="form-group">
					<label class="checkbox-label checkbox-terms">
						<input type="checkbox" name="terms_acknowledged" value="1" required>
						<span>
							<?php
							printf(
								/* translators: 1: terms url, 2: privacy url */
								__( 'I agree to the <a href="%1$s" target="_blank" rel="noopener">Terms of Service</a> and acknowledge the <a href="%2$s" target="_blank" rel="noopener">Privacy Policy</a>.', 'fraud-tenant-theme' ),
								esc_url( home_url( '/terms-of-service/' ) ),
								esc_url( home_url( '/privacy-policy/' ) )
							);
							?>
						</span>
					</label>
				</div>

				<button type="submit" class="btn btn-primary btn-block btn-auth-submit">
					<span><?php esc_html_e( 'Continue', 'fraud-tenant-theme' ); ?></span>
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
				</button>
			</form>

			<div class="auth-footer-prompt text-center">
				<p><?php esc_html_e( 'Already have an account?', 'fraud-tenant-theme' ); ?> <a href="<?php echo esc_url( home_url( '/login/' ) ); ?>" class="auth-link-bold"><?php esc_html_e( 'Sign in', 'fraud-tenant-theme' ); ?></a></p>
			</div>
		</div>
	</div>
</div>

<?php
get_footer();
