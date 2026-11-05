<?php
/**
 * Template Name: Reset Password Page
 *
 * Implements the mandatory password recovery workflow:
 * Forgot Password -> Request Reset OTP -> Verify Reset OTP -> Set New Password -> Login Page.
 *
 * @package FraudTenantTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use FraudTenant\Auth\AuthHandler;
use FraudTenant\Services\OtpService;
use FraudTenant\Utils\Sanitizer;

$flash_notice = class_exists( AuthHandler::class ) ? AuthHandler::get_flash_notice() : null;

// Determine workflow step
$step  = isset( $_GET['step'] ) ? sanitize_key( $_GET['step'] ) : '';
$email = isset( $_GET['email'] ) ? sanitize_email( wp_unslash( $_GET['email'] ) ) : '';
$key   = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
$login = isset( $_GET['login'] ) ? sanitize_text_field( wp_unslash( $_GET['login'] ) ) : '';

$is_legacy_link = ( ! empty( $key ) && ! empty( $login ) );
$is_new_pw_step = ( 'new_password' === $step );

// The reset authorization is read from the HttpOnly cookie only (never from the URL).
$token_user_id = 0;
if ( $is_new_pw_step ) {
	$token_user_id = \FraudTenant\Auth\AuthService::resolve_reset_authorization( \FraudTenant\Auth\AuthService::get_reset_cookie_token() );
}

// Never send this page's URL to third parties as a referrer.
if ( ! headers_sent() ) {
	header( 'Referrer-Policy: no-referrer' );
}

// Calculate resend cooldown for reset OTP
$cooldown_remaining = 0;
if ( ! empty( $email ) && class_exists( OtpService::class ) ) {
	$cooldown_remaining = OtpService::get_resend_cooldown_remaining( $email, 'password_reset' );
}

get_header();
?>

<div class="auth-page-wrapper">
	<div class="auth-container auth-compact-container">
		<div class="auth-card auth-compact-card text-center">

			<?php if ( $is_new_pw_step ) : ?>
				<!-- ========================================================
				     STEP 2: SET NEW PASSWORD (USING VERIFIED RESET TOKEN)
				     ======================================================== -->
				<div class="otp-icon-wrapper">
					<div class="otp-icon-circle">
						<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#0E9488" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
					</div>
				</div>

				<div class="auth-card-header">
					<h1 class="auth-title"><?php esc_html_e( 'Set New Password', 'fraud-tenant-theme' ); ?></h1>
					<p class="auth-subtitle"><?php esc_html_e( 'Your verification code was accepted. Please create a new, strong password.', 'fraud-tenant-theme' ); ?></p>
				</div>

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

				<?php if ( ! $token_user_id ) : ?>
					<div class="auth-alert auth-alert-error" role="alert">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
						<span><?php esc_html_e( 'This password reset session has expired or is invalid. Please request a new verification code.', 'fraud-tenant-theme' ); ?></span>
					</div>
					<div style="margin-top: 24px;">
						<a href="<?php echo esc_url( home_url( '/forgot-password/' ) ); ?>" class="btn btn-primary btn-block"><?php esc_html_e( 'Request New Code', 'fraud-tenant-theme' ); ?></a>
					</div>
				<?php else : ?>
					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="auth-form" novalidate>
						<input type="hidden" name="action" value="ft_set_new_password">
						<?php wp_nonce_field( 'ft_set_new_password_action', 'ft_set_new_password_nonce' ); ?>

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

			<?php elseif ( $is_legacy_link ) : ?>
				<!-- ========================================================
				     LEGACY RESET LINK HANDLER (wp_password_reset_key)
				     ======================================================== -->
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
						<span><?php echo esc_html( $flash_notice['message'] ); ?></span>
					</div>
				<?php endif; ?>

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

			<?php else : ?>
				<!-- ========================================================
				     STEP 1: VERIFY 6-DIGIT RESET OTP (AUTHORITATIVE WORKFLOW)
				     ======================================================== -->
				<div class="otp-icon-wrapper">
					<div class="otp-icon-circle">
						<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#0E9488" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
					</div>
				</div>

				<div class="auth-card-header">
					<h1 class="auth-title"><?php esc_html_e( 'Verify Reset Code', 'fraud-tenant-theme' ); ?></h1>
					<p class="auth-subtitle">
						<?php esc_html_e( 'Enter the 6-digit verification code sent to your email to verify password reset.', 'fraud-tenant-theme' ); ?>
						<?php if ( ! empty( $email ) ) : ?>
							<br><strong class="otp-target-email"><?php echo esc_html( $email ); ?></strong>
						<?php endif; ?>
					</p>
				</div>

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

				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="auth-form otp-form" id="ft-otp-form">
					<input type="hidden" name="action" value="ft_verify_reset_otp">
					<input type="hidden" name="email" value="<?php echo esc_attr( $email ); ?>">
					<input type="hidden" name="otp_code" id="ft-otp-full-code" value="">
					<?php wp_nonce_field( 'ft_verify_reset_otp_action', 'ft_verify_reset_otp_nonce' ); ?>

					<?php if ( empty( $email ) ) : ?>
						<div class="form-group text-left" style="margin-bottom: 20px;">
							<label for="otp-email-input" class="form-label"><?php esc_html_e( 'Registered Email Address', 'fraud-tenant-theme' ); ?></label>
							<input type="email" id="otp-email-input" name="email" class="form-input" placeholder="you@example.com" required value="<?php echo esc_attr( $email ); ?>" autofocus>
						</div>
					<?php endif; ?>

					<!-- 6-digit numeric input boxes (Showcase Design) -->
					<div class="otp-digits-wrapper" role="group" aria-label="<?php esc_attr_e( '6-digit password reset code', 'fraud-tenant-theme' ); ?>">
						<input type="text" class="otp-digit-input" name="otp_1" id="otp-1" maxlength="1" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" <?php echo ! empty( $email ) ? 'autofocus' : ''; ?> required>
						<input type="text" class="otp-digit-input" name="otp_2" id="otp-2" maxlength="1" inputmode="numeric" pattern="[0-9]*" required>
						<input type="text" class="otp-digit-input" name="otp_3" id="otp-3" maxlength="1" inputmode="numeric" pattern="[0-9]*" required>
						<input type="text" class="otp-digit-input" name="otp_4" id="otp-4" maxlength="1" inputmode="numeric" pattern="[0-9]*" required>
						<input type="text" class="otp-digit-input" name="otp_5" id="otp-5" maxlength="1" inputmode="numeric" pattern="[0-9]*" required>
						<input type="text" class="otp-digit-input" name="otp_6" id="otp-6" maxlength="1" inputmode="numeric" pattern="[0-9]*" required>
					</div>

					<button type="submit" class="btn btn-primary btn-block btn-auth-submit" id="btn-verify-otp">
						<span><?php esc_html_e( 'Verify Code', 'fraud-tenant-theme' ); ?></span>
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
					</button>
				</form>

				<!-- Resend Reset OTP with countdown timer -->
				<div class="otp-resend-wrapper" id="otp-resend-container" data-seconds="<?php echo esc_attr( $cooldown_remaining ); ?>">
					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" id="ft-resend-otp-form" style="display:inline;">
						<input type="hidden" name="action" value="ft_resend_reset_otp">
						<input type="hidden" name="email" value="<?php echo esc_attr( $email ); ?>">
						<?php wp_nonce_field( 'ft_resend_reset_otp_action', 'ft_resend_reset_otp_nonce' ); ?>

						<p class="otp-resend-text">
							<?php esc_html_e( "Didn't receive the code?", 'fraud-tenant-theme' ); ?>
							<span id="otp-timer-block" <?php echo $cooldown_remaining <= 0 ? 'style="display:none;"' : ''; ?>>
								<?php esc_html_e( 'Resend in', 'fraud-tenant-theme' ); ?> <span id="otp-countdown-display"><?php echo sprintf( '%02d:%02d', floor( $cooldown_remaining / 60 ), $cooldown_remaining % 60 ); ?></span>
							</span>
							<button type="submit" id="btn-resend-otp" class="btn-link-action" <?php echo $cooldown_remaining > 0 ? 'disabled style="display:none;"' : ''; ?>>
								<?php esc_html_e( 'Resend Code', 'fraud-tenant-theme' ); ?>
							</button>
						</p>
					</form>
				</div>

			<?php endif; ?>

			<div class="auth-footer-prompt">
				<p><a href="<?php echo esc_url( home_url( '/login/' ) ); ?>" class="auth-link"><?php esc_html_e( 'Back to Sign In', 'fraud-tenant-theme' ); ?></a></p>
			</div>
		</div>
	</div>
</div>

<?php
get_footer();
