<?php
/**
 * Contact & Enquiry Form Template Part.
 *
 * @package FraudTenantTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$notice = get_transient( 'ft_contact_notice_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ) );
if ( $notice ) {
	delete_transient( 'ft_contact_notice_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ) );
}
?>

<div class="contact-form-wrap">
	<?php if ( ! empty( $notice ) ) : ?>
		<div class="form-alert <?php echo 'success' === $notice['type'] ? 'form-alert-success' : 'form-alert-error'; ?>" role="alert">
			<?php echo esc_html( $notice['message'] ); ?>
		</div>
	<?php endif; ?>

	<div class="form-alert form-alert-error form-alert-client" style="display: none;" role="alert"></div>

	<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="POST" class="ft-contact-form" novalidate>
		<input type="hidden" name="action" value="ft_submit_contact_enquiry">
		<?php wp_nonce_field( 'ft_contact_enquiry_action', 'ft_contact_enquiry_nonce' ); ?>

		<!-- Anti-Spam Honeypot (hidden from genuine users) -->
		<div class="form-hp-wrap" aria-hidden="true">
			<label for="ft_enquiry_hp"><?php esc_html_e( 'Leave this field blank', 'fraud-tenant-theme' ); ?></label>
			<input type="text" name="ft_enquiry_hp" id="ft_enquiry_hp" tabindex="-1" autocomplete="off">
		</div>

		<div class="form-group">
			<label for="enquiry_name" class="form-label">
				<?php esc_html_e( 'Full Name', 'fraud-tenant-theme' ); ?> <span class="required" aria-hidden="true">*</span>
			</label>
			<input type="text" name="enquiry_name" id="enquiry_name" class="form-control" required maxlength="100" placeholder="<?php esc_attr_e( 'e.g. John Smith', 'fraud-tenant-theme' ); ?>" value="<?php echo esc_attr( $_GET['prev_name'] ?? '' ); ?>">
		</div>

		<div class="form-group">
			<label for="enquiry_email" class="form-label">
				<?php esc_html_e( 'Email Address', 'fraud-tenant-theme' ); ?> <span class="required" aria-hidden="true">*</span>
			</label>
			<input type="email" name="enquiry_email" id="enquiry_email" class="form-control" required maxlength="100" placeholder="<?php esc_attr_e( 'name@example.com', 'fraud-tenant-theme' ); ?>" value="<?php echo esc_attr( $_GET['prev_email'] ?? '' ); ?>">
		</div>

		<div class="form-group">
			<label for="enquiry_subject" class="form-label">
				<?php esc_html_e( 'Subject', 'fraud-tenant-theme' ); ?>
			</label>
			<input type="text" name="enquiry_subject" id="enquiry_subject" class="form-control" maxlength="150" placeholder="<?php esc_attr_e( 'General inquiry, screening question, etc.', 'fraud-tenant-theme' ); ?>" value="<?php echo esc_attr( $_GET['prev_subject'] ?? '' ); ?>">
		</div>

		<div class="form-group">
			<label for="enquiry_message" class="form-label">
				<?php esc_html_e( 'Message', 'fraud-tenant-theme' ); ?> <span class="required" aria-hidden="true">*</span>
			</label>
			<textarea name="enquiry_message" id="enquiry_message" class="form-control" rows="5" required minlength="10" maxlength="2000" placeholder="<?php esc_attr_e( 'How can our team help you?', 'fraud-tenant-theme' ); ?>"><?php echo esc_textarea( $_GET['prev_message'] ?? '' ); ?></textarea>
		</div>

		<button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
			<?php esc_html_e( 'Send Enquiry', 'fraud-tenant-theme' ); ?>
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
				<line x1="5" y1="12" x2="19" y2="12"></line>
				<polyline points="12 5 19 12 12 19"></polyline>
			</svg>
		</button>
	</form>
</div>

