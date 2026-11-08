<?php
/**
 * Tenant Dispute Workflow Service.
 *
 * Handles tenant dispute submissions against disclosed incident reports,
 * anti-duplicate logic, private evidence attachment, administrative review queues,
 * multi-tier resolution workflows, notifications, and append-only audit logging.
 *
 * @package FraudTenant\Services
 */

namespace FraudTenant\Services;

use FraudTenant\Auth\Capabilities;
use FraudTenant\Services\AuditLogger;
use FraudTenant\Services\DocumentService;
use FraudTenant\Services\IncidentService;
use FraudTenant\Services\ReportVisibilityService;
use FraudTenant\Utils\Constants;
use FraudTenant\Utils\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DisputeService {

	/**
	 * Supported dispute statuses.
	 */
	public const STATUS_PENDING      = 'pending';
	public const STATUS_UNDER_REVIEW = 'under_review';
	public const STATUS_RESOLVED     = 'resolved';
	public const STATUS_REJECTED     = 'rejected';

	/**
	 * Supported dispute reasons.
	 */
	public const REASON_INACCURATE       = 'inaccurate_record';
	public const REASON_INCORRECT_AMOUNT = 'incorrect_amount';
	public const REASON_IDENTITY_MISTAKE = 'identity_mistake';
	public const REASON_ALREADY_PAID     = 'already_paid';
	public const REASON_UNAUTHORIZED     = 'unauthorized_report';
	public const REASON_OTHER            = 'other';

	/**
	 * Supported resolution action verbs.
	 */
	public const ACTION_UPHELD    = 'resolve_upheld';
	public const ACTION_REJECTED  = 'resolve_rejected';
	public const ACTION_CORRECTED = 'resolve_corrected';

	/**
	 * Get allowed dispute reasons with user-facing labels.
	 *
	 * @return array<string, string>
	 */
	public static function get_allowed_reasons(): array {
		return array(
			self::REASON_INACCURATE       => __( 'Inaccurate Record', 'fraud-tenant-core' ),
			self::REASON_INCORRECT_AMOUNT => __( 'Incorrect Amount / Disputed Balance', 'fraud-tenant-core' ),
			self::REASON_IDENTITY_MISTAKE => __( 'Mistaken Identity / Not Me', 'fraud-tenant-core' ),
			self::REASON_ALREADY_PAID     => __( 'Balance Already Paid / Satisfied', 'fraud-tenant-core' ),
			self::REASON_UNAUTHORIZED     => __( 'Unauthorized Report / Invalid Tenancy', 'fraud-tenant-core' ),
			self::REASON_OTHER            => __( 'Other Factual Inaccuracy', 'fraud-tenant-core' ),
		);
	}

	/**
	 * Get dispute status labels.
	 *
	 * @return array<string, string>
	 */
	public static function get_status_labels(): array {
		return array(
			self::STATUS_PENDING      => __( 'Pending Review', 'fraud-tenant-core' ),
			self::STATUS_UNDER_REVIEW => __( 'Under Review', 'fraud-tenant-core' ),
			self::STATUS_RESOLVED     => __( 'Resolved', 'fraud-tenant-core' ),
			self::STATUS_REJECTED     => __( 'Rejected', 'fraud-tenant-core' ),
		);
	}

	/**
	 * Get status badge CSS class.
	 *
	 * @param string $status Dispute status.
	 * @return string CSS class name.
	 */
	public static function get_status_badge_class( string $status ): string {
		return match ( $status ) {
			self::STATUS_PENDING      => 'badge-dash-pending',
			self::STATUS_UNDER_REVIEW => 'badge-dash-review',
			self::STATUS_RESOLVED     => 'badge-dash-verified',
			self::STATUS_REJECTED     => 'badge-dash-rejected',
			default                   => 'badge-dash-muted',
		};
	}

	/**
	 * Check if a user can review tenant disputes.
	 *
	 * @param int $user_id WordPress User ID.
	 * @return bool
	 */
	public static function can_review_disputes( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}
		return user_can( $user_id, Capabilities::REVIEW_TENANT_DISPUTES ) ||
			   user_can( $user_id, 'manage_options' );
	}

	/**
	 * Check if a user can resolve tenant disputes.
	 *
	 * @param int $user_id WordPress User ID.
	 * @return bool
	 */
	public static function can_resolve_disputes( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}
		return user_can( $user_id, Capabilities::RESOLVE_TENANT_DISPUTES ) ||
			   user_can( $user_id, 'manage_options' );
	}

	/**
	 * Verify whether a tenant is permitted to submit a dispute against a report.
	 *
	 * Requires:
	 * 1. BLK-08 security gate: \FraudTenant\Services\ComplianceService::is_data_gate_open().
	 * 2. Authenticated tenant with dispute capability.
	 * 3. Active tenant association to the report.
	 * 4. Visibility status is 'visible'.
	 * 5. Incident report status is 'approved' and disclosable = 1.
	 * 6. No existing active dispute (pending or under_review) for this report and tenant.
	 *
	 * @param int $report_id       Incident report ID.
	 * @param int $tenant_user_id  Tenant WordPress user ID.
	 * @return array{allowed: bool, error?: string, message?: string, report?: object}
	 */
	public static function can_submit_dispute( int $report_id, int $tenant_user_id ): array {
		global $wpdb;

		// 0. BLK-08 Security Gate
		if ( ! \FraudTenant\Services\ComplianceService::is_data_gate_open() ) {
			return array(
				'allowed' => false,
				'error'   => 'submission_locked_blk08',
				'message' => __( 'Dispute submission is currently locked pending completion of sensitive PII encryption infrastructure (BLK-08). Non-production test mode is required for synthetic testing.', 'fraud-tenant-core' ),
			);
		}

		// 1. User & Capability Verification
		if ( $tenant_user_id <= 0 ) {
			return array(
				'allowed' => false,
				'error'   => 'unauthenticated',
				'message' => __( 'Authentication required to submit a dispute.', 'fraud-tenant-core' ),
			);
		}

		if ( ! user_can( $tenant_user_id, Capabilities::SUBMIT_TENANT_DISPUTE ) && ! user_can( $tenant_user_id, 'ft_tenant' ) ) {
			return array(
				'allowed' => false,
				'error'   => 'unauthorized_role',
				'message' => __( 'Only registered tenants may submit dispute statements.', 'fraud-tenant-core' ),
			);
		}

		if ( $report_id <= 0 ) {
			return array(
				'allowed' => false,
				'error'   => 'invalid_report_id',
				'message' => __( 'Invalid incident report specified.', 'fraud-tenant-core' ),
			);
		}

		// 2. The report must be visible to this tenant: approved, tenant-disclosed and linked to the
		//    identity the tenant has verifiably claimed (ReportVisibilityService rule 2).
		if ( ! ReportVisibilityService::tenant_can_view( $report_id, $tenant_user_id ) ) {
			return array(
				'allowed' => false,
				'error'   => 'not_associated_or_not_visible',
				'message' => __( 'You can only dispute reviewed records that are associated with your verified identity.', 'fraud-tenant-core' ),
			);
		}

		// 3. Verify Report is Approved and Disclosable
		$rep_table = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;
		$report    = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, status, disclosable, category, amount_involved, incident_date, property_id, owner_id 
				 FROM {$rep_table} WHERE id = %d LIMIT 1",
				$report_id
			)
		);

		if ( ! $report ) {
			return array(
				'allowed' => false,
				'error'   => 'report_not_found',
				'message' => __( 'Incident report record not found.', 'fraud-tenant-core' ),
			);
		}

		if ( Constants::INCIDENT_STATUS_APPROVED !== $report->status || 1 !== (int) $report->disclosable ) {
			return array(
				'allowed' => false,
				'error'   => 'report_not_disclosable',
				'message' => __( 'Only approved and disclosable incident reports can be disputed.', 'fraud-tenant-core' ),
			);
		}

		// 4. Duplicate Active Dispute Prevention
		$dsp_table = $wpdb->prefix . Constants::TABLE_TENANT_DISPUTES;
		$existing_active = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$dsp_table} 
				 WHERE report_id = %d AND tenant_id = %d 
				   AND status IN ('pending', 'under_review') 
				 LIMIT 1",
				$report_id,
				$tenant_user_id
			)
		);

		if ( $existing_active ) {
			return array(
				'allowed' => false,
				'error'   => 'duplicate_active_dispute',
				'message' => __( 'You already have an active dispute pending review for this report. Multiple active disputes for the same record are not permitted.', 'fraud-tenant-core' ),
			);
		}

		return array(
			'allowed' => true,
			'report'  => $report,
		);
	}

	/**
	 * Submit a new tenant dispute against an associated incident report.
	 *
	 * @param int         $report_id       Incident Report ID.
	 * @param int         $tenant_user_id  Tenant User ID.
	 * @param string      $reason          Dispute reason code.
	 * @param string      $statement       Tenant factual statement.
	 * @param array|null  $evidence_file   Optional uploaded evidence file ($_FILES entry).
	 * @return array Result array with success status, dispute_id, or error.
	 */
	public static function submit_dispute(
		int $report_id,
		int $tenant_user_id,
		string $reason,
		string $statement,
		?array $evidence_file = null
	): array {
		global $wpdb;

		// 1. Validate Reason
		$allowed_reasons = self::get_allowed_reasons();
		if ( ! array_key_exists( $reason, $allowed_reasons ) ) {
			return array(
				'success' => false,
				'error'   => 'invalid_reason',
				'message' => __( 'Please select a valid dispute reason category.', 'fraud-tenant-core' ),
			);
		}

		// 2. Validate Statement
		$clean_statement = trim( sanitize_textarea_field( $statement ) );
		$stmt_len        = mb_strlen( $clean_statement );

		if ( $stmt_len < 10 ) {
			return array(
				'success' => false,
				'error'   => 'statement_too_short',
				'message' => __( 'Please provide a clear factual explanation of your dispute (minimum 10 characters).', 'fraud-tenant-core' ),
			);
		}

		if ( $stmt_len > 3000 ) {
			return array(
				'success' => false,
				'error'   => 'statement_too_long',
				'message' => __( 'Dispute statement exceeds maximum allowed length of 3,000 characters.', 'fraud-tenant-core' ),
			);
		}

		// 3. User & Capability Pre-check
		if ( $tenant_user_id <= 0 ) {
			return array(
				'success' => false,
				'error'   => 'unauthenticated',
				'message' => __( 'Authentication required to submit a dispute.', 'fraud-tenant-core' ),
			);
		}

		if ( ! user_can( $tenant_user_id, Capabilities::SUBMIT_TENANT_DISPUTE ) && ! user_can( $tenant_user_id, 'ft_tenant' ) ) {
			return array(
				'success' => false,
				'error'   => 'unauthorized_role',
				'message' => __( 'Only registered tenants may submit dispute statements.', 'fraud-tenant-core' ),
			);
		}

		// 4. BLK-08 Security Gate
		if ( ! \FraudTenant\Services\ComplianceService::is_data_gate_open() ) {
			return array(
				'success' => false,
				'error'   => 'submission_locked_blk08',
				'message' => __( 'Dispute submission is currently locked pending completion of sensitive PII encryption infrastructure (BLK-08). Non-production test mode is required for synthetic testing.', 'fraud-tenant-core' ),
			);
		}

		// 5. Transaction-Safe Concurrency & Insertion
		$rep_table   = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;
		$assoc_table = $wpdb->prefix . Constants::TABLE_REPORT_ASSOCIATIONS;
		$dsp_table   = $wpdb->prefix . Constants::TABLE_TENANT_DISPUTES;

		$uploaded_doc_id = 0;

		$wpdb->query( 'START TRANSACTION' );

		try {
			// A. Lock and verify parent incident report
			$report = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, status, disclosable FROM {$rep_table} WHERE id = %d FOR UPDATE",
					$report_id
				)
			);

			if ( ! $report ) {
				$wpdb->query( 'ROLLBACK' );
				return array(
					'success' => false,
					'error'   => 'report_not_found',
					'message' => __( 'Incident report record not found.', 'fraud-tenant-core' ),
				);
			}

			if ( Constants::INCIDENT_STATUS_APPROVED !== $report->status || 1 !== (int) $report->disclosable ) {
				$wpdb->query( 'ROLLBACK' );
				return array(
					'success' => false,
					'error'   => 'not_associated_or_not_visible',
					'message' => __( 'You can only dispute incident reports that are actively associated and visible to your account.', 'fraud-tenant-core' ),
				);
			}

			// B. Re-verify (with the report row locked) that the report is visible to this tenant through
			//    their claimed identity.
			if ( ! ReportVisibilityService::tenant_can_view( $report_id, $tenant_user_id ) ) {
				$wpdb->query( 'ROLLBACK' );
				return array(
					'success' => false,
					'error'   => 'not_associated_or_not_visible',
					'message' => __( 'You can only dispute reviewed records that are associated with your verified identity.', 'fraud-tenant-core' ),
				);
			}

			// C. Lock and check for existing active dispute (anti-duplicate concurrency guard)
			$existing_active = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$dsp_table} 
					 WHERE report_id = %d AND tenant_id = %d 
					   AND status IN ('pending', 'under_review') 
					 LIMIT 1 FOR UPDATE",
					$report_id,
					$tenant_user_id
				)
			);

			if ( $existing_active ) {
				$wpdb->query( 'ROLLBACK' );
				return array(
					'success' => false,
					'error'   => 'duplicate_active_dispute',
					'message' => __( 'You already have an active dispute pending review for this report. Multiple active disputes for the same record are not permitted.', 'fraud-tenant-core' ),
				);
			}

			// D. Insert Dispute Record into wp_ft_tenant_disputes
			$now      = current_time( 'mysql', true );
			$inserted = $wpdb->insert(
				$dsp_table,
				array(
					'report_id'        => $report_id,
					'tenant_id'        => $tenant_user_id,
					'dispute_reason'   => $reason,
					'statement'        => $clean_statement,
					'status'           => self::STATUS_PENDING,
					'resolution_notes' => null,
					'resolved_by'      => null,
					'resolved_at'      => null,
					'created_at'       => $now,
					'updated_at'       => $now,
				),
				array( '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
			);

			if ( false === $inserted ) {
				$wpdb->query( 'ROLLBACK' );
				return array(
					'success' => false,
					'error'   => 'db_insert_failed',
					'message' => __( 'Database error while saving dispute submission.', 'fraud-tenant-core' ),
				);
			}

			$dispute_id = (int) $wpdb->insert_id;

			// E. Handle Optional Evidence Upload (staged safely inside transaction)
			if ( ! empty( $evidence_file ) && ! empty( $evidence_file['name'] ) ) {
				$upload_res = DocumentService::store_dispute_document( $tenant_user_id, $dispute_id, $evidence_file );
				if ( $upload_res['success'] ) {
					$uploaded_doc_id = (int) $upload_res['document_id'];
				} else {
					// Upload failed: abort transaction to prevent orphaned dispute without evidence
					$wpdb->query( 'ROLLBACK' );
					return array(
						'success' => false,
						'error'   => $upload_res['error'] ?? 'upload_error',
						'message' => $upload_res['message'] ?? __( 'Failed to securely store evidence document. Dispute submission was cancelled.', 'fraud-tenant-core' ),
					);
				}
			}

			$wpdb->query( 'COMMIT' );

		} catch ( \Throwable $t ) {
			$wpdb->query( 'ROLLBACK' );
			if ( $uploaded_doc_id > 0 ) {
				DocumentService::delete_document( $uploaded_doc_id );
			}
			return array(
				'success' => false,
				'error'   => 'transaction_failed',
				'message' => __( 'An unexpected error occurred while processing dispute submission.', 'fraud-tenant-core' ),
			);
		}

		// 6. Append-Only Audit Logging (Data Minimized: NO narrative/PII in log payload)
		AuditLogger::log(
			'tenant.dispute_submitted',
			'dispute',
			$dispute_id,
			array(
				'report_id'      => $report_id,
				'dispute_reason' => $reason,
				'has_evidence'   => $uploaded_doc_id > 0,
				'document_id'    => $uploaded_doc_id,
			),
			$tenant_user_id
		);

		// 7. Notifications: confirmation to the tenant (in-app + email), queue notice to administrators.
		NotificationService::notify( $tenant_user_id, __( 'Dispute submitted', 'fraud-tenant-core' ), sprintf( /* translators: 1: dispute ref, 2: report ref */ __( 'Your dispute %1$s about report %2$s was received and will be reviewed by our team.', 'fraud-tenant-core' ), sprintf( 'DSP-%06d', $dispute_id ), sprintf( 'INC-%06d', $report_id ) ), home_url( '/tenant-dashboard/?section=disputes&dispute_id=' . $dispute_id ), 'dispute_submitted', 'disputes' );
		self::notify_admins_new_dispute( $dispute_id, $report_id, $tenant_user_id );

		return array(
			'success'      => true,
			'dispute_id'   => $dispute_id,
			'reference'    => sprintf( 'DSP-%06d', $dispute_id ),
			'status'       => self::STATUS_PENDING,
			'has_evidence' => $uploaded_doc_id > 0,
			'document_id'  => $uploaded_doc_id,
			'message'      => __( 'Your dispute has been submitted successfully and is queued for administrative review.', 'fraud-tenant-core' ),
		);
	}

	/**
	 * Retrieve a dispute record by ID with access control.
	 *
	 * Allows access to:
	 * - The tenant who submitted the dispute ($tenant_id === $requesting_user_id)
	 * - Administrators with dispute review/resolve capabilities or 'manage_options'
	 *
	 * @param int $dispute_id         Dispute ID.
	 * @param int $requesting_user_id Requesting user ID.
	 * @return object|null Enriched dispute row or null if not found/unauthorized.
	 */
	public static function get_dispute( int $dispute_id, int $requesting_user_id ): ?object {
		global $wpdb;

		if ( $dispute_id <= 0 || $requesting_user_id <= 0 ) {
			return null;
		}

		$dsp_table = $wpdb->prefix . Constants::TABLE_TENANT_DISPUTES;
		$row       = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$dsp_table} WHERE id = %d LIMIT 1",
				$dispute_id
			)
		);

		if ( ! $row ) {
			return null;
		}

		$is_owner = ( (int) $row->tenant_id === $requesting_user_id );
		$is_admin = self::can_review_disputes( $requesting_user_id );

		if ( ! $is_owner && ! $is_admin ) {
			return null;
		}

		return self::enrich_dispute_object( $row );
	}

	/**
	 * Retrieve a raw dispute row for internal service linkage without access filters.
	 *
	 * @param int $dispute_id Dispute ID.
	 * @return object|null
	 */
	public static function get_dispute_raw( int $dispute_id ): ?object {
		global $wpdb;
		$dsp_table = $wpdb->prefix . Constants::TABLE_TENANT_DISPUTES;
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$dsp_table} WHERE id = %d LIMIT 1", $dispute_id )
		) ?: null;
	}

	/**
	 * Retrieve comprehensive dispute detail including attached evidence and report context.
	 *
	 * @param int $dispute_id         Dispute ID.
	 * @param int $requesting_user_id Requesting user ID.
	 * @return array|null Detail array or null if unauthorized/not found.
	 */
	public static function get_dispute_detail( int $dispute_id, int $requesting_user_id ): ?array {
		global $wpdb;

		$dispute = self::get_dispute( $dispute_id, $requesting_user_id );
		if ( ! $dispute ) {
			return null;
		}

		// 1. Evidence Documents
		$evidence_docs = DocumentService::get_dispute_documents( $dispute_id );

		// 2. Incident Report Context (handles legacy rows with report_id = 0 safely)
		// Access to parent report details requires reviewer capability OR active visible association.
		// Disassociated tenants retain access to their own dispute, but cannot inspect parent report details.
		$is_admin        = self::can_review_disputes( $requesting_user_id );
		$can_view_report = $is_admin || ( (int) $dispute->report_id > 0 && ReportVisibilityService::tenant_can_view( (int) $dispute->report_id, $requesting_user_id ) );
		if ( ! $is_admin ) {
			unset( $dispute->internal_notes ); // Internal administrator notes are never shown to tenants.
		}

		$report_summary = null;
		if ( $can_view_report && (int) $dispute->report_id > 0 ) {
			$rep_table  = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;
			$prop_table = $wpdb->prefix . Constants::TABLE_PROPERTIES;
			$report_row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT r.id, r.category, r.amount_involved, r.incident_date, r.factual_description,
					        r.status, r.disclosable, r.property_id, r.owner_id,
					        p.address_street, p.unit_number, p.city, p.state, p.postal_code, p.title AS property_title
					 FROM {$rep_table} r
					 LEFT JOIN {$prop_table} p ON r.property_id = p.id
					 WHERE r.id = %d LIMIT 1",
					$dispute->report_id
				)
			);

			if ( $report_row ) {
				$report_summary = array(
					'id'                  => (int) $report_row->id,
					'reference'           => sprintf( 'INC-%06d', $report_row->id ),
					'category'            => $report_row->category,
					'category_label'      => IncidentService::ALLOWED_CATEGORIES[ $report_row->category ] ?? ucwords( str_replace( '_', ' ', $report_row->category ) ),
					'amount_involved'     => (float) $report_row->amount_involved,
					'amount_formatted'    => '$' . number_format( (float) $report_row->amount_involved, 2 ),
					'incident_date'       => $report_row->incident_date,
					'status'              => $report_row->status,
					'disclosable'         => (int) $report_row->disclosable,
					'property_address'    => ! empty( $report_row->address_street ) ? ( $report_row->address_street . ( ! empty( $report_row->unit_number ) ? ' Unit ' . $report_row->unit_number : '' ) . ', ' . $report_row->city . ', ' . $report_row->state ) : ( $report_row->property_title ?? '' ),
					'factual_description' => $report_row->factual_description,
				);
			}
		} elseif ( ! $can_view_report && (int) $dispute->report_id > 0 ) {
			// Minimal non-sensitive reference for disputant without exposing report details or landlord narrative
			$report_summary = array(
				'id'        => (int) $dispute->report_id,
				'reference' => sprintf( 'INC-%06d', (int) $dispute->report_id ),
			);
		}

		// 3. Tenant User Context
		$tenant_user = get_user_by( 'id', (int) $dispute->tenant_id );
		$tenant_data = array(
			'id'           => (int) $dispute->tenant_id,
			'display_name' => $tenant_user ? $tenant_user->display_name : __( 'Tenant #' . $dispute->tenant_id, 'fraud-tenant-core' ),
			'email'        => $tenant_user ? $tenant_user->user_email : '',
		);

		// 4. Resolver User Context
		$resolver_data = null;
		if ( ! empty( $dispute->resolved_by ) ) {
			$resolver_user = get_user_by( 'id', (int) $dispute->resolved_by );
			$resolver_data = array(
				'id'           => (int) $dispute->resolved_by,
				'display_name' => $resolver_user ? $resolver_user->display_name : __( 'Admin #' . $dispute->resolved_by, 'fraud-tenant-core' ),
			);
		}

		return array(
			'dispute'       => $dispute,
			'evidence_docs' => $evidence_docs,
			'report'        => $report_summary,
			'tenant'        => $tenant_data,
			'resolver'      => $resolver_data,
		);
	}

	/**
	 * Retrieve all disputes submitted by a specific tenant.
	 *
	 * @param int $tenant_user_id Tenant WordPress user ID.
	 * @return array Array of enriched dispute objects.
	 */
	public static function get_tenant_disputes( int $tenant_user_id ): array {
		global $wpdb;

		if ( $tenant_user_id <= 0 ) {
			return array();
		}

		$dsp_table = $wpdb->prefix . Constants::TABLE_TENANT_DISPUTES;
		$rep_table = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT d.*, r.category AS report_category, r.amount_involved AS report_amount
				 FROM {$dsp_table} d
				 LEFT JOIN {$rep_table} r ON d.report_id = r.id
				 WHERE d.tenant_id = %d
				 ORDER BY d.id DESC",
				$tenant_user_id
			)
		) ?: array();

		$disputes = array();
		foreach ( $rows as $row ) {
			unset( $row->internal_notes ); // Never shown to tenants.
			$enriched = self::enrich_dispute_object( $row );

			// The tenant keeps their own dispute history even if the report was later disassociated
			// from their identity; report details are only shown while the report is still visible.
			$enriched->report_visible = ReportVisibilityService::tenant_can_view( (int) $row->report_id, $tenant_user_id );
			if ( ! $enriched->report_visible ) {
				unset( $row->report_amount );
			}
			$enriched->evidence_docs  = DocumentService::get_dispute_documents( (int) $row->id );
			$enriched->evidence_count = count( $enriched->evidence_docs );

			$disputes[] = $enriched;
		}

		return $disputes;
	}

	/**
	 * Retrieve administrative disputes queue with search, status filters, and pagination.
	 *
	 * Preserves all legacy rows (where report_id = 0) via LEFT JOINs.
	 *
	 * @param array $args     Filter arguments (status, search, page, per_page).
	 * @param int   $admin_id Current administrator user ID.
	 * @return array{items: array, total: int, pages: int}
	 */
	public static function get_admin_disputes_queue( array $args = array(), int $admin_id = 0 ): array {
		global $wpdb;

		if ( ! self::can_review_disputes( $admin_id ) ) {
			return array( 'items' => array(), 'total' => 0, 'pages' => 0 );
		}

		$status   = isset( $args['status'] ) ? sanitize_key( $args['status'] ) : 'all';
		$search   = isset( $args['search'] ) ? sanitize_text_field( $args['search'] ) : '';
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$per_page = isset( $args['per_page'] ) ? max( 1, min( 100, (int) $args['per_page'] ) ) : 20;
		$offset   = ( $page - 1 ) * $per_page;

		$dsp_table = $wpdb->prefix . Constants::TABLE_TENANT_DISPUTES;
		$rep_table = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;
		$usr_table = $wpdb->users;

		$where_clauses = array( '1=1' );
		$params        = array();

		if ( ! empty( $status ) && 'all' !== $status ) {
			$where_clauses[] = 'd.status = %s';
			$params[]        = $status;
		}

		if ( ! empty( $search ) ) {
			$like            = '%' . $wpdb->esc_like( $search ) . '%';
			$where_clauses[] = '(d.id LIKE %s OR d.dispute_reason LIKE %s OR u.display_name LIKE %s OR u.user_email LIKE %s OR r.id LIKE %s)';
			$params[]        = $like;
			$params[]        = $like;
			$params[]        = $like;
			$params[]        = $like;
			$params[]        = $like;
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Count Query
		$count_sql = "SELECT COUNT(d.id) 
		              FROM {$dsp_table} d 
		              LEFT JOIN {$usr_table} u ON d.tenant_id = u.ID 
		              LEFT JOIN {$rep_table} r ON d.report_id = r.id 
		              WHERE {$where_sql}";

		$total = ! empty( $params ) ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) ) : (int) $wpdb->get_var( $count_sql );

		// Items Query
		$query_params   = $params;
		$query_params[] = $per_page;
		$query_params[] = $offset;

		$items_sql = "SELECT d.*, 
		                     u.display_name AS tenant_name, 
		                     u.user_email AS tenant_email,
		                     r.category AS report_category,
		                     r.amount_involved AS report_amount,
		                     r.status AS report_status,
		                     r.disclosable AS report_disclosable
		              FROM {$dsp_table} d
		              LEFT JOIN {$usr_table} u ON d.tenant_id = u.ID
		              LEFT JOIN {$rep_table} r ON d.report_id = r.id
		              WHERE {$where_sql}
		              ORDER BY d.id DESC
		              LIMIT %d OFFSET %d";

		$rows = $wpdb->get_results( $wpdb->prepare( $items_sql, ...$query_params ) ) ?: array();

		$items = array();
		foreach ( $rows as $row ) {
			$enriched                 = self::enrich_dispute_object( $row );
			$enriched->evidence_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}" . Constants::TABLE_DOCUMENTS . " WHERE object_type = 'dispute_evidence' AND object_id = %d",
					$row->id
				)
			);
			$items[] = $enriched;
		}

		$pages = $per_page > 0 ? (int) ceil( $total / $per_page ) : 1;

		return array(
			'items' => $items,
			'total' => $total,
			'pages' => $pages,
		);
	}

	/**
	 * Get counts for dispute queue status tabs.
	 *
	 * @return array<string, int>
	 */
	public static function get_queue_status_counts(): array {
		global $wpdb;

		$dsp_table = $wpdb->prefix . Constants::TABLE_TENANT_DISPUTES;
		$counts    = array(
			'all'          => 0,
			'pending'      => 0,
			'under_review' => 0,
			'resolved'     => 0,
			'rejected'     => 0,
		);

		$rows = $wpdb->get_results(
			"SELECT status, COUNT(*) AS count FROM {$dsp_table} GROUP BY status"
		) ?: array();

		$total = 0;
		foreach ( $rows as $r ) {
			$st = $r->status;
			$c  = (int) $r->count;
			$total += $c;
			if ( array_key_exists( $st, $counts ) ) {
				$counts[ $st ] = $c;
			}
		}
		$counts['all'] = $total;

		return $counts;
	}

	/**
	 * Mark a pending dispute as under review by an administrator.
	 *
	 * @param int    $dispute_id Dispute ID.
	 * @param int    $admin_id   Administrator user ID.
	 * @param string $notes      Optional internal notes.
	 * @return array Result array.
	 */
	public static function review_dispute( int $dispute_id, int $admin_id, string $notes = '' ): array {
		global $wpdb;

		// 0. BLK-08 Security Gate
		if ( ! \FraudTenant\Services\ComplianceService::is_data_gate_open() ) {
			return array(
				'success' => false,
				'error'   => 'dispute_review_locked_blk08',
				'message' => __( 'Dispute review is locked in production mode pending sensitive PII encryption infrastructure (BLK-08).', 'fraud-tenant-core' ),
			);
		}

		if ( ! self::can_review_disputes( $admin_id ) ) {
			return array(
				'success' => false,
				'error'   => 'unauthorized',
				'message' => __( 'You do not have permission to review tenant disputes.', 'fraud-tenant-core' ),
			);
		}

		$dsp_table = $wpdb->prefix . Constants::TABLE_TENANT_DISPUTES;
		$dispute   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$dsp_table} WHERE id = %d LIMIT 1", $dispute_id ) );

		if ( ! $dispute ) {
			return array(
				'success' => false,
				'error'   => 'not_found',
				'message' => __( 'Dispute record not found.', 'fraud-tenant-core' ),
			);
		}

		if ( self::STATUS_PENDING !== $dispute->status && self::STATUS_UNDER_REVIEW !== $dispute->status ) {
			return array(
				'success' => false,
				'error'   => 'invalid_transition',
				'message' => sprintf(
					/* translators: %s: current dispute status */
					__( 'Disputes with status "%s" cannot be transitioned to under review.', 'fraud-tenant-core' ),
					$dispute->status
				),
			);
		}

		$prev_status = $dispute->status;
		$now         = current_time( 'mysql', true );

		$data  = array(
			'status'     => self::STATUS_UNDER_REVIEW,
			'updated_at' => $now,
		);
		$notes = trim( sanitize_textarea_field( $notes ) );
		if ( '' !== $notes ) {
			$data['internal_notes'] = self::append_internal_note( (string) ( $dispute->internal_notes ?? '' ), $notes, $admin_id );
		}
		$updated = $wpdb->update( $dsp_table, $data, array( 'id' => $dispute_id ) );

		if ( false === $updated ) {
			return array(
				'success' => false,
				'error'   => 'db_update_failed',
				'message' => __( 'Failed to update dispute status in database.', 'fraud-tenant-core' ),
			);
		}

		AuditLogger::log(
			'admin.dispute_reviewed',
			'dispute',
			$dispute_id,
			array(
				'previous_status' => $prev_status,
				'new_status'      => self::STATUS_UNDER_REVIEW,
				'report_id'       => (int) $dispute->report_id,
				'tenant_id'       => (int) $dispute->tenant_id,
			),
			$admin_id
		);

		return array(
			'success' => true,
			'status'  => self::STATUS_UNDER_REVIEW,
			'message' => __( 'Dispute marked as under review.', 'fraud-tenant-core' ),
		);
	}

	/**
	 * Formally resolve or reject a tenant dispute.
	 *
	 * Supported resolution actions:
	 * - resolve_upheld: Tenant's factual challenge is accepted (status: resolved).
	 * - resolve_rejected: Tenant's challenge is determined unfounded (status: rejected).
	 * - resolve_corrected: Record inaccuracy corrected (status: resolved).
	 *
	 * Under NO circumstances does resolving a dispute alter the original incident report,
	 * landlord attestation, or uploaded evidence.
	 *
	 * @param int    $dispute_id         Dispute ID.
	 * @param int    $admin_id           Administrator user ID.
	 * @param string $resolution_action  Action code (resolve_upheld, resolve_rejected, resolve_corrected).
	 * @param string $resolution_notes   Mandatory administrative explanation.
	 * @return array Result array.
	 */
	public static function resolve_dispute(
		int $dispute_id,
		int $admin_id,
		string $resolution_action,
		string $resolution_notes,
		string $internal_notes = ''
	): array {
		global $wpdb;

		// 0. BLK-08 Security Gate
		if ( ! \FraudTenant\Services\ComplianceService::is_data_gate_open() ) {
			return array(
				'success' => false,
				'error'   => 'dispute_review_locked_blk08',
				'message' => __( 'Dispute resolution is locked in production mode pending sensitive PII encryption infrastructure (BLK-08).', 'fraud-tenant-core' ),
			);
		}

		// 1. Capability Verification
		if ( ! self::can_resolve_disputes( $admin_id ) ) {
			return array(
				'success' => false,
				'error'   => 'unauthorized',
				'message' => __( 'You do not have permission to resolve tenant disputes.', 'fraud-tenant-core' ),
			);
		}

		// 2. Action Verification
		$valid_actions = array( self::ACTION_UPHELD, self::ACTION_REJECTED, self::ACTION_CORRECTED );
		if ( ! in_array( $resolution_action, $valid_actions, true ) ) {
			return array(
				'success' => false,
				'error'   => 'invalid_action',
				'message' => __( 'Invalid dispute resolution action specified.', 'fraud-tenant-core' ),
			);
		}

		// 3. Notes Validation
		$clean_notes = trim( sanitize_textarea_field( $resolution_notes ) );
		$notes_len   = mb_strlen( $clean_notes );

		if ( $notes_len < 5 ) {
			return array(
				'success' => false,
				'error'   => 'notes_too_short',
				'message' => __( 'Administrative resolution notes are required (minimum 5 characters).', 'fraud-tenant-core' ),
			);
		}

		if ( $notes_len > 2000 ) {
			return array(
				'success' => false,
				'error'   => 'notes_too_long',
				'message' => __( 'Resolution notes exceed maximum allowed length of 2,000 characters.', 'fraud-tenant-core' ),
			);
		}

		// 4. Retrieve Dispute Row
		$dsp_table = $wpdb->prefix . Constants::TABLE_TENANT_DISPUTES;
		$dispute   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$dsp_table} WHERE id = %d LIMIT 1", $dispute_id ) );

		if ( ! $dispute ) {
			return array(
				'success' => false,
				'error'   => 'not_found',
				'message' => __( 'Dispute record not found.', 'fraud-tenant-core' ),
			);
		}

		if ( self::STATUS_RESOLVED === $dispute->status || self::STATUS_REJECTED === $dispute->status ) {
			return array(
				'success' => false,
				'error'   => 'already_finalized',
				'message' => sprintf(
					/* translators: %s: finalized status */
					__( 'This dispute is already finalized with status "%s" and cannot be modified.', 'fraud-tenant-core' ),
					$dispute->status
				),
			);
		}

		// Determine new status
		$new_status = ( self::ACTION_REJECTED === $resolution_action ) ? self::STATUS_REJECTED : self::STATUS_RESOLVED;
		$now        = current_time( 'mysql', true );

		// Explicit determination prefix distinguishing administrative determination from report modification
		$determination_header = match ( $resolution_action ) {
			self::ACTION_CORRECTED => __( '[Determination: Correction Acknowledged — An administrator recorded that a correction was warranted. The original incident report remains unchanged by this dispute action.]', 'fraud-tenant-core' ),
			self::ACTION_UPHELD    => __( '[Determination: Dispute Upheld — Factual challenge validated. The original incident report remains unchanged by this dispute action.]', 'fraud-tenant-core' ),
			self::ACTION_REJECTED  => __( '[Determination: Dispute Dismissed — Record stands unaltered.]', 'fraud-tenant-core' ),
			default                => '',
		};

		$stored_notes = ! empty( $determination_header ) ? ( $determination_header . "\n\n" . $clean_notes ) : $clean_notes;

		// 5. Update Dispute Record (conditional on the dispute still being open: no double decision).
		$data = array(
			'status'            => $new_status,
			'resolution_action' => $resolution_action,
			'resolution_notes'  => $stored_notes,
			'resolved_by'       => $admin_id,
			'resolved_at'       => $now,
			'updated_at'        => $now,
		);
		$internal_notes = trim( sanitize_textarea_field( $internal_notes ) );
		if ( '' !== $internal_notes ) {
			$data['internal_notes'] = self::append_internal_note( (string) ( $dispute->internal_notes ?? '' ), $internal_notes, $admin_id );
		}
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$dsp_table} SET status = %s, resolution_action = %s, resolution_notes = %s, resolved_by = %d, resolved_at = %s, updated_at = %s" . ( isset( $data['internal_notes'] ) ? ', internal_notes = %s' : '' ) . " WHERE id = %d AND status IN ('pending','under_review')",
				...array_merge( array( $new_status, $resolution_action, $stored_notes, $admin_id, $now, $now ), isset( $data['internal_notes'] ) ? array( $data['internal_notes'] ) : array(), array( $dispute_id ) )
			)
		);
		if ( 1 !== (int) $updated ) {
			$updated = false;
		}

		if ( false === $updated ) {
			return array(
				'success' => false,
				'error'   => 'db_update_failed',
				'message' => __( 'Failed to save dispute resolution in database.', 'fraud-tenant-core' ),
			);
		}

		// 6. Append-Only Audit Logging (Data Minimized: NO narrative/PII in log payload)
		AuditLogger::log(
			'admin.dispute_resolved',
			'dispute',
			$dispute_id,
			array(
				'action'      => $resolution_action,
				'new_status'  => $new_status,
				'resolved_by' => $admin_id,
				'report_id'   => (int) $dispute->report_id,
				'tenant_id'   => (int) $dispute->tenant_id,
			),
			$admin_id
		);

		// 7. In-App Notification to Disputing Tenant
		$dsp_ref    = sprintf( 'DSP-%06d', $dispute_id );
		$rep_ref    = (int) $dispute->report_id > 0 ? sprintf( 'INC-%06d', $dispute->report_id ) : __( 'Associated Report', 'fraud-tenant-core' );
		$status_lbl = self::get_status_labels()[ $new_status ] ?? $new_status;

		NotificationService::notify(
			(int) $dispute->tenant_id,
			/* translators: %s: dispute reference */
			sprintf( __( 'Dispute decision issued: %s', 'fraud-tenant-core' ), $dsp_ref ),
			sprintf(
				/* translators: 1: dispute reference, 2: determination status, 3: report reference */
				__( 'A decision (%2$s) was recorded for your dispute %1$s about report %3$s. Sign in to read the explanation.', 'fraud-tenant-core' ),
				$dsp_ref,
				$status_lbl,
				$rep_ref
			),
			home_url( '/tenant-dashboard/?section=disputes&dispute_id=' . $dispute_id ),
			'dispute_resolution',
			'disputes'
		);
		$owner_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT owner_id FROM {$wpdb->prefix}" . Constants::TABLE_INCIDENT_REPORTS . ' WHERE id = %d', (int) $dispute->report_id ) );
		if ( $owner_id > 0 ) {
			/* translators: %s: report reference */
			NotificationService::notify( $owner_id, __( 'Dispute decision on your report', 'fraud-tenant-core' ), sprintf( __( 'A tenant dispute about report %s was decided by our review team.', 'fraud-tenant-core' ), $rep_ref ), home_url( '/owner-dashboard/?section=reports&report_id=' . (int) $dispute->report_id ), 'dispute_resolution', 'reports', false );
		}

		return array(
			'success'    => true,
			'status'     => $new_status,
			'dispute_id' => $dispute_id,
			'message'    => sprintf(
				/* translators: %s: final status */
				__( 'Dispute determination recorded successfully as "%s".', 'fraud-tenant-core' ),
				$status_lbl
			),
		);
	}

	/**
	 * Deliver an in-app notification to a user.
	 *
	 * @param int    $user_id    Target User ID.
	 * @param string $title      Notification title.
	 * @param string $message    Notification message text.
	 * @param string $action_url Optional action link.
	 * @param string $type       Notification type identifier.
	 * @return bool True on success.
	 */
	public static function add_notification(
		int $user_id,
		string $title,
		string $message,
		string $action_url = '',
		string $type = 'dispute'
	): bool {
		return NotificationService::notify( $user_id, $title, $message, $action_url, $type, 'disputes', false );
	}

	/**
	 * Append a timestamped internal (administrator-only) note.
	 *
	 * @param string $existing Existing notes.
	 * @param string $note     New note.
	 * @param int    $admin_id Author.
	 * @return string
	 */
	protected static function append_internal_note( string $existing, string $note, int $admin_id ): string {
		$author = get_userdata( $admin_id );
		$line   = '[' . gmdate( 'Y-m-d H:i' ) . ' UTC — ' . ( $author ? $author->display_name : 'Admin #' . $admin_id ) . '] ' . $note;
		return trim( $existing . "\n" . $line );
	}

	/**
	 * Decision timeline for a dispute (administrators): audit events for this dispute, newest last.
	 *
	 * @param int $dispute_id Dispute.
	 * @return array
	 */
	public static function get_decision_history( int $dispute_id ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( "SELECT a.action, a.created_at, u.display_name AS actor FROM {$wpdb->prefix}" . Constants::TABLE_AUDIT_LOG . " a LEFT JOIN {$wpdb->users} u ON u.ID = a.user_id WHERE a.object_type = 'dispute' AND a.object_id = %d ORDER BY a.id ASC", $dispute_id ) ) ?: array();
	}

	/**
	 * Send in-app notification to administrators regarding a new dispute submission.
	 *
	 * @param int $dispute_id Dispute ID.
	 * @param int $report_id  Incident Report ID.
	 * @param int $tenant_id  Tenant User ID.
	 */
	protected static function notify_admins_new_dispute( int $dispute_id, int $report_id, int $tenant_id ): void {
		$dsp_ref = sprintf( 'DSP-%06d', $dispute_id );
		NotificationService::notify_admins(
			/* translators: %s: dispute ref */
			sprintf( __( 'New tenant dispute: %s', 'fraud-tenant-core' ), $dsp_ref ),
			/* translators: 1: dispute ref, 2: report ref */
			sprintf( __( 'Dispute %1$s about report %2$s is waiting for review.', 'fraud-tenant-core' ), $dsp_ref, sprintf( 'INC-%06d', $report_id ) ),
			home_url( '/admin-dashboard/?section=disputes&dispute_id=' . $dispute_id ),
			'dispute_submitted'
		);
	}

	/**
	 * Enrich a dispute database row with formatted strings, labels, and reference codes.
	 *
	 * @param object $row Dispute row from database.
	 * @return object
	 */
	protected static function enrich_dispute_object( object $row ): object {
		$reasons = self::get_allowed_reasons();
		$labels  = self::get_status_labels();

		$row->id                 = (int) $row->id;
		$row->report_id          = (int) $row->report_id;
		$row->tenant_id          = (int) $row->tenant_id;
		$row->resolved_by        = ! empty( $row->resolved_by ) ? (int) $row->resolved_by : null;
		$row->reference          = sprintf( 'DSP-%06d', $row->id );
		$row->report_reference   = $row->report_id > 0 ? sprintf( 'INC-%06d', $row->report_id ) : '—';
		$row->reason_label       = $reasons[ $row->dispute_reason ] ?? ucwords( str_replace( '_', ' ', $row->dispute_reason ) );
		$row->status_label       = $labels[ $row->status ] ?? ucfirst( $row->status );
		$row->status_badge_class = self::get_status_badge_class( $row->status );
		$row->created_at_fmt     = ! empty( $row->created_at ) ? gmdate( 'M j, Y', strtotime( $row->created_at ) ) : '—';
		$row->resolved_at_fmt    = ! empty( $row->resolved_at ) ? gmdate( 'M j, Y', strtotime( $row->resolved_at ) ) : '—';

		return $row;
	}
}
