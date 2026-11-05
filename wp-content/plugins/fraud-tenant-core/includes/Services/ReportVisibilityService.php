<?php
/**
 * Report Visibility and Tenant Association Service.
 *
 * Manages administrative association of incident reports to verified tenant accounts,
 * manual visibility approval/revocation, association and disclosure history tracking,
 * and tenant-facing disclosed report queries.
 *
 * @package FraudTenant\Services
 */

namespace FraudTenant\Services;

use FraudTenant\Auth\Capabilities;
use FraudTenant\Utils\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ReportVisibilityService {

	/**
	 * Association statuses.
	 */
	public const STATUS_ACTIVE        = 'active';
	public const STATUS_DISASSOCIATED = 'disassociated';
	public const STATUS_REVOKED       = 'revoked';

	/**
	 * Visibility statuses.
	 */
	public const VISIBILITY_HIDDEN  = 'hidden';
	public const VISIBILITY_VISIBLE = 'visible';
	public const VISIBILITY_REVOKED = 'revoked';

	/**
	 * Check whether a user has administrative permission to manage report visibility.
	 *
	 * @param int $user_id User ID to inspect.
	 * @return bool True if authorized.
	 */
	public static function can_manage_visibility( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}

		return user_can( $user_id, Capabilities::MANAGE_REPORT_VISIBILITY ) ||
		       user_can( $user_id, Capabilities::ASSOCIATE_TENANT_REPORTS ) ||
		       user_can( $user_id, Capabilities::DECIDE_INCIDENTS ) ||
		       user_can( $user_id, Capabilities::REVIEW_INCIDENTS ) ||
		       user_can( $user_id, 'manage_options' );
	}

	/**
	 * Check whether a user has administrative permission specifically to associate or disassociate tenant reports.
	 *
	 * @param int $user_id User ID to inspect.
	 * @return bool True if authorized.
	 */
	public static function can_associate_tenant( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}

		return user_can( $user_id, Capabilities::ASSOCIATE_TENANT_REPORTS ) ||
		       user_can( $user_id, 'manage_options' );
	}

	/**
	 * Check whether a user has administrative permission specifically to approve or revoke report visibility.
	 *
	 * @param int $user_id User ID to inspect.
	 * @return bool True if authorized.
	 */
	public static function can_manage_report_visibility( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}

		return user_can( $user_id, Capabilities::MANAGE_REPORT_VISIBILITY ) ||
		       user_can( $user_id, 'manage_options' );
	}

	/**
	 * Retrieve a paginated queue of incident reports filtered by association/visibility status.
	 *
	 * Supported views:
	 * - 'all': All incident reports
	 * - 'unassociated': Reports without an active tenant association
	 * - 'associated': Reports with an active tenant association
	 * - 'awaiting_visibility': Reports that are approved + associated, but disclosable=0
	 * - 'tenant_visible': Reports that are approved + associated + disclosable=1
	 * - 'visibility_revoked': Reports with an association where visibility was revoked
	 *
	 * @param array $args     Query arguments (status, search, page, per_page).
	 * @param int   $admin_id Administrator ID.
	 * @return array
	 */
	public static function get_visibility_queue( array $args = array(), int $admin_id = 0 ): array {
		if ( ! \FraudTenant\Services\ComplianceService::is_data_gate_open() ) {
			return array(
				'success' => false,
				'error'   => 'visibility_locked_blk08',
				'message' => __( 'Report visibility management is locked in production mode pending sensitive PII encryption infrastructure (BLK-08).', 'fraud-tenant-core' ),
			);
		}

		if ( ! self::can_manage_visibility( $admin_id ) ) {
			return array(
				'success' => false,
				'error'   => 'unauthorized',
				'message' => __( 'You do not have permission to manage report visibility.', 'fraud-tenant-core' ),
			);
		}

		global $wpdb;

		$rep_table   = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;
		$prop_table  = $wpdb->prefix . Constants::TABLE_PROPERTIES;
		$assoc_table = $wpdb->prefix . Constants::TABLE_REPORT_ASSOCIATIONS;

		$status   = isset( $args['status'] ) ? sanitize_key( $args['status'] ) : 'all';
		$search   = isset( $args['search'] ) ? trim( sanitize_text_field( $args['search'] ) ) : '';
		$per_page = isset( $args['per_page'] ) ? max( 1, min( 100, (int) $args['per_page'] ) ) : 20;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$offset   = ( $page - 1 ) * $per_page;

		// Calculate status breakdown counters across all records
		$status_counts = self::get_queue_status_counts();

		// Build WHERE clause based on selected view
		$where_clauses = array( '1=1' );
		$where_params  = array();

		switch ( $status ) {
			case 'unassociated':
				$where_clauses[] = "(ra.id IS NULL OR ra.status != 'active')";
				break;
			case 'associated':
				$where_clauses[] = "ra.status = 'active'";
				break;
			case 'awaiting_visibility':
				$where_clauses[] = "r.status = 'approved' AND ra.status = 'active' AND (ra.visibility_status = 'hidden' OR r.disclosable = 0)";
				break;
			case 'tenant_visible':
				$where_clauses[] = "r.status = 'approved' AND ra.status = 'active' AND ra.visibility_status = 'visible' AND r.disclosable = 1";
				break;
			case 'visibility_revoked':
				$where_clauses[] = "ra.visibility_status = 'revoked'";
				break;
			case 'all':
			default:
				// No extra filter
				break;
		}

		if ( ! empty( $search ) ) {
			$search_term     = '%' . $wpdb->esc_like( $search ) . '%';
			$where_clauses[] = '(r.id LIKE %s OR p.title LIKE %s OR p.address_street LIKE %s OR u_tenant.display_name LIKE %s OR u_tenant.user_email LIKE %s)';
			$where_params[]  = $search_term;
			$where_params[]  = $search_term;
			$where_params[]  = $search_term;
			$where_params[]  = $search_term;
			$where_params[]  = $search_term;
		}

		$where_sql = implode( ' AND ', $where_clauses );

		$base_from_sql = "FROM {$rep_table} r
			LEFT JOIN {$prop_table} p ON r.property_id = p.id
			LEFT JOIN {$wpdb->users} u_owner ON r.owner_id = u_owner.ID
			LEFT JOIN {$assoc_table} ra ON (ra.report_id = r.id AND ra.status = 'active')
			LEFT JOIN {$wpdb->users} u_tenant ON ra.tenant_user_id = u_tenant.ID";

		// Count total items
		$count_query = "SELECT COUNT(DISTINCT r.id) {$base_from_sql} WHERE {$where_sql}";
		if ( ! empty( $where_params ) ) {
			$total_items = (int) $wpdb->get_var( $wpdb->prepare( $count_query, $where_params ) );
		} else {
			$total_items = (int) $wpdb->get_var( $count_query );
		}

		$total_pages = ceil( $total_items / $per_page );

		// Query records
		$query = "SELECT r.*,
		                 p.title AS property_title, p.address_street AS property_street,
		                 p.unit_number AS property_unit, p.city AS property_city,
		                 p.state AS property_state, p.postal_code AS property_zip,
		                 u_owner.display_name AS owner_name, u_owner.user_email AS owner_email,
		                 ra.id AS association_id, ra.tenant_user_id, ra.tenant_identity_id AS assoc_identity_id,
		                 ra.status AS assoc_status, ra.visibility_status, ra.associated_at,
		                 ra.disclosed_at, ra.revoked_at,
		                 u_tenant.display_name AS tenant_name, u_tenant.user_email AS tenant_email
		          {$base_from_sql}
		          WHERE {$where_sql}
		          GROUP BY r.id
		          ORDER BY r.id DESC
		          LIMIT %d OFFSET %d";

		$query_params = array_merge( $where_params, array( $per_page, $offset ) );
		$rows         = $wpdb->get_results( $wpdb->prepare( $query, $query_params ) ) ?: array();

		$items = array();
		foreach ( $rows as $row ) {
			$items[] = self::format_report_queue_item( $row );
		}

		return array(
			'success'       => true,
			'items'         => $items,
			'total_items'   => $total_items,
			'total_pages'   => $total_pages,
			'current_page'  => $page,
			'per_page'      => $per_page,
			'status_counts' => $status_counts,
			'active_filter' => $status,
		);
	}

	/**
	 * Get breakdown counters for all queue filter tabs.
	 *
	 * @return array<string, int>
	 */
	public static function get_queue_status_counts(): array {
		global $wpdb;

		$rep_table   = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;
		$assoc_table = $wpdb->prefix . Constants::TABLE_REPORT_ASSOCIATIONS;

		$counts = array(
			'all'                 => 0,
			'unassociated'        => 0,
			'associated'          => 0,
			'awaiting_visibility' => 0,
			'tenant_visible'      => 0,
			'visibility_revoked'  => 0,
		);

		$counts['all'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$rep_table}" );

		$counts['unassociated'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$rep_table} r 
			 LEFT JOIN {$assoc_table} ra ON (ra.report_id = r.id AND ra.status = 'active')
			 WHERE ra.id IS NULL"
		);

		$counts['associated'] = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT r.id) FROM {$rep_table} r 
			 INNER JOIN {$assoc_table} ra ON (ra.report_id = r.id AND ra.status = 'active')"
		);

		$counts['awaiting_visibility'] = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT r.id) FROM {$rep_table} r 
			 INNER JOIN {$assoc_table} ra ON (ra.report_id = r.id AND ra.status = 'active')
			 WHERE r.status = 'approved' AND (ra.visibility_status = 'hidden' OR r.disclosable = 0)"
		);

		$counts['tenant_visible'] = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT r.id) FROM {$rep_table} r 
			 INNER JOIN {$assoc_table} ra ON (ra.report_id = r.id AND ra.status = 'active')
			 WHERE r.status = 'approved' AND ra.visibility_status = 'visible' AND r.disclosable = 1"
		);

		$counts['visibility_revoked'] = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT r.id) FROM {$rep_table} r 
			 INNER JOIN {$assoc_table} ra ON (ra.report_id = r.id)
			 WHERE ra.visibility_status = 'revoked'"
		);

		return $counts;
	}

	/**
	 * Retrieve a detailed report record with associations, evidence, and history.
	 *
	 * @param int $report_id Incident report ID.
	 * @param int $admin_id  Administrator ID.
	 * @return array
	 */
	public static function get_report_visibility_detail( int $report_id, int $admin_id ): array {
		if ( ! \FraudTenant\Services\ComplianceService::is_data_gate_open() ) {
			return array(
				'success' => false,
				'error'   => 'visibility_locked_blk08',
				'message' => __( 'Report inspection is locked in production mode pending sensitive PII encryption infrastructure (BLK-08).', 'fraud-tenant-core' ),
			);
		}

		if ( ! self::can_manage_visibility( $admin_id ) ) {
			return array(
				'success' => false,
				'error'   => 'unauthorized',
				'message' => __( 'You do not have permission to inspect report visibility.', 'fraud-tenant-core' ),
			);
		}

		global $wpdb;

		$rep_table   = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;
		$prop_table  = $wpdb->prefix . Constants::TABLE_PROPERTIES;
		$doc_table   = $wpdb->prefix . Constants::TABLE_DOCUMENTS;
		$assoc_table = $wpdb->prefix . Constants::TABLE_REPORT_ASSOCIATIONS;
		$hist_table  = $wpdb->prefix . Constants::TABLE_REPORT_DISCLOSURE_HISTORY;

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
			$doc->id            = (int) $doc->id;
			$doc->file_size_fmt = size_format( (int) $doc->file_size );
			$doc->stream_nonce  = wp_create_nonce( 'ft_view_doc_' . $doc->id );
			$doc->view_url      = add_query_arg(
				array(
					'action'      => 'ft_view_document',
					'document_id' => $doc->id,
					'_wpnonce'    => $doc->stream_nonce,
				),
				admin_url( 'admin-post.php' )
			);
		}
		unset( $doc );

		// Load associations
		$associations = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ra.*, 
				        u.display_name AS tenant_name, u.user_email AS tenant_email,
				        u_assoc.display_name AS associated_by_name,
				        u_disc.display_name AS disclosed_by_name,
				        u_rev.display_name AS revoked_by_name
				 FROM {$assoc_table} ra
				 LEFT JOIN {$wpdb->users} u ON ra.tenant_user_id = u.ID
				 LEFT JOIN {$wpdb->users} u_assoc ON ra.associated_by = u_assoc.ID
				 LEFT JOIN {$wpdb->users} u_disc ON ra.disclosed_by = u_disc.ID
				 LEFT JOIN {$wpdb->users} u_rev ON ra.revoked_by = u_rev.ID
				 WHERE ra.report_id = %d
				 ORDER BY ra.id DESC",
				$report_id
			)
		) ?: array();

		// Load history trail
		$history = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT h.*, 
				        u_actor.display_name AS actor_name,
				        u_tenant.display_name AS tenant_name
				 FROM {$hist_table} h
				 LEFT JOIN {$wpdb->users} u_actor ON h.performed_by = u_actor.ID
				 LEFT JOIN {$wpdb->users} u_tenant ON h.tenant_user_id = u_tenant.ID
				 WHERE h.report_id = %d
				 ORDER BY h.id DESC",
				$report_id
			)
		) ?: array();

		// Load verified tenant candidate list for association
		$tenant_candidates = self::get_tenant_candidates();

		return array(
			'success'           => true,
			'report'            => $report,
			'evidence_docs'     => $evidence_docs,
			'associations'      => $associations,
			'history'           => $history,
			'tenant_candidates' => $tenant_candidates,
		);
	}

	/**
	 * Get list of registered tenant accounts available for administrative association.
	 *
	 * @return array
	 */
	public static function get_tenant_candidates(): array {
		global $wpdb;

		$ident_table   = $wpdb->prefix . Constants::TABLE_TENANT_IDENTITIES;
		$profile_table = $wpdb->prefix . Constants::TABLE_USER_PROFILES;

		$users = get_users(
			array(
				'role'    => 'ft_tenant',
				'orderby' => 'display_name',
				'order'   => 'ASC',
				'number'  => 100,
			)
		);

		$candidates = array();
		foreach ( $users as $u ) {
			$profile = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$profile_table} WHERE user_id = %d", $u->ID ) );
			$ident   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$ident_table} WHERE user_id = %d", $u->ID ) );

			$status_label = $profile ? $profile->approval_status : 'registered';

			$candidates[] = array(
				'user_id'         => (int) $u->ID,
				'display_name'    => $u->display_name,
				'user_email'      => $u->user_email,
				'approval_status' => $status_label,
				'identity_id'     => $ident ? (int) $ident->id : 0,
				'ssn_last_four'   => $ident ? $ident->ssn_last_four : '••••',
			);
		}

		return $candidates;
	}

	/**
	 * Associate an incident report with a verified tenant account.
	 *
	 * @param int    $report_id      Incident report ID.
	 * @param int    $tenant_user_id WordPress User ID of tenant.
	 * @param string $reason         Administrative justification.
	 * @param string $notes          Optional internal notes.
	 * @param int    $admin_id       Acting administrator ID.
	 * @return array Result payload.
	 */
	public static function associate_tenant( int $report_id, int $tenant_user_id, string $reason, string $notes, int $admin_id ): array {
		if ( $report_id <= 0 || $tenant_user_id <= 0 || $admin_id <= 0 ) {
			return array(
				'success' => false,
				'error'   => 'invalid_parameters',
				'message' => __( 'Invalid report, tenant, or administrator identifier.', 'fraud-tenant-core' ),
			);
		}

		if ( ! \FraudTenant\Services\ComplianceService::is_data_gate_open() ) {
			return array(
				'success' => false,
				'error'   => 'visibility_locked_blk08',
				'message' => __( 'Tenant association is locked in production mode pending sensitive PII encryption infrastructure (BLK-08).', 'fraud-tenant-core' ),
			);
		}

		if ( ! self::can_associate_tenant( $admin_id ) ) {
			return array(
				'success' => false,
				'error'   => 'unauthorized',
				'message' => __( 'You do not have permission to associate reports with tenant accounts.', 'fraud-tenant-core' ),
			);
		}

		$reason = trim( sanitize_textarea_field( $reason ) );
		if ( strlen( $reason ) < 5 ) {
			return array(
				'success' => false,
				'error'   => 'reason_required',
				'message' => __( 'An administrative reason for association is required (minimum 5 characters).', 'fraud-tenant-core' ),
			);
		}

		$notes = trim( sanitize_textarea_field( $notes ) );

		// Validate tenant account
		$tenant_user = get_user_by( 'id', $tenant_user_id );
		if ( ! $tenant_user || ! in_array( 'ft_tenant', (array) $tenant_user->roles, true ) ) {
			return array(
				'success' => false,
				'error'   => 'invalid_tenant',
				'message' => __( 'The selected user account is not a valid tenant.', 'fraud-tenant-core' ),
			);
		}

		global $wpdb;

		$rep_table   = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;
		$ident_table = $wpdb->prefix . Constants::TABLE_TENANT_IDENTITIES;
		$assoc_table = $wpdb->prefix . Constants::TABLE_REPORT_ASSOCIATIONS;
		$hist_table  = $wpdb->prefix . Constants::TABLE_REPORT_DISCLOSURE_HISTORY;

		// Verify report exists
		$report = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$rep_table} WHERE id = %d", $report_id ) );
		if ( ! $report ) {
			return array(
				'success' => false,
				'error'   => 'report_not_found',
				'message' => __( 'Incident report record was not found.', 'fraud-tenant-core' ),
			);
		}

		// Single-active-association check: At most ONE active association permitted per report
		$existing_active = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$assoc_table} WHERE report_id = %d AND status = 'active' LIMIT 1",
				$report_id
			)
		);
		if ( $existing_active ) {
			if ( (int) $existing_active->tenant_user_id === $tenant_user_id ) {
				return array(
					'success' => false,
					'error'   => 'already_associated',
					'message' => __( 'This tenant is already actively associated with this incident report.', 'fraud-tenant-core' ),
				);
			}
			return array(
				'success' => false,
				'error'   => 'report_already_associated',
				'message' => __( 'This incident report already has an active tenant association. You must disassociate the existing tenant before associating a new tenant.', 'fraud-tenant-core' ),
			);
		}

		// Resolve or seed tenant identity record
		$identity = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$ident_table} WHERE user_id = %d", $tenant_user_id ) );
		$tenant_identity_id = $identity ? (int) $identity->id : 0;

		if ( ! $identity ) {
			$name_parts = explode( ' ', $tenant_user->display_name, 2 );
			$first_name = $name_parts[0] ?? 'Tenant';
			$last_name  = $name_parts[1] ?? 'User';
			$token_hash = hash( 'sha256', 'tenant_ident_' . $tenant_user_id . '_' . wp_generate_password( 12, false ) );

			$wpdb->insert(
				$ident_table,
				array(
					'user_id'       => $tenant_user_id,
					'token_hash'    => $token_hash,
					'ssn_last_four' => '••••',
					'first_name'    => $first_name,
					'last_name'     => $last_name,
					'dob'           => null,
					'created_at'    => current_time( 'mysql' ),
					'updated_at'    => current_time( 'mysql' ),
				)
			);
			$tenant_identity_id = (int) $wpdb->insert_id;
		}

		$now = current_time( 'mysql' );

		// Execute atomic association in an explicit transaction
		$wpdb->query( 'START TRANSACTION' );

		try {
			// Lock parent incident report row to serialize concurrent association attempts
			$locked_report = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, tenant_identity_id FROM {$rep_table} WHERE id = %d FOR UPDATE",
					$report_id
				)
			);
			if ( ! $locked_report ) {
				$wpdb->query( 'ROLLBACK' );
				return array(
					'success' => false,
					'error'   => 'report_not_found',
					'message' => __( 'Incident report record was not found.', 'fraud-tenant-core' ),
				);
			}

			// Concurrency guard: re-verify no active association was created in race
			$concurrent_active = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$assoc_table} WHERE report_id = %d AND status = 'active' LIMIT 1 FOR UPDATE",
					$report_id
				)
			);
			if ( $concurrent_active ) {
				$wpdb->query( 'ROLLBACK' );
				if ( (int) $concurrent_active->tenant_user_id === $tenant_user_id ) {
					return array(
						'success' => false,
						'error'   => 'already_associated',
						'message' => __( 'This tenant is already actively associated with this incident report.', 'fraud-tenant-core' ),
					);
				}
				return array(
					'success' => false,
					'error'   => 'report_already_associated',
					'message' => __( 'This incident report already has an active tenant association. You must disassociate the existing tenant before associating a new tenant.', 'fraud-tenant-core' ),
				);
			}

			$insert_assoc = $wpdb->insert(
				$assoc_table,
				array(
					'report_id'          => $report_id,
					'tenant_identity_id' => $tenant_identity_id,
					'tenant_user_id'     => $tenant_user_id,
					'status'             => self::STATUS_ACTIVE,
					'visibility_status'  => self::VISIBILITY_HIDDEN,
					'association_notes'  => $notes,
					'associated_by'      => $admin_id,
					'associated_at'      => $now,
					'created_at'         => $now,
					'updated_at'         => $now,
				)
			);
			$association_id = (int) $wpdb->insert_id;

			if ( ! $insert_assoc || $association_id <= 0 ) {
				$wpdb->query( 'ROLLBACK' );
				return array(
					'success' => false,
					'error'   => 'db_error',
					'message' => __( 'Could not create association record.', 'fraud-tenant-core' ),
				);
			}

			// Sync primary tenant identity onto incident report; disclosable MUST remain 0 until explicitly approved
			$update_rep = $wpdb->update(
				$rep_table,
				array(
					'tenant_identity_id' => $tenant_identity_id,
					'disclosable'        => 0,
					'updated_at'         => $now,
				),
				array( 'id' => $report_id ),
				array( '%d', '%d', '%s' ),
				array( '%d' )
			);

			// Record audit history trail
			$ip_address = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
			$user_agent = sanitize_text_field( wp_unslash( substr( $_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255 ) ) );

			$insert_hist = $wpdb->insert(
				$hist_table,
				array(
					'report_id'           => $report_id,
					'association_id'      => $association_id,
					'tenant_identity_id'  => $tenant_identity_id,
					'tenant_user_id'      => $tenant_user_id,
					'action'              => 'associated',
					'previous_visibility' => 'none',
					'new_visibility'      => self::VISIBILITY_HIDDEN,
					'performed_by'        => $admin_id,
					'reason'              => $reason,
					'internal_notes'      => $notes,
					'created_at'          => $now,
					'ip_address'          => $ip_address,
					'user_agent'          => $user_agent,
				)
			);

			if ( false === $update_rep || ! $insert_hist ) {
				$wpdb->query( 'ROLLBACK' );
				return array(
					'success' => false,
					'error'   => 'db_error',
					'message' => __( 'Could not finalize association transaction.', 'fraud-tenant-core' ),
				);
			}

			$wpdb->query( 'COMMIT' );
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			return array(
				'success' => false,
				'error'   => 'transaction_failed',
				'message' => __( 'An unexpected error occurred during association: ', 'fraud-tenant-core' ) . $e->getMessage(),
			);
		}

		AuditLogger::log(
			'report.tenant_associated',
			'incident_report',
			$report_id,
			array(
				'tenant_user_id'     => $tenant_user_id,
				'tenant_identity_id' => $tenant_identity_id,
				'association_id'     => $association_id,
				'reason'             => $reason,
			),
			$admin_id
		);

		return array(
			'success'        => true,
			'association_id' => $association_id,
			'message'        => __( 'Tenant successfully associated with incident report.', 'fraud-tenant-core' ),
		);
	}

	/**
	 * Disassociate a tenant from an incident report.
	 *
	 * Preserves historical association and disclosure records while clearing
	 * active association and resetting report tenant_identity_id and disclosable.
	 *
	 * @param int    $report_id      Incident report ID.
	 * @param int    $association_id Association ID.
	 * @param string $reason         Administrative justification.
	 * @param int    $admin_id       Acting administrator ID.
	 * @return array
	 */
	public static function disassociate_tenant( int $report_id, int $association_id, string $reason, int $admin_id ): array {
		if ( $report_id <= 0 || $association_id <= 0 || $admin_id <= 0 ) {
			return array(
				'success' => false,
				'error'   => 'invalid_parameters',
				'message' => __( 'Invalid report, association, or administrator identifier.', 'fraud-tenant-core' ),
			);
		}

		if ( ! \FraudTenant\Services\ComplianceService::is_data_gate_open() ) {
			return array(
				'success' => false,
				'error'   => 'visibility_locked_blk08',
				'message' => __( 'Tenant disassociation is locked in production mode pending sensitive PII encryption infrastructure (BLK-08).', 'fraud-tenant-core' ),
			);
		}

		if ( ! self::can_associate_tenant( $admin_id ) ) {
			return array(
				'success' => false,
				'error'   => 'unauthorized',
				'message' => __( 'You do not have permission to disassociate reports.', 'fraud-tenant-core' ),
			);
		}

		$reason = trim( sanitize_textarea_field( $reason ) );
		if ( strlen( $reason ) < 5 ) {
			return array(
				'success' => false,
				'error'   => 'reason_required',
				'message' => __( 'An administrative reason for disassociation is required (minimum 5 characters).', 'fraud-tenant-core' ),
			);
		}

		global $wpdb;

		$rep_table   = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;
		$assoc_table = $wpdb->prefix . Constants::TABLE_REPORT_ASSOCIATIONS;
		$hist_table  = $wpdb->prefix . Constants::TABLE_REPORT_DISCLOSURE_HISTORY;

		$now = current_time( 'mysql' );

		$wpdb->query( 'START TRANSACTION' );

		try {
			$assoc = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$assoc_table} WHERE id = %d AND report_id = %d FOR UPDATE",
					$association_id,
					$report_id
				)
			);

			if ( ! $assoc || self::STATUS_ACTIVE !== $assoc->status ) {
				$wpdb->query( 'ROLLBACK' );
				return array(
					'success' => false,
					'error'   => 'association_not_active',
					'message' => __( 'Active association record was not found.', 'fraud-tenant-core' ),
				);
			}

			$prev_visibility = $assoc->visibility_status;

			// Mark association as disassociated (preserves row in database for history!)
			$update_assoc = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$assoc_table} 
					 SET status = %s, visibility_status = %s, revoked_by = %d, revoked_at = %s, revocation_reason = %s, updated_at = %s 
					 WHERE id = %d AND status = 'active'",
					self::STATUS_DISASSOCIATED,
					self::VISIBILITY_REVOKED,
					$admin_id,
					$now,
					$reason,
					$now,
					$association_id
				)
			);

			if ( 1 !== $update_assoc ) {
				$wpdb->query( 'ROLLBACK' );
				return array(
					'success' => false,
					'error'   => 'conflict_already_modified',
					'message' => __( 'The association record was modified by another administrator.', 'fraud-tenant-core' ),
				);
			}

			// Under the single-active-association model, removing active association resets identity and disclosable
			$update_rep = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$rep_table} 
					 SET tenant_identity_id = NULL, disclosable = 0, updated_at = %s 
					 WHERE id = %d",
					$now,
					$report_id
				)
			);

			if ( false === $update_rep ) {
				$wpdb->query( 'ROLLBACK' );
				return array(
					'success' => false,
					'error'   => 'db_error',
					'message' => __( 'Could not update report identity status.', 'fraud-tenant-core' ),
				);
			}

			// Record audit history trail
			$ip_address = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
			$user_agent = sanitize_text_field( wp_unslash( substr( $_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255 ) ) );

			$insert_hist = $wpdb->insert(
				$hist_table,
				array(
					'report_id'           => $report_id,
					'association_id'      => $association_id,
					'tenant_identity_id'  => $assoc->tenant_identity_id,
					'tenant_user_id'      => $assoc->tenant_user_id,
					'action'              => 'disassociated',
					'previous_visibility' => $prev_visibility,
					'new_visibility'      => self::VISIBILITY_REVOKED,
					'performed_by'        => $admin_id,
					'reason'              => $reason,
					'created_at'          => $now,
					'ip_address'          => $ip_address,
					'user_agent'          => $user_agent,
				)
			);

			if ( ! $insert_hist ) {
				$wpdb->query( 'ROLLBACK' );
				return array(
					'success' => false,
					'error'   => 'db_error',
					'message' => __( 'Could not finalize disassociation transaction.', 'fraud-tenant-core' ),
				);
			}

			$wpdb->query( 'COMMIT' );
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			return array(
				'success' => false,
				'error'   => 'transaction_failed',
				'message' => __( 'An unexpected error occurred during disassociation: ', 'fraud-tenant-core' ) . $e->getMessage(),
			);
		}

		AuditLogger::log(
			'report.tenant_disassociated',
			'incident_report',
			$report_id,
			array(
				'tenant_user_id'     => (int) $assoc->tenant_user_id,
				'tenant_identity_id' => (int) $assoc->tenant_identity_id,
				'association_id'     => $association_id,
				'reason'             => $reason,
			),
			$admin_id
		);

		return array(
			'success' => true,
			'message' => __( 'Tenant successfully disassociated from incident report.', 'fraud-tenant-core' ),
		);
	}

	/**
	 * Approve tenant visibility for an associated incident report.
	 *
	 * Pre-requisite: The incident report MUST have status = 'approved' (from Phase 8.2 review).
	 *
	 * @param int    $report_id      Incident report ID.
	 * @param int    $association_id Association ID.
	 * @param string $reason         Administrative justification for disclosure.
	 * @param int    $admin_id       Acting administrator ID.
	 * @return array
	 */
	public static function approve_visibility( int $report_id, int $association_id, string $reason, int $admin_id ): array {
		if ( $report_id <= 0 || $association_id <= 0 || $admin_id <= 0 ) {
			return array(
				'success' => false,
				'error'   => 'invalid_parameters',
				'message' => __( 'Invalid report, association, or administrator identifier.', 'fraud-tenant-core' ),
			);
		}

		if ( ! \FraudTenant\Services\ComplianceService::is_data_gate_open() ) {
			return array(
				'success' => false,
				'error'   => 'visibility_locked_blk08',
				'message' => __( 'Visibility approval is locked in production mode pending sensitive PII encryption infrastructure (BLK-08).', 'fraud-tenant-core' ),
			);
		}

		if ( ! self::can_manage_report_visibility( $admin_id ) ) {
			return array(
				'success' => false,
				'error'   => 'unauthorized',
				'message' => __( 'You do not have permission to approve report visibility.', 'fraud-tenant-core' ),
			);
		}

		$reason = trim( sanitize_textarea_field( $reason ) );
		if ( strlen( $reason ) < 5 ) {
			return array(
				'success' => false,
				'error'   => 'reason_required',
				'message' => __( 'An administrative reason for visibility approval is required (minimum 5 characters).', 'fraud-tenant-core' ),
			);
		}

		global $wpdb;

		$rep_table   = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;
		$assoc_table = $wpdb->prefix . Constants::TABLE_REPORT_ASSOCIATIONS;
		$hist_table  = $wpdb->prefix . Constants::TABLE_REPORT_DISCLOSURE_HISTORY;

		// 1. Strict Check: Report MUST be approved by Phase 8.2 administrative review
		$report = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$rep_table} WHERE id = %d", $report_id ) );
		if ( ! $report ) {
			return array(
				'success' => false,
				'error'   => 'report_not_found',
				'message' => __( 'Incident report record was not found.', 'fraud-tenant-core' ),
			);
		}

		if ( 'approved' !== $report->status ) {
			return array(
				'success' => false,
				'error'   => 'report_not_approved',
				'message' => __( 'Incident report must be approved by administrative review before visibility can be granted to a tenant.', 'fraud-tenant-core' ),
			);
		}

		$now = current_time( 'mysql' );

		$wpdb->query( 'START TRANSACTION' );

		try {
			// 2. Association must exist and be active
			$assoc = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$assoc_table} WHERE id = %d AND report_id = %d FOR UPDATE",
					$association_id,
					$report_id
				)
			);

			if ( ! $assoc || self::STATUS_ACTIVE !== $assoc->status ) {
				$wpdb->query( 'ROLLBACK' );
				return array(
					'success' => false,
					'error'   => 'association_not_active',
					'message' => __( 'Active association record was not found.', 'fraud-tenant-core' ),
				);
			}

			if ( self::VISIBILITY_VISIBLE === $assoc->visibility_status && 1 === (int) $report->disclosable ) {
				$wpdb->query( 'ROLLBACK' );
				return array(
					'success' => false,
					'error'   => 'already_visible',
					'message' => __( 'This incident report is already visible to the associated tenant.', 'fraud-tenant-core' ),
				);
			}

			$prev_visibility = $assoc->visibility_status;

			// Update association visibility status
			$update_assoc = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$assoc_table} 
					 SET visibility_status = %s, disclosed_by = %d, disclosed_at = %s, revoked_by = NULL, revoked_at = NULL, revocation_reason = NULL, updated_at = %s 
					 WHERE id = %d AND status = 'active'",
					self::VISIBILITY_VISIBLE,
					$admin_id,
					$now,
					$now,
					$association_id
				)
			);

			if ( 1 !== $update_assoc ) {
				$wpdb->query( 'ROLLBACK' );
				return array(
					'success' => false,
					'error'   => 'conflict_already_modified',
					'message' => __( 'The association record was modified by another administrator.', 'fraud-tenant-core' ),
				);
			}

			// Activate disclosable flag on report
			$update_rep = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$rep_table} 
					 SET disclosable = 1, tenant_identity_id = %d, updated_at = %s 
					 WHERE id = %d AND status = 'approved'",
					$assoc->tenant_identity_id,
					$now,
					$report_id
				)
			);

			if ( false === $update_rep ) {
				$wpdb->query( 'ROLLBACK' );
				return array(
					'success' => false,
					'error'   => 'db_error',
					'message' => __( 'Could not update report disclosure status.', 'fraud-tenant-core' ),
				);
			}

			// Record audit history trail
			$ip_address = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
			$user_agent = sanitize_text_field( wp_unslash( substr( $_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255 ) ) );

			$insert_hist = $wpdb->insert(
				$hist_table,
				array(
					'report_id'           => $report_id,
					'association_id'      => $association_id,
					'tenant_identity_id'  => $assoc->tenant_identity_id,
					'tenant_user_id'      => $assoc->tenant_user_id,
					'action'              => 'visibility_approved',
					'previous_visibility' => $prev_visibility,
					'new_visibility'      => self::VISIBILITY_VISIBLE,
					'performed_by'        => $admin_id,
					'reason'              => $reason,
					'created_at'          => $now,
					'ip_address'          => $ip_address,
					'user_agent'          => $user_agent,
				)
			);

			if ( ! $insert_hist ) {
				$wpdb->query( 'ROLLBACK' );
				return array(
					'success' => false,
					'error'   => 'db_error',
					'message' => __( 'Could not finalize visibility approval transaction.', 'fraud-tenant-core' ),
				);
			}

			$wpdb->query( 'COMMIT' );
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			return array(
				'success' => false,
				'error'   => 'transaction_failed',
				'message' => __( 'An unexpected error occurred during visibility approval: ', 'fraud-tenant-core' ) . $e->getMessage(),
			);
		}

		AuditLogger::log(
			'report.visibility_approved',
			'incident_report',
			$report_id,
			array(
				'tenant_user_id'     => (int) $assoc->tenant_user_id,
				'tenant_identity_id' => (int) $assoc->tenant_identity_id,
				'association_id'     => $association_id,
				'reason'             => $reason,
			),
			$admin_id
		);

		return array(
			'success' => true,
			'message' => __( 'Report visibility successfully approved for the associated tenant.', 'fraud-tenant-core' ),
		);
	}

	/**
	 * Revoke / withdraw tenant visibility for an associated incident report.
	 *
	 * Does NOT delete the original report or its evidence.
	 *
	 * @param int    $report_id      Incident report ID.
	 * @param int    $association_id Association ID.
	 * @param string $reason         Administrative justification for revocation.
	 * @param int    $admin_id       Acting administrator ID.
	 * @return array
	 */
	public static function revoke_visibility( int $report_id, int $association_id, string $reason, int $admin_id ): array {
		if ( $report_id <= 0 || $association_id <= 0 || $admin_id <= 0 ) {
			return array(
				'success' => false,
				'error'   => 'invalid_parameters',
				'message' => __( 'Invalid report, association, or administrator identifier.', 'fraud-tenant-core' ),
			);
		}

		if ( ! \FraudTenant\Services\ComplianceService::is_data_gate_open() ) {
			return array(
				'success' => false,
				'error'   => 'visibility_locked_blk08',
				'message' => __( 'Visibility revocation is locked in production mode pending sensitive PII encryption infrastructure (BLK-08).', 'fraud-tenant-core' ),
			);
		}

		if ( ! self::can_manage_report_visibility( $admin_id ) ) {
			return array(
				'success' => false,
				'error'   => 'unauthorized',
				'message' => __( 'You do not have permission to revoke report visibility.', 'fraud-tenant-core' ),
			);
		}

		$reason = trim( sanitize_textarea_field( $reason ) );
		if ( strlen( $reason ) < 5 ) {
			return array(
				'success' => false,
				'error'   => 'reason_required',
				'message' => __( 'An administrative reason for visibility revocation is required (minimum 5 characters).', 'fraud-tenant-core' ),
			);
		}

		global $wpdb;

		$rep_table   = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;
		$assoc_table = $wpdb->prefix . Constants::TABLE_REPORT_ASSOCIATIONS;
		$hist_table  = $wpdb->prefix . Constants::TABLE_REPORT_DISCLOSURE_HISTORY;

		$now = current_time( 'mysql' );

		$wpdb->query( 'START TRANSACTION' );

		try {
			$assoc = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$assoc_table} WHERE id = %d AND report_id = %d FOR UPDATE",
					$association_id,
					$report_id
				)
			);

			if ( ! $assoc ) {
				$wpdb->query( 'ROLLBACK' );
				return array(
					'success' => false,
					'error'   => 'association_not_found',
					'message' => __( 'Association record was not found.', 'fraud-tenant-core' ),
				);
			}

			if ( self::VISIBILITY_REVOKED === $assoc->visibility_status ) {
				$wpdb->query( 'ROLLBACK' );
				return array(
					'success' => false,
					'error'   => 'already_revoked',
					'message' => __( 'Visibility is already revoked for this association.', 'fraud-tenant-core' ),
				);
			}

			$prev_visibility = $assoc->visibility_status;

			// Update association visibility status to revoked
			$update_assoc = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$assoc_table} 
					 SET visibility_status = %s, revoked_by = %d, revoked_at = %s, revocation_reason = %s, updated_at = %s 
					 WHERE id = %d",
					self::VISIBILITY_REVOKED,
					$admin_id,
					$now,
					$reason,
					$now,
					$association_id
				)
			);

			if ( 1 !== $update_assoc ) {
				$wpdb->query( 'ROLLBACK' );
				return array(
					'success' => false,
					'error'   => 'conflict_already_modified',
					'message' => __( 'The association record was modified by another administrator.', 'fraud-tenant-core' ),
				);
			}

			// Turn off disclosable flag on report (report and evidence remain 100% intact!)
			$update_rep = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$rep_table} 
					 SET disclosable = 0, updated_at = %s 
					 WHERE id = %d",
					$now,
					$report_id
				)
			);

			if ( false === $update_rep ) {
				$wpdb->query( 'ROLLBACK' );
				return array(
					'success' => false,
					'error'   => 'db_error',
					'message' => __( 'Could not update report disclosure status.', 'fraud-tenant-core' ),
				);
			}

			// Record audit history trail
			$ip_address = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
			$user_agent = sanitize_text_field( wp_unslash( substr( $_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255 ) ) );

			$insert_hist = $wpdb->insert(
				$hist_table,
				array(
					'report_id'           => $report_id,
					'association_id'      => $association_id,
					'tenant_identity_id'  => $assoc->tenant_identity_id,
					'tenant_user_id'      => $assoc->tenant_user_id,
					'action'              => 'visibility_revoked',
					'previous_visibility' => $prev_visibility,
					'new_visibility'      => self::VISIBILITY_REVOKED,
					'performed_by'        => $admin_id,
					'reason'              => $reason,
					'created_at'          => $now,
					'ip_address'          => $ip_address,
					'user_agent'          => $user_agent,
				)
			);

			if ( ! $insert_hist ) {
				$wpdb->query( 'ROLLBACK' );
				return array(
					'success' => false,
					'error'   => 'db_error',
					'message' => __( 'Could not finalize visibility revocation transaction.', 'fraud-tenant-core' ),
				);
			}

			$wpdb->query( 'COMMIT' );
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			return array(
				'success' => false,
				'error'   => 'transaction_failed',
				'message' => __( 'An unexpected error occurred during visibility revocation: ', 'fraud-tenant-core' ) . $e->getMessage(),
			);
		}

		AuditLogger::log(
			'report.visibility_revoked',
			'incident_report',
			$report_id,
			array(
				'tenant_user_id'     => (int) $assoc->tenant_user_id,
				'tenant_identity_id' => (int) $assoc->tenant_identity_id,
				'association_id'     => $association_id,
				'reason'             => $reason,
			),
			$admin_id
		);

		return array(
			'success' => true,
			'message' => __( 'Report visibility successfully revoked. Record is no longer visible to tenant.', 'fraud-tenant-core' ),
		);
	}

	/**
	 * Retrieve all approved and disclosable incident reports for a specific tenant.
	 *
	 * Strict Anti-IDOR & Authoritative Invariant:
	 * Tenant can ONLY retrieve reports where:
	 * - Tenant is the currently authorized associated account (active association)
	 * - Association visibility status is 'visible'
	 * - Report status is 'approved'
	 * - Report disclosable is 1
	 * - Test mode / BLK-08 security gate is satisfied
	 *
	 * @param int $tenant_user_id WordPress User ID of tenant.
	 * @return array List of disclosed report objects.
	 */
	public static function get_tenant_disclosed_reports( int $tenant_user_id ): array {
		if ( $tenant_user_id <= 0 ) {
			return array();
		}

		if ( ! \FraudTenant\Services\ComplianceService::is_data_gate_open() ) {
			return array();
		}

		global $wpdb;

		$rep_table   = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;
		$prop_table  = $wpdb->prefix . Constants::TABLE_PROPERTIES;
		$doc_table   = $wpdb->prefix . Constants::TABLE_DOCUMENTS;
		$assoc_table = $wpdb->prefix . Constants::TABLE_REPORT_ASSOCIATIONS;

		// Authoritative Invariant: Must have an ACTIVE association where visibility_status = 'visible', report status = 'approved', and disclosable = 1
		$query = "SELECT DISTINCT r.*, 
		                 p.title AS property_title, p.address_street AS property_street,
		                 p.unit_number AS property_unit, p.city AS property_city,
		                 p.state AS property_state, p.postal_code AS property_zip,
		                 ra.disclosed_at AS report_disclosed_at
		          FROM {$rep_table} r
		          INNER JOIN {$assoc_table} ra ON (ra.report_id = r.id AND ra.tenant_user_id = %d AND ra.status = 'active' AND ra.visibility_status = 'visible')
		          LEFT JOIN {$prop_table} p ON r.property_id = p.id
		          WHERE r.status = 'approved'
		            AND r.disclosable = 1
		          ORDER BY r.incident_date DESC, r.id DESC";

		$rows = $wpdb->get_results( $wpdb->prepare( $query, $tenant_user_id ) ) ?: array();

		$reports = array();
		foreach ( $rows as $row ) {
			$row->id               = (int) $row->id;
			$row->owner_id         = (int) $row->owner_id;
			$row->property_id      = (int) $row->property_id;
			$row->reference        = sprintf( 'INC-%06d', $row->id );
			$row->category_label   = IncidentService::ALLOWED_CATEGORIES[ $row->category ] ?? ucwords( str_replace( '_', ' ', $row->category ) );
			$row->amount_formatted = '$' . number_format( (float) $row->amount_involved, 2 );

			// Load evidence documents with tenant-authorized streaming nonces
			$evidence = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, original_name, file_size, mime_type, uploaded_at 
					 FROM {$doc_table} 
					 WHERE object_type = 'incident_evidence' AND object_id = %d",
					$row->id
				)
			) ?: array();

			foreach ( $evidence as &$doc ) {
				$doc->id            = (int) $doc->id;
				$doc->file_size_fmt = size_format( (int) $doc->file_size );
				$doc->stream_nonce  = wp_create_nonce( 'ft_view_doc_' . $doc->id );
				$doc->view_url      = add_query_arg(
					array(
						'action'      => 'ft_view_document',
						'document_id' => $doc->id,
						'_wpnonce'    => $doc->stream_nonce,
					),
					admin_url( 'admin-post.php' )
				);
			}
			unset( $doc );

			$row->evidence_docs = $evidence;
			$reports[]          = $row;
		}

		return $reports;
	}

	/**
	 * Retrieve a single disclosed report detail for an authorized tenant.
	 *
	 * Strict Anti-IDOR: Fails with 404 if the report is not associated or not disclosed.
	 *
	 * @param int $report_id      Incident report ID.
	 * @param int $tenant_user_id Requesting tenant user ID.
	 * @return array
	 */
	public static function get_tenant_report_detail( int $report_id, int $tenant_user_id ): array {
		if ( $report_id <= 0 || $tenant_user_id <= 0 ) {
			return array(
				'success' => false,
				'error'   => 'not_found',
				'message' => __( 'Disclosed report record was not found.', 'fraud-tenant-core' ),
			);
		}

		$reports = self::get_tenant_disclosed_reports( $tenant_user_id );
		foreach ( $reports as $r ) {
			if ( (int) $r->id === $report_id ) {
				return array(
					'success' => true,
					'report'  => $r,
				);
			}
		}

		// Return generic 404 to prevent reference/ID enumeration
		return array(
			'success' => false,
			'error'   => 'not_found',
			'message' => __( 'Disclosed report record was not found or is inaccessible.', 'fraud-tenant-core' ),
		);
	}

	/**
	 * Check if a tenant is actively associated with a report and approved for visibility.
	 * Used by DocumentService to authorize document streaming for tenants.
	 *
	 * @param int $report_id      Incident report ID.
	 * @param int $tenant_user_id Requesting user ID.
	 * @return bool
	 */
	public static function is_tenant_associated_and_visible( int $report_id, int $tenant_user_id ): bool {
		if ( $report_id <= 0 || $tenant_user_id <= 0 ) {
			return false;
		}

		if ( ! \FraudTenant\Services\ComplianceService::is_data_gate_open() ) {
			return false;
		}

		global $wpdb;

		$rep_table   = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;
		$assoc_table = $wpdb->prefix . Constants::TABLE_REPORT_ASSOCIATIONS;

		// Authoritative Invariant: Must be active, visible association on approved, disclosable report
		$check = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) 
				 FROM {$rep_table} r
				 INNER JOIN {$assoc_table} ra ON (ra.report_id = r.id AND ra.tenant_user_id = %d AND ra.status = 'active' AND ra.visibility_status = 'visible')
				 WHERE r.id = %d
				   AND r.status = 'approved'
				   AND r.disclosable = 1",
				$tenant_user_id,
				$report_id
			)
		);

		return ( $check > 0 );
	}

	/**
	 * Helper formatter for queue items.
	 *
	 * @param object $row Raw DB row.
	 * @return object
	 */
	protected static function format_report_queue_item( object $row ): object {
		$item = clone $row;

		$item->id               = (int) $item->id;
		$item->owner_id         = (int) $item->owner_id;
		$item->property_id      = (int) $item->property_id;
		$item->disclosable      = (int) $item->disclosable;
		$item->reference        = sprintf( 'INC-%06d', $item->id );
		$item->category_label   = IncidentService::ALLOWED_CATEGORIES[ $item->category ] ?? ucwords( str_replace( '_', ' ', $item->category ) );
		$item->amount_formatted = '$' . number_format( (float) $item->amount_involved, 2 );

		// Clean up property address
		$address_parts = array_filter(
			array(
				$item->property_street,
				$item->property_unit ? 'Unit ' . $item->property_unit : '',
				$item->property_city,
				$item->property_state,
				$item->property_zip,
			)
		);
		$item->property_address = implode( ', ', $address_parts ) ?: __( 'No address recorded', 'fraud-tenant-core' );

		// Visibility status badge info
		if ( empty( $item->assoc_status ) || self::STATUS_ACTIVE !== $item->assoc_status ) {
			$item->visibility_badge = 'unassociated';
			$item->visibility_label = __( 'Unassociated', 'fraud-tenant-core' );
		} elseif ( self::VISIBILITY_VISIBLE === $item->visibility_status && 1 === $item->disclosable ) {
			$item->visibility_badge = 'visible';
			$item->visibility_label = __( 'Tenant-Visible', 'fraud-tenant-core' );
		} elseif ( self::VISIBILITY_REVOKED === $item->visibility_status ) {
			$item->visibility_badge = 'revoked';
			$item->visibility_label = __( 'Visibility Revoked', 'fraud-tenant-core' );
		} else {
			$item->visibility_badge = 'hidden';
			$item->visibility_label = __( 'Associated (Pending Visibility)', 'fraud-tenant-core' );
		}

		return $item;
	}
}

