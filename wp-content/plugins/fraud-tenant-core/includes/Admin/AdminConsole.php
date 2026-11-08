<?php
/**
 * Admin Command Center — one implementation, two interfaces.
 *
 * The same section views (includes/Admin/views/*.php), the same action handler and the same
 * service layer power:
 *   1. the custom Admin Dashboard (front-end app shell at /admin-dashboard/), and
 *   2. native WordPress admin pages (Fraud Tenant menu in /wp-admin/).
 * Only the surrounding chrome differs. Every action posts to admin-post.php (action=ft_admin),
 * is checked for a per-action nonce AND a per-action capability, calls a service, records a flash
 * message and returns the administrator to the interface they came from (validated ft_return).
 *
 * @package FraudTenant\Admin
 */

namespace FraudTenant\Admin;

use FraudTenant\Auth\Capabilities;
use FraudTenant\Utils\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminConsole {

	/**
	 * Sections: key => [label, capability, group, wp-admin slug].
	 */
	public const SECTIONS = array(
		'overview'      => array( 'Overview', Capabilities::ACCESS_ADMIN_PORTAL, 'Command Center', 'fraud-tenant-admin' ),
		'users'         => array( 'User Management', Capabilities::REVIEW_USER_REGISTRATIONS, 'People', 'fraud-tenant-users' ),
		'owners'        => array( 'Owner Management', Capabilities::REVIEW_USER_REGISTRATIONS, 'People', 'fraud-tenant-owners' ),
		'tenants'       => array( 'Tenant Management', Capabilities::REVIEW_USER_REGISTRATIONS, 'People', 'fraud-tenant-tenants' ),
		'accounts'      => array( 'Account & Email Verification', Capabilities::SUSPEND_USERS, 'People', 'fraud-tenant-accounts' ),
		'properties'    => array( 'Property Management', Capabilities::REVIEW_PROPERTIES, 'Records', 'fraud-tenant-properties' ),
		'incidents'     => array( 'Incident Reports', Capabilities::REVIEW_INCIDENT_REPORTS, 'Records', 'fraud-tenant-incidents' ),
		'disputes'      => array( 'Tenant Disputes', Capabilities::REVIEW_TENANT_DISPUTES, 'Records', 'fraud-tenant-disputes' ),
		'screening'     => array( 'Screening Activity', Capabilities::VIEW_ALL_SCREENING_LOGS, 'Records', 'fraud-tenant-screening' ),
		'identities'    => array( 'Tenant Identities', Capabilities::MANAGE_TENANT_IDENTITIES, 'Identity', 'fraud-tenant-identities' ),
		'exceptions'    => array( 'Identity Match Exceptions', Capabilities::MANAGE_TENANT_IDENTITIES, 'Identity', 'fraud-tenant-exceptions' ),
		'claims'        => array( 'Tenant Account Claims', Capabilities::MANAGE_TENANT_IDENTITIES, 'Identity', 'fraud-tenant-claims' ),
		'disclosure'    => array( 'Report Association & Disclosure', Capabilities::MANAGE_REPORT_VISIBILITY, 'Identity', 'fraud-tenant-disclosure' ),
		'plans'         => array( 'Membership Plans', Capabilities::MANAGE_MEMBERSHIP_PLANS, 'Billing', 'fraud-tenant-plans' ),
		'subscriptions' => array( 'Subscriptions & Billing', Capabilities::MANAGE_MEMBERSHIP_PLANS, 'Billing', 'fraud-tenant-subscriptions' ),
		'stripe'        => array( 'Stripe Settings', 'manage_options', 'Billing', 'fraud-tenant-settings' ),
		'notifications' => array( 'Notifications & Email', Capabilities::MANAGE_PLATFORM_SETTINGS, 'Platform', 'fraud-tenant-notifications' ),
		'audit'         => array( 'Audit Logs', Capabilities::VIEW_AUDIT_LOGS, 'Platform', 'fraud-tenant-audit' ),
		'settings'      => array( 'Platform Settings', Capabilities::MANAGE_PLATFORM_SETTINGS, 'Platform', 'fraud-tenant-platform' ),
		'security'      => array( 'Security & Compliance', Capabilities::MANAGE_PLATFORM_SETTINGS, 'Platform', 'fraud-tenant-security' ),
	);

	/**
	 * Wire hooks.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_wp_menus' ) );
		add_action( 'admin_post_ft_admin', array( __CLASS__, 'handle' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_wp_assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'guard_wp_page' ) );
	}

	// ------------------------------------------------------------------ access & routing

	/**
	 * Whether a user may open a section.
	 *
	 * @param string $section Section key.
	 * @param int    $user_id User (0 = current).
	 * @return bool
	 */
	public static function can( string $section, int $user_id = 0 ): bool {
		$user_id = $user_id ?: get_current_user_id();
		if ( $user_id <= 0 || ! isset( self::SECTIONS[ $section ] ) ) {
			return false;
		}
		return user_can( $user_id, 'manage_options' ) || user_can( $user_id, self::SECTIONS[ $section ][1] );
	}

	/**
	 * Sections visible to the user, grouped.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function nav(): array {
		$out = array();
		foreach ( self::SECTIONS as $key => $def ) {
			if ( self::can( $key ) ) {
				$out[ $def[2] ][ $key ] = $def[0];
			}
		}
		return $out;
	}

	/**
	 * Is the custom (front-end) console rendering?
	 *
	 * @return bool
	 */
	public static function in_app(): bool {
		return ! is_admin();
	}

	/**
	 * URL of a section in the current interface.
	 *
	 * @param string $section Section.
	 * @param array  $args    Query args.
	 * @return string
	 */
	public static function url( string $section, array $args = array() ): string {
		if ( self::in_app() ) {
			return add_query_arg( array_merge( array( 'section' => $section ), $args ), home_url( '/admin-dashboard/' ) );
		}
		$slug = self::SECTIONS[ $section ][3] ?? 'fraud-tenant-admin';
		return add_query_arg( array_merge( array( 'page' => $slug ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Register the native WordPress admin menu (same sections as the custom console).
	 */
	public static function register_wp_menus(): void {
		add_menu_page( __( 'Fraud Tenant', 'fraud-tenant-core' ), __( 'Fraud Tenant', 'fraud-tenant-core' ), Capabilities::ACCESS_ADMIN_PORTAL, 'fraud-tenant-admin', array( __CLASS__, 'render_wp_page' ), 'dashicons-shield', 30 );
		foreach ( self::SECTIONS as $key => $def ) {
			add_submenu_page( 'fraud-tenant-admin', $def[0], 'overview' === $key ? __( 'Overview', 'fraud-tenant-core' ) : $def[0], $def[1], $def[3], array( __CLASS__, 'render_wp_page' ) );
		}
	}

	/**
	 * Section for the current wp-admin page.
	 *
	 * @return string|null
	 */
	protected static function wp_section(): ?string {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		foreach ( self::SECTIONS as $key => $def ) {
			if ( $def[3] === $page ) {
				return $key;
			}
		}
		return null;
	}

	/**
	 * True 403 before wp-admin output when the user lacks the section capability.
	 */
	public static function guard_wp_page(): void {
		$section = self::wp_section();
		if ( null !== $section && ! wp_doing_ajax() && ! self::can( $section ) ) {
			wp_die( esc_html__( 'You do not have permission to view this section.', 'fraud-tenant-core' ), esc_html__( 'Forbidden', 'fraud-tenant-core' ), array( 'response' => 403 ) );
		}
	}

	/**
	 * wp-admin page callback.
	 */
	public static function render_wp_page(): void {
		$section = self::wp_section() ?? 'overview';
		echo '<div class="wrap ft-ui ft-wp">';
		echo '<nav class="ft-wp-nav" aria-label="' . esc_attr__( 'Fraud Tenant sections', 'fraud-tenant-core' ) . '">';
		foreach ( self::nav() as $group => $items ) {
			echo '<div class="ft-wp-nav-group"><span>' . esc_html( $group ) . '</span>';
			foreach ( $items as $key => $label ) {
				printf( '<a href="%s" class="%s">%s</a>', esc_url( self::url( $key ) ), $key === $section ? 'is-active' : '', esc_html( $label ) );
			}
			echo '</div>';
		}
		printf( '<a class="ft-wp-nav-app" href="%s">%s →</a>', esc_url( home_url( '/admin-dashboard/?section=' . $section ) ), esc_html__( 'Open in Command Center', 'fraud-tenant-core' ) );
		echo '</nav>';
		self::render_section( $section );
		echo '</div>';
	}

	/**
	 * Render a section view (permission re-checked here; views never bypass it).
	 *
	 * @param string $section Section.
	 */
	public static function render_section( string $section ): void {
		if ( ! self::can( $section ) ) {
			echo '<div class="ft-alert is-error">' . esc_html__( 'You do not have permission to view this section.', 'fraud-tenant-core' ) . '</div>';
			return;
		}
		self::render_flash();
		$file = __DIR__ . '/views/' . $section . '.php';
		if ( file_exists( $file ) ) {
			include $file;
		}
	}

	/**
	 * Shared stylesheet on Fraud Tenant wp-admin pages.
	 *
	 * @param string $hook Admin page hook.
	 */
	public static function enqueue_wp_assets( string $hook ): void {
		if ( null === self::wp_section() ) {
			return;
		}
		wp_enqueue_style( 'ft-ui', FRAUD_TENANT_CORE_URL . 'assets/css/ft-ui.css', array(), self::asset_version() );
	}

	/**
	 * Cache-busting version for the shared stylesheet.
	 *
	 * @return string
	 */
	public static function asset_version(): string {
		$f = FRAUD_TENANT_CORE_PATH . 'assets/css/ft-ui.css';
		return file_exists( $f ) ? (string) filemtime( $f ) : Constants::VERSION;
	}

	// ------------------------------------------------------------------ forms & actions

	/**
	 * Open an action form (nonce + return URL) — every admin mutation goes through handle().
	 *
	 * @param string $do     Action key.
	 * @param array  $hidden Hidden fields.
	 * @param string $attrs  Extra form attributes.
	 */
	public static function form( string $do, array $hidden = array(), string $attrs = '' ): void {
		printf( '<form method="post" action="%s" class="ft-form" %s>', esc_url( admin_url( 'admin-post.php' ) ), $attrs ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<input type="hidden" name="action" value="ft_admin">';
		echo '<input type="hidden" name="do" value="' . esc_attr( $do ) . '">';
		echo '<input type="hidden" name="ft_return" value="' . esc_attr( self::current_url() ) . '">';
		wp_nonce_field( 'ft_admin_' . $do, '_ftnonce' );
		foreach ( $hidden as $k => $v ) {
			echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( (string) $v ) . '">';
		}
	}

	/**
	 * Hidden return field for legacy forms (Stripe / plans) so they come back to the same interface.
	 */
	public static function return_field(): void {
		echo '<input type="hidden" name="ft_return" value="' . esc_attr( self::current_url() ) . '">';
	}

	/**
	 * Current request URL (same-site).
	 *
	 * @return string
	 */
	public static function current_url(): string {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		return esc_url_raw( set_url_scheme( 'http://' . wp_parse_url( home_url(), PHP_URL_HOST ) . $uri ) );
	}

	/**
	 * Validated return URL (same host only), else the section URL in wp-admin.
	 *
	 * @param string $fallback_section Section.
	 * @return string
	 */
	public static function return_url( string $fallback_section = 'overview' ): string {
		$fallback = self::url( $fallback_section );
		$raw      = isset( $_POST['ft_return'] ) ? esc_url_raw( wp_unslash( (string) $_POST['ft_return'] ) ) : '';
		return $raw ? wp_validate_redirect( $raw, $fallback ) : $fallback;
	}

	/**
	 * Flash message for the current admin.
	 *
	 * @param bool   $ok      Success?
	 * @param string $message Message.
	 */
	public static function flash( bool $ok, string $message ): void {
		set_transient( 'ft_console_flash_' . get_current_user_id(), array( 'ok' => $ok, 'message' => $message ), 60 );
	}

	/**
	 * Print and clear the flash message.
	 */
	public static function render_flash(): void {
		$key = 'ft_console_flash_' . get_current_user_id();
		$f   = get_transient( $key );
		if ( is_array( $f ) ) {
			delete_transient( $key );
			printf( '<div class="ft-alert %s" role="status">%s</div>', $f['ok'] ? 'is-success' : 'is-error', esc_html( (string) $f['message'] ) );
		}
	}

	/**
	 * Read a POST field.
	 *
	 * @param string $k   Key.
	 * @param string $def Default.
	 * @return string
	 */
	protected static function post( string $k, string $def = '' ): string {
		return isset( $_POST[ $k ] ) ? (string) wp_unslash( $_POST[ $k ] ) : $def;
	}

	/**
	 * Single admin action handler (both interfaces).
	 */
	public static function handle(): void {
		$do  = sanitize_key( self::post( 'do' ) );
		$uid = get_current_user_id();
		if ( ! $uid || ! wp_verify_nonce( self::post( '_ftnonce' ), 'ft_admin_' . $do ) ) {
			wp_die( esc_html__( 'Security check failed. Please refresh the page and try again.', 'fraud-tenant-core' ), 403 );
		}
		$map = self::actions();
		if ( ! isset( $map[ $do ] ) ) {
			wp_die( esc_html__( 'Unknown action.', 'fraud-tenant-core' ), 400 );
		}
		list( $section, $callback ) = $map[ $do ];
		if ( ! self::can( $section, $uid ) ) {
			\FraudTenant\Services\AuditLogger::log( 'admin.action_denied', 'system', null, array( 'action' => $do ), $uid );
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'fraud-tenant-core' ), 403 );
		}
		$res = call_user_func( $callback, $uid );
		self::flash( ! empty( $res['success'] ), (string) ( $res['message'] ?? '' ) );
		wp_safe_redirect( self::return_url( $section ) );
		if ( ! defined( 'FT_DOING_TESTS' ) ) {
			exit;
		}
	}

	/**
	 * Action registry: do => [section (capability scope), callback(int $admin_id): array].
	 *
	 * @return array<string, array{0: string, 1: callable}>
	 */
	public static function actions(): array {
		$int = static fn( string $k ): int => absint( self::post( $k ) );
		return array(
			// People & accounts.
			'user_suspend'      => array( 'accounts', static fn( $a ) => AdminReviewService::suspend_account( $int( 'user_id' ), $a, self::post( 'reason' ) ) ),
			'user_reactivate'   => array( 'accounts', static fn( $a ) => AdminReviewService::reactivate_account( $int( 'user_id' ), $a, self::post( 'reason' ) ) ),
			'user_resend_otp'   => array( 'accounts', static fn( $a ) => self::resend_verification( $int( 'user_id' ), $a ) ),
			'user_verify_email' => array( 'accounts', static fn( $a ) => self::verify_email_manually( $int( 'user_id' ), $a, self::post( 'reason' ) ) ),
			'billing_qa_flag'   => array( 'stripe', static fn( $a ) => array( 'success' => \FraudTenant\Services\StripeCredentialService::set_billing_qa_account( $int( 'user_id' ), ! empty( $_POST['enable'] ), $a ), 'message' => __( 'Billing QA flag updated.', 'fraud-tenant-core' ) ) ),
			// Properties.
			'property_verify'   => array( 'properties', static fn( $a ) => user_can( $a, Capabilities::VERIFY_PROPERTIES ) || user_can( $a, 'manage_options' ) ? AdminReviewService::verify_property( $int( 'property_id' ), $a, self::post( 'notes' ) ) : array( 'success' => false, 'message' => __( 'Verifying properties requires the verify capability.', 'fraud-tenant-core' ) ) ),
			'property_reject'   => array( 'properties', static fn( $a ) => AdminReviewService::reject_property( $int( 'property_id' ), $a, self::post( 'reason' ) ) ),
			'property_info'     => array( 'properties', static fn( $a ) => AdminReviewService::request_property_info( $int( 'property_id' ), $a, self::post( 'note' ) ) ),
			// Incidents.
			'incident_decide'   => array( 'incidents', static fn( $a ) => \FraudTenant\Services\IncidentReviewService::decide_incident_report( $int( 'report_id' ), $a, sanitize_key( self::post( 'decision' ) ), sanitize_key( self::post( 'reason_code' ) ), array( 'disclose_tenant' => ! empty( $_POST['disclose_tenant'] ), 'disclose_screening' => ! empty( $_POST['disclose_screening'] ), 'feedback' => self::post( 'feedback' ) ) ) ),
			'incident_correct'  => array( 'incidents', static fn( $a ) => \FraudTenant\Services\IncidentService::admin_correct_report( $int( 'report_id' ), $a, array_filter( array( 'category' => self::post( 'category' ) ?: null, 'incident_date' => self::post( 'incident_date' ) ?: null, 'amount_involved' => '' !== self::post( 'amount_involved' ) ? self::post( 'amount_involved' ) : null, 'factual_description' => self::post( 'factual_description' ) ?: null ), static fn( $v ) => null !== $v ), self::post( 'reason' ), $int( 'dispute_id' ) ) ),
			// Identity.
			'identity_link'     => array( 'disclosure', static fn( $a ) => \FraudTenant\Services\IdentityService::link_report( $int( 'report_id' ), $int( 'identity_id' ), $a, self::post( 'reason' ) ) ),
			'identity_link_ssn' => array( 'disclosure', static fn( $a ) => \FraudTenant\Services\IdentityService::admin_link_by_identity( $int( 'report_id' ), \FraudTenant\Services\IdentityService::clean_name( self::post( 'first_name' ) ), \FraudTenant\Services\IdentityService::clean_name( self::post( 'last_name' ) ), self::post( 'ssn' ), $a, self::post( 'reason' ) ) ),
			'identity_unlink'   => array( 'disclosure', static fn( $a ) => \FraudTenant\Services\IdentityService::unlink_report( $int( 'report_id' ), $a, self::post( 'reason' ) ) ),
			'exception_resolve' => array( 'exceptions', static fn( $a ) => \FraudTenant\Services\IdentityService::resolve_exception( $int( 'report_id' ), sanitize_key( self::post( 'resolution' ) ), $a, self::post( 'note' ) ) ),
			'claim_approve'     => array( 'claims', static fn( $a ) => \FraudTenant\Services\IdentityClaimService::approve( $int( 'claim_id' ), $a, self::post( 'note' ) ) ),
			'claim_reject'      => array( 'claims', static fn( $a ) => \FraudTenant\Services\IdentityClaimService::reject( $int( 'claim_id' ), $a, self::post( 'note' ) ) ),
			'claim_revoke'      => array( 'identities', static fn( $a ) => \FraudTenant\Services\IdentityService::revoke_claim( $int( 'identity_id' ), $a, self::post( 'reason' ) ) ),
			'disclosure_set'    => array( 'disclosure', static fn( $a ) => \FraudTenant\Services\ReportVisibilityService::set_disclosure( $int( 'report_id' ), sanitize_key( self::post( 'audience' ) ), '1' === self::post( 'enable' ), $a, self::post( 'reason' ) ) ),
			// Disputes.
			'dispute_review'    => array( 'disputes', static fn( $a ) => \FraudTenant\Services\DisputeService::review_dispute( $int( 'dispute_id' ), $a, self::post( 'internal_notes' ) ) ),
			'dispute_resolve'   => array( 'disputes', static fn( $a ) => \FraudTenant\Services\DisputeService::can_resolve_disputes( $a ) ? \FraudTenant\Services\DisputeService::resolve_dispute( $int( 'dispute_id' ), $a, sanitize_key( self::post( 'resolution_action' ) ), self::post( 'resolution_notes' ), self::post( 'internal_notes' ) ) : array( 'success' => false, 'message' => __( 'Resolving disputes requires the resolve capability.', 'fraud-tenant-core' ) ) ),
			// Billing.
			'subscription_sync' => array( 'subscriptions', static fn( $a ) => self::sync_subscription( $int( 'subscription_id' ) ) ),
			// Platform & security.
			'legal_release'     => array( 'security', static fn( $a ) => \FraudTenant\Services\ComplianceService::set_gate( \FraudTenant\Services\ComplianceService::GATE_OWNER_DISCLOSURE, '1' === self::post( 'enable' ), $a, self::post( 'statement' ) ) ),
			'reencrypt'         => array( 'security', static fn( $a ) => self::reencrypt() ),
			'test_email'        => array( 'notifications', static fn( $a ) => self::send_test_email( $a ) ),
		);
	}

	// ------------------------------------------------------------------ action implementations

	/**
	 * Resend the registration verification code to an unverified account.
	 *
	 * @param int $user_id  User.
	 * @param int $admin_id Admin.
	 * @return array
	 */
	protected static function resend_verification( int $user_id, int $admin_id ): array {
		$user = get_userdata( $user_id );
		if ( ! $user || Constants::STATUS_EMAIL_UNVERIFIED !== \FraudTenant\Auth\AuthService::get_user_account_status( $user_id ) ) {
			return array( 'success' => false, 'message' => __( 'Only unverified accounts can be sent a verification code.', 'fraud-tenant-core' ) );
		}
		$r = \FraudTenant\Services\OtpService::generate_otp( $user_id, $user->user_email, 'registration' );
		\FraudTenant\Services\AuditLogger::log( 'admin.verification_resent', 'user', $user_id, array( 'sent' => ! empty( $r['success'] ) ), $admin_id );
		return array( 'success' => ! empty( $r['success'] ), 'message' => ! empty( $r['success'] ) ? __( 'A new verification code was emailed to the user.', 'fraud-tenant-core' ) : ( $r['message'] ?? __( 'The code could not be sent.', 'fraud-tenant-core' ) ) );
	}

	/**
	 * Support override: mark an email as verified after out-of-band confirmation (reason required, audited).
	 *
	 * @param int    $user_id  User.
	 * @param int    $admin_id Admin.
	 * @param string $reason   Reason.
	 * @return array
	 */
	protected static function verify_email_manually( int $user_id, int $admin_id, string $reason ): array {
		global $wpdb;
		$reason = trim( sanitize_textarea_field( $reason ) );
		if ( mb_strlen( $reason ) < 10 ) {
			return array( 'success' => false, 'message' => __( 'Describe how the email address was confirmed (at least 10 characters).', 'fraud-tenant-core' ) );
		}
		$n = $wpdb->update( $wpdb->prefix . Constants::TABLE_USER_PROFILES, array( 'approval_status' => Constants::STATUS_APPROVED, 'updated_at' => current_time( 'mysql', true ) ), array( 'user_id' => $user_id, 'approval_status' => Constants::STATUS_EMAIL_UNVERIFIED ) );
		if ( ! $n ) {
			return array( 'success' => false, 'message' => __( 'The account is not awaiting email verification.', 'fraud-tenant-core' ) );
		}
		\FraudTenant\Services\AuditLogger::log( 'admin.email_verified_manually', 'user', $user_id, array( 'reason_length' => mb_strlen( $reason ) ), $admin_id );
		return array( 'success' => true, 'message' => __( 'The email address was marked verified and the account is active.', 'fraud-tenant-core' ) );
	}

	/**
	 * Re-sync one subscription from Stripe's authoritative state.
	 *
	 * @param int $subscription_id Local subscription row.
	 * @return array
	 */
	protected static function sync_subscription( int $subscription_id ): array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT stripe_subscription_id, livemode FROM {$wpdb->prefix}" . Constants::TABLE_SUBSCRIPTIONS . ' WHERE id = %d', $subscription_id ) );
		if ( ! $row || ! preg_match( '/^sub_[A-Za-z0-9]+$/', (string) $row->stripe_subscription_id ) ) {
			return array( 'success' => false, 'message' => __( 'This subscription has no Stripe subscription to synchronize.', 'fraud-tenant-core' ) );
		}
		try {
			$r = \FraudTenant\Services\StripeWebhookService::sync_subscription( (string) $row->stripe_subscription_id, $row->livemode ? Constants::STRIPE_MODE_LIVE : Constants::STRIPE_MODE_TEST, 'admin.manual_sync' );
			/* translators: %s: status */
			return array( 'success' => true, 'message' => sprintf( __( 'Synchronized from Stripe. Current status: %s.', 'fraud-tenant-core' ), $r['status'] ) );
		} catch ( \Throwable $e ) {
			return array( 'success' => false, 'message' => __( 'Stripe synchronization failed verification. Nothing was changed.', 'fraud-tenant-core' ) );
		}
	}

	/**
	 * Re-encrypt protected fields with the current data key and protect legacy plaintext names.
	 *
	 * @return array
	 */
	protected static function reencrypt(): array {
		if ( ! \FraudTenant\Services\SensitiveDataService::is_available() ) {
			return array( 'success' => false, 'message' => __( 'The data encryption key is not configured.', 'fraud-tenant-core' ) );
		}
		$n = \FraudTenant\Services\IdentityService::protect_legacy_names();
		$r = \FraudTenant\Services\SensitiveDataService::reencrypt_all();
		/* translators: 1: updated, 2: failed, 3: legacy protected */
		return array( 'success' => 0 === $r['failed'], 'message' => sprintf( __( 'Re-encryption complete: %1$d updated, %2$d could not be decrypted, %3$d legacy names protected.', 'fraud-tenant-core' ), $r['updated'], $r['failed'], $n['protected'] ) );
	}

	/**
	 * Send a test email to the acting administrator through the configured mail transport.
	 *
	 * @param int $admin_id Admin.
	 * @return array
	 */
	protected static function send_test_email( int $admin_id ): array {
		$ok = \FraudTenant\Services\NotificationService::send_email( $admin_id, __( 'Test email', 'fraud-tenant-core' ), __( 'This is a test message from the Fraud Tenant Notifications & Email settings page.', 'fraud-tenant-core' ) );
		\FraudTenant\Services\AuditLogger::log( 'admin.test_email_sent', 'system', null, array( 'accepted' => $ok ), $admin_id );
		return array( 'success' => $ok, 'message' => $ok ? __( 'Test email handed to the mail transport. Check your inbox to confirm delivery.', 'fraud-tenant-core' ) : __( 'The mail transport rejected the message. Check the SMTP configuration.', 'fraud-tenant-core' ) );
	}

	// ------------------------------------------------------------------ view helpers

	/**
	 * Status badge.
	 *
	 * @param string $status Status key.
	 * @param string $label  Optional label.
	 * @return string HTML.
	 */
	public static function badge( string $status, string $label = '' ): string {
		$tone = array(
			'approved' => 'success', 'verified' => 'success', 'active' => 'success', 'resolved' => 'success', 'match' => 'success', 'matched' => 'success', 'created' => 'info', 'manual' => 'info', 'yes' => 'success', 'visible' => 'success', 'processed' => 'success', 'trialing' => 'success',
			'submitted' => 'info', 'pending' => 'warning', 'pending_verification' => 'warning', 'under_review' => 'warning', 'needs_clarification' => 'warning', 'info_requested' => 'warning', 'email_unverified' => 'warning', 'exception' => 'warning', 'name_mismatch' => 'warning', 'no_identity' => 'info', 'past_due' => 'warning', 'incomplete' => 'warning', 'processing' => 'warning',
			'rejected' => 'danger', 'suspended' => 'danger', 'claimed_by_other' => 'danger', 'canceled' => 'neutral', 'unpaid' => 'danger', 'failed' => 'danger', 'unlinked' => 'neutral', 'archived' => 'neutral', 'no' => 'neutral', 'hidden' => 'neutral',
			'success' => 'success', 'warning' => 'warning', 'danger' => 'danger', 'info' => 'info', 'neutral' => 'neutral',
		);
		$t = $tone[ $status ] ?? 'neutral';
		return '<span class="ft-badge is-' . esc_attr( $t ) . '">' . esc_html( $label ?: ucwords( str_replace( '_', ' ', $status ) ) ) . '</span>';
	}

	/**
	 * Date for display (stored values are UTC).
	 *
	 * @param string|null $mysql Datetime.
	 * @param bool        $time  Include time.
	 * @return string
	 */
	public static function date( ?string $mysql, bool $time = false ): string {
		if ( empty( $mysql ) || '0000-00-00 00:00:00' === $mysql ) {
			return '—';
		}
		return esc_html( mysql2date( $time ? 'M j, Y g:i A' : 'M j, Y', $mysql ) );
	}

	/**
	 * Pagination links.
	 *
	 * @param string $section Section.
	 * @param int    $page    Current page.
	 * @param int    $total   Total items.
	 * @param int    $per     Per page.
	 * @param array  $args    Extra args.
	 */
	public static function pagination( string $section, int $page, int $total, int $per, array $args = array() ): void {
		$pages = (int) ceil( $total / max( 1, $per ) );
		if ( $pages <= 1 ) {
			return;
		}
		echo '<nav class="ft-pagination" aria-label="' . esc_attr__( 'Pagination', 'fraud-tenant-core' ) . '">';
		for ( $i = max( 1, $page - 3 ); $i <= min( $pages, $page + 3 ); $i++ ) {
			printf( '<a href="%s" class="%s">%d</a>', esc_url( self::url( $section, array_merge( $args, array( 'pg' => $i ) ) ) ), $i === $page ? 'is-active' : '', (int) $i );
		}
		echo '<span class="ft-muted">' . esc_html( sprintf( /* translators: %d: total */ __( '%d total', 'fraud-tenant-core' ), $total ) ) . '</span></nav>';
	}

	/**
	 * Filter pills.
	 *
	 * @param string $section Section.
	 * @param string $param   Query parameter.
	 * @param array  $options value => label (label may include count).
	 * @param string $current Current value.
	 * @param array  $keep    Args to keep.
	 */
	public static function pills( string $section, string $param, array $options, string $current, array $keep = array() ): void {
		echo '<div class="ft-tabs" role="tablist">';
		foreach ( $options as $value => $label ) {
			printf( '<a href="%s" class="ft-tab %s" role="tab" aria-selected="%s">%s</a>', esc_url( self::url( $section, array_merge( $keep, array( $param => $value ) ) ) ), (string) $value === $current ? 'is-active' : '', (string) $value === $current ? 'true' : 'false', esc_html( $label ) );
		}
		echo '</div>';
	}

	/**
	 * Current GET value.
	 *
	 * @param string $k   Key.
	 * @param string $def Default.
	 * @return string
	 */
	public static function get( string $k, string $def = '' ): string {
		return isset( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( (string) $_GET[ $k ] ) ) : $def;
	}

	/**
	 * Section header.
	 *
	 * @param string $title    Title.
	 * @param string $subtitle Subtitle.
	 */
	public static function head( string $title, string $subtitle = '' ): void {
		echo '<header class="ft-page-head"><div><h1>' . esc_html( $title ) . '</h1>';
		if ( $subtitle ) {
			echo '<p>' . esc_html( $subtitle ) . '</p>';
		}
		echo '</div></header>';
	}

	/**
	 * Empty state.
	 *
	 * @param string $message Message.
	 */
	public static function empty_state( string $message ): void {
		echo '<div class="ft-empty">' . esc_html( $message ) . '</div>';
	}

	/**
	 * Secure document link (nonce-bound, streamed by the authorized controller).
	 *
	 * @param int    $doc_id Document.
	 * @param string $label  Label.
	 * @return string HTML.
	 */
	public static function doc_link( int $doc_id, string $label ): string {
		$url = add_query_arg( array( 'action' => 'ft_view_document', 'document_id' => $doc_id, '_wpnonce' => wp_create_nonce( 'ft_view_doc_' . $doc_id ) ), admin_url( 'admin-post.php' ) );
		return '<a class="ft-btn ft-btn-secondary ft-btn-sm" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
	}
}
