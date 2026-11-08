<?php
/**
 * Notifications & Email Configuration — mail transport status, test email, notification volume,
 * and the administrator's own notification inbox.
 *
 * @package FraudTenant\Admin
 */

defined( 'ABSPATH' ) || exit;

use FraudTenant\Admin\AdminConsole as C;
use FraudTenant\Services\NotificationService;

global $wpdb;
$p        = $wpdb->prefix;
$uid      = get_current_user_id();
$fluent   = function_exists( 'is_plugin_active' ) ? is_plugin_active( 'fluent-smtp/fluent-smtp.php' ) : defined( 'FLUENTMAIL' );
$qa_mail  = defined( 'FT_QA_INSTANCE' ) && defined( 'FT_QA_MAIL_DIR' );
$by_type  = $wpdb->get_results( "SELECT type, COUNT(*) AS n FROM {$p}ft_notifications WHERE created_at > UTC_TIMESTAMP() - INTERVAL 30 DAY GROUP BY type ORDER BY n DESC" ) ?: array();
$mine     = NotificationService::get_for_user( $uid, 20 );

C::head( __( 'Notifications & Email', 'fraud-tenant-core' ), __( 'All platform messages go through one notification service (in-app + email via the WordPress mail transport).', 'fraud-tenant-core' ) );
?>
<div class="ft-grid ft-grid-2">
	<section class="ft-card">
		<div class="ft-card-head"><h2><?php esc_html_e( 'Mail transport', 'fraud-tenant-core' ); ?></h2></div>
		<dl class="ft-dl">
			<dt><?php esc_html_e( 'Transport', 'fraud-tenant-core' ); ?></dt>
			<dd><?php echo $qa_mail ? C::badge( 'info', __( 'QA mail capture (no real delivery)', 'fraud-tenant-core' ) ) : ( $fluent ? C::badge( 'active', __( 'FluentSMTP (configured SMTP provider)', 'fraud-tenant-core' ) ) : C::badge( 'pending', __( 'PHP mail() — configure an SMTP provider', 'fraud-tenant-core' ) ) ); // phpcs:ignore ?></dd>
			<dt><?php esc_html_e( 'From address', 'fraud-tenant-core' ); ?></dt><dd><?php echo esc_html( get_option( 'admin_email' ) ); ?></dd>
		</dl>
		<p class="ft-muted"><?php esc_html_e( 'SMTP credentials are managed in the mail plugin settings (WordPress admin). Delivery to real inboxes must be confirmed with a test message.', 'fraud-tenant-core' ); ?></p>
		<?php C::form( 'test_email' ); ?><button class="ft-btn ft-btn-secondary" type="submit"><?php esc_html_e( 'Send me a test email', 'fraud-tenant-core' ); ?></button></form>
		<?php if ( $fluent && current_user_can( 'manage_options' ) ) : ?><p><a href="<?php echo esc_url( admin_url( 'options-general.php?page=fluent-mail' ) ); ?>"><?php esc_html_e( 'Open SMTP settings', 'fraud-tenant-core' ); ?> →</a></p><?php endif; ?>
	</section>
	<section class="ft-card">
		<div class="ft-card-head"><h2><?php esc_html_e( 'Messages in the last 30 days', 'fraud-tenant-core' ); ?></h2></div>
		<?php if ( ! $by_type ) : C::empty_state( __( 'No notifications sent in the last 30 days.', 'fraud-tenant-core' ) ); else : ?>
			<ul class="ft-list"><?php foreach ( $by_type as $t ) : ?><li class="ft-mono"><?php echo esc_html( (string) $t->type ); ?> <strong><?php echo (int) $t->n; ?></strong></li><?php endforeach; ?></ul>
		<?php endif; ?>
		<p class="ft-help"><?php esc_html_e( 'Users choose which optional categories they receive by email in their account settings; account and security messages are always sent.', 'fraud-tenant-core' ); ?></p>
	</section>
</div>
<section class="ft-card">
	<div class="ft-card-head"><h2><?php esc_html_e( 'My notifications', 'fraud-tenant-core' ); ?></h2></div>
	<?php if ( ! $mine ) : C::empty_state( __( 'No notifications.', 'fraud-tenant-core' ) ); else : ?>
		<ul class="ft-list ft-notifs"><?php foreach ( $mine as $n ) : ?>
			<li class="<?php echo $n->is_read ? '' : 'is-unread'; ?>"><strong><?php echo esc_html( (string) $n->title ); ?></strong> <span class="ft-muted"><?php echo C::date( $n->created_at, true ); // phpcs:ignore ?></span><br><?php echo esc_html( (string) $n->message ); ?><?php if ( $n->action_url ) : ?> <a href="<?php echo esc_url( (string) $n->action_url ); ?>"><?php esc_html_e( 'Open', 'fraud-tenant-core' ); ?></a><?php endif; ?></li>
		<?php endforeach; ?></ul>
	<?php endif; ?>
</section>
