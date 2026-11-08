<?php
/**
 * Authenticated app shell — header (Owner / Tenant / Admin dashboards).
 * No marketing header or footer: compact topbar + sidebar navigation.
 *
 * Expects $args: role_label, base (page slug), section (current), nav (group => [key => [label, icon, badge]]).
 *
 * @package FraudTenantTheme
 */

defined( 'ABSPATH' ) || exit;

$args    = isset( $args ) && is_array( $args ) ? $args : array();
$user    = wp_get_current_user();
$base    = (string) ( $args['base'] ?? 'owner-dashboard' );
$current = (string) ( $args['section'] ?? 'overview' );
$unread  = (int) ( $args['unread'] ?? 0 );
$initial = strtoupper( mb_substr( $user->display_name ?: $user->user_login, 0, 1 ) );
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'ft-app ft-ui' ); ?>>
<?php wp_body_open(); ?>
<a class="ft-skip" href="#ft-main"><?php esc_html_e( 'Skip to content', 'fraud-tenant-theme' ); ?></a>
<div class="ft-shell">
	<aside class="ft-side" id="ftSide" aria-label="<?php echo esc_attr( $args['role_label'] ?? '' ); ?>">
		<div class="ft-side-brand">
			<a href="<?php echo esc_url( ft_app_url( $base ) ); ?>" class="ft-brand">
				<svg viewBox="0 0 36 36" width="30" height="30" aria-hidden="true"><path d="M18 3L4 9v8c0 7.5 6 14.4 14 16 8-1.6 14-8.5 14-16V9L18 3z" fill="#0E9488" fill-opacity=".18" stroke="#2dd4bf" stroke-width="2.5" stroke-linejoin="round"/><path d="M18 10v16M12 18h12" stroke="#2dd4bf" stroke-width="2.5" stroke-linecap="round"/></svg>
				<span>Fraud<b>Tenant</b></span>
			</a>
			<button type="button" class="ft-side-close" data-ft-close-nav aria-label="<?php esc_attr_e( 'Close menu', 'fraud-tenant-theme' ); ?>">×</button>
		</div>
		<nav class="ft-nav" aria-label="<?php esc_attr_e( 'Dashboard sections', 'fraud-tenant-theme' ); ?>">
			<?php foreach ( (array) ( $args['nav'] ?? array() ) as $group => $items ) : ?>
				<div class="ft-nav-group">
					<span class="ft-nav-title"><?php echo esc_html( $group ); ?></span>
					<?php foreach ( $items as $key => $item ) : ?>
						<a class="ft-nav-item <?php echo $key === $current ? 'is-active' : ''; ?>" href="<?php echo esc_url( ft_app_url( $base, $key ) ); ?>" <?php echo $key === $current ? 'aria-current="page"' : ''; ?>>
							<?php echo ft_icon( $item[1] ?? 'grid' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<span><?php echo esc_html( $item[0] ); ?></span>
							<?php if ( ! empty( $item[2] ) ) : ?><span class="ft-nav-count"><?php echo (int) $item[2]; ?></span><?php endif; ?>
						</a>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>
		</nav>
		<div class="ft-side-foot">
			<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Help & support', 'fraud-tenant-theme' ); ?></a>
		</div>
	</aside>
	<div class="ft-backdrop" data-ft-close-nav hidden></div>
	<div class="ft-body">
		<header class="ft-top">
			<button type="button" class="ft-menu-btn" data-ft-open-nav aria-controls="ftSide" aria-expanded="false" aria-label="<?php esc_attr_e( 'Open menu', 'fraud-tenant-theme' ); ?>"><?php echo ft_icon( 'menu' ); // phpcs:ignore ?></button>
			<div class="ft-top-title"><?php echo esc_html( $args['role_label'] ?? '' ); ?></div>
			<div class="ft-top-right">
				<a class="ft-top-bell" href="<?php echo esc_url( ft_app_url( $base, 'notifications' ) ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: unread */ _n( '%d unread notification', '%d unread notifications', $unread, 'fraud-tenant-theme' ), $unread ) ); ?>">
					<?php echo ft_icon( 'bell' ); // phpcs:ignore ?><?php if ( $unread ) : ?><span class="ft-dot"><?php echo $unread > 9 ? '9+' : (int) $unread; ?></span><?php endif; ?>
				</a>
				<div class="ft-user">
					<span class="ft-avatar" aria-hidden="true"><?php echo esc_html( $initial ); ?></span>
					<span class="ft-user-name"><?php echo esc_html( $user->display_name ); ?></span>
				</div>
				<a class="ft-btn ft-btn-ghost ft-btn-sm" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ft_logout' ), 'ft_logout_action' ) ); ?>"><?php echo ft_icon( 'logout' ); // phpcs:ignore ?><span class="ft-hide-sm"><?php esc_html_e( 'Sign out', 'fraud-tenant-theme' ); ?></span></a>
			</div>
		</header>
		<main id="ft-main" class="ft-main" tabindex="-1">
			<?php ft_app_flash(); ?>
