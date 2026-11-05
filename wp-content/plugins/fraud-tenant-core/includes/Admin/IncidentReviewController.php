<?php
/**
 * Administrative Incident Review Controller.
 *
 * Renders the incident review queue, report detail interface, evidence inspection,
 * and handles review decision actions (approve, reject, request clarification).
 *
 * @package FraudTenant\Admin
 */

namespace FraudTenant\Admin;

use FraudTenant\Auth\Capabilities;
use FraudTenant\Services\DocumentService;
use FraudTenant\Services\IncidentReviewService;
use FraudTenant\Services\IncidentService;
use FraudTenant\Utils\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IncidentReviewController {

	/**
	 * Main entry point for the Incident Review admin page.
	 */
	public static function render(): void {
		$reviewer_id = get_current_user_id();

		if ( ! IncidentReviewService::can_review_incidents( $reviewer_id ) ) {
			wp_die( esc_html__( 'Unauthorized access to Fraud Tenant incident review portal.', 'fraud-tenant-core' ), 403 );
		}

		// BLK-08 Security Gate: Fail closed in production mode
		if ( ! \FraudTenant\Services\ComplianceService::is_data_gate_open() ) {
			self::render_locked_screen();
			return;
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';

		if ( 'review' === $action ) {
			self::render_detail();
		} else {
			self::render_list();
		}
	}

	/**
	 * Render BLK-08 production privacy lockdown screen.
	 */
	protected static function render_locked_screen(): void {
		?>
		<div class="wrap ft-admin-wrap" style="max-width: 900px; margin: 30px auto;">
			<div style="background: #FFF; border: 1px solid #E2E8F0; border-radius: 12px; padding: 40px; text-align: center; box-shadow: 0 4px 12px rgba(20,38,62,0.06);">
				<div style="width: 64px; height: 64px; background: #FEF3C7; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 20px;">
					<span class="dashicons dashicons-lock" style="font-size: 36px; width: 36px; height: 36px; color: #D97706;"></span>
				</div>
				<h1 style="color: #14263E; font-size: 24px; font-weight: 700; margin-bottom: 12px;">
					<?php esc_html_e( 'Incident Review Locked — BLK-08 Privacy Gate Active', 'fraud-tenant-core' ); ?>
				</h1>
				<p style="color: #64748B; font-size: 15px; max-width: 640px; margin: 0 auto 24px; line-height: 1.6;">
					<?php esc_html_e( 'Administrative incident review and evidence inspection are locked in production mode. Sensitive narrative descriptions and tenant PII remain fail-closed until application-level encryption at rest and database security policies (BLK-08) are formally approved and implemented.', 'fraud-tenant-core' ); ?>
				</p>
				<div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 16px; display: inline-block; text-align: left; font-size: 13px; color: #475569;">
					<div><strong><?php esc_html_e( 'Current Environment:', 'fraud-tenant-core' ); ?></strong> <code><?php echo esc_html( defined( 'WP_ENVIRONMENT_TYPE' ) ? constant( 'WP_ENVIRONMENT_TYPE' ) : 'undefined' ); ?></code></div>
					<div style="margin-top: 6px;"><strong><?php esc_html_e( 'Test Mode Flag:', 'fraud-tenant-core' ); ?></strong> <code><?php echo esc_html( defined( 'FT_INCIDENT_TEST_MODE' ) && true === constant( 'FT_INCIDENT_TEST_MODE' ) ? 'true' : 'false' ); ?></code></div>
					<div style="margin-top: 6px;"><strong><?php esc_html_e( 'Gate Status:', 'fraud-tenant-core' ); ?></strong> <span style="color: #DC2626; font-weight: 600;"><?php esc_html_e( 'FAIL-CLOSED (Locked)', 'fraud-tenant-core' ); ?></span></div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Incident Review Queue List.
	 */
	protected static function render_list(): void {
		$reviewer_id    = get_current_user_id();
		$current_status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : 'all';
		$search_query   = isset( $_GET['s'] ) ? sanitize_text_field( trim( $_GET['s'] ) ) : '';
		$paged          = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

		$queue_data = IncidentReviewService::get_review_queue(
			array(
				'status'   => $current_status,
				'search'   => $search_query,
				'page'     => $paged,
				'per_page' => 20,
			),
			$reviewer_id
		);

		$items         = $queue_data['items'] ?? array();
		$status_counts = $queue_data['status_counts'] ?? array( 'all' => 0, 'submitted' => 0, 'approved' => 0, 'rejected' => 0, 'needs_clarification' => 0 );
		$total_items   = $queue_data['total_items'] ?? 0;
		$total_pages   = $queue_data['total_pages'] ?? 1;

		$base_url = admin_url( 'admin.php?page=fraud-tenant-incidents' );
		?>
		<div class="wrap ft-admin-wrap" style="max-width: 1200px; margin: 20px auto 40px;">
			<h1 class="wp-heading-inline" style="color: #14263E; font-size: 24px; font-weight: 700; margin-bottom: 6px;">
				<span class="dashicons dashicons-shield" style="font-size:28px;width:28px;height:28px;vertical-align:middle;color:#0E9488;margin-right:6px;"></span>
				<?php esc_html_e( 'Fraud Tenant — Incident Review Queue', 'fraud-tenant-core' ); ?>
			</h1>
			<p class="description" style="color: #64748B; font-size: 14px; margin-bottom: 16px;">
				<?php esc_html_e( 'Moderate and verify property-owner submitted rental incidents. Administrative review confirms documentation completeness without authorizing public disclosure.', 'fraud-tenant-core' ); ?>
			</p>
			<hr class="wp-header-end">

			<?php self::render_flash_notices(); ?>

			<!-- Status Filter Tabs -->
			<ul class="subsubsub" style="margin: 14px 0 18px; font-size: 13px;">
				<li class="all">
					<a href="<?php echo esc_url( remove_query_arg( array( 'status', 'paged' ), $base_url ) ); ?>" class="<?php echo ( empty( $current_status ) || 'all' === $current_status ) ? 'current' : ''; ?>">
						<?php esc_html_e( 'All Reports', 'fraud-tenant-core' ); ?> <span class="count">(<?php echo (int) $status_counts['all']; ?>)</span>
					</a> |
				</li>
				<li class="submitted">
					<a href="<?php echo esc_url( add_query_arg( array( 'status' => 'submitted' ), $base_url ) ); ?>" class="<?php echo 'submitted' === $current_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Pending Review', 'fraud-tenant-core' ); ?> <span class="count">(<?php echo (int) $status_counts['submitted']; ?>)</span>
					</a> |
				</li>
				<li class="needs_clarification">
					<a href="<?php echo esc_url( add_query_arg( array( 'status' => 'needs_clarification' ), $base_url ) ); ?>" class="<?php echo 'needs_clarification' === $current_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Needs Clarification', 'fraud-tenant-core' ); ?> <span class="count">(<?php echo (int) $status_counts['needs_clarification']; ?>)</span>
					</a> |
				</li>
				<li class="approved">
					<a href="<?php echo esc_url( add_query_arg( array( 'status' => 'approved' ), $base_url ) ); ?>" class="<?php echo 'approved' === $current_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Approved (Internal)', 'fraud-tenant-core' ); ?> <span class="count">(<?php echo (int) $status_counts['approved']; ?>)</span>
					</a> |
				</li>
				<li class="rejected">
					<a href="<?php echo esc_url( add_query_arg( array( 'status' => 'rejected' ), $base_url ) ); ?>" class="<?php echo 'rejected' === $current_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Rejected', 'fraud-tenant-core' ); ?> <span class="count">(<?php echo (int) $status_counts['rejected']; ?>)</span>
					</a>
				</li>
			</ul>

			<!-- Search Bar -->
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="float: right; margin-bottom: 12px;">
				<input type="hidden" name="page" value="fraud-tenant-incidents" />
				<?php if ( ! empty( $current_status ) ) : ?>
					<input type="hidden" name="status" value="<?php echo esc_attr( $current_status ); ?>" />
				<?php endif; ?>
				<input type="search" name="s" value="<?php echo esc_attr( $search_query ); ?>" placeholder="<?php esc_attr_e( 'Search by reference, property, category...', 'fraud-tenant-core' ); ?>" style="width: 260px;" />
				<input type="submit" class="button" value="<?php esc_attr_e( 'Search', 'fraud-tenant-core' ); ?>" />
			</form>
			<div style="clear: both;"></div>

			<!-- Queue Table -->
			<div style="background: #FFF; border: 1px solid #E2E8F0; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
				<table class="wp-list-table widefat fixed striped table-view-list" style="border: none;">
					<thead>
						<tr>
							<th style="width: 120px; font-weight: 600; color: #14263E;"><?php esc_html_e( 'Reference', 'fraud-tenant-core' ); ?></th>
							<th style="width: 180px; font-weight: 600; color: #14263E;"><?php esc_html_e( 'Category', 'fraud-tenant-core' ); ?></th>
							<th style="font-weight: 600; color: #14263E;"><?php esc_html_e( 'Associated Property', 'fraud-tenant-core' ); ?></th>
							<th style="width: 160px; font-weight: 600; color: #14263E;"><?php esc_html_e( 'Submitting Owner', 'fraud-tenant-core' ); ?></th>
							<th style="width: 110px; font-weight: 600; color: #14263E;"><?php esc_html_e( 'Date', 'fraud-tenant-core' ); ?></th>
							<th style="width: 110px; font-weight: 600; color: #14263E;"><?php esc_html_e( 'Amount', 'fraud-tenant-core' ); ?></th>
							<th style="width: 140px; font-weight: 600; color: #14263E;"><?php esc_html_e( 'Status', 'fraud-tenant-core' ); ?></th>
							<th style="width: 120px; text-align: right; font-weight: 600; color: #14263E;"><?php esc_html_e( 'Action', 'fraud-tenant-core' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $items ) ) : ?>
							<tr>
								<td colspan="8" style="text-align: center; padding: 40px 20px; color: #64748B;">
									<span class="dashicons dashicons-yes-alt" style="font-size: 32px; width: 32px; height: 32px; color: #0E9488; margin-bottom: 8px; display: block; margin: 0 auto 8px;"></span>
									<p style="font-size: 15px; margin: 0; font-weight: 500;"><?php esc_html_e( 'No incident reports found matching the selected filter.', 'fraud-tenant-core' ); ?></p>
								</td>
							</tr>
						<?php else : ?>
							<?php foreach ( $items as $item ) : ?>
								<tr>
									<td>
										<strong style="color: #14263E; font-family: monospace; font-size: 13px;"><?php echo esc_html( $item->reference ); ?></strong>
										<?php if ( $item->is_self_report ) : ?>
											<div style="font-size: 10px; color: #D97706; font-weight: 600; text-transform: uppercase; margin-top: 2px;">
												<?php esc_html_e( 'Your Report', 'fraud-tenant-core' ); ?>
											</div>
										<?php endif; ?>
									</td>
									<td>
										<span style="font-weight: 500; color: #334155;"><?php echo esc_html( $item->category_label ); ?></span>
									</td>
									<td>
										<strong><?php echo esc_html( $item->property_title ?: __( 'Property ID #' . $item->property_id, 'fraud-tenant-core' ) ); ?></strong>
										<div style="font-size: 12px; color: #64748B; margin-top: 2px;">
											<?php echo esc_html( $item->property_street . ( $item->property_unit ? ' #' . $item->property_unit : '' ) . ', ' . $item->property_city . ', ' . $item->property_state ); ?>
										</div>
									</td>
									<td>
										<span style="color: #334155;"><?php echo esc_html( $item->owner_name ?: 'User #' . $item->owner_id ); ?></span>
										<div style="font-size: 11px; color: #94A3B8;"><?php echo esc_html( $item->owner_email ); ?></div>
									</td>
									<td>
										<span style="color: #64748B; font-size: 12px;"><?php echo esc_html( $item->incident_date ); ?></span>
									</td>
									<td>
										<span style="font-weight: 600; color: #14263E;"><?php echo esc_html( $item->amount_formatted ); ?></span>
									</td>
									<td>
										<?php self::render_status_badge( $item->status ); ?>
									</td>
									<td style="text-align: right;">
										<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'fraud-tenant-incidents', 'action' => 'review', 'report_id' => $item->id ), admin_url( 'admin.php' ) ) ); ?>" class="button button-small" style="color: #14263E; border-color: #CBD5E1; font-weight: 600;">
											<?php esc_html_e( 'Inspect', 'fraud-tenant-core' ); ?>
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
				<div class="tablenav bottom" style="margin-top: 14px;">
					<div class="tablenav-pages">
						<span class="displaying-num"><?php echo sprintf( esc_html__( '%d reports', 'fraud-tenant-core' ), $total_items ); ?></span>
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
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the Incident Report Review Detail Interface.
	 */
	protected static function render_detail(): void {
		$reviewer_id = get_current_user_id();
		$report_id   = isset( $_GET['report_id'] ) ? (int) $_GET['report_id'] : 0;

		$detail_data = IncidentReviewService::get_incident_for_review( $report_id, $reviewer_id );

		if ( empty( $detail_data['success'] ) ) {
			?>
			<div class="wrap ft-admin-wrap" style="max-width: 900px; margin: 30px auto;">
				<div class="notice notice-error" style="padding: 16px; border-radius: 8px;">
					<p style="font-size: 15px; margin: 0; font-weight: 600;">
						<?php echo esc_html( $detail_data['message'] ?? __( 'Could not load incident report record.', 'fraud-tenant-core' ) ); ?>
					</p>
				</div>
				<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=fraud-tenant-incidents' ) ); ?>" class="button">&larr; <?php esc_html_e( 'Back to Incident Queue', 'fraud-tenant-core' ); ?></a></p>
			</div>
			<?php
			return;
		}

		$report          = $detail_data['report'];
		$evidence_docs   = $detail_data['evidence_docs'] ?? array();
		$review_history  = $detail_data['review_history'] ?? array();
		$rejection_codes = $detail_data['rejection_reasons'] ?? array();
		$clarify_codes   = $detail_data['clarify_reasons'] ?? array();
		$is_decidable    = ( Constants::INCIDENT_STATUS_SUBMITTED === $report->status && ! $report->is_self_report );
		$nonce           = wp_create_nonce( 'ft_admin_incident_review_' . $report->id );
		?>
		<div class="wrap ft-admin-wrap" style="max-width: 1200px; margin: 20px auto 40px;">
			<p style="margin-bottom: 12px;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=fraud-tenant-incidents' ) ); ?>" class="button" style="color: #64748B;">
					&larr; <?php esc_html_e( 'Back to Review Queue', 'fraud-tenant-core' ); ?>
				</a>
			</p>

			<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
				<div>
					<h1 style="color: #14263E; font-size: 24px; font-weight: 700; margin: 0 0 6px;">
						<?php echo sprintf( esc_html__( 'Incident Report %s', 'fraud-tenant-core' ), esc_html( $report->reference ) ); ?>
					</h1>
					<p style="color: #64748B; margin: 0; font-size: 14px;">
						<?php echo sprintf( esc_html__( 'Submitted on %s by %s', 'fraud-tenant-core' ), esc_html( $report->created_at ), esc_html( $report->owner_name ) ); ?>
					</p>
				</div>
				<div>
					<?php self::render_status_badge( $report->status ); ?>
				</div>
			</div>

			<?php self::render_flash_notices(); ?>

			<!-- 2-Column PropTech Layout -->
			<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
				<!-- Left Column: Details, Evidence, Narrative, History -->
				<div>
					<!-- Allegation & Category Card -->
					<div style="background: #FFF; border: 1px solid #E2E8F0; border-radius: 8px; padding: 24px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
						<h2 style="font-size: 16px; font-weight: 700; color: #14263E; margin-top: 0; margin-bottom: 16px; border-bottom: 1px solid #F1F5F9; padding-bottom: 10px;">
							<?php esc_html_e( 'Incident Allegation Details', 'fraud-tenant-core' ); ?>
						</h2>
						<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
							<div>
								<span style="font-size: 12px; color: #64748B; text-transform: uppercase; font-weight: 600; display: block;"><?php esc_html_e( 'Incident Category', 'fraud-tenant-core' ); ?></span>
								<strong style="font-size: 15px; color: #14263E;"><?php echo esc_html( $report->category_label ); ?></strong>
							</div>
							<div>
								<span style="font-size: 12px; color: #64748B; text-transform: uppercase; font-weight: 600; display: block;"><?php esc_html_e( 'Amount Involved', 'fraud-tenant-core' ); ?></span>
								<strong style="font-size: 16px; color: #0E9488;"><?php echo esc_html( $report->amount_formatted ); ?></strong>
							</div>
							<div>
								<span style="font-size: 12px; color: #64748B; text-transform: uppercase; font-weight: 600; display: block;"><?php esc_html_e( 'Date of Occurrence', 'fraud-tenant-core' ); ?></span>
								<strong style="font-size: 14px; color: #14263E;"><?php echo esc_html( $report->incident_date ); ?></strong>
							</div>
							<div>
								<span style="font-size: 12px; color: #64748B; text-transform: uppercase; font-weight: 600; display: block;"><?php esc_html_e( 'Tenant Self-View (own dashboard)', 'fraud-tenant-core' ); ?></span>
								<span style="background: #F1F5F9; color: #475569; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">
									<?php echo (int) $report->disclosable === 0 ? esc_html__( 'Internal Only (Non-Disclosable)', 'fraud-tenant-core' ) : esc_html__( 'Disclosable', 'fraud-tenant-core' ); ?>
								</span>
							</div>
						</div>

						<h3 style="font-size: 13px; font-weight: 700; color: #64748B; text-transform: uppercase; margin: 16px 0 8px;">
							<?php esc_html_e( 'Factual Description (Submitted Allegation)', 'fraud-tenant-core' ); ?>
						</h3>
						<div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 6px; padding: 14px; font-size: 14px; color: #334155; line-height: 1.6; white-space: pre-wrap;">
							<?php echo esc_html( $report->factual_description ); ?>
						</div>

						<div style="margin-top: 16px; padding: 10px 14px; background: #FEF3C7; border-left: 4px solid #D97706; border-radius: 4px; font-size: 12px; color: #92400E;">
							<strong><?php esc_html_e( 'Legal Notice:', 'fraud-tenant-core' ); ?></strong>
							<?php esc_html_e( 'Allegations represent unproven claims submitted by the property owner under penalty of account termination. Administrative review does not constitute legal adjudication.', 'fraud-tenant-core' ); ?>
						</div>
					</div>

					<!-- Attached Evidence Documents -->
					<div style="background: #FFF; border: 1px solid #E2E8F0; border-radius: 8px; padding: 24px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
						<h2 style="font-size: 16px; font-weight: 700; color: #14263E; margin-top: 0; margin-bottom: 16px; border-bottom: 1px solid #F1F5F9; padding-bottom: 10px;">
							<span class="dashicons dashicons-media-document" style="color: #0E9488; margin-right: 4px;"></span>
							<?php esc_html_e( 'Supporting Evidence Documentation', 'fraud-tenant-core' ); ?>
						</h2>

						<?php if ( empty( $evidence_docs ) ) : ?>
							<p style="color: #64748B; font-size: 14px; margin: 0;">
								<?php esc_html_e( 'No supporting evidence files were attached to this incident submission.', 'fraud-tenant-core' ); ?>
							</p>
						<?php else : ?>
							<ul style="margin: 0; padding: 0; list-style: none;">
								<?php foreach ( $evidence_docs as $doc ) : ?>
									<li style="display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; border: 1px solid #E2E8F0; border-radius: 6px; margin-bottom: 8px; background: #F8FAFC;">
										<div>
											<strong style="color: #14263E; font-size: 14px;"><?php echo esc_html( $doc->original_name ); ?></strong>
											<div style="font-size: 12px; color: #64748B; margin-top: 2px;">
												<span><?php echo esc_html( $doc->file_size_fmt ); ?></span> &bull; 
												<span><?php echo esc_html( $doc->mime_type ); ?></span> &bull; 
												<span><?php echo sprintf( esc_html__( 'Uploaded: %s', 'fraud-tenant-core' ), esc_html( $doc->uploaded_at ) ); ?></span>
											</div>
										</div>
										<div>
											<a href="<?php echo esc_url( $doc->view_url ); ?>" class="button button-secondary" target="_blank" style="font-size: 12px; font-weight: 600;">
												<span class="dashicons dashicons-visibility" style="vertical-align: middle; font-size: 16px; width: 16px; height: 16px;"></span>
												<?php esc_html_e( 'Inspect Evidence', 'fraud-tenant-core' ); ?>
											</a>
										</div>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>

					<!-- Review History Trail -->
					<div style="background: #FFF; border: 1px solid #E2E8F0; border-radius: 8px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
						<h2 style="font-size: 16px; font-weight: 700; color: #14263E; margin-top: 0; margin-bottom: 16px; border-bottom: 1px solid #F1F5F9; padding-bottom: 10px;">
							<span class="dashicons dashicons-backup" style="color: #0E9488; margin-right: 4px;"></span>
							<?php esc_html_e( 'Administrative Review History Trail', 'fraud-tenant-core' ); ?>
						</h2>

						<?php if ( empty( $review_history ) ) : ?>
							<p style="color: #64748B; font-size: 14px; margin: 0;">
								<?php esc_html_e( 'No review decisions recorded yet. This incident report is awaiting initial moderation.', 'fraud-tenant-core' ); ?>
							</p>
						<?php else : ?>
							<div class="ft-review-timeline" style="border-left: 2px solid #E2E8F0; margin-left: 12px; padding-left: 18px;">
								<?php foreach ( $review_history as $rev ) : ?>
									<div style="position: relative; margin-bottom: 18px;">
										<div style="position: absolute; left: -25px; top: 2px; width: 12px; height: 12px; border-radius: 50%; background: #0E9488; border: 2px solid #FFF;"></div>
										<div style="display: flex; justify-content: space-between; align-items: baseline;">
											<strong style="color: #14263E; font-size: 13px;">
												<?php echo esc_html( ucwords( str_replace( '_', ' ', $rev->decision ) ) ); ?>
											</strong>
											<span style="font-size: 11px; color: #94A3B8;"><?php echo esc_html( $rev->formatted_date ); ?></span>
										</div>
										<div style="font-size: 12px; color: #475569; margin-top: 4px;">
											<span><?php esc_html_e( 'Reviewer:', 'fraud-tenant-core' ); ?> <strong><?php echo esc_html( $rev->reviewer_name ?: 'Reviewer #' . $rev->reviewer_id ); ?></strong></span>
											<?php if ( ! empty( $rev->reason_code ) ) : ?>
												&bull; <span><?php esc_html_e( 'Reason:', 'fraud-tenant-core' ); ?> <code><?php echo esc_html( $rev->reason_code ); ?></code></span>
											<?php endif; ?>
										</div>
										<?php if ( ! empty( $rev->internal_notes ) ) : ?>
											<div style="margin-top: 6px; font-size: 12px; color: #64748B; background: #F8FAFC; padding: 6px 10px; border-radius: 4px;">
												<?php echo esc_html( $rev->internal_notes ); ?>
											</div>
										<?php endif; ?>
									</div>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
				</div>

				<!-- Right Column: Associated Property, Owner, Decision Actions -->
				<div>
					<!-- Associated Property Card -->
					<div style="background: #FFF; border: 1px solid #E2E8F0; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
						<h3 style="font-size: 14px; font-weight: 700; color: #14263E; margin-top: 0; margin-bottom: 12px; border-bottom: 1px solid #F1F5F9; padding-bottom: 8px;">
							<span class="dashicons dashicons-admin-home" style="color: #0E9488; margin-right: 4px;"></span>
							<?php esc_html_e( 'Associated Property', 'fraud-tenant-core' ); ?>
						</h3>
						<strong style="font-size: 15px; color: #14263E; display: block; margin-bottom: 4px;">
							<?php echo esc_html( $report->property_title ?: __( 'Rental Property', 'fraud-tenant-core' ) ); ?>
						</strong>
						<div style="font-size: 13px; color: #475569; line-height: 1.5;">
							<?php echo esc_html( $report->property_street ); ?><br>
							<?php if ( ! empty( $report->property_unit ) ) : ?>
								<?php echo sprintf( esc_html__( 'Unit %s', 'fraud-tenant-core' ), esc_html( $report->property_unit ) ); ?><br>
							<?php endif; ?>
							<?php echo esc_html( $report->property_city . ', ' . $report->property_state . ' ' . $report->property_zip ); ?>
						</div>
					</div>

					<!-- Submitting Owner Card -->
					<div style="background: #FFF; border: 1px solid #E2E8F0; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
						<h3 style="font-size: 14px; font-weight: 700; color: #14263E; margin-top: 0; margin-bottom: 12px; border-bottom: 1px solid #F1F5F9; padding-bottom: 8px;">
							<span class="dashicons dashicons-admin-users" style="color: #0E9488; margin-right: 4px;"></span>
							<?php esc_html_e( 'Submitting Owner', 'fraud-tenant-core' ); ?>
						</h3>
						<strong style="font-size: 14px; color: #14263E; display: block;">
							<?php echo esc_html( $report->owner_name ); ?>
						</strong>
						<div style="font-size: 12px; color: #64748B; margin-top: 2px;">
							<?php echo esc_html( $report->owner_email ); ?>
						</div>
						<div style="margin-top: 8px; font-size: 11px; color: #0E9488; font-weight: 600;">
							<span class="dashicons dashicons-yes" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle;"></span>
							<?php esc_html_e( 'Attestation Statement v1.0 Recorded', 'fraud-tenant-core' ); ?>
						</div>
					</div>

					<!-- Tenant (Subject) Identity Card -->
					<?php
					$ft_subject = \FraudTenant\Services\IncidentService::get_subject_display( $report );
					$ft_scr_msg = get_transient( 'ft_admin_scr_notice_' . get_current_user_id() );
					if ( $ft_scr_msg ) {
						delete_transient( 'ft_admin_scr_notice_' . get_current_user_id() );
					}
					?>
					<div class="ft-subject-card" style="background: #FFF; border: 1px solid #E2E8F0; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
						<h3 style="font-size: 14px; font-weight: 700; color: #14263E; margin-top: 0; margin-bottom: 12px; border-bottom: 1px solid #F1F5F9; padding-bottom: 8px;">
							<span class="dashicons dashicons-id" style="color: #0E9488; margin-right: 4px;"></span>
							<?php esc_html_e( 'Reported Tenant', 'fraud-tenant-core' ); ?>
						</h3>
						<?php if ( $ft_subject['has_subject'] ) : ?>
							<strong style="font-size: 14px; color: #14263E; display: block;"><?php echo esc_html( trim( $ft_subject['first'] . ' ' . $ft_subject['last'] ) ?: __( '(name unavailable)', 'fraud-tenant-core' ) ); ?></strong>
							<div style="font-size: 12px; color: #64748B; margin-top: 2px;"><?php echo esc_html( sprintf( __( 'SSN ending %s (full SSN never stored)', 'fraud-tenant-core' ), $ft_subject['last4'] ) ); ?></div>
						<?php else : ?>
							<div style="font-size: 12px; color: #92400E;"><?php esc_html_e( 'No tenant identity was captured on this report (filed before identity capture). It can only be linked manually under Report Visibility and cannot appear in owner screening.', 'fraud-tenant-core' ); ?></div>
						<?php endif; ?>
					</div>

					<!-- Owner Screening Disclosure Card -->
					<div class="ft-screening-disclosure-card" style="background: #FFF; border: 1px solid #E2E8F0; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
						<h3 style="font-size: 14px; font-weight: 700; color: #14263E; margin-top: 0; margin-bottom: 12px; border-bottom: 1px solid #F1F5F9; padding-bottom: 8px;">
							<span class="dashicons dashicons-search" style="color: #0E9488; margin-right: 4px;"></span>
							<?php esc_html_e( 'Owner Screening Disclosure', 'fraud-tenant-core' ); ?>
						</h3>
						<?php if ( is_array( $ft_scr_msg ) ) : ?>
							<div class="notice notice-<?php echo ! empty( $ft_scr_msg['success'] ) ? 'success' : 'error'; ?> inline" style="margin: 0 0 10px;"><p><?php echo esc_html( (string) $ft_scr_msg['message'] ); ?></p></div>
						<?php endif; ?>
						<?php $ft_released = 1 === (int) ( $report->screening_disclosure ?? 0 ); ?>
						<p style="font-size: 12px; color: #475569; margin: 0 0 10px;">
							<?php if ( $ft_released ) : ?>
								<strong style="color: #047857;"><?php esc_html_e( 'Released:', 'fraud-tenant-core' ); ?></strong>
								<?php esc_html_e( 'other verified owners who search this tenant (name + SSN, with consent) will see this approved record.', 'fraud-tenant-core' ); ?>
							<?php else : ?>
								<strong><?php esc_html_e( 'Not released:', 'fraud-tenant-core' ); ?></strong>
								<?php esc_html_e( 'this record never appears in other owners\' screening results.', 'fraud-tenant-core' ); ?>
							<?php endif; ?>
						</p>
						<?php if ( ! \FraudTenant\Services\ComplianceService::owner_search_ready() ) : ?>
							<p style="font-size: 11px; color: #92400E; margin: 0 0 10px;"><?php esc_html_e( 'Owner screening is currently disabled platform-wide (Platform Settings → Compliance Gates). Released records stay hidden until it is enabled.', 'fraud-tenant-core' ); ?></p>
						<?php endif; ?>
						<?php if ( \FraudTenant\Services\TenantScreeningService::can_manage_disclosure( get_current_user_id() ) && ! $report->is_self_report ) : ?>
							<?php if ( $ft_released || ( Constants::INCIDENT_STATUS_APPROVED === $report->status && $ft_subject['has_subject'] ) ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( $ft_released ? __( 'Withdraw this report from owner screening results?', 'fraud-tenant-core' ) : __( 'Release this APPROVED report to owner screening results?', 'fraud-tenant-core' ) ); ?>');">
									<input type="hidden" name="action" value="ft_admin_screening_disclosure" />
									<input type="hidden" name="report_id" value="<?php echo (int) $report->id; ?>" />
									<input type="hidden" name="enable" value="<?php echo $ft_released ? '0' : '1'; ?>" />
									<?php wp_nonce_field( 'ft_screening_disclosure_' . (int) $report->id ); ?>
									<button type="submit" class="button <?php echo $ft_released ? 'button-secondary' : 'button-primary'; ?>" style="width:100%;<?php echo $ft_released ? '' : ' background:#0E9488; border-color:#0E9488;'; ?>">
										<?php echo $ft_released ? esc_html__( 'Withdraw from Screening', 'fraud-tenant-core' ) : esc_html__( 'Release to Owner Screening', 'fraud-tenant-core' ); ?>
									</button>
								</form>
							<?php else : ?>
								<p style="font-size: 11px; color: #64748B; margin: 0;"><?php esc_html_e( 'Available after the report is approved and has a protected tenant identity.', 'fraud-tenant-core' ); ?></p>
							<?php endif; ?>
						<?php endif; ?>
					</div>

					<!-- Administrative Decision Card -->
					<div style="background: #FFF; border: 1px solid #E2E8F0; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
						<h3 style="font-size: 14px; font-weight: 700; color: #14263E; margin-top: 0; margin-bottom: 12px; border-bottom: 1px solid #F1F5F9; padding-bottom: 8px;">
							<span class="dashicons dashicons-hammer" style="color: #0E9488; margin-right: 4px;"></span>
							<?php esc_html_e( 'Administrative Action', 'fraud-tenant-core' ); ?>
						</h3>

						<?php if ( $report->is_self_report ) : ?>
							<!-- Self-Decision Blocked Notice -->
							<div style="background: #FEF3C7; border: 1px solid #FCD34D; border-radius: 6px; padding: 14px; font-size: 13px; color: #92400E; line-height: 1.5;">
								<strong><span class="dashicons dashicons-warning" style="vertical-align: middle; margin-right: 4px;"></span><?php esc_html_e( 'Self-Decision Blocked', 'fraud-tenant-core' ); ?></strong>
								<p style="margin: 6px 0 0;">
									<?php esc_html_e( 'You submitted this incident report. In accordance with platform governance, an owner cannot decide the outcome of their own report. You may inspect the details and evidence, but another authorized administrator must review and decide this record.', 'fraud-tenant-core' ); ?>
								</p>
							</div>
						<?php elseif ( Constants::INCIDENT_STATUS_SUBMITTED !== $report->status ) : ?>
							<!-- Already Moderated Notice -->
							<div style="background: #F1F5F9; border: 1px solid #E2E8F0; border-radius: 6px; padding: 14px; font-size: 13px; color: #475569; line-height: 1.5;">
								<strong><span class="dashicons dashicons-lock" style="vertical-align: middle; margin-right: 4px;"></span><?php esc_html_e( 'Report Moderated', 'fraud-tenant-core' ); ?></strong>
								<p style="margin: 6px 0 0;">
									<?php echo sprintf( esc_html__( 'This incident report has been decided as "%s". Re-evaluations require an authorized reopening procedure.', 'fraud-tenant-core' ), esc_html( $report->status ) ); ?>
								</p>
							</div>
						<?php else : ?>
							<!-- Decision Form -->
							<p style="font-size: 13px; color: #64748B; margin-top: 0; margin-bottom: 16px;">
								<?php esc_html_e( 'Select an administrative decision. All actions are final and recorded in the audit history.', 'fraud-tenant-core' ); ?>
							</p>

							<!-- Form 1: Approve -->
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom: 16px;" onsubmit="return confirm('<?php esc_attr_e( 'Are you sure you want to APPROVE this incident report for internal records?', 'fraud-tenant-core' ); ?>');">
								<input type="hidden" name="action" value="ft_admin_incident_review_action" />
								<input type="hidden" name="report_id" value="<?php echo (int) $report->id; ?>" />
								<input type="hidden" name="decision" value="approve" />
								<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />
								<input type="hidden" name="reason_code" value="evidence_verified" />
								<button type="submit" class="button button-primary button-large" style="width: 100%; background: #0E9488; border-color: #0E9488; font-weight: 600;">
									<span class="dashicons dashicons-yes" style="vertical-align: middle;"></span>
									<?php esc_html_e( 'Approve Report', 'fraud-tenant-core' ); ?>
								</button>
							</form>

							<hr style="border: 0; border-top: 1px solid #E2E8F0; margin: 16px 0;">

							<!-- Form 2: Request Clarification -->
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom: 16px;" onsubmit="return confirm('<?php esc_attr_e( 'Request clarification from the submitting owner?', 'fraud-tenant-core' ); ?>');">
								<input type="hidden" name="action" value="ft_admin_incident_review_action" />
								<input type="hidden" name="report_id" value="<?php echo (int) $report->id; ?>" />
								<input type="hidden" name="decision" value="request_clarification" />
								<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />
								<label for="ft-clarify-reason" style="font-size: 12px; font-weight: 600; color: #475569; display: block; margin-bottom: 4px;">
									<?php esc_html_e( 'Clarification Reason (Controlled):', 'fraud-tenant-core' ); ?>
								</label>
								<select id="ft-clarify-reason" name="reason_code" style="width: 100%; margin-bottom: 8px; font-size: 13px;" required>
									<option value=""><?php esc_html_e( '— Select Reason —', 'fraud-tenant-core' ); ?></option>
									<?php foreach ( $clarify_codes as $code => $label ) : ?>
										<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
								<button type="submit" class="button button-secondary" style="width: 100%; color: #D97706; border-color: #FCD34D; font-weight: 600;">
									<span class="dashicons dashicons-editor-help" style="vertical-align: middle;"></span>
									<?php esc_html_e( 'Request Clarification', 'fraud-tenant-core' ); ?>
								</button>
							</form>

							<hr style="border: 0; border-top: 1px solid #E2E8F0; margin: 16px 0;">

							<!-- Form 3: Reject -->
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php esc_attr_e( 'Are you sure you want to REJECT this incident report? This action is permanent.', 'fraud-tenant-core' ); ?>');">
								<input type="hidden" name="action" value="ft_admin_incident_review_action" />
								<input type="hidden" name="report_id" value="<?php echo (int) $report->id; ?>" />
								<input type="hidden" name="decision" value="reject" />
								<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />
								<label for="ft-reject-reason" style="font-size: 12px; font-weight: 600; color: #475569; display: block; margin-bottom: 4px;">
									<?php esc_html_e( 'Rejection Reason (Controlled):', 'fraud-tenant-core' ); ?>
								</label>
								<select id="ft-reject-reason" name="reason_code" style="width: 100%; margin-bottom: 8px; font-size: 13px;" required>
									<option value=""><?php esc_html_e( '— Select Reason —', 'fraud-tenant-core' ); ?></option>
									<?php foreach ( $rejection_codes as $code => $label ) : ?>
										<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
								<button type="submit" class="button button-secondary" style="width: 100%; color: #DC2626; border-color: #FCA5A5; font-weight: 600;">
									<span class="dashicons dashicons-dismiss" style="vertical-align: middle;"></span>
									<?php esc_html_e( 'Reject Incident Report', 'fraud-tenant-core' ); ?>
								</button>
							</form>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Helper to render consistent status badge.
	 *
	 * @param string $status Incident status code.
	 */
	protected static function render_status_badge( string $status ): void {
		$bg    = '#F1F5F9';
		$color = '#475569';
		$label = ucwords( str_replace( '_', ' ', $status ) );

		switch ( $status ) {
			case 'submitted':
				$bg    = '#EFF6FF';
				$color = '#2563EB';
				$label = __( 'Pending Review', 'fraud-tenant-core' );
				break;
			case 'approved':
				$bg    = '#ECFDF5';
				$color = '#0E9488';
				$label = __( 'Approved (Internal)', 'fraud-tenant-core' );
				break;
			case 'rejected':
				$bg    = '#FEF2F2';
				$color = '#DC2626';
				$label = __( 'Rejected', 'fraud-tenant-core' );
				break;
			case 'needs_clarification':
				$bg    = '#FFFBEB';
				$color = '#D97706';
				$label = __( 'Needs Clarification', 'fraud-tenant-core' );
				break;
		}

		echo '<span style="display:inline-block;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;background:' . esc_attr( $bg ) . ';color:' . esc_attr( $color ) . ';">' . esc_html( $label ) . '</span>';
	}

	/**
	 * Helper to render admin flash notice banner.
	 */
	protected static function render_flash_notices(): void {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return;
		}

		$transient_key = 'ft_admin_incident_notice_' . $user_id;
		$notice        = get_transient( $transient_key );

		if ( $notice && is_array( $notice ) ) {
			delete_transient( $transient_key );
			$class = ( 'success' === ( $notice['type'] ?? '' ) ) ? 'notice-success' : 'notice-error';
			?>
			<div class="notice <?php echo esc_attr( $class ); ?> is-dismissible" style="margin: 12px 0 20px;">
				<p><strong><?php echo esc_html( $notice['message'] ?? '' ); ?></strong></p>
			</div>
			<?php
		}
	}
}

