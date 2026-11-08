<?php
/**
 * Report access rules and disclosure decisions.
 *
 * Four audiences, four explicit rules (all enforced server-side):
 * 1. Reporting owner — always sees their own reports (any status), their evidence, review feedback.
 * 2. Subject tenant  — sees a report only when ALL hold: status = approved; the report is linked
 *    (tenant_identity_id) to the identity the tenant has verifiably claimed (identity.user_id);
 *    tenant disclosure (disclosable = 1) is on. Never sees owner evidence or internal notes.
 * 3. Other screening owner — sees a report only when: status = approved; it is linked to the
 *    identity matched by their search (SSN token + legal name); screening_disclosure = 1. Free plan:
 *    count only. Standard plan: factual summary. Never evidence, filing owner, street address,
 *    dispute statements/evidence or internal notes.
 * 4. Administrator — capability-scoped access to everything, every change audited.
 *
 * "Submitted" is not "approved", and "approved" is not "visible to everyone": disclosure to the
 * tenant and to screening owners are separate switches, set by an administrator at approval time and
 * changeable later. Identity changes reset both switches. History is append-only
 * (wp_ft_report_disclosure_history).
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

	public const AUDIENCE_TENANT    = 'tenant';
	public const AUDIENCE_SCREENING = 'screening';

	/**
	 * Whether a user may change disclosure settings.
	 *
	 * @param int $user_id User.
	 * @return bool
	 */
	public static function can_manage_disclosure( int $user_id ): bool {
		return $user_id > 0 && ( user_can( $user_id, 'manage_options' ) || user_can( $user_id, Capabilities::MANAGE_REPORT_VISIBILITY ) || user_can( $user_id, Capabilities::DECIDE_INCIDENTS ) );
	}

	// ------------------------------------------------------------------ history

	/**
	 * Append a disclosure / identity history entry.
	 *
	 * @param int         $report_id      Report.
	 * @param string      $action         Action key.
	 * @param int         $actor          Acting user.
	 * @param string      $reason         Reason.
	 * @param int|null    $identity_id    Identity involved.
	 * @param int|null    $tenant_user_id Tenant account involved.
	 * @param string|null $prev           Previous state.
	 * @param string|null $new            New state.
	 */
	public static function record_history( int $report_id, string $action, int $actor, string $reason, ?int $identity_id = null, ?int $tenant_user_id = null, ?string $prev = null, ?string $new = null ): void {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . Constants::TABLE_REPORT_DISCLOSURE_HISTORY,
			array(
				'report_id'           => $report_id,
				'association_id'      => null,
				'tenant_identity_id'  => $identity_id ?: null,
				'tenant_user_id'      => $tenant_user_id ?: null,
				'action'              => sanitize_key( $action ),
				'previous_visibility' => $prev,
				'new_visibility'      => $new,
				'performed_by'        => $actor,
				'reason'              => '' !== $reason ? sanitize_textarea_field( $reason ) : '—',
				'created_at'          => current_time( 'mysql', true ),
				'ip_address'          => AuditLogger::get_client_ip(),
				'user_agent'          => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 ) : null,
			)
		);
	}

	/**
	 * History for a report (admin).
	 *
	 * @param int $report_id Report.
	 * @return array
	 */
	public static function get_history( int $report_id ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( "SELECT h.action, h.previous_visibility, h.new_visibility, h.reason, h.created_at, h.tenant_identity_id, u.display_name AS actor FROM {$wpdb->prefix}" . Constants::TABLE_REPORT_DISCLOSURE_HISTORY . " h LEFT JOIN {$wpdb->users} u ON u.ID = h.performed_by WHERE h.report_id = %d ORDER BY h.id ASC", $report_id ) ) ?: array();
	}

	// ------------------------------------------------------------------ decisions

	/**
	 * Turn disclosure to an audience on or off.
	 *
	 * @param int    $report_id Report.
	 * @param string $audience  'tenant' | 'screening'.
	 * @param bool   $enable    On/off.
	 * @param int    $admin_id  Administrator.
	 * @param string $reason    Reason (required).
	 * @return array{success: bool, error?: string, message: string}
	 */
	public static function set_disclosure( int $report_id, string $audience, bool $enable, int $admin_id, string $reason ): array {
		global $wpdb;
		if ( ! self::can_manage_disclosure( $admin_id ) ) {
			return array( 'success' => false, 'error' => 'unauthorized', 'message' => __( 'You are not authorized to change report disclosure.', 'fraud-tenant-core' ) );
		}
		if ( ! ComplianceService::is_data_gate_open() ) {
			return array( 'success' => false, 'error' => 'locked_blk08', 'message' => __( 'Report disclosure is unavailable until tenant data protection is configured.', 'fraud-tenant-core' ) );
		}
		if ( ! in_array( $audience, array( self::AUDIENCE_TENANT, self::AUDIENCE_SCREENING ), true ) ) {
			return array( 'success' => false, 'error' => 'invalid_audience', 'message' => __( 'Unknown disclosure audience.', 'fraud-tenant-core' ) );
		}
		$reason = trim( sanitize_textarea_field( $reason ) );
		if ( mb_strlen( $reason ) < 5 ) {
			return array( 'success' => false, 'error' => 'reason_required', 'message' => __( 'Enter a reason for the disclosure change (at least 5 characters).', 'fraud-tenant-core' ) );
		}
		$table  = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;
		$report = $wpdb->get_row( $wpdb->prepare( "SELECT id, owner_id, status, tenant_identity_id, disclosable, screening_disclosure FROM {$table} WHERE id = %d", $report_id ) );
		if ( ! $report ) {
			return array( 'success' => false, 'error' => 'not_found', 'message' => __( 'Incident report not found.', 'fraud-tenant-core' ) );
		}
		if ( (int) $report->owner_id === $admin_id ) {
			return array( 'success' => false, 'error' => 'self_report', 'message' => __( 'You cannot change disclosure of your own report.', 'fraud-tenant-core' ) );
		}
		if ( $enable && Constants::INCIDENT_STATUS_APPROVED !== $report->status ) {
			return array( 'success' => false, 'error' => 'not_approved', 'message' => __( 'Only approved reports can be disclosed.', 'fraud-tenant-core' ) );
		}
		if ( $enable && empty( $report->tenant_identity_id ) ) {
			return array( 'success' => false, 'error' => 'no_identity', 'message' => __( 'Link the report to the correct tenant identity before disclosing it.', 'fraud-tenant-core' ) );
		}
		$col     = self::AUDIENCE_TENANT === $audience ? 'disclosable' : 'screening_disclosure';
		$current = (bool) $report->{$col};
		if ( $current === $enable ) {
			return array( 'success' => true, 'message' => __( 'No change: the disclosure setting was already in that state.', 'fraud-tenant-core' ) );
		}
		$now  = current_time( 'mysql', true );
		$data = array( $col => $enable ? 1 : 0, 'updated_at' => $now );
		if ( self::AUDIENCE_SCREENING === $audience ) {
			$data['screening_disclosed_by'] = $enable ? $admin_id : null;
			$data['screening_disclosed_at'] = $enable ? $now : null;
		}
		$wpdb->update( $table, $data, array( 'id' => $report_id ) );
		$identity = IdentityService::get( (int) $report->tenant_identity_id );
		self::record_history( $report_id, $audience . ( $enable ? '_disclosed' : '_withheld' ), $admin_id, $reason, (int) $report->tenant_identity_id ?: null, $identity ? (int) $identity->user_id : null, $current ? 'visible' : 'hidden', $enable ? 'visible' : 'hidden' );
		AuditLogger::log( 'report.' . $audience . '_disclosure_' . ( $enable ? 'enabled' : 'disabled' ), 'incident_report', $report_id, array(), $admin_id );
		if ( self::AUDIENCE_TENANT === $audience && $enable ) {
			self::notify_tenant_record_available( $report_id );
		}
		return array( 'success' => true, 'message' => __( 'Disclosure setting updated.', 'fraud-tenant-core' ) );
	}

	/**
	 * Apply the disclosure choices made in the approval decision (called after approval commits).
	 *
	 * @param int  $report_id Report.
	 * @param bool $tenant    Disclose to the subject tenant.
	 * @param bool $screening Include in screening results.
	 * @param int  $admin_id  Administrator.
	 */
	public static function apply_on_approval( int $report_id, bool $tenant, bool $screening, int $admin_id ): void {
		global $wpdb;
		$table  = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;
		$report = $wpdb->get_row( $wpdb->prepare( "SELECT id, tenant_identity_id FROM {$table} WHERE id = %d AND status = %s", $report_id, Constants::INCIDENT_STATUS_APPROVED ) );
		if ( ! $report || empty( $report->tenant_identity_id ) ) {
			return;
		}
		$now = current_time( 'mysql', true );
		$wpdb->update(
			$table,
			array(
				'disclosable'            => $tenant ? 1 : 0,
				'screening_disclosure'   => $screening ? 1 : 0,
				'screening_disclosed_by' => $screening ? $admin_id : null,
				'screening_disclosed_at' => $screening ? $now : null,
				'updated_at'             => $now,
			),
			array( 'id' => $report_id )
		);
		$identity = IdentityService::get( (int) $report->tenant_identity_id );
		$uid      = $identity ? (int) $identity->user_id : null;
		self::record_history( $report_id, $tenant ? 'tenant_disclosed' : 'tenant_withheld', $admin_id, __( 'Disclosure decided at approval.', 'fraud-tenant-core' ), (int) $report->tenant_identity_id, $uid, 'hidden', $tenant ? 'visible' : 'hidden' );
		self::record_history( $report_id, $screening ? 'screening_disclosed' : 'screening_withheld', $admin_id, __( 'Disclosure decided at approval.', 'fraud-tenant-core' ), (int) $report->tenant_identity_id, $uid, 'hidden', $screening ? 'visible' : 'hidden' );
		if ( $tenant ) {
			self::notify_tenant_record_available( $report_id );
		}
	}

	/**
	 * Tell the claimed tenant that a reviewed record is now visible to them.
	 *
	 * @param int $report_id Report.
	 */
	protected static function notify_tenant_record_available( int $report_id ): void {
		global $wpdb;
		$identity_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT tenant_identity_id FROM {$wpdb->prefix}" . Constants::TABLE_INCIDENT_REPORTS . ' WHERE id = %d', $report_id ) );
		$identity    = IdentityService::get( $identity_id );
		if ( $identity && ! empty( $identity->user_id ) ) {
			NotificationService::notify(
				(int) $identity->user_id,
				__( 'A reviewed rental record was added to your account', 'fraud-tenant-core' ),
				/* translators: %s: report reference */
				sprintf( __( 'Report %s was reviewed by our team and is now visible in your dashboard. If it is inaccurate you can open a dispute.', 'fraud-tenant-core' ), sprintf( 'INC-%06d', $report_id ) ),
				home_url( '/tenant-dashboard/?section=records&report_id=' . $report_id ),
				'record_available',
				'reports'
			);
		}
	}

	// ------------------------------------------------------------------ tenant access

	/**
	 * Rule 2: may this tenant account see this report?
	 *
	 * @param int $report_id      Report.
	 * @param int $tenant_user_id Tenant.
	 * @return bool
	 */
	public static function tenant_can_view( int $report_id, int $tenant_user_id ): bool {
		global $wpdb;
		if ( $report_id <= 0 || $tenant_user_id <= 0 || ! ComplianceService::is_data_gate_open() ) {
			return false;
		}
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}" . Constants::TABLE_INCIDENT_REPORTS . " r
				 INNER JOIN {$wpdb->prefix}" . Constants::TABLE_TENANT_IDENTITIES . ' i ON i.id = r.tenant_identity_id
				 WHERE r.id = %d AND i.user_id = %d AND r.status = %s AND r.disclosable = 1',
				$report_id,
				$tenant_user_id,
				Constants::INCIDENT_STATUS_APPROVED
			)
		);
	}

	/**
	 * Legacy alias (document/dispute callers).
	 *
	 * @param int $report_id      Report.
	 * @param int $tenant_user_id Tenant.
	 * @return bool
	 */
	public static function is_tenant_associated_and_visible( int $report_id, int $tenant_user_id ): bool {
		return self::tenant_can_view( $report_id, $tenant_user_id );
	}

	/**
	 * Reports visible to a tenant (rule 2), newest incident first.
	 *
	 * @param int $tenant_user_id Tenant.
	 * @return array
	 */
	public static function get_tenant_reports( int $tenant_user_id ): array {
		global $wpdb;
		if ( $tenant_user_id <= 0 || ! ComplianceService::is_data_gate_open() ) {
			return array();
		}
		$r    = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;
		$i    = $wpdb->prefix . Constants::TABLE_TENANT_IDENTITIES;
		$p    = $wpdb->prefix . Constants::TABLE_PROPERTIES;
		$d    = $wpdb->prefix . Constants::TABLE_TENANT_DISPUTES;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.id, r.category, r.incident_date, r.amount_involved, r.factual_description, r.status, r.created_at, r.updated_at,
				        p.title AS property_title, p.unit_number AS property_unit, p.city AS property_city, p.state AS property_state,
				        (SELECT d.status FROM {$d} d WHERE d.report_id = r.id AND d.tenant_id = %d ORDER BY d.id DESC LIMIT 1) AS my_dispute_status,
				        (SELECT d.id FROM {$d} d WHERE d.report_id = r.id AND d.tenant_id = %d ORDER BY d.id DESC LIMIT 1) AS my_dispute_id
				 FROM {$r} r
				 INNER JOIN {$i} i ON i.id = r.tenant_identity_id
				 LEFT JOIN {$p} p ON p.id = r.property_id
				 WHERE i.user_id = %d AND r.status = %s AND r.disclosable = 1
				 ORDER BY r.incident_date DESC, r.id DESC",
				$tenant_user_id,
				$tenant_user_id,
				$tenant_user_id,
				Constants::INCIDENT_STATUS_APPROVED
			)
		) ?: array();
		foreach ( $rows as $row ) {
			self::decorate( $row );
		}
		return $rows;
	}

	/**
	 * Legacy alias.
	 *
	 * @param int $tenant_user_id Tenant.
	 * @return array
	 */
	public static function get_tenant_disclosed_reports( int $tenant_user_id ): array {
		return self::get_tenant_reports( $tenant_user_id );
	}

	/**
	 * Single report for its subject tenant (404 semantics: null when not visible).
	 *
	 * @param int $report_id      Report.
	 * @param int $tenant_user_id Tenant.
	 * @return object|null
	 */
	public static function get_tenant_report( int $report_id, int $tenant_user_id ): ?object {
		foreach ( self::get_tenant_reports( $tenant_user_id ) as $row ) {
			if ( (int) $row->id === $report_id ) {
				return $row;
			}
		}
		return null;
	}

	/**
	 * Legacy wrapper returning the old array shape.
	 *
	 * @param int $report_id      Report.
	 * @param int $tenant_user_id Tenant.
	 * @return array
	 */
	public static function get_tenant_report_detail( int $report_id, int $tenant_user_id ): array {
		$r = self::get_tenant_report( $report_id, $tenant_user_id );
		return $r ? array( 'success' => true, 'report' => $r ) : array( 'success' => false, 'error' => 'not_found', 'message' => __( 'Report not found.', 'fraud-tenant-core' ) );
	}

	// ------------------------------------------------------------------ screening access

	/**
	 * Rule 3: records of a matched identity that are released for screening.
	 *
	 * @param int        $identity_id  Matched identity.
	 * @param int        $viewer_id    Searching owner (flags their own reports).
	 * @param int[]|null $restrict_ids Limit to these report IDs (saved search snapshot).
	 * @return array
	 */
	public static function get_screening_records( int $identity_id, int $viewer_id, ?array $restrict_ids = null ): array {
		global $wpdb;
		if ( $identity_id <= 0 || ! ComplianceService::owner_search_ready() ) {
			return array();
		}
		$r      = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;
		$p      = $wpdb->prefix . Constants::TABLE_PROPERTIES;
		$d      = $wpdb->prefix . Constants::TABLE_TENANT_DISPUTES;
		$filter = '';
		if ( null !== $restrict_ids ) {
			$ids = array_filter( array_map( 'intval', $restrict_ids ) );
			if ( ! $ids ) {
				return array();
			}
			$filter = ' AND r.id IN (' . implode( ',', $ids ) . ')';
		}
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.id, r.owner_id, r.category, r.incident_date, r.amount_involved, r.factual_description, r.created_at, r.updated_at,
				        p.city AS property_city, p.state AS property_state,
				        (SELECT d.status FROM {$d} d WHERE d.report_id = r.id ORDER BY d.id DESC LIMIT 1) AS dispute_status,
				        (SELECT d.resolution_action FROM {$d} d WHERE d.report_id = r.id ORDER BY d.id DESC LIMIT 1) AS dispute_action
				 FROM {$r} r LEFT JOIN {$p} p ON p.id = r.property_id
				 WHERE r.tenant_identity_id = %d AND r.status = %s AND r.screening_disclosure = 1{$filter}
				 ORDER BY r.incident_date DESC, r.id DESC",
				$identity_id,
				Constants::INCIDENT_STATUS_APPROVED
			)
		) ?: array();
		$out = array();
		foreach ( $rows as $row ) {
			self::decorate( $row );
			$out[] = array(
				'reference'      => $row->reference,
				'category'       => $row->category_label,
				'incident_date'  => mysql2date( 'M j, Y', $row->incident_date ),
				'amount'         => (float) $row->amount_involved,
				'location'       => trim( (string) $row->property_city . ', ' . (string) $row->property_state, ', ' ),
				'narrative'      => (string) $row->factual_description,
				'review_status'  => __( 'Reviewed and approved by the Fraud Tenant review team', 'fraud-tenant-core' ),
				'reported'       => mysql2date( 'M j, Y', $row->created_at ),
				'dispute'        => self::dispute_label( (string) $row->dispute_status, (string) $row->dispute_action ),
				'dispute_state'  => (string) ( $row->dispute_status ?: 'none' ),
				'own_report'     => (int) $row->owner_id === $viewer_id,
			);
		}
		return $out;
	}

	/**
	 * Public dispute outcome label (no statements or evidence).
	 *
	 * @param string $status Dispute status.
	 * @param string $action Resolution action.
	 * @return string
	 */
	public static function dispute_label( string $status, string $action ): string {
		switch ( $status ) {
			case 'pending':
			case 'under_review':
				return __( 'Disputed by the tenant — under review', 'fraud-tenant-core' );
			case 'resolved':
				return DisputeService::ACTION_CORRECTED === $action
					? __( 'Disputed — record corrected after review', 'fraud-tenant-core' )
					: __( 'Disputed — tenant\'s dispute upheld', 'fraud-tenant-core' );
			case 'rejected':
				return __( 'Disputed — report maintained after review', 'fraud-tenant-core' );
			default:
				return __( 'No dispute on file', 'fraud-tenant-core' );
		}
	}

	// ------------------------------------------------------------------ admin queue

	/**
	 * Reports with identity + disclosure state for the admin "Report Association & Disclosure" area.
	 *
	 * @param array $args state (all|unlinked|exception|linked|tenant_visible|screening_visible), page, per_page.
	 * @return array{items: array, total: int}
	 */
	public static function get_admin_queue( array $args = array() ): array {
		global $wpdb;
		$r     = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;
		$i     = $wpdb->prefix . Constants::TABLE_TENANT_IDENTITIES;
		$p     = $wpdb->prefix . Constants::TABLE_PROPERTIES;
		$state = sanitize_key( $args['state'] ?? 'all' );
		$map   = array(
			'unlinked'          => 'r.tenant_identity_id IS NULL AND (r.identity_match IS NULL OR r.identity_match <> \'exception\')',
			'exception'         => "r.identity_match = 'exception'",
			'linked'            => 'r.tenant_identity_id IS NOT NULL',
			'tenant_visible'    => "r.status = 'approved' AND r.disclosable = 1 AND r.tenant_identity_id IS NOT NULL",
			'screening_visible' => "r.status = 'approved' AND r.screening_disclosure = 1 AND r.tenant_identity_id IS NOT NULL",
		);
		$where = $map[ $state ] ?? '1=1';
		$per   = max( 1, min( 100, (int) ( $args['per_page'] ?? 25 ) ) );
		$page  = max( 1, (int) ( $args['page'] ?? 1 ) );
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$r} r WHERE {$where}" );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.id, r.owner_id, r.category, r.status, r.identity_match, r.tenant_identity_id, r.disclosable, r.screening_disclosure, r.created_at,
				        i.ssn_last_four, i.user_id AS tenant_user_id, p.city AS property_city, p.state AS property_state, u.display_name AS owner_name
				 FROM {$r} r LEFT JOIN {$i} i ON i.id = r.tenant_identity_id LEFT JOIN {$p} p ON p.id = r.property_id LEFT JOIN {$wpdb->users} u ON u.ID = r.owner_id
				 WHERE {$where} ORDER BY r.id DESC LIMIT %d OFFSET %d",
				$per,
				( $page - 1 ) * $per
			)
		) ?: array();
		foreach ( $rows as $row ) {
			self::decorate( $row );
		}
		return array( 'items' => $rows, 'total' => $total );
	}

	/**
	 * Counts per queue state.
	 *
	 * @return array<string,int>
	 */
	public static function get_status_counts(): array {
		global $wpdb;
		$r = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;
		return array(
			'exception'         => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$r} WHERE identity_match = 'exception'" ),
			'unlinked'          => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$r} WHERE tenant_identity_id IS NULL AND (identity_match IS NULL OR identity_match <> 'exception')" ),
			'linked'            => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$r} WHERE tenant_identity_id IS NOT NULL" ),
			'tenant_visible'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$r} WHERE status = 'approved' AND disclosable = 1 AND tenant_identity_id IS NOT NULL" ),
			'screening_visible' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$r} WHERE status = 'approved' AND screening_disclosure = 1 AND tenant_identity_id IS NOT NULL" ),
		);
	}

	/**
	 * Common display fields.
	 *
	 * @param object $row Report row.
	 */
	public static function decorate( object $row ): void {
		$row->id               = (int) $row->id;
		$row->reference        = sprintf( 'INC-%06d', $row->id );
		$row->category_label   = IncidentService::ALLOWED_CATEGORIES[ $row->category ] ?? ucwords( str_replace( '_', ' ', (string) $row->category ) );
		$row->amount_formatted = '$' . number_format( (float) ( $row->amount_involved ?? 0 ), 2 );
	}
}
