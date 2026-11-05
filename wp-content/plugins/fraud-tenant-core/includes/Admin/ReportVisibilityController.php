<?php
/**
 * Admin Report Visibility & Tenant Association Controller.
 *
 * Provides administrative screens to associate incident reports with verified
 * tenant accounts, approve visibility, revoke visibility, and inspect disclosure history.
 *
 * @package FraudTenant\Admin
 */

namespace FraudTenant\Admin;

use FraudTenant\Services\IncidentService;
use FraudTenant\Services\ReportVisibilityService;
use FraudTenant\Utils\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ReportVisibilityController {

	/**
	 * Main controller entry point.
	 */
	public static function render(): void {
		$admin_id = get_current_user_id();

		// 1. BLK-08 Security Gate
		if ( ! \FraudTenant\Services\ComplianceService::is_data_gate_open() ) {
			self::render_locked();
			return;
		}

		// 2. Authorization Check
		if ( ! ReportVisibilityService::can_manage_visibility( $admin_id ) ) {
			wp_die( esc_html__( 'Unauthorized access. You do not have permission to manage report visibility.', 'fraud-tenant-core' ), 403 );
		}

		$report_id = isset( $_GET['report_id'] ) ? (int) $_GET['report_id'] : 0;
		if ( $report_id > 0 ) {
			self::render_detail( $report_id );
		} else {
			self::render_list();
		}
	}

	/**
	 * Render locked state banner if BLK-08 privacy gate is active.
	 */
	protected static function render_locked(): void {
		?>
		<div class="wrap ft-admin-wrap" style="max-width: 960px; margin: 30px auto;">
			<div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; padding: 40px; text-align: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
				<div style="width: 64px; height: 64px; border-radius: 50%; background: #FEE2E2; color: #DC2626; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
					<span class="dashicons dashicons-lock" style="font-size: 32px; width: 32px; height: 32px;"></span>
				</div>
				<h1 style="color: #14263E; font-size: 24px; font-weight: 700; margin-bottom: 12px;">
					<?php esc_html_e( 'Report Visibility Locked — BLK-08 Privacy Gate Active', 'fraud-tenant-core' ); ?>
				</h1>
				<p style="color: #64748B; font-size: 15px; max-width: 640px; margin: 0 auto 24px; line-height: 1.6;">
					<?php esc_html_e( 'Administrative tenant association and disclosure management are locked in production mode. Tenant records remain fail-closed until application-level encryption at rest (BLK-08) is formally approved and implemented.', 'fraud-tenant-core' ); ?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Report Visibility Queue.
	 */
	protected static function render_list(): void {
		$admin_id       = get_current_user_id();
		$current_status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : 'all';
		$search_query   = isset( $_GET['s'] ) ? sanitize_text_field( trim( $_GET['s'] ) ) : '';
		$paged          = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

		$queue_data = ReportVisibilityService::get_visibility_queue(
			array(
				'status'   => $current_status,
				'search'   => $search_query,
				'page'     => $paged,
				'per_page' => 20,
			),
			$admin_id
		);

		$items         = $queue_data['items'] ?? array();
		$status_counts = $queue_data['status_counts'] ?? array();
		$total_items   = $queue_data['total_items'] ?? 0;
		$total_pages   = $queue_data['total_pages'] ?? 1;

		$base_url = admin_url( 'admin.php?page=fraud-tenant-visibility' );
		?>
		<div class="wrap ft-admin-wrap" style="max-width: 1240px; margin: 20px auto 40px;">
			<h1 class="wp-heading-inline" style="color: #14263E; font-size: 24px; font-weight: 700; margin-bottom: 6px;">
				<span class="dashicons dashicons-visibility" style="font-size:28px;width:28px;height:28px;vertical-align:middle;color:#0E9488;margin-right:6px;"></span>
				<?php esc_html_e( 'Report Disclosure & Tenant Visibility Queue', 'fraud-tenant-core' ); ?>
			</h1>
			<p class="description" style="color: #64748B; font-size: 14px; margin-bottom: 16px;">
				<?php esc_html_e( 'Manually associate approved incident reports with verified tenant accounts and approve or revoke tenant-facing visibility.', 'fraud-tenant-core' ); ?>
			</p>
			<hr class="wp-header-end">

			<?php self::render_flash_notices(); ?>

			<!-- Status Filter Tabs -->
			<ul class="subsubsub" style="margin: 14px 0 18px; font-size: 13px;">
				<li class="all">
					<a href="<?php echo esc_url( remove_query_arg( array( 'status', 'paged' ), $base_url ) ); ?>" class="<?php echo ( empty( $current_status ) || 'all' === $current_status ) ? 'current' : ''; ?>">
						<?php esc_html_e( 'All Reports', 'fraud-tenant-core' ); ?> <span class="count">(<?php echo (int) ( $status_counts['all'] ?? 0 ); ?>)</span>
					</a> |
				</li>
				<li class="unassociated">
					<a href="<?php echo esc_url( add_query_arg( array( 'status' => 'unassociated' ), $base_url ) ); ?>" class="<?php echo 'unassociated' === $current_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Unassociated', 'fraud-tenant-core' ); ?> <span class="count">(<?php echo (int) ( $status_counts['unassociated'] ?? 0 ); ?>)</span>
					</a> |
				</li>
				<li class="associated">
					<a href="<?php echo esc_url( add_query_arg( array( 'status' => 'associated' ), $base_url ) ); ?>" class="<?php echo 'associated' === $current_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Associated', 'fraud-tenant-core' ); ?> <span class="count">(<?php echo (int) ( $status_counts['associated'] ?? 0 ); ?>)</span>
					</a> |
				</li>
				<li class="awaiting_visibility">
					<a href="<?php echo esc_url( add_query_arg( array( 'status' => 'awaiting_visibility' ), $base_url ) ); ?>" class="<?php echo 'awaiting_visibility' === $current_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Awaiting Visibility', 'fraud-tenant-core' ); ?> <span class="count">(<?php echo (int) ( $status_counts['awaiting_visibility'] ?? 0 ); ?>)</span>
					</a> |
				</li>
				<li class="tenant_visible">
					<a href="<?php echo esc_url( add_query_arg( array( 'status' => 'tenant_visible' ), $base_url ) ); ?>" class="<?php echo 'tenant_visible' === $current_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Tenant-Visible', 'fraud-tenant-core' ); ?> <span class="count">(<?php echo (int) ( $status_counts['tenant_visible'] ?? 0 ); ?>)</span>
					</a> |
				</li>
				<li class="visibility_revoked">
					<a href="<?php echo esc_url( add_query_arg( array( 'status' => 'visibility_revoked' ), $base_url ) ); ?>" class="<?php echo 'visibility_revoked' === $current_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Visibility Revoked', 'fraud-tenant-core' ); ?> <span class="count">(<?php echo (int) ( $status_counts['visibility_revoked'] ?? 0 ); ?>)</span>
					</a>
				</li>
			</ul>

			<!-- Search Form -->
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="float: right; margin-bottom: 12px;">
				<input type="hidden" name="page" value="fraud-tenant-visibility" />
				<?php if ( ! empty( $current_status ) && 'all' !== $current_status ) : ?>
					<input type="hidden" name="status" value="<?php echo esc_attr( $current_status ); ?>" />
				<?php endif; ?>
				<p class="search-box" style="margin: 0;">
					<label class="screen-reader-text" for="report-search-input"><?php esc_html_e( 'Search Reports:', 'fraud-tenant-core' ); ?></label>
					<input type="search" id="report-search-input" name="s" value="<?php echo esc_attr( $search_query ); ?>" placeholder="<?php esc_attr_e( 'Reference, Address, Tenant...', 'fraud-tenant-core' ); ?>" />
					<input type="submit" id="search-submit" class="button" value="<?php esc_attr_e( 'Search', 'fraud-tenant-core' ); ?>" />
				</p>
			</form>
			<div class="clear"></div>

			<!-- Report Items Table -->
			<table class="wp-list-table widefat fixed striped" style="border: 1px solid #E2E8F0; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-top: 8px;">
				<thead>
					<tr style="background: #14263E; color: #FFFFFF;">
						<th style="color: #FFFFFF; font-weight: 600; width: 130px;"><?php esc_html_e( 'Reference', 'fraud-tenant-core' ); ?></th>
						<th style="color: #FFFFFF; font-weight: 600; width: 220px;"><?php esc_html_e( 'Property Address', 'fraud-tenant-core' ); ?></th>
						<th style="color: #FFFFFF; font-weight: 600; width: 160px;"><?php esc_html_e( 'Category & Date', 'fraud-tenant-core' ); ?></th>
						<th style="color: #FFFFFF; font-weight: 600; width: 110px;"><?php esc_html_e( 'Amount', 'fraud-tenant-core' ); ?></th>
						<th style="color: #FFFFFF; font-weight: 600; width: 120px;"><?php esc_html_e( 'Review Status', 'fraud-tenant-core' ); ?></th>
						<th style="color: #FFFFFF; font-weight: 600; width: 200px;"><?php esc_html_e( 'Associated Tenant', 'fraud-tenant-core' ); ?></th>
						<th style="color: #FFFFFF; font-weight: 600; width: 150px;"><?php esc_html_e( 'Visibility State', 'fraud-tenant-core' ); ?></th>
						<th style="color: #FFFFFF; font-weight: 600; width: 130px; text-align: center;"><?php esc_html_e( 'Action', 'fraud-tenant-core' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $items ) ) : ?>
						<tr>
							<td colspan="8" style="text-align: center; padding: 36px 16px; color: #64748B;">
								<span class="dashicons dashicons-info" style="font-size: 32px; width: 32px; height: 32px; display: block; margin: 0 auto 8px; color: #94A3B8;"></span>
								<strong style="font-size: 15px; color: #334155;"><?php esc_html_e( 'No Incident Reports Found', 'fraud-tenant-core' ); ?></strong>
								<p style="margin: 4px 0 0;"><?php esc_html_e( 'There are no incident records matching the selected queue filter.', 'fraud-tenant-core' ); ?></p>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $items as $item ) : ?>
							<?php
							// Badge styles
							$badge_style = 'background: #F1F5F9; color: #475569;';
							if ( 'visible' === $item->visibility_badge ) {
								$badge_style = 'background: #D1FAE5; color: #065F46; border: 1px solid #A7F3D0;';
							} elseif ( 'hidden' === $item->visibility_badge ) {
								$badge_style = 'background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A;';
							} elseif ( 'revoked' === $item->visibility_badge ) {
								$badge_style = 'background: #FEE2E2; color: #991B1B; border: 1px solid #FECACA;';
							}

							$review_badge_style = 'background: #F1F5F9; color: #475569;';
							if ( 'approved' === $item->status ) {
								$review_badge_style = 'background: #DCFCE7; color: #166534; font-weight: 600;';
							} elseif ( 'rejected' === $item->status ) {
								$review_badge_style = 'background: #FEE2E2; color: #991B1B;';
							} elseif ( 'needs_clarification' === $item->status ) {
								$review_badge_style = 'background: #FEF3C7; color: #92400E;';
							}
							?>
							<tr>
								<td>
									<strong style="color: #14263E; font-size: 13px; font-family: monospace;"><?php echo esc_html( $item->reference ); ?></strong>
								</td>
								<td>
									<strong><?php echo esc_html( $item->property_title ?: __( 'Property #' . $item->property_id, 'fraud-tenant-core' ) ); ?></strong>
									<div style="color: #64748B; font-size: 12px; margin-top: 2px;"><?php echo esc_html( $item->property_address ); ?></div>
								</td>
								<td>
									<span style="font-weight: 500; color: #1E293B;"><?php echo esc_html( $item->category_label ); ?></span>
									<div style="color: #64748B; font-size: 12px;"><?php echo esc_html( $item->incident_date ); ?></div>
								</td>
								<td>
									<span style="font-weight: 600; color: #14263E;"><?php echo esc_html( $item->amount_formatted ); ?></span>
								</td>
								<td>
									<span style="display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 12px; <?php echo esc_attr( $review_badge_style ); ?>">
										<?php echo esc_html( ucwords( str_replace( '_', ' ', $item->status ) ) ); ?>
									</span>
								</td>
								<td>
									<?php if ( ! empty( $item->tenant_name ) ) : ?>
										<strong style="color: #0E9488;"><?php echo esc_html( $item->tenant_name ); ?></strong>
										<div style="color: #64748B; font-size: 11px;"><?php echo esc_html( $item->tenant_email ); ?></div>
									<?php else : ?>
										<span style="color: #94A3B8; font-style: italic;"><?php esc_html_e( 'Unassociated', 'fraud-tenant-core' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<span style="display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; <?php echo esc_attr( $badge_style ); ?>">
										<?php echo esc_html( $item->visibility_label ); ?>
									</span>
								</td>
								<td style="text-align: center;">
									<a href="<?php echo esc_url( add_query_arg( array( 'report_id' => $item->id ), $base_url ) ); ?>" class="button button-small" style="color: #14263E; border-color: #CBD5E1;">
										<?php esc_html_e( 'Manage Visibility', 'fraud-tenant-core' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<!-- Pagination -->
			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav bottom" style="margin-top: 14px;">
					<div class="tablenav-pages">
						<span class="displaying-num"><?php printf( esc_html__( '%d reports', 'fraud-tenant-core' ), $total_items ); ?></span>
						<span class="pagination-links">
							<?php
							echo paginate_links(
								array(
									'base'      => add_query_arg( 'paged', '%#%' ),
									'format'    => '',
									'prev_text' => '&laquo;',
									'next_text' => '&raquo;',
									'total'     => $total_pages,
									'current'   => $paged,
								)
							);
							?>
						</span>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the Detail & Disclosure Management Screen for a single incident report.
	 *
	 * @param int $report_id Incident Report ID.
	 */
	protected static function render_detail( int $report_id ): void {
		$admin_id = get_current_user_id();

		$data = ReportVisibilityService::get_report_visibility_detail( $report_id, $admin_id );
		if ( empty( $data['success'] ) ) {
			?>
			<div class="wrap ft-admin-wrap" style="max-width: 1000px; margin: 30px auto;">
				<div class="notice notice-error"><p><?php echo esc_html( $data['message'] ?? __( 'Could not load report record.', 'fraud-tenant-core' ) ); ?></p></div>
				<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=fraud-tenant-visibility' ) ); ?>" class="button">&larr; <?php esc_html_e( 'Back to Visibility Queue', 'fraud-tenant-core' ); ?></a></p>
			</div>
			<?php
			return;
		}

		$report            = $data['report'];
		$evidence_docs     = $data['evidence_docs'] ?? array();
		$associations      = $data['associations'] ?? array();
		$history           = $data['history'] ?? array();
		$tenant_candidates = $data['tenant_candidates'] ?? array();

		// Find active association if any
		$active_assoc = null;
		foreach ( $associations as $a ) {
			if ( ReportVisibilityService::STATUS_ACTIVE === $a->status ) {
				$active_assoc = $a;
				break;
			}
		}

		$queue_url = admin_url( 'admin.php?page=fraud-tenant-visibility' );
		?>
		<div class="wrap ft-admin-wrap" style="max-width: 1240px; margin: 20px auto 50px;">
			<div style="margin-bottom: 14px;">
				<a href="<?php echo esc_url( $queue_url ); ?>" style="text-decoration: none; color: #64748B; font-weight: 500;">
					&larr; <?php esc_html_e( 'Back to Visibility Queue', 'fraud-tenant-core' ); ?>
				</a>
			</div>

			<h1 class="wp-heading-inline" style="color: #14263E; font-size: 24px; font-weight: 700; margin-bottom: 6px;">
				<?php printf( esc_html__( 'Manage Visibility — Incident Report %s', 'fraud-tenant-core' ), esc_html( $report->reference ) ); ?>
			</h1>
			<p class="description" style="color: #64748B; font-size: 14px; margin-bottom: 18px;">
				<?php esc_html_e( 'Associate this incident record with a verified tenant account, review evidence, and approve or revoke tenant visibility.', 'fraud-tenant-core' ); ?>
			</p>
			<hr class="wp-header-end">

			<?php self::render_flash_notices(); ?>

			<!-- Top Status Banner -->
			<div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 8px; padding: 16px 20px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
				<div style="display: flex; gap: 24px; align-items: center;">
					<div>
						<span style="font-size: 11px; text-transform: uppercase; color: #64748B; font-weight: 600; display: block;"><?php esc_html_e( 'Incident Status (Phase 8.2)', 'fraud-tenant-core' ); ?></span>
						<strong style="color: #14263E; font-size: 15px; text-transform: capitalize;"><?php echo esc_html( str_replace( '_', ' ', $report->status ) ); ?></strong>
					</div>
					<div style="border-left: 1px solid #E2E8F0; padding-left: 24px;">
						<span style="font-size: 11px; text-transform: uppercase; color: #64748B; font-weight: 600; display: block;"><?php esc_html_e( 'Tenant Association', 'fraud-tenant-core' ); ?></span>
						<?php if ( $active_assoc ) : ?>
							<strong style="color: #0E9488; font-size: 15px;"><?php echo esc_html( $active_assoc->tenant_name ); ?> (User #<?php echo (int) $active_assoc->tenant_user_id; ?>)</strong>
						<?php else : ?>
							<span style="color: #94A3B8; font-size: 15px; font-style: italic;"><?php esc_html_e( 'No active tenant association', 'fraud-tenant-core' ); ?></span>
						<?php endif; ?>
					</div>
					<div style="border-left: 1px solid #E2E8F0; padding-left: 24px;">
						<span style="font-size: 11px; text-transform: uppercase; color: #64748B; font-weight: 600; display: block;"><?php esc_html_e( 'Tenant-Facing Visibility', 'fraud-tenant-core' ); ?></span>
						<?php if ( $active_assoc && ReportVisibilityService::VISIBILITY_VISIBLE === $active_assoc->visibility_status && 1 === (int) $report->disclosable ) : ?>
							<span style="background: #D1FAE5; color: #065F46; font-size: 12px; font-weight: 700; padding: 3px 10px; border-radius: 12px; border: 1px solid #A7F3D0;">
								<?php esc_html_e( 'Active (Tenant-Visible)', 'fraud-tenant-core' ); ?>
							</span>
						<?php elseif ( $active_assoc && ReportVisibilityService::VISIBILITY_REVOKED === $active_assoc->visibility_status ) : ?>
							<span style="background: #FEE2E2; color: #991B1B; font-size: 12px; font-weight: 700; padding: 3px 10px; border-radius: 12px; border: 1px solid #FECACA;">
								<?php esc_html_e( 'Visibility Revoked', 'fraud-tenant-core' ); ?>
							</span>
						<?php elseif ( $active_assoc ) : ?>
							<span style="background: #FEF3C7; color: #92400E; font-size: 12px; font-weight: 700; padding: 3px 10px; border-radius: 12px; border: 1px solid #FDE68A;">
								<?php esc_html_e( 'Associated (Hidden / Pending Approval)', 'fraud-tenant-core' ); ?>
							</span>
						<?php else : ?>
							<span style="background: #F1F5F9; color: #64748B; font-size: 12px; font-weight: 600; padding: 3px 10px; border-radius: 12px;">
								<?php esc_html_e( 'Unassociated (Hidden)', 'fraud-tenant-core' ); ?>
							</span>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<!-- Main 2-Column Grid -->
			<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">

				<!-- Left Column: Report Details & Evidence -->
				<div>
					<!-- Card 1: Report Details -->
					<div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 8px; padding: 20px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
						<h2 style="color: #14263E; font-size: 16px; font-weight: 700; margin-top: 0; padding-bottom: 12px; border-bottom: 1px solid #F1F5F9;">
							<?php esc_html_e( 'Incident Report Summary', 'fraud-tenant-core' ); ?>
						</h2>
						<table style="width: 100%; font-size: 13px; line-height: 1.8;">
							<tr>
								<td style="color: #64748B; width: 140px;"><?php esc_html_e( 'Reference:', 'fraud-tenant-core' ); ?></td>
								<td><strong style="color: #14263E; font-family: monospace;"><?php echo esc_html( $report->reference ); ?></strong></td>
							</tr>
							<tr>
								<td style="color: #64748B;"><?php esc_html_e( 'Category:', 'fraud-tenant-core' ); ?></td>
								<td><strong><?php echo esc_html( $report->category_label ); ?></strong></td>
							</tr>
							<tr>
								<td style="color: #64748B;"><?php esc_html_e( 'Incident Date:', 'fraud-tenant-core' ); ?></td>
								<td><?php echo esc_html( $report->incident_date ); ?></td>
							</tr>
							<tr>
								<td style="color: #64748B;"><?php esc_html_e( 'Amount Involved:', 'fraud-tenant-core' ); ?></td>
								<td><strong style="color: #0E9488;"><?php echo esc_html( $report->amount_formatted ); ?></strong></td>
							</tr>
							<tr>
								<td style="color: #64748B;"><?php esc_html_e( 'Reporting Owner:', 'fraud-tenant-core' ); ?></td>
								<td><?php echo esc_html( $report->owner_name ); ?> (<?php echo esc_html( $report->owner_email ); ?>)</td>
							</tr>
							<tr>
								<td style="color: #64748B;"><?php esc_html_e( 'Property:', 'fraud-tenant-core' ); ?></td>
								<td>
									<strong><?php echo esc_html( $report->property_title ); ?></strong><br>
									<span style="color: #64748B;"><?php echo esc_html( $report->property_street . ( $report->property_unit ? ' Unit ' . $report->property_unit : '' ) . ', ' . $report->property_city . ', ' . $report->property_state . ' ' . $report->property_zip ); ?></span>
								</td>
							</tr>
							<tr>
								<td style="color: #64748B;"><?php esc_html_e( 'Owner Attestation:', 'fraud-tenant-core' ); ?></td>
								<td>
									<?php if ( 1 === (int) $report->owner_attestation ) : ?>
										<span style="color: #166534; font-weight: 500;">&check; <?php esc_html_e( 'Attested under penalty of perjury', 'fraud-tenant-core' ); ?></span>
									<?php else : ?>
										<span style="color: #DC2626;"><?php esc_html_e( 'Missing attestation', 'fraud-tenant-core' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
						</table>

						<div style="margin-top: 16px; padding-top: 14px; border-top: 1px solid #F1F5F9;">
							<span style="color: #64748B; font-size: 12px; font-weight: 600; display: block; margin-bottom: 6px; text-transform: uppercase;">
								<?php esc_html_e( 'Factual Description Submitted by Owner:', 'fraud-tenant-core' ); ?>
							</span>
							<div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 6px; padding: 12px 14px; font-size: 13px; color: #334155; line-height: 1.6; white-space: pre-wrap;">
								<?php echo esc_html( $report->factual_description ); ?>
							</div>
						</div>
					</div>

					<!-- Card 2: Attached Evidence Documents -->
					<div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
						<h2 style="color: #14263E; font-size: 16px; font-weight: 700; margin-top: 0; padding-bottom: 12px; border-bottom: 1px solid #F1F5F9;">
							<?php esc_html_e( 'Attached Evidence Documents', 'fraud-tenant-core' ); ?> (<?php echo count( $evidence_docs ); ?>)
						</h2>
						<?php if ( empty( $evidence_docs ) ) : ?>
							<p style="color: #94A3B8; font-style: italic; font-size: 13px; margin: 12px 0;">
								<?php esc_html_e( 'No supporting documents attached to this report.', 'fraud-tenant-core' ); ?>
							</p>
						<?php else : ?>
							<ul style="margin: 0; padding: 0; list-style: none;">
								<?php foreach ( $evidence_docs as $doc ) : ?>
									<li style="padding: 10px 12px; border: 1px solid #E2E8F0; border-radius: 6px; margin-bottom: 8px; display: flex; align-items: center; justify-content: space-between; font-size: 13px;">
										<div>
											<span class="dashicons dashicons-media-document" style="color: #0E9488; vertical-align: middle; margin-right: 6px;"></span>
											<strong><?php echo esc_html( $doc->original_name ); ?></strong>
											<span style="color: #64748B; font-size: 11px; margin-left: 8px;">(<?php echo esc_html( $doc->file_size_fmt ); ?>)</span>
										</div>
										<a href="<?php echo esc_url( $doc->view_url ); ?>" target="_blank" class="button button-small" style="color: #14263E;">
											<?php esc_html_e( 'View Document', 'fraud-tenant-core' ); ?> &rarr;
										</a>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				</div>

				<!-- Right Column: Association Controls & Visibility Actions -->
				<div>
					<!-- Card 3: Administrative Association & Visibility Actions -->
					<div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 8px; padding: 20px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
						<h2 style="color: #14263E; font-size: 16px; font-weight: 700; margin-top: 0; padding-bottom: 12px; border-bottom: 1px solid #F1F5F9;">
							<?php esc_html_e( 'Tenant Association & Disclosure Controls', 'fraud-tenant-core' ); ?>
						</h2>

						<?php if ( $active_assoc ) : ?>
							<!-- Active Association Info -->
							<div style="background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 6px; padding: 14px; margin-bottom: 20px; font-size: 13px;">
								<strong style="color: #166534; font-size: 14px; display: block; margin-bottom: 6px;">
									&check; <?php esc_html_e( 'Currently Associated Tenant:', 'fraud-tenant-core' ); ?>
								</strong>
								<div><strong><?php echo esc_html( $active_assoc->tenant_name ); ?></strong> (<?php echo esc_html( $active_assoc->tenant_email ); ?>)</div>
								<div style="color: #475569; font-size: 12px; margin-top: 2px;">
									<?php printf( esc_html__( 'User ID: #%d | Associated: %s', 'fraud-tenant-core' ), (int) $active_assoc->tenant_user_id, esc_html( $active_assoc->associated_at ) ); ?>
								</div>
								<?php if ( ! empty( $active_assoc->association_notes ) ) : ?>
									<div style="margin-top: 6px; color: #334155; font-style: italic;">
										"<?php echo esc_html( $active_assoc->association_notes ); ?>"
									</div>
								<?php endif; ?>
							</div>

							<!-- Action 1: Approve Visibility (If not yet visible) -->
							<?php if ( ReportVisibilityService::VISIBILITY_VISIBLE !== $active_assoc->visibility_status || 1 !== (int) $report->disclosable ) : ?>
								<div style="background: #FEF3C7; border: 1px solid #FDE68A; border-radius: 6px; padding: 14px; margin-bottom: 18px;">
									<h3 style="color: #92400E; font-size: 14px; font-weight: 700; margin: 0 0 8px;">
										<?php esc_html_e( 'Approve Tenant-Facing Visibility', 'fraud-tenant-core' ); ?>
									</h3>
									<?php if ( 'approved' !== $report->status ) : ?>
										<p style="color: #B45309; font-size: 13px; margin: 0;">
											<strong><?php esc_html_e( 'Notice:', 'fraud-tenant-core' ); ?></strong>
											<?php esc_html_e( 'This report is currently in status "' . $report->status . '". Under platform policy, visibility cannot be approved until the incident report itself is approved in Phase 8.2 administrative review.', 'fraud-tenant-core' ); ?>
										</p>
									<?php else : ?>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
											<input type="hidden" name="action" value="ft_admin_visibility_action" />
											<input type="hidden" name="visibility_action" value="approve_visibility" />
											<input type="hidden" name="report_id" value="<?php echo (int) $report->id; ?>" />
											<input type="hidden" name="association_id" value="<?php echo (int) $active_assoc->id; ?>" />
											<?php wp_nonce_field( 'ft_visibility_action_' . $report->id, 'ft_visibility_nonce' ); ?>

											<label style="display: block; font-size: 12px; font-weight: 600; color: #78350F; margin-bottom: 4px;">
												<?php esc_html_e( 'Administrative Disclosure Reason (Required):', 'fraud-tenant-core' ); ?>
											</label>
											<textarea name="action_reason" rows="2" style="width: 100%; font-size: 13px; margin-bottom: 10px;" placeholder="<?php esc_attr_e( 'E.g., Verified lease agreement and tenant identity match. Approving tenant visibility.', 'fraud-tenant-core' ); ?>" required></textarea>

											<button type="submit" class="button button-primary" style="background: #0E9488; border-color: #0D7A70;">
												<?php esc_html_e( 'Approve Tenant Visibility (Disclose to Tenant)', 'fraud-tenant-core' ); ?>
											</button>
										</form>
									<?php endif; ?>
								</div>
							<?php endif; ?>

							<!-- Action 2: Revoke Visibility (If visible) -->
							<?php if ( ReportVisibilityService::VISIBILITY_VISIBLE === $active_assoc->visibility_status && 1 === (int) $report->disclosable ) : ?>
								<div style="background: #FEE2E2; border: 1px solid #FECACA; border-radius: 6px; padding: 14px; margin-bottom: 18px;">
									<h3 style="color: #991B1B; font-size: 14px; font-weight: 700; margin: 0 0 8px;">
										<?php esc_html_e( 'Revoke Tenant Visibility', 'fraud-tenant-core' ); ?>
									</h3>
									<p style="color: #7F1D1D; font-size: 12px; margin-bottom: 8px;">
										<?php esc_html_e( 'Withdrawing visibility immediately hides this record from the tenant dashboard. The original landlord incident report and evidence documents will NOT be deleted.', 'fraud-tenant-core' ); ?>
									</p>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php esc_attr_e( 'Are you sure you want to revoke tenant visibility for this report?', 'fraud-tenant-core' ); ?>');">
										<input type="hidden" name="action" value="ft_admin_visibility_action" />
										<input type="hidden" name="visibility_action" value="revoke_visibility" />
										<input type="hidden" name="report_id" value="<?php echo (int) $report->id; ?>" />
										<input type="hidden" name="association_id" value="<?php echo (int) $active_assoc->id; ?>" />
										<?php wp_nonce_field( 'ft_visibility_action_' . $report->id, 'ft_visibility_nonce' ); ?>

										<label style="display: block; font-size: 12px; font-weight: 600; color: #991B1B; margin-bottom: 4px;">
											<?php esc_html_e( 'Revocation Reason (Required):', 'fraud-tenant-core' ); ?>
										</label>
										<textarea name="action_reason" rows="2" style="width: 100%; font-size: 13px; margin-bottom: 10px;" placeholder="<?php esc_attr_e( 'E.g., Disclosing paused pending further identity review or dispute investigation.', 'fraud-tenant-core' ); ?>" required></textarea>

										<button type="submit" class="button" style="background: #DC2626; color: #FFFFFF; border-color: #B91C1C;">
											<?php esc_html_e( 'Revoke Tenant Visibility', 'fraud-tenant-core' ); ?>
										</button>
									</form>
								</div>
							<?php endif; ?>

							<!-- Action 3: Disassociate Tenant -->
							<div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 6px; padding: 14px;">
								<h3 style="color: #334155; font-size: 14px; font-weight: 700; margin: 0 0 6px;">
									<?php esc_html_e( 'Disassociate Tenant from Report', 'fraud-tenant-core' ); ?>
								</h3>
								<p style="color: #64748B; font-size: 12px; margin-bottom: 8px;">
									<?php esc_html_e( 'Sever the relationship between this report and the current tenant account. If the report was visible, visibility is also revoked.', 'fraud-tenant-core' ); ?>
								</p>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php esc_attr_e( 'Are you sure you want to disassociate this tenant?', 'fraud-tenant-core' ); ?>');">
									<input type="hidden" name="action" value="ft_admin_visibility_action" />
									<input type="hidden" name="visibility_action" value="disassociate_tenant" />
									<input type="hidden" name="report_id" value="<?php echo (int) $report->id; ?>" />
									<input type="hidden" name="association_id" value="<?php echo (int) $active_assoc->id; ?>" />
									<?php wp_nonce_field( 'ft_visibility_action_' . $report->id, 'ft_visibility_nonce' ); ?>

									<textarea name="action_reason" rows="1" style="width: 100%; font-size: 13px; margin-bottom: 8px;" placeholder="<?php esc_attr_e( 'Reason for disassociation (required)...', 'fraud-tenant-core' ); ?>" required></textarea>

									<button type="submit" class="button" style="color: #64748B;">
										<?php esc_html_e( 'Disassociate Tenant', 'fraud-tenant-core' ); ?>
									</button>
								</form>
							</div>

						<?php else : ?>
							<!-- Form to Associate a Tenant -->
							<div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 6px; padding: 16px;">
								<h3 style="color: #14263E; font-size: 15px; font-weight: 700; margin: 0 0 10px;">
									<span class="dashicons dashicons-admin-users" style="color: #0E9488; vertical-align: middle;"></span>
									<?php esc_html_e( 'Associate Verified Tenant Account', 'fraud-tenant-core' ); ?>
								</h3>
								<p style="color: #64748B; font-size: 13px; margin-bottom: 14px; line-height: 1.5;">
									<?php esc_html_e( 'After manually reviewing the landlord\'s submission and verifying the tenant\'s lease documentation, select the corresponding tenant account to establish the association.', 'fraud-tenant-core' ); ?>
								</p>

								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="ft_admin_visibility_action" />
									<input type="hidden" name="visibility_action" value="associate_tenant" />
									<input type="hidden" name="report_id" value="<?php echo (int) $report->id; ?>" />
									<?php wp_nonce_field( 'ft_visibility_action_' . $report->id, 'ft_visibility_nonce' ); ?>

									<div style="margin-bottom: 14px;">
										<label style="display: block; font-size: 12px; font-weight: 600; color: #1E293B; margin-bottom: 4px;">
											<?php esc_html_e( 'Select Tenant Account:', 'fraud-tenant-core' ); ?>
										</label>
										<select name="tenant_user_id" style="width: 100%; max-width: 100%; font-size: 13px;" required>
											<option value=""><?php esc_html_e( '-- Select Tenant Account --', 'fraud-tenant-core' ); ?></option>
											<?php foreach ( $tenant_candidates as $cand ) : ?>
												<option value="<?php echo (int) $cand['user_id']; ?>">
													#<?php echo (int) $cand['user_id']; ?> — <?php echo esc_html( $cand['display_name'] ); ?> (<?php echo esc_html( $cand['user_email'] ); ?>) [SSN: <?php echo esc_html( $cand['ssn_last_four'] ); ?>]
												</option>
											<?php endforeach; ?>
										</select>
									</div>

									<div style="margin-bottom: 14px;">
										<label style="display: block; font-size: 12px; font-weight: 600; color: #1E293B; margin-bottom: 4px;">
											<?php esc_html_e( 'Administrative Association Justification (Required):', 'fraud-tenant-core' ); ?>
										</label>
										<textarea name="action_reason" rows="2" style="width: 100%; font-size: 13px;" placeholder="<?php esc_attr_e( 'E.g., Cross-verified lease agreement with tenant identity records and photo ID.', 'fraud-tenant-core' ); ?>" required></textarea>
									</div>

									<div style="margin-bottom: 16px;">
										<label style="display: block; font-size: 12px; font-weight: 600; color: #1E293B; margin-bottom: 4px;">
											<?php esc_html_e( 'Internal Notes (Optional):', 'fraud-tenant-core' ); ?>
										</label>
										<textarea name="action_notes" rows="2" style="width: 100%; font-size: 13px;" placeholder="<?php esc_attr_e( 'Internal staff notes (not visible to tenant or owner)...', 'fraud-tenant-core' ); ?>"></textarea>
									</div>

									<button type="submit" class="button button-primary" style="background: #14263E; border-color: #0F1E31;">
										<?php esc_html_e( 'Establish Association', 'fraud-tenant-core' ); ?>
									</button>
								</form>
							</div>
						<?php endif; ?>
					</div>

					<!-- Card 4: Complete Association & Disclosure History -->
					<div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
						<h2 style="color: #14263E; font-size: 16px; font-weight: 700; margin-top: 0; padding-bottom: 12px; border-bottom: 1px solid #F1F5F9;">
							<?php esc_html_e( 'Association & Disclosure History', 'fraud-tenant-core' ); ?>
						</h2>
						<?php if ( empty( $history ) ) : ?>
							<p style="color: #94A3B8; font-style: italic; font-size: 13px; margin: 12px 0;">
								<?php esc_html_e( 'No historical visibility decisions recorded for this report.', 'fraud-tenant-core' ); ?>
							</p>
						<?php else : ?>
							<ul style="margin: 0; padding: 0; list-style: none;">
								<?php foreach ( $history as $h ) : ?>
									<?php
									$action_label = ucwords( str_replace( '_', ' ', $h->action ) );
									$action_color = '#64748B';
									if ( 'visibility_approved' === $h->action ) {
										$action_color = '#0E9488';
									} elseif ( 'visibility_revoked' === $h->action ) {
										$action_color = '#DC2626';
									} elseif ( 'associated' === $h->action ) {
										$action_color = '#14263E';
									}
									?>
									<li style="padding: 10px 0; border-bottom: 1px solid #F1F5F9; font-size: 12px;">
										<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
											<strong style="color: <?php echo esc_attr( $action_color ); ?>; font-size: 13px;">
												<?php echo esc_html( $action_label ); ?>
											</strong>
											<span style="color: #94A3B8;"><?php echo esc_html( $h->created_at ); ?></span>
										</div>
										<div style="color: #475569;">
											<?php printf( esc_html__( 'By Admin: %s | Tenant: %s', 'fraud-tenant-core' ), esc_html( $h->actor_name ?: '#' . $h->performed_by ), esc_html( $h->tenant_name ?: '#' . $h->tenant_user_id ) ); ?>
										</div>
										<div style="background: #F8FAFC; border-left: 2px solid <?php echo esc_attr( $action_color ); ?>; padding: 4px 8px; margin-top: 4px; color: #334155;">
											<?php echo esc_html( $h->reason ); ?>
										</div>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				</div>

			</div>
		</div>
		<?php
	}

	/**
	 * Render flash notices from query parameters.
	 */
	protected static function render_flash_notices(): void {
		if ( empty( $_GET['ft_msg'] ) ) {
			return;
		}

		$msg  = sanitize_key( $_GET['ft_msg'] );
		$type = 'success';
		$text = '';

		switch ( $msg ) {
			case 'associated':
				$text = __( 'Tenant was successfully associated with the incident report.', 'fraud-tenant-core' );
				break;
			case 'disassociated':
				$text = __( 'Tenant was successfully disassociated from the incident report.', 'fraud-tenant-core' );
				break;
			case 'visibility_approved':
				$text = __( 'Report visibility was approved. The record is now visible in the associated tenant\'s dashboard.', 'fraud-tenant-core' );
				break;
			case 'visibility_revoked':
				$text = __( 'Report visibility was successfully revoked. The record has been hidden from the tenant dashboard.', 'fraud-tenant-core' );
				break;
			case 'error':
				$type = 'error';
				$err_code = isset( $_GET['ft_err'] ) ? sanitize_key( $_GET['ft_err'] ) : 'unknown';
				$text = sprintf( __( 'An error occurred while updating report visibility (%s).', 'fraud-tenant-core' ), $err_code );
				break;
		}

		if ( ! empty( $text ) ) {
			?>
			<div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible" style="margin: 16px 0;">
				<p><strong><?php echo esc_html( $text ); ?></strong></p>
			</div>
			<?php
		}
	}
}

