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
	 * Query applications with pagination, status filtering, role filtering, and search.
	 *
	 * @param array $args Query arguments (status, role, search, paged, per_page).
	 * @return array Array containing 'items' list, 'total_items', and 'total_pages'.
	 */
	public static function get_applications( array $args = array() ): array {
		global $wpdb;

		$status   = isset( $args['status'] ) ? sanitize_key( $args['status'] ) : '';
		$role     = isset( $args['role'] ) ? sanitize_key( $args['role'] ) : '';
		$search   = isset( $args['search'] ) ? sanitize_text_field( trim( $args['search'] ) ) : '';
		$paged    = isset( $args['paged'] ) ? max( 1, (int) $args['paged'] ) : 1;
		$per_page = isset( $args['per_page'] ) ? max( 1, (int) $args['per_page'] ) : 20;
		$offset   = ( $paged - 1 ) * $per_page;

		$p_table = $wpdb->prefix . Constants::TABLE_USER_PROFILES;
		$u_table = $wpdb->users;

		$where_clauses = array( '1=1' );
		$params        = array();

		// Exclude unverified accounts by default unless explicitly requested
		if ( empty( $status ) ) {
			$where_clauses[] = "p.approval_status != 'email_unverified'";
		} else {
			$where_clauses[] = 'p.approval_status = %s';
			$params[]        = $status;
		}

		if ( ! empty( $role ) && in_array( $role, array( 'owner', 'tenant' ), true ) ) {
			$where_clauses[] = 'p.account_type = %s';
			$params[]        = $role;
		}

		if ( ! empty( $search ) ) {
			$search_like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where_clauses[] = '(u.user_login LIKE %s OR u.user_email LIKE %s OR u.display_name LIKE %s OR p.phone LIKE %s)';
			$params[]        = $search_like;
			$params[]        = $search_like;
			$params[]        = $search_like;
			$params[]        = $search_like;
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Count total
		$count_sql = "SELECT COUNT(p.id) FROM {$p_table} p INNER JOIN {$u_table} u ON p.user_id = u.ID WHERE {$where_sql}";
		$total     = (int) ( empty( $params ) ? $wpdb->get_var( $count_sql ) : $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) );

		// Query items
		$query_sql = "SELECT p.*, u.user_login, u.user_email, u.display_name, u.user_registered 
					  FROM {$p_table} p 
					  INNER JOIN {$u_table} u ON p.user_id = u.ID 
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
	 * Count applications grouped by approval status.
	 *
	 * @return array<string, int> Status counts.
	 */
	public static function count_applications_by_status(): array {
		global $wpdb;

		$table = $wpdb->prefix . Constants::TABLE_USER_PROFILES;
		$sql   = "SELECT approval_status, COUNT(*) as cnt FROM {$table} GROUP BY approval_status";
		$rows  = $wpdb->get_results( $sql );

		$counts = array(
			'all'              => 0,
			'pending_approval' => 0,
			'info_requested'   => 0,
			'approved'         => 0,
			'rejected'         => 0,
			'suspended'        => 0,
			'email_unverified' => 0,
		);

		if ( $rows ) {
			foreach ( $rows as $r ) {
				if ( isset( $counts[ $r->approval_status ] ) ) {
					$counts[ $r->approval_status ] = (int) $r->cnt;
				}
				if ( 'email_unverified' !== $r->approval_status ) {
					$counts['all'] += (int) $r->cnt;
				}
			}
		}

		return $counts;
	}

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
	 * Approve an applicant account.
	 *
	 * Note: Property Owner account approval activates platform login access,
	 * but deliberately leaves attached properties in pending_verification status.
	 *
	 * @param int $user_id     Applicant user ID.
	 * @param int $reviewer_id Reviewer admin user ID.
	 * @return array Result array.
	 */
	public static function approve_application( int $user_id, int $reviewer_id ): array {
		if ( ! self::can_review( $reviewer_id ) ) {
			return array(
				'success' => false,
				'error'   => 'unauthorized',
				'message' => __( 'You do not have permission to approve applications.', 'fraud-tenant-core' ),
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
		if ( ! in_array( $current_status, array( Constants::STATUS_PENDING_APPROVAL, Constants::STATUS_INFO_REQUESTED ), true ) ) {
			return array(
				'success' => false,
				'error'   => 'invalid_transition',
				'message' => sprintf(
					/* translators: %s: current status */
					__( 'Cannot approve application from its current status (%s).', 'fraud-tenant-core' ),
					$current_status
				),
			);
		}

		global $wpdb;
		$p_table = $wpdb->prefix . Constants::TABLE_USER_PROFILES;
		$now     = current_time( 'mysql', true );

		$updated = $wpdb->update(
			$p_table,
			array(
				'approval_status' => Constants::STATUS_APPROVED,
				'reviewed_by'     => $reviewer_id,
				'reviewed_at'     => $now,
				'updated_at'      => $now,
			),
			array( 'user_id' => $user_id ),
			array( '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return array(
				'success' => false,
				'error'   => 'database_error',
				'message' => __( 'Failed to update applicant approval status.', 'fraud-tenant-core' ),
			);
		}

		// Log audit event
		AuditLogger::log(
			'admin.application_approved',
			'user',
			$user_id,
			array(
				'previous_status' => $current_status,
				'new_status'      => Constants::STATUS_APPROVED,
				'account_type'    => $detail['profile']->account_type,
				'reviewer_id'     => $reviewer_id,
			),
			$reviewer_id
		);

		// Dispatch notification email
		$user    = $detail['user'];
		$subject = __( '[Fraud Tenant] Your Account Application Has Been Approved', 'fraud-tenant-core' );
		$body    = sprintf( __( "Hello %s,\n\nYour registration application for Fraud Tenant has been reviewed and approved!\n\nYou may now sign in to your account at: %s\n\nThank you,\nFraud Tenant Compliance Team", 'fraud-tenant-core' ), $user->display_name, home_url( '/login/' ) );
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		$mail_sent = wp_mail( $user->user_email, $subject, $body, $headers );

		return array(
			'success'   => true,
			'message'   => __( 'Applicant account approved successfully.', 'fraud-tenant-core' ),
			'mail_sent' => $mail_sent,
		);
	}

	/**
	 * Reject an applicant account.
	 *
	 * Requires an explicit, non-empty rejection reason.
	 *
	 * @param int    $user_id     Applicant user ID.
	 * @param int    $reviewer_id Reviewer admin user ID.
	 * @param string $reason      Rejection explanation.
	 * @return array Result array.
	 */
	public static function reject_application( int $user_id, int $reviewer_id, string $reason ): array {
		if ( ! self::can_review( $reviewer_id ) ) {
			return array(
				'success' => false,
				'error'   => 'unauthorized',
				'message' => __( 'You do not have permission to reject applications.', 'fraud-tenant-core' ),
			);
		}

		$clean_reason = sanitize_textarea_field( trim( $reason ) );
		if ( mb_strlen( $clean_reason ) < self::MIN_REASON_LENGTH ) {
			return array(
				'success' => false,
				'error'   => 'missing_or_short_reason',
				'message' => sprintf(
					/* translators: %d: minimum length */
					__( 'Please provide a meaningful rejection reason of at least %d characters.', 'fraud-tenant-core' ),
					self::MIN_REASON_LENGTH
				),
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
		if ( in_array( $current_status, array( Constants::STATUS_REJECTED ), true ) ) {
			return array(
				'success' => false,
				'error'   => 'already_rejected',
				'message' => __( 'Application is already rejected.', 'fraud-tenant-core' ),
			);
		}

		global $wpdb;
		$p_table = $wpdb->prefix . Constants::TABLE_USER_PROFILES;
		$now     = current_time( 'mysql', true );

		$updated = $wpdb->update(
			$p_table,
			array(
				'approval_status'  => Constants::STATUS_REJECTED,
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
				'message' => __( 'Failed to record rejection decision.', 'fraud-tenant-core' ),
			);
		}

		// Log audit event
		AuditLogger::log(
			'admin.application_rejected',
			'user',
			$user_id,
			array(
				'previous_status' => $current_status,
				'new_status'      => Constants::STATUS_REJECTED,
				'reason'          => $clean_reason,
				'account_type'    => $detail['profile']->account_type,
				'reviewer_id'     => $reviewer_id,
			),
			$reviewer_id
		);

		// Dispatch notification email
		$user    = $detail['user'];
		$subject = __( '[Fraud Tenant] Update on Your Account Application', 'fraud-tenant-core' );
		$body    = sprintf(
			__( "Hello %s,\n\nWe have reviewed your registration application for Fraud Tenant.\n\nUnfortunately, your application was not approved at this time for the following reason:\n\"%s\"\n\nIf you believe this decision was made in error or have additional questions, please contact our support team at %s.\n\nThank you,\nFraud Tenant Compliance Team", 'fraud-tenant-core' ),
			$user->display_name,
			$clean_reason,
			home_url( '/contact/' )
		);
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		$mail_sent = wp_mail( $user->user_email, $subject, $body, $headers );

		return array(
			'success'   => true,
			'message'   => __( 'Applicant account has been rejected.', 'fraud-tenant-core' ),
			'mail_sent' => $mail_sent,
		);
	}

	/**
	 * Request additional information from applicant.
	 *
	 * Sets approval_status to 'info_requested' and records the request details.
	 * Keeps applicant account restricted from all dashboard features.
	 *
	 * @param int    $user_id      Applicant user ID.
	 * @param int    $reviewer_id  Reviewer admin user ID.
	 * @param string $info_request Clear explanation of needed info.
	 * @return array Result array.
	 */
	public static function request_additional_info( int $user_id, int $reviewer_id, string $info_request ): array {
		if ( ! self::can_review( $reviewer_id ) ) {
			return array(
				'success' => false,
				'error'   => 'unauthorized',
				'message' => __( 'You do not have permission to review applications.', 'fraud-tenant-core' ),
			);
		}

		$clean_request = sanitize_textarea_field( trim( $info_request ) );
		if ( mb_strlen( $clean_request ) < self::MIN_REASON_LENGTH ) {
			return array(
				'success' => false,
				'error'   => 'missing_or_short_request',
				'message' => sprintf(
					/* translators: %d: minimum length */
					__( 'Please provide a clear request message of at least %d characters.', 'fraud-tenant-core' ),
					self::MIN_REASON_LENGTH
				),
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
		if ( in_array( $current_status, array( Constants::STATUS_APPROVED ), true ) ) {
			return array(
				'success' => false,
				'error'   => 'account_already_approved',
				'message' => __( 'Cannot request additional information for an already approved account.', 'fraud-tenant-core' ),
			);
		}

		global $wpdb;
		$p_table = $wpdb->prefix . Constants::TABLE_USER_PROFILES;
		$now     = current_time( 'mysql', true );

		$updated = $wpdb->update(
			$p_table,
			array(
				'approval_status'  => Constants::STATUS_INFO_REQUESTED,
				'rejection_reason' => $clean_request,
				'admin_notes'      => $clean_request,
				'reviewed_by'      => $reviewer_id,
				'reviewed_at'      => $now,
				'updated_at'       => $now,
			),
			array( 'user_id' => $user_id ),
			array( '%s', '%s', '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return array(
				'success' => false,
				'error'   => 'database_error',
				'message' => __( 'Failed to record information request.', 'fraud-tenant-core' ),
			);
		}

		// Log audit event
		AuditLogger::log(
			'admin.info_requested',
			'user',
			$user_id,
			array(
				'previous_status' => $current_status,
				'new_status'      => Constants::STATUS_INFO_REQUESTED,
				'request_details' => $clean_request,
				'account_type'    => $detail['profile']->account_type,
				'reviewer_id'     => $reviewer_id,
			),
			$reviewer_id
		);

		// Dispatch notification email
		$user    = $detail['user'];
		$subject = __( '[Fraud Tenant] Action Required: Additional Information Needed for Your Application', 'fraud-tenant-core' );
		$body    = sprintf(
			__( "Hello %s,\n\nOur compliance team is reviewing your registration application for Fraud Tenant and requires additional information before we can proceed:\n\n\"%s\"\n\nPlease sign in to your account at %s to check your application status, or reply directly with the requested information.\n\nThank you,\nFraud Tenant Compliance Team", 'fraud-tenant-core' ),
			$user->display_name,
			$clean_request,
			home_url( '/login/' )
		);
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		$mail_sent = wp_mail( $user->user_email, $subject, $body, $headers );

		return array(
			'success'   => true,
			'message'   => __( 'Additional information requested. Applicant notified.', 'fraud-tenant-core' ),
			'mail_sent' => $mail_sent,
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

		AuditLogger::log(
			'admin.account_suspended',
			'user',
			$user_id,
			array(
				'reason'      => $clean_reason,
				'reviewer_id' => $reviewer_id,
			),
			$reviewer_id
		);

		return array(
			'success' => true,
			'message' => __( 'Account has been suspended.', 'fraud-tenant-core' ),
		);
	}

	/**
	 * Separately verify a primary rental property.
	 *
	 * Requires that ownership documents have been inspected.
	 * Deliberately isolated from user account approval.
	 *
	 * @param int $property_id Property ID.
	 * @param int $reviewer_id Reviewer ID.
	 * @return array
	 */
	public static function verify_property( int $property_id, int $reviewer_id ): array {
		if ( ! self::can_review( $reviewer_id ) ) {
			return array(
				'success' => false,
				'error'   => 'unauthorized',
				'message' => __( 'You do not have permission to verify properties.', 'fraud-tenant-core' ),
			);
		}

		global $wpdb;
		$prop_table = $wpdb->prefix . Constants::TABLE_PROPERTIES;
		$property   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$prop_table} WHERE id = %d", $property_id ) );

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
				'status'     => 'verified',
				'updated_at' => $now,
			),
			array( 'id' => $property_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return array(
				'success' => false,
				'error'   => 'database_error',
				'message' => __( 'Failed to update property status.', 'fraud-tenant-core' ),
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
			),
			$reviewer_id
		);

		return array(
			'success' => true,
			'message' => __( 'Property ownership successfully verified.', 'fraud-tenant-core' ),
		);
	}

	/**
	 * Separately reject a primary rental property ownership verification.
	 *
	 * @param int    $property_id Property ID.
	 * @param int    $reviewer_id Reviewer ID.
	 * @param string $reason      Rejection explanation.
	 * @return array
	 */
	public static function reject_property( int $property_id, int $reviewer_id, string $reason = '' ): array {
		if ( ! self::can_review( $reviewer_id ) ) {
			return array(
				'success' => false,
				'error'   => 'unauthorized',
				'message' => __( 'You do not have permission to review properties.', 'fraud-tenant-core' ),
			);
		}

		global $wpdb;
		$prop_table = $wpdb->prefix . Constants::TABLE_PROPERTIES;
		$property   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$prop_table} WHERE id = %d", $property_id ) );

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
				'status'     => 'rejected',
				'updated_at' => $now,
			),
			array( 'id' => $property_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return array(
				'success' => false,
				'error'   => 'database_error',
				'message' => __( 'Failed to update property status.', 'fraud-tenant-core' ),
			);
		}

		AuditLogger::log(
			'admin.property_rejected',
			'property',
			$property_id,
			array(
				'property_id' => $property_id,
				'owner_id'    => (int) $property->owner_id,
				'reason'      => sanitize_text_field( $reason ),
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
}

