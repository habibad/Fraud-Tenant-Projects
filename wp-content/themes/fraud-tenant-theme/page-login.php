<?php
/**
 * Template Name: Login Page
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

get_header();
?>

<div class="auth-page-wrapper">
	<div class="auth-container auth-login-container">
		<div class="auth-card auth-login-card">
			<!-- Left Form Column -->
			<div class="auth-form-column">
				<div class="auth-card-header">
					<h1 class="auth-title"><?php esc_html_e( 'Welcome Back', 'fraud-tenant-theme' ); ?></h1>
					<p class="auth-subtitle"><?php esc_html_e( 'Sign in to your account', 'fraud-tenant-theme' ); ?></p>
				</div>

				<?php if ( isset( $_GET['loggedout'] ) && '1' === $_GET['loggedout'] ) : ?>
					<div class="auth-alert auth-alert-success" role="alert">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
						<span><?php esc_html_e( 'You have been signed out successfully.', 'fraud-tenant-theme' ); ?></span>
					</div>
				<?php endif; ?>

				<?php if ( isset( $_GET['pw_reset'] ) && 'success' === $_GET['pw_reset'] ) : ?>
					<div class="auth-alert auth-alert-success" role="alert">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
						<span><?php esc_html_e( 'Your password has been reset successfully. Please sign in with your new credentials.', 'fraud-tenant-theme' ); ?></span>
					</div>
				<?php endif; ?>

				<?php if ( $flash_notice ) : ?>
					<div class="auth-alert auth-alert-<?php echo esc_attr( $flash_notice['type'] === 'success' ? 'success' : 'error' ); ?>" role="alert">
						<?php if ( $flash_notice['type'] === 'success' ) : ?>
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
						<?php else : ?>
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
						<?php endif; ?>
						<span><?php echo esc_html( $flash_notice['message'] ); ?></span>
					</div>
				<?php endif; ?>

				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="auth-form" novalidate>
					<input type="hidden" name="action" value="ft_login">
					<?php wp_nonce_field( 'ft_login_action', 'ft_login_nonce' ); ?>

					<div class="form-group">
						<label for="ft-login-email" class="form-label"><?php esc_html_e( 'Email Address', 'fraud-tenant-theme' ); ?></label>
						<div class="input-with-icon">
							<input type="email" id="ft-login-email" name="log" class="form-input" placeholder="you@example.com" required autocomplete="email">
							<span class="field-icon" aria-hidden="true">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path></svg>
							</span>
						</div>
					</div>

					<div class="form-group">
						<label for="ft-login-password" class="form-label"><?php esc_html_e( 'Password', 'fraud-tenant-theme' ); ?></label>
						<div class="input-with-icon">
							<input type="password" id="ft-login-password" name="pwd" class="form-input" placeholder="<?php esc_attr_e( 'Enter your password', 'fraud-tenant-theme' ); ?>" required autocomplete="current-password">
							<button type="button" class="btn-toggle-pw" aria-label="<?php esc_attr_e( 'Toggle password visibility', 'fraud-tenant-theme' ); ?>" data-target="ft-login-password">
								<svg class="icon-eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
								<svg class="icon-eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
							</button>
						</div>
					</div>

					<div class="form-row form-row-split">
						<label class="checkbox-label">
							<input type="checkbox" name="rememberme" value="forever">
							<span><?php esc_html_e( 'Remember me', 'fraud-tenant-theme' ); ?></span>
						</label>
						<a href="<?php echo esc_url( home_url( '/forgot-password/' ) ); ?>" class="auth-link"><?php esc_html_e( 'Forgot Password?', 'fraud-tenant-theme' ); ?></a>
					</div>

					<button type="submit" class="btn btn-primary btn-block btn-auth-submit">
						<span><?php esc_html_e( 'Sign In', 'fraud-tenant-theme' ); ?></span>
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
					</button>
				</form>

				<div class="auth-footer-prompt">
					<p><?php esc_html_e( "Don't have an account?", 'fraud-tenant-theme' ); ?> <a href="<?php echo esc_url( home_url( '/register/' ) ); ?>" class="auth-link-bold"><?php esc_html_e( 'Register Now', 'fraud-tenant-theme' ); ?></a></p>
				</div>
			</div>

			<!-- Right Visual Column (Matching Showcase Design) -->
			<div class="auth-visual-column">
				<div class="auth-visual-inner">
					<img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/contact-house-visual.png' ); ?>" alt="<?php esc_attr_e( 'Modern residential property', 'fraud-tenant-theme' ); ?>" class="auth-visual-img">
					<div class="auth-visual-overlay">
						<div class="auth-visual-badge">
							<div class="auth-visual-badge-icon">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
							</div>
							<div>
								<div class="badge-title"><?php esc_html_e( 'Safer Tenancies Stronger Communities', 'fraud-tenant-theme' ); ?></div>
								<div class="badge-sub"><?php esc_html_e( 'A trusted rental network for property owners and tenants.', 'fraud-tenant-theme' ); ?></div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<?php
get_footer();
