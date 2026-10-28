<?php
/**
 * Template Name: Register Page
 *
 * Distinct registration forms for Property Owners and Tenants.
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

$flash_notice     = class_exists( AuthHandler::class ) ? AuthHandler::get_flash_notice() : null;
$form_data        = class_exists( AuthHandler::class ) ? AuthHandler::get_flash_form_data() : array();
$preselected_role = isset( $_GET['role'] ) && in_array( $_GET['role'], array( 'owner', 'tenant' ), true )
	? sanitize_key( $_GET['role'] )
	: ( isset( $form_data['account_type'] ) && 'tenant' === $form_data['account_type'] ? 'tenant' : 'owner' );

$us_states = array(
	'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California',
	'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware', 'DC' => 'District of Columbia', 'FL' => 'Florida',
	'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana',
	'IA' => 'Iowa', 'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine',
	'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi',
	'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada', 'NH' => 'New Hampshire',
	'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota',
	'OH' => 'Ohio', 'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island',
	'SC' => 'South Carolina', 'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah',
	'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming'
);

get_header();
?>

<div class="auth-page-wrapper">
	<div class="auth-container auth-register-container">
		<div class="auth-card auth-register-card">
			<div class="auth-card-header text-center">
				<h1 class="auth-title"><?php esc_html_e( 'Create Your Account', 'fraud-tenant-theme' ); ?></h1>
				<p class="auth-subtitle"><?php esc_html_e( 'Select your role to access customized verification and registration features', 'fraud-tenant-theme' ); ?></p>
			</div>

			<?php if ( $flash_notice ) : ?>
				<div class="auth-alert auth-alert-<?php echo esc_attr( $flash_notice['type'] === 'success' ? 'success' : 'error' ); ?>" role="alert">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
					<span><?php echo esc_html( $flash_notice['message'] ); ?></span>
				</div>
			<?php endif; ?>

			<!-- Role Switcher Cards (Matches Showcase Visuals) -->
			<div class="form-group role-selector-section">
				<label class="form-label text-center" style="display:block;"><?php esc_html_e( 'Select Account Type', 'fraud-tenant-theme' ); ?></label>
				<div class="role-selector-grid" role="tablist" aria-label="<?php esc_attr_e( 'Account Roles', 'fraud-tenant-theme' ); ?>">
					<!-- Property Owner Tab Card -->
					<button type="button" class="role-card role-tab-btn <?php echo 'owner' === $preselected_role ? 'is-active' : ''; ?>" id="tab-owner" data-role="owner" role="tab" aria-selected="<?php echo 'owner' === $preselected_role ? 'true' : 'false'; ?>" aria-controls="form-container-owner">
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
					</button>

					<!-- Tenant Tab Card -->
					<button type="button" class="role-card role-tab-btn <?php echo 'tenant' === $preselected_role ? 'is-active' : ''; ?>" id="tab-tenant" data-role="tenant" role="tab" aria-selected="<?php echo 'tenant' === $preselected_role ? 'true' : 'false'; ?>" aria-controls="form-container-tenant">
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
					</button>
				</div>
			</div>

			<!-- ======================================================= -->
			<!-- FORM A: PROPERTY OWNER REGISTRATION                      -->
			<!-- ======================================================= -->
			<div class="role-form-container <?php echo 'owner' === $preselected_role ? 'is-visible' : 'is-hidden'; ?>" id="form-container-owner" role="tabpanel" aria-labelledby="tab-owner">
				<div class="form-role-banner">
					<div class="form-role-badge"><?php esc_html_e( 'Individual Property Owner Application', 'fraud-tenant-theme' ); ?></div>
					<p class="form-role-note"><?php esc_html_e( 'Provide your personal details, primary rental property address, and an ownership verification document to begin verification.', 'fraud-tenant-theme' ); ?></p>
				</div>

				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" enctype="multipart/form-data" class="auth-form" id="ft-register-owner-form" novalidate>
					<input type="hidden" name="action" value="ft_register">
					<input type="hidden" name="account_type" value="owner">
					<?php wp_nonce_field( 'ft_register_action', 'ft_register_nonce' ); ?>

					<!-- Section: Account & Contact -->
					<div class="form-section-title"><?php esc_html_e( '1. Account & Legal Contact', 'fraud-tenant-theme' ); ?></div>

					<div class="form-row form-row-2col">
						<div class="form-group">
							<label for="owner-first-name" class="form-label"><?php esc_html_e( 'First Name', 'fraud-tenant-theme' ); ?> <span class="required">*</span></label>
							<input type="text" id="owner-first-name" name="first_name" class="form-input" placeholder="e.g. John" value="<?php echo esc_attr( $form_data['first_name'] ?? '' ); ?>" required autocomplete="given-name">
						</div>
						<div class="form-group">
							<label for="owner-last-name" class="form-label"><?php esc_html_e( 'Last Name', 'fraud-tenant-theme' ); ?> <span class="required">*</span></label>
							<input type="text" id="owner-last-name" name="last_name" class="form-input" placeholder="e.g. Smith" value="<?php echo esc_attr( $form_data['last_name'] ?? '' ); ?>" required autocomplete="family-name">
						</div>
					</div>

					<div class="form-row form-row-2col">
						<div class="form-group">
							<label for="owner-email" class="form-label"><?php esc_html_e( 'Email Address', 'fraud-tenant-theme' ); ?> <span class="required">*</span></label>
							<div class="input-with-icon">
								<input type="email" id="owner-email" name="email" class="form-input" placeholder="you@example.com" value="<?php echo esc_attr( $form_data['email'] ?? '' ); ?>" required autocomplete="email">
								<span class="field-icon" aria-hidden="true">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path></svg>
								</span>
							</div>
						</div>
						<div class="form-group">
							<label for="owner-phone" class="form-label"><?php esc_html_e( 'Phone Number', 'fraud-tenant-theme' ); ?> <span class="required">*</span></label>
							<div class="input-with-icon">
								<input type="tel" id="owner-phone" name="phone" class="form-input" placeholder="(555) 000-0000" value="<?php echo esc_attr( $form_data['phone'] ?? '' ); ?>" required autocomplete="tel">
								<span class="field-icon" aria-hidden="true">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
								</span>
							</div>
						</div>
					</div>

					<div class="form-row form-row-2col">
						<div class="form-group">
							<label for="owner-password" class="form-label"><?php esc_html_e( 'Password', 'fraud-tenant-theme' ); ?> <span class="required">*</span></label>
							<div class="input-with-icon">
								<input type="password" id="owner-password" name="password" class="form-input" placeholder="<?php esc_attr_e( 'Min 8 characters', 'fraud-tenant-theme' ); ?>" required minlength="8" autocomplete="new-password">
								<button type="button" class="btn-toggle-pw" aria-label="<?php esc_attr_e( 'Toggle password visibility', 'fraud-tenant-theme' ); ?>" data-target="owner-password">
									<svg class="icon-eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
									<svg class="icon-eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
								</button>
							</div>
						</div>
						<div class="form-group">
							<label for="owner-confirm-password" class="form-label"><?php esc_html_e( 'Confirm Password', 'fraud-tenant-theme' ); ?> <span class="required">*</span></label>
							<div class="input-with-icon">
								<input type="password" id="owner-confirm-password" name="confirm_password" class="form-input" placeholder="<?php esc_attr_e( 'Repeat password', 'fraud-tenant-theme' ); ?>" required minlength="8" autocomplete="new-password">
								<button type="button" class="btn-toggle-pw" aria-label="<?php esc_attr_e( 'Toggle password visibility', 'fraud-tenant-theme' ); ?>" data-target="owner-confirm-password">
									<svg class="icon-eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
									<svg class="icon-eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
								</button>
							</div>
						</div>
					</div>

					<!-- Section: Primary Rental Property -->
					<div class="form-section-title"><?php esc_html_e( '2. Primary Rental Property Details', 'fraud-tenant-theme' ); ?></div>

					<div class="form-group">
						<label for="owner-property-street" class="form-label"><?php esc_html_e( 'Property Street Address', 'fraud-tenant-theme' ); ?> <span class="required">*</span></label>
						<input type="text" id="owner-property-street" name="property_street" class="form-input" placeholder="e.g. 742 Evergreen Terrace, Unit 4" value="<?php echo esc_attr( $form_data['property_street'] ?? '' ); ?>" required>
					</div>

					<div class="form-row form-row-3col">
						<div class="form-group">
							<label for="owner-property-city" class="form-label"><?php esc_html_e( 'City', 'fraud-tenant-theme' ); ?> <span class="required">*</span></label>
							<input type="text" id="owner-property-city" name="property_city" class="form-input" placeholder="e.g. Springfield" value="<?php echo esc_attr( $form_data['property_city'] ?? '' ); ?>" required>
						</div>
						<div class="form-group">
							<label for="owner-property-state" class="form-label"><?php esc_html_e( 'State', 'fraud-tenant-theme' ); ?> <span class="required">*</span></label>
							<select id="owner-property-state" name="property_state" class="form-input" required>
								<option value=""><?php esc_html_e( 'Select State', 'fraud-tenant-theme' ); ?></option>
								<?php
								$selected_state = $form_data['property_state'] ?? '';
								foreach ( $us_states as $code => $name ) :
									?>
									<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $selected_state, $code ); ?>>
										<?php echo esc_html( $name . ' (' . $code . ')' ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="form-group">
							<label for="owner-property-zip" class="form-label"><?php esc_html_e( 'ZIP Code', 'fraud-tenant-theme' ); ?> <span class="required">*</span></label>
							<input type="text" id="owner-property-zip" name="property_zip" class="form-input" placeholder="e.g. 97477" value="<?php echo esc_attr( $form_data['property_zip'] ?? '' ); ?>" required maxlength="10">
						</div>
					</div>

					<!-- Section: Ownership Verification Document Upload -->
					<div class="form-section-title"><?php esc_html_e( '3. Ownership Verification Document', 'fraud-tenant-theme' ); ?></div>

					<div class="form-group">
						<label for="owner-document" class="form-label"><?php esc_html_e( 'Proof of Ownership Document', 'fraud-tenant-theme' ); ?> <span class="required">*</span></label>
						<div class="document-upload-card">
							<div class="document-upload-icon">
								<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#0E9488" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
							</div>
							<div class="document-upload-content">
								<input type="file" id="owner-document" name="ownership_document" class="document-file-input" accept=".pdf,.png,.jpg,.jpeg" required>
								<div class="document-upload-text">
									<span class="upload-primary-text"><?php esc_html_e( 'Select deed, tax assessment, or municipal utility bill', 'fraud-tenant-theme' ); ?></span>
									<span class="upload-hint-text"><?php esc_html_e( 'Accepted formats: PDF, PNG, JPG (maximum 10MB)', 'fraud-tenant-theme' ); ?></span>
								</div>
							</div>
						</div>
						<div class="privacy-security-notice">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0E9488" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
							<span><?php esc_html_e( 'Encrypted Private Storage: Documents are stored in secure protected storage and never served via public URLs.', 'fraud-tenant-theme' ); ?></span>
						</div>
					</div>

					<!-- Section: Agreements & Legal Consents -->
					<div class="form-section-title"><?php esc_html_e( '4. Agreements & Legal Consents', 'fraud-tenant-theme' ); ?></div>

					<div class="form-group checkbox-group">
						<label class="checkbox-label checkbox-terms">
							<input type="checkbox" name="terms_accepted" value="1" required <?php checked( ! empty( $form_data['terms_accepted'] ) ); ?>>
							<span>
								<?php
								printf(
									/* translators: 1: terms url */
									__( 'I agree to the <a href="%1$s" target="_blank" rel="noopener">Terms of Service</a> as a Property Owner. <span class="required">*</span>', 'fraud-tenant-theme' ),
									esc_url( home_url( '/terms-of-service/' ) )
								);
								?>
							</span>
						</label>

						<label class="checkbox-label checkbox-terms">
							<input type="checkbox" name="privacy_accepted" value="1" required <?php checked( ! empty( $form_data['privacy_accepted'] ) ); ?>>
							<span>
								<?php
								printf(
									/* translators: 1: privacy url */
									__( 'I acknowledge and agree to the <a href="%1$s" target="_blank" rel="noopener">Privacy Policy</a> and FCRA permissible purpose requirements. <span class="required">*</span>', 'fraud-tenant-theme' ),
									esc_url( home_url( '/privacy-policy/' ) )
								);
								?>
							</span>
						</label>
					</div>

					<button type="submit" class="btn btn-primary btn-block btn-auth-submit" id="btn-submit-owner">
						<span><?php esc_html_e( 'Register as Property Owner', 'fraud-tenant-theme' ); ?></span>
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
					</button>
				</form>
			</div>

			<!-- ======================================================= -->
			<!-- FORM B: TENANT REGISTRATION                              -->
			<!-- ======================================================= -->
			<div class="role-form-container <?php echo 'tenant' === $preselected_role ? 'is-visible' : 'is-hidden'; ?>" id="form-container-tenant" role="tabpanel" aria-labelledby="tab-tenant">
				<div class="form-role-banner">
					<div class="form-role-badge badge-tenant"><?php esc_html_e( 'Tenant Application', 'fraud-tenant-theme' ); ?></div>
					<p class="form-role-note"><?php esc_html_e( 'Provide your personal details and current residence to create your account and access verified rental records. Date of birth is not required.', 'fraud-tenant-theme' ); ?></p>
				</div>

				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="auth-form" id="ft-register-tenant-form" novalidate>
					<input type="hidden" name="action" value="ft_register">
					<input type="hidden" name="account_type" value="tenant">
					<?php wp_nonce_field( 'ft_register_action', 'ft_register_nonce' ); ?>

					<!-- Section: Account & Contact -->
					<div class="form-section-title"><?php esc_html_e( '1. Account & Legal Contact', 'fraud-tenant-theme' ); ?></div>

					<div class="form-row form-row-2col">
						<div class="form-group">
							<label for="tenant-first-name" class="form-label"><?php esc_html_e( 'First Name', 'fraud-tenant-theme' ); ?> <span class="required">*</span></label>
							<input type="text" id="tenant-first-name" name="first_name" class="form-input" placeholder="e.g. Jane" value="<?php echo esc_attr( $form_data['first_name'] ?? '' ); ?>" required autocomplete="given-name">
						</div>
						<div class="form-group">
							<label for="tenant-last-name" class="form-label"><?php esc_html_e( 'Last Name', 'fraud-tenant-theme' ); ?> <span class="required">*</span></label>
							<input type="text" id="tenant-last-name" name="last_name" class="form-input" placeholder="e.g. Doe" value="<?php echo esc_attr( $form_data['last_name'] ?? '' ); ?>" required autocomplete="family-name">
						</div>
					</div>

					<div class="form-row form-row-2col">
						<div class="form-group">
							<label for="tenant-email" class="form-label"><?php esc_html_e( 'Email Address', 'fraud-tenant-theme' ); ?> <span class="required">*</span></label>
							<div class="input-with-icon">
								<input type="email" id="tenant-email" name="email" class="form-input" placeholder="you@example.com" value="<?php echo esc_attr( $form_data['email'] ?? '' ); ?>" required autocomplete="email">
								<span class="field-icon" aria-hidden="true">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path></svg>
								</span>
							</div>
						</div>
						<div class="form-group">
							<label for="tenant-phone" class="form-label"><?php esc_html_e( 'Phone Number', 'fraud-tenant-theme' ); ?> <span class="required">*</span></label>
							<div class="input-with-icon">
								<input type="tel" id="tenant-phone" name="phone" class="form-input" placeholder="(555) 000-0000" value="<?php echo esc_attr( $form_data['phone'] ?? '' ); ?>" required autocomplete="tel">
								<span class="field-icon" aria-hidden="true">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
								</span>
							</div>
						</div>
					</div>

					<div class="form-row form-row-2col">
						<div class="form-group">
							<label for="tenant-password" class="form-label"><?php esc_html_e( 'Password', 'fraud-tenant-theme' ); ?> <span class="required">*</span></label>
							<div class="input-with-icon">
								<input type="password" id="tenant-password" name="password" class="form-input" placeholder="<?php esc_attr_e( 'Min 8 characters', 'fraud-tenant-theme' ); ?>" required minlength="8" autocomplete="new-password">
								<button type="button" class="btn-toggle-pw" aria-label="<?php esc_attr_e( 'Toggle password visibility', 'fraud-tenant-theme' ); ?>" data-target="tenant-password">
									<svg class="icon-eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
									<svg class="icon-eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
								</button>
							</div>
						</div>
						<div class="form-group">
							<label for="tenant-confirm-password" class="form-label"><?php esc_html_e( 'Confirm Password', 'fraud-tenant-theme' ); ?> <span class="required">*</span></label>
							<div class="input-with-icon">
								<input type="password" id="tenant-confirm-password" name="confirm_password" class="form-input" placeholder="<?php esc_attr_e( 'Repeat password', 'fraud-tenant-theme' ); ?>" required minlength="8" autocomplete="new-password">
								<button type="button" class="btn-toggle-pw" aria-label="<?php esc_attr_e( 'Toggle password visibility', 'fraud-tenant-theme' ); ?>" data-target="tenant-confirm-password">
									<svg class="icon-eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
									<svg class="icon-eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
								</button>
							</div>
						</div>
					</div>

					<!-- Section: Current Residential Address -->
					<div class="form-section-title"><?php esc_html_e( '2. Current Residential Address', 'fraud-tenant-theme' ); ?></div>

					<div class="form-group">
						<label for="tenant-current-street" class="form-label"><?php esc_html_e( 'Street Address', 'fraud-tenant-theme' ); ?> <span class="required">*</span></label>
						<input type="text" id="tenant-current-street" name="current_street" class="form-input" placeholder="e.g. 123 Main Street, Apt 2B" value="<?php echo esc_attr( $form_data['current_street'] ?? '' ); ?>" required>
					</div>

					<div class="form-row form-row-3col">
						<div class="form-group">
							<label for="tenant-current-city" class="form-label"><?php esc_html_e( 'City', 'fraud-tenant-theme' ); ?> <span class="required">*</span></label>
							<input type="text" id="tenant-current-city" name="current_city" class="form-input" placeholder="e.g. Chicago" value="<?php echo esc_attr( $form_data['current_city'] ?? '' ); ?>" required>
						</div>
						<div class="form-group">
							<label for="tenant-current-state" class="form-label"><?php esc_html_e( 'State', 'fraud-tenant-theme' ); ?> <span class="required">*</span></label>
							<select id="tenant-current-state" name="current_state" class="form-input" required>
								<option value=""><?php esc_html_e( 'Select State', 'fraud-tenant-theme' ); ?></option>
								<?php
								$selected_state = $form_data['current_state'] ?? '';
								foreach ( $us_states as $code => $name ) :
									?>
									<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $selected_state, $code ); ?>>
										<?php echo esc_html( $name . ' (' . $code . ')' ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="form-group">
							<label for="tenant-current-zip" class="form-label"><?php esc_html_e( 'ZIP Code', 'fraud-tenant-theme' ); ?> <span class="required">*</span></label>
							<input type="text" id="tenant-current-zip" name="current_zip" class="form-input" placeholder="e.g. 60601" value="<?php echo esc_attr( $form_data['current_zip'] ?? '' ); ?>" required maxlength="10">
						</div>
					</div>

					<!-- Section: Previous Rental History (Optional / Conditional) -->
					<div class="form-section-title"><?php esc_html_e( '3. Previous Rental History (Optional)', 'fraud-tenant-theme' ); ?></div>

					<div class="form-group">
						<label class="checkbox-label" for="toggle-previous-address">
							<input type="checkbox" id="toggle-previous-address" name="has_previous_address" value="1" <?php checked( ! empty( $form_data['has_previous_address'] ) ); ?>>
							<span><?php esc_html_e( 'I have lived at a previous rental address within the last 2 years', 'fraud-tenant-theme' ); ?></span>
						</label>
					</div>

					<div class="conditional-prev-address <?php echo ! empty( $form_data['has_previous_address'] ) ? 'is-visible' : 'is-hidden'; ?>" id="prev-address-fields">
						<div class="form-group">
							<label for="tenant-prev-street" class="form-label"><?php esc_html_e( 'Previous Street Address', 'fraud-tenant-theme' ); ?></label>
							<input type="text" id="tenant-prev-street" name="prev_street" class="form-input" placeholder="e.g. 456 Oak Avenue" value="<?php echo esc_attr( $form_data['prev_street'] ?? '' ); ?>">
						</div>
						<div class="form-row form-row-3col">
							<div class="form-group">
								<label for="tenant-prev-city" class="form-label"><?php esc_html_e( 'City', 'fraud-tenant-theme' ); ?></label>
								<input type="text" id="tenant-prev-city" name="prev_city" class="form-input" placeholder="e.g. Evanston" value="<?php echo esc_attr( $form_data['prev_city'] ?? '' ); ?>">
							</div>
							<div class="form-group">
								<label for="tenant-prev-state" class="form-label"><?php esc_html_e( 'State', 'fraud-tenant-theme' ); ?></label>
								<select id="tenant-prev-state" name="prev_state" class="form-input">
									<option value=""><?php esc_html_e( 'Select State', 'fraud-tenant-theme' ); ?></option>
									<?php
									$prev_state_val = $form_data['prev_state'] ?? '';
									foreach ( $us_states as $code => $name ) :
										?>
										<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $prev_state_val, $code ); ?>>
											<?php echo esc_html( $name . ' (' . $code . ')' ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="form-group">
								<label for="tenant-prev-zip" class="form-label"><?php esc_html_e( 'ZIP Code', 'fraud-tenant-theme' ); ?></label>
								<input type="text" id="tenant-prev-zip" name="prev_zip" class="form-input" placeholder="e.g. 60201" value="<?php echo esc_attr( $form_data['prev_zip'] ?? '' ); ?>" maxlength="10">
							</div>
						</div>
					</div>

					<!-- Section: Agreements & Legal Consents -->
					<div class="form-section-title"><?php esc_html_e( '4. Agreements & Legal Consents', 'fraud-tenant-theme' ); ?></div>

					<div class="form-group checkbox-group">
						<label class="checkbox-label checkbox-terms">
							<input type="checkbox" name="terms_accepted" value="1" required <?php checked( ! empty( $form_data['terms_accepted'] ) ); ?>>
							<span>
								<?php
								printf(
									/* translators: 1: terms url */
									__( 'I agree to the <a href="%1$s" target="_blank" rel="noopener">Terms of Service</a> as a Tenant. <span class="required">*</span>', 'fraud-tenant-theme' ),
									esc_url( home_url( '/terms-of-service/' ) )
								);
								?>
							</span>
						</label>

						<label class="checkbox-label checkbox-terms">
							<input type="checkbox" name="privacy_accepted" value="1" required <?php checked( ! empty( $form_data['privacy_accepted'] ) ); ?>>
							<span>
								<?php
								printf(
									/* translators: 1: privacy url */
									__( 'I acknowledge and agree to the <a href="%1$s" target="_blank" rel="noopener">Privacy Policy</a> and understand my dispute rights under FCRA. <span class="required">*</span>', 'fraud-tenant-theme' ),
									esc_url( home_url( '/privacy-policy/' ) )
								);
								?>
							</span>
						</label>
					</div>

					<button type="submit" class="btn btn-primary btn-block btn-auth-submit" id="btn-submit-tenant">
						<span><?php esc_html_e( 'Register as Tenant', 'fraud-tenant-theme' ); ?></span>
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
					</button>
				</form>
			</div>

			<!-- Form Card Footer -->
			<div class="auth-card-footer text-center">
				<p class="auth-footer-prompt">
					<?php esc_html_e( 'Already have an account?', 'fraud-tenant-theme' ); ?>
					<a href="<?php echo esc_url( home_url( '/login/' ) ); ?>" class="auth-footer-link"><?php esc_html_e( 'Sign In', 'fraud-tenant-theme' ); ?></a>
				</p>
			</div>
		</div>
	</div>
</div>

<?php
get_footer();
