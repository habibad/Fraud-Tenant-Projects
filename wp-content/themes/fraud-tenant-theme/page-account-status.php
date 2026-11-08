<?php
/**
 * Template Name: Account Status Page
 *
 * Email verification activates an account, so active users are sent to their dashboard. This page
 * only explains the remaining states: not signed in, email not verified, suspended, or rejected.
 *
 * @package FraudTenantTheme
 */

defined( 'ABSPATH' ) || exit;

use FraudTenant\Auth\AuthService;
use FraudTenant\Utils\Constants;

$user_id = get_current_user_id();
$status  = ( $user_id && class_exists( AuthService::class ) ) ? AuthService::get_user_account_status( $user_id ) : 'signed_out';

if ( $user_id && ( Constants::STATUS_APPROVED === $status || user_can( $user_id, 'manage_options' ) ) ) {
	wp_safe_redirect( AuthService::dashboard_url( $user_id ) );
	exit;
}
if ( $user_id && Constants::STATUS_EMAIL_UNVERIFIED === $status ) {
	wp_safe_redirect( add_query_arg( 'email', rawurlencode( wp_get_current_user()->user_email ), home_url( '/verify-otp/' ) ) );
	exit;
}

$profile = $user_id ? AuthService::get_user_profile( $user_id ) : null;
$copy    = array(
	'signed_out'                => array( __( 'Account status', 'fraud-tenant-theme' ), __( 'Sign in to see your account.', 'fraud-tenant-theme' ) ),
	Constants::STATUS_SUSPENDED => array( __( 'Account suspended', 'fraud-tenant-theme' ), __( 'Your account has been suspended. Contact support if you believe this is a mistake.', 'fraud-tenant-theme' ) ),
	Constants::STATUS_REJECTED  => array( __( 'Account not active', 'fraud-tenant-theme' ), __( 'Your account is not active. Contact support for help.', 'fraud-tenant-theme' ) ),
);
$c = $copy[ $status ] ?? array( __( 'Account status', 'fraud-tenant-theme' ), __( 'We could not find your account profile. Please contact support.', 'fraud-tenant-theme' ) );

get_header();
?>
<div class="auth-page-wrapper">
	<div class="auth-container auth-status-container">
		<div class="auth-card auth-status-card text-center">
			<h1 class="auth-title"><?php echo esc_html( $c[0] ); ?></h1>
			<p class="auth-subtitle"><?php echo esc_html( $c[1] ); ?></p>
			<?php if ( $profile && ! empty( $profile->rejection_reason ) && in_array( $status, array( Constants::STATUS_SUSPENDED, Constants::STATUS_REJECTED ), true ) ) : ?>
				<div class="auth-alert auth-alert-error" role="alert" style="margin: 16px 0;"><span><?php echo esc_html( (string) $profile->rejection_reason ); ?></span></div>
			<?php endif; ?>
			<div style="margin-top: 24px; display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
				<?php if ( ! $user_id ) : ?>
					<a href="<?php echo esc_url( home_url( '/login/' ) ); ?>" class="btn btn-primary"><?php esc_html_e( 'Sign in', 'fraud-tenant-theme' ); ?></a>
				<?php else : ?>
					<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="btn btn-primary"><?php esc_html_e( 'Contact support', 'fraud-tenant-theme' ); ?></a>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ft_logout' ), 'ft_logout_action' ) ); ?>" class="btn btn-secondary"><?php esc_html_e( 'Sign out', 'fraud-tenant-theme' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
<?php
get_footer();
