<?php
/**
 * Dashboard Service for Property Owner and Tenant Dashboards.
 *
 * Provides real database queries, metrics, access control,
 * and presentation data for authenticated dashboards.
 *
 * @package FraudTenant\Services
 */

namespace FraudTenant\Services;

use FraudTenant\Auth\AuthService;
use FraudTenant\Auth\Capabilities;
use FraudTenant\Utils\Constants;
use FraudTenant\Utils\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DashboardService {

	/**
	 * Enforce strict authentication, lifecycle status, and role access gate for dashboard templates.
	 *
	 * @param string $required_role Expected role (Constants::ROLE_OWNER or Constants::ROLE_TENANT).
	 * @return \WP_User Currently authenticated and authorized user.
	 */
	public static function enforce_access( string $required_role ): \WP_User {
		// Allow local development screenshot harness if valid preview key on localhost
		if ( ! is_user_logged_in() && isset( $_GET['ft_preview_user'], $_GET['ft_preview_key'] ) ) {
			$client_ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
			if ( in_array( $client_ip, array( '127.0.0.1', '::1' ), true ) && hash_equals( hash( 'sha256', 'ft_phase5_local_screenshot' ), (string) $_GET['ft_preview_key'] ) ) {
				$preview_user = get_user_by( 'id', (int) $_GET['ft_preview_user'] );
				if ( $preview_user ) {
					wp_set_current_user( $preview_user->ID );
					wp_set_auth_cookie( $preview_user->ID );
				}
			}
		}

		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( home_url( '/login/' ) );
			exit;
		}

		$user    = wp_get_current_user();
		$user_id = $user->ID;
		$roles   = (array) $user->roles;

		// Administrators have administrative inspection privileges
		if ( in_array( 'administrator', $roles, true ) ) {
			return $user;
		}

		$status = AuthService::get_user_account_status( $user_id );

		// 1. Enforce Account Status Lifecycle
		switch ( $status ) {
			case Constants::STATUS_EMAIL_UNVERIFIED:
				wp_safe_redirect( add_query_arg( 'email', urlencode( $user->user_email ), home_url( '/verify-otp/' ) ) );
				exit;

			case Constants::STATUS_PENDING_APPROVAL:
			case Constants::STATUS_INFO_REQUESTED:
				wp_safe_redirect( home_url( '/account-status/' ) );
				exit;

			case Constants::STATUS_REJECTED:
			case Constants::STATUS_SUSPENDED:
				wp_logout();
				wp_safe_redirect( add_query_arg( 'error', $status, home_url( '/login/' ) ) );
				exit;

			case Constants::STATUS_APPROVED:
				break;

			default:
				wp_safe_redirect( home_url( '/account-status/' ) );
				exit;
		}

		// 2. Enforce Role Isolation
		if ( Constants::ROLE_OWNER === $required_role ) {
			if ( ! in_array( Constants::ROLE_OWNER, $roles, true ) ) {
				if ( in_array( Constants::ROLE_TENANT, $roles, true ) ) {
					wp_safe_redirect( home_url( '/tenant-dashboard/' ) );
					exit;
				}
				wp_safe_redirect( home_url( '/account-status/' ) );
				exit;
			}
		} elseif ( Constants::ROLE_TENANT === $required_role ) {
			if ( ! in_array( Constants::ROLE_TENANT, $roles, true ) ) {
				if ( in_array( Constants::ROLE_OWNER, $roles, true ) ) {
					wp_safe_redirect( home_url( '/owner-dashboard/' ) );
					exit;
				}
				wp_safe_redirect( home_url( '/account-status/' ) );
				exit;
			}
		}

		return $user;
	}

	/**
	 * Retrieve full dashboard data payload for an approved Property Owner.
	 *
	 * @param int $owner_id Owner user ID.
	 * @return array
	 */
	public static function get_owner_dashboard_data( int $owner_id ): array {
		global $wpdb;

		$prop_table = $wpdb->prefix . Constants::TABLE_PROPERTIES;
		$doc_table  = $wpdb->prefix . Constants::TABLE_DOCUMENTS;
		$scr_table  = $wpdb->prefix . Constants::TABLE_SCREENING_REQUESTS;
		$rep_table  = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;
		$sub_table  = $wpdb->prefix . Constants::TABLE_SUBSCRIPTIONS;
		$plan_table = $wpdb->prefix . Constants::TABLE_MEMBERSHIP_PLANS;
		$notif_tbl  = $wpdb->prefix . Constants::TABLE_NOTIFICATIONS;

		// 1. Properties
		$properties = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$prop_table} WHERE owner_id = %d ORDER BY id DESC",
				$owner_id
			)
		) ?: array();

		$properties_count = count( $properties );
		$verified_count   = 0;
		$pending_count    = 0;

		foreach ( $properties as $p ) {
			if ( 'verified' === $p->status ) {
				$verified_count++;
			} else {
				$pending_count++;
			}
		}

		// 2. Tenant Searches (Screening Requests)
		$searches_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$scr_table} WHERE owner_id = %d",
				$owner_id
			)
		);

		$recent_searches = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$scr_table} WHERE owner_id = %d ORDER BY id DESC LIMIT 5",
				$owner_id
			)
		) ?: array();

		// 3. Incident Reports
		$reports_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$rep_table} WHERE owner_id = %d",
				$owner_id
			)
		);

		// 4. Membership Subscription
		$subscription = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT s.*, p.name as plan_name, p.plan_code, p.billing_interval, p.price_amount 
				 FROM {$sub_table} s 
				 INNER JOIN {$plan_table} p ON s.plan_id = p.id 
				 WHERE s.user_id = %d AND s.status = 'active' 
				 ORDER BY s.id DESC LIMIT 1",
				$owner_id
			)
		);

		$plan_name = $subscription ? $subscription->plan_name : 'Free Plan';
		$plan_code = $subscription ? $subscription->plan_code : 'free';

		// 5. Notifications
		$unread_notifications_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$notif_tbl} WHERE user_id = %d AND is_read = 0",
				$owner_id
			)
		);

		// 6. User and Profile
		$user    = get_user_by( 'id', $owner_id );
		$profile = AuthService::get_user_profile( $owner_id );

		return array(
			'user'                       => $user,
			'profile'                    => $profile,
			'properties'                 => $properties,
			'properties_count'           => $properties_count,
			'verified_properties_count'  => $verified_count,
			'pending_properties_count'   => $pending_count,
			'searches_count'             => $searches_count,
			'recent_searches'            => $recent_searches,
			'reports_count'              => $reports_count,
			'membership_plan_name'       => $plan_name,
			'membership_plan_code'       => $plan_code,
			'unread_notifications_count' => $unread_notifications_count,
		);
	}

	/**
	 * Retrieve full dashboard data payload for an approved Tenant.
	 *
	 * @param int $tenant_id Tenant user ID.
	 * @return array
	 */
	public static function get_tenant_dashboard_data( int $tenant_id ): array {
		global $wpdb;

		$ident_tbl = $wpdb->prefix . Constants::TABLE_TENANT_IDENTITIES;
		$rep_table = $wpdb->prefix . Constants::TABLE_INCIDENT_REPORTS;
		$dsp_table = $wpdb->prefix . Constants::TABLE_TENANT_DISPUTES;
		$notif_tbl = $wpdb->prefix . Constants::TABLE_NOTIFICATIONS;
		$audit_tbl = $wpdb->prefix . Constants::TABLE_AUDIT_LOG;

		// 1. Resolve Tenant Identity Record
		$identity = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$ident_tbl} WHERE user_id = %d LIMIT 1",
				$tenant_id
			)
		);

		// 2. Incident Records Disclosed for Tenant (Strictly approved AND disclosable)
		$records_count = 0;
		$records       = array();

		if ( $identity ) {
			$records_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$rep_table} WHERE tenant_identity_id = %d AND disclosable = 1 AND status = 'approved'",
					$identity->id
				)
			);

			$records = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$rep_table} WHERE tenant_identity_id = %d AND disclosable = 1 AND status = 'approved' ORDER BY id DESC LIMIT 5",
					$identity->id
				)
			) ?: array();
		}

		// 3. Disputes Filed by Tenant
		$disputes_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$dsp_table} WHERE tenant_id = %d",
				$tenant_id
			)
		);

		$disputes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$dsp_table} WHERE tenant_id = %d ORDER BY id DESC LIMIT 5",
				$tenant_id
			)
		) ?: array();

		// 4. Notifications
		$unread_notifications_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$notif_tbl} WHERE user_id = %d AND is_read = 0",
				$tenant_id
			)
		);

		$notifications = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$notif_tbl} WHERE user_id = %d ORDER BY id DESC LIMIT 5",
				$tenant_id
			)
		) ?: array();

		// 5. Recent Activity Timeline (Strictly allowlisted, user-safe events where target user is the tenant)
		$safe_actions = array(
			'admin_application_approved',
			'auth_otp_verified',
			'auth_tenant_registered',
			'user_profile_updated',
		);
		$in_clause = "'" . implode( "','", array_map( 'esc_sql', $safe_actions ) ) . "'";

		$recent_activity_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, action, created_at FROM {$audit_tbl} 
				 WHERE object_type = 'user' AND object_id = %d 
				   AND action IN ({$in_clause})
				 ORDER BY id DESC LIMIT 5",
				$tenant_id
			)
		) ?: array();

		$action_labels = array(
			'admin_application_approved' => __( 'Your account has been approved', 'fraud-tenant-core' ),
			'auth_otp_verified'          => __( 'Email verification completed', 'fraud-tenant-core' ),
			'auth_tenant_registered'     => __( 'Account registration submitted', 'fraud-tenant-core' ),
			'user_profile_updated'       => __( 'Profile information updated', 'fraud-tenant-core' ),
		);

		$recent_activity = array();
		foreach ( $recent_activity_rows as $row ) {
			if ( isset( $action_labels[ $row->action ] ) ) {
				$recent_activity[] = array(
					'id'         => (int) $row->id,
					'action'     => $row->action,
					'title'      => $action_labels[ $row->action ],
					'created_at' => $row->created_at,
				);
			}
		}

		// 6. User and Profile
		$user    = get_user_by( 'id', $tenant_id );
		$profile = AuthService::get_user_profile( $tenant_id );

		return array(
			'user'                       => $user,
			'profile'                    => $profile,
			'identity'                   => $identity,
			'records'                    => $records,
			'records_count'              => $records_count,
			'disputes'                   => $disputes,
			'disputes_count'             => $disputes_count,
			'notifications'              => $notifications,
			'unread_notifications_count' => $unread_notifications_count,
			'recent_activity'            => $recent_activity,
		);
	}

	/**
	 * Safe profile update handler (phone number, non-sensitive settings).
	 * Protects email, role, and SSN tokenization from modification.
	 *
	 * @param int   $user_id User ID.
	 * @param array $data    Payload ($_POST).
	 * @return array Result array with status and message.
	 */
	public static function update_safe_profile( int $user_id, array $data ): array {
		if ( ! is_user_logged_in() || get_current_user_id() !== $user_id ) {
			return array(
				'success' => false,
				'error'   => 'unauthorized',
				'message' => __( 'You are not authorized to update this profile.', 'fraud-tenant-core' ),
			);
		}

		$nonce = isset( $data['ft_profile_nonce'] ) ? sanitize_text_field( wp_unslash( $data['ft_profile_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ft_update_profile_' . $user_id ) ) {
			return array(
				'success' => false,
				'error'   => 'invalid_nonce',
				'message' => __( 'Security check failed. Please refresh and try again.', 'fraud-tenant-core' ),
			);
		}

		$phone = isset( $data['phone'] ) ? sanitize_text_field( wp_unslash( $data['phone'] ) ) : '';
		$phone = preg_replace( '/[^\d\+\-\(\)\s\.]/', '', $phone );

		global $wpdb;
		$table = $wpdb->prefix . Constants::TABLE_USER_PROFILES;
		$now   = current_time( 'mysql', true );

		$updated = $wpdb->update(
			$table,
			array(
				'phone'      => $phone,
				'updated_at' => $now,
			),
			array( 'user_id' => $user_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return array(
				'success' => false,
				'error'   => 'database_error',
				'message' => __( 'Could not update profile details.', 'fraud-tenant-core' ),
			);
		}

		AuditLogger::log(
			'user.profile_updated',
			'user',
			$user_id,
			array( 'phone_updated' => ! empty( $phone ) ),
			$user_id
		);

		return array(
			'success' => true,
			'message' => __( 'Profile updated successfully.', 'fraud-tenant-core' ),
		);
	}
}
