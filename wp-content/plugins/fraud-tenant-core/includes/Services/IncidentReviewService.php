<?php
/**
 * Administrative Incident Review Workflow Service.
 *
 * Handles administrative incident queue queries, report detail inspection,
 * review decision state transitions (approve, reject, request clarification),
 * reason code validation, self-review prevention, and review history tracking for Phase 8.2.
 *
 * @package FraudTenant\Services
 */

namespace FraudTenant\Services;

use FraudTenant\Auth\Capabilities;
use FraudTenant\Services\AuditLogger;
use FraudTenant\Services\DocumentService;
use FraudTenant\Services\IncidentService;
use FraudTenant\Utils\Constants;
use FraudTenant\Utils\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IncidentReviewService {

	/**
	 * Controlled reason codes allowlist for report rejection.
	 */
	public const REJECTION_REASONS = array(
		'insufficient_evidence'  => 'Insufficient or unverifiable supporting documentation.',
		'ineligible_claim'       => 'Claim does not qualify under platform reporting categories.',
		'lease_discrepancy'      => 'Discrepancy between dates/parties and lease documentation.',
		'duplicate_submission'   => 'Report duplicates a previously recorded or resolved matter.',
		'policy_window_exceeded' => 'Alleged incident occurred outside platform reporting time window (internal policy).',
		'other_factual_grounds'  => 'Administrative rejection based on documented factual criteria.',
	);

	/**
	 * Controlled reason codes allowlist for clarification requests.
	 */
	public const CLARIFICATION_REASONS = array(
		'clarify_dates'         => 'Additional clarification required regarding incident dates.',
		'clarify_accounting'    => 'Itemized ledger or accounting clarification needed for balance.',
		'clarify_documentation' => 'Legible copy or additional lease documentation requested.',
		'clarify_parties'       => 'Clarification needed regarding tenant identities or lease signatories.',
		'general_clarification' => 'Specific factual clarification requested by moderation team.',
	);

	/**
	 * Controlled reason codes allowlist for report approval.
	 */
	public const APPROVAL_REASONS = array(
		'evidence_verified'         => 'Factual attestation and supporting documentation verified.',
		'factual_standard_met'      => 'Submission meets standard administrative review criteria.',
		'administrative_acceptance' => 'Internal administrative moderation accepted.',
	);

	/**
	 * Check whether an actor has permission to view the administrative incident review queue.
	 *
	 * @param int $user_id User ID.
	 * @return bool True if authorized to review incident reports.
	 */
	public static function can_review_incidents( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}

		return user_can( $user_id, Capabilities::REVIEW_INCIDENTS ) ||
		       user_can( $user_id, Capabilities::REVIEW_INCIDENT_REPORTS ) ||
		       user_can( $user_id, 'manage_options' );
	}

	/**
	 * Check whether an actor has permission to decide the outcome of an incident report.
	 *
	 * @param int $user_id User ID.
	 * @return bool True if authorized to approve, reject, or request clarification.
	 */
	public static function can_decide_incidents( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}

		return user_can( $user_id, Capabilities::DECIDE_INCIDENTS ) ||
		       user_can( $user_id, Capabilities::APPROVE_INCIDENT_REPORTS ) ||
		       user_can( $user_id, 'manage_options' );
	}

	/**
	 * Retrieve a paginated list of incident reports for the administrative review queue.
	 *
	 * @param array $args        Query filtering/pagination arguments.
	 * @param int   $reviewer_id Current reviewer user ID.
	 * @return array Result array containing items, status counts, pagination info, or error.
	 */
	public static function get_review_queue( array $args = array(), int $reviewer_id = 0 ): array {
		// BLK-08 Security Gate: strictly fail closed in production
		if ( ! \FraudTenant\Services\ComplianceService::is_data_gate_open() ) {
			return array(
				'success' => false,
				'error'   => 'incident_review_locked_blk08',
				'message' => __( 'Incident review is locked in production mode pending sensitive PII encryption infrastructure (BLK-08).', 'fraud-tenant-core' ),
			);
		}

		if ( ! self::can_review_incidents( $reviewer_id ) ) {
			return array(
				'success' => false,
				'error'   => 'unauthorized',
				'message' => __( 'You do not have permission to access the incident review queue.', 'fraud-tenant-core' ),
			);
		}

		global $wpdb;

		$rep_table  = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;
		$prop_table = $wpdb->prefix . Constants::TABLE_PROPERTIES;

		$per_page = isset( $args['per_page'] ) ? max( 1, min( 100, (int) $args['per_page'] ) ) : 20;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$offset   = ( $page - 1 ) * $per_page;
		$status   = isset( $args['status'] ) ? sanitize_key( $args['status'] ) : 'all';

		$where_clauses = array( '1=1' );
		$where_params  = array();

		if ( ! empty( $status ) && 'all' !== $status ) {
			$where_clauses[] = 'r.status = %s';
			$where_params[]  = $status;
		}

		if ( ! empty( $args['search'] ) ) {
			$search_term     = '%' . $wpdb->esc_like( trim( $args['search'] ) ) . '%';
			$where_clauses[] = '(r.id LIKE %s OR p.title LIKE %s OR p.address_street LIKE %s OR r.category LIKE %s)';
			$where_params[]  = $search_term;
			$where_params[]  = $search_term;
			$where_params[]  = $search_term;
			$where_params[]  = $search_term;
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// 1. Get total matching items for pagination
		$count_query = "SELECT COUNT(*) FROM {$rep_table} r LEFT JOIN {$prop_table} p ON r.property_id = p.id WHERE {$where_sql}";
		if ( ! empty( $where_params ) ) {
			$total_items = (int) $wpdb->get_var( $wpdb->prepare( $count_query, $where_params ) );
		} else {
			$total_items = (int) $wpdb->get_var( $count_query );
		}

		// 2. Query paginated records with safe display projections
		$query = "SELECT r.id, r.owner_id, r.property_id, r.category, r.incident_date, 
		                 r.amount_involved, r.status, r.disclosable, r.created_at,
		                 p.title AS property_title, p.address_street AS property_street, 
		                 p.unit_number AS property_unit, p.city AS property_city, p.state AS property_state,
		                 u.display_name AS owner_name, u.user_email AS owner_email
		          FROM {$rep_table} r
		          LEFT JOIN {$prop_table} p ON r.property_id = p.id
		          LEFT JOIN {$wpdb->users} u ON r.owner_id = u.ID
		          WHERE {$where_sql}
		          ORDER BY r.id DESC
		          LIMIT %d OFFSET %d";

		$query_params   = $where_params;
		$query_params[] = $per_page;
		$query_params[] = $offset;

		$rows = $wpdb->get_results( $wpdb->prepare( $query, $query_params ) ) ?: array();

		foreach ( $rows as &$row ) {
			$row->id               = (int) $row->id;
			$row->owner_id         = (int) $row->owner_id;
			$row->property_id      = (int) $row->property_id;
			$row->reference        = sprintf( 'INC-%06d', $row->id );
			$row->category_label   = IncidentService::ALLOWED_CATEGORIES[ $row->category ] ?? ucwords( str_replace( '_', ' ', $row->category ) );
			$row->amount_formatted = '$' . number_format( (float) $row->amount_involved, 2 );
			$row->disclosable      = (int) $row->disclosable;
			$row->is_self_report   = ( (int) $row->owner_id === $reviewer_id );
		}
		unset( $row );

		// 3. Compute status counts for queue tabs
		$status_counts = array(
			'all'                 => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$rep_table}" ),
			'submitted'           => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$rep_table} WHERE status = %s", Constants::INCIDENT_STATUS_SUBMITTED ) ),
			'approved'            => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$rep_table} WHERE status = %s", Constants::INCIDENT_STATUS_APPROVED ) ),
			'rejected'            => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$rep_table} WHERE status = %s", Constants::INCIDENT_STATUS_REJECTED ) ),
			'needs_clarification' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$rep_table} WHERE status = %s", Constants::INCIDENT_STATUS_NEEDS_CLARIFICATION ) ),
		);

		return array(
			'success'       => true,
			'items'         => $rows,
			'total_items'   => $total_items,
			'total_pages'   => (int) ceil( $total_items / $per_page ),
			'current_page'  => $page,
			'per_page'      => $per_page,
			'status_counts' => $status_counts,
		);
	}

	/**
	 * Retrieve a single incident report for authorized administrative review.
	 *
	 * Pre-condition: Evaluates BLK-08 privacy gate before loading narrative content.
	 *
	 * @param int $report_id   Incident Report ID.
	 * @param int $reviewer_id Reviewer user ID.
	 * @return array Formatted report details, evidence metadata, review history, or error.
	 */
	public static function get_incident_for_review( int $report_id, int $reviewer_id ): array {
		// BLK-08 Security Gate: strictly fail closed in production before loading narrative content
		if ( ! \FraudTenant\Services\ComplianceService::is_data_gate_open() ) {
			return array(
				'success' => false,
				'error'   => 'incident_review_locked_blk08',
				'message' => __( 'Incident report inspection is locked in production mode pending sensitive PII encryption infrastructure (BLK-08).', 'fraud-tenant-core' ),
			);
		}

		if ( ! self::can_review_incidents( $reviewer_id ) ) {
			return array(
				'success' => false,
				'error'   => 'unauthorized',
				'message' => __( 'You do not have permission to inspect this incident report.', 'fraud-tenant-core' ),
			);
		}

		global $wpdb;

		$rep_table  = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;
		$prop_table = $wpdb->prefix . Constants::TABLE_PROPERTIES;
		$doc_table  = $wpdb->prefix . Constants::TABLE_DOCUMENTS;

		$report = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT r.*, 
				        p.title AS property_title, p.address_street AS property_street, 
				        p.unit_number AS property_unit, p.city AS property_city, 
				        p.state AS property_state, p.postal_code AS property_zip,
				        u.display_name AS owner_name, u.user_email AS owner_email
				 FROM {$rep_table} r
				 LEFT JOIN {$prop_table} p ON r.property_id = p.id
				 LEFT JOIN {$wpdb->users} u ON r.owner_id = u.ID
				 WHERE r.id = %d",
				$report_id
			)
		);

		if ( ! $report ) {
			return array(
				'success' => false,
				'error'   => 'report_not_found',
				'message' => __( 'Incident report record was not found.', 'fraud-tenant-core' ),
			);
		}

		$report->id               = (int) $report->id;
		$report->owner_id         = (int) $report->owner_id;
		$report->property_id      = (int) $report->property_id;
		$report->reference        = sprintf( 'INC-%06d', $report->id );
		$report->category_label   = IncidentService::ALLOWED_CATEGORIES[ $report->category ] ?? ucwords( str_replace( '_', ' ', $report->category ) );
		$report->amount_formatted = '$' . number_format( (float) $report->amount_involved, 2 );
		$report->disclosable      = (int) $report->disclosable;
		$report->is_self_report   = ( (int) $report->owner_id === $reviewer_id );

		// Load attached evidence documents
		$evidence_docs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, original_name, file_size, mime_type, review_status, uploaded_at 
				 FROM {$doc_table} 
				 WHERE object_type = 'incident_evidence' AND object_id = %d 
				 ORDER BY id DESC",
				$report_id
			)
		) ?: array();

		foreach ( $evidence_docs as &$doc ) {
			$doc->id             = (int) $doc->id;
			$doc->file_size_fmt  = size_format( (int) $doc->file_size );
			$doc->stream_nonce   = wp_create_nonce( 'ft_view_doc_' . $doc->id );
			$doc->view_url       = add_query_arg(
				array(
					'action'      => 'ft_view_document',
					'document_id' => $doc->id,
					'_wpnonce'    => $doc->stream_nonce,
				),
				admin_url( 'admin-post.php' )
			);
		}
		unset( $doc );

		// Load review history trail
		$history = self::get_incident_review_history( $report_id, $reviewer_id );

		return array(
			'success'          => true,
			'report'           => $report,
			'evidence_docs'    => $evidence_docs,
			'review_history'   => $history['items'] ?? array(),
			'rejection_reasons'=> self::REJECTION_REASONS,
			'clarify_reasons'  => self::CLARIFICATION_REASONS,
			'approval_reasons' => self::APPROVAL_REASONS,
		);
	}

	/**
	 * Execute an administrative review decision (Approve, Reject, or Request Clarification).
	 *
	 * Enforces:
	 * - Reviewer capability ft_decide_incidents (or ft_approve_incident_reports / manage_options).
	 * - BLK-08 production privacy gate (fail-closed in production).
	 * - Self-review prevention: reviewer cannot review their own submitted report.
	 * - Report existence and current status validation (submitted only).
	 * - Controlled reason code validation from server-side allowlists.
	 * - Atomic transaction processing with conditional status update guard.
	 * - Review history record creation in wp_ft_report_reviews.
	 * - Append-only application-level audit logging.
	 * - Non-disclosure guarantee: disclosable remains strictly 0.
	 *
	 * @param int    $report_id   Incident report ID.
	 * @param int    $reviewer_id Reviewer user ID.
	 * @param string $decision    'approve', 'reject', or 'request_clarification'.
	 * @param string $reason_code Controlled reason code.
	 * @return array Success or failure response payload.
	 */
	public static function decide_incident_report( int $report_id, int $reviewer_id, string $decision, string $reason_code = '' ): array {
		if ( $report_id <= 0 || $reviewer_id <= 0 ) {
			return array(
				'success' => false,
				'error'   => 'invalid_parameters',
				'message' => __( 'Invalid report or reviewer identifier.', 'fraud-tenant-core' ),
			);
		}

		// 1. BLK-08 Security Gate: Fail-closed in production
		if ( ! \FraudTenant\Services\ComplianceService::is_data_gate_open() ) {
			return array(
				'success' => false,
				'error'   => 'incident_review_locked_blk08',
				'message' => __( 'Incident review decisions are locked in production mode pending sensitive PII encryption infrastructure (BLK-08).', 'fraud-tenant-core' ),
			);
		}

		// 2. Reviewer Capability Authorization Check
		if ( ! self::can_decide_incidents( $reviewer_id ) ) {
			return array(
				'success' => false,
				'error'   => 'unauthorized',
				'message' => __( 'You do not have permission to decide the outcome of incident reports.', 'fraud-tenant-core' ),
			);
		}

		global $wpdb;

		$rep_table = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;
		$rev_table = $wpdb->prefix . Constants::TABLE_REPORT_REVIEWS;

		// 3. Retrieve report for validation
		$report = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$rep_table} WHERE id = %d", $report_id ) );
		if ( ! $report ) {
			return array(
				'success' => false,
				'error'   => 'report_not_found',
				'message' => __( 'Incident report record was not found.', 'fraud-tenant-core' ),
			);
		}

		// 4. SELF-REVIEW PREVENTION: Property owner must NEVER decide their own submitted report
		if ( (int) $report->owner_id === $reviewer_id ) {
			AuditLogger::log(
				'incident.self_review_blocked',
				'incident_report',
				$report_id,
				array(
					'reason'      => 'self_review_attempt_forbidden',
					'owner_id'    => (int) $report->owner_id,
					'reviewer_id' => $reviewer_id,
					'decision'    => $decision,
				),
				$reviewer_id
			);

			return array(
				'success' => false,
				'error'   => 'self_review_forbidden',
				'message' => __( 'Self-review is strictly forbidden: reviewers cannot decide the outcome of their own incident reports.', 'fraud-tenant-core' ),
			);
		}

		// 5. Decision & State Transition Validation
		$allowed_decisions = array( 'approve', 'reject', 'request_clarification' );
		if ( ! in_array( $decision, $allowed_decisions, true ) ) {
			return array(
				'success' => false,
				'error'   => 'invalid_decision',
				'message' => __( 'Invalid review decision specified.', 'fraud-tenant-core' ),
			);
		}

		// Enforce initial status requirement: report must be in 'submitted' status
		if ( Constants::INCIDENT_STATUS_SUBMITTED !== $report->status ) {
			return array(
				'success' => false,
				'error'   => 'invalid_status_transition',
				'message' => sprintf(
					/* translators: %s: Current report status */
					__( 'Report cannot be decided because it is currently in "%s" status.', 'fraud-tenant-core' ),
					esc_html( $report->status )
				),
			);
		}

		// Map target new status
		$new_status = '';
		if ( 'approve' === $decision ) {
			$new_status = Constants::INCIDENT_STATUS_APPROVED;
		} elseif ( 'reject' === $decision ) {
			$new_status = Constants::INCIDENT_STATUS_REJECTED;
		} elseif ( 'request_clarification' === $decision ) {
			$new_status = Constants::INCIDENT_STATUS_NEEDS_CLARIFICATION;
		}

		// 6. Controlled Reason Code Validation
		$reason_code = sanitize_key( $reason_code );
		$reason_text = '';

		// Reject unsupported legal/statutory determination codes pending formal legal counsel review
		if ( 'statute_exceeded' === $reason_code ) {
			return array(
				'success' => false,
				'error'   => 'statutory_determination_unsupported',
				'message' => __( 'Statutory determinations (such as statute of limitations) cannot be used as an automated platform reason code pending qualified legal counsel approval. Use "policy_window_exceeded" for internal platform policy time limits.', 'fraud-tenant-core' ),
			);
		}

		if ( 'reject' === $decision ) {
			if ( empty( $reason_code ) || ! array_key_exists( $reason_code, self::REJECTION_REASONS ) ) {
				return array(
					'success' => false,
					'error'   => 'invalid_reason_code',
					'message' => __( 'A valid controlled rejection reason code is required.', 'fraud-tenant-core' ),
				);
			}
			$reason_text = self::REJECTION_REASONS[ $reason_code ];
		} elseif ( 'request_clarification' === $decision ) {
			if ( empty( $reason_code ) || ! array_key_exists( $reason_code, self::CLARIFICATION_REASONS ) ) {
				return array(
					'success' => false,
					'error'   => 'invalid_reason_code',
					'message' => __( 'A valid controlled clarification reason code is required.', 'fraud-tenant-core' ),
				);
			}
			$reason_text = self::CLARIFICATION_REASONS[ $reason_code ];
		} elseif ( 'approve' === $decision ) {
			if ( empty( $reason_code ) ) {
				$reason_code = 'evidence_verified';
			}
			if ( ! array_key_exists( $reason_code, self::APPROVAL_REASONS ) ) {
				return array(
					'success' => false,
					'error'   => 'invalid_reason_code',
					'message' => __( 'A valid controlled approval reason code is required.', 'fraud-tenant-core' ),
				);
			}
			$reason_text = self::APPROVAL_REASONS[ $reason_code ];
		}

		// 7. Atomic Database Transaction with Conditional Status Update Guard
		$now = current_time( 'mysql', true );

		$trans_started = $wpdb->query( 'START TRANSACTION' );
		if ( false === $trans_started ) {
			return array(
				'success' => false,
				'error'   => 'transaction_start_failed',
				'message' => __( 'Failed to start database transaction.', 'fraud-tenant-core' ),
			);
		}

		try {
			// Conditional atomic update: status must still be 'submitted'
			$updated = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$rep_table} 
					 SET status = %s, disclosable = 0, updated_at = %s 
					 WHERE id = %d AND status = %s",
					$new_status,
					$now,
					$report_id,
					Constants::INCIDENT_STATUS_SUBMITTED
				)
			);

			if ( false === $updated ) {
				$wpdb->query( 'ROLLBACK' );

				return array(
					'success' => false,
					'error'   => 'status_update_failed',
					'message' => __( 'Failed to update incident report status.', 'fraud-tenant-core' ),
				);
			}

			if ( 1 !== $updated ) {
				$wpdb->query( 'ROLLBACK' );

				return array(
					'success' => false,
					'error'   => 'conflict_already_decided',
					'message' => __( 'Decision conflict: this incident report was already decided or modified by another reviewer.', 'fraud-tenant-core' ),
				);
			}

			// Insert durable review history record
			$history_inserted = $wpdb->insert(
				$rev_table,
				array(
					'report_id'         => $report_id,
					'reviewer_id'       => $reviewer_id,
					'decision'          => $decision,
					'previous_status'   => Constants::INCIDENT_STATUS_SUBMITTED,
					'new_status'        => $new_status,
					'reason_code'       => $reason_code,
					'internal_notes'    => $reason_text,
					'feedback_to_owner' => $reason_text,
					'created_at'        => $now,
				),
				array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);

			if ( false === $history_inserted ) {
				$wpdb->query( 'ROLLBACK' );

				return array(
					'success' => false,
					'error'   => 'history_record_failed',
					'message' => __( 'Failed to record administrative review history.', 'fraud-tenant-core' ),
				);
			}

			$history_id = (int) $wpdb->insert_id;

			$committed = $wpdb->query( 'COMMIT' );
			if ( false === $committed ) {
				$wpdb->query( 'ROLLBACK' );

				return array(
					'success' => false,
					'error'   => 'transaction_commit_failed',
					'message' => __( 'Failed to commit database transaction.', 'fraud-tenant-core' ),
				);
			}
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );

			return array(
				'success' => false,
				'error'   => 'transaction_failed',
				'message' => __( 'Review decision transaction failed unexpectedly: ', 'fraud-tenant-core' ) . $e->getMessage(),
			);
		}

		// 8. Application-Level Append-Only Audit Logging
		// CRITICAL PRIVACY DIRECTIVE: Zero incident narrative or tenant PII in audit log.
		// Actor IDs (user_id, reviewer_id) are intentionally retained for accountability and provenance.
		$audit_action = 'incident.report_' . $decision;
		if ( 'request_clarification' === $decision ) {
			$audit_action = 'incident.clarification_requested';
		}

		AuditLogger::log(
			$audit_action,
			'incident_report',
			$report_id,
			array(
				'report_id'       => $report_id,
				'reference'       => sprintf( 'INC-%06d', $report_id ),
				'reviewer_id'     => $reviewer_id,
				'decision'        => $decision,
				'previous_status' => Constants::INCIDENT_STATUS_SUBMITTED,
				'new_status'      => $new_status,
				'reason_code'     => $reason_code,
				'history_id'      => $history_id,
				'disclosable'     => 0, // Explicitly guaranteed non-disclosable
			),
			$reviewer_id
		);

		return array(
			'success'         => true,
			'report_id'       => $report_id,
			'reference'       => sprintf( 'INC-%06d', $report_id ),
			'previous_status' => Constants::INCIDENT_STATUS_SUBMITTED,
			'new_status'      => $new_status,
			'decision'        => $decision,
			'reason_code'     => $reason_code,
			'reason_text'     => $reason_text,
			'disclosable'     => 0,
			'history_id'      => $history_id,
			'message'         => sprintf(
				/* translators: 1: Decision name, 2: Report reference */
				__( 'Incident %2$s successfully marked as %1$s.', 'fraud-tenant-core' ),
				esc_html( $new_status ),
				sprintf( 'INC-%06d', $report_id )
			),
		);
	}

	/**
	 * Retrieve the immutable review history records for a specific incident report.
	 *
	 * @param int $report_id   Incident report ID.
	 * @param int $reviewer_id Reviewer user ID.
	 * @return array Array of formatted review history items or error.
	 */
	public static function get_incident_review_history( int $report_id, int $reviewer_id ): array {
		if ( ! self::can_review_incidents( $reviewer_id ) ) {
			return array(
				'success' => false,
				'error'   => 'unauthorized',
				'message' => __( 'You do not have permission to view review history.', 'fraud-tenant-core' ),
			);
		}

		if ( ! \FraudTenant\Services\ComplianceService::is_data_gate_open() ) {
			return array(
				'success' => false,
				'error'   => 'incident_review_locked_blk08',
				'message' => __( 'Incident review history is locked in production mode (BLK-08).', 'fraud-tenant-core' ),
			);
		}

		global $wpdb;

		$rev_table = $wpdb->prefix . Constants::TABLE_REPORT_REVIEWS;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.*, u.display_name AS reviewer_name, u.user_login AS reviewer_login
				 FROM {$rev_table} r
				 LEFT JOIN {$wpdb->users} u ON r.reviewer_id = u.ID
				 WHERE r.report_id = %d
				 ORDER BY r.id ASC",
				$report_id
			)
		) ?: array();

		foreach ( $rows as &$row ) {
			$row->id          = (int) $row->id;
			$row->report_id   = (int) $row->report_id;
			$row->reviewer_id = (int) $row->reviewer_id;
			$row->formatted_date = get_date_from_gmt( $row->created_at, 'M j, Y g:i A' );
		}
		unset( $row );

		return array(
			'success' => true,
			'items'   => $rows,
		);
	}

	/**
	 * Verify that a document record belongs to the requested incident report.
	 *
	 * @param int $document_id Document ID.
	 * @param int $incident_id Incident Report ID.
	 * @return bool True if document belongs to incident as evidence, false otherwise.
	 */
	public static function verify_incident_evidence_link( int $document_id, int $incident_id ): bool {
		if ( $document_id <= 0 || $incident_id <= 0 ) {
			return false;
		}

		global $wpdb;
		$table = $wpdb->prefix . Constants::TABLE_DOCUMENTS;

		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE id = %d AND object_type = 'incident_evidence' AND object_id = %d",
				$document_id,
				$incident_id
			)
		);

		return ! empty( $found );
	}
}
