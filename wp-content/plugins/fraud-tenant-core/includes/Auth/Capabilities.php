<?php
/**
 * Granular Capabilities Registry.
 *
 * @package FraudTenant\Auth
 */

namespace FraudTenant\Auth;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Capabilities {

	// Owner Capabilities
	public const ACCESS_OWNER_DASHBOARD   = 'ft_access_owner_dashboard';
	public const MANAGE_PROPERTIES        = 'ft_manage_properties';
	public const VIEW_PROPERTIES          = 'ft_view_properties';
	public const CREATE_PROPERTIES        = 'ft_create_properties';
	public const EDIT_PROPERTIES          = 'ft_edit_properties';
	public const DELETE_PROPERTIES        = 'ft_delete_properties';
	public const UPLOAD_PROPERTY_DOCS     = 'ft_upload_property_docs';
	public const REQUEST_TENANT_SCREENING = 'ft_request_tenant_screening';
	public const VIEW_OWN_SCREENINGS      = 'ft_view_own_screenings';
	public const SUBMIT_INCIDENT_REPORT   = 'ft_submit_incident_report';
	public const VIEW_OWN_REPORTS         = 'ft_view_own_reports';
	public const MANAGE_MEMBERSHIP        = 'ft_manage_membership';
	public const VIEW_NOTIFICATIONS       = 'ft_view_notifications';

	// Tenant Capabilities
	public const ACCESS_TENANT_DASHBOARD  = 'ft_access_tenant_dashboard';
	public const VIEW_OWN_RENTAL_RECORDS  = 'ft_view_own_rental_records';
	public const VIEW_OWN_REPORT_DETAILS  = 'ft_view_own_report_details';
	public const SUBMIT_TENANT_DISPUTE    = 'ft_submit_tenant_dispute';
	public const VIEW_OWN_DISPUTES        = 'ft_view_own_disputes';
	public const UPLOAD_DISPUTE_EVIDENCE  = 'ft_upload_dispute_evidence';

	// Administrative Capabilities
	public const ACCESS_ADMIN_PORTAL        = 'ft_access_admin_portal';
	public const REVIEW_USER_REGISTRATIONS  = 'ft_review_user_registrations';
	public const APPROVE_USER_REGISTRATIONS = 'ft_approve_user_registrations';
	public const REJECT_USER_REGISTRATIONS  = 'ft_reject_user_registrations';
	public const REVIEW_PROPERTIES          = 'ft_review_properties';
	public const VERIFY_PROPERTIES          = 'ft_verify_properties';
	public const SUSPEND_USERS              = 'ft_suspend_users';
	public const REVIEW_INCIDENT_REPORTS    = 'ft_review_incident_reports';
	public const APPROVE_INCIDENT_REPORTS   = 'ft_approve_incident_reports';
	public const REJECT_INCIDENT_REPORTS    = 'ft_reject_incident_reports';
	public const REVIEW_TENANT_DISPUTES     = 'ft_review_tenant_disputes';
	public const RESOLVE_TENANT_DISPUTES    = 'ft_resolve_tenant_disputes';
	public const MANAGE_MEMBERSHIP_PLANS    = 'ft_manage_membership_plans';
	public const VIEW_ALL_SCREENING_LOGS    = 'ft_view_all_screening_logs';
	public const VIEW_AUDIT_LOGS            = 'ft_view_audit_logs';
	public const MANAGE_PLATFORM_SETTINGS   = 'ft_manage_platform_settings';

	/**
	 * Get all capabilities for Property Owners.
	 *
	 * @return array<string, bool>
	 */
	public static function get_owner_caps(): array {
		return array(
			'read'                        => true,
			self::ACCESS_OWNER_DASHBOARD   => true,
			self::MANAGE_PROPERTIES        => true,
			self::VIEW_PROPERTIES          => true,
			self::CREATE_PROPERTIES        => true,
			self::EDIT_PROPERTIES          => true,
			self::DELETE_PROPERTIES        => true,
			self::UPLOAD_PROPERTY_DOCS     => true,
			self::REQUEST_TENANT_SCREENING => true,
			self::VIEW_OWN_SCREENINGS      => true,
			self::SUBMIT_INCIDENT_REPORT   => true,
			self::VIEW_OWN_REPORTS         => true,
			self::MANAGE_MEMBERSHIP        => true,
			self::VIEW_NOTIFICATIONS       => true,
		);
	}

	/**
	 * Get all capabilities for Tenants.
	 *
	 * @return array<string, bool>
	 */
	public static function get_tenant_caps(): array {
		return array(
			'read'                       => true,
			self::ACCESS_TENANT_DASHBOARD => true,
			self::VIEW_OWN_RENTAL_RECORDS => true,
			self::VIEW_OWN_REPORT_DETAILS => true,
			self::SUBMIT_TENANT_DISPUTE   => true,
			self::VIEW_OWN_DISPUTES       => true,
			self::UPLOAD_DISPUTE_EVIDENCE => true,
			self::VIEW_NOTIFICATIONS      => true,
		);
	}

	/**
	 * Get all custom capabilities that should be granted to administrators.
	 *
	 * @return array<string>
	 */
	public static function get_admin_caps(): array {
		return array(
			self::ACCESS_ADMIN_PORTAL,
			self::REVIEW_USER_REGISTRATIONS,
			self::APPROVE_USER_REGISTRATIONS,
			self::REJECT_USER_REGISTRATIONS,
			self::REVIEW_PROPERTIES,
			self::VERIFY_PROPERTIES,
			self::SUSPEND_USERS,
			self::REVIEW_INCIDENT_REPORTS,
			self::APPROVE_INCIDENT_REPORTS,
			self::REJECT_INCIDENT_REPORTS,
			self::REVIEW_TENANT_DISPUTES,
			self::RESOLVE_TENANT_DISPUTES,
			self::MANAGE_MEMBERSHIP_PLANS,
			self::VIEW_ALL_SCREENING_LOGS,
			self::VIEW_AUDIT_LOGS,
			self::MANAGE_PLATFORM_SETTINGS,
			self::VIEW_PROPERTIES,
			self::VIEW_OWN_REPORTS,
			self::VIEW_OWN_DISPUTES,
			self::VIEW_NOTIFICATIONS,
		);
	}
}

