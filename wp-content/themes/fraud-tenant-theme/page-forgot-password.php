<?php
/**
 * Template Name: Forgot Password Page
 *
 * @package FraudTenantTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use FraudTenant\Auth\AuthHandler;

$flash_notice = class_exists( AuthHandler::class ) ? AuthHandler::get_flash_notice() : null;

get_header();
?>

<div class="auth-page-wrapper">
	<div class="auth-container auth-compact-container">
		<div class="auth-card auth-compact-card text-center">
			<div class="otp-icon-wrapper">
				<div class="otp-icon-circle">
					<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#0E9488" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
				</div>
			</div>

			<div class="auth-card-header">
				<h1 class="auth-title"><?php esc_html_e( 'Reset Password', 'fraud-tenant-theme' ); ?></h1>
				<p class="auth-subtitle"><?php esc_html_e( "Enter your registered email address and we'll dispatch a link to reset your password.", 'fraud-tenant-theme' ); ?></p>
			</div>

			<?php if ( isset( $_GET['sent'] ) && '1' === $_GET['sent'] ) : ?>
				<div class="auth-alert auth-alert-success" role="alert">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
					<span><?php esc_html_e( 'If an account exists with that email, a password reset link has been dispatched.', 'fraud-tenant-theme' ); ?></span>
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
				<input type="hidden" name="action" value="ft_forgot_password">
				<?php wp_nonce_field( 'ft_forgot_action', 'ft_forgot_nonce' ); ?>

				<div class="form-group text-left">
					<label for="forgot-email" class="form-label"><?php esc_html_e( 'Registered Email Address', 'fraud-tenant-theme' ); ?></label>
					<div class="input-with-icon">
						<input type="email" id="forgot-email" name="user_login" class="form-input" placeholder="you@example.com" required autocomplete="email" autofocus>
						<span class="field-icon" aria-hidden="true">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path></svg>
						</span>
					</div>
				</div>

				<button type="submit" class="btn btn-primary btn-block btn-auth-submit">
					<span><?php esc_html_e( 'Send Reset Link', 'fraud-tenant-theme' ); ?></span>
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
				</button>
			</form>

			<div class="auth-footer-prompt">
				<p><a href="<?php echo esc_url( home_url( '/login/' ) ); ?>" class="auth-link"><?php esc_html_e( 'Back to Sign In', 'fraud-tenant-theme' ); ?></a></p>
			</div>
		</div>
	</div>
</div>

<?php
get_footer();
