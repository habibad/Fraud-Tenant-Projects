<?php
/**
 * Header template for Fraud Tenant Theme.
 *
 * @package FraudTenantTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<?php
$is_auth_page      = is_page( array( 'login', 'register', 'verify-otp', 'forgot-password', 'reset-password', 'account-status' ) );
$is_dashboard_page = is_page( array( 'owner-dashboard', 'tenant-dashboard' ) );
$current_user      = wp_get_current_user();
?>

<a class="skip-link" href="#main-content"><?php esc_html_e( 'Skip to content', 'fraud-tenant-theme' ); ?></a>

<?php if ( $is_dashboard_page ) : ?>
<header class="dashboard-topbar">
	<div class="dashboard-topbar-inner">
		<div class="dashboard-topbar-left">
			<button type="button" class="dashboard-mobile-toggle" id="ftDashboardMobileToggle" aria-label="<?php esc_attr_e( 'Toggle Navigation Menu', 'fraud-tenant-theme' ); ?>">
				<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
			</button>
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="dashboard-brand" rel="home">
				<svg class="dashboard-brand-icon" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
					<path d="M18 3L4 9V17C4 24.5 10 31.4 18 33C26 31.4 32 24.5 32 17V9L18 3Z" fill="#0E9488" fill-opacity="0.15" stroke="#0E9488" stroke-width="2.5" stroke-linejoin="round"/>
					<path d="M18 10V26" stroke="#0E9488" stroke-width="2.5" stroke-linecap="round"/>
					<path d="M12 18H24" stroke="#0E9488" stroke-width="2.5" stroke-linecap="round"/>
				</svg>
				<span class="brand-text">Fraud<span>Tenant</span></span>
			</a>
			<div class="dashboard-topbar-divider"></div>
			<div class="dashboard-topbar-title">
				<?php echo is_page( 'owner-dashboard' ) ? esc_html__( 'Property Owner Dashboard', 'fraud-tenant-theme' ) : esc_html__( 'Tenant Dashboard', 'fraud-tenant-theme' ); ?>
			</div>
		</div>
		<div class="dashboard-topbar-right">
			<button type="button" class="dashboard-notif-btn" aria-label="<?php esc_attr_e( 'Notifications', 'fraud-tenant-theme' ); ?>" data-ft-modal-target="ftNotificationsModal">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
				<span class="dashboard-notif-badge"></span>
			</button>
			<div class="dashboard-user-capsule">
				<div class="dashboard-avatar">
					<?php echo esc_html( strtoupper( substr( $current_user->display_name ?: $current_user->user_login, 0, 1 ) ) ); ?>
				</div>
				<div class="dashboard-user-info">
					<span class="dashboard-user-name"><?php echo esc_html( $current_user->display_name ?: $current_user->user_login ); ?></span>
					<span class="dashboard-user-role">
						<?php echo is_page( 'owner-dashboard' ) ? esc_html__( 'Property Owner', 'fraud-tenant-theme' ) : esc_html__( 'Tenant', 'fraud-tenant-theme' ); ?>
					</span>
				</div>
			</div>
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ft_logout' ), 'ft_logout_action' ) ); ?>" class="dashboard-signout-btn" title="<?php esc_attr_e( 'Sign Out', 'fraud-tenant-theme' ); ?>">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
				<span><?php esc_html_e( 'Sign Out', 'fraud-tenant-theme' ); ?></span>
			</a>
		</div>
	</div>
</header>
<?php elseif ( $is_auth_page ) : ?>
<header class="site-header auth-header">
	<div class="container auth-header-inner">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="brand-logo" rel="home">
			<svg class="brand-icon" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
				<path d="M18 3L4 9V17C4 24.5 10 31.4 18 33C26 31.4 32 24.5 32 17V9L18 3Z" fill="#0E9488" fill-opacity="0.15" stroke="#0E9488" stroke-width="2.5" stroke-linejoin="round"/>
				<path d="M18 10V26" stroke="#0E9488" stroke-width="2.5" stroke-linecap="round"/>
				<path d="M12 18H24" stroke="#0E9488" stroke-width="2.5" stroke-linecap="round"/>
			</svg>
			<span class="brand-text">Fraud<span>Tenant</span></span>
		</a>
		<div class="auth-header-right">
			<?php if ( is_user_logged_in() ) : ?>
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ft_logout' ), 'ft_logout_action' ) ); ?>" class="auth-link-text"><?php esc_html_e( 'Sign Out', 'fraud-tenant-theme' ); ?></a>
			<?php elseif ( is_page( 'login' ) ) : ?>
				<span class="auth-hint-text"><?php esc_html_e( "Don't have an account?", 'fraud-tenant-theme' ); ?></span>
				<a href="<?php echo esc_url( home_url( '/register/' ) ); ?>" class="btn btn-outline btn-sm auth-btn-sm"><?php esc_html_e( 'Sign Up', 'fraud-tenant-theme' ); ?></a>
			<?php elseif ( is_page( 'register' ) ) : ?>
				<span class="auth-hint-text"><?php esc_html_e( 'Already have an account?', 'fraud-tenant-theme' ); ?></span>
				<a href="<?php echo esc_url( home_url( '/login/' ) ); ?>" class="btn btn-outline btn-sm auth-btn-sm"><?php esc_html_e( 'Sign In', 'fraud-tenant-theme' ); ?></a>
			<?php else : ?>
				<a href="<?php echo esc_url( home_url( '/login/' ) ); ?>" class="btn btn-outline btn-sm auth-btn-sm"><?php esc_html_e( 'Sign In', 'fraud-tenant-theme' ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</header>
<?php else : ?>
<header class="site-header">
	<div class="container header-inner">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="brand-logo" rel="home">
			<svg class="brand-icon" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
				<path d="M18 3L4 9V17C4 24.5 10 31.4 18 33C26 31.4 32 24.5 32 17V9L18 3Z" fill="#0E9488" fill-opacity="0.15" stroke="#0E9488" stroke-width="2.5" stroke-linejoin="round"/>
				<path d="M18 10V26" stroke="#0E9488" stroke-width="2.5" stroke-linecap="round"/>
				<path d="M12 18H24" stroke="#0E9488" stroke-width="2.5" stroke-linecap="round"/>
			</svg>
			<span class="brand-text">Fraud<span>Tenant</span></span>
		</a>

		<nav class="main-navigation" aria-label="<?php esc_attr_e( 'Primary Navigation', 'fraud-tenant-theme' ); ?>">
			<?php
			if ( has_nav_menu( 'primary' ) ) {
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'menu_class'     => 'nav-menu',
						'container'      => false,
						'fallback_cb'    => 'fraud_tenant_fallback_menu',
					)
				);
			} else {
				fraud_tenant_fallback_menu();
			}
			?>
		</nav>

		<div class="header-actions">
			<?php if ( is_user_logged_in() ) : ?>
				<?php if ( current_user_can( 'administrator' ) ) : ?>
					<a href="<?php echo esc_url( admin_url() ); ?>" class="btn-signin"><?php esc_html_e( 'WP Admin', 'fraud-tenant-theme' ); ?></a>
				<?php else : ?>
					<a href="<?php echo esc_url( home_url( '/account-status/' ) ); ?>" class="btn-signin"><?php esc_html_e( 'My Account', 'fraud-tenant-theme' ); ?></a>
				<?php endif; ?>
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ft_logout' ), 'ft_logout_action' ) ); ?>" class="btn btn-secondary btn-sm"><?php esc_html_e( 'Sign Out', 'fraud-tenant-theme' ); ?></a>
			<?php else : ?>
				<a href="<?php echo esc_url( home_url( '/login/' ) ); ?>" class="btn-signin"><?php esc_html_e( 'Sign In', 'fraud-tenant-theme' ); ?></a>
				<a href="<?php echo esc_url( home_url( '/register/' ) ); ?>" class="btn btn-primary btn-sm"><?php esc_html_e( 'Get Started', 'fraud-tenant-theme' ); ?></a>
			<?php endif; ?>
			<button class="mobile-menu-toggle" aria-expanded="false" aria-label="<?php esc_attr_e( 'Toggle navigation menu', 'fraud-tenant-theme' ); ?>">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<line x1="3" y1="12" x2="21" y2="12"></line>
					<line x1="3" y1="6" x2="21" y2="6"></line>
					<line x1="3" y1="18" x2="21" y2="18"></line>
				</svg>
			</button>
		</div>
	</div>

	<!-- Accessible Mobile Drawer -->
	<div class="mobile-drawer" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Mobile Menu', 'fraud-tenant-theme' ); ?>">
		<ul class="mobile-nav-list">
			<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'fraud-tenant-theme' ); ?></a></li>
			<li><a href="<?php echo esc_url( home_url( '/how-it-works/' ) ); ?>"><?php esc_html_e( 'How It Works', 'fraud-tenant-theme' ); ?></a></li>
			<li><a href="<?php echo esc_url( home_url( '/for-property-owners/' ) ); ?>"><?php esc_html_e( 'For Property Owners', 'fraud-tenant-theme' ); ?></a></li>
			<li><a href="<?php echo esc_url( home_url( '/for-tenants/' ) ); ?>"><?php esc_html_e( 'For Tenants', 'fraud-tenant-theme' ); ?></a></li>
			<li><a href="<?php echo esc_url( home_url( '/faq/' ) ); ?>"><?php esc_html_e( 'FAQ', 'fraud-tenant-theme' ); ?></a></li>
			<li><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Contact', 'fraud-tenant-theme' ); ?></a></li>
		</ul>
		<div class="mobile-actions">
			<a href="<?php echo esc_url( home_url( '/how-it-works/' ) ); ?>" class="btn btn-primary" style="width: 100%; text-align: center;"><?php esc_html_e( 'Get Started', 'fraud-tenant-theme' ); ?></a>
			<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="btn btn-outline" style="width: 100%; text-align: center;"><?php esc_html_e( 'Send Enquiry', 'fraud-tenant-theme' ); ?></a>
		</div>
	</div>
</header>
<?php endif; ?>

<main id="main-content" class="site-main">

