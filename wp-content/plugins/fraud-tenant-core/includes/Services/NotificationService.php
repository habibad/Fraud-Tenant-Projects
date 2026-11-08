<?php
/**
 * Single notification service: in-app notifications (wp_ft_notifications) plus optional email
 * through the WordPress mail abstraction (wp_mail → configured SMTP provider).
 *
 * Privacy: messages must never contain SSNs, tenant names in screening contexts, evidence content
 * or internal notes. Emails carry a short summary and a link to the authenticated dashboard.
 *
 * Email preferences: users can opt out of non-essential categories. Security/account messages
 * (verification codes, password and email changes, identity decisions) are always delivered.
 *
 * @package FraudTenant\Services
 */

namespace FraudTenant\Services;

use FraudTenant\Utils\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NotificationService {

	public const PREF_META = '_ft_email_prefs';

	/** Email categories users may turn off (account is always on). */
	public const OPTIONAL_CATEGORIES = array(
		'reports'   => 'Incident report and rental record updates',
		'disputes'  => 'Dispute updates',
		'billing'   => 'Membership and billing',
		'screening' => 'Screening inquiries that included my records',
	);

	/**
	 * Register the "mark as read" handler (members and administrators).
	 */
	public static function register(): void {
		add_action( 'admin_post_ft_notifications_read', array( __CLASS__, 'handle_mark_read' ) );
	}

	/**
	 * Mark one notification (owned by the current user) or all as read.
	 */
	public static function handle_mark_read(): void {
		$uid = get_current_user_id();
		if ( ! $uid || ! check_admin_referer( 'ft_notifications_read_' . $uid ) ) {
			wp_die( esc_html__( 'Security check failed.', 'fraud-tenant-core' ), 403 );
		}
		$id = isset( $_POST['notification_id'] ) ? absint( $_POST['notification_id'] ) : 0;
		self::mark_read( $uid, $id ?: null );
		$back = isset( $_POST['redirect_to'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( (string) $_POST['redirect_to'] ) ), home_url( '/' ) ) : home_url( '/' );
		wp_safe_redirect( $back );
		exit;
	}

	/**
	 * Create an in-app notification and (optionally) email the user.
	 *
	 * @param int    $user_id  Recipient.
	 * @param string $title    Short title.
	 * @param string $message  Message (no sensitive data).
	 * @param string $url      Dashboard link.
	 * @param string $type     Notification type tag.
	 * @param string $category Email category (account|reports|disputes|billing|screening).
	 * @param bool   $email    Also send an email.
	 * @return bool
	 */
	public static function notify( int $user_id, string $title, string $message, string $url = '', string $type = 'info', string $category = 'account', bool $email = true ): bool {
		global $wpdb;
		if ( $user_id <= 0 || ! get_userdata( $user_id ) ) {
			return false;
		}
		$ok = false !== $wpdb->insert(
			$wpdb->prefix . Constants::TABLE_NOTIFICATIONS,
			array(
				'user_id'    => $user_id,
				'title'      => sanitize_text_field( $title ),
				'message'    => sanitize_textarea_field( $message ),
				'action_url' => $url ? esc_url_raw( $url ) : null,
				'type'       => sanitize_key( $type ),
				'is_read'    => 0,
				'created_at' => current_time( 'mysql', true ),
			)
		);
		if ( $email && self::wants_email( $user_id, $category ) ) {
			self::send_email( $user_id, $title, $message, $url );
		}
		return $ok;
	}

	/**
	 * In-app notification to every administrator (no email, to avoid inbox noise).
	 *
	 * @param string $title   Title.
	 * @param string $message Message.
	 * @param string $url     Admin link.
	 * @param string $type    Type tag.
	 */
	public static function notify_admins( string $title, string $message, string $url = '', string $type = 'admin' ): void {
		foreach ( get_users( array( 'role' => 'administrator', 'fields' => 'ID' ) ) as $admin_id ) {
			self::notify( (int) $admin_id, $title, $message, $url, $type, 'account', false );
		}
	}

	/**
	 * Send a plain-text email (summary + dashboard link).
	 *
	 * @param int    $user_id Recipient.
	 * @param string $title   Subject (prefixed).
	 * @param string $message Body.
	 * @param string $url     Link.
	 * @return bool
	 */
	public static function send_email( int $user_id, string $title, string $message, string $url = '' ): bool {
		$user = get_userdata( $user_id );
		if ( ! $user || ! is_email( $user->user_email ) ) {
			return false;
		}
		$name = $user->first_name ? $user->first_name : $user->display_name;
		/* translators: %s: recipient first name */
		$body  = sprintf( __( 'Hello %s,', 'fraud-tenant-core' ), $name ) . "\n\n" . $message . "\n\n";
		if ( $url ) {
			$body .= __( 'Sign in to view the details:', 'fraud-tenant-core' ) . "\n" . $url . "\n\n";
		}
		$body .= "---\n" . __( 'Fraud Tenant — A Safer Rental Community. You can change email preferences in Profile & Account Settings.', 'fraud-tenant-core' ) . "\n";
		return (bool) wp_mail( $user->user_email, '[Fraud Tenant] ' . $title, $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );
	}

	/**
	 * Email preference for a category.
	 *
	 * @param int    $user_id  User.
	 * @param string $category Category.
	 * @return bool
	 */
	public static function wants_email( int $user_id, string $category ): bool {
		if ( ! isset( self::OPTIONAL_CATEGORIES[ $category ] ) ) {
			return true; // Account/security messages are always sent.
		}
		$prefs = self::get_prefs( $user_id );
		return ! empty( $prefs[ $category ] );
	}

	/**
	 * Preferences (all optional categories default to on).
	 *
	 * @param int $user_id User.
	 * @return array<string,bool>
	 */
	public static function get_prefs( int $user_id ): array {
		$stored = get_user_meta( $user_id, self::PREF_META, true );
		$stored = is_array( $stored ) ? $stored : array();
		$out    = array();
		foreach ( array_keys( self::OPTIONAL_CATEGORIES ) as $k ) {
			$out[ $k ] = array_key_exists( $k, $stored ) ? (bool) $stored[ $k ] : true;
		}
		return $out;
	}

	/**
	 * Save preferences from submitted checkboxes.
	 *
	 * @param int   $user_id User.
	 * @param array $input   Submitted values (key => truthy).
	 * @return array<string,bool>
	 */
	public static function save_prefs( int $user_id, array $input ): array {
		$prefs = array();
		foreach ( array_keys( self::OPTIONAL_CATEGORIES ) as $k ) {
			$prefs[ $k ] = ! empty( $input[ $k ] );
		}
		update_user_meta( $user_id, self::PREF_META, $prefs );
		AuditLogger::log( 'user.notification_preferences_updated', 'user', $user_id, array( 'prefs' => $prefs ), $user_id );
		return $prefs;
	}

	/**
	 * Notifications for a user (most recent first).
	 *
	 * @param int $user_id User.
	 * @param int $limit   Limit.
	 * @param int $offset  Offset.
	 * @return array
	 */
	public static function get_for_user( int $user_id, int $limit = 20, int $offset = 0 ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( "SELECT id, title, message, action_url, type, is_read, created_at FROM {$wpdb->prefix}" . Constants::TABLE_NOTIFICATIONS . ' WHERE user_id = %d ORDER BY id DESC LIMIT %d OFFSET %d', $user_id, max( 1, $limit ), max( 0, $offset ) ) ) ?: array();
	}

	/**
	 * Unread count.
	 *
	 * @param int $user_id User.
	 * @return int
	 */
	public static function unread_count( int $user_id ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}" . Constants::TABLE_NOTIFICATIONS . ' WHERE user_id = %d AND is_read = 0', $user_id ) );
	}

	/**
	 * Mark one (owned) notification, or all, as read.
	 *
	 * @param int      $user_id User (ownership enforced).
	 * @param int|null $id      Notification ID, or null for all.
	 * @return int Rows updated.
	 */
	public static function mark_read( int $user_id, ?int $id = null ): int {
		global $wpdb;
		$table = $wpdb->prefix . Constants::TABLE_NOTIFICATIONS;
		$now   = current_time( 'mysql', true );
		if ( null === $id ) {
			return (int) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET is_read = 1, read_at = %s WHERE user_id = %d AND is_read = 0", $now, $user_id ) );
		}
		return (int) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET is_read = 1, read_at = %s WHERE id = %d AND user_id = %d", $now, $id, $user_id ) );
	}
}
