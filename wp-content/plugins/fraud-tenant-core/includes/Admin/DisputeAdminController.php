<?php
/**
 * Admin Tenant Disputes Management Controller.
 *
 * Provides administrative screens to review tenant disputes filed against
 * disclosed incident reports, inspect dispute evidence, mark disputes under review,
 * and issue formal determinations with resolution notes.
 *
 * @package FraudTenant\Admin
 */

namespace FraudTenant\Admin;

use FraudTenant\Auth\Capabilities;
use FraudTenant\Services\DisputeService;
use FraudTenant\Services\IncidentService;
use FraudTenant\Utils\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DisputeAdminController {

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
		if ( ! DisputeService::can_review_disputes( $admin_id ) ) {
			wp_die( esc_html__( 'Unauthorized access. You do not have permission to manage tenant disputes.', 'fraud-tenant-core' ), 403 );
		}

		$dispute_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( $dispute_id > 0 ) {
			self::render_detail( $dispute_id );
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
					<?php esc_html_e( 'Dispute Management Locked — BLK-08 Privacy Gate Active', 'fraud-tenant-core' ); ?>
				</h1>
				<p style="color: #64748B; font-size: 15px; max-width: 640px; margin: 0 auto 24px; line-height: 1.6;">
					<?php esc_html_e( 'Tenant dispute management is locked in production mode. Sensitive dispute statements and evidence remain fail-closed until application-level encryption at rest (BLK-08) is formally approved and implemented.', 'fraud-tenant-core' ); ?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Disputes Queue list screen.
	 */
	protected static function render_list(): void {
		$admin_id       = get_current_user_id();
		$current_status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : 'all';
		$search_query   = isset( $_GET['s'] ) ? sanitize_text_field( trim( $_GET['s'] ) ) : '';
		$paged          = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

		$queue_data = DisputeService::get_admin_disputes_queue(
			array(
				'status'   => $current_status,
				'search'   => $search_query,
				'page'     => $paged,
				'per_page' => 20,
			),
			$admin_id
		);

		$items         = $queue_data['items'] ?? array();
		$status_counts = DisputeService::get_queue_status_counts();
		$total_items   = $queue_data['total'] ?? 0;
		$total_pages   = $queue_data['pages'] ?? 1;

		$base_url = admin_url( 'admin.php?page=fraud-tenant-disputes' );

		// Render any flash notices
		self::render_flash_notice();
		?>
		<div class="wrap ft-admin-wrap" style="max-width: 1240px; margin: 20px auto 40px;">
			<h1 class="wp-heading-inline" style="color: #14263E; font-size: 24px; font-weight: 700; margin-bottom: 6px;">
				<span class="dashicons dashicons-id-alt" style="font-size:28px;width:28px;height:28px;vertical-align:middle;color:#0E9488;margin-right:6px;"></span>
				<?php esc_html_e( 'Tenant Disputes Queue', 'fraud-tenant-core' ); ?>
			</h1>
			<p class="description" style="color: #64748B; font-size: 14px; margin-bottom: 16px;">
				<?php esc_html_e( 'Review factual dispute statements and evidence submitted by tenants regarding disclosed incident reports.', 'fraud-tenant-core' ); ?>
			</p>

			<!-- Status Filter Tabs -->
			<ul class="subsubsub" style="margin-bottom: 16px; font-size: 13px;">
				<?php
				$tabs = array(
					'all'          => __( 'All', 'fraud-tenant-core' ),
					'pending'      => __( 'Pending Review', 'fraud-tenant-core' ),
					'under_review' => __( 'Under Review', 'fraud-tenant-core' ),
					'resolved'     => __( 'Resolved', 'fraud-tenant-core' ),
					'rejected'     => __( 'Rejected', 'fraud-tenant-core' ),
				);
				$tab_links = array();
				foreach ( $tabs as $key => $label ) {
					$count       = $status_counts[ $key ] ?? 0;
					$is_current  = ( $current_status === $key );
					$url         = ( 'all' === $key ) ? $base_url : add_query_arg( 'status', $key, $base_url );
					$class       = $is_current ? 'class="current"' : '';
					$tab_links[] = sprintf(
						'<li><a href="%s" %s>%s <span class="count">(%d)</span></a></li>',
						esc_url( $url ),
						$class,
						esc_html( $label ),
						$count
					);
				}
				echo implode( ' | ', $tab_links ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</ul>

			<!-- Search Form -->
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="margin-bottom: 16px; display: flex; gap: 8px; justify-content: flex-end;">
				<input type="hidden" name="page" value="fraud-tenant-disputes" />
				<?php if ( 'all' !== $current_status ) : ?>
					<input type="hidden" name="status" value="<?php echo esc_attr( $current_status ); ?>" />
				<?php endif; ?>
				<input type="search" name="s" value="<?php echo esc_attr( $search_query ); ?>" placeholder="<?php esc_attr_e( 'Search disputes, reports, tenants...', 'fraud-tenant-core' ); ?>" style="min-width: 280px; height: 36px; border-radius: 6px; border: 1px solid #CBD5E1; padding: 0 10px;" />
				<button type="submit" class="button" style="height: 36px; padding: 0 14px;">
					<?php esc_html_e( 'Search', 'fraud-tenant-core' ); ?>
				</button>
				<?php if ( ! empty( $search_query ) ) : ?>
					<a href="<?php echo esc_url( 'all' === $current_status ? $base_url : add_query_arg( 'status', $current_status, $base_url ) ); ?>" class="button" style="height: 36px; line-height: 34px; padding: 0 12px;">
						<?php esc_html_e( 'Clear', 'fraud-tenant-core' ); ?>
					</a>
				<?php endif; ?>
			</form>

			<!-- Disputes Table -->
			<div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
				<table class="wp-list-table widefat fixed striped table-view-list" style="border: none;">
					<thead>
						<tr style="background: #F8FAFC;">
							<th style="width: 100px; padding: 12px 14px; font-weight: 600; color: #14263E;"><?php esc_html_e( 'Dispute Ref', 'fraud-tenant-core' ); ?></th>
							<th style="width: 110px; padding: 12px 14px; font-weight: 600; color: #14263E;"><?php esc_html_e( 'Incident Ref', 'fraud-tenant-core' ); ?></th>
							<th style="width: 180px; padding: 12px 14px; font-weight: 600; color: #14263E;"><?php esc_html_e( 'Tenant', 'fraud-tenant-core' ); ?></th>
							<th style="padding: 12px 14px; font-weight: 600; color: #14263E;"><?php esc_html_e( 'Dispute Reason', 'fraud-tenant-core' ); ?></th>
							<th style="width: 120px; padding: 12px 14px; font-weight: 600; color: #14263E;"><?php esc_html_e( 'Status', 'fraud-tenant-core' ); ?></th>
							<th style="width: 80px; padding: 12px 14px; font-weight: 600; color: #14263E;"><?php esc_html_e( 'Evidence', 'fraud-tenant-core' ); ?></th>
							<th style="width: 130px; padding: 12px 14px; font-weight: 600; color: #14263E;"><?php esc_html_e( 'Submitted', 'fraud-tenant-core' ); ?></th>
							<th style="width: 130px; padding: 12px 14px; font-weight: 600; color: #14263E; text-align: right;"><?php esc_html_e( 'Actions', 'fraud-tenant-core' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $items ) ) : ?>
							<tr>
								<td colspan="8" style="padding: 32px 14px; text-align: center; color: #64748B;">
									<span class="dashicons dashicons-yes-alt" style="font-size: 32px; width: 32px; height: 32px; color: #0E9488; margin-bottom: 8px; display: block; margin: 0 auto;"></span>
									<p style="font-size: 15px; margin: 8px 0 0 0;"><?php esc_html_e( 'No disputes found matching current criteria.', 'fraud-tenant-core' ); ?></p>
								</td>
							</tr>
						<?php else : ?>
							<?php foreach ( $items as $item ) : ?>
								<?php
								$view_url = add_query_arg(
									array(
										'page' => 'fraud-tenant-disputes',
										'id'   => $item->id,
									),
									admin_url( 'admin.php' )
								);

								$badge_bg = match ( $item->status ) {
									'pending'      => '#FEF3C7',
									'under_review' => '#DBEAFE',
									'resolved'     => '#DCFCE7',
									'rejected'     => '#FEE2E2',
									default        => '#F1F5F9',
								};
								$badge_color = match ( $item->status ) {
									'pending'      => '#92400E',
									'under_review' => '#1E40AF',
									'resolved'     => '#166534',
									'rejected'     => '#991B1B',
									default        => '#475569',
								};
								?>
								<tr>
									<td style="padding: 12px 14px; vertical-align: middle;">
										<a href="<?php echo esc_url( $view_url ); ?>" style="font-weight: 700; color: #14263E; font-family: monospace;">
											<?php echo esc_html( $item->reference ); ?>
										</a>
									</td>
									<td style="padding: 12px 14px; vertical-align: middle;">
										<?php if ( (int) $item->report_id > 0 ) : ?>
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=fraud-tenant-visibility&report_id=' . (int) $item->report_id ) ); ?>" style="color: #0E9488; font-family: monospace; font-weight: 600;">
												<?php echo esc_html( $item->report_reference ); ?>
											</a>
										<?php else : ?>
											<span style="color: #94A3B8;">—</span>
										<?php endif; ?>
									</td>
									<td style="padding: 12px 14px; vertical-align: middle;">
										<strong><?php echo esc_html( $item->tenant_name ?? ( 'Tenant #' . $item->tenant_id ) ); ?></strong>
										<?php if ( ! empty( $item->tenant_email ) ) : ?>
											<div style="font-size: 11px; color: #64748B;"><?php echo esc_html( $item->tenant_email ); ?></div>
										<?php endif; ?>
									</td>
									<td style="padding: 12px 14px; vertical-align: middle;">
										<strong style="color: #334155;"><?php echo esc_html( $item->reason_label ); ?></strong>
									</td>
									<td style="padding: 12px 14px; vertical-align: middle;">
										<span style="display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; background: <?php echo esc_attr( $badge_bg ); ?>; color: <?php echo esc_attr( $badge_color ); ?>;">
											<?php echo esc_html( $item->status_label ); ?>
										</span>
									</td>
									<td style="padding: 12px 14px; vertical-align: middle; text-align: center;">
										<?php if ( (int) $item->evidence_count > 0 ) : ?>
											<span style="display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 50%; background: #0E9488; color: #FFFFFF; font-size: 11px; font-weight: 700;">
												<?php echo (int) $item->evidence_count; ?>
											</span>
										<?php else : ?>
											<span style="color: #CBD5E1;">0</span>
										<?php endif; ?>
									</td>
									<td style="padding: 12px 14px; vertical-align: middle; font-size: 12px; color: #64748B;">
										<?php echo esc_html( $item->created_at_fmt ); ?>
									</td>
									<td style="padding: 12px 14px; vertical-align: middle; text-align: right;">
										<a href="<?php echo esc_url( $view_url ); ?>" class="button button-small" style="font-weight: 600;">
											<?php esc_html_e( 'Review', 'fraud-tenant-core' ); ?> &rarr;
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<!-- Pagination -->
			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav" style="margin-top: 16px;">
					<div class="tablenav-pages">
						<span class="displaying-num">
							<?php printf( esc_html__( '%d disputes', 'fraud-tenant-core' ), (int) $total_items ); ?>
						</span>
						<?php
						$page_links = paginate_links(
							array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
								'total'     => $total_pages,
								'current'   => $paged,
							)
						);
						if ( $page_links ) {
							echo '<span class="pagination-links">' . $page_links . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						}
						?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the Dispute Detail & Review screen.
	 *
	 * @param int $dispute_id Dispute ID.
	 */
	protected static function render_detail( int $dispute_id ): void {
		$admin_id = get_current_user_id();
		$detail   = DisputeService::get_dispute_detail( $dispute_id, $admin_id );

		if ( ! $detail ) {
			wp_die( esc_html__( 'Dispute record not found or inaccessible.', 'fraud-tenant-core' ), 404 );
		}

		$dispute       = $detail['dispute'];
		$evidence_docs = $detail['evidence_docs'] ?? array();
		$report        = $detail['report'];
		$tenant        = $detail['tenant'];
		$resolver      = $detail['resolver'];

		$back_url = admin_url( 'admin.php?page=fraud-tenant-disputes' );

		$badge_bg = match ( $dispute->status ) {
			'pending'      => '#FEF3C7',
			'under_review' => '#DBEAFE',
			'resolved'     => '#DCFCE7',
			'rejected'     => '#FEE2E2',
			default        => '#F1F5F9',
		};
		$badge_color = match ( $dispute->status ) {
			'pending'      => '#92400E',
			'under_review' => '#1E40AF',
			'resolved'     => '#166534',
			'rejected'     => '#991B1B',
			default        => '#475569',
		};

		// Flash notices
		self::render_flash_notice();
		?>
		<div class="wrap ft-admin-wrap" style="max-width: 1240px; margin: 20px auto 40px;">
			<!-- Header & Back Navigation -->
			<div style="margin-bottom: 20px;">
				<a href="<?php echo esc_url( $back_url ); ?>" style="display: inline-flex; align-items: center; color: #0E9488; text-decoration: none; font-weight: 600; font-size: 13px; margin-bottom: 8px;">
					&larr; <?php esc_html_e( 'Back to Disputes Queue', 'fraud-tenant-core' ); ?>
				</a>
				<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
					<h1 style="margin: 0; color: #14263E; font-size: 24px; font-weight: 700;">
						<?php esc_html_e( 'Tenant Dispute', 'fraud-tenant-core' ); ?>: <span style="font-family: monospace; color: #0E9488;"><?php echo esc_html( $dispute->reference ); ?></span>
					</h1>
					<span style="display: inline-block; padding: 6px 14px; border-radius: 6px; font-size: 13px; font-weight: 700; background: <?php echo esc_attr( $badge_bg ); ?>; color: <?php echo esc_attr( $badge_color ); ?>;">
						<?php echo esc_html( $dispute->status_label ); ?>
					</span>
				</div>
			</div>

			<!-- Main 2-Column Grid -->
			<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; align-items: start;">

				<!-- Left Column: Dispute Statement, Attached Evidence, and Associated Incident Report -->
				<div style="display: flex; flex-direction: column; gap: 24px;">

					<!-- Card 1: Tenant Dispute Statement -->
					<div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
						<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 12px;">
							<h3 style="margin: 0; color: #14263E; font-size: 16px; font-weight: 700;">
								<?php esc_html_e( 'Tenant Dispute Statement', 'fraud-tenant-core' ); ?>
							</h3>
							<span style="font-size: 12px; color: #64748B;">
								<?php printf( esc_html__( 'Submitted: %s', 'fraud-tenant-core' ), esc_html( $dispute->created_at_fmt ) ); ?>
							</span>
						</div>

						<div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 12px 14px; margin-bottom: 16px; font-size: 13px;">
							<strong style="color: #14263E; display: block; margin-bottom: 4px;"><?php esc_html_e( 'Stated Reason Category:', 'fraud-tenant-core' ); ?></strong>
							<span style="color: #0E9488; font-weight: 600;"><?php echo esc_html( $dispute->reason_label ); ?></span>
						</div>

						<div style="margin-bottom: 0;">
							<strong style="color: #14263E; font-size: 13px; display: block; margin-bottom: 6px;">
								<?php esc_html_e( 'Factual Explanation Provided by Tenant:', 'fraud-tenant-core' ); ?>
							</strong>
							<div style="background: #FFFFFF; border: 1px solid #CBD5E1; border-radius: 8px; padding: 14px; font-size: 14px; color: #334155; line-height: 1.6; white-space: pre-wrap;">
								<?php echo esc_html( $dispute->statement ); ?>
							</div>
						</div>
					</div>

					<!-- Card 2: Attached Dispute Evidence Documents -->
					<div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
						<h3 style="margin: 0 0 16px; color: #14263E; font-size: 16px; font-weight: 700;">
							<?php esc_html_e( 'Tenant Supporting Documentation & Evidence', 'fraud-tenant-core' ); ?>
						</h3>

						<?php if ( empty( $evidence_docs ) ) : ?>
							<p style="color: #64748B; font-size: 13px; margin: 0; font-style: italic;">
								<?php esc_html_e( 'No supporting documentation files were uploaded with this dispute.', 'fraud-tenant-core' ); ?>
							</p>
						<?php else : ?>
							<ul style="margin: 0; padding: 0; list-style: none;">
								<?php foreach ( $evidence_docs as $edoc ) : ?>
									<li style="padding: 10px 14px; border: 1px solid #E2E8F0; border-radius: 6px; margin-bottom: 8px; display: flex; align-items: center; justify-content: space-between; font-size: 13px; background: #F8FAFC;">
										<div>
											<strong style="color: #334155;"><?php echo esc_html( $edoc->original_name ); ?></strong>
											<span style="color: #64748B; font-size: 11px; margin-left: 6px;">(<?php echo esc_html( $edoc->file_size_fmt ); ?> &bull; <?php echo esc_html( $edoc->mime_type ); ?>)</span>
										</div>
										<a href="<?php echo esc_url( $edoc->view_url ); ?>" target="_blank" class="button button-small" style="font-weight: 600; color: #0E9488;">
											<?php esc_html_e( 'View File', 'fraud-tenant-core' ); ?> &rarr;
										</a>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>

					<!-- Card 3: Associated Incident Report Context -->
					<div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
						<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
							<h3 style="margin: 0; color: #14263E; font-size: 16px; font-weight: 700;">
								<?php esc_html_e( 'Associated Incident Report', 'fraud-tenant-core' ); ?>
							</h3>
							<?php if ( $report ) : ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=fraud-tenant-visibility&report_id=' . (int) $report['id'] ) ); ?>" style="font-size: 12px; color: #0E9488; font-weight: 600; text-decoration: none;">
									<?php esc_html_e( 'Open in Report Visibility', 'fraud-tenant-core' ); ?> &rarr;
								</a>
							<?php endif; ?>
						</div>

						<?php if ( ! $report ) : ?>
							<p style="color: #64748B; font-size: 13px; margin: 0; font-style: italic;">
								<?php esc_html_e( 'Legacy dispute row: No active incident report linked (report_id = 0).', 'fraud-tenant-core' ); ?>
							</p>
						<?php else : ?>
							<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; font-size: 13px; margin-bottom: 16px; background: #F8FAFC; padding: 14px; border-radius: 8px; border: 1px solid #E2E8F0;">
								<div>
									<span style="color: #64748B; display: block; font-size: 11px; text-transform: uppercase;"><?php esc_html_e( 'Report Reference', 'fraud-tenant-core' ); ?></span>
									<strong style="color: #14263E; font-family: monospace;"><?php echo esc_html( $report['reference'] ); ?></strong>
								</div>
								<div>
									<span style="color: #64748B; display: block; font-size: 11px; text-transform: uppercase;"><?php esc_html_e( 'Category', 'fraud-tenant-core' ); ?></span>
									<strong style="color: #14263E;"><?php echo esc_html( $report['category_label'] ); ?></strong>
								</div>
								<div>
									<span style="color: #64748B; display: block; font-size: 11px; text-transform: uppercase;"><?php esc_html_e( 'Amount Involved', 'fraud-tenant-core' ); ?></span>
									<strong style="color: #0E9488;"><?php echo esc_html( $report['amount_formatted'] ); ?></strong>
								</div>
								<div>
									<span style="color: #64748B; display: block; font-size: 11px; text-transform: uppercase;"><?php esc_html_e( 'Incident Date', 'fraud-tenant-core' ); ?></span>
									<strong style="color: #14263E;"><?php echo esc_html( gmdate( 'M j, Y', strtotime( $report['incident_date'] ) ) ); ?></strong>
								</div>
								<?php if ( ! empty( $report['property_address'] ) ) : ?>
									<div style="grid-column: span 2;">
										<span style="color: #64748B; display: block; font-size: 11px; text-transform: uppercase;"><?php esc_html_e( 'Property Address', 'fraud-tenant-core' ); ?></span>
										<span style="color: #334155;"><?php echo esc_html( $report['property_address'] ); ?></span>
									</div>
								<?php endif; ?>
							</div>

							<div>
								<strong style="color: #14263E; font-size: 13px; display: block; margin-bottom: 6px;">
									<?php esc_html_e( 'Original Landlord Narrative:', 'fraud-tenant-core' ); ?>
								</strong>
								<div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 8px; padding: 12px; font-size: 13px; color: #475569; max-height: 140px; overflow-y: auto; white-space: pre-wrap;">
									<?php echo esc_html( $report['factual_description'] ); ?>
								</div>
							</div>
						<?php endif; ?>
					</div>

				</div>

				<!-- Right Column: Tenant Profile, Current Status & Review Actions -->
				<div style="display: flex; flex-direction: column; gap: 24px;">

					<!-- Card: Disputing Tenant Profile -->
					<div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
						<h4 style="margin: 0 0 12px; color: #14263E; font-size: 14px; font-weight: 700;">
							<?php esc_html_e( 'Disputing Tenant', 'fraud-tenant-core' ); ?>
						</h4>
						<div style="font-size: 13px; line-height: 1.6; color: #334155;">
							<div><strong><?php echo esc_html( $tenant['display_name'] ); ?></strong></div>
							<div style="color: #64748B; font-size: 12px;"><?php echo esc_html( $tenant['email'] ); ?></div>
							<div style="font-size: 11px; color: #94A3B8; margin-top: 4px;">User ID: #<?php echo (int) $tenant['id']; ?></div>
						</div>
					</div>

					<!-- Card: Administrative Determination Form / Status -->
					<div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
						<h4 style="margin: 0 0 14px; color: #14263E; font-size: 14px; font-weight: 700;">
							<?php esc_html_e( 'Dispute Review & Determination', 'fraud-tenant-core' ); ?>
						</h4>

						<?php if ( 'pending' === $dispute->status ) : ?>
							<!-- Mark Under Review Form -->
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom: 18px;">
								<input type="hidden" name="action" value="ft_admin_dispute_action" />
								<input type="hidden" name="dispute_id" value="<?php echo (int) $dispute->id; ?>" />
								<input type="hidden" name="sub_action" value="review" />
								<?php wp_nonce_field( 'ft_admin_dispute_' . (int) $dispute->id, 'ft_dispute_nonce' ); ?>

								<p style="font-size: 12px; color: #64748B; margin: 0 0 10px;">
									<?php esc_html_e( 'Mark this dispute as under active administrative review.', 'fraud-tenant-core' ); ?>
								</p>
								<button type="submit" class="button" style="width: 100%; justify-content: center; height: 34px;">
									<span class="dashicons dashicons-search" style="font-size: 16px; width: 16px; height: 16px; vertical-align: middle; margin-right: 4px;"></span>
									<?php esc_html_e( 'Mark Under Review', 'fraud-tenant-core' ); ?>
								</button>
							</form>
							<hr style="border: 0; border-top: 1px solid #E2E8F0; margin: 18px 0;" />
						<?php endif; ?>

						<?php if ( in_array( $dispute->status, array( 'pending', 'under_review' ), true ) ) : ?>
							<!-- Resolution Form -->
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="ft_admin_dispute_action" />
								<input type="hidden" name="dispute_id" value="<?php echo (int) $dispute->id; ?>" />
								<input type="hidden" name="sub_action" value="resolve" />
								<?php wp_nonce_field( 'ft_admin_dispute_' . (int) $dispute->id, 'ft_dispute_nonce' ); ?>

								<div style="margin-bottom: 14px;">
									<label for="resolution_action" style="display: block; font-size: 12px; font-weight: 600; color: #14263E; margin-bottom: 6px;">
										<?php esc_html_e( 'Determination Action', 'fraud-tenant-core' ); ?> <span style="color:#DC2626;">*</span>
									</label>
									<select id="resolution_action" name="resolution_action" style="width: 100%; border: 1px solid #CBD5E1; border-radius: 6px; height: 36px;" required>
										<option value=""><?php esc_html_e( '-- Select Action --', 'fraud-tenant-core' ); ?></option>
										<option value="resolve_upheld"><?php esc_html_e( 'Uphold Dispute (Factual Challenge Validated)', 'fraud-tenant-core' ); ?></option>
										<option value="resolve_rejected"><?php esc_html_e( 'Reject Dispute (Record Stands Unaltered)', 'fraud-tenant-core' ); ?></option>
										<option value="resolve_corrected"><?php esc_html_e( 'Correction Acknowledged (Administrative Determination Recorded)', 'fraud-tenant-core' ); ?></option>
									</select>
								</div>

								<div style="margin-bottom: 14px;">
									<label for="resolution_notes" style="display: block; font-size: 12px; font-weight: 600; color: #14263E; margin-bottom: 6px;">
										<?php esc_html_e( 'Determination Notes', 'fraud-tenant-core' ); ?> <span style="color:#DC2626;">*</span>
									</label>
									<textarea id="resolution_notes" name="resolution_notes" rows="4" style="width: 100%; border: 1px solid #CBD5E1; border-radius: 6px; padding: 8px; font-size: 13px;" placeholder="<?php esc_attr_e( 'Explain factual evaluation and reasons for determination...', 'fraud-tenant-core' ); ?>" required></textarea>
									<span style="font-size: 11px; color: #64748B;"><?php esc_html_e( 'Min 5 characters. Tenant receives notification of determination.', 'fraud-tenant-core' ); ?></span>
								</div>

								<div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 6px; padding: 10px; font-size: 11px; color: #64748B; margin-bottom: 14px; line-height: 1.4;">
									<strong><?php esc_html_e( 'Notice:', 'fraud-tenant-core' ); ?></strong>
									<?php esc_html_e( 'Issuing a determination records an administrative resolution. It does not modify or delete the landlord\'s original incident report narrative, claim amount, or attached evidence. If "Correction Acknowledged" is selected, this records that a correction was warranted; the original incident report remains unchanged by this dispute action.', 'fraud-tenant-core' ); ?>
								</div>

								<button type="submit" class="button button-primary" style="width: 100%; justify-content: center; height: 38px; background: #0E9488; border-color: #0E9488; font-weight: 600;">
									<?php esc_html_e( 'Record Determination', 'fraud-tenant-core' ); ?>
								</button>
							</form>
						<?php else : ?>
							<!-- Finalized Resolution Summary -->
							<div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 14px; font-size: 13px;">
								<div style="margin-bottom: 10px;">
									<span style="color: #64748B; font-size: 11px; text-transform: uppercase; display: block;"><?php esc_html_e( 'Final Status', 'fraud-tenant-core' ); ?></span>
									<span style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; background: <?php echo esc_attr( $badge_bg ); ?>; color: <?php echo esc_attr( $badge_color ); ?>; margin-top: 2px;">
										<?php echo esc_html( $dispute->status_label ); ?>
									</span>
								</div>

								<?php if ( $resolver ) : ?>
									<div style="margin-bottom: 10px;">
										<span style="color: #64748B; font-size: 11px; text-transform: uppercase; display: block;"><?php esc_html_e( 'Resolved By', 'fraud-tenant-core' ); ?></span>
										<strong style="color: #14263E;"><?php echo esc_html( $resolver['display_name'] ); ?></strong>
									</div>
								<?php endif; ?>

								<?php if ( ! empty( $dispute->resolved_at_fmt ) && '—' !== $dispute->resolved_at_fmt ) : ?>
									<div style="margin-bottom: 10px;">
										<span style="color: #64748B; font-size: 11px; text-transform: uppercase; display: block;"><?php esc_html_e( 'Resolved At', 'fraud-tenant-core' ); ?></span>
										<span style="color: #334155;"><?php echo esc_html( $dispute->resolved_at_fmt ); ?></span>
									</div>
								<?php endif; ?>

								<div>
									<span style="color: #64748B; font-size: 11px; text-transform: uppercase; display: block; margin-bottom: 4px;"><?php esc_html_e( 'Administrative Notes', 'fraud-tenant-core' ); ?></span>
									<div style="background: #FFFFFF; border: 1px solid #CBD5E1; border-radius: 6px; padding: 10px; font-size: 12px; color: #334155; white-space: pre-wrap; line-height: 1.5;">
										<?php echo esc_html( $dispute->resolution_notes ?? __( 'No notes recorded.', 'fraud-tenant-core' ) ); ?>
									</div>
								</div>
							</div>
						<?php endif; ?>
					</div>

				</div>

			</div>
		</div>
		<?php
	}

	/**
	 * Handle admin dispute action POST request.
	 */
	public static function handle_admin_action(): void {
		$admin_id = get_current_user_id();

		// 0. BLK-08 Security Gate
		if ( ! \FraudTenant\Services\ComplianceService::is_data_gate_open() ) {
			wp_die( esc_html__( 'Action locked pending sensitive PII encryption infrastructure (BLK-08).', 'fraud-tenant-core' ), 423 );
		}

		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Authentication required.', 'fraud-tenant-core' ), 403 );
		}

		$dispute_id = isset( $_POST['dispute_id'] ) ? (int) $_POST['dispute_id'] : 0;
		$sub_action = isset( $_POST['sub_action'] ) ? sanitize_key( $_POST['sub_action'] ) : '';
		$nonce      = isset( $_POST['ft_dispute_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ft_dispute_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'ft_admin_dispute_' . $dispute_id ) ) {
			wp_die( esc_html__( 'Security check failed. Please refresh the page and try again.', 'fraud-tenant-core' ), 403 );
		}

		$redirect_url = add_query_arg(
			array(
				'page' => 'fraud-tenant-disputes',
				'id'   => $dispute_id,
			),
			admin_url( 'admin.php' )
		);

		if ( 'review' === $sub_action ) {
			if ( ! DisputeService::can_review_disputes( $admin_id ) ) {
				wp_die( esc_html__( 'You do not have permission to review tenant disputes.', 'fraud-tenant-core' ), 403 );
			}
			$res = DisputeService::review_dispute( $dispute_id, $admin_id );
		} elseif ( 'resolve' === $sub_action ) {
			if ( ! DisputeService::can_resolve_disputes( $admin_id ) ) {
				wp_die( esc_html__( 'You do not have permission to resolve tenant disputes.', 'fraud-tenant-core' ), 403 );
			}
			$action = isset( $_POST['resolution_action'] ) ? sanitize_key( $_POST['resolution_action'] ) : '';
			$notes  = isset( $_POST['resolution_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['resolution_notes'] ) ) : '';
			$res    = DisputeService::resolve_dispute( $dispute_id, $admin_id, $action, $notes );
		} else {
			$res = array( 'success' => false, 'message' => __( 'Unknown dispute action.', 'fraud-tenant-core' ) );
		}

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

	/**
	 * Set transient flash notice for current admin user.
	 *
	 * @param string $type    'success' or 'error'.
	 * @param string $message Notice message.
	 */
	protected static function set_flash_notice( string $type, string $message ): void {
		$admin_id = get_current_user_id();
		if ( $admin_id > 0 ) {
			set_transient(
				'ft_dispute_notice_' . $admin_id,
				array(
					'type'    => $type,
					'message' => $message,
				),
				30
			);
		}
	}

	/**
	 * Render and delete any pending flash notice.
	 */
	protected static function render_flash_notice(): void {
		$admin_id = get_current_user_id();
		if ( $admin_id <= 0 ) {
			return;
		}

		$notice = get_transient( 'ft_dispute_notice_' . $admin_id );
		if ( $notice && is_array( $notice ) ) {
			delete_transient( 'ft_dispute_notice_' . $admin_id );
			$class   = 'error' === $notice['type'] ? 'notice-error' : 'notice-success';
			$message = esc_html( $notice['message'] );
			echo "<div class='notice {$class} is-dismissible' style='margin: 15px 0;'><p>{$message}</p></div>";
		}
	}
}

