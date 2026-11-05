<?php
/**
 * Pending email-change confirmation (shared by Owner and Tenant Account Settings).
 *
 * Expects $args: user_id (int), redirect (string).
 *
 * @package FraudTenantTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ft_uid      = (int) ( $args['user_id'] ?? 0 );
$ft_redirect = (string) ( $args['redirect'] ?? home_url( '/' ) );
$ft_pending  = $ft_uid ? (string) get_user_meta( $ft_uid, \FraudTenant\Services\DashboardService::META_PENDING_EMAIL, true ) : '';

if ( '' === $ft_pending ) {
	return;
}
?>
<div class="ft-email-pending" style="background:#F0FDFA; border:1px solid #0E9488; border-radius:8px; padding:14px; margin-bottom:18px; font-size:13px; color:#14263E;">
	<strong style="display:block; margin-bottom:4px;"><?php esc_html_e( 'Confirm your new email address', 'fraud-tenant-theme' ); ?></strong>
	<p style="margin:0 0 10px 0;">
		<?php
		echo esc_html(
			sprintf(
				/* translators: %s: masked pending email */
				__( 'Enter the 6-digit code sent to %s. Your current email stays active until you confirm.', 'fraud-tenant-theme' ),
				\FraudTenant\Utils\Sanitizer::mask_email( $ft_pending )
			)
		);
		?>
	</p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex; gap:8px; flex-wrap:wrap;">
		<input type="hidden" name="action" value="ft_update_profile" />
		<input type="hidden" name="account_section" value="email_confirm" />
		<input type="hidden" name="redirect_to" value="<?php echo esc_url( $ft_redirect ); ?>" />
		<?php wp_nonce_field( 'ft_update_profile_' . $ft_uid, 'ft_profile_nonce' ); ?>
		<label for="ft_email_code_<?php echo esc_attr( (string) $ft_uid ); ?>" class="screen-reader-text"><?php esc_html_e( 'Confirmation code', 'fraud-tenant-theme' ); ?></label>
		<input type="text" id="ft_email_code_<?php echo esc_attr( (string) $ft_uid ); ?>" name="email_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required class="search-ssn-input" style="height:40px; max-width:160px; background:#FFFFFF;" placeholder="000000" />
		<button type="submit" class="dashboard-btn-add" style="justify-content:center;"><?php esc_html_e( 'Confirm Email', 'fraud-tenant-theme' ); ?></button>
	</form>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:8px;">
		<input type="hidden" name="action" value="ft_update_profile" />
		<input type="hidden" name="account_section" value="email_cancel" />
		<input type="hidden" name="redirect_to" value="<?php echo esc_url( $ft_redirect ); ?>" />
		<?php wp_nonce_field( 'ft_update_profile_' . $ft_uid, 'ft_profile_nonce' ); ?>
		<button type="submit" style="background:none; border:none; color:#0E9488; cursor:pointer; padding:0; font-size:12px; text-decoration:underline;"><?php esc_html_e( 'Cancel pending change', 'fraud-tenant-theme' ); ?></button>
	</form>
</div>
