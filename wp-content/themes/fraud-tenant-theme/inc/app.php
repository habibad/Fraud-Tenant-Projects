<?php
/**
 * Authenticated app shell helpers (Owner / Tenant / Admin dashboards).
 * Presentation only — all data and authorization come from the Fraud Tenant Core services.
 *
 * @package FraudTenantTheme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Inline SVG icon (decorative; aria-hidden).
 *
 * @param string $name Icon key.
 * @return string
 */
function ft_icon( string $name ): string {
	$p = array(
		'home'     => '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
		'building' => '<path d="M4 21V5a1 1 0 0 1 1-1h9a1 1 0 0 1 1 1v16M15 9h4a1 1 0 0 1 1 1v11M8 8h3M8 12h3M8 16h3M3 21h18"/>',
		'plus'     => '<path d="M12 5v14M5 12h14"/>',
		'check'    => '<path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="9"/>',
		'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
		'file'     => '<path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>',
		'fileplus' => '<path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5M12 11v6M9 14h6"/>',
		'card'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h4"/>',
		'bell'     => '<path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
		'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
		'shield'   => '<path d="M12 3 4 6v6c0 5 3.4 8.4 8 9 4.6-.6 8-4 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
		'scale'    => '<path d="M12 3v18M5 7h14M7 7l-3 7a3 3 0 0 0 6 0zM17 7l-3 7a3 3 0 0 0 6 0zM8 21h8"/>',
		'history'  => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8M3 3v5h5M12 7v5l3 3"/>',
		'id'       => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="12" r="2.5"/><path d="M14 10h4M14 14h3"/>',
		'logout'   => '<path d="M9 21H5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h4M16 17l5-5-5-5M21 12H9"/>',
		'menu'     => '<path d="M3 6h18M3 12h18M3 18h18"/>',
		'grid'     => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
	);
	return '<svg class="ft-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ( $p[ $name ] ?? $p['grid'] ) . '</svg>';
}

/**
 * Dashboard section URL.
 *
 * @param string $base    Page slug (owner-dashboard|tenant-dashboard|admin-dashboard).
 * @param string $section Section key ('' = overview).
 * @param array  $args    Extra query args.
 * @return string
 */
function ft_app_url( string $base, string $section = '', array $args = array() ): string {
	$url = home_url( '/' . $base . '/' );
	if ( '' !== $section && 'overview' !== $section ) {
		$args = array_merge( array( 'section' => $section ), $args );
	}
	return $args ? add_query_arg( $args, $url ) : $url;
}

/**
 * Requested section, validated against an allowlist (legacy ?ft_tab= / ?tab= still accepted).
 *
 * @param array  $allowed Allowed keys.
 * @param string $default Default key.
 * @return string
 */
function ft_app_section( array $allowed, string $default = 'overview' ): string {
	$raw = $_GET['section'] ?? ( get_query_var( 'ft_tab' ) ?: ( $_GET['ft_tab'] ?? ( $_GET['tab'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$key = sanitize_key( (string) $raw );
	return in_array( $key, $allowed, true ) ? $key : $default;
}

/**
 * Flash notice set by a request handler (one-time).
 */
function ft_app_flash(): void {
	if ( ! class_exists( '\FraudTenant\Auth\AuthHandler' ) ) {
		return;
	}
	$f = \FraudTenant\Auth\AuthHandler::get_flash_notice();
	if ( $f ) {
		printf( '<div class="ft-alert %s" role="%s">%s</div>', 'success' === $f['type'] ? 'is-success' : 'is-error', 'success' === $f['type'] ? 'status' : 'alert', esc_html( (string) $f['message'] ) );
	}
}

/**
 * Status badge (same component as the admin console).
 *
 * @param string $status Status.
 * @param string $label  Label.
 * @return string
 */
function ft_badge( string $status, string $label = '' ): string {
	return \FraudTenant\Admin\AdminConsole::badge( $status, $label );
}

/**
 * Date display.
 *
 * @param string|null $mysql Datetime (UTC).
 * @param bool        $time  With time.
 * @return string
 */
function ft_date( ?string $mysql, bool $time = false ): string {
	return \FraudTenant\Admin\AdminConsole::date( $mysql, $time );
}

/**
 * Money display.
 *
 * @param float|string $amount Amount.
 * @return string
 */
function ft_money( $amount ): string {
	return '$' . number_format_i18n( (float) $amount, 2 );
}

/**
 * Hidden fields for member forms: admin-post action + a validated return URL.
 *
 * @param string $action admin-post action.
 * @param string $return Return URL.
 */
function ft_form_fields( string $action, string $return ): void {
	echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
	echo '<input type="hidden" name="redirect_to" value="' . esc_url( $return ) . '">';
}

/**
 * Previously submitted (non-sensitive) form values after a validation error.
 *
 * @return array
 */
function ft_old_input(): array {
	static $data = null;
	if ( null === $data ) {
		$data = class_exists( '\FraudTenant\Auth\AuthHandler' ) ? (array) \FraudTenant\Auth\AuthHandler::get_flash_form_data() : array();
	}
	return $data;
}

/**
 * Empty state.
 *
 * @param string $message Message.
 * @param string $cta_url Optional call-to-action URL.
 * @param string $cta     Call-to-action label.
 */
function ft_empty( string $message, string $cta_url = '', string $cta = '' ): void {
	echo '<div class="ft-empty"><p>' . esc_html( $message ) . '</p>';
	if ( $cta_url ) {
		echo '<a class="ft-btn ft-btn-primary" href="' . esc_url( $cta_url ) . '">' . esc_html( $cta ) . '</a>';
	}
	echo '</div>';
}
