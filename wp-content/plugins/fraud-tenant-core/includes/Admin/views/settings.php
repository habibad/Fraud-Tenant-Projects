<?php
/**
 * Platform Settings — platform configuration overview and links to the settings that own each value.
 *
 * @package FraudTenant\Admin
 */

defined( 'ABSPATH' ) || exit;

use FraudTenant\Admin\AdminConsole as C;
use FraudTenant\Services\IncidentService;
use FraudTenant\Services\ScreeningService;
use FraudTenant\Services\StripeCredentialService;
use FraudTenant\Services\TenantScreeningService;
use FraudTenant\Utils\Constants;

C::head( __( 'Platform Settings', 'fraud-tenant-core' ), __( 'How the platform is configured. Values that hold secrets are configured outside the database and are never displayed.', 'fraud-tenant-core' ) );
?>
<div class="ft-grid ft-grid-2">
	<section class="ft-card">
		<div class="ft-card-head"><h2><?php esc_html_e( 'Platform', 'fraud-tenant-core' ); ?></h2></div>
		<dl class="ft-dl">
			<dt><?php esc_html_e( 'Site', 'fraud-tenant-core' ); ?></dt><dd><?php echo esc_html( get_bloginfo( 'name' ) ); ?> · <?php echo esc_html( home_url() ); ?></dd>
			<dt><?php esc_html_e( 'Environment', 'fraud-tenant-core' ); ?></dt><dd><?php echo esc_html( defined( 'WP_ENVIRONMENT_TYPE' ) ? (string) WP_ENVIRONMENT_TYPE : 'production' ); ?></dd>
			<dt><?php esc_html_e( 'Database schema', 'fraud-tenant-core' ); ?></dt><dd><?php echo esc_html( (string) get_option( Constants::DB_VERSION_OPTION ) ); ?> (<?php esc_html_e( 'code', 'fraud-tenant-core' ); ?> <?php echo esc_html( Constants::DB_VERSION ); ?>)</dd>
			<dt><?php esc_html_e( 'Stripe mode', 'fraud-tenant-core' ); ?></dt><dd><?php echo esc_html( ucfirst( StripeCredentialService::get_active_mode() ) ); ?> — <a href="<?php echo esc_url( C::url( 'stripe' ) ); ?>"><?php esc_html_e( 'Stripe Settings', 'fraud-tenant-core' ); ?></a></dd>
			<dt><?php esc_html_e( 'Membership plans', 'fraud-tenant-core' ); ?></dt><dd><a href="<?php echo esc_url( C::url( 'plans' ) ); ?>"><?php esc_html_e( 'Free (1 property, limited screening) · Standard monthly $29 · Standard yearly $290', 'fraud-tenant-core' ); ?></a></dd>
		</dl>
	</section>
	<section class="ft-card">
		<div class="ft-card-head"><h2><?php esc_html_e( 'Business rules (enforced server-side)', 'fraud-tenant-core' ); ?></h2></div>
		<ul class="ft-list">
			<li><?php esc_html_e( 'Email verification activates owner and tenant accounts; there is no separate approval step.', 'fraud-tenant-core' ); ?></li>
			<li><?php esc_html_e( 'Property ownership verification is required for screening and incident reports on that property.', 'fraud-tenant-core' ); ?></li>
			<li><?php esc_html_e( 'Incident reporting and full screening details require an active Standard membership.', 'fraud-tenant-core' ); ?></li>
			<li><?php echo esc_html( sprintf( /* translators: 1: per window, 2: per day */ __( 'Screening limits per owner: %1$d searches per 5 minutes, %2$d per day.', 'fraud-tenant-core' ), ScreeningService::MAX_SCREENINGS_PER_WINDOW, TenantScreeningService::MAX_SEARCHES_PER_DAY ) ); ?></li>
			<li><?php echo esc_html( sprintf( /* translators: %d: count */ __( 'Incident reports per owner: %d per 5 minutes.', 'fraud-tenant-core' ), IncidentService::MAX_INCIDENTS_PER_WINDOW ) ); ?></li>
			<li><?php esc_html_e( 'Reports are allegations pending review. Disclosure to the tenant and to screening owners are separate, explicit administrator decisions.', 'fraud-tenant-core' ); ?></li>
			<li><?php esc_html_e( 'No scores, rankings, blacklists or automated rental decisions. Owners decide independently.', 'fraud-tenant-core' ); ?></li>
		</ul>
	</section>
</div>
<?php if ( current_user_can( 'manage_options' ) ) : ?>
<section class="ft-card">
	<div class="ft-card-head"><h2><?php esc_html_e( 'WordPress administration', 'fraud-tenant-core' ); ?></h2></div>
	<p><a href="<?php echo esc_url( admin_url( 'options-general.php' ) ); ?>"><?php esc_html_e( 'General settings', 'fraud-tenant-core' ); ?></a> · <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=page' ) ); ?>"><?php esc_html_e( 'Pages & legal content', 'fraud-tenant-core' ); ?></a> · <a href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>"><?php esc_html_e( 'WordPress users', 'fraud-tenant-core' ); ?></a> · <a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>"><?php esc_html_e( 'Plugins', 'fraud-tenant-core' ); ?></a></p>
</section>
<?php endif; ?>
