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

<a class="skip-link" href="#main-content"><?php esc_html_e( 'Skip to content', 'fraud-tenant-theme' ); ?></a>

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
			<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="btn btn-outline btn-sm"><?php esc_html_e( 'Enquire', 'fraud-tenant-theme' ); ?></a>
			<a href="<?php echo esc_url( home_url( '/how-it-works/' ) ); ?>" class="btn btn-primary btn-sm"><?php esc_html_e( 'Get Started', 'fraud-tenant-theme' ); ?></a>
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

<main id="main-content" class="site-main">

