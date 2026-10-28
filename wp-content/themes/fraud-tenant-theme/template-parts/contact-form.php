<?php
/**
 * Contact & Enquiry Form Template Part.
 *
 * @package FraudTenantTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$notice_code = sanitize_text_field( wp_unslash( $_GET['ft_notice'] ?? '' ) );
$notice_type = '';
$notice_msg  = '';

if ( 'success' === $notice_code ) {
	$notice_type = 'success';
	$notice_msg  = __( 'Thank you for reaching out! Your enquiry has been received and our team will get back to you shortly.', 'fraud-tenant-theme' );
} elseif ( 'ssn_detected' === $notice_code ) {
	$notice_type = 'error';
	$notice_msg  = __( 'For your security and privacy, please do not include Social Security Numbers or sensitive financial identifiers in general contact messages.', 'fraud-tenant-theme' );
} elseif ( 'rate_limit' === $notice_code ) {
	$notice_type = 'error';
	$notice_msg  = __( 'You have submitted an enquiry recently. Please wait a moment before sending another message.', 'fraud-tenant-theme' );
} elseif ( 'invalid_fields' === $notice_code ) {
	$notice_type = 'error';
	$notice_msg  = __( 'Please fill out all required fields with valid information.', 'fraud-tenant-theme' );
} elseif ( 'mail_failed' === $notice_code ) {
	$notice_type = 'success';
	$notice_msg  = __( 'Thank you! Your enquiry has been safely received by our system.', 'fraud-tenant-theme' );
}
?>

<div class="contact-form-wrap" id="contact-form">
	<?php if ( ! empty( $notice_msg ) ) : ?>
		<div class="form-alert <?php echo 'success' === $notice_type ? 'form-alert-success' : 'form-alert-error'; ?>" role="alert">
			<?php echo esc_html( $notice_msg ); ?>
		</div>
	<?php endif; ?>

	<div class="form-alert form-alert-error form-alert-client" style="display: none;" role="alert"></div>

	<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="POST" class="ft-contact-form" novalidate>
		<input type="hidden" name="action" value="ft_submit_contact_enquiry">
		<?php wp_nonce_field( 'ft_contact_enquiry_action', 'ft_contact_enquiry_nonce' ); ?>

		<!-- Anti-Spam Honeypot (hidden from genuine users) -->
		<div class="form-hp-wrap" aria-hidden="true" style="display:none !important;">
			<label for="ft_enquiry_hp"><?php esc_html_e( 'Leave this field blank', 'fraud-tenant-theme' ); ?></label>
			<input type="text" name="ft_enquiry_hp" id="ft_enquiry_hp" tabindex="-1" autocomplete="off">
		</div>

		<div class="form-row-3">
			<div class="form-group">
				<label for="enquiry_name" class="form-label">
					<?php esc_html_e( 'Your Name', 'fraud-tenant-theme' ); ?> <span class="required" aria-hidden="true">*</span>
				</label>
				<input type="text" name="enquiry_name" id="enquiry_name" class="form-control" required maxlength="100" placeholder="<?php esc_attr_e( 'John Smith', 'fraud-tenant-theme' ); ?>">
			</div>

			<div class="form-group">
				<label for="enquiry_email" class="form-label">
					<?php esc_html_e( 'Your Email', 'fraud-tenant-theme' ); ?> <span class="required" aria-hidden="true">*</span>
				</label>
				<input type="email" name="enquiry_email" id="enquiry_email" class="form-control" required maxlength="100" placeholder="<?php esc_attr_e( 'name@example.com', 'fraud-tenant-theme' ); ?>">
			</div>

			<div class="form-group">
				<label for="enquiry_subject" class="form-label">
					<?php esc_html_e( 'Subject', 'fraud-tenant-theme' ); ?> <span class="required" aria-hidden="true">*</span>
				</label>
				<input type="text" name="enquiry_subject" id="enquiry_subject" class="form-control" required maxlength="150" placeholder="<?php esc_attr_e( 'General enquiry', 'fraud-tenant-theme' ); ?>">
			</div>
		</div>

		<div class="form-group">
			<label for="enquiry_message" class="form-label">
				<?php esc_html_e( 'Your Message', 'fraud-tenant-theme' ); ?> <span class="required" aria-hidden="true">*</span>
			</label>
			<textarea name="enquiry_message" id="enquiry_message" class="form-control" rows="4" required minlength="10" maxlength="3000" placeholder="<?php esc_attr_e( 'How can our team help you?', 'fraud-tenant-theme' ); ?>"></textarea>
			<span class="form-hint" style="font-size: 0.78rem; color: #64748B; margin-top: 4px; display: block;">
				<?php esc_html_e( 'Privacy notice: Do not include Social Security Numbers, banking details, or confidential identity documents in this enquiry message.', 'fraud-tenant-theme' ); ?>
			</span>
		</div>

		<button type="submit" class="btn btn-primary btn-lg" style="margin-top: 8px;">
			<?php esc_html_e( 'Send Enquiry', 'fraud-tenant-theme' ); ?>
			<span aria-hidden="true">&rarr;</span>
		</button>
	</form>
</div>
