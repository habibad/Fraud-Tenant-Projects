<?php
/**
 * Owner / Tenant — Notifications (the user's own in-app notifications only).
 *
 * @package FraudTenantTheme
 */

defined( 'ABSPATH' ) || exit;

use FraudTenant\Services\NotificationService;

$uid   = (int) $args['user']->ID;
$base  = $args['base'];
$items = NotificationService::get_for_user( $uid, 50 );
$here  = ft_app_url( $base, 'notifications' );
?>
<header class="ft-page-head">
	<div><h1><?php esc_html_e( 'Notifications', 'fraud-tenant-theme' ); ?></h1><p><?php esc_html_e( 'Updates about your account, reports, disputes and billing.', 'fraud-tenant-theme' ); ?></p></div>
	<?php if ( NotificationService::unread_count( $uid ) ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php ft_form_fields( 'ft_notifications_read', $here ); ?>
			<?php wp_nonce_field( 'ft_notifications_read_' . $uid ); ?>
			<button class="ft-btn ft-btn-secondary" type="submit"><?php esc_html_e( 'Mark all as read', 'fraud-tenant-theme' ); ?></button>
		</form>
	<?php endif; ?>
</header>
<section class="ft-card">
	<?php if ( ! $items ) : ft_empty( __( 'You have no notifications.', 'fraud-tenant-theme' ) ); else : ?>
		<ul class="ft-list ft-notifs">
			<?php foreach ( $items as $n ) : ?>
				<li class="<?php echo $n->is_read ? '' : 'is-unread'; ?>">
					<strong><?php echo esc_html( (string) $n->title ); ?></strong> <span class="ft-muted"><?php echo ft_date( $n->created_at, true ); // phpcs:ignore ?></span><br>
					<span><?php echo esc_html( (string) $n->message ); ?></span>
					<?php if ( $n->action_url && 0 === strpos( (string) $n->action_url, home_url() ) ) : ?> <a href="<?php echo esc_url( (string) $n->action_url ); ?>"><?php esc_html_e( 'Open', 'fraud-tenant-theme' ); ?></a><?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>
