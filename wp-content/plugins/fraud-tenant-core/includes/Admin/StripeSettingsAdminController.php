<?php
/**
 * Administrator Stripe Settings Controller.
 *
 * Provides administrative screens to inspect, configure, and manage encrypted
 * Stripe API credentials for independent Test (Sandbox) and Live (Production) modes,
 * test server-side Stripe connectivity, review membership plan Stripe mappings,
 * and enforce environment security policies.
 *
 * @package FraudTenant\Admin
 */

namespace FraudTenant\Admin;

use FraudTenant\Auth\Capabilities;
use FraudTenant\Services\MembershipService;
use FraudTenant\Services\StripeCredentialService;
use FraudTenant\Services\StripeService;
use FraudTenant\Utils\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class StripeSettingsAdminController {

	/**
	 * Main controller entry point for admin menu.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( Capabilities::MANAGE_PLATFORM_SETTINGS ) ) {
			wp_die( esc_html__( 'Unauthorized access. You do not have permission to manage platform settings.', 'fraud-tenant-core' ), 403 );
		}

		self::render_stripe_settings();
	}

	/**
	 * Render flash notices stored in transient for the current admin user.
	 */
	public static function display_flash_notices(): void {
		$admin_id = get_current_user_id();
		if ( $admin_id <= 0 ) {
			return;
		}

		$notice = get_transient( 'ft_stripe_admin_notice_' . $admin_id );
		if ( $notice && is_array( $notice ) ) {
			delete_transient( 'ft_stripe_admin_notice_' . $admin_id );
			$class   = 'success' === ( $notice['type'] ?? '' ) ? 'notice-success' : 'notice-error';
			$message = $notice['message'] ?? '';
			?>
			<div class="notice <?php echo esc_attr( $class ); ?> is-dismissible" style="margin: 16px 0; border-radius: 6px;">
				<p><strong><?php echo esc_html( $message ); ?></strong></p>
			</div>
			<?php
		}
	}

	/**
	 * Set transient flash notice for current admin user.
	 *
	 * @param string $type    'success' or 'error'.
	 * @param string $message Notice message.
	 */
	public static function set_flash_notice( string $type, string $message ): void {
		$admin_id = get_current_user_id();
		if ( $admin_id > 0 ) {
			set_transient(
				'ft_stripe_admin_notice_' . $admin_id,
				array(
					'type'    => $type,
					'message' => $message,
				),
				30
			);
		}
	}

	/**
	 * Render the main Stripe Settings screen.
	 */
	public static function render_stripe_settings(): void {
		$dual_status = StripeCredentialService::get_full_dual_status();
		$active_mode = $dual_status['active_mode'];
		$plans       = MembershipService::get_plans( false );
		$is_prod     = $dual_status['is_production'];
		$is_qa_prod  = $dual_status['qa_restricted'];
		$env         = $dual_status['environment'];
		$is_ssl      = $dual_status['ssl_active'];

		$test_status = $dual_status['test'];
		$live_status = $dual_status['live'];
		?>
		<div class="wrap ft-admin-wrap" style="max-width: 1280px; margin: 20px auto 40px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;">

			<!-- Header -->
			<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
				<div>
					<h1 class="wp-heading-inline" style="color: #14263E; font-size: 24px; font-weight: 700; margin: 0 0 6px;">
						<span class="dashicons dashicons-shield" style="font-size: 28px; width: 28px; height: 28px; vertical-align: middle; color: #0E9488; margin-right: 6px;"></span>
						<?php esc_html_e( 'Stripe Gateway & Environment Settings', 'fraud-tenant-core' ); ?>
					</h1>
					<p class="description" style="color: #64748B; font-size: 14px; margin: 0;">
						<?php esc_html_e( 'Manage independent Test (Sandbox) and Live (Production) credentials, webhook verification, and plan price mappings.', 'fraud-tenant-core' ); ?>
					</p>
				</div>
				<div style="display: flex; align-items: center; gap: 10px;">
					<?php if ( Constants::STRIPE_MODE_LIVE === $active_mode ) : ?>
						<span style="background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; padding: 6px 14px; border-radius: 9999px; font-size: 13px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
							<span style="width: 10px; height: 10px; background: #059669; border-radius: 50%;"></span>
							<?php esc_html_e( 'LIVE MODE ACTIVE (PRODUCTION)', 'fraud-tenant-core' ); ?>
						</span>
					<?php else : ?>
						<span style="background: #FFFBEB; color: #D97706; border: 1px solid #FDE68A; padding: 6px 14px; border-radius: 9999px; font-size: 13px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
							<span style="width: 10px; height: 10px; background: #D97706; border-radius: 50%;"></span>
							<?php esc_html_e( 'TEST MODE ACTIVE (SANDBOX)', 'fraud-tenant-core' ); ?>
						</span>
					<?php endif; ?>
				</div>
			</div>


			<?php self::display_flash_notices(); ?>

			<?php if ( $is_qa_prod ) : ?>
				<div style="background: #FEF3C7; border: 1px solid #F59E0B; border-radius: 8px; padding: 14px 18px; margin-bottom: 24px; color: #92400E; font-size: 14px; display: flex; align-items: center; gap: 12px;">
					<span class="dashicons dashicons-warning" style="font-size: 24px; width: 24px; height: 24px; color: #D97706;"></span>
					<div>
						<strong><?php esc_html_e( 'Notice: Test Mode is Active in Production Environment.', 'fraud-tenant-core' ); ?></strong>
						<span style="display: block; font-size: 13px; margin-top: 2px;">
							<?php esc_html_e( 'Subscription checkout is strictly restricted to administrator QA accounts. Regular property owners will be informed that billing is in QA test mode.', 'fraud-tenant-core' ); ?>
						</span>
					</div>
				</div>
			<?php endif; ?>

			<!-- Top Row: Mode Switcher Card -->
			<div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 8px; padding: 20px 24px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
				<div>
					<h2 style="font-size: 16px; font-weight: 700; color: #14263E; margin: 0 0 4px;">
						<?php esc_html_e( 'Operational Stripe Mode Selector', 'fraud-tenant-core' ); ?>
					</h2>
					<p style="color: #64748B; font-size: 13px; margin: 0;">
						<?php esc_html_e( 'Switch between Sandbox Test Mode and Production Live Mode. Customers, subscriptions, and webhooks remain strictly isolated.', 'fraud-tenant-core' ); ?>
					</p>
				</div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: flex; align-items: center; gap: 12px;"><?php AdminConsole::return_field(); ?>
					<input type="hidden" name="action" value="ft_switch_stripe_mode" />
					<?php wp_nonce_field( 'ft_switch_stripe_mode_action', 'ft_mode_nonce' ); ?>

					<label style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">
						<input type="radio" name="stripe_mode" value="test" <?php checked( $active_mode, Constants::STRIPE_MODE_TEST ); ?> />
						<span style="color: #D97706;"><?php esc_html_e( 'Test Mode (Sandbox)', 'fraud-tenant-core' ); ?></span>
					</label>

					<label style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; cursor: pointer; margin-left: 8px;">
						<input type="radio" name="stripe_mode" value="live" <?php checked( $active_mode, Constants::STRIPE_MODE_LIVE ); ?> />
						<span style="color: #059669;"><?php esc_html_e( 'Live Mode (Production)', 'fraud-tenant-core' ); ?></span>
					</label>

					<button type="submit" class="button button-primary" style="background: #14263E; border-color: #14263E; margin-left: 8px;">
						<?php esc_html_e( 'Apply Operational Mode', 'fraud-tenant-core' ); ?>
					</button>
				</form>
			</div>

			<!-- Dual Columns: Test Credentials vs. Live Credentials -->
			<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">

				<!-- CARD 1: TEST MODE (SANDBOX) CREDENTIALS -->
				<div style="background: #FFFFFF; border: 2px solid <?php echo Constants::STRIPE_MODE_TEST === $active_mode ? '#F59E0B' : '#E2E8F0'; ?>; border-radius: 8px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); position: relative;">
					<?php if ( Constants::STRIPE_MODE_TEST === $active_mode ) : ?>
						<span style="position: absolute; top: 12px; right: 12px; background: #FFFBEB; color: #D97706; border: 1px solid #FDE68A; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px;">
							<?php esc_html_e( 'CURRENT ACTIVE GATEWAY', 'fraud-tenant-core' ); ?>
						</span>
					<?php endif; ?>

					<h2 style="font-size: 17px; font-weight: 700; color: #14263E; margin: 0 0 6px;">
						<?php esc_html_e( 'Test Mode Credentials (Sandbox)', 'fraud-tenant-core' ); ?>
					</h2>
					<p style="color: #64748B; font-size: 12px; margin: 0 0 18px;">
						<?php esc_html_e( 'Uses Stripe test keys (sk_test_ / pk_test_). Safe for QA, local development, and end-to-end testing.', 'fraud-tenant-core' ); ?>
					</p>

					<!-- Status indicator -->
					<div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 6px; padding: 12px; margin-bottom: 18px; font-size: 12px;">
						<div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
							<span><?php esc_html_e( 'Status:', 'fraud-tenant-core' ); ?></span>
							<strong><?php echo $test_status['is_configured'] ? '<span style="color:#059669;">' . esc_html__( 'Configured & Encrypted', 'fraud-tenant-core' ) . '</span>' : '<span style="color:#D97706;">' . esc_html__( 'Not Configured', 'fraud-tenant-core' ) . '</span>'; ?></strong>
						</div>
						<?php if ( ! empty( $test_status['masked_secret'] ) ) : ?>
							<div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
								<span><?php esc_html_e( 'Secret Key:', 'fraud-tenant-core' ); ?></span>
								<code style="font-size: 11px;"><?php echo esc_html( $test_status['masked_secret'] ); ?></code>
							</div>
						<?php endif; ?>
						<?php if ( ! empty( $test_status['masked_publishable'] ) ) : ?>
							<div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
								<span><?php esc_html_e( 'Publishable Key:', 'fraud-tenant-core' ); ?></span>
								<code style="font-size: 11px;"><?php echo esc_html( $test_status['masked_publishable'] ); ?></code>
							</div>
						<?php endif; ?>
						<?php if ( ! empty( $test_status['masked_webhook'] ) ) : ?>
							<div style="display: flex; justify-content: space-between;">
								<span><?php esc_html_e( 'Webhook Secret:', 'fraud-tenant-core' ); ?></span>
								<code style="font-size: 11px;"><?php echo esc_html( $test_status['masked_webhook'] ); ?></code>
							</div>
						<?php endif; ?>
					</div>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php AdminConsole::return_field(); ?>
						<input type="hidden" name="action" value="ft_save_stripe_settings" />
						<input type="hidden" name="stripe_mode" value="test" />
						<?php wp_nonce_field( 'ft_save_stripe_settings', 'ft_stripe_nonce' ); ?>

						<div style="margin-bottom: 14px;">
							<label for="test_secret_key" style="display: block; font-size: 12px; font-weight: 600; color: #14263E; margin-bottom: 4px;">
								<?php esc_html_e( 'Test Secret Key (sk_test_... or rk_test_...)', 'fraud-tenant-core' ); ?>
							</label>
							<input type="password" id="test_secret_key" name="stripe_secret_key" placeholder="<?php echo $test_status['has_secret_key'] ? '••••••••••••••••••••••••••••••••' : 'sk_test_...'; ?>" autocomplete="new-password" style="width: 100%; border: 1px solid #CBD5E1; border-radius: 6px; padding: 7px 10px; font-family: monospace; font-size: 12px;" />
							<span style="font-size: 11px; color: #64748B;"><?php esc_html_e( 'Leave blank to preserve existing saved key.', 'fraud-tenant-core' ); ?></span>
						</div>

						<div style="margin-bottom: 14px;">
							<label for="test_publishable_key" style="display: block; font-size: 12px; font-weight: 600; color: #14263E; margin-bottom: 4px;">
								<?php esc_html_e( 'Test Publishable Key (pk_test_...)', 'fraud-tenant-core' ); ?>
							</label>
							<input type="text" id="test_publishable_key" name="stripe_publishable_key" placeholder="<?php echo $test_status['has_publishable_key'] ? '••••••••••••••••••••••••••••••••' : 'pk_test_...'; ?>" autocomplete="off" style="width: 100%; border: 1px solid #CBD5E1; border-radius: 6px; padding: 7px 10px; font-family: monospace; font-size: 12px;" />
						</div>

						<div style="margin-bottom: 18px;">
							<label for="test_webhook_secret" style="display: block; font-size: 12px; font-weight: 600; color: #14263E; margin-bottom: 4px;">
								<?php esc_html_e( 'Test Webhook Secret (whsec_...)', 'fraud-tenant-core' ); ?>
							</label>
							<input type="password" id="test_webhook_secret" name="stripe_webhook_secret" placeholder="<?php echo $test_status['has_webhook_secret'] ? '••••••••••••••••••••••••••••••••' : 'whsec_...'; ?>" autocomplete="new-password" style="width: 100%; border: 1px solid #CBD5E1; border-radius: 6px; padding: 7px 10px; font-family: monospace; font-size: 12px;" />
						</div>

						<div style="display: flex; gap: 8px; flex-wrap: wrap;">
							<button type="submit" class="button button-primary" style="background: #0E9488; border-color: #0E9488;">
								<?php esc_html_e( 'Save Test Credentials', 'fraud-tenant-core' ); ?>
							</button>
						</div>
					</form>

					<div style="margin-top: 14px; pt-3; border-top: 1px solid #F1F5F9; display: flex; gap: 8px; justify-content: space-between;">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;"><?php AdminConsole::return_field(); ?>
							<input type="hidden" name="action" value="ft_test_stripe_connection" />
							<input type="hidden" name="stripe_mode" value="test" />
							<?php wp_nonce_field( 'ft_test_stripe_connection', 'ft_stripe_test_nonce' ); ?>
							<button type="submit" class="button button-secondary" style="font-size: 12px;">
								<span class="dashicons dashicons-yes-alt" style="font-size: 14px; width: 14px; height: 14px; vertical-align: text-top; color: #0E9488;"></span>
								<?php esc_html_e( 'Test Connection (Test Mode)', 'fraud-tenant-core' ); ?>
							</button>
						</form>

						<?php if ( $test_status['is_configured'] ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Are you sure you want to remove Test Mode Stripe credentials?', 'fraud-tenant-core' ) ); ?><?php AdminConsole::return_field(); ?>');" style="display: inline;">
								<input type="hidden" name="action" value="ft_remove_stripe_settings" />
								<input type="hidden" name="stripe_mode" value="test" />
								<?php wp_nonce_field( 'ft_remove_stripe_settings', 'ft_stripe_remove_nonce' ); ?>
								<button type="submit" class="button" style="color: #DC2626; font-size: 12px;">
									<?php esc_html_e( 'Remove Test Keys', 'fraud-tenant-core' ); ?>
								</button>
							</form>
						<?php endif; ?>
					</div>
				</div>

				<!-- CARD 2: LIVE MODE (PRODUCTION) CREDENTIALS -->
				<div style="background: #FFFFFF; border: 2px solid <?php echo Constants::STRIPE_MODE_LIVE === $active_mode ? '#059669' : '#E2E8F0'; ?>; border-radius: 8px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); position: relative;">
					<?php if ( Constants::STRIPE_MODE_LIVE === $active_mode ) : ?>
						<span style="position: absolute; top: 12px; right: 12px; background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px;">
							<?php esc_html_e( 'CURRENT ACTIVE GATEWAY', 'fraud-tenant-core' ); ?>
						</span>
					<?php endif; ?>

					<h2 style="font-size: 17px; font-weight: 700; color: #14263E; margin: 0 0 6px;">
						<?php esc_html_e( 'Live Mode Credentials (Production)', 'fraud-tenant-core' ); ?>
					</h2>
					<p style="color: #64748B; font-size: 12px; margin: 0 0 18px;">
						<?php esc_html_e( 'Uses live Stripe keys (sk_live_ / pk_live_). Genuine card payments processed through Stripe Checkout.', 'fraud-tenant-core' ); ?>
					</p>

					<!-- Status indicator -->
					<div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 6px; padding: 12px; margin-bottom: 18px; font-size: 12px;">
						<div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
							<span><?php esc_html_e( 'Status:', 'fraud-tenant-core' ); ?></span>
							<strong><?php echo $live_status['is_configured'] ? '<span style="color:#059669;">' . esc_html__( 'Configured & Encrypted', 'fraud-tenant-core' ) . '</span>' : '<span style="color:#D97706;">' . esc_html__( 'Not Configured', 'fraud-tenant-core' ) . '</span>'; ?></strong>
						</div>
						<?php if ( ! empty( $live_status['masked_secret'] ) ) : ?>
							<div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
								<span><?php esc_html_e( 'Secret Key:', 'fraud-tenant-core' ); ?></span>
								<code style="font-size: 11px;"><?php echo esc_html( $live_status['masked_secret'] ); ?></code>
							</div>
						<?php endif; ?>
						<?php if ( ! empty( $live_status['masked_publishable'] ) ) : ?>
							<div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
								<span><?php esc_html_e( 'Publishable Key:', 'fraud-tenant-core' ); ?></span>
								<code style="font-size: 11px;"><?php echo esc_html( $live_status['masked_publishable'] ); ?></code>
							</div>
						<?php endif; ?>
						<?php if ( ! empty( $live_status['masked_webhook'] ) ) : ?>
							<div style="display: flex; justify-content: space-between;">
								<span><?php esc_html_e( 'Webhook Secret:', 'fraud-tenant-core' ); ?></span>
								<code style="font-size: 11px;"><?php echo esc_html( $live_status['masked_webhook'] ); ?></code>
							</div>
						<?php endif; ?>
					</div>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php AdminConsole::return_field(); ?>
						<input type="hidden" name="action" value="ft_save_stripe_settings" />
						<input type="hidden" name="stripe_mode" value="live" />
						<?php wp_nonce_field( 'ft_save_stripe_settings', 'ft_stripe_nonce' ); ?>

						<div style="margin-bottom: 14px;">
							<label for="live_secret_key" style="display: block; font-size: 12px; font-weight: 600; color: #14263E; margin-bottom: 4px;">
								<?php esc_html_e( 'Live Secret Key (sk_live_... or rk_live_...)', 'fraud-tenant-core' ); ?>
							</label>
							<input type="password" id="live_secret_key" name="stripe_secret_key" placeholder="<?php echo $live_status['has_secret_key'] ? '••••••••••••••••••••••••••••••••' : 'sk_live_...'; ?>" autocomplete="new-password" style="width: 100%; border: 1px solid #CBD5E1; border-radius: 6px; padding: 7px 10px; font-family: monospace; font-size: 12px;" />
							<span style="font-size: 11px; color: #64748B;"><?php esc_html_e( 'Leave blank to preserve existing saved key.', 'fraud-tenant-core' ); ?></span>
						</div>

						<div style="margin-bottom: 14px;">
							<label for="live_publishable_key" style="display: block; font-size: 12px; font-weight: 600; color: #14263E; margin-bottom: 4px;">
								<?php esc_html_e( 'Live Publishable Key (pk_live_...)', 'fraud-tenant-core' ); ?>
							</label>
							<input type="text" id="live_publishable_key" name="stripe_publishable_key" placeholder="<?php echo $live_status['has_publishable_key'] ? '••••••••••••••••••••••••••••••••' : 'pk_live_...'; ?>" autocomplete="off" style="width: 100%; border: 1px solid #CBD5E1; border-radius: 6px; padding: 7px 10px; font-family: monospace; font-size: 12px;" />
						</div>

						<div style="margin-bottom: 18px;">
							<label for="live_webhook_secret" style="display: block; font-size: 12px; font-weight: 600; color: #14263E; margin-bottom: 4px;">
								<?php esc_html_e( 'Live Webhook Secret (whsec_...)', 'fraud-tenant-core' ); ?>
							</label>
							<input type="password" id="live_webhook_secret" name="stripe_webhook_secret" placeholder="<?php echo $live_status['has_webhook_secret'] ? '••••••••••••••••••••••••••••••••' : 'whsec_...'; ?>" autocomplete="new-password" style="width: 100%; border: 1px solid #CBD5E1; border-radius: 6px; padding: 7px 10px; font-family: monospace; font-size: 12px;" />
						</div>

						<div style="display: flex; gap: 8px; flex-wrap: wrap;">
							<button type="submit" class="button button-primary" style="background: #14263E; border-color: #14263E;">
								<?php esc_html_e( 'Save Live Credentials', 'fraud-tenant-core' ); ?>
							</button>
						</div>
					</form>

					<div style="margin-top: 14px; pt-3; border-top: 1px solid #F1F5F9; display: flex; gap: 8px; justify-content: space-between;">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;"><?php AdminConsole::return_field(); ?>
							<input type="hidden" name="action" value="ft_test_stripe_connection" />
							<input type="hidden" name="stripe_mode" value="live" />
							<?php wp_nonce_field( 'ft_test_stripe_connection', 'ft_stripe_test_nonce' ); ?>
							<button type="submit" class="button button-secondary" style="font-size: 12px;">
								<span class="dashicons dashicons-yes-alt" style="font-size: 14px; width: 14px; height: 14px; vertical-align: text-top; color: #059669;"></span>
								<?php esc_html_e( 'Test Connection (Live Mode)', 'fraud-tenant-core' ); ?>
							</button>
						</form>

						<?php if ( $live_status['is_configured'] ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Are you sure you want to remove Live Mode Stripe credentials?', 'fraud-tenant-core' ) ); ?><?php AdminConsole::return_field(); ?>');" style="display: inline;">
								<input type="hidden" name="action" value="ft_remove_stripe_settings" />
								<input type="hidden" name="stripe_mode" value="live" />
								<?php wp_nonce_field( 'ft_remove_stripe_settings', 'ft_stripe_remove_nonce' ); ?>
								<button type="submit" class="button" style="color: #DC2626; font-size: 12px;">
									<?php esc_html_e( 'Remove Live Keys', 'fraud-tenant-core' ); ?>
								</button>
							</form>
						<?php endif; ?>
					</div>
				</div>

			</div>

			<!-- Middle Row: Plan Stripe ID Mappings Card -->
			<div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 8px; padding: 24px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
				<div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #F1F5F9; padding-bottom: 14px; margin-bottom: 18px;">
					<div>
						<h2 style="font-size: 17px; font-weight: 700; color: #14263E; margin: 0 0 4px;">
							<?php esc_html_e( 'Membership Plans — Test & Live Stripe Mappings', 'fraud-tenant-core' ); ?>
						</h2>
						<p style="color: #64748B; font-size: 13px; margin: 0;">
							<?php esc_html_e( 'Configure separate Stripe Product and Price IDs for Test Mode and Live Mode.', 'fraud-tenant-core' ); ?>
						</p>
					</div>
				</div>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php AdminConsole::return_field(); ?>
					<input type="hidden" name="action" value="ft_save_stripe_plan_mappings" />
					<?php wp_nonce_field( 'ft_save_stripe_plan_mappings', 'ft_plan_map_nonce' ); ?>

					<table class="wp-list-table widefat fixed striped" style="border: 1px solid #E2E8F0; border-radius: 6px;">
						<thead>
							<tr style="background: #F8FAFC;">
								<th style="font-weight: 600; width: 15%;"><?php esc_html_e( 'Plan Name', 'fraud-tenant-core' ); ?></th>
								<th style="font-weight: 600; width: 12%;"><?php esc_html_e( 'Price / Term', 'fraud-tenant-core' ); ?></th>
								<th style="font-weight: 600; width: 18%;"><?php esc_html_e( 'Test Product ID', 'fraud-tenant-core' ); ?></th>
								<th style="font-weight: 600; width: 18%;"><?php esc_html_e( 'Test Price ID', 'fraud-tenant-core' ); ?></th>
								<th style="font-weight: 600; width: 18%;"><?php esc_html_e( 'Live Product ID', 'fraud-tenant-core' ); ?></th>
								<th style="font-weight: 600; width: 18%;"><?php esc_html_e( 'Live Price ID', 'fraud-tenant-core' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $plans as $plan ) : ?>
								<tr>
									<td>
										<strong><?php echo esc_html( $plan->name ); ?></strong>
										<br><code style="font-size: 11px;"><?php echo esc_html( $plan->plan_code ); ?></code>
									</td>
									<td>
										<?php if ( (float) $plan->price_amount <= 0 ) : ?>
											<span style="color: #64748B; font-weight: 600;"><?php esc_html_e( 'Free ($0.00)', 'fraud-tenant-core' ); ?></span>
										<?php else : ?>
											<strong>$<?php echo number_format( (float) $plan->price_amount, 2 ); ?></strong>
											<span style="color: #64748B; font-size: 11px;">/ <?php echo esc_html( $plan->billing_interval ); ?></span>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( 'free' === $plan->plan_code ) : ?>
											<span style="color: #94A3B8; font-size: 12px;"><?php esc_html_e( 'N/A (Free Plan)', 'fraud-tenant-core' ); ?></span>
										<?php else : ?>
											<input type="text" name="plans[<?php echo esc_attr( $plan->id ); ?>][stripe_test_product_id]" value="<?php echo esc_attr( $plan->stripe_test_product_id ?? '' ); ?>" placeholder="prod_..." style="width: 100%; font-family: monospace; font-size: 11px;" />
										<?php endif; ?>
									</td>
									<td>
										<?php if ( 'free' === $plan->plan_code ) : ?>
											<span style="color: #94A3B8; font-size: 12px;"><?php esc_html_e( 'N/A (Free Plan)', 'fraud-tenant-core' ); ?></span>
										<?php else : ?>
											<input type="text" name="plans[<?php echo esc_attr( $plan->id ); ?>][stripe_test_price_id]" value="<?php echo esc_attr( $plan->stripe_test_price_id ?? '' ); ?>" placeholder="price_..." style="width: 100%; font-family: monospace; font-size: 11px;" />
										<?php endif; ?>
									</td>
									<td>
										<?php if ( 'free' === $plan->plan_code ) : ?>
											<span style="color: #94A3B8; font-size: 12px;"><?php esc_html_e( 'N/A (Free Plan)', 'fraud-tenant-core' ); ?></span>
										<?php else : ?>
											<input type="text" name="plans[<?php echo esc_attr( $plan->id ); ?>][stripe_live_product_id]" value="<?php echo esc_attr( $plan->stripe_live_product_id ?? '' ); ?>" placeholder="prod_..." style="width: 100%; font-family: monospace; font-size: 11px;" />
										<?php endif; ?>
									</td>
									<td>
										<?php if ( 'free' === $plan->plan_code ) : ?>
											<span style="color: #94A3B8; font-size: 12px;"><?php esc_html_e( 'N/A (Free Plan)', 'fraud-tenant-core' ); ?></span>
										<?php else : ?>
											<input type="text" name="plans[<?php echo esc_attr( $plan->id ); ?>][stripe_live_price_id]" value="<?php echo esc_attr( $plan->stripe_live_price_id ?? '' ); ?>" placeholder="price_..." style="width: 100%; font-family: monospace; font-size: 11px;" />
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<div style="margin-top: 16px; text-align: right;">
						<button type="submit" class="button button-primary" style="background: #14263E; border-color: #14263E;">
							<?php esc_html_e( 'Save Stripe Plan Mappings', 'fraud-tenant-core' ); ?>
						</button>
					</div>
				</form>
			</div>

			<!-- Bottom Row: Webhook Configuration & Security Info -->
			<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">

				<!-- Webhook Integration Card -->
				<div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
					<h3 style="font-size: 15px; font-weight: 700; color: #14263E; margin: 0 0 12px; border-bottom: 1px solid #F1F5F9; padding-bottom: 10px;">
						<?php esc_html_e( 'Stripe Webhook Configuration', 'fraud-tenant-core' ); ?>
					</h3>
					<p style="font-size: 13px; color: #475569; margin: 0 0 12px; line-height: 1.5;">
						<?php esc_html_e( 'Configure this webhook endpoint in your Stripe Dashboard for both Test Mode and Live Mode:', 'fraud-tenant-core' ); ?>
					</p>
					<div style="background: #F1F5F9; padding: 10px 12px; border-radius: 6px; font-family: monospace; font-size: 12px; color: #14263E; word-break: break-all; margin-bottom: 12px;">
						<?php echo esc_url( rest_url( 'fraud-tenant/v1/stripe/webhook' ) ); ?>
					</div>
					<div style="font-size: 12px; color: #64748B;">
						<strong><?php esc_html_e( 'Required Stripe Events to subscribe:', 'fraud-tenant-core' ); ?></strong>
						<ul style="margin: 6px 0 0 16px; list-style-type: disc;">
							<li><code>checkout.session.completed</code></li>
							<li><code>customer.subscription.created</code></li>
							<li><code>customer.subscription.updated</code></li>
							<li><code>customer.subscription.deleted</code></li>
							<li><code>invoice.payment_succeeded</code></li>
							<li><code>invoice.payment_failed</code></li>
						</ul>
					</div>
				</div>

				<!-- Security & Cryptography Policy Card -->
				<div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
					<h3 style="font-size: 15px; font-weight: 700; color: #14263E; margin: 0 0 12px; border-bottom: 1px solid #F1F5F9; padding-bottom: 10px;">
						<?php esc_html_e( 'Security & Cryptography Policy', 'fraud-tenant-core' ); ?>
					</h3>
					<div style="display: flex; flex-direction: column; gap: 10px; font-size: 12px; color: #475569;">
						<div style="display: flex; justify-content: space-between;">
							<span><?php esc_html_e( 'WordPress Environment:', 'fraud-tenant-core' ); ?></span>
							<strong style="color: #14263E; font-family: monospace;"><?php echo esc_html( $env ); ?></strong>
						</div>
						<div style="display: flex; justify-content: space-between;">
							<span><?php esc_html_e( 'Active Gateway Mode:', 'fraud-tenant-core' ); ?></span>
							<strong style="color: <?php echo Constants::STRIPE_MODE_LIVE === $active_mode ? '#059669' : '#D97706'; ?>;">
								<?php echo esc_html( strtoupper( $active_mode ) ); ?>
							</strong>
						</div>
						<div style="display: flex; justify-content: space-between;">
							<span><?php esc_html_e( 'Encryption Engine:', 'fraud-tenant-core' ); ?></span>
							<strong style="color: #14263E;">AES-256-GCM (96-bit IV, 128-bit Tag)</strong>
						</div>
						<div style="display: flex; justify-content: space-between;">
							<span><?php esc_html_e( 'Key Derivation Mechanism:', 'fraud-tenant-core' ); ?></span>
							<strong style="color: #14263E;">HKDF-SHA256 (wp-config.php Salts)</strong>
						</div>
						<div style="display: flex; justify-content: space-between;">
							<span><?php esc_html_e( 'SSL / HTTPS Connection:', 'fraud-tenant-core' ); ?></span>
							<strong style="color: <?php echo $is_ssl ? '#059669' : '#D97706'; ?>;">
								<?php echo $is_ssl ? esc_html__( 'Active (Enforced)', 'fraud-tenant-core' ) : esc_html__( 'Inactive (Local/Dev Only)', 'fraud-tenant-core' ); ?>
							</strong>
						</div>
					</div>
				</div>

			</div>

		</div>
		<?php
	}

	/**
	 * Handle admin operational Stripe mode switch POST submission.
	 */
	public static function handle_switch_mode(): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			wp_die( esc_html__( 'Invalid request method.', 'fraud-tenant-core' ), 405 );
		}

		$admin_id = get_current_user_id();
		if ( ! is_user_logged_in() || ( ! current_user_can( 'manage_options' ) && ! current_user_can( Capabilities::MANAGE_PLATFORM_SETTINGS ) ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'fraud-tenant-core' ), 403 );
		}

		$nonce = isset( $_POST['ft_mode_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ft_mode_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ft_switch_stripe_mode_action' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'fraud-tenant-core' ), 403 );
		}

		$mode = isset( $_POST['stripe_mode'] ) ? sanitize_key( $_POST['stripe_mode'] ) : '';
		$res  = StripeCredentialService::set_active_mode( $mode, $admin_id );

		if ( $res['success'] ) {
			self::set_flash_notice( 'success', $res['message'] );
		} else {
			self::set_flash_notice( 'error', $res['message'] );
		}

		wp_safe_redirect( AdminConsole::return_url( 'stripe' ) );
		if ( ! defined( 'FT_DOING_TESTS' ) ) {
			exit;
		}
	}

	/**
	 * Handle admin Stripe credential save POST submission for a given mode.
	 */
	public static function handle_save(): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			wp_die( esc_html__( 'Invalid request method.', 'fraud-tenant-core' ), 405 );
		}

		$admin_id = get_current_user_id();
		if ( ! is_user_logged_in() || ( ! current_user_can( 'manage_options' ) && ! current_user_can( Capabilities::MANAGE_PLATFORM_SETTINGS ) ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'fraud-tenant-core' ), 403 );
		}

		$nonce = isset( $_POST['ft_stripe_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ft_stripe_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ft_save_stripe_settings' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'fraud-tenant-core' ), 403 );
		}

		$mode  = isset( $_POST['stripe_mode'] ) ? sanitize_key( $_POST['stripe_mode'] ) : '';
		$input = array(
			'secret_key'      => isset( $_POST['stripe_secret_key'] ) ? sanitize_text_field( wp_unslash( $_POST['stripe_secret_key'] ) ) : '',
			'publishable_key' => isset( $_POST['stripe_publishable_key'] ) ? sanitize_text_field( wp_unslash( $_POST['stripe_publishable_key'] ) ) : '',
			'webhook_secret'  => isset( $_POST['stripe_webhook_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['stripe_webhook_secret'] ) ) : '',
		);

		$res = StripeCredentialService::save_credentials( $input, $admin_id, $mode );

		if ( $res['success'] ) {
			self::set_flash_notice( 'success', $res['message'] );
		} else {
			self::set_flash_notice( 'error', $res['message'] );
		}

		wp_safe_redirect( AdminConsole::return_url( 'stripe' ) );
		if ( ! defined( 'FT_DOING_TESTS' ) ) {
			exit;
		}
	}

	/**
	 * Handle admin Stripe credential removal POST submission for a given mode.
	 */
	public static function handle_remove(): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			wp_die( esc_html__( 'Invalid request method.', 'fraud-tenant-core' ), 405 );
		}

		$admin_id = get_current_user_id();
		if ( ! is_user_logged_in() || ( ! current_user_can( 'manage_options' ) && ! current_user_can( Capabilities::MANAGE_PLATFORM_SETTINGS ) ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'fraud-tenant-core' ), 403 );
		}

		$nonce = isset( $_POST['ft_stripe_remove_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ft_stripe_remove_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ft_remove_stripe_settings' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'fraud-tenant-core' ), 403 );
		}

		$mode = isset( $_POST['stripe_mode'] ) ? sanitize_key( $_POST['stripe_mode'] ) : '';
		$res  = StripeCredentialService::remove_credentials( $admin_id, $mode );

		if ( $res['success'] ) {
			self::set_flash_notice( 'success', $res['message'] );
		} else {
			self::set_flash_notice( 'error', $res['message'] );
		}

		wp_safe_redirect( AdminConsole::return_url( 'stripe' ) );
		if ( ! defined( 'FT_DOING_TESTS' ) ) {
			exit;
		}
	}

	/**
	 * Handle admin Stripe test connection POST submission.
	 */
	public static function handle_test_connection(): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			wp_die( esc_html__( 'Invalid request method.', 'fraud-tenant-core' ), 405 );
		}

		$admin_id = get_current_user_id();
		if ( ! is_user_logged_in() || ( ! current_user_can( 'manage_options' ) && ! current_user_can( Capabilities::MANAGE_PLATFORM_SETTINGS ) ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'fraud-tenant-core' ), 403 );
		}

		$nonce = isset( $_POST['ft_stripe_test_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ft_stripe_test_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ft_test_stripe_connection' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'fraud-tenant-core' ), 403 );
		}

		$mode = isset( $_POST['stripe_mode'] ) ? sanitize_key( $_POST['stripe_mode'] ) : '';
		$res  = StripeService::test_connection( $mode );

		if ( $res['success'] ) {
			self::set_flash_notice( 'success', $res['message'] );
		} else {
			self::set_flash_notice( 'error', $res['message'] );
		}

		wp_safe_redirect( AdminConsole::return_url( 'stripe' ) );
		if ( ! defined( 'FT_DOING_TESTS' ) ) {
			exit;
		}
	}

	/**
	 * Handle admin Stripe plan mappings save POST submission.
	 */
	public static function handle_save_plan_mappings(): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			wp_die( esc_html__( 'Invalid request method.', 'fraud-tenant-core' ), 405 );
		}

		$admin_id = get_current_user_id();
		if ( ! is_user_logged_in() || ( ! current_user_can( 'manage_options' ) && ! current_user_can( Capabilities::MANAGE_MEMBERSHIP_PLANS ) ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'fraud-tenant-core' ), 403 );
		}

		$nonce = isset( $_POST['ft_plan_map_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ft_plan_map_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ft_save_stripe_plan_mappings' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'fraud-tenant-core' ), 403 );
		}

		$plans_data = isset( $_POST['plans'] ) && is_array( $_POST['plans'] ) ? $_POST['plans'] : array();
		global $wpdb;
		$table = $wpdb->prefix . Constants::TABLE_MEMBERSHIP_PLANS;

		foreach ( $plans_data as $plan_id => $data ) {
			$pid = (int) $plan_id;
			if ( $pid <= 0 ) {
				continue;
			}

			$test_prod  = isset( $data['stripe_test_product_id'] ) ? sanitize_text_field( wp_unslash( $data['stripe_test_product_id'] ) ) : '';
			$test_price = isset( $data['stripe_test_price_id'] ) ? sanitize_text_field( wp_unslash( $data['stripe_test_price_id'] ) ) : '';
			$live_prod  = isset( $data['stripe_live_product_id'] ) ? sanitize_text_field( wp_unslash( $data['stripe_live_product_id'] ) ) : '';
			$live_price = isset( $data['stripe_live_price_id'] ) ? sanitize_text_field( wp_unslash( $data['stripe_live_price_id'] ) ) : '';

			$wpdb->update(
				$table,
				array(
					'stripe_test_product_id' => $test_prod ?: null,
					'stripe_test_price_id'   => $test_price ?: null,
					'stripe_live_product_id' => $live_prod ?: null,
					'stripe_live_price_id'   => $live_price ?: null,
					'updated_at'             => current_time( 'mysql' ),
				),
				array( 'id' => $pid ),
				array( '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
		}

		self::set_flash_notice( 'success', __( 'Stripe plan mappings updated successfully.', 'fraud-tenant-core' ) );
		wp_safe_redirect( AdminConsole::return_url( 'stripe' ) );
		if ( ! defined( 'FT_DOING_TESTS' ) ) {
			exit;
		}
	}
}
