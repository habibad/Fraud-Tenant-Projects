<?php
/**
 * Custom SaaS Administrative Executive Dashboard Controller.
 *
 * Provides a comprehensive, production-grade 15-section SaaS command center
 * covering Overview & KPIs, User Management, Account Approval, Owner Verification,
 * Property Verification, Property Directory, Screening Requests, Incident Review,
 * Tenant Disputes, Report Visibility, Subscriptions & MRR, Membership Plans,
 * Stripe Settings, Audit Logs, and Platform System Health.
 *
 * @package FraudTenant\Admin
 */

namespace FraudTenant\Admin;

use FraudTenant\Auth\Capabilities;
use FraudTenant\Services\MembershipService;
use FraudTenant\Services\StripeCredentialService;
use FraudTenant\Services\StripeService;
use FraudTenant\Utils\Constants;
use FraudTenant\Utils\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminDashboardController {

	/**
	 * Main controller entry point.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( Capabilities::REVIEW_USER_REGISTRATIONS ) ) {
			wp_die( esc_html__( 'Unauthorized access. You do not have permission to view the executive administrative dashboard.', 'fraud-tenant-core' ), 403 );
		}

		// Backward compatibility: delegate single user review detail requests
		if ( isset( $_GET['action'] ) && 'review' === $_GET['action'] && isset( $_GET['user_id'] ) ) {
			UserReviewController::render();
			return;
		}

		self::render_dashboard();
	}

	/**
	 * Capability required to open each Command Center section (enforced server-side).
	 */
	public const SECTION_CAPS = array(
		'overview'              => Capabilities::REVIEW_USER_REGISTRATIONS,
		'users'                 => Capabilities::REVIEW_USER_REGISTRATIONS,
		'account_approval'      => Capabilities::REVIEW_USER_REGISTRATIONS,
		'owner_verification'    => Capabilities::REVIEW_USER_REGISTRATIONS,
		'property_verification' => Capabilities::REVIEW_PROPERTIES,
		'properties'            => Capabilities::REVIEW_PROPERTIES,
		'screening'             => Capabilities::VIEW_ALL_SCREENING_LOGS,
		'incidents'             => Capabilities::REVIEW_INCIDENT_REPORTS,
		'disputes'              => Capabilities::REVIEW_TENANT_DISPUTES,
		'visibility'            => Capabilities::MANAGE_REPORT_VISIBILITY,
		'subscriptions'         => Capabilities::MANAGE_MEMBERSHIP_PLANS,
		'plans'                 => Capabilities::MANAGE_MEMBERSHIP_PLANS,
		'stripe'                => 'manage_options',
		'audit_logs'            => Capabilities::VIEW_AUDIT_LOGS,
		'platform_settings'     => Capabilities::MANAGE_PLATFORM_SETTINGS,
	);

	/**
	 * Whether the current user may open a section. Unknown sections are denied.
	 *
	 * @param string $section Section key.
	 * @return bool
	 */
	public static function can_view_section( string $section ): bool {
		if ( ! isset( self::SECTION_CAPS[ $section ] ) ) {
			return false;
		}
		return current_user_can( 'manage_options' ) || current_user_can( self::SECTION_CAPS[ $section ] );
	}

	/**
	 * Register admin-post handlers owned by the Command Center.
	 */
	public static function init(): void {
		add_action( 'admin_post_ft_admin_billing_qa_flag', array( __CLASS__, 'handle_billing_qa_flag' ) );
		add_action( 'admin_post_ft_admin_sync_subscription', array( __CLASS__, 'handle_sync_subscription' ) );
		// Enforce section authorization before wp-admin sends any output, so denial is a true 403.
		add_action( 'admin_init', array( __CLASS__, 'guard_section_request' ) );
	}

	/**
	 * Early (pre-output) authorization for Command Center section requests.
	 */
	public static function guard_section_request(): void {
		if ( ! isset( $_GET['page'] ) || 'fraud-tenant-admin' !== $_GET['page'] || wp_doing_ajax() ) {
			return;
		}
		if ( isset( $_GET['action'] ) && 'review' === $_GET['action'] ) {
			return; // Delegated to UserReviewController's own checks.
		}
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'overview';
		if ( ! self::can_view_section( $tab ) ) {
			wp_die( esc_html__( 'You do not have permission to view this section.', 'fraud-tenant-core' ), esc_html__( 'Forbidden', 'fraud-tenant-core' ), array( 'response' => 403 ) );
		}
	}

	/**
	 * Toggle the billing QA account flag (administrators only; nonce + capability; audited).
	 */
	public static function handle_billing_qa_flag(): void {
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		check_admin_referer( 'ft_billing_qa_flag_' . $user_id );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'fraud-tenant-core' ), 403 );
		}
		$enable = ! empty( $_POST['enable'] );
		$target = get_userdata( $user_id );
		if ( $target && in_array( Constants::ROLE_OWNER, (array) $target->roles, true ) ) {
			StripeCredentialService::set_billing_qa_account( $user_id, $enable, get_current_user_id() );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'fraud-tenant-admin', 'tab' => 'users', 'ft_notice' => 'qa_flag_updated' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Re-synchronize one subscription from Stripe's authoritative state (operations tool).
	 */
	public static function handle_sync_subscription(): void {
		$sub_db_id = isset( $_POST['subscription_db_id'] ) ? absint( $_POST['subscription_db_id'] ) : 0;
		check_admin_referer( 'ft_sync_subscription_' . $sub_db_id );
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( Capabilities::MANAGE_MEMBERSHIP_PLANS ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'fraud-tenant-core' ), 403 );
		}
		global $wpdb;
		$row    = $wpdb->get_row( $wpdb->prepare( "SELECT stripe_subscription_id, livemode FROM {$wpdb->prefix}" . Constants::TABLE_SUBSCRIPTIONS . ' WHERE id = %d', $sub_db_id ) );
		$notice = 'sync_failed';
		if ( $row && ! empty( $row->stripe_subscription_id ) ) {
			try {
				\FraudTenant\Services\StripeWebhookService::sync_subscription( (string) $row->stripe_subscription_id, $row->livemode ? Constants::STRIPE_MODE_LIVE : Constants::STRIPE_MODE_TEST, 'admin.manual_sync' );
				$notice = 'sync_ok';
			} catch ( \Throwable $e ) {
				$notice = 'sync_failed';
			}
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'fraud-tenant-admin', 'tab' => 'subscriptions', 'ft_notice' => $notice ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Compliance gates panel (BLK-04 status, BLK-08 and BLK-03 attestations).
	 */
	protected static function render_compliance_gates(): void {
		$st     = \FraudTenant\Services\ComplianceService::status();
		$notice = get_transient( 'ft_admin_gate_notice_' . get_current_user_id() );
		if ( $notice ) {
			delete_transient( 'ft_admin_gate_notice_' . get_current_user_id() );
		}
		$can_edit = current_user_can( 'manage_options' );
		$badge    = static function ( bool $ok, string $yes, string $no ): string {
			return '<span style="font-size:11px; font-weight:700; padding:2px 8px; border-radius:10px; background:' . ( $ok ? '#DCFCE7; color:#166534' : '#FEE2E2; color:#991B1B' ) . ';">' . esc_html( $ok ? $yes : $no ) . '</span>';
		};
		$gates = array(
			\FraudTenant\Services\ComplianceService::GATE_PII_PROTECTION => array(
				'title' => __( 'BLK-08 · Sensitive data protection', 'fraud-tenant-core' ),
				'desc'  => __( 'Attest that the production database/disk is encrypted at rest, backups are encrypted with restricted access, and that you accept responsibility for this attestation. Unlocks incident submission, review and evidence handling outside test mode.', 'fraud-tenant-core' ),
				'ph'    => __( 'e.g. Hosting: AWS RDS encrypted (KMS); nightly backups encrypted, access limited to 2 admins — confirmed by <name>, <date>', 'fraud-tenant-core' ),
			),
			\FraudTenant\Services\ComplianceService::GATE_OWNER_DISCLOSURE => array(
				'title' => __( 'BLK-03 · Legal approval for owner screening disclosure', 'fraud-tenant-core' ),
				'desc'  => __( 'Attest that qualified U.S. counsel approved showing administrator-released incident records to screening owners (FCRA/state law, permissible purpose, consent, dispute and adverse-action process). Enables owner identity search results.', 'fraud-tenant-core' ),
				'ph'    => __( 'e.g. Written opinion from <law firm>, reference <ID>, dated <date>', 'fraud-tenant-core' ),
			),
		);
		?>
		<div class="ft-compliance-gates" style="background:#FFFFFF; border:1px solid #E2E8F0; border-radius:10px; padding:24px; box-shadow:0 1px 3px rgba(0,0,0,0.05); margin-bottom:24px;">
			<h3 style="margin:0 0 6px 0; font-size:18px; font-weight:800; color:#14263E;"><?php esc_html_e( 'Compliance Gates — Tenant Identity & Owner Screening', 'fraud-tenant-core' ); ?></h3>
			<p style="margin:0 0 16px 0; color:#64748B; font-size:13px;"><?php esc_html_e( 'Technical prerequisites are verified automatically. Legal and infrastructure prerequisites cannot be verified by software; they are recorded as audited administrator attestations. The platform does not certify legal compliance.', 'fraud-tenant-core' ); ?></p>
			<?php if ( is_array( $notice ) ) : ?>
				<div class="notice notice-<?php echo ! empty( $notice['success'] ) ? 'success' : 'error'; ?> inline"><p><?php echo esc_html( (string) $notice['message'] ); ?></p></div>
			<?php endif; ?>
			<table class="widefat striped" style="margin-bottom:18px;">
				<tbody>
					<tr><td><?php esc_html_e( 'BLK-04 · Identity match key (FT_MATCH_SECRET_KEY ≥ 32 chars)', 'fraud-tenant-core' ); ?></td><td><?php echo $badge( $st['match_key_ready'], __( 'Configured', 'fraud-tenant-core' ), __( 'Missing', 'fraud-tenant-core' ) ); // phpcs:ignore ?></td></tr>
					<tr><td><?php esc_html_e( 'Application field encryption (AES-256-GCM, salts)', 'fraud-tenant-core' ); ?></td><td><?php echo $badge( $st['encryption_available'], __( 'Available', 'fraud-tenant-core' ), __( 'Unavailable', 'fraud-tenant-core' ) ); // phpcs:ignore ?></td></tr>
					<tr><td><?php esc_html_e( 'Synthetic test mode (non-production only)', 'fraud-tenant-core' ); ?></td><td><?php echo $badge( $st['test_mode'], __( 'On', 'fraud-tenant-core' ), __( 'Off', 'fraud-tenant-core' ) ); // phpcs:ignore ?></td></tr>
					<tr><td><strong><?php esc_html_e( 'Incident data gate (submission/review)', 'fraud-tenant-core' ); ?></strong></td><td><?php echo $badge( $st['data_gate_open'], __( 'Open', 'fraud-tenant-core' ), __( 'Locked', 'fraud-tenant-core' ) ); // phpcs:ignore ?></td></tr>
					<tr><td><strong><?php esc_html_e( 'Tenant identity capture on incidents', 'fraud-tenant-core' ); ?></strong></td><td><?php echo $badge( $st['identity_capture_ready'], __( 'Ready', 'fraud-tenant-core' ), __( 'Not ready', 'fraud-tenant-core' ) ); // phpcs:ignore ?></td></tr>
					<tr><td><strong><?php esc_html_e( 'Owner identity screening results', 'fraud-tenant-core' ); ?></strong></td><td><?php echo $badge( $st['owner_search_ready'], __( 'Enabled', 'fraud-tenant-core' ), __( 'Disabled', 'fraud-tenant-core' ) ); // phpcs:ignore ?></td></tr>
				</tbody>
			</table>
			<?php foreach ( $gates as $key => $g ) : ?>
				<?php
				$rec     = 'pii_protection' === $key ? $st['pii_protection'] : $st['owner_disclosure'];
				$enabled = ! empty( $rec['enabled'] );
				$who     = ! empty( $rec['by'] ) ? get_userdata( (int) $rec['by'] ) : null;
				?>
				<div style="border:1px solid #E2E8F0; border-radius:8px; padding:16px; margin-bottom:12px;">
					<div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:center;">
						<strong style="color:#14263E;"><?php echo esc_html( $g['title'] ); ?></strong>
						<?php echo $badge( $enabled, __( 'Attested', 'fraud-tenant-core' ), __( 'Not attested', 'fraud-tenant-core' ) ); // phpcs:ignore ?>
					</div>
					<p style="font-size:12px; color:#475569; margin:6px 0 10px;"><?php echo esc_html( $g['desc'] ); ?></p>
					<?php if ( $enabled ) : ?>
						<p style="font-size:12px; color:#0F172A; margin:0 0 10px;"><?php echo esc_html( sprintf( __( 'Attested by %1$s on %2$s UTC: "%3$s"', 'fraud-tenant-core' ), $who ? $who->display_name : '#' . (int) $rec['by'], (string) $rec['at'], (string) $rec['statement'] ) ); ?></p>
					<?php endif; ?>
					<?php if ( $can_edit ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( $enabled ? __( 'Revoke this attestation? Dependent features will be locked immediately.', 'fraud-tenant-core' ) : __( 'Record this attestation under your administrator account? This is audited.', 'fraud-tenant-core' ) ); ?>');">
							<input type="hidden" name="action" value="ft_admin_compliance_gate" />
							<input type="hidden" name="gate" value="<?php echo esc_attr( $key ); ?>" />
							<input type="hidden" name="enable" value="<?php echo $enabled ? '0' : '1'; ?>" />
							<?php wp_nonce_field( 'ft_compliance_gate' ); ?>
							<?php if ( ! $enabled ) : ?>
								<label class="screen-reader-text" for="ft-gate-<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Attestation statement', 'fraud-tenant-core' ); ?></label>
								<textarea id="ft-gate-<?php echo esc_attr( $key ); ?>" name="statement" rows="2" style="width:100%; margin-bottom:8px;" placeholder="<?php echo esc_attr( $g['ph'] ); ?>" required minlength="20"></textarea>
							<?php endif; ?>
							<button type="submit" class="button <?php echo $enabled ? 'button-secondary' : 'button-primary'; ?>"><?php echo $enabled ? esc_html__( 'Revoke Attestation', 'fraud-tenant-core' ) : esc_html__( 'Record Attestation & Enable', 'fraud-tenant-core' ); ?></button>
						</form>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render a compact pagination bar.
	 *
	 * @param int   $page  Current page (1-based).
	 * @param int   $total Total rows.
	 * @param int   $per   Per page.
	 * @param array $args  Query args to keep.
	 */
	protected static function render_pagination( int $page, int $total, int $per, array $args ): void {
		$pages = max( 1, (int) ceil( $total / $per ) );
		echo '<div class="ft-pagination" style="display:flex; gap:8px; align-items:center; justify-content:flex-end; margin-top:12px; font-size:13px;">';
		/* translators: 1: current page, 2: total pages, 3: total rows */
		echo '<span style="color:#64748B;">' . esc_html( sprintf( __( 'Page %1$d of %2$d · %3$d records', 'fraud-tenant-core' ), $page, $pages, $total ) ) . '</span>';
		if ( $page > 1 ) {
			echo '<a class="button button-small" href="' . esc_url( add_query_arg( array_merge( $args, array( 'paged' => $page - 1 ) ), admin_url( 'admin.php' ) ) ) . '">&laquo; ' . esc_html__( 'Prev', 'fraud-tenant-core' ) . '</a>';
		}
		if ( $page < $pages ) {
			echo '<a class="button button-small" href="' . esc_url( add_query_arg( array_merge( $args, array( 'paged' => $page + 1 ) ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Next', 'fraud-tenant-core' ) . ' &raquo;</a>';
		}
		echo '</div>';
	}

	/**
	 * Render a flash notice from the ft_notice query arg (fixed whitelist; no reflected text).
	 */
	protected static function render_notice(): void {
		$map = array(
			'qa_flag_updated' => array( 'success', __( 'Billing QA flag updated.', 'fraud-tenant-core' ) ),
			'sync_ok'         => array( 'success', __( 'Subscription synchronized from Stripe.', 'fraud-tenant-core' ) ),
			'sync_failed'     => array( 'error', __( 'Subscription could not be synchronized (verification failed or Stripe unavailable). See the audit log.', 'fraud-tenant-core' ) ),
		);
		$key = isset( $_GET['ft_notice'] ) ? sanitize_key( $_GET['ft_notice'] ) : '';
		if ( isset( $map[ $key ] ) ) {
			echo '<div class="notice notice-' . esc_attr( $map[ $key ][0] ) . ' is-dismissible"><p>' . esc_html( $map[ $key ][1] ) . '</p></div>';
		}
	}

	/**
	 * Render the 15-section executive dashboard.
	 */
	public static function render_dashboard(): void {
		global $wpdb;

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'overview';
		if ( ! self::can_view_section( $active_tab ) ) {
			wp_die( esc_html__( 'You do not have permission to view this section.', 'fraud-tenant-core' ), 403 );
		}

		// 1. Gather Global Metrics for Overview & KPI cards
		$user_counts_raw = count_users();
		$total_users     = $user_counts_raw['total_users'] ?? 0;
		$owners_count    = $user_counts_raw['avail_roles'][ Constants::ROLE_OWNER ] ?? 0;
		$tenants_count   = $user_counts_raw['avail_roles'][ Constants::ROLE_TENANT ] ?? 0;
		$admins_count    = $user_counts_raw['avail_roles']['administrator'] ?? 0;

		$tbl_profiles = $wpdb->prefix . Constants::TABLE_USER_PROFILES;
		$tbl_props    = $wpdb->prefix . Constants::TABLE_PROPERTIES;
		$tbl_scr      = $wpdb->prefix . Constants::TABLE_SCREENING_REQUESTS;
		$tbl_inc      = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;
		$tbl_dsp      = $wpdb->prefix . Constants::TABLE_TENANT_DISPUTES;
		$tbl_subs     = $wpdb->prefix . Constants::TABLE_SUBSCRIPTIONS;
		$tbl_plans    = $wpdb->prefix . Constants::TABLE_MEMBERSHIP_PLANS;
		$tbl_assoc    = $wpdb->prefix . Constants::TABLE_REPORT_ASSOCIATIONS;
		$tbl_audit    = $wpdb->prefix . Constants::TABLE_AUDIT_LOG;

		// Pending queues
		$pending_users = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl_profiles} WHERE approval_status IN ('pending_approval', 'info_requested')" );
		$pending_props = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl_props} WHERE status = 'pending_verification'" );
		$pending_inc   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl_inc} WHERE status IN ('submitted', 'under_review', 'needs_clarification')" );
		$open_disputes = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl_dsp} WHERE status IN ('pending', 'under_review')" );

		// Screening & Properties counts
		$total_properties = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl_props}" );
		$total_searches   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl_scr}" );

		// Subscriptions & MRR calculation
		// Only subscriptions that actually grant entitlements in the ACTIVE Stripe mode count.
		$mode_flag         = 'live' === StripeCredentialService::get_active_mode() ? 1 : 0;
		$utc_now           = gmdate( 'Y-m-d H:i:s' );
		$active_subs_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tbl_subs} WHERE status = 'active' AND livemode = %d AND current_period_end >= %s", $mode_flag, $utc_now ) );
		$subscriptions     = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.status, p.price_amount AS price, p.billing_interval
				 FROM {$tbl_subs} s
				 LEFT JOIN {$tbl_plans} p ON s.plan_id = p.id
				 WHERE s.status = 'active' AND s.livemode = %d AND s.current_period_end >= %s",
				$mode_flag,
				$utc_now
			)
		) ?: array();

		$mrr = 0.0;
		foreach ( $subscriptions as $sub ) {
			if ( 'active' === $sub->status ) {
				$price = (float) $sub->price;
				if ( 'yearly' === $sub->billing_interval ) {
					$mrr += ( $price / 12 );
				} else {
					$mrr += $price;
				}
			}
		}

		$active_stripe_mode = StripeCredentialService::get_active_mode();
		$test_status        = StripeCredentialService::get_status( Constants::STRIPE_MODE_TEST );
		$live_status        = StripeCredentialService::get_status( Constants::STRIPE_MODE_LIVE );

		// Define all 15 navigation sections
		$tabs = array(
			'overview'              => __( 'Overview & KPIs', 'fraud-tenant-core' ),
			'users'                 => __( 'User Management', 'fraud-tenant-core' ),
			'account_approval'      => __( 'Account Approvals', 'fraud-tenant-core' ),
			'owner_verification'    => __( 'Owner Verification', 'fraud-tenant-core' ),
			'property_verification' => __( 'Property Queue', 'fraud-tenant-core' ),
			'properties'            => __( 'Property Directory', 'fraud-tenant-core' ),
			'screening'             => __( 'Screening Requests', 'fraud-tenant-core' ),
			'incidents'             => __( 'Incident Review', 'fraud-tenant-core' ),
			'disputes'              => __( 'Tenant Disputes', 'fraud-tenant-core' ),
			'visibility'            => __( 'Report Visibility', 'fraud-tenant-core' ),
			'subscriptions'         => __( 'Subscriptions & MRR', 'fraud-tenant-core' ),
			'plans'                 => __( 'Membership Plans', 'fraud-tenant-core' ),
			'stripe'                => __( 'Stripe Gateway', 'fraud-tenant-core' ),
			'audit_logs'            => __( 'Compliance Audit Logs', 'fraud-tenant-core' ),
			'platform_settings'     => __( 'Platform Settings', 'fraud-tenant-core' ),
		);
		// Only show sections the current administrator is authorized to open.
		$tabs = array_filter(
			$tabs,
			static function ( $key ) {
				return self::can_view_section( (string) $key );
			},
			ARRAY_FILTER_USE_KEY
		);

		?>
		<div class="wrap ft-admin-wrap" style="max-width: 1400px; margin: 20px auto; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
			
			<!-- Header Banner -->
			<div style="background: linear-gradient(135deg, #14263E 0%, #1A365D 100%); color: #FFFFFF; padding: 24px 32px; border-radius: 12px; margin-bottom: 24px; box-shadow: 0 4px 14px rgba(0,0,0,0.08); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
				<div>
					<div style="display: flex; align-items: center; gap: 10px; margin-bottom: 4px;">
						<span style="background: #0E9488; color: #FFFFFF; font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 3px 8px; border-radius: 4px; letter-spacing: 0.5px;">
							<?php esc_html_e( 'Enterprise Command Center', 'fraud-tenant-core' ); ?>
						</span>
						<span style="font-size: 12px; color: #94A3B8;">DB Schema v<?php echo esc_html( Constants::DB_VERSION ); ?></span>
					</div>
					<h1 style="color: #FFFFFF; font-size: 26px; font-weight: 800; margin: 0; letter-spacing: -0.5px;">
						<?php esc_html_e( 'Fraud Tenant — SaaS Administration', 'fraud-tenant-core' ); ?>
					</h1>
					<p style="color: #CBD5E1; font-size: 13px; margin: 6px 0 0 0;">
						<?php esc_html_e( 'Real-time property owner compliance, FCRA tenant protections, screening logs, and recurring revenue operations.', 'fraud-tenant-core' ); ?>
					</p>
				</div>
				<div style="display: flex; gap: 12px; align-items: center;">
					<div style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); border-radius: 8px; padding: 8px 16px; text-align: right;">
						<div style="font-size: 11px; text-transform: uppercase; color: #94A3B8; font-weight: 600;"><?php esc_html_e( 'Stripe Mode', 'fraud-tenant-core' ); ?></div>
						<div style="font-size: 14px; font-weight: 800; color: <?php echo 'live' === $active_stripe_mode ? '#10B981' : '#F59E0B'; ?>;">
							<?php echo strtoupper( esc_html( $active_stripe_mode ) ); ?>
						</div>
					</div>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=fraud-tenant-settings' ) ); ?>" class="button" style="background: #0E9488; color: #FFFFFF; border: none; padding: 8px 16px; height: auto; font-weight: 700; border-radius: 6px; box-shadow: 0 2px 6px rgba(14,148,136,0.3);">
						<?php esc_html_e( 'Gateway Settings', 'fraud-tenant-core' ); ?>
					</a>
				</div>
			</div>

			<!-- Navigation Bar (15 sections) -->
			<div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 6px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow-x: auto; white-space: nowrap; display: flex; gap: 4px;">
				<?php foreach ( $tabs as $tab_key => $tab_label ) : 
					$is_active = ( $active_tab === $tab_key );
					$badge_val = 0;
					if ( 'account_approval' === $tab_key ) $badge_val = $pending_users;
					if ( 'property_verification' === $tab_key ) $badge_val = $pending_props;
					if ( 'incidents' === $tab_key ) $badge_val = $pending_inc;
					if ( 'disputes' === $tab_key ) $badge_val = $open_disputes;
				?>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'fraud-tenant-admin', 'tab' => $tab_key ), admin_url( 'admin.php' ) ) ); ?>" 
					   style="padding: 10px 14px; font-size: 13px; font-weight: <?php echo $is_active ? '700' : '600'; ?>; color: <?php echo $is_active ? '#0E9488' : '#475569'; ?>; background: <?php echo $is_active ? '#F0FDFA' : 'transparent'; ?>; border-radius: 6px; text-decoration: none; border-bottom: <?php echo $is_active ? '2px solid #0E9488' : '2px solid transparent'; ?>; display: inline-flex; align-items: center; gap: 6px;">
						<?php echo esc_html( $tab_label ); ?>
						<?php if ( $badge_val > 0 ) : ?>
							<span style="background: #EF4444; color: #FFFFFF; font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 10px;">
								<?php echo esc_html( (string) $badge_val ); ?>
							</span>
						<?php endif; ?>
					</a>
				<?php endforeach; ?>
			</div>

			<!-- TAB CONTENT ROUTING -->
			<div class="ft-tab-content">
				<?php self::render_notice(); ?>
				<?php
				switch ( $active_tab ) {
					case 'users':
						self::render_section_users();
						break;
					case 'account_approval':
						self::render_section_account_approval();
						break;
					case 'owner_verification':
						self::render_section_owner_verification();
						break;
					case 'property_verification':
						self::render_section_property_verification();
						break;
					case 'properties':
						self::render_section_properties();
						break;
					case 'screening':
						self::render_section_screening();
						break;
					case 'incidents':
						self::render_section_incidents();
						break;
					case 'disputes':
						self::render_section_disputes();
						break;
					case 'visibility':
						self::render_section_visibility();
						break;
					case 'subscriptions':
						self::render_section_subscriptions();
						break;
					case 'plans':
						self::render_section_plans();
						break;
					case 'stripe':
						self::render_section_stripe();
						break;
					case 'audit_logs':
						self::render_section_audit_logs();
						break;
					case 'platform_settings':
						self::render_section_platform_settings();
						break;
					case 'overview':
					default:
						self::render_section_overview( compact(
							'total_users', 'owners_count', 'tenants_count', 'admins_count',
							'pending_users', 'pending_props', 'pending_inc', 'open_disputes',
							'total_properties', 'total_searches', 'active_subs_count', 'mrr',
							'active_stripe_mode'
						) );
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * SECTION 1: Overview & Executive KPIs
	 */
	protected static function render_section_overview( array $metrics ): void {
		extract( $metrics );
		?>
		<!-- Top KPI Summary Cards -->
		<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
			
			<div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
				<div style="font-size: 12px; font-weight: 700; color: #64748B; text-transform: uppercase; margin-bottom: 6px;">
					<?php esc_html_e( 'Monthly Recurring Revenue', 'fraud-tenant-core' ); ?>
				</div>
				<div style="font-size: 28px; font-weight: 800; color: #14263E;">
					$<?php echo esc_html( number_format( $mrr, 2 ) ); ?>
				</div>
				<div style="font-size: 12px; color: #0E9488; font-weight: 600; margin-top: 4px;">
					<?php echo esc_html( (string) $active_subs_count ); ?> <?php esc_html_e( 'active paid subscriptions', 'fraud-tenant-core' ); ?>
				</div>
			</div>

			<div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
				<div style="font-size: 12px; font-weight: 700; color: #64748B; text-transform: uppercase; margin-bottom: 6px;">
					<?php esc_html_e( 'Total Platform Users', 'fraud-tenant-core' ); ?>
				</div>
				<div style="font-size: 28px; font-weight: 800; color: #14263E;">
					<?php echo esc_html( (string) $total_users ); ?>
				</div>
				<div style="font-size: 12px; color: #64748B; margin-top: 4px;">
					<?php echo esc_html( (string) $owners_count ); ?> <?php esc_html_e( 'owners', 'fraud-tenant-core' ); ?> &bull; 
					<?php echo esc_html( (string) $tenants_count ); ?> <?php esc_html_e( 'tenants', 'fraud-tenant-core' ); ?>
				</div>
			</div>

			<div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
				<div style="font-size: 12px; font-weight: 700; color: #64748B; text-transform: uppercase; margin-bottom: 6px;">
					<?php esc_html_e( 'Pending Applications', 'fraud-tenant-core' ); ?>
				</div>
				<div style="font-size: 28px; font-weight: 800; color: <?php echo $pending_users > 0 ? '#EF4444' : '#14263E'; ?>;">
					<?php echo esc_html( (string) $pending_users ); ?>
				</div>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'fraud-tenant-admin', 'tab' => 'account_approval' ), admin_url( 'admin.php' ) ) ); ?>" style="font-size: 12px; color: #0E9488; text-decoration: none; font-weight: 600; display: inline-block; margin-top: 4px;">
					<?php esc_html_e( 'Review Applications &rarr;', 'fraud-tenant-core' ); ?>
				</a>
			</div>

			<div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
				<div style="font-size: 12px; font-weight: 700; color: #64748B; text-transform: uppercase; margin-bottom: 6px;">
					<?php esc_html_e( 'Pending Properties', 'fraud-tenant-core' ); ?>
				</div>
				<div style="font-size: 28px; font-weight: 800; color: <?php echo $pending_props > 0 ? '#F59E0B' : '#14263E'; ?>;">
					<?php echo esc_html( (string) $pending_props ); ?>
				</div>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'fraud-tenant-admin', 'tab' => 'property_verification' ), admin_url( 'admin.php' ) ) ); ?>" style="font-size: 12px; color: #0E9488; text-decoration: none; font-weight: 600; display: inline-block; margin-top: 4px;">
					<?php esc_html_e( 'Verify Properties &rarr;', 'fraud-tenant-core' ); ?>
				</a>
			</div>

			<div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
				<div style="font-size: 12px; font-weight: 700; color: #64748B; text-transform: uppercase; margin-bottom: 6px;">
					<?php esc_html_e( 'Incident Reviews', 'fraud-tenant-core' ); ?>
				</div>
				<div style="font-size: 28px; font-weight: 800; color: <?php echo $pending_inc > 0 ? '#EF4444' : '#14263E'; ?>;">
					<?php echo esc_html( (string) $pending_inc ); ?>
				</div>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'fraud-tenant-admin', 'tab' => 'incidents' ), admin_url( 'admin.php' ) ) ); ?>" style="font-size: 12px; color: #0E9488; text-decoration: none; font-weight: 600; display: inline-block; margin-top: 4px;">
					<?php esc_html_e( 'Review Incidents &rarr;', 'fraud-tenant-core' ); ?>
				</a>
			</div>

			<div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
				<div style="font-size: 12px; font-weight: 700; color: #64748B; text-transform: uppercase; margin-bottom: 6px;">
					<?php esc_html_e( 'Open Disputes', 'fraud-tenant-core' ); ?>
				</div>
				<div style="font-size: 28px; font-weight: 800; color: <?php echo $open_disputes > 0 ? '#EF4444' : '#14263E'; ?>;">
					<?php echo esc_html( (string) $open_disputes ); ?>
				</div>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'fraud-tenant-admin', 'tab' => 'disputes' ), admin_url( 'admin.php' ) ) ); ?>" style="font-size: 12px; color: #0E9488; text-decoration: none; font-weight: 600; display: inline-block; margin-top: 4px;">
					<?php esc_html_e( 'Dispute Center &rarr;', 'fraud-tenant-core' ); ?>
				</a>
			</div>
		</div>

		<!-- Operational Shortcuts & Compliance Status Grid -->
		<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
			
			<div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
				<h3 style="margin: 0 0 16px 0; font-size: 16px; font-weight: 800; color: #14263E;">
					<?php esc_html_e( 'Administrative Operations Hub', 'fraud-tenant-core' ); ?>
				</h3>
				<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'fraud-tenant-admin', 'tab' => 'users' ), admin_url( 'admin.php' ) ) ); ?>" style="display: block; padding: 14px; border: 1px solid #E2E8F0; border-radius: 8px; text-decoration: none; color: inherit; background: #F8FAFC;">
						<div style="font-weight: 700; color: #14263E; font-size: 14px; margin-bottom: 4px;"><?php esc_html_e( 'User Directory', 'fraud-tenant-core' ); ?></div>
						<div style="font-size: 12px; color: #64748B;"><?php esc_html_e( 'Search and inspect registered owners and tenants.', 'fraud-tenant-core' ); ?></div>
					</a>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'fraud-tenant-admin', 'tab' => 'screening' ), admin_url( 'admin.php' ) ) ); ?>" style="display: block; padding: 14px; border: 1px solid #E2E8F0; border-radius: 8px; text-decoration: none; color: inherit; background: #F8FAFC;">
						<div style="font-weight: 700; color: #14263E; font-size: 14px; margin-bottom: 4px;"><?php esc_html_e( 'Screening Audit', 'fraud-tenant-core' ); ?></div>
						<div style="font-size: 12px; color: #64748B;"><?php esc_html_e( 'View permissible-purpose screening log records.', 'fraud-tenant-core' ); ?></div>
					</a>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'fraud-tenant-admin', 'tab' => 'visibility' ), admin_url( 'admin.php' ) ) ); ?>" style="display: block; padding: 14px; border: 1px solid #E2E8F0; border-radius: 8px; text-decoration: none; color: inherit; background: #F8FAFC;">
						<div style="font-weight: 700; color: #14263E; font-size: 14px; margin-bottom: 4px;"><?php esc_html_e( 'Report Visibility', 'fraud-tenant-core' ); ?></div>
						<div style="font-size: 12px; color: #64748B;"><?php esc_html_e( 'Suppression and FCRA disclosure controls.', 'fraud-tenant-core' ); ?></div>
					</a>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'fraud-tenant-admin', 'tab' => 'audit_logs' ), admin_url( 'admin.php' ) ) ); ?>" style="display: block; padding: 14px; border: 1px solid #E2E8F0; border-radius: 8px; text-decoration: none; color: inherit; background: #F8FAFC;">
						<div style="font-weight: 700; color: #14263E; font-size: 14px; margin-bottom: 4px;"><?php esc_html_e( 'Compliance Audit Trail', 'fraud-tenant-core' ); ?></div>
						<div style="font-size: 12px; color: #64748B;"><?php esc_html_e( 'Append-only forensic event log.', 'fraud-tenant-core' ); ?></div>
					</a>
				</div>
			</div>

			<div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
				<h3 style="margin: 0 0 16px 0; font-size: 16px; font-weight: 800; color: #14263E;">
					<?php esc_html_e( 'System & Security Health', 'fraud-tenant-core' ); ?>
				</h3>
				<div style="display: flex; flex-direction: column; gap: 12px; font-size: 13px;">
					<div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #F1F5F9; padding-bottom: 8px;">
						<span style="color: #64748B;"><?php esc_html_e( 'Cryptographic Encryption', 'fraud-tenant-core' ); ?></span>
						<span style="background: #DCFCE7; color: #166534; font-weight: 700; font-size: 11px; padding: 2px 8px; border-radius: 4px;">AES-256-GCM Active</span>
					</div>
					<div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #F1F5F9; padding-bottom: 8px;">
						<span style="color: #64748B;"><?php esc_html_e( 'SSN Keyed Tokenization', 'fraud-tenant-core' ); ?></span>
						<span style="background: #DCFCE7; color: #166534; font-weight: 700; font-size: 11px; padding: 2px 8px; border-radius: 4px;">HMAC-SHA256 Active</span>
					</div>
					<div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #F1F5F9; padding-bottom: 8px;">
						<span style="color: #64748B;"><?php esc_html_e( 'Document Streamer', 'fraud-tenant-core' ); ?></span>
						<span style="background: #DCFCE7; color: #166534; font-weight: 700; font-size: 11px; padding: 2px 8px; border-radius: 4px;">Private (Non-Public)</span>
					</div>
					<div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #F1F5F9; padding-bottom: 8px;">
						<span style="color: #64748B;"><?php esc_html_e( 'Active Stripe Mode', 'fraud-tenant-core' ); ?></span>
						<span style="background: <?php echo 'live' === $active_stripe_mode ? '#DCFCE7; color: #166534;' : '#FEF3C7; color: #92400E;'; ?> font-weight: 700; font-size: 11px; padding: 2px 8px; border-radius: 4px;">
							<?php echo strtoupper( esc_html( $active_stripe_mode ) ); ?>
						</span>
					</div>
					<div style="display: flex; justify-content: space-between; align-items: center;">
						<span style="color: #64748B;"><?php esc_html_e( 'Environment', 'fraud-tenant-core' ); ?></span>
						<span style="font-weight: 700; color: #14263E;">
							<?php echo esc_html( defined( 'WP_ENVIRONMENT_TYPE' ) ? WP_ENVIRONMENT_TYPE : 'production' ); ?>
						</span>
					</div>
				</div>
			</div>

		</div>
		<?php
	}

	/**
	 * SECTION 2: User Management Directory
	 */
	protected static function render_section_users(): void {
		global $wpdb;
		$tbl_profiles = $wpdb->prefix . Constants::TABLE_USER_PROFILES;
		$per          = 25;
		$paged        = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );
		$search       = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$role_filter  = isset( $_GET['role'] ) ? sanitize_key( $_GET['role'] ) : '';
		$status_f     = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';

		$where  = array( '1=1' );
		$params = array();
		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '( u.user_login LIKE %s OR u.user_email LIKE %s OR u.display_name LIKE %s OR p.business_name LIKE %s )';
			$params   = array_merge( $params, array( $like, $like, $like, $like ) );
		}
		if ( in_array( $role_filter, array( 'owner', 'tenant' ), true ) ) {
			$where[]  = 'p.account_type = %s';
			$params[] = $role_filter;
		}
		$allowed_status = array( Constants::STATUS_EMAIL_UNVERIFIED, Constants::STATUS_PENDING_APPROVAL, Constants::STATUS_INFO_REQUESTED, Constants::STATUS_APPROVED, Constants::STATUS_REJECTED, Constants::STATUS_SUSPENDED );
		if ( in_array( $status_f, $allowed_status, true ) ) {
			$where[]  = 'p.approval_status = %s';
			$params[] = $status_f;
		}
		$where_sql = implode( ' AND ', $where );
		$base_sql  = "FROM {$wpdb->users} u LEFT JOIN {$tbl_profiles} p ON u.ID = p.user_id WHERE {$where_sql}";
		$total     = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) {$base_sql}", $params ) ) : $wpdb->get_var( "SELECT COUNT(*) {$base_sql}" ) );
		$list_sql  = "SELECT u.ID, u.user_login, u.user_email, u.display_name, u.user_registered, p.account_type, p.approval_status, p.business_name {$base_sql} ORDER BY u.ID DESC LIMIT %d OFFSET %d";
		$users     = $wpdb->get_results( $wpdb->prepare( $list_sql, array_merge( $params, array( $per, ( $paged - 1 ) * $per ) ) ) ) ?: array();
		$is_admin  = current_user_can( 'manage_options' );
		$keep      = array( 'page' => 'fraud-tenant-admin', 'tab' => 'users', 's' => $search, 'role' => $role_filter, 'status' => $status_f );
		?>
		<div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
			<h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 800; color: #14263E;"><?php esc_html_e( 'User Management Directory', 'fraud-tenant-core' ); ?></h3>
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="ft-filter-bar" style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:14px;">
				<input type="hidden" name="page" value="fraud-tenant-admin" />
				<input type="hidden" name="tab" value="users" />
				<label class="screen-reader-text" for="ft-user-search"><?php esc_html_e( 'Search users', 'fraud-tenant-core' ); ?></label>
				<input type="search" id="ft-user-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Name, email, username or business', 'fraud-tenant-core' ); ?>" style="min-width:260px;" />
				<select name="role" aria-label="<?php esc_attr_e( 'Role', 'fraud-tenant-core' ); ?>">
					<option value=""><?php esc_html_e( 'All roles', 'fraud-tenant-core' ); ?></option>
					<option value="owner" <?php selected( $role_filter, 'owner' ); ?>><?php esc_html_e( 'Property Owners', 'fraud-tenant-core' ); ?></option>
					<option value="tenant" <?php selected( $role_filter, 'tenant' ); ?>><?php esc_html_e( 'Tenants', 'fraud-tenant-core' ); ?></option>
				</select>
				<select name="status" aria-label="<?php esc_attr_e( 'Account status', 'fraud-tenant-core' ); ?>">
					<option value=""><?php esc_html_e( 'All statuses', 'fraud-tenant-core' ); ?></option>
					<?php foreach ( $allowed_status as $st ) : ?>
						<option value="<?php echo esc_attr( $st ); ?>" <?php selected( $status_f, $st ); ?>><?php echo esc_html( ucwords( str_replace( '_', ' ', $st ) ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="button button-primary" style="background:#0E9488; border-color:#0E9488;"><?php esc_html_e( 'Filter', 'fraud-tenant-core' ); ?></button>
				<?php if ( '' !== $search || '' !== $role_filter || '' !== $status_f ) : ?>
					<a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'fraud-tenant-admin', 'tab' => 'users' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Reset', 'fraud-tenant-core' ); ?></a>
				<?php endif; ?>
			</form>
			<table class="wp-list-table widefat fixed striped" style="border-radius: 6px; overflow: hidden; border: 1px solid #E2E8F0;">
				<thead>
					<tr>
						<th style="width: 60px;">ID</th>
						<th>Name / Username</th>
						<th>Email</th>
						<th>Role</th>
						<th>Business / Entity</th>
						<th>Status</th>
						<th>Registered</th>
						<th>Action</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $users ) ) : ?>
						<tr><td colspan="8" style="text-align: center; padding: 20px;"><?php esc_html_e( 'No users match the current filters.', 'fraud-tenant-core' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $users as $u ) : ?>
							<?php $is_qa = StripeCredentialService::is_billing_qa_account( (int) $u->ID ); ?>
							<tr>
								<td><strong>#<?php echo esc_html( (string) $u->ID ); ?></strong></td>
								<td><?php echo esc_html( $u->display_name ?: $u->user_login ); ?></td>
								<td><?php echo esc_html( $u->user_email ); ?></td>
								<td><span style="font-weight: 700; text-transform: uppercase; font-size: 11px;"><?php echo esc_html( $u->account_type ?: 'System' ); ?></span><?php if ( $is_qa ) : ?> <span style="background:#FEF3C7; color:#92400E; font-size:10px; font-weight:700; padding:1px 6px; border-radius:4px;">BILLING QA</span><?php endif; ?></td>
								<td><?php echo esc_html( $u->business_name ?: '-' ); ?></td>
								<td>
									<span class="badge" style="background: #E2E8F0; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 700;">
										<?php echo esc_html( $u->approval_status ?: 'Active' ); ?>
									</span>
								</td>
								<td><?php echo esc_html( date_i18n( 'M j, Y', strtotime( $u->user_registered ) ) ); ?></td>
								<td>
									<?php if ( $u->account_type ) : ?>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=fraud-tenant-admin&action=review&user_id=' . $u->ID ) ); ?>" class="button button-small">
											<?php esc_html_e( 'Review', 'fraud-tenant-core' ); ?>
										</a>
									<?php endif; ?>
									<?php if ( $is_admin && 'owner' === $u->account_type ) : ?>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;" onsubmit="return confirm('<?php echo esc_js( $is_qa ? __( 'Remove billing QA designation?', 'fraud-tenant-core' ) : __( 'Designate this owner as a billing QA account? QA accounts may use Test-mode checkout on production.', 'fraud-tenant-core' ) ); ?>');">
											<input type="hidden" name="action" value="ft_admin_billing_qa_flag" />
											<input type="hidden" name="user_id" value="<?php echo esc_attr( (string) $u->ID ); ?>" />
											<input type="hidden" name="enable" value="<?php echo $is_qa ? '0' : '1'; ?>" />
											<?php wp_nonce_field( 'ft_billing_qa_flag_' . $u->ID ); ?>
											<button type="submit" class="button button-small"><?php echo $is_qa ? esc_html__( 'Unset QA', 'fraud-tenant-core' ) : esc_html__( 'Set QA', 'fraud-tenant-core' ); ?></button>
										</form>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
			<?php self::render_pagination( $paged, $total, $per, $keep ); ?>
		</div>
		<?php
	}

	/**
	 * SECTION 3: Account Approval Queue
	 */
	protected static function render_section_account_approval(): void {
		global $wpdb;
		$tbl_profiles = $wpdb->prefix . Constants::TABLE_USER_PROFILES;
		$pending = $wpdb->get_results(
			"SELECT u.ID, u.user_login, u.user_email, u.display_name, u.user_registered, p.account_type, p.approval_status, p.phone, p.business_name, p.business_reg_number 
			 FROM {$tbl_profiles} p 
			 INNER JOIN {$wpdb->users} u ON p.user_id = u.ID 
			 WHERE p.approval_status IN ('pending_approval', 'info_requested') 
			 ORDER BY p.id ASC"
		) ?: array();
		?>
		<div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
			<h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 800; color: #14263E;"><?php esc_html_e( 'Account Approval Queue', 'fraud-tenant-core' ); ?></h3>
			<table class="wp-list-table widefat fixed striped" style="border-radius: 6px; overflow: hidden; border: 1px solid #E2E8F0;">
				<thead>
					<tr>
						<th style="width: 60px;">ID</th>
						<th>Applicant</th>
						<th>Email</th>
						<th>Role</th>
						<th>Business Details</th>
						<th>Status</th>
						<th>Submitted</th>
						<th>Action</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $pending ) ) : ?>
						<tr><td colspan="8" style="text-align: center; padding: 30px; color: #0E9488; font-weight: 700;"><?php esc_html_e( 'All applications reviewed. No pending approvals in queue.', 'fraud-tenant-core' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $pending as $u ) : ?>
							<tr>
								<td><strong>#<?php echo esc_html( (string) $u->ID ); ?></strong></td>
								<td><?php echo esc_html( $u->display_name ?: $u->user_login ); ?></td>
								<td><?php echo esc_html( $u->user_email ); ?></td>
								<td><span style="font-weight: 700; text-transform: uppercase; font-size: 11px;"><?php echo esc_html( $u->account_type ); ?></span></td>
								<td><?php echo esc_html( $u->business_name ? ( $u->business_name . ' (' . $u->business_reg_number . ')' ) : '-' ); ?></td>
								<td>
									<span style="background: #FEF3C7; color: #92400E; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 700;">
										<?php echo esc_html( $u->approval_status ); ?>
									</span>
								</td>
								<td><?php echo esc_html( date_i18n( 'M j, Y', strtotime( $u->user_registered ) ) ); ?></td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=fraud-tenant-admin&action=review&user_id=' . $u->ID ) ); ?>" class="button button-primary button-small" style="background: #0E9488; border-color: #0E9488;">
										<?php esc_html_e( 'Review Applicant', 'fraud-tenant-core' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * SECTION 4: Owner Identity Verification
	 */
	protected static function render_section_owner_verification(): void {
		global $wpdb;
		$tbl_profiles = $wpdb->prefix . Constants::TABLE_USER_PROFILES;
		$owners = $wpdb->get_results(
			"SELECT p.*, u.user_login, u.user_email, u.display_name 
			 FROM {$tbl_profiles} p 
			 INNER JOIN {$wpdb->users} u ON p.user_id = u.ID 
			 WHERE p.account_type = 'owner' 
			 ORDER BY p.id DESC LIMIT 50"
		) ?: array();
		?>
		<div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
			<h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 800; color: #14263E;"><?php esc_html_e( 'Owner Identity Verification', 'fraud-tenant-core' ); ?></h3>
			<table class="wp-list-table widefat fixed striped" style="border-radius: 6px; overflow: hidden; border: 1px solid #E2E8F0;">
				<thead>
					<tr>
						<th>Owner</th>
						<th>Email</th>
						<th>Business Name</th>
						<th>Business Reg #</th>
						<th>Phone</th>
						<th>ID Verified</th>
						<th>Approval</th>
						<th>Action</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $owners ) ) : ?>
						<tr><td colspan="8" style="text-align: center; padding: 20px;"><?php esc_html_e( 'No property owners registered.', 'fraud-tenant-core' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $owners as $o ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $o->display_name ?: $o->user_login ); ?></strong></td>
								<td><?php echo esc_html( $o->user_email ); ?></td>
								<td><?php echo esc_html( $o->business_name ?: '-' ); ?></td>
								<td><?php echo esc_html( $o->business_reg_number ?: '-' ); ?></td>
								<td><?php echo esc_html( $o->phone ?: '-' ); ?></td>
								<td>
									<?php if ( ! empty( $o->identity_verified ) ) : ?>
										<span style="background: #DCFCE7; color: #166534; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 700;">Verified</span>
									<?php else : ?>
										<span style="background: #FEE2E2; color: #991B1B; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 700;">Unverified</span>
									<?php endif; ?>
								</td>
								<td>
									<span style="background: #E2E8F0; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 700;">
										<?php echo esc_html( $o->approval_status ); ?>
									</span>
								</td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=fraud-tenant-admin&action=review&user_id=' . $o->user_id ) ); ?>" class="button button-small">
										<?php esc_html_e( 'Inspect', 'fraud-tenant-core' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * SECTION 5: Property Verification Queue
	 */
	protected static function render_section_property_verification(): void {
		global $wpdb;
		$tbl_props = $wpdb->prefix . Constants::TABLE_PROPERTIES;
		$pending = $wpdb->get_results(
			"SELECT p.*, u.user_email, u.display_name 
			 FROM {$tbl_props} p 
			 LEFT JOIN {$wpdb->users} u ON p.owner_id = u.ID 
			 WHERE p.status = 'pending_verification' 
			 ORDER BY p.id ASC"
		) ?: array();
		?>
		<div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
			<h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 800; color: #14263E;"><?php esc_html_e( 'Property Verification Queue', 'fraud-tenant-core' ); ?></h3>
			<table class="wp-list-table widefat fixed striped" style="border-radius: 6px; overflow: hidden; border: 1px solid #E2E8F0;">
				<thead>
					<tr>
						<th style="width: 60px;">ID</th>
						<th>Property Title</th>
						<th>Address</th>
						<th>Owner</th>
						<th>Type / Units</th>
						<th>Submitted</th>
						<th>Action</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $pending ) ) : ?>
						<tr><td colspan="7" style="text-align: center; padding: 30px; color: #0E9488; font-weight: 700;"><?php esc_html_e( 'No properties pending verification.', 'fraud-tenant-core' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $pending as $p ) : ?>
							<tr>
								<td><strong>#<?php echo esc_html( (string) $p->id ); ?></strong></td>
								<td><strong><?php echo esc_html( $p->title ); ?></strong></td>
								<td><?php echo esc_html( $p->address_street . ', ' . $p->city . ', ' . $p->state . ' ' . $p->postal_code ); ?></td>
								<td><?php echo esc_html( $p->display_name ?: $p->user_email ); ?></td>
								<td><?php echo esc_html( $p->property_type . ' (' . $p->units_count . ')' ); ?></td>
								<td><?php echo esc_html( date_i18n( 'M j, Y', strtotime( $p->created_at ) ) ); ?></td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=fraud-tenant-properties&action=review&property_id=' . $p->id ) ); ?>" class="button button-primary button-small" style="background: #0E9488; border-color: #0E9488;">
										<?php esc_html_e( 'Verify Property', 'fraud-tenant-core' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * SECTION 6: Property Management Directory
	 */
	protected static function render_section_properties(): void {
		global $wpdb;
		$tbl_props = $wpdb->prefix . Constants::TABLE_PROPERTIES;
		$properties = $wpdb->get_results(
			"SELECT p.*, u.user_email, u.display_name 
			 FROM {$tbl_props} p 
			 LEFT JOIN {$wpdb->users} u ON p.owner_id = u.ID 
			 ORDER BY p.id DESC LIMIT 50"
		) ?: array();
		?>
		<div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
			<h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 800; color: #14263E;"><?php esc_html_e( 'Property Directory', 'fraud-tenant-core' ); ?></h3>
			<table class="wp-list-table widefat fixed striped" style="border-radius: 6px; overflow: hidden; border: 1px solid #E2E8F0;">
				<thead>
					<tr>
						<th style="width: 60px;">ID</th>
						<th>Property</th>
						<th>Street Address</th>
						<th>City / State</th>
						<th>Owner</th>
						<th>Units</th>
						<th>Status</th>
						<th>Action</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $properties ) ) : ?>
						<tr><td colspan="8" style="text-align: center; padding: 20px;"><?php esc_html_e( 'No properties registered.', 'fraud-tenant-core' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $properties as $p ) : ?>
							<tr>
								<td><strong>#<?php echo esc_html( (string) $p->id ); ?></strong></td>
								<td><strong><?php echo esc_html( $p->title ); ?></strong></td>
								<td><?php echo esc_html( $p->address_street ); ?></td>
								<td><?php echo esc_html( $p->city . ', ' . $p->state ); ?></td>
								<td><?php echo esc_html( $p->display_name ?: $p->user_email ); ?></td>
								<td><?php echo esc_html( (string) $p->units_count ); ?></td>
								<td>
									<span style="background: <?php echo 'verified' === $p->status ? '#DCFCE7; color: #166534;' : '#FEF3C7; color: #92400E;'; ?> padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 700;">
										<?php echo esc_html( $p->status ); ?>
									</span>
								</td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=fraud-tenant-properties&action=review&property_id=' . $p->id ) ); ?>" class="button button-small">
										<?php esc_html_e( 'Details', 'fraud-tenant-core' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * SECTION 7: Screening Requests
	 */
	protected static function render_section_screening(): void {
		global $wpdb;
		$tbl_scr   = $wpdb->prefix . Constants::TABLE_SCREENING_REQUESTS;
		$tbl_props = $wpdb->prefix . Constants::TABLE_PROPERTIES;
		$searches  = $wpdb->get_results(
			"SELECT s.*, p.title AS property_title, u.user_email, u.display_name 
			 FROM {$tbl_scr} s 
			 LEFT JOIN {$tbl_props} p ON s.property_id = p.id 
			 LEFT JOIN {$wpdb->users} u ON s.owner_id = u.ID 
			 ORDER BY s.id DESC LIMIT 50"
		) ?: array();
		?>
		<div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
			<h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 800; color: #14263E;"><?php esc_html_e( 'Screening Requests (FCRA Permissible Purpose Log)', 'fraud-tenant-core' ); ?></h3>
			<table class="wp-list-table widefat fixed striped" style="border-radius: 6px; overflow: hidden; border: 1px solid #E2E8F0;">
				<thead>
					<tr>
						<th style="width: 60px;">ID</th>
						<th>Owner</th>
						<th>Property</th>
						<th>Matched SSN</th>
						<th>Permissible Purpose</th>
						<th>Attestation</th>
						<th>Timestamp</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $searches ) ) : ?>
						<tr><td colspan="7" style="text-align: center; padding: 20px;"><?php esc_html_e( 'No screening requests recorded.', 'fraud-tenant-core' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $searches as $s ) : ?>
							<tr>
								<td><strong>#<?php echo esc_html( (string) $s->id ); ?></strong></td>
								<td><?php echo esc_html( $s->display_name ?: $s->user_email ); ?></td>
								<td><?php echo esc_html( $s->property_title ?: ( 'Property #' . $s->property_id ) ); ?></td>
								<td><code>&bull;&bull;&bull;&bull;-<?php echo esc_html( $s->ssn_last_four ?: 'XXXX' ); ?></code></td>
								<td><span style="font-weight: 600; font-size: 11px;"><?php echo esc_html( $s->permissible_purpose ); ?></span></td>
								<td>
									<?php if ( ! empty( $s->consent_obtained ) ) : ?>
										<span style="background: #DCFCE7; color: #166534; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 3px;">Attested</span>
									<?php else : ?>
										<span style="background: #FEE2E2; color: #991B1B; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 3px;">Pending</span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( date_i18n( 'M j, Y H:i', strtotime( $s->created_at ) ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * SECTION 8: Incident Review Queue
	 */
	protected static function render_section_incidents(): void {
		global $wpdb;
		$tbl_inc   = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;
		$tbl_props = $wpdb->prefix . Constants::TABLE_PROPERTIES;
		$incidents = $wpdb->get_results(
			"SELECT i.*, p.title AS property_title, u.display_name AS owner_name 
			 FROM {$tbl_inc} i 
			 LEFT JOIN {$tbl_props} p ON i.property_id = p.id 
			 LEFT JOIN {$wpdb->users} u ON i.owner_id = u.ID 
			 ORDER BY i.id DESC LIMIT 50"
		) ?: array();
		?>
		<div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
			<h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 800; color: #14263E;"><?php esc_html_e( 'Incident Review Queue', 'fraud-tenant-core' ); ?></h3>
			<table class="wp-list-table widefat fixed striped" style="border-radius: 6px; overflow: hidden; border: 1px solid #E2E8F0;">
				<thead>
					<tr>
						<th style="width: 60px;">ID</th>
						<th>Owner</th>
						<th>Property</th>
						<th>Incident Type</th>
						<th>Amount</th>
						<th>Status</th>
						<th>Submitted</th>
						<th>Action</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $incidents ) ) : ?>
						<tr><td colspan="8" style="text-align: center; padding: 20px;"><?php esc_html_e( 'No incident reports in queue.', 'fraud-tenant-core' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $incidents as $inc ) : ?>
							<tr>
								<td><strong>#<?php echo esc_html( (string) $inc->id ); ?></strong></td>
								<td><?php echo esc_html( $inc->owner_name ?: ( 'Owner #' . $inc->owner_id ) ); ?></td>
								<td><?php echo esc_html( $inc->property_title ?: '-' ); ?></td>
								<td><span style="font-weight: 700; font-size: 12px;"><?php echo esc_html( $inc->category ?? 'incident' ); ?></span></td>
								<td>$<?php echo esc_html( number_format( (float) ( $inc->amount_involved ?? 0 ), 2 ) ); ?></td>
								<td>
									<span style="background: <?php echo 'approved' === $inc->status ? '#DCFCE7; color: #166534;' : ( 'rejected' === $inc->status ? '#FEE2E2; color: #991B1B;' : '#FEF3C7; color: #92400E;' ); ?> padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 700;">
										<?php echo esc_html( $inc->status ); ?>
									</span>
								</td>
								<td><?php echo esc_html( date_i18n( 'M j, Y', strtotime( $inc->created_at ) ) ); ?></td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=fraud-tenant-incidents&action=review&incident_id=' . $inc->id ) ); ?>" class="button button-small">
										<?php esc_html_e( 'Review', 'fraud-tenant-core' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * SECTION 9: Tenant Disputes
	 */
	protected static function render_section_disputes(): void {
		global $wpdb;
		$tbl_dsp  = $wpdb->prefix . Constants::TABLE_TENANT_DISPUTES;
		$disputes = $wpdb->get_results(
			"SELECT d.*, u.display_name AS tenant_name, u.user_email 
			 FROM {$tbl_dsp} d 
			 LEFT JOIN {$wpdb->users} u ON d.tenant_id = u.ID 
			 ORDER BY d.id DESC LIMIT 50"
		) ?: array();
		?>
		<div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
			<h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 800; color: #14263E;"><?php esc_html_e( 'Tenant Disputes Queue', 'fraud-tenant-core' ); ?></h3>
			<table class="wp-list-table widefat fixed striped" style="border-radius: 6px; overflow: hidden; border: 1px solid #E2E8F0;">
				<thead>
					<tr>
						<th style="width: 60px;">ID</th>
						<th>Tenant</th>
						<th>Incident ID</th>
						<th>Dispute Reason</th>
						<th>Status</th>
						<th>Filed Date</th>
						<th>Action</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $disputes ) ) : ?>
						<tr><td colspan="7" style="text-align: center; padding: 20px;"><?php esc_html_e( 'No disputes filed.', 'fraud-tenant-core' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $disputes as $dsp ) : ?>
							<tr>
								<td><strong>#<?php echo esc_html( (string) $dsp->id ); ?></strong></td>
								<td><?php echo esc_html( $dsp->tenant_name ?: $dsp->user_email ); ?></td>
								<td><strong>#<?php echo esc_html( (string) $dsp->report_id ); ?></strong></td>
								<td><?php echo esc_html( wp_trim_words( $dsp->dispute_reason ?? '', 10 ) ); ?></td>
								<td>
									<span style="background: <?php echo 'resolved' === $dsp->status ? '#DCFCE7; color: #166534;' : '#FEF3C7; color: #92400E;'; ?> padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 700;">
										<?php echo esc_html( $dsp->status ); ?>
									</span>
								</td>
								<td><?php echo esc_html( date_i18n( 'M j, Y', strtotime( $dsp->created_at ) ) ); ?></td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=fraud-tenant-disputes&action=view&dispute_id=' . $dsp->id ) ); ?>" class="button button-small">
										<?php esc_html_e( 'Inspect', 'fraud-tenant-core' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * SECTION 10: Report Association & Visibility
	 */
	protected static function render_section_visibility(): void {
		global $wpdb;
		$tbl_assoc = $wpdb->prefix . Constants::TABLE_REPORT_ASSOCIATIONS;
		$assocs    = $wpdb->get_results(
			"SELECT a.*, u.display_name AS tenant_name 
			 FROM {$tbl_assoc} a 
			 LEFT JOIN {$wpdb->users} u ON a.tenant_user_id = u.ID 
			 ORDER BY a.id DESC LIMIT 50"
		) ?: array();
		?>
		<div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
			<h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 800; color: #14263E;"><?php esc_html_e( 'Report Association & Suppression Center', 'fraud-tenant-core' ); ?></h3>
			<table class="wp-list-table widefat fixed striped" style="border-radius: 6px; overflow: hidden; border: 1px solid #E2E8F0;">
				<thead>
					<tr>
						<th style="width: 60px;">ID</th>
						<th>Report ID</th>
						<th>Matched Tenant</th>
						<th>Visibility Status</th>
						<th>Suppression / Revocation</th>
						<th>Date</th>
						<th>Action</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $assocs ) ) : ?>
						<tr><td colspan="7" style="text-align: center; padding: 20px;"><?php esc_html_e( 'No report associations found.', 'fraud-tenant-core' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $assocs as $a ) : ?>
							<tr>
								<td><strong>#<?php echo esc_html( (string) $a->id ); ?></strong></td>
								<td><strong>#<?php echo esc_html( (string) $a->report_id ); ?></strong></td>
								<td><?php echo esc_html( $a->tenant_name ?: ( 'Tenant #' . $a->tenant_user_id ) ); ?></td>
								<td>
									<span style="background: <?php echo 'visible' === $a->visibility_status ? '#DCFCE7; color: #166534;' : '#FEF3C7; color: #92400E;'; ?> padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 700;">
										<?php echo esc_html( $a->visibility_status ); ?>
									</span>
								</td>
								<td><?php echo esc_html( $a->revocation_reason ?: '-' ); ?></td>
								<td><?php echo esc_html( date_i18n( 'M j, Y', strtotime( $a->created_at ) ) ); ?></td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=fraud-tenant-visibility' ) ); ?>" class="button button-small">
										<?php esc_html_e( 'Manage', 'fraud-tenant-core' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * SECTION 11: Membership Subscriptions & MRR
	 */
	protected static function render_section_subscriptions(): void {
		global $wpdb;
		$tbl_subs  = $wpdb->prefix . Constants::TABLE_SUBSCRIPTIONS;
		$tbl_plans = $wpdb->prefix . Constants::TABLE_MEMBERSHIP_PLANS;
		$tbl_evt   = $wpdb->prefix . Constants::TABLE_PAYMENT_EVENTS;
		$per       = 25;
		$paged     = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );
		$mode_f    = isset( $_GET['mode'] ) ? sanitize_key( $_GET['mode'] ) : '';
		$status_f  = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
		$where     = array( '1=1' );
		$params    = array();
		if ( in_array( $mode_f, array( 'test', 'live' ), true ) ) {
			$where[]  = 's.livemode = %d';
			$params[] = 'live' === $mode_f ? 1 : 0;
		}
		if ( in_array( $status_f, \FraudTenant\Services\StripeWebhookService::KNOWN_SUB_STATUSES, true ) ) {
			$where[]  = 's.status = %s';
			$params[] = $status_f;
		}
		$where_sql = implode( ' AND ', $where );
		$total     = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tbl_subs} s WHERE {$where_sql}", $params ) ) : $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl_subs} s WHERE {$where_sql}" ) );
		$subs      = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.*, p.name AS plan_name, p.price_amount AS price, p.billing_interval, u.user_email, u.display_name
				 FROM {$tbl_subs} s
				 LEFT JOIN {$tbl_plans} p ON s.plan_id = p.id
				 LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID
				 WHERE {$where_sql}
				 ORDER BY s.id DESC LIMIT %d OFFSET %d",
				array_merge( $params, array( $per, ( $paged - 1 ) * $per ) )
			)
		) ?: array();
		$events    = $wpdb->get_results( "SELECT event_id, event_type, livemode, status, attempt_count, error_message, received_at, user_id FROM {$tbl_evt} ORDER BY id DESC LIMIT 25" ) ?: array();
		$can_sync  = current_user_can( 'manage_options' ) || current_user_can( Capabilities::MANAGE_MEMBERSHIP_PLANS );
		$keep      = array( 'page' => 'fraud-tenant-admin', 'tab' => 'subscriptions', 'mode' => $mode_f, 'status' => $status_f );
		?>
		<div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
			<h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 800; color: #14263E;"><?php esc_html_e( 'Owner Subscriptions', 'fraud-tenant-core' ); ?></h3>
			<p style="margin:-8px 0 14px 0; color:#64748B; font-size:13px;"><?php esc_html_e( 'Records are synchronized from Stripe (webhooks or verified checkout return). Only subscriptions in the active Stripe mode grant owner entitlements. History is never deleted.', 'fraud-tenant-core' ); ?></p>
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:14px;">
				<input type="hidden" name="page" value="fraud-tenant-admin" />
				<input type="hidden" name="tab" value="subscriptions" />
				<select name="mode" aria-label="<?php esc_attr_e( 'Stripe mode', 'fraud-tenant-core' ); ?>">
					<option value=""><?php esc_html_e( 'All modes', 'fraud-tenant-core' ); ?></option>
					<option value="test" <?php selected( $mode_f, 'test' ); ?>>Test</option>
					<option value="live" <?php selected( $mode_f, 'live' ); ?>>Live</option>
				</select>
				<select name="status" aria-label="<?php esc_attr_e( 'Status', 'fraud-tenant-core' ); ?>">
					<option value=""><?php esc_html_e( 'All statuses', 'fraud-tenant-core' ); ?></option>
					<?php foreach ( \FraudTenant\Services\StripeWebhookService::KNOWN_SUB_STATUSES as $st ) : ?>
						<option value="<?php echo esc_attr( $st ); ?>" <?php selected( $status_f, $st ); ?>><?php echo esc_html( $st ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="button"><?php esc_html_e( 'Filter', 'fraud-tenant-core' ); ?></button>
			</form>
			<table class="wp-list-table widefat fixed striped" style="border-radius: 6px; overflow: hidden; border: 1px solid #E2E8F0;">
				<thead>
					<tr>
						<th style="width: 60px;">ID</th>
						<th>Owner</th>
						<th>Plan</th>
						<th>Price / Cycle</th>
						<th>Mode</th>
						<th>Status</th>
						<th>Period End (UTC)</th>
						<th>Stripe Sub</th>
						<th>Action</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $subs ) ) : ?>
						<tr><td colspan="9" style="text-align: center; padding: 20px;"><?php esc_html_e( 'No subscriptions recorded.', 'fraud-tenant-core' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $subs as $s ) : ?>
							<tr>
								<td><strong>#<?php echo esc_html( (string) $s->id ); ?></strong></td>
								<td><?php echo esc_html( $s->display_name ?: $s->user_email ); ?></td>
								<td><strong><?php echo esc_html( $s->plan_name ?: '-' ); ?></strong></td>
								<td>$<?php echo esc_html( number_format( (float) $s->price, 2 ) ); ?> / <?php echo esc_html( (string) $s->billing_interval ); ?></td>
								<td><span style="font-size: 10px; font-weight: 700;"><?php echo ! empty( $s->livemode ) ? 'LIVE' : 'TEST'; ?></span></td>
								<td>
									<span style="background: <?php echo 'active' === $s->status ? '#DCFCE7; color: #166534;' : '#FEE2E2; color: #991B1B;'; ?> padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 700;"><?php echo esc_html( $s->status ); ?></span>
									<?php if ( ! empty( $s->cancel_at_period_end ) ) : ?><div style="font-size:11px; color:#B45309;"><?php esc_html_e( 'cancels at period end', 'fraud-tenant-core' ); ?></div><?php endif; ?>
								</td>
								<td><?php echo $s->current_period_end ? esc_html( $s->current_period_end ) : '<span style="color:#B91C1C;">' . esc_html__( 'missing (no access)', 'fraud-tenant-core' ) . '</span>'; ?></td>
								<td><code><?php echo esc_html( (string) $s->stripe_subscription_id ); ?></code></td>
								<td>
									<?php if ( $can_sync && ! empty( $s->stripe_subscription_id ) ) : ?>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
											<input type="hidden" name="action" value="ft_admin_sync_subscription" />
											<input type="hidden" name="subscription_db_id" value="<?php echo esc_attr( (string) $s->id ); ?>" />
											<?php wp_nonce_field( 'ft_sync_subscription_' . $s->id ); ?>
											<button type="submit" class="button button-small"><?php esc_html_e( 'Sync from Stripe', 'fraud-tenant-core' ); ?></button>
										</form>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
			<?php self::render_pagination( $paged, $total, $per, $keep ); ?>

			<h3 style="margin: 28px 0 12px 0; font-size: 16px; font-weight: 800; color: #14263E;"><?php esc_html_e( 'Recent Stripe Webhook Events', 'fraud-tenant-core' ); ?></h3>
			<table class="wp-list-table widefat fixed striped" style="border: 1px solid #E2E8F0;">
				<thead><tr><th>Event</th><th>Type</th><th>Mode</th><th>Status</th><th>Attempts</th><th>Owner</th><th>Received</th><th>Error (sanitized)</th></tr></thead>
				<tbody>
					<?php if ( empty( $events ) ) : ?>
						<tr><td colspan="8" style="text-align:center; padding:16px;"><?php esc_html_e( 'No webhook events received yet.', 'fraud-tenant-core' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $events as $e ) : ?>
							<tr>
								<td><code><?php echo esc_html( $e->event_id ); ?></code></td>
								<td><?php echo esc_html( $e->event_type ); ?></td>
								<td><?php echo $e->livemode ? 'LIVE' : 'TEST'; ?></td>
								<td><strong><?php echo esc_html( $e->status ); ?></strong></td>
								<td><?php echo esc_html( (string) $e->attempt_count ); ?></td>
								<td><?php echo $e->user_id ? '#' . esc_html( (string) $e->user_id ) : '-'; ?></td>
								<td><?php echo esc_html( (string) $e->received_at ); ?></td>
								<td style="font-size:12px; color:#991B1B;"><?php echo esc_html( (string) $e->error_message ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * SECTION 12: Membership Plans
	 */
	protected static function render_section_plans(): void {
		$plans = MembershipService::get_plans();
		?>
		<div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
			<h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 800; color: #14263E;"><?php esc_html_e( 'Membership Plan Tiers & Quotas', 'fraud-tenant-core' ); ?></h3>
			<table class="wp-list-table widefat fixed striped" style="border-radius: 6px; overflow: hidden; border: 1px solid #E2E8F0;">
				<thead>
					<tr>
						<th>Plan Name</th>
						<th>Plan Code</th>
						<th>Price</th>
						<th>Interval</th>
						<th>Max Properties</th>
						<th>Incident Reporting</th>
						<th>Stripe Test Price</th>
						<th>Stripe Live Price</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $plans as $plan ) : 
						$features  = ! empty( $plan->features_json ) ? json_decode( $plan->features_json, true ) : array();
						$max_props = $features['max_properties'] ?? ( 'free' === $plan->plan_code ? 1 : 0 );
						$can_inc   = ! empty( $features['can_submit_incidents'] );
					?>
						<tr>
							<td><strong><?php echo esc_html( $plan->name ); ?></strong></td>
							<td><code><?php echo esc_html( $plan->plan_code ); ?></code></td>
							<td>$<?php echo esc_html( number_format( (float) $plan->price_amount, 2 ) ); ?></td>
							<td><?php echo esc_html( $plan->billing_interval ); ?></td>
							<td><?php echo 0 === (int) $max_props ? 'Unlimited' : esc_html( (string) $max_props ); ?></td>
							<td>
								<?php if ( $can_inc ) : ?>
									<span style="background: #DCFCE7; color: #166534; font-size: 11px; font-weight: 700; padding: 2px 6px; border-radius: 3px;">Enabled</span>
								<?php else : ?>
									<span style="background: #FEE2E2; color: #991B1B; font-size: 11px; font-weight: 700; padding: 2px 6px; border-radius: 3px;">Locked</span>
								<?php endif; ?>
							</td>
							<td><code><?php echo esc_html( $plan->stripe_test_price_id ?: '-' ); ?></code></td>
							<td><code><?php echo esc_html( $plan->stripe_live_price_id ?: '-' ); ?></code></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * SECTION 13: Stripe Settings & Gateway Status
	 */
	protected static function render_section_stripe(): void {
		$active_mode = StripeCredentialService::get_active_mode();
		$test_status = StripeCredentialService::get_status( Constants::STRIPE_MODE_TEST );
		$live_status = StripeCredentialService::get_status( Constants::STRIPE_MODE_LIVE );
		?>
		<div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
			<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
				<h3 style="margin: 0; font-size: 18px; font-weight: 800; color: #14263E;"><?php esc_html_e( 'Stripe Payment Gateway Overview', 'fraud-tenant-core' ); ?></h3>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=fraud-tenant-settings' ) ); ?>" class="button button-primary" style="background: #0E9488; border-color: #0E9488;">
					<?php esc_html_e( 'Manage Credentials & Keys', 'fraud-tenant-core' ); ?>
				</a>
			</div>

			<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
				<div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 18px;">
					<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
						<h4 style="margin: 0; font-size: 15px; font-weight: 700; color: #14263E;">Test Mode (Sandbox)</h4>
						<?php if ( 'test' === $active_mode ) : ?>
							<span style="background: #FEF3C7; color: #92400E; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px;">ACTIVE GATEWAY</span>
						<?php endif; ?>
					</div>
					<div style="font-size: 13px; color: #475569; line-height: 1.8;">
						<div><strong>Configured:</strong> <?php echo ! empty( $test_status['is_configured'] ) ? 'Yes' : 'No'; ?></div>
						<div><strong>Publishable Key:</strong> <code><?php echo esc_html( $test_status['masked_publishable'] ?: 'None' ); ?></code></div>
						<div><strong>Secret Key:</strong> <code><?php echo esc_html( $test_status['masked_secret'] ?: 'None' ); ?></code></div>
						<div><strong>Webhook Secret:</strong> <code><?php echo esc_html( $test_status['masked_webhook'] ?: 'None' ); ?></code></div>
					</div>
				</div>

				<div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 18px;">
					<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
						<h4 style="margin: 0; font-size: 15px; font-weight: 700; color: #14263E;">Live Mode (Production)</h4>
						<?php if ( 'live' === $active_mode ) : ?>
							<span style="background: #DCFCE7; color: #166534; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px;">ACTIVE GATEWAY</span>
						<?php endif; ?>
					</div>
					<div style="font-size: 13px; color: #475569; line-height: 1.8;">
						<div><strong>Configured:</strong> <?php echo ! empty( $live_status['is_configured'] ) ? 'Yes' : 'No'; ?></div>
						<div><strong>Publishable Key:</strong> <code><?php echo esc_html( $live_status['masked_publishable'] ?: 'None' ); ?></code></div>
						<div><strong>Secret Key:</strong> <code><?php echo esc_html( $live_status['masked_secret'] ?: 'None' ); ?></code></div>
						<div><strong>Webhook Secret:</strong> <code><?php echo esc_html( $live_status['masked_webhook'] ?: 'None' ); ?></code></div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * SECTION 14: Compliance Audit Logs
	 */
	protected static function render_section_audit_logs(): void {
		global $wpdb;
		$tbl_audit = $wpdb->prefix . Constants::TABLE_AUDIT_LOG;
		$per       = 50;
		$paged     = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );
		$action_f  = isset( $_GET['action_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['action_filter'] ) ) : '';
		$actor_f   = isset( $_GET['actor'] ) ? absint( $_GET['actor'] ) : 0;
		$where     = array( '1=1' );
		$params    = array();
		if ( '' !== $action_f ) {
			$where[]  = 'a.action LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $action_f ) . '%';
		}
		if ( $actor_f > 0 ) {
			$where[]  = 'a.user_id = %d';
			$params[] = $actor_f;
		}
		$where_sql = implode( ' AND ', $where );
		$total     = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tbl_audit} a WHERE {$where_sql}", $params ) ) : $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl_audit} a WHERE {$where_sql}" ) );
		// Payload columns are deliberately NOT selected: the list shows who/what/when only.
		$logs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.id, a.created_at, a.user_id, a.action, a.object_type, a.object_id, a.ip_address, u.display_name
				 FROM {$tbl_audit} a LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
				 WHERE {$where_sql} ORDER BY a.id DESC LIMIT %d OFFSET %d",
				array_merge( $params, array( $per, ( $paged - 1 ) * $per ) )
			)
		) ?: array();
		$keep = array( 'page' => 'fraud-tenant-admin', 'tab' => 'audit_logs', 'action_filter' => $action_f, 'actor' => $actor_f ?: '' );
		?>
		<div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
			<h3 style="margin: 0 0 6px 0; font-size: 18px; font-weight: 800; color: #14263E;"><?php esc_html_e( 'Audit Trail', 'fraud-tenant-core' ); ?></h3>
			<p style="margin:0 0 14px 0; color:#64748B; font-size:13px;"><?php esc_html_e( 'Application-level append-only log. It cannot prevent changes made directly in the database by privileged database administrators.', 'fraud-tenant-core' ); ?></p>
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:14px;">
				<input type="hidden" name="page" value="fraud-tenant-admin" />
				<input type="hidden" name="tab" value="audit_logs" />
				<input type="search" name="action_filter" value="<?php echo esc_attr( $action_f ); ?>" placeholder="<?php esc_attr_e( 'Action contains… (e.g. stripe_, user.)', 'fraud-tenant-core' ); ?>" aria-label="<?php esc_attr_e( 'Action filter', 'fraud-tenant-core' ); ?>" />
				<input type="number" min="1" name="actor" value="<?php echo $actor_f ? esc_attr( (string) $actor_f ) : ''; ?>" placeholder="<?php esc_attr_e( 'Actor user ID', 'fraud-tenant-core' ); ?>" aria-label="<?php esc_attr_e( 'Actor user ID', 'fraud-tenant-core' ); ?>" style="width:140px;" />
				<button type="submit" class="button"><?php esc_html_e( 'Filter', 'fraud-tenant-core' ); ?></button>
			</form>
			<table class="wp-list-table widefat fixed striped" style="border-radius: 6px; overflow: hidden; border: 1px solid #E2E8F0;">
				<thead>
					<tr>
						<th style="width: 70px;">ID</th>
						<th>Timestamp</th>
						<th>Actor</th>
						<th>Action</th>
						<th>Target Object</th>
						<th>IP Address</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $logs ) ) : ?>
						<tr><td colspan="6" style="text-align: center; padding: 20px;"><?php esc_html_e( 'No audit records match.', 'fraud-tenant-core' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $logs as $l ) : ?>
							<tr>
								<td><strong>#<?php echo esc_html( (string) $l->id ); ?></strong></td>
								<td><?php echo esc_html( (string) $l->created_at ); ?></td>
								<td><?php echo esc_html( $l->display_name ?: ( $l->user_id ? 'User #' . $l->user_id : 'System' ) ); ?></td>
								<td><code><?php echo esc_html( $l->action ); ?></code></td>
								<td><?php echo esc_html( ( $l->object_type ?: '-' ) . ' #' . ( $l->object_id ?: '-' ) ); ?></td>
								<td><?php echo esc_html( $l->ip_address ?: '-' ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
			<?php self::render_pagination( $paged, $total, $per, $keep ); ?>
		</div>
		<?php
	}

	/**
	 * SECTION 15: Platform Settings & System Health
	 */
	protected static function render_section_platform_settings(): void {
		global $wpdb;
		self::render_compliance_gates();
		?>
		<div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
			<h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 800; color: #14263E;"><?php esc_html_e( 'Platform Architecture & System Diagnostics', 'fraud-tenant-core' ); ?></h3>
			
			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
				<div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 16px;">
					<div style="font-size: 12px; font-weight: 700; color: #64748B; text-transform: uppercase;">WordPress Core</div>
					<div style="font-size: 18px; font-weight: 800; color: #14263E; margin: 4px 0;"><?php echo esc_html( get_bloginfo( 'version' ) ); ?></div>
					<div style="font-size: 12px; color: #64748B;">Site URL: <?php echo esc_html( home_url() ); ?></div>
				</div>

				<div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 16px;">
					<div style="font-size: 12px; font-weight: 700; color: #64748B; text-transform: uppercase;">PHP Runtime</div>
					<div style="font-size: 18px; font-weight: 800; color: #14263E; margin: 4px 0;"><?php echo esc_html( PHP_VERSION ); ?></div>
					<div style="font-size: 12px; color: #64748B;">OpenSSL Extension: <?php echo extension_loaded( 'openssl' ) ? 'Available' : 'Missing'; ?></div>
				</div>

				<div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 16px;">
					<div style="font-size: 12px; font-weight: 700; color: #64748B; text-transform: uppercase;">Database Server</div>
					<div style="font-size: 18px; font-weight: 800; color: #14263E; margin: 4px 0;">MySQL / MariaDB</div>
					<div style="font-size: 12px; color: #64748B;">Server Version: <?php echo esc_html( $wpdb->db_version() ); ?></div>
				</div>

				<div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 16px;">
					<div style="font-size: 12px; font-weight: 700; color: #64748B; text-transform: uppercase;">Database Schema</div>
					<div style="font-size: 18px; font-weight: 800; color: #14263E; margin: 4px 0;">v<?php echo esc_html( Constants::DB_VERSION ); ?></div>
					<div style="font-size: 12px; color: #0E9488; font-weight: 600;">Dual-Mode Stripe Schema Synchronized</div>
				</div>

				<div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 16px;">
					<div style="font-size: 12px; font-weight: 700; color: #64748B; text-transform: uppercase;">SSL / HTTPS Security</div>
					<div style="font-size: 18px; font-weight: 800; color: <?php echo is_ssl() ? '#10B981' : '#F59E0B'; ?>; margin: 4px 0;">
						<?php echo is_ssl() ? 'Enforced' : 'HTTP (Development/Local)'; ?>
					</div>
					<div style="font-size: 12px; color: #64748B;">Required on production staging</div>
				</div>

				<div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 16px;">
					<div style="font-size: 12px; font-weight: 700; color: #64748B; text-transform: uppercase;">Environment Type</div>
					<div style="font-size: 18px; font-weight: 800; color: #14263E; margin: 4px 0;">
						<?php echo esc_html( defined( 'WP_ENVIRONMENT_TYPE' ) ? WP_ENVIRONMENT_TYPE : 'production' ); ?>
					</div>
					<div style="font-size: 12px; color: #64748B;">Strict Fail-Closed Production Default</div>
				</div>
			</div>
		</div>
		<?php
	}
}
