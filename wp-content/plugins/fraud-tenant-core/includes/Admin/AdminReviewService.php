<?php
/**
 * Administrative User and Property Review Service.
 *
 * @package FraudTenant\Admin
 */

namespace FraudTenant\Admin;

use FraudTenant\Auth\Capabilities;
use FraudTenant\Services\AuditLogger;
use FraudTenant\Services\DocumentService;
use FraudTenant\Utils\Constants;
use FraudTenant\Utils\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminReviewService {

	/**
	 * Minimum character length for review action reasons.
	 */
	public const MIN_REASON_LENGTH = 10;

	/**
	 * Get full application details for an applicant.
	 *
	 * @param int $user_id User ID.
	 * @return array|null Detailed application record or null.
	 */
	public static function get_application_detail( int $user_id ): ?array {
		global $wpdb;

		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return null;
		}

		$p_table = $wpdb->prefix . Constants::TABLE_USER_PROFILES;
		$profile = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$p_table} WHERE user_id = %d", $user_id ) );
		if ( ! $profile ) {
			return null;
		}

		// Retrieve primary property if owner
		$property = null;
		$document = null;
		if ( 'owner' === $profile->account_type ) {
			$prop_table = $wpdb->prefix . Constants::TABLE_PROPERTIES;
			$property   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$prop_table} WHERE owner_id = %d ORDER BY id ASC LIMIT 1", $user_id ) );

			if ( $property ) {
				$doc_table = $wpdb->prefix . Constants::TABLE_DOCUMENTS;
				$document  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$doc_table} WHERE user_id = %d AND object_type = 'property_ownership' ORDER BY id DESC LIMIT 1", $user_id ) );
			}
		}

		// Retrieve reviewer info if reviewed
		$reviewer_name = null;
		if ( ! empty( $profile->reviewed_by ) ) {
			$rev_user      = get_user_by( 'id', $profile->reviewed_by );
			$reviewer_name = $rev_user ? $rev_user->display_name : sprintf( 'User #%d', $profile->reviewed_by );
		}

		return array(
			'user'          => $user,
			'profile'       => $profile,
			'property'      => $property,
			'document'      => $document,
			'reviewer_name' => $reviewer_name,
		);
	}

	/**
	 * Suspend an approved account.
	 *
	 * @param int    $user_id     User ID.
	 * @param int    $reviewer_id Reviewer ID.
	 * @param string $reason      Suspension reason.
	 * @return array
	 */
	public static function suspend_account( int $user_id, int $reviewer_id, string $reason ): array {
		if ( ! self::can_review( $reviewer_id ) ) {
			return array(
				'success' => false,
				'error'   => 'unauthorized',
				'message' => __( 'You do not have permission to suspend accounts.', 'fraud-tenant-core' ),
			);
		}

		$clean_reason = sanitize_textarea_field( trim( $reason ) );
		if ( mb_strlen( $clean_reason ) < self::MIN_REASON_LENGTH ) {
			return array(
				'success' => false,
				'error'   => 'missing_or_short_reason',
				'message' => sprintf(
					/* translators: %d: minimum length */
					__( 'Please provide a suspension reason of at least %d characters.', 'fraud-tenant-core' ),
					self::MIN_REASON_LENGTH
				),
			);
		}

		global $wpdb;
		$p_table = $wpdb->prefix . Constants::TABLE_USER_PROFILES;
		$now     = current_time( 'mysql', true );

		// Only Owner / Tenant member accounts have a profile; administrators and unknown IDs cannot be suspended here.
		if ( ! (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$p_table} WHERE user_id = %d", $user_id ) ) ) {
			return array(
				'success' => false,
				'error'   => 'not_found',
				'message' => __( 'No member account was found to suspend.', 'fraud-tenant-core' ),
			);
		}

		$updated = $wpdb->update(
			$p_table,
			array(
				'approval_status'  => Constants::STATUS_SUSPENDED,
				'rejection_reason' => $clean_reason,
				'reviewed_by'      => $reviewer_id,
				'reviewed_at'      => $now,
				'updated_at'       => $now,
			),
			array( 'user_id' => $user_id ),
			array( '%s', '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return array(
				'success' => false,
				'error'   => 'database_error',
				'message' => __( 'Failed to suspend account.', 'fraud-tenant-core' ),
			);
		}

		// End every active login so the suspension also applies to REST and form requests made with an
		// existing cookie (dashboards re-check status, but REST / admin-post requests do not render them).
		\WP_Session_Tokens::get_instance( $user_id )->destroy_all();

		AuditLogger::log(
			'admin.account_suspended',
			'user',
			$user_id,
			array(
				'reason'         => $clean_reason,
				'reviewer_id'    => $reviewer_id,
				'sessions_ended' => true,
			),
			$reviewer_id
		);

		return array(
			'success' => true,
			'message' => __( 'Account has been suspended.', 'fraud-tenant-core' ),
		);
	}

	/**
	 * Reactivate a suspended account back to approved status.
	 *
	 * @param int    $user_id     User ID.
	 * @param int    $reviewer_id Reviewer ID.
	 * @param string $reason      Reactivation explanation.
	 * @return array
	 */
	public static function reactivate_account( int $user_id, int $reviewer_id, string $reason = '' ): array {
		if ( ! self::can_review( $reviewer_id ) ) {
			return array(
				'success' => false,
				'error'   => 'unauthorized',
				'message' => __( 'You do not have permission to reactivate accounts.', 'fraud-tenant-core' ),
			);
		}

		$detail = self::get_application_detail( $user_id );
		if ( ! $detail ) {
			return array(
				'success' => false,
				'error'   => 'user_not_found',
				'message' => __( 'Applicant profile not found.', 'fraud-tenant-core' ),
			);
		}

		$current_status = $detail['profile']->approval_status;
		if ( ! in_array( $current_status, array( Constants::STATUS_SUSPENDED, Constants::STATUS_REJECTED ), true ) ) { // Legacy rejections can be lifted: email verification now activates accounts.
			return array(
				'success' => false,
				'error'   => 'invalid_transition',
				'message' => __( 'Only suspended accounts can be reactivated.', 'fraud-tenant-core' ),
			);
		}

		global $wpdb;
		$p_table = $wpdb->prefix . Constants::TABLE_USER_PROFILES;
		$now     = current_time( 'mysql', true );

		$updated = $wpdb->update(
			$p_table,
			array(
				'approval_status'  => Constants::STATUS_APPROVED,
				'rejection_reason' => null,
				'reviewed_by'      => $reviewer_id,
				'reviewed_at'      => $now,
				'updated_at'       => $now,
			),
			array( 'user_id' => $user_id ),
			array( '%s', '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return array(
				'success' => false,
				'error'   => 'database_error',
				'message' => __( 'Failed to reactivate account.', 'fraud-tenant-core' ),
			);
		}

		AuditLogger::log(
			'admin.account_reactivated',
			'user',
			$user_id,
			array(
				'previous_status' => $current_status,
				'new_status'      => Constants::STATUS_APPROVED,
				'reason'          => sanitize_text_field( $reason ),
				'reviewer_id'     => $reviewer_id,
			),
			$reviewer_id
		);

		return array(
			'success' => true,
			'message' => __( 'Account has been reactivated successfully.', 'fraud-tenant-core' ),
		);
	}

	/**
	 * Query properties with pagination, status filtering, and search for the dedicated Property Queue.
	 *
	 * @param array $args Query arguments (status, search, paged, per_page).
	 * @return array Array containing 'items' list, 'total_items', and 'total_pages'.
	 */
	public static function get_properties_queue( array $args = array() ): array {
		global $wpdb;

		$status   = isset( $args['status'] ) ? sanitize_key( $args['status'] ) : '';
		$search   = isset( $args['search'] ) ? sanitize_text_field( trim( $args['search'] ) ) : '';
		$paged    = isset( $args['paged'] ) ? max( 1, (int) $args['paged'] ) : 1;
		$per_page = isset( $args['per_page'] ) ? max( 1, (int) $args['per_page'] ) : 20;
		$offset   = ( $paged - 1 ) * $per_page;

		$prop_table = $wpdb->prefix . Constants::TABLE_PROPERTIES;
		$u_table    = $wpdb->users;
		$doc_table  = $wpdb->prefix . Constants::TABLE_DOCUMENTS;

		$where_clauses = array( '1=1' );
		$params        = array();

		if ( 'archived' === $status ) {
			$where_clauses[] = "(p.status = 'archived' OR p.archived_at IS NOT NULL)";
		} elseif ( ! empty( $status ) ) {
			$where_clauses[] = 'p.status = %s';
			$params[]        = $status;
		} else {
			// All active properties (excluding archived)
			$where_clauses[] = "p.status != 'archived' AND p.archived_at IS NULL";
		}

		if ( ! empty( $search ) ) {
			$search_like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where_clauses[] = '(p.title LIKE %s OR p.address_street LIKE %s OR p.city LIKE %s OR p.postal_code LIKE %s OR u.display_name LIKE %s OR u.user_email LIKE %s)';
			$params[]        = $search_like;
			$params[]        = $search_like;
			$params[]        = $search_like;
			$params[]        = $search_like;
			$params[]        = $search_like;
			$params[]        = $search_like;
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Count total items
		$count_sql = "SELECT COUNT(p.id) 
					  FROM {$prop_table} p 
					  INNER JOIN {$u_table} u ON p.owner_id = u.ID 
					  WHERE {$where_sql}";

		$total = (int) ( empty( $params ) ? $wpdb->get_var( $count_sql ) : $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) );

		// Query rows with document count
		$query_sql = "SELECT p.*, 
							 u.display_name AS owner_name, 
							 u.user_email AS owner_email,
							 (SELECT COUNT(d.id) FROM {$doc_table} d WHERE d.object_type = 'property_ownership' AND d.object_id = p.id) AS document_count,
							 (SELECT d2.review_status FROM {$doc_table} d2 WHERE d2.object_type = 'property_ownership' AND d2.object_id = p.id ORDER BY d2.id DESC LIMIT 1) AS latest_doc_status
					  FROM {$prop_table} p
					  INNER JOIN {$u_table} u ON p.owner_id = u.ID
					  WHERE {$where_sql}
					  ORDER BY p.id DESC
					  LIMIT %d OFFSET %d";

		$query_params   = array_merge( $params, array( $per_page, $offset ) );
		$prepared_query = $wpdb->prepare( $query_sql, $query_params );
		$rows           = $wpdb->get_results( $prepared_query );

		return array(
			'items'       => $rows ?: array(),
			'total_items' => $total,
			'total_pages' => ceil( $total / $per_page ),
			'paged'       => $paged,
		);
	}

	/**
	 * Count properties grouped by verification status.
	 *
	 * @return array<string, int> Status counts.
	 */
	public static function count_properties_by_status(): array {
		global $wpdb;

		$prop_table = $wpdb->prefix . Constants::TABLE_PROPERTIES;
		$sql        = "SELECT status, archived_at, COUNT(*) as cnt FROM {$prop_table} GROUP BY status, (archived_at IS NOT NULL)";
		$rows       = $wpdb->get_results( $sql );

		$counts = array(
			'all'                  => 0,
			'pending_verification' => 0,
			'info_requested'       => 0,
			'verified'             => 0,
			'rejected'             => 0,
			'archived'             => 0,
		);

		if ( $rows ) {
			foreach ( $rows as $r ) {
				$cnt = (int) $r->cnt;
				if ( ! empty( $r->archived_at ) || 'archived' === $r->status ) {
					$counts['archived'] += $cnt;
				} else {
					if ( isset( $counts[ $r->status ] ) ) {
						$counts[ $r->status ] += $cnt;
					}
					$counts['all'] += $cnt;
				}
			}
		}

		return $counts;
	}

	/**
	 * Get full property review detail for administrator inspection.
	 *
	 * @param int $property_id Property ID.
	 * @return array|null Detailed property record or null.
	 */
	public static function get_property_review_detail( int $property_id ): ?array {
		global $wpdb;

		$prop_table = $wpdb->prefix . Constants::TABLE_PROPERTIES;
		$property   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$prop_table} WHERE id = %d", $property_id ) );

		if ( ! $property ) {
			return null;
		}

		// Resolve owner user & profile
		$owner   = get_user_by( 'id', (int) $property->owner_id );
		$profile = \FraudTenant\Auth\AuthService::get_user_profile( (int) $property->owner_id );

		// Resolve associated ownership documents
		$doc_table = $wpdb->prefix . Constants::TABLE_DOCUMENTS;
		$documents = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$doc_table} WHERE object_type = 'property_ownership' AND object_id = %d ORDER BY id DESC",
				$property_id
			)
		) ?: array();

		// Reviewer display name
		$reviewer_name = null;
		if ( ! empty( $property->reviewed_by ) ) {
			$rev_user      = get_user_by( 'id', $property->reviewed_by );
			$reviewer_name = $rev_user ? $rev_user->display_name : sprintf( 'User #%d', $property->reviewed_by );
		}

		// Property audit events
		$audit_table = $wpdb->prefix . Constants::TABLE_AUDIT_LOG;
		$u_table     = $wpdb->users;
		$audit_sql   = "SELECT a.*, u.display_name AS actor_name 
						FROM {$audit_table} a 
						LEFT JOIN {$u_table} u ON a.user_id = u.ID 
						WHERE a.object_type = 'property' AND a.object_id = %d 
						ORDER BY a.id DESC LIMIT 10";
		$audit_logs  = $wpdb->get_results( $wpdb->prepare( $audit_sql, $property_id ) ) ?: array();

		return array(
			'property'      => $property,
			'owner'         => $owner,
			'profile'       => $profile,
			'documents'     => $documents,
			'reviewer_name' => $reviewer_name,
			'audit_logs'    => $audit_logs,
		);
	}

	/**
	 * Separately verify a rental property ownership.
	 *
	 * Requires that ownership documents have been inspected.
	 * Deliberately isolated from user account approval.
	 *
	 * @param int    $property_id Property ID.
	 * @param int    $reviewer_id Reviewer ID.
	 * @param string $notes       Optional reviewer notes.
	 * @return array
	 */
	public static function verify_property( int $property_id, int $reviewer_id, string $notes = '' ): array {
		if ( ! self::can_verify_property( $reviewer_id ) ) {
			AuditLogger::log(
				'admin.property_verify_denied',
				'property',
				$property_id,
				array(
					'reviewer_id' => $reviewer_id,
					'reason'      => 'missing_verify_properties_capability',
				),
				$reviewer_id
			);

			return array(
				'success' => false,
				'error'   => 'unauthorized',
				'message' => __( 'You do not have permission to verify properties.', 'fraud-tenant-core' ),
			);
		}

		global $wpdb;
		$prop_table = $wpdb->prefix . Constants::TABLE_PROPERTIES;
		$doc_table  = $wpdb->prefix . Constants::TABLE_DOCUMENTS;

		$property = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$prop_table} WHERE id = %d", $property_id ) );

		if ( ! $property ) {
			return array(
				'success' => false,
				'error'   => 'property_not_found',
				'message' => __( 'Property record not found.', 'fraud-tenant-core' ),
			);
		}

		$now     = current_time( 'mysql', true );
		$updated = $wpdb->update(
			$prop_table,
			array(
				'status'           => Constants::PROP_STATUS_VERIFIED,
				'admin_notes'      => ! empty( $notes ) ? sanitize_textarea_field( $notes ) : $property->admin_notes,
				'rejection_reason' => null,
				'reviewed_by'      => $reviewer_id,
				'reviewed_at'      => $now,
				'updated_at'       => $now,
			),
			array( 'id' => $property_id ),
			array( '%s', '%s', null, '%d', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return array(
				'success' => false,
				'error'   => 'database_error',
				'message' => __( 'Failed to update property status in database.', 'fraud-tenant-core' ),
			);
		}

		// Approve only current (latest) ownership document; mark older versions superseded
		$latest_doc_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$doc_table} WHERE object_type = 'property_ownership' AND object_id = %d ORDER BY id DESC LIMIT 1",
				$property_id
			)
		);

		if ( $latest_doc_id > 0 ) {
			$wpdb->update(
				$doc_table,
				array( 'review_status' => Constants::DOC_STATUS_APPROVED ),
				array( 'id' => $latest_doc_id ),
				array( '%s' ),
				array( '%d' )
			);

			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$doc_table} SET review_status = %s WHERE object_type = 'property_ownership' AND object_id = %d AND id != %d",
					Constants::DOC_STATUS_SUPERSEDED,
					$property_id,
					$latest_doc_id
				)
			);
		}

		AuditLogger::log(
			'admin.property_verified',
			'property',
			$property_id,
			array(
				'property_id' => $property_id,
				'owner_id'    => (int) $property->owner_id,
				'reviewer_id' => $reviewer_id,
				'notes'       => sanitize_text_field( $notes ),
			),
			$reviewer_id
		);

		return array(
			'success' => true,
			'message' => __( 'Property ownership successfully verified.', 'fraud-tenant-core' ),
		);
	}

	/**
	 * Separately reject a rental property ownership verification.
	 *
	 * Requires an explicit, non-empty rejection reason of at least 10 characters.
	 *
	 * @param int    $property_id Property ID.
	 * @param int    $reviewer_id Reviewer ID.
	 * @param string $reason      Rejection explanation.
	 * @return array
	 */
	public static function reject_property( int $property_id, int $reviewer_id, string $reason = '' ): array {
		if ( ! self::can_review_property( $reviewer_id ) ) {
			return array(
				'success' => false,
				'error'   => 'unauthorized',
				'message' => __( 'You do not have permission to review properties.', 'fraud-tenant-core' ),
			);
		}

		$clean_reason = sanitize_textarea_field( trim( $reason ) );
		if ( mb_strlen( $clean_reason ) < self::MIN_REASON_LENGTH ) {
			return array(
				'success' => false,
				'error'   => 'missing_or_short_reason',
				'message' => sprintf(
					/* translators: %d: minimum length */
					__( 'Please provide a clear property rejection reason of at least %d characters.', 'fraud-tenant-core' ),
					self::MIN_REASON_LENGTH
				),
			);
		}

		global $wpdb;
		$prop_table = $wpdb->prefix . Constants::TABLE_PROPERTIES;
		$doc_table  = $wpdb->prefix . Constants::TABLE_DOCUMENTS;

		$property = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$prop_table} WHERE id = %d", $property_id ) );

		if ( ! $property ) {
			return array(
				'success' => false,
				'error'   => 'property_not_found',
				'message' => __( 'Property record not found.', 'fraud-tenant-core' ),
			);
		}

		$now     = current_time( 'mysql', true );
		$updated = $wpdb->update(
			$prop_table,
			array(
				'status'           => Constants::PROP_STATUS_REJECTED,
				'rejection_reason' => $clean_reason,
				'reviewed_by'      => $reviewer_id,
				'reviewed_at'      => $now,
				'updated_at'       => $now,
			),
			array( 'id' => $property_id ),
			array( '%s', '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return array(
				'success' => false,
				'error'   => 'database_error',
				'message' => __( 'Failed to record property rejection.', 'fraud-tenant-core' ),
			);
		}

		// Mark only current (latest) document as rejected
		$latest_doc_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$doc_table} WHERE object_type = 'property_ownership' AND object_id = %d ORDER BY id DESC LIMIT 1",
				$property_id
			)
		);

		if ( $latest_doc_id > 0 ) {
			$wpdb->update(
				$doc_table,
				array( 'review_status' => Constants::DOC_STATUS_REJECTED ),
				array( 'id' => $latest_doc_id ),
				array( '%s' ),
				array( '%d' )
			);
		}

		AuditLogger::log(
			'admin.property_rejected',
			'property',
			$property_id,
			array(
				'property_id' => $property_id,
				'owner_id'    => (int) $property->owner_id,
				'reason'      => $clean_reason,
				'reviewer_id' => $reviewer_id,
			),
			$reviewer_id
		);

		return array(
			'success' => true,
			'message' => __( 'Property ownership verification rejected.', 'fraud-tenant-core' ),
		);
	}

	/**
	 * Request additional information or clarification for property ownership verification.
	 *
	 * Sets property status to 'info_requested'.
	 *
	 * @param int    $property_id  Property ID.
	 * @param int    $reviewer_id  Reviewer ID.
	 * @param string $request_note Specific information or documentation requested.
	 * @return array
	 */
	public static function request_property_info( int $property_id, int $reviewer_id, string $request_note ): array {
		if ( ! self::can_review_property( $reviewer_id ) ) {
			return array(
				'success' => false,
				'error'   => 'unauthorized',
				'message' => __( 'You do not have permission to review properties.', 'fraud-tenant-core' ),
			);
		}

		$clean_note = sanitize_textarea_field( trim( $request_note ) );
		if ( mb_strlen( $clean_note ) < self::MIN_REASON_LENGTH ) {
			return array(
				'success' => false,
				'error'   => 'missing_or_short_note',
				'message' => sprintf(
					/* translators: %d: minimum length */
					__( 'Please provide a clear request message of at least %d characters.', 'fraud-tenant-core' ),
					self::MIN_REASON_LENGTH
				),
			);
		}

		global $wpdb;
		$prop_table = $wpdb->prefix . Constants::TABLE_PROPERTIES;

		$property = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$prop_table} WHERE id = %d", $property_id ) );

		if ( ! $property ) {
			return array(
				'success' => false,
				'error'   => 'property_not_found',
				'message' => __( 'Property record not found.', 'fraud-tenant-core' ),
			);
		}

		$now     = current_time( 'mysql', true );
		$updated = $wpdb->update(
			$prop_table,
			array(
				'status'      => Constants::PROP_STATUS_INFO_REQUESTED,
				'admin_notes' => $clean_note,
				'reviewed_by' => $reviewer_id,
				'reviewed_at' => $now,
				'updated_at'  => $now,
			),
			array( 'id' => $property_id ),
			array( '%s', '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return array(
				'success' => false,
				'error'   => 'database_error',
				'message' => __( 'Failed to record information request for property.', 'fraud-tenant-core' ),
			);
		}

		AuditLogger::log(
			'admin.property_info_requested',
			'property',
			$property_id,
			array(
				'property_id'  => $property_id,
				'owner_id'     => (int) $property->owner_id,
				'request_note' => $clean_note,
				'reviewer_id'  => $reviewer_id,
			),
			$reviewer_id
		);

		return array(
			'success' => true,
			'message' => __( 'Additional information requested for property ownership verification.', 'fraud-tenant-core' ),
		);
	}

	/**
	 * Retrieve audit log events related to a user.
	 *
	 * @param int $user_id Target user ID.
	 * @param int $limit   Maximum number of events.
	 * @return array
	 */
	public static function get_user_audit_history( int $user_id, int $limit = 10 ): array {
		global $wpdb;

		$table   = $wpdb->prefix . Constants::TABLE_AUDIT_LOG;
		$u_table = $wpdb->users;

		$sql = "SELECT a.*, u.display_name as actor_name 
				FROM {$table} a 
				LEFT JOIN {$u_table} u ON a.user_id = u.ID 
				WHERE (a.object_type = 'user' AND a.object_id = %d) 
				   OR (a.user_id = %d)
				ORDER BY a.id DESC 
				LIMIT %d";

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $user_id, $user_id, $limit ) );

		return $rows ?: array();
	}

	/**
	 * Verify if an actor has permission to review users.
	 *
	 * @param int $user_id Reviewer user ID.
	 * @return bool
	 */
	public static function can_review( int $user_id ): bool {
		return user_can( $user_id, Capabilities::REVIEW_USER_REGISTRATIONS ) ||
			   user_can( $user_id, Capabilities::APPROVE_USER_REGISTRATIONS ) ||
			   user_can( $user_id, 'manage_options' );
	}

	/**
	 * Verify if an actor has permission to review properties (view queue and inspection details).
	 *
	 * Requires dedicated ft_review_properties, ft_verify_properties, or core manage_options.
	 *
	 * @param int $user_id Reviewer user ID.
	 * @return bool
	 */
	public static function can_review_property( int $user_id ): bool {
		return user_can( $user_id, Capabilities::REVIEW_PROPERTIES ) ||
			   user_can( $user_id, Capabilities::VERIFY_PROPERTIES ) ||
			   user_can( $user_id, 'manage_options' );
	}

	/**
	 * Verify if an actor has permission to verify properties.
	 *
	 * Strictly requires dedicated ft_verify_properties or core administrator manage_options.
	 * A user with only ft_review_properties cannot verify properties.
	 *
	 * @param int $user_id Reviewer user ID.
	 * @return bool
	 */
	public static function can_verify_property( int $user_id ): bool {
		return user_can( $user_id, Capabilities::VERIFY_PROPERTIES ) ||
			   user_can( $user_id, 'manage_options' );
	}
}

