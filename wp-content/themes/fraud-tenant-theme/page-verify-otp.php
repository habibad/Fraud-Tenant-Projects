<?php
/**
 * Template Name: Verify Email OTP Page
 *
 * @package FraudTenantTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use FraudTenant\Auth\AuthHandler;
use FraudTenant\Services\OtpService;

$email = isset( $_GET['email'] ) ? sanitize_email( wp_unslash( $_GET['email'] ) ) : '';
$flash_notice = class_exists( AuthHandler::class ) ? AuthHandler::get_flash_notice() : null;

// Calculate remaining cooldown if any
$cooldown_remaining = 60;
if ( ! empty( $email ) && class_exists( OtpService::class ) ) {
	$cooldown_remaining = OtpService::get_resend_cooldown_remaining( $email, 'registration' );
}

get_header();
?>

<div class="auth-page-wrapper">
	<div class="auth-container auth-otp-container">
		<div class="auth-card auth-otp-card text-center">
			<!-- Envelope Icon in Teal Circle (Showcase Design) -->
			<div class="otp-icon-wrapper">
				<div class="otp-icon-circle">
					<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#0E9488" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
				</div>
			</div>

			<div class="auth-card-header">
				<h1 class="auth-title"><?php esc_html_e( 'Verify Your Email', 'fraud-tenant-theme' ); ?></h1>
				<p class="auth-subtitle">
					<?php esc_html_e( 'We have sent a 6-digit verification code to your email address.', 'fraud-tenant-theme' ); ?>
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
				<input type="hidden" name="action" value="ft_verify_otp">
				<input type="hidden" name="email" value="<?php echo esc_attr( $email ); ?>">
				<input type="hidden" name="otp_code" id="ft-otp-full-code" value="">
				<?php wp_nonce_field( 'ft_otp_action', 'ft_otp_nonce' ); ?>

				<?php if ( empty( $email ) ) : ?>
					<div class="form-group text-left" style="margin-bottom: 20px;">
						<label for="otp-email-input" class="form-label"><?php esc_html_e( 'Registered Email Address', 'fraud-tenant-theme' ); ?></label>
						<input type="email" id="otp-email-input" name="email" class="form-input" placeholder="you@example.com" required value="<?php echo esc_attr( $email ); ?>">
					</div>
				<?php endif; ?>

				<!-- 6-digit numeric input boxes (Showcase Design) -->
				<div class="otp-digits-wrapper" role="group" aria-label="<?php esc_attr_e( '6-digit verification code', 'fraud-tenant-theme' ); ?>">
					<input type="text" class="otp-digit-input" name="otp_1" id="otp-1" maxlength="1" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" autofocus required>
					<input type="text" class="otp-digit-input" name="otp_2" id="otp-2" maxlength="1" inputmode="numeric" pattern="[0-9]*" required>
					<input type="text" class="otp-digit-input" name="otp_3" id="otp-3" maxlength="1" inputmode="numeric" pattern="[0-9]*" required>
					<input type="text" class="otp-digit-input" name="otp_4" id="otp-4" maxlength="1" inputmode="numeric" pattern="[0-9]*" required>
					<input type="text" class="otp-digit-input" name="otp_5" id="otp-5" maxlength="1" inputmode="numeric" pattern="[0-9]*" required>
					<input type="text" class="otp-digit-input" name="otp_6" id="otp-6" maxlength="1" inputmode="numeric" pattern="[0-9]*" required>
				</div>

				<button type="submit" class="btn btn-primary btn-block btn-auth-submit" id="btn-verify-otp">
					<span><?php esc_html_e( 'Verify OTP', 'fraud-tenant-theme' ); ?></span>
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
				</button>
			</form>

			<!-- Resend OTP with countdown timer -->
			<div class="otp-resend-wrapper" id="otp-resend-container" data-seconds="<?php echo esc_attr( $cooldown_remaining ); ?>">
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" id="ft-resend-otp-form" style="display:inline;">
					<input type="hidden" name="action" value="ft_resend_otp">
					<input type="hidden" name="email" value="<?php echo esc_attr( $email ); ?>">
					<?php wp_nonce_field( 'ft_resend_otp_action', 'ft_resend_otp_nonce' ); ?>

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

			<div class="auth-footer-prompt">
				<p><a href="<?php echo esc_url( home_url( '/login/' ) ); ?>" class="auth-link"><?php esc_html_e( 'Back to Sign In', 'fraud-tenant-theme' ); ?></a></p>
			</div>
		</div>
	</div>
</div>

<?php
get_footer();
