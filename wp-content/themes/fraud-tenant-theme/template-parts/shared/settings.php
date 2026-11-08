<?php
/**
 * Owner / Tenant — Profile & Account Settings: profile, verified email change, password,
 * notification preferences, sessions, account status. All changes go through
 * DashboardService::update_safe_profile (nonce, current-user-only, current password where required).
 *
 * @package FraudTenantTheme
 */

defined( 'ABSPATH' ) || exit;

use FraudTenant\Auth\AuthService;
use FraudTenant\Services\DashboardService;
use FraudTenant\Services\NotificationService;
use FraudTenant\Utils\Constants;

$u        = $args['user'];
$uid      = (int) $u->ID;
$base     = $args['base'];
$profile  = AuthService::get_user_profile( $uid );
$is_owner = in_array( Constants::ROLE_OWNER, (array) $u->roles, true );
$pending  = (string) get_user_meta( $uid, DashboardService::META_PENDING_EMAIL, true );
$prefs    = NotificationService::get_prefs( $uid );
$sessions = class_exists( '\WP_Session_Tokens' ) ? count( \WP_Session_Tokens::get_instance( $uid )->get_all() ) : 1;
$here     = ft_app_url( $base, 'settings' );
$nonce    = static function () use ( $uid ): void {
	wp_nonce_field( 'ft_update_profile_' . $uid, 'ft_profile_nonce' );
};
$open     = static function ( string $section ) use ( $here, $nonce ): void {
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ft-form">';
	ft_form_fields( 'ft_update_profile', $here );
	echo '<input type="hidden" name="account_section" value="' . esc_attr( $section ) . '">';
	$nonce();
};
?>
<header class="ft-page-head"><div><h1><?php esc_html_e( 'Profile & account settings', 'fraud-tenant-theme' ); ?></h1><p><?php esc_html_e( 'Manage your contact details, sign-in security and email preferences.', 'fraud-tenant-theme' ); ?></p></div></header>
<div class="ft-grid ft-grid-2">
	<section class="ft-card">
		<div class="ft-card-head"><h2><?php esc_html_e( 'Profile', 'fraud-tenant-theme' ); ?></h2></div>
		<?php $open( 'profile' ); ?>
			<div class="ft-row">
				<div><label class="ft-label" for="ft-sf"><?php esc_html_e( 'First name', 'fraud-tenant-theme' ); ?></label><input class="ft-input" id="ft-sf" name="first_name" value="<?php echo esc_attr( $u->first_name ); ?>" autocomplete="given-name"></div>
				<div><label class="ft-label" for="ft-sl"><?php esc_html_e( 'Last name', 'fraud-tenant-theme' ); ?></label><input class="ft-input" id="ft-sl" name="last_name" value="<?php echo esc_attr( $u->last_name ); ?>" autocomplete="family-name"></div>
			</div>
			<label class="ft-label" for="ft-sp"><?php esc_html_e( 'Phone', 'fraud-tenant-theme' ); ?></label>
			<input class="ft-input" id="ft-sp" name="phone" type="tel" value="<?php echo esc_attr( (string) ( $profile->phone ?? '' ) ); ?>" autocomplete="tel">
			<?php if ( $is_owner ) : ?>
				<label class="ft-label" for="ft-sb"><?php esc_html_e( 'Business name (optional)', 'fraud-tenant-theme' ); ?></label>
				<input class="ft-input" id="ft-sb" name="business_name" value="<?php echo esc_attr( (string) ( $profile->business_name ?? '' ) ); ?>" autocomplete="organization">
			<?php else : ?>
				<label class="ft-label" for="ft-sa"><?php esc_html_e( 'Mailing address', 'fraud-tenant-theme' ); ?></label>
				<input class="ft-input" id="ft-sa" name="address_line1" value="<?php echo esc_attr( (string) ( $profile->address_line1 ?? '' ) ); ?>" autocomplete="address-line1">
				<div class="ft-row">
					<div><label class="ft-label" for="ft-sc"><?php esc_html_e( 'City', 'fraud-tenant-theme' ); ?></label><input class="ft-input" id="ft-sc" name="city" value="<?php echo esc_attr( (string) ( $profile->city ?? '' ) ); ?>"></div>
					<div><label class="ft-label" for="ft-ss"><?php esc_html_e( 'State', 'fraud-tenant-theme' ); ?></label><input class="ft-input" id="ft-ss" name="state" maxlength="2" value="<?php echo esc_attr( (string) ( $profile->state ?? '' ) ); ?>"></div>
					<div><label class="ft-label" for="ft-sz"><?php esc_html_e( 'ZIP', 'fraud-tenant-theme' ); ?></label><input class="ft-input" id="ft-sz" name="postal_code" value="<?php echo esc_attr( (string) ( $profile->postal_code ?? '' ) ); ?>"></div>
				</div>
			<?php endif; ?>
			<button class="ft-btn ft-btn-primary" type="submit"><?php esc_html_e( 'Save profile', 'fraud-tenant-theme' ); ?></button>
		</form>
	</section>

	<section class="ft-card">
		<div class="ft-card-head"><h2><?php esc_html_e( 'Account', 'fraud-tenant-theme' ); ?></h2><?php echo ft_badge( AuthService::get_user_account_status( $uid ), __( 'Active', 'fraud-tenant-theme' ) ); // phpcs:ignore ?></div>
		<dl class="ft-dl">
			<dt><?php esc_html_e( 'Account type', 'fraud-tenant-theme' ); ?></dt><dd><?php echo $is_owner ? esc_html__( 'Property owner', 'fraud-tenant-theme' ) : esc_html__( 'Tenant', 'fraud-tenant-theme' ); ?></dd>
			<dt><?php esc_html_e( 'Member since', 'fraud-tenant-theme' ); ?></dt><dd><?php echo ft_date( $u->user_registered ); // phpcs:ignore ?></dd>
			<dt><?php esc_html_e( 'Email', 'fraud-tenant-theme' ); ?></dt><dd><?php echo esc_html( $u->user_email ); ?> <?php echo ft_badge( 'success', __( 'Verified', 'fraud-tenant-theme' ) ); // phpcs:ignore ?></dd>
			<dt><?php esc_html_e( 'Active sessions', 'fraud-tenant-theme' ); ?></dt><dd><?php echo (int) $sessions; ?></dd>
		</dl>
		<?php $open( 'sessions' ); ?>
			<button class="ft-btn ft-btn-secondary" type="submit" <?php disabled( $sessions <= 1 ); ?>><?php esc_html_e( 'Sign out of all other devices', 'fraud-tenant-theme' ); ?></button>
		</form>
		<p class="ft-help"><?php esc_html_e( 'Forgot your password? You can reset it from the sign-in page with a code sent to your email.', 'fraud-tenant-theme' ); ?> <a href="<?php echo esc_url( home_url( '/forgot-password/' ) ); ?>"><?php esc_html_e( 'Reset password', 'fraud-tenant-theme' ); ?></a></p>
	</section>

	<section class="ft-card">
		<div class="ft-card-head"><h2><?php esc_html_e( 'Change email address', 'fraud-tenant-theme' ); ?></h2></div>
		<?php if ( $pending ) : ?>
			<div class="ft-alert is-info"><?php echo esc_html( sprintf( /* translators: %s: masked new email */ __( 'We sent a 6-digit code to %s. Your current email stays active until you confirm.', 'fraud-tenant-theme' ), \FraudTenant\Utils\Sanitizer::mask_email( $pending ) ) ); ?></div>
			<?php $open( 'email_confirm' ); ?>
				<label class="ft-label" for="ft-ec"><?php esc_html_e( 'Confirmation code', 'fraud-tenant-theme' ); ?></label>
				<input class="ft-input" id="ft-ec" name="email_code" inputmode="numeric" pattern="\d{6}" maxlength="6" required autocomplete="one-time-code">
				<button class="ft-btn ft-btn-primary" type="submit"><?php esc_html_e( 'Confirm new email', 'fraud-tenant-theme' ); ?></button>
			</form>
			<?php $open( 'email_cancel' ); ?><button class="ft-btn ft-btn-ghost ft-btn-sm" type="submit"><?php esc_html_e( 'Cancel the change', 'fraud-tenant-theme' ); ?></button></form>
		<?php else : ?>
			<?php $open( 'email' ); ?>
				<label class="ft-label" for="ft-ne"><?php esc_html_e( 'New email address', 'fraud-tenant-theme' ); ?></label>
				<input class="ft-input" id="ft-ne" name="new_email" type="email" required autocomplete="email">
				<label class="ft-label" for="ft-ep"><?php esc_html_e( 'Current password', 'fraud-tenant-theme' ); ?></label>
				<input class="ft-input" id="ft-ep" name="current_password" type="password" required autocomplete="current-password">
				<button class="ft-btn ft-btn-secondary" type="submit"><?php esc_html_e( 'Send confirmation code', 'fraud-tenant-theme' ); ?></button>
				<p class="ft-help"><?php esc_html_e( 'The change only happens after you enter the code sent to the new address. Your old address is notified.', 'fraud-tenant-theme' ); ?></p>
			</form>
		<?php endif; ?>
	</section>

	<section class="ft-card">
		<div class="ft-card-head"><h2><?php esc_html_e( 'Change password', 'fraud-tenant-theme' ); ?></h2></div>
		<?php $open( 'password' ); ?>
			<label class="ft-label" for="ft-cp"><?php esc_html_e( 'Current password', 'fraud-tenant-theme' ); ?></label>
			<input class="ft-input" id="ft-cp" name="current_password" type="password" required autocomplete="current-password">
			<label class="ft-label" for="ft-np"><?php esc_html_e( 'New password (8+ characters)', 'fraud-tenant-theme' ); ?></label>
			<input class="ft-input" id="ft-np" name="new_password" type="password" required minlength="8" autocomplete="new-password">
			<label class="ft-label" for="ft-np2"><?php esc_html_e( 'Confirm new password', 'fraud-tenant-theme' ); ?></label>
			<input class="ft-input" id="ft-np2" name="confirm_password" type="password" required minlength="8" autocomplete="new-password">
			<button class="ft-btn ft-btn-secondary" type="submit"><?php esc_html_e( 'Update password', 'fraud-tenant-theme' ); ?></button>
			<p class="ft-help"><?php esc_html_e( 'Changing your password signs you out on every other device.', 'fraud-tenant-theme' ); ?></p>
		</form>
	</section>

	<section class="ft-card">
		<div class="ft-card-head"><h2><?php esc_html_e( 'Email notifications', 'fraud-tenant-theme' ); ?></h2></div>
		<?php $open( 'notifications' ); ?>
			<?php foreach ( NotificationService::OPTIONAL_CATEGORIES as $k => $label ) : ?>
				<?php if ( ( 'screening' === $k && $is_owner ) ) { continue; } ?>
				<label class="ft-check"><input type="checkbox" name="email_prefs[<?php echo esc_attr( $k ); ?>]" value="1" <?php checked( ! empty( $prefs[ $k ] ) ); ?>> <span><?php echo esc_html( $label ); ?></span></label>
			<?php endforeach; ?>
			<p class="ft-help"><?php esc_html_e( 'Security and account messages (verification codes, password and email changes, identity decisions) are always sent.', 'fraud-tenant-theme' ); ?></p>
			<button class="ft-btn ft-btn-secondary" type="submit"><?php esc_html_e( 'Save preferences', 'fraud-tenant-theme' ); ?></button>
		</form>
	</section>
</div>
