<?php
/**
 * Template Name: Reset Password Page
 *
 * @package FraudTenantTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use FraudTenant\Auth\AuthHandler;

$key   = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
$login = isset( $_GET['login'] ) ? sanitize_text_field( wp_unslash( $_GET['login'] ) ) : '';
$flash_notice = class_exists( AuthHandler::class ) ? AuthHandler::get_flash_notice() : null;

$is_link_present = ! empty( $key ) && ! empty( $login );

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
				<h1 class="auth-title"><?php esc_html_e( 'Set New Password', 'fraud-tenant-theme' ); ?></h1>
				<p class="auth-subtitle"><?php esc_html_e( 'Please create a new, strong password for your account.', 'fraud-tenant-theme' ); ?></p>
			</div>

			<?php if ( $flash_notice ) : ?>
				<div class="auth-alert auth-alert-<?php echo esc_attr( $flash_notice['type'] === 'success' ? 'success' : 'error' ); ?>" role="alert">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
					<span><?php echo esc_html( $flash_notice['message'] ); ?></span>
				</div>
			<?php endif; ?>

			<?php if ( ! $is_link_present ) : ?>
				<div class="auth-alert auth-alert-error" role="alert">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
					<span><?php esc_html_e( 'This password reset link is invalid or incomplete. Please request a new link.', 'fraud-tenant-theme' ); ?></span>
				</div>
				<div style="margin-top: 24px;">
					<a href="<?php echo esc_url( home_url( '/forgot-password/' ) ); ?>" class="btn btn-primary btn-block"><?php esc_html_e( 'Request New Reset Link', 'fraud-tenant-theme' ); ?></a>
				</div>
			<?php else : ?>
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="auth-form" novalidate>
					<input type="hidden" name="action" value="ft_reset_password">
					<input type="hidden" name="rp_key" value="<?php echo esc_attr( $key ); ?>">
					<input type="hidden" name="rp_login" value="<?php echo esc_attr( $login ); ?>">
					<?php wp_nonce_field( 'ft_reset_action', 'ft_reset_nonce' ); ?>

					<div class="form-group text-left">
						<label for="reset-password-val" class="form-label"><?php esc_html_e( 'New Password', 'fraud-tenant-theme' ); ?></label>
						<div class="input-with-icon">
							<input type="password" id="reset-password-val" name="new_password" class="form-input" placeholder="<?php esc_attr_e( 'Minimum 8 characters', 'fraud-tenant-theme' ); ?>" required minlength="8" autocomplete="new-password" autofocus>
							<button type="button" class="btn-toggle-pw" aria-label="<?php esc_attr_e( 'Toggle password visibility', 'fraud-tenant-theme' ); ?>" data-target="reset-password-val">
								<svg class="icon-eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
								<svg class="icon-eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
							</button>
						</div>
					</div>

					<div class="form-group text-left">
						<label for="reset-confirm-password-val" class="form-label"><?php esc_html_e( 'Confirm New Password', 'fraud-tenant-theme' ); ?></label>
						<div class="input-with-icon">
							<input type="password" id="reset-confirm-password-val" name="confirm_password" class="form-input" placeholder="<?php esc_attr_e( 'Repeat new password', 'fraud-tenant-theme' ); ?>" required minlength="8" autocomplete="new-password">
							<button type="button" class="btn-toggle-pw" aria-label="<?php esc_attr_e( 'Toggle password visibility', 'fraud-tenant-theme' ); ?>" data-target="reset-confirm-password-val">
								<svg class="icon-eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
								<svg class="icon-eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
							</button>
						</div>
					</div>

					<button type="submit" class="btn btn-primary btn-block btn-auth-submit">
						<span><?php esc_html_e( 'Update Password', 'fraud-tenant-theme' ); ?></span>
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
					</button>
				</form>
			<?php endif; ?>

			<div class="auth-footer-prompt">
				<p><a href="<?php echo esc_url( home_url( '/login/' ) ); ?>" class="auth-link"><?php esc_html_e( 'Back to Sign In', 'fraud-tenant-theme' ); ?></a></p>
			</div>
		</div>
	</div>
</div>

<?php
get_footer();

