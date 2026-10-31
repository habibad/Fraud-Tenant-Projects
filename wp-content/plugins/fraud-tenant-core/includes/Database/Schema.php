<?php
/**
 * Database Schema Definitions.
 *
 * @package FraudTenant\Database
 */

namespace FraudTenant\Database;

use FraudTenant\Utils\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Schema {

	/**
	 * Get the SQL statements for all custom tables formatted for dbDelta.
	 *
	 * @return array<string> Array of CREATE TABLE statements.
	 */
	public static function get_schema_statements(): array {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$prefix          = $wpdb->prefix;

		return array(
			// 1. User Profiles & Review Metadata
			"CREATE TABLE {$prefix}" . Constants::TABLE_USER_PROFILES . " (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
user_id bigint(20) unsigned NOT NULL,
account_type varchar(30) NOT NULL DEFAULT 'owner',
approval_status varchar(30) NOT NULL DEFAULT 'pending_approval',
phone varchar(30) DEFAULT NULL,
business_name varchar(200) DEFAULT NULL,
business_reg_number varchar(100) DEFAULT NULL,
identity_verified tinyint(1) NOT NULL DEFAULT 0,
address_line1 varchar(255) DEFAULT NULL,
address_line2 varchar(255) DEFAULT NULL,
city varchar(100) DEFAULT NULL,
state varchar(50) DEFAULT NULL,
postal_code varchar(20) DEFAULT NULL,
prev_address_line1 varchar(255) DEFAULT NULL,
prev_city varchar(100) DEFAULT NULL,
prev_state varchar(50) DEFAULT NULL,
prev_postal_code varchar(20) DEFAULT NULL,
terms_accepted_at datetime DEFAULT NULL,
privacy_accepted_at datetime DEFAULT NULL,
rejection_reason text DEFAULT NULL,
admin_notes text DEFAULT NULL,
reviewed_by bigint(20) unsigned DEFAULT NULL,
reviewed_at datetime DEFAULT NULL,
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY user_id (user_id),
KEY approval_status (approval_status),
KEY account_type (account_type)
) {$charset_collate};",

			// 2. Email OTP Verifications
			"CREATE TABLE {$prefix}" . Constants::TABLE_EMAIL_OTPS . " (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
email varchar(191) NOT NULL,
user_id bigint(20) unsigned DEFAULT NULL,
otp_hash varchar(255) NOT NULL,
purpose varchar(50) NOT NULL DEFAULT 'registration',
attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
max_attempts tinyint(3) unsigned NOT NULL DEFAULT 5,
resend_available_at datetime NOT NULL,
expires_at datetime NOT NULL,
consumed_at datetime DEFAULT NULL,
ip_address varchar(45) DEFAULT NULL,
created_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY email (email),
KEY user_id (user_id),
KEY purpose (purpose),
KEY expires_at (expires_at)
) {$charset_collate};",

			// 3. Properties
			"CREATE TABLE {$prefix}" . Constants::TABLE_PROPERTIES . " (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
owner_id bigint(20) unsigned NOT NULL,
title varchar(255) NOT NULL,
address_street varchar(255) NOT NULL,
unit_number varchar(50) DEFAULT NULL,
city varchar(100) NOT NULL,
state varchar(50) NOT NULL,
postal_code varchar(20) NOT NULL,
property_type varchar(50) NOT NULL DEFAULT 'single_family',
units_count int(10) unsigned NOT NULL DEFAULT 1,
status varchar(30) NOT NULL DEFAULT 'pending_verification',
admin_notes text DEFAULT NULL,
rejection_reason text DEFAULT NULL,
reviewed_by bigint(20) unsigned DEFAULT NULL,
reviewed_at datetime DEFAULT NULL,
archived_at datetime DEFAULT NULL,
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY owner_id (owner_id),
KEY status (status),
KEY postal_code (postal_code)
) {$charset_collate};",

			// 4. Protected Documents
			"CREATE TABLE {$prefix}" . Constants::TABLE_DOCUMENTS . " (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
user_id bigint(20) unsigned NOT NULL,
object_type varchar(50) NOT NULL,
object_id bigint(20) unsigned DEFAULT NULL,
file_path varchar(500) NOT NULL,
original_name varchar(255) NOT NULL,
file_size bigint(20) unsigned NOT NULL,
mime_type varchar(100) NOT NULL,
access_permission varchar(50) NOT NULL DEFAULT 'private',
review_status varchar(30) NOT NULL DEFAULT 'pending',
admin_notes text DEFAULT NULL,
uploaded_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY user_id (user_id),
KEY object_type_id (object_type, object_id)
) {$charset_collate};",

			// 5. Tenant Identities (Keyed SSN Tokenization)
			"CREATE TABLE {$prefix}" . Constants::TABLE_TENANT_IDENTITIES . " (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
user_id bigint(20) unsigned DEFAULT NULL,
token_hash varchar(64) NOT NULL,
ssn_last_four varchar(4) NOT NULL,
first_name varchar(100) NOT NULL,
last_name varchar(100) NOT NULL,
dob date DEFAULT NULL,
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY token_hash (token_hash),
KEY user_id (user_id),
KEY ssn_last_four (ssn_last_four),
KEY last_name (last_name)
) {$charset_collate};",

			// 6. Screening Requests
			"CREATE TABLE {$prefix}" . Constants::TABLE_SCREENING_REQUESTS . " (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
owner_id bigint(20) unsigned NOT NULL,
tenant_identity_id bigint(20) unsigned DEFAULT NULL,
property_id bigint(20) unsigned DEFAULT NULL,
permissible_purpose_attestation tinyint(1) NOT NULL DEFAULT 0,
purpose_reason varchar(255) NOT NULL DEFAULT 'rental_application',
ssn_last_four varchar(4) NOT NULL,
match_found tinyint(1) NOT NULL DEFAULT 0,
records_count int(10) unsigned NOT NULL DEFAULT 0,
disclosure_level varchar(30) NOT NULL DEFAULT 'locked',
search_date datetime NOT NULL,
ip_address varchar(45) DEFAULT NULL,
PRIMARY KEY  (id),
KEY owner_id (owner_id),
KEY tenant_identity_id (tenant_identity_id),
KEY property_id (property_id),
KEY search_date (search_date)
) {$charset_collate};",

			// 7. Incident Reports
			"CREATE TABLE {$prefix}" . Constants::TABLE_INCIDENT_REPORTS . " (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
owner_id bigint(20) unsigned NOT NULL,
tenant_identity_id bigint(20) unsigned NOT NULL,
property_id bigint(20) unsigned DEFAULT NULL,
category varchar(50) NOT NULL,
incident_date date NOT NULL,
amount_involved decimal(10,2) DEFAULT 0.00,
factual_description text NOT NULL,
owner_attestation tinyint(1) NOT NULL DEFAULT 0,
status varchar(30) NOT NULL DEFAULT 'submitted',
disclosable tinyint(1) NOT NULL DEFAULT 0,
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY owner_id (owner_id),
KEY tenant_identity_id (tenant_identity_id),
KEY property_id (property_id),
KEY status (status),
KEY disclosable (disclosable)
) {$charset_collate};",

			// 8. Report Reviews
			"CREATE TABLE {$prefix}" . Constants::TABLE_REPORT_REVIEWS . " (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
report_id bigint(20) unsigned NOT NULL,
reviewer_id bigint(20) unsigned NOT NULL,
decision varchar(30) NOT NULL,
internal_notes text DEFAULT NULL,
feedback_to_owner text DEFAULT NULL,
created_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY report_id (report_id),
KEY reviewer_id (reviewer_id)
) {$charset_collate};",

			// 9. Tenant Disputes
			"CREATE TABLE {$prefix}" . Constants::TABLE_TENANT_DISPUTES . " (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
report_id bigint(20) unsigned NOT NULL,
tenant_id bigint(20) unsigned NOT NULL,
dispute_reason varchar(100) NOT NULL,
statement text NOT NULL,
status varchar(30) NOT NULL DEFAULT 'pending',
resolution_notes text DEFAULT NULL,
resolved_by bigint(20) unsigned DEFAULT NULL,
resolved_at datetime DEFAULT NULL,
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY report_id (report_id),
KEY tenant_id (tenant_id),
KEY status (status)
) {$charset_collate};",

			// 10. Membership Plans
			"CREATE TABLE {$prefix}" . Constants::TABLE_MEMBERSHIP_PLANS . " (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
plan_code varchar(50) NOT NULL,
name varchar(100) NOT NULL,
description text DEFAULT NULL,
billing_interval varchar(20) NOT NULL DEFAULT 'monthly',
price_amount decimal(10,2) NOT NULL DEFAULT 0.00,
currency varchar(3) NOT NULL DEFAULT 'USD',
stripe_price_id varchar(100) DEFAULT NULL,
stripe_product_id varchar(100) DEFAULT NULL,
features_json text DEFAULT NULL,
is_active tinyint(1) NOT NULL DEFAULT 1,
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY plan_code (plan_code),
KEY is_active (is_active)
) {$charset_collate};",

			// 11. Subscriptions
			"CREATE TABLE {$prefix}" . Constants::TABLE_SUBSCRIPTIONS . " (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
user_id bigint(20) unsigned NOT NULL,
plan_id bigint(20) unsigned NOT NULL,
stripe_customer_id varchar(100) DEFAULT NULL,
stripe_subscription_id varchar(100) DEFAULT NULL,
status varchar(30) NOT NULL DEFAULT 'active',
current_period_start datetime DEFAULT NULL,
current_period_end datetime DEFAULT NULL,
cancel_at_period_end tinyint(1) NOT NULL DEFAULT 0,
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY user_id (user_id),
KEY plan_id (plan_id),
KEY stripe_subscription_id (stripe_subscription_id),
KEY status (status)
) {$charset_collate};",

			// 12. Payment & Webhook Events
			"CREATE TABLE {$prefix}" . Constants::TABLE_PAYMENT_EVENTS . " (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
event_id varchar(100) NOT NULL,
event_type varchar(100) NOT NULL,
user_id bigint(20) unsigned DEFAULT NULL,
payload_json longtext DEFAULT NULL,
status varchar(30) NOT NULL DEFAULT 'processed',
error_message text DEFAULT NULL,
processed_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY event_id (event_id),
KEY event_type (event_type),
KEY user_id (user_id),
KEY status (status)
) {$charset_collate};",

			// 13. In-App Notifications
			"CREATE TABLE {$prefix}" . Constants::TABLE_NOTIFICATIONS . " (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
user_id bigint(20) unsigned NOT NULL,
title varchar(255) NOT NULL,
message text NOT NULL,
action_url varchar(500) DEFAULT NULL,
type varchar(50) NOT NULL DEFAULT 'info',
is_read tinyint(1) NOT NULL DEFAULT 0,
read_at datetime DEFAULT NULL,
created_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY user_id (user_id),
KEY is_read (is_read),
KEY created_at (created_at)
) {$charset_collate};",

			// 14. Audit Log Trail
			"CREATE TABLE {$prefix}" . Constants::TABLE_AUDIT_LOG . " (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
user_id bigint(20) unsigned DEFAULT NULL,
action varchar(100) NOT NULL,
object_type varchar(50) DEFAULT NULL,
object_id bigint(20) unsigned DEFAULT NULL,
ip_address varchar(45) DEFAULT NULL,
user_agent varchar(255) DEFAULT NULL,
details_json longtext DEFAULT NULL,
created_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY user_id (user_id),
KEY action (action),
KEY object_type_id (object_type, object_id),
KEY created_at (created_at)
) {$charset_collate};"
		);
	}

	/**
	 * Get array of all custom table names with dynamic prefix.
	 *
	 * @return array<string>
	 */
	public static function get_all_table_names(): array {
		global $wpdb;
		$prefix = $wpdb->prefix;

		return array(
			$prefix . Constants::TABLE_USER_PROFILES,
			$prefix . Constants::TABLE_EMAIL_OTPS,
			$prefix . Constants::TABLE_PROPERTIES,
			$prefix . Constants::TABLE_DOCUMENTS,
			$prefix . Constants::TABLE_TENANT_IDENTITIES,
			$prefix . Constants::TABLE_SCREENING_REQUESTS,
			$prefix . Constants::TABLE_INCIDENT_REPORTS,
			$prefix . Constants::TABLE_REPORT_REVIEWS,
			$prefix . Constants::TABLE_TENANT_DISPUTES,
			$prefix . Constants::TABLE_MEMBERSHIP_PLANS,
			$prefix . Constants::TABLE_SUBSCRIPTIONS,
			$prefix . Constants::TABLE_PAYMENT_EVENTS,
			$prefix . Constants::TABLE_NOTIFICATIONS,
			$prefix . Constants::TABLE_AUDIT_LOG,
		);
	}
}

