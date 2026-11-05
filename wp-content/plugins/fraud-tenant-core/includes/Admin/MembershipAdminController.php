<?php
/**
 * Administrator Membership Plans Controller.
 *
 * Provides administrative screens to inspect, configure, and manage membership plans,
 * property quotas, feature entitlements, and Stripe identifier mappings.
 *
 * @package FraudTenant\Admin
 */

namespace FraudTenant\Admin;

use FraudTenant\Auth\Capabilities;
use FraudTenant\Services\MembershipService;
use FraudTenant\Utils\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MembershipAdminController {

	/**
	 * Main controller entry point for admin menu.
	 */
	public static function render(): void {
		$admin_id = get_current_user_id();

		// Capability authorization check
		if ( ! current_user_can( Capabilities::MANAGE_MEMBERSHIP_PLANS ) ) {
			wp_die( esc_html__( 'Unauthorized access. You do not have permission to manage membership plans.', 'fraud-tenant-core' ), 403 );
		}

		$action  = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';
		$plan_id = isset( $_GET['plan_id'] ) ? (int) $_GET['plan_id'] : 0;

		if ( 'edit' === $action && $plan_id > 0 ) {
			self::render_edit_plan( $plan_id );
		} else {
			self::render_plans_list();
		}
	}

	/**
	 * Render flash notices stored in transient for the current admin user.
	 */
	protected static function display_flash_notices(): void {
		$admin_id = get_current_user_id();
		if ( $admin_id <= 0 ) {
			return;
		}

		$notice = get_transient( 'ft_admin_notice_' . $admin_id );
		if ( $notice && is_array( $notice ) ) {
			delete_transient( 'ft_admin_notice_' . $admin_id );
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
				'ft_admin_notice_' . $admin_id,
				array(
					'type'    => $type,
					'message' => $message,
				),
				30
			);
		}
	}

	/**
	 * Render the Membership Plans list screen.
	 */
	protected static function render_plans_list(): void {
		$plans = MembershipService::get_plans( false );
		?>
		<div class="wrap ft-admin-wrap" style="max-width: 1240px; margin: 20px auto 40px;">
			<h1 class="wp-heading-inline" style="color: #14263E; font-size: 24px; font-weight: 700; margin-bottom: 6px;">
				<span class="dashicons dashicons-tickets-alt" style="font-size:28px;width:28px;height:28px;vertical-align:middle;color:#0E9488;margin-right:6px;"></span>
				<?php esc_html_e( 'Membership Plans Management', 'fraud-tenant-core' ); ?>
			</h1>
			<p class="description" style="color: #64748B; font-size: 14px; margin-bottom: 20px;">
				<?php esc_html_e( 'Configure membership plans, feature entitlement flags, Free-plan property quotas, and Stripe product/price mappings.', 'fraud-tenant-core' ); ?>
			</p>

			<?php self::display_flash_notices(); ?>

			<!-- Informational Scope Banner -->
			<div style="background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 8px; padding: 14px 18px; margin-bottom: 24px; display: flex; align-items: flex-start; gap: 12px;">
				<span class="dashicons dashicons-shield" style="color: #16A34A; font-size: 22px; width: 22px; height: 22px; margin-top: 2px;"></span>
				<div style="font-size: 13px; color: #166534; line-height: 1.5;">
					<strong><?php esc_html_e( 'Phase 9.1 Membership Foundation Active:', 'fraud-tenant-core' ); ?></strong>
					<?php esc_html_e( 'Entitlement rules and Free property quotas are evaluated server-side by MembershipService. Stripe Product and Price IDs are validated locally (^prod_..., ^price_...); live remote Stripe synchronization and checkout integration will activate in Phase 9.2.', 'fraud-tenant-core' ); ?>
				</div>
			</div>

			<!-- Plans Table -->
			<div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 8px; overflow-x: auto; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
				<table class="wp-list-table widefat striped table-view-list" style="border: none; table-layout: auto !important; min-width: 1080px; width: 100%;">
					<thead>
						<tr style="background: #F8FAFC;">
							<th style="width: 130px; padding: 12px 14px; font-weight: 600; color: #14263E; white-space: nowrap;"><?php esc_html_e( 'Plan Name', 'fraud-tenant-core' ); ?></th>
							<th style="width: 110px; padding: 12px 14px; font-weight: 600; color: #14263E; white-space: nowrap;"><?php esc_html_e( 'Plan Code', 'fraud-tenant-core' ); ?></th>
							<th style="width: 120px; padding: 12px 14px; font-weight: 600; color: #14263E; white-space: nowrap;"><?php esc_html_e( 'Price & Billing', 'fraud-tenant-core' ); ?></th>
							<th style="width: 80px; padding: 12px 14px; font-weight: 600; color: #14263E; white-space: nowrap;"><?php esc_html_e( 'Status', 'fraud-tenant-core' ); ?></th>
							<th style="width: 110px; padding: 12px 14px; font-weight: 600; color: #14263E; white-space: nowrap;"><?php esc_html_e( 'Property Quota', 'fraud-tenant-core' ); ?></th>
							<th style="min-width: 220px; padding: 12px 14px; font-weight: 600; color: #14263E; white-space: nowrap;"><?php esc_html_e( 'Feature Entitlements', 'fraud-tenant-core' ); ?></th>
							<th style="width: 150px; padding: 12px 14px; font-weight: 600; color: #14263E; white-space: nowrap;"><?php esc_html_e( 'Stripe Product ID', 'fraud-tenant-core' ); ?></th>
							<th style="width: 150px; padding: 12px 14px; font-weight: 600; color: #14263E; white-space: nowrap;"><?php esc_html_e( 'Stripe Price ID', 'fraud-tenant-core' ); ?></th>
							<th style="width: 80px; padding: 12px 14px; font-weight: 600; color: #14263E; text-align: right; white-space: nowrap;"><?php esc_html_e( 'Actions', 'fraud-tenant-core' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $plans ) ) : ?>
							<tr>
								<td colspan="9" style="text-align: center; padding: 24px; color: #64748B;">
									<?php esc_html_e( 'No membership plans found in database.', 'fraud-tenant-core' ); ?>
								</td>
							</tr>
						<?php else : ?>
							<?php foreach ( $plans as $plan ) :
								$features = ! empty( $plan->features_json ) ? json_decode( $plan->features_json, true ) : array();
								$is_active = (bool) $plan->is_active;

								// Property Limit label
								if ( ! empty( $features['unlimited_properties'] ) ) {
									$quota_label = __( 'Unlimited', 'fraud-tenant-core' );
									$quota_bg    = '#ECFDF5';
									$quota_color = '#047857';
								} elseif ( isset( $features['max_active_properties'] ) ) {
									$quota_label = sprintf( _n( '%d Property', '%d Properties', (int) $features['max_active_properties'], 'fraud-tenant-core' ), (int) $features['max_active_properties'] );
									$quota_bg    = '#EFF6FF';
									$quota_color = '#1D4ED8';
								} else {
									$quota_label = __( '1 Property', 'fraud-tenant-core' );
									$quota_bg    = '#EFF6FF';
									$quota_color = '#1D4ED8';
								}

								$price_label = ( (float) $plan->price_amount > 0 )
									? sprintf( '$%0.2f / %s', (float) $plan->price_amount, esc_html( $plan->billing_interval ) )
									: __( 'Free', 'fraud-tenant-core' );
								?>
								<tr>
									<td style="padding: 12px 14px; font-weight: 600; color: #14263E; white-space: nowrap;">
										<?php echo esc_html( $plan->name ); ?>
									</td>
									<td style="padding: 12px 14px; white-space: nowrap;">
										<code style="background: #F1F5F9; color: #334155; padding: 2px 6px; border-radius: 4px; font-size: 11px;">
											<?php echo esc_html( $plan->plan_code ); ?>
										</code>
									</td>
									<td style="padding: 12px 14px; font-weight: 500; color: #14263E; white-space: nowrap;">
										<?php echo esc_html( $price_label ); ?>
									</td>
									<td style="padding: 12px 14px; white-space: nowrap;">
										<?php if ( $is_active ) : ?>
											<span style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; background: #D1FAE5; color: #065F46;">
												<?php esc_html_e( 'Active', 'fraud-tenant-core' ); ?>
											</span>
										<?php else : ?>
											<span style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; background: #F1F5F9; color: #64748B;">
												<?php esc_html_e( 'Inactive', 'fraud-tenant-core' ); ?>
											</span>
										<?php endif; ?>
									</td>
									<td style="padding: 12px 14px; white-space: nowrap;">
										<span style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; background: <?php echo esc_attr( $quota_bg ); ?>; color: <?php echo esc_attr( $quota_color ); ?>;">
											<?php echo esc_html( $quota_label ); ?>
										</span>
									</td>
									<td style="padding: 12px 14px; font-size: 12px; color: #475569; min-width: 220px;">
										<div style="display: flex; flex-wrap: wrap; gap: 4px;">
											<?php if ( ! empty( $features['tenant_search_basic'] ) ) : ?>
												<span style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 2px 7px; border-radius: 4px; font-size: 11px; white-space: nowrap; display: inline-flex; align-items: center; gap: 3px;">
													<span style="color: #0E9488; font-weight: bold;">✓</span> <?php esc_html_e( 'Basic Search', 'fraud-tenant-core' ); ?>
												</span>
											<?php endif; ?>
											<?php if ( ! empty( $features['full_reports'] ) ) : ?>
												<span style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 2px 7px; border-radius: 4px; font-size: 11px; white-space: nowrap; display: inline-flex; align-items: center; gap: 3px;">
													<span style="color: #0E9488; font-weight: bold;">✓</span> <?php esc_html_e( 'Full Reports', 'fraud-tenant-core' ); ?>
												</span>
											<?php endif; ?>
											<?php if ( ! empty( $features['incident_reporting'] ) ) : ?>
												<span style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 2px 7px; border-radius: 4px; font-size: 11px; white-space: nowrap; display: inline-flex; align-items: center; gap: 3px;">
													<span style="color: #0E9488; font-weight: bold;">✓</span> <?php esc_html_e( 'Incident Reporting', 'fraud-tenant-core' ); ?>
												</span>
											<?php endif; ?>
											<?php if ( ! empty( $features['unlimited_properties'] ) ) : ?>
												<span style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 2px 7px; border-radius: 4px; font-size: 11px; white-space: nowrap; display: inline-flex; align-items: center; gap: 3px;">
													<span style="color: #0E9488; font-weight: bold;">✓</span> <?php esc_html_e( 'Unlimited Props', 'fraud-tenant-core' ); ?>
												</span>
											<?php endif; ?>
										</div>
									</td>
									<td style="padding: 12px 14px; font-size: 12px; white-space: nowrap;">
										<?php if ( ! empty( $plan->stripe_product_id ) ) : ?>
											<code style="background: #F8FAFC; color: #0E9488; padding: 2px 6px; border-radius: 4px; font-size: 11px;">
												<?php echo esc_html( $plan->stripe_product_id ); ?>
											</code>
										<?php else : ?>
											<span style="color: #94A3B8; font-style: italic; font-size: 11px;">— <?php esc_html_e( 'Not Set', 'fraud-tenant-core' ); ?> —</span>
										<?php endif; ?>
									</td>
									<td style="padding: 12px 14px; font-size: 12px; white-space: nowrap;">
										<?php if ( ! empty( $plan->stripe_price_id ) ) : ?>
											<code style="background: #F8FAFC; color: #0E9488; padding: 2px 6px; border-radius: 4px; font-size: 11px;">
												<?php echo esc_html( $plan->stripe_price_id ); ?>
											</code>
										<?php else : ?>
											<span style="color: #94A3B8; font-style: italic; font-size: 11px;">— <?php esc_html_e( 'Not Set', 'fraud-tenant-core' ); ?> —</span>
										<?php endif; ?>
									</td>
									<td style="padding: 12px 14px; text-align: right; white-space: nowrap;">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=fraud-tenant-plans&action=edit&plan_id=' . (int) $plan->id ) ); ?>" class="button button-small" style="font-size: 12px; height: 28px; line-height: 26px;">
											<?php esc_html_e( 'Edit', 'fraud-tenant-core' ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Plan Edit form screen.
	 *
	 * @param int $plan_id Plan database ID.
	 */
	protected static function render_edit_plan( int $plan_id ): void {
		$plan = MembershipService::get_plan_by_id( $plan_id );
		if ( ! $plan ) {
			?>
			<div class="wrap ft-admin-wrap" style="max-width: 960px; margin: 30px auto;">
				<div class="notice notice-error" style="border-radius: 6px;">
					<p><?php esc_html_e( 'Membership plan not found.', 'fraud-tenant-core' ); ?></p>
				</div>
				<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=fraud-tenant-plans' ) ); ?>" class="button">← <?php esc_html_e( 'Back to Plans', 'fraud-tenant-core' ); ?></a></p>
			</div>
			<?php
			return;
		}

		$features   = ! empty( $plan->features_json ) ? json_decode( $plan->features_json, true ) : array();
		$is_free    = ( 'free' === $plan->plan_code );
		$price_text = ( (float) $plan->price_amount > 0 )
			? sprintf( '$%0.2f %s / %s', (float) $plan->price_amount, esc_html( $plan->currency ), esc_html( $plan->billing_interval ) )
			: __( '$0.00 USD (Free)', 'fraud-tenant-core' );
		?>
		<div class="wrap ft-admin-wrap" style="max-width: 960px; margin: 20px auto 40px;">
			<div style="margin-bottom: 16px;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=fraud-tenant-plans' ) ); ?>" class="button" style="height: 32px; line-height: 30px; font-size: 13px;">
					← <?php esc_html_e( 'Back to Plans List', 'fraud-tenant-core' ); ?>
				</a>
			</div>

			<h1 class="wp-heading-inline" style="color: #14263E; font-size: 24px; font-weight: 700; margin-bottom: 6px;">
				<span class="dashicons dashicons-edit" style="font-size:26px;width:26px;height:26px;vertical-align:middle;color:#0E9488;margin-right:6px;"></span>
				<?php
				printf(
					/* translators: %s: Plan name */
					esc_html__( 'Edit Membership Plan: %s', 'fraud-tenant-core' ),
					esc_html( $plan->name )
				);
				?>
			</h1>
			<p class="description" style="color: #64748B; font-size: 14px; margin-bottom: 20px;">
				<?php esc_html_e( 'Modify plan display details, feature entitlements, property quotas, and Stripe IDs.', 'fraud-tenant-core' ); ?>
			</p>

			<?php self::display_flash_notices(); ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 8px; padding: 28px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
				<input type="hidden" name="action" value="ft_admin_membership_plan_action" />
				<input type="hidden" name="plan_id" value="<?php echo esc_attr( (string) $plan->id ); ?>" />
				<?php wp_nonce_field( 'ft_admin_membership_plan_' . $plan->id, 'ft_plan_nonce' ); ?>

				<!-- Section 1: Immutable Identity & Pricing -->
				<div style="margin-bottom: 24px; border-bottom: 1px solid #F1F5F9; padding-bottom: 20px;">
					<h2 style="font-size: 16px; font-weight: 600; color: #14263E; margin-bottom: 14px;">
						<?php esc_html_e( 'Plan Identity & Financial Terms (Phase 9.1 Immutable)', 'fraud-tenant-core' ); ?>
					</h2>
					<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
						<div>
							<label style="display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px;">
								<?php esc_html_e( 'Plan Code (System Identifier)', 'fraud-tenant-core' ); ?>
							</label>
							<input type="text" value="<?php echo esc_attr( $plan->plan_code ); ?>" class="regular-text" style="background: #F8FAFC; cursor: not-allowed; width: 100%; border: 1px solid #CBD5E1; border-radius: 6px;" readonly />
							<p class="description" style="font-size: 12px; color: #94A3B8; margin-top: 4px;">
								<?php esc_html_e( 'Plan codes are immutable system keys referenced by application logic.', 'fraud-tenant-core' ); ?>
							</p>
						</div>
						<div>
							<label style="display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px;">
								<?php esc_html_e( 'Pricing & Billing Cadence', 'fraud-tenant-core' ); ?>
							</label>
							<input type="text" value="<?php echo esc_attr( $price_text ); ?>" class="regular-text" style="background: #F8FAFC; cursor: not-allowed; width: 100%; border: 1px solid #CBD5E1; border-radius: 6px;" readonly />
							<p class="description" style="font-size: 12px; color: #94A3B8; margin-top: 4px;">
								<?php esc_html_e( 'Plan price and billing interval remain immutable in Phase 9.1.', 'fraud-tenant-core' ); ?>
							</p>
						</div>
					</div>
				</div>

				<!-- Section 2: Display Information -->
				<div style="margin-bottom: 24px; border-bottom: 1px solid #F1F5F9; padding-bottom: 20px;">
					<h2 style="font-size: 16px; font-weight: 600; color: #14263E; margin-bottom: 14px;">
						<?php esc_html_e( 'Display Settings', 'fraud-tenant-core' ); ?>
					</h2>
					<div style="margin-bottom: 16px;">
						<label for="ft_plan_name" style="display: block; font-size: 13px; font-weight: 600; color: #14263E; margin-bottom: 6px;">
							<?php esc_html_e( 'Display Name', 'fraud-tenant-core' ); ?> <span style="color:#DC2626;">*</span>
						</label>
						<input type="text" id="ft_plan_name" name="name" value="<?php echo esc_attr( $plan->name ); ?>" style="width: 100%; max-width: 500px; border: 1px solid #CBD5E1; border-radius: 6px; padding: 6px 10px;" required />
					</div>
					<div style="margin-bottom: 16px;">
						<label for="ft_plan_desc" style="display: block; font-size: 13px; font-weight: 600; color: #14263E; margin-bottom: 6px;">
							<?php esc_html_e( 'Description', 'fraud-tenant-core' ); ?>
						</label>
						<textarea id="ft_plan_desc" name="description" rows="3" style="width: 100%; max-width: 650px; border: 1px solid #CBD5E1; border-radius: 6px; padding: 8px 10px; font-size: 13px;"><?php echo esc_textarea( $plan->description ?? '' ); ?></textarea>
					</div>
					<div>
						<label style="display: inline-flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: #14263E; cursor: pointer;">
							<?php if ( $is_free ) : ?>
								<input type="checkbox" name="is_active" value="1" checked disabled style="cursor: not-allowed;" />
								<input type="hidden" name="is_active" value="1" />
								<span><?php esc_html_e( 'Active (Free plan cannot be deactivated)', 'fraud-tenant-core' ); ?></span>
							<?php else : ?>
								<input type="checkbox" name="is_active" value="1" <?php checked( 1, (int) $plan->is_active ); ?> />
								<span><?php esc_html_e( 'Active for owner subscription checkout', 'fraud-tenant-core' ); ?></span>
							<?php endif; ?>
						</label>
					</div>
				</div>

				<!-- Section 3: Feature Entitlements & Property Quotas -->
				<div style="margin-bottom: 24px; border-bottom: 1px solid #F1F5F9; padding-bottom: 20px;">
					<h2 style="font-size: 16px; font-weight: 600; color: #14263E; margin-bottom: 14px;">
						<?php esc_html_e( 'Feature Entitlements & Quotas', 'fraud-tenant-core' ); ?>
					</h2>

					<!-- Free Plan Property Quota -->
					<?php if ( $is_free ) : ?>
						<div style="margin-bottom: 18px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 6px; padding: 14px;">
							<label for="ft_max_active_properties" style="display: block; font-size: 13px; font-weight: 600; color: #14263E; margin-bottom: 6px;">
								<?php esc_html_e( 'Maximum Active Properties (Free Quota)', 'fraud-tenant-core' ); ?> <span style="color:#DC2626;">*</span>
							</label>
							<input type="number" id="ft_max_active_properties" name="max_active_properties" value="<?php echo esc_attr( (string) ( $features['max_active_properties'] ?? 1 ) ); ?>" min="1" max="100" style="width: 120px; border: 1px solid #CBD5E1; border-radius: 6px; padding: 6px 10px;" required />
							<p class="description" style="font-size: 12px; color: #64748B; margin-top: 4px;">
								<?php esc_html_e( 'Baseline property quota for unpaying owners. Default is 1 active property.', 'fraud-tenant-core' ); ?>
							</p>
						</div>
					<?php else : ?>
						<div style="margin-bottom: 18px; background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 6px; padding: 14px;">
							<strong style="color: #166534; font-size: 13px;"><?php esc_html_e( 'Property Quota: Unlimited', 'fraud-tenant-core' ); ?></strong>
							<p style="font-size: 12px; color: #15803D; margin: 4px 0 0;">
								<?php esc_html_e( 'Standard plans include unlimited properties via the unlimited_properties entitlement flag (-1 property limit).', 'fraud-tenant-core' ); ?>
							</p>
						</div>
					<?php endif; ?>

					<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-top: 12px;">
						<label style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #334155;">
							<input type="checkbox" name="features[tenant_search_basic]" value="1" <?php checked( ! empty( $features['tenant_search_basic'] ) ); ?> />
							<span><?php esc_html_e( 'Basic Tenant Search Flag', 'fraud-tenant-core' ); ?></span>
						</label>

						<label style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #334155;">
							<input type="checkbox" name="features[full_reports]" value="1" <?php checked( ! empty( $features['full_reports'] ) ); ?> />
							<span><?php esc_html_e( 'Full Screening Reports', 'fraud-tenant-core' ); ?></span>
						</label>

						<label style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #334155;">
							<input type="checkbox" name="features[incident_reporting]" value="1" <?php checked( ! empty( $features['incident_reporting'] ) ); ?> />
							<span><?php esc_html_e( 'Incident Reporting', 'fraud-tenant-core' ); ?></span>
						</label>

						<?php if ( ! $is_free ) : ?>
							<label style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #334155;">
								<input type="checkbox" name="features[unlimited_properties]" value="1" <?php checked( ! empty( $features['unlimited_properties'] ) ); ?> />
								<span><?php esc_html_e( 'Unlimited Active Properties', 'fraud-tenant-core' ); ?></span>
							</label>
						<?php endif; ?>
					</div>
				</div>

				<!-- Section 4: Stripe Identifiers -->
				<div style="margin-bottom: 28px;">
					<h2 style="font-size: 16px; font-weight: 600; color: #14263E; margin-bottom: 14px;">
						<?php esc_html_e( 'Stripe Integration Identifiers (Dual Mode)', 'fraud-tenant-core' ); ?>
					</h2>
					<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 16px;">
						<div>
							<label for="ft_stripe_test_prod_id" style="display: block; font-size: 13px; font-weight: 600; color: #D97706; margin-bottom: 6px;">
								<?php esc_html_e( 'Test Product ID (Sandbox)', 'fraud-tenant-core' ); ?>
							</label>
							<input type="text" id="ft_stripe_test_prod_id" name="stripe_test_product_id" value="<?php echo esc_attr( $plan->stripe_test_product_id ?? $plan->stripe_product_id ?? '' ); ?>" placeholder="prod_xxxxxxxxxxxxxx" style="width: 100%; border: 1px solid #CBD5E1; border-radius: 6px; padding: 6px 10px; font-family: monospace;" />
						</div>
						<div>
							<label for="ft_stripe_test_price_id" style="display: block; font-size: 13px; font-weight: 600; color: #D97706; margin-bottom: 6px;">
								<?php esc_html_e( 'Test Price ID (Sandbox)', 'fraud-tenant-core' ); ?>
							</label>
							<input type="text" id="ft_stripe_test_price_id" name="stripe_test_price_id" value="<?php echo esc_attr( $plan->stripe_test_price_id ?? $plan->stripe_price_id ?? '' ); ?>" placeholder="price_xxxxxxxxxxxxxx" style="width: 100%; border: 1px solid #CBD5E1; border-radius: 6px; padding: 6px 10px; font-family: monospace;" />
						</div>
					</div>

					<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
						<div>
							<label for="ft_stripe_live_prod_id" style="display: block; font-size: 13px; font-weight: 600; color: #059669; margin-bottom: 6px;">
								<?php esc_html_e( 'Live Product ID (Production)', 'fraud-tenant-core' ); ?>
							</label>
							<input type="text" id="ft_stripe_live_prod_id" name="stripe_live_product_id" value="<?php echo esc_attr( $plan->stripe_live_product_id ?? '' ); ?>" placeholder="prod_xxxxxxxxxxxxxx" style="width: 100%; border: 1px solid #CBD5E1; border-radius: 6px; padding: 6px 10px; font-family: monospace;" />
						</div>
						<div>
							<label for="ft_stripe_live_price_id" style="display: block; font-size: 13px; font-weight: 600; color: #059669; margin-bottom: 6px;">
								<?php esc_html_e( 'Live Price ID (Production)', 'fraud-tenant-core' ); ?>
							</label>
							<input type="text" id="ft_stripe_live_price_id" name="stripe_live_price_id" value="<?php echo esc_attr( $plan->stripe_live_price_id ?? '' ); ?>" placeholder="price_xxxxxxxxxxxxxx" style="width: 100%; border: 1px solid #CBD5E1; border-radius: 6px; padding: 6px 10px; font-family: monospace;" />
						</div>
					</div>
					<div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 6px; padding: 10px 14px; font-size: 12px; color: #64748B; margin-top: 12px;">
						<strong><?php esc_html_e( 'Dual Mode Isolation:', 'fraud-tenant-core' ); ?></strong>
						<?php esc_html_e( 'Test IDs are used when Stripe operational mode is Test (Sandbox); Live IDs are used when Live Mode is active.', 'fraud-tenant-core' ); ?>
					</div>
				</div>

				<!-- Actions -->
				<div style="display: flex; align-items: center; gap: 12px; border-top: 1px solid #F1F5F9; padding-top: 20px;">
					<button type="submit" class="button button-primary" style="height: 38px; padding: 0 20px; background: #0E9488; border-color: #0E9488; font-weight: 600; font-size: 14px;">
						<?php esc_html_e( 'Save Plan Changes', 'fraud-tenant-core' ); ?>
					</button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=fraud-tenant-plans' ) ); ?>" class="button" style="height: 38px; line-height: 36px; padding: 0 16px; font-size: 14px;">
						<?php esc_html_e( 'Cancel', 'fraud-tenant-core' ); ?>
					</a>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle admin membership plan update POST submission.
	 */
	public static function handle_admin_action(): void {
		$admin_id = get_current_user_id();

		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Authentication required.', 'fraud-tenant-core' ), 403 );
		}

		if ( ! current_user_can( Capabilities::MANAGE_MEMBERSHIP_PLANS ) ) {
			wp_die( esc_html__( 'You do not have permission to manage membership plans.', 'fraud-tenant-core' ), 403 );
		}

		$plan_id = isset( $_POST['plan_id'] ) ? (int) $_POST['plan_id'] : 0;
		$nonce   = isset( $_POST['ft_plan_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ft_plan_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'ft_admin_membership_plan_' . $plan_id ) ) {
			wp_die( esc_html__( 'Security check failed. Please refresh the page and try again.', 'fraud-tenant-core' ), 403 );
		}

		$redirect_url = add_query_arg(
			array(
				'page'    => 'fraud-tenant-plans',
				'action'  => 'edit',
				'plan_id' => $plan_id,
			),
			admin_url( 'admin.php' )
		);

		$data = array(
			'name'                  => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'description'           => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
			'is_active'             => ! empty( $_POST['is_active'] ),
			'stripe_product_id'      => isset( $_POST['stripe_product_id'] ) ? sanitize_text_field( wp_unslash( $_POST['stripe_product_id'] ) ) : '',
			'stripe_price_id'        => isset( $_POST['stripe_price_id'] ) ? sanitize_text_field( wp_unslash( $_POST['stripe_price_id'] ) ) : '',
			'stripe_test_product_id' => isset( $_POST['stripe_test_product_id'] ) ? sanitize_text_field( wp_unslash( $_POST['stripe_test_product_id'] ) ) : '',
			'stripe_test_price_id'   => isset( $_POST['stripe_test_price_id'] ) ? sanitize_text_field( wp_unslash( $_POST['stripe_test_price_id'] ) ) : '',
			'stripe_live_product_id' => isset( $_POST['stripe_live_product_id'] ) ? sanitize_text_field( wp_unslash( $_POST['stripe_live_product_id'] ) ) : '',
			'stripe_live_price_id'   => isset( $_POST['stripe_live_price_id'] ) ? sanitize_text_field( wp_unslash( $_POST['stripe_live_price_id'] ) ) : '',
			'max_active_properties' => isset( $_POST['max_active_properties'] ) ? (int) $_POST['max_active_properties'] : 1,
			'features'              => array(
				'tenant_search_basic'  => ! empty( $_POST['features']['tenant_search_basic'] ),
				'full_reports'         => ! empty( $_POST['features']['full_reports'] ),
				'incident_reporting'   => ! empty( $_POST['features']['incident_reporting'] ),
				'unlimited_properties' => ! empty( $_POST['features']['unlimited_properties'] ),
			),
		);

		$res = MembershipService::update_plan( $plan_id, $data, $admin_id );

		if ( $res['success'] ) {
			self::set_flash_notice( 'success', $res['message'] );
		} else {
			self::set_flash_notice( 'error', $res['message'] );
		}

		wp_safe_redirect( $redirect_url );
		if ( ! defined( 'FT_DOING_TESTS' ) ) {
			exit;
		}
	}
}

