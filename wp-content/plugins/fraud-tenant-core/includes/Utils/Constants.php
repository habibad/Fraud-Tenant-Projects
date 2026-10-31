<?php
/**
 * System Constants for Fraud Tenant.
 *
 * @package FraudTenant\Utils
 */

namespace FraudTenant\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Constants {

	/**
	 * Plugin version.
	 */
	public const VERSION = '1.0.0';

	/**
	 * Database schema version.
	 */
	public const DB_VERSION = '1.3.0';

	/**
	 * Option key storing database schema version.
	 */
	public const DB_VERSION_OPTION = 'fraud_tenant_db_version';

	/**
	 * REST API Namespace.
	 */
	public const REST_NAMESPACE = 'fraud-tenant/v1';

	/**
	 * User Roles.
	 */
	public const ROLE_OWNER  = 'ft_owner';
	public const ROLE_TENANT = 'ft_tenant';

	/**
	 * Account Statuses.
	 */
	public const STATUS_EMAIL_UNVERIFIED = 'email_unverified';
	public const STATUS_PENDING_APPROVAL = 'pending_approval';
	public const STATUS_INFO_REQUESTED   = 'info_requested';
	public const STATUS_APPROVED         = 'approved';
	public const STATUS_REJECTED         = 'rejected';
	public const STATUS_SUSPENDED        = 'suspended';

	/**
	 * Property Verification Statuses.
	 */
	public const PROP_STATUS_PENDING        = 'pending_verification';
	public const PROP_STATUS_VERIFIED       = 'verified';
	public const PROP_STATUS_REJECTED       = 'rejected';
	public const PROP_STATUS_INFO_REQUESTED = 'info_requested';
	public const PROP_STATUS_ARCHIVED       = 'archived';

	/**
	 * Document Review Statuses.
	 */
	public const DOC_STATUS_PENDING  = 'pending';
	public const DOC_STATUS_APPROVED = 'approved';
	public const DOC_STATUS_REJECTED = 'rejected';

	/**
	 * Database Table Names (without prefix).
	 */
	public const TABLE_USER_PROFILES      = 'ft_user_profiles';
	public const TABLE_EMAIL_OTPS         = 'ft_email_otps';
	public const TABLE_PROPERTIES         = 'ft_properties';
	public const TABLE_DOCUMENTS          = 'ft_documents';
	public const TABLE_TENANT_IDENTITIES  = 'ft_tenant_identities';
	public const TABLE_SCREENING_REQUESTS = 'ft_screening_requests';
	public const TABLE_INCIDENT_REPORTS   = 'ft_incident_reports';
	public const TABLE_REPORT_REVIEWS     = 'ft_report_reviews';
	public const TABLE_TENANT_DISPUTES    = 'ft_tenant_disputes';
	public const TABLE_MEMBERSHIP_PLANS   = 'ft_membership_plans';
	public const TABLE_SUBSCRIPTIONS      = 'ft_subscriptions';
	public const TABLE_PAYMENT_EVENTS     = 'ft_payment_events';
	public const TABLE_NOTIFICATIONS      = 'ft_notifications';
	public const TABLE_AUDIT_LOG          = 'ft_audit_log';
}

