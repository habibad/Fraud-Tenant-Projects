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
	public const DB_VERSION = '1.8.0';

	/**
	 * Option key storing database schema version.
	 */
	public const DB_VERSION_OPTION = 'fraud_tenant_db_version';

	/**
	 * Option keys storing encrypted Stripe API credentials and active mode.
	 */
	public const OPTION_STRIPE_CREDENTIALS      = 'ft_stripe_credentials'; // Legacy single-mode
	public const OPTION_STRIPE_CREDENTIALS_TEST = 'ft_stripe_credentials_test';
	public const OPTION_STRIPE_CREDENTIALS_LIVE = 'ft_stripe_credentials_live';
	public const OPTION_STRIPE_ACTIVE_MODE      = 'ft_stripe_active_mode';

	/**
	 * Stripe operational modes.
	 */
	public const STRIPE_MODE_TEST = 'test';
	public const STRIPE_MODE_LIVE = 'live';

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
	public const DOC_STATUS_PENDING    = 'pending';
	public const DOC_STATUS_APPROVED   = 'approved';
	public const DOC_STATUS_REJECTED   = 'rejected';
	public const DOC_STATUS_SUPERSEDED = 'superseded';

	/**
	 * Incident Report Statuses.
	 */
	public const INCIDENT_STATUS_SUBMITTED           = 'submitted';
	public const INCIDENT_STATUS_UNDER_REVIEW        = 'under_review';
	public const INCIDENT_STATUS_APPROVED            = 'approved';
	public const INCIDENT_STATUS_REJECTED            = 'rejected';
	public const INCIDENT_STATUS_NEEDS_CLARIFICATION = 'needs_clarification';

	/**
	 * Incident Report Categories.
	 */
	public const INCIDENT_CAT_UNPAID_RENT      = 'unpaid_rent';
	public const INCIDENT_CAT_PROPERTY_DAMAGE  = 'property_damage';
	public const INCIDENT_CAT_LATE_RENT        = 'late_rent';
	public const INCIDENT_CAT_LEASE_VIOLATION  = 'lease_violation';
	public const INCIDENT_CAT_UNAUTHORIZED_PET = 'unauthorized_pet';
	public const INCIDENT_CAT_NOISE_COMPLAINT  = 'noise_complaint';
	public const INCIDENT_CAT_OTHER            = 'other_violation';

	/**
	 * Tenant Dispute Statuses.
	 */
	public const DISPUTE_STATUS_PENDING      = 'pending';
	public const DISPUTE_STATUS_UNDER_REVIEW = 'under_review';
	public const DISPUTE_STATUS_RESOLVED     = 'resolved';
	public const DISPUTE_STATUS_REJECTED     = 'rejected';

	/**
	 * Tenant Dispute Reasons.
	 */
	public const DISPUTE_REASON_INACCURATE       = 'inaccurate_record';
	public const DISPUTE_REASON_INCORRECT_AMOUNT = 'incorrect_amount';
	public const DISPUTE_REASON_IDENTITY_MISTAKE = 'identity_mistake';
	public const DISPUTE_REASON_ALREADY_PAID     = 'already_paid';
	public const DISPUTE_REASON_UNAUTHORIZED     = 'unauthorized_report';
	public const DISPUTE_REASON_OTHER            = 'other';

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
	public const TABLE_REPORT_ASSOCIATIONS = 'ft_report_associations';
	public const TABLE_REPORT_DISCLOSURE_HISTORY = 'ft_report_disclosure_history';
	public const TABLE_CHECKOUT_LOCKS = 'ft_checkout_locks';
}

