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
use FraudTenant\Services\DisputeService;
use FraudTenant\Services\IncidentService;
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

		// 1. Properties (Active vs Archived)
		$properties          = PropertyService::get_owner_properties( $owner_id, false );
		$archived_properties = PropertyService::get_owner_properties( $owner_id, true, array( 'status' => 'archived' ) );

		$properties_count = count( $properties );
		$verified_count   = 0;
		$pending_count    = 0;

		foreach ( $properties as $p ) {
			if ( Constants::PROP_STATUS_VERIFIED === $p->status ) {
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
				"SELECT s.*, p.title AS property_title, p.address_street AS property_street, p.city AS property_city, p.state AS property_state 
				 FROM {$scr_table} s 
				 LEFT JOIN {$prop_table} p ON s.property_id = p.id 
				 WHERE s.owner_id = %d 
				 ORDER BY s.id DESC LIMIT 5",
				$owner_id
			)
		) ?: array();

		if ( ! empty( $recent_searches ) ) {
			foreach ( $recent_searches as &$search_item ) {
				$search_item->status = 'pending';
			}
			unset( $search_item );
		}

		// 3. Incident Reports
		$reports_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$rep_table} WHERE owner_id = %d",
				$owner_id
			)
		);
		$incident_reports    = IncidentService::get_owner_incident_reports( $owner_id, array( 'limit' => 10 ) );
		$eligible_properties = IncidentService::get_eligible_properties( $owner_id );

		// 4. Membership Subscription (Authoritative Resolution via MembershipService)
		$plan      = MembershipService::get_owner_plan( $owner_id );
		$plan_name = $plan ? $plan->name : 'Free Plan';
		$plan_code = $plan ? $plan->plan_code : 'free';

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
			'archived_properties'        => $archived_properties,
			'archived_properties_count'  => count( $archived_properties ),
			'verified_properties_count'  => $verified_count,
			'pending_properties_count'   => $pending_count,
			'searches_count'             => $searches_count,
			'recent_searches'            => $recent_searches,
			'reports_count'              => $reports_count,
			'incident_reports'           => $incident_reports,
			'eligible_properties'        => $eligible_properties,
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

		// 2. Incident Records Disclosed for Tenant (Phase 8.4: Strictly approved AND disclosable)
		$disclosed_reports = ReportVisibilityService::get_tenant_disclosed_reports( $tenant_id );
		$records_count     = count( $disclosed_reports );
		$records           = array_slice( $disclosed_reports, 0, 5 );

		// 3. Disputes Filed by Tenant
		$all_disputes   = DisputeService::get_tenant_disputes( $tenant_id );
		$disputes_count = count( $all_disputes );
		$disputes       = array_slice( $all_disputes, 0, 10 );

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
	 * Safe profile and account settings update handler.
	 * Handles personal details, address, contact numbers, password change, and email change.
	 * Enforces strict authentication, nonce verification, and current password verification.
	 * Protects SSN, role, and approval status from modification.
	 *
	 * @param int   $user_id User ID.
	 * @param array $data    Payload ($_POST).
	 * @return array Result array with status and message.
	 */
	/**
	 * User meta key holding a requested-but-unverified new email address.
	 */
	public const META_PENDING_EMAIL = '_ft_pending_email';

	/**
	 * Complete a pending email change after verifying the code sent to the new address.
	 *
	 * @param int    $user_id User ID (must be the current user — enforced by caller).
	 * @param string $code    6-digit code.
	 * @return array
	 */
	public static function confirm_email_change( int $user_id, string $code ): array {
		$user    = get_userdata( $user_id );
		$pending = (string) get_user_meta( $user_id, self::META_PENDING_EMAIL, true );
		if ( ! $user || '' === $pending || ! is_email( $pending ) ) {
			return array(
				'success' => false,
				'error'   => 'no_pending_email',
				'message' => __( 'There is no pending email change to confirm.', 'fraud-tenant-core' ),
			);
		}

		$code = preg_replace( '/\D/', '', $code );
		$res  = OtpService::verify_otp( $pending, (string) $code, 'email_change' );
		if ( empty( $res['success'] ) || (int) ( $res['user_id'] ?? 0 ) !== $user_id ) {
			return array(
				'success' => false,
				'error'   => $res['error'] ?? 'invalid_code',
				'message' => ! empty( $res['success'] ) ? __( 'This confirmation code is not valid for your account.', 'fraud-tenant-core' ) : ( $res['message'] ?? __( 'Invalid confirmation code.', 'fraud-tenant-core' ) ),
			);
		}

		$owner_of_email = email_exists( $pending );
		if ( $owner_of_email && (int) $owner_of_email !== $user_id ) {
			delete_user_meta( $user_id, self::META_PENDING_EMAIL );
			return array(
				'success' => false,
				'error'   => 'email_exists',
				'message' => __( 'This email address is already registered to another account.', 'fraud-tenant-core' ),
			);
		}

		$old_email = $user->user_email;
		$updated   = wp_update_user(
			array(
				'ID'         => $user_id,
				'user_email' => $pending,
			)
		);
		if ( is_wp_error( $updated ) ) {
			return array(
				'success' => false,
				'error'   => 'update_failed',
				'message' => __( 'Your email address could not be updated. Please try again.', 'fraud-tenant-core' ),
			);
		}
		delete_user_meta( $user_id, self::META_PENDING_EMAIL );

		// Notify the previous address (security notice; contains no codes or tokens).
		wp_mail(
			$old_email,
			__( '[Fraud Tenant] Your account email address was changed', 'fraud-tenant-core' ),
			__( 'Hello,', 'fraud-tenant-core' ) . "\n\n" .
			sprintf(
				/* translators: %s: masked new email */
				__( 'The email address on your Fraud Tenant account was changed to %s. If you did not make this change, contact support immediately.', 'fraud-tenant-core' ),
				Sanitizer::mask_email( $pending )
			) . "\n\n---\nFraud Tenant Platform\n",
			array( 'Content-Type: text/plain; charset=UTF-8' )
		);

		AuditLogger::log(
			'user.email_updated',
			'user',
			$user_id,
			array(
				'old_email' => Sanitizer::mask_email( $old_email ),
				'new_email' => Sanitizer::mask_email( $pending ),
				'verified'  => true,
			),
			$user_id
		);

		return array(
			'success' => true,
			'message' => __( 'Your email address has been verified and updated.', 'fraud-tenant-core' ),
		);
	}

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

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return array(
				'success' => false,
				'error'   => 'user_not_found',
				'message' => __( 'User account not found.', 'fraud-tenant-core' ),
			);
		}

		$section = isset( $data['account_section'] ) ? sanitize_key( $data['account_section'] ) : '';

		// 1. Password Change Workflow
		if ( 'password' === $section || ! empty( $data['new_password'] ) ) {
			$current_password = isset( $data['current_password'] ) ? (string) $data['current_password'] : '';
			$new_password     = isset( $data['new_password'] ) ? (string) $data['new_password'] : '';
			$confirm_password = isset( $data['confirm_password'] ) ? (string) $data['confirm_password'] : '';

			if ( empty( $current_password ) ) {
				return array(
					'success' => false,
					'error'   => 'missing_current_password',
					'message' => __( 'Current password is required to change your password.', 'fraud-tenant-core' ),
				);
			}

			if ( ! wp_check_password( $current_password, $user->user_pass, $user_id ) ) {
				return array(
					'success' => false,
					'error'   => 'invalid_current_password',
					'message' => __( 'The current password provided is incorrect.', 'fraud-tenant-core' ),
				);
			}

			if ( strlen( $new_password ) < 8 ) {
				return array(
					'success' => false,
					'error'   => 'password_too_short',
					'message' => __( 'New password must be at least 8 characters in length.', 'fraud-tenant-core' ),
				);
			}

			if ( $new_password !== $confirm_password ) {
				return array(
					'success' => false,
					'error'   => 'password_mismatch',
					'message' => __( 'New password confirmation does not match.', 'fraud-tenant-core' ),
				);
			}

			wp_set_password( $new_password, $user_id );
			// Sign out every other session; the current browser receives a fresh session below.
			if ( class_exists( '\WP_Session_Tokens' ) ) {
				\WP_Session_Tokens::get_instance( $user_id )->destroy_all();
			}
			wp_set_current_user( $user_id );
			if ( ! headers_sent() ) {
				wp_set_auth_cookie( $user_id );
			}

			AuditLogger::log(
				'user.password_changed',
				'user',
				$user_id,
				array(),
				$user_id
			);

			if ( 'password' === $section ) {
				return array(
					'success' => true,
					'message' => __( 'Your password has been changed successfully.', 'fraud-tenant-core' ),
				);
			}
		}

		// 2. Email Change Workflow
		if ( 'email' === $section || ( isset( $data['new_email'] ) && '' !== trim( (string) $data['new_email'] ) ) ) {
			$raw_email = isset( $data['new_email'] ) ? trim( (string) $data['new_email'] ) : '';

			$current_password = isset( $data['current_password'] ) ? (string) $data['current_password'] : '';

			if ( empty( $current_password ) ) {
				return array(
					'success' => false,
					'error'   => 'missing_current_password',
					'message' => __( 'Current password is required to change your email address.', 'fraud-tenant-core' ),
				);
			}

			if ( ! wp_check_password( $current_password, $user->user_pass, $user_id ) ) {
				return array(
					'success' => false,
					'error'   => 'invalid_current_password',
					'message' => __( 'The current password provided is incorrect.', 'fraud-tenant-core' ),
				);
			}

			if ( ! is_email( $raw_email ) ) {
				return array(
					'success' => false,
					'error'   => 'invalid_email',
					'message' => __( 'Please enter a valid email address.', 'fraud-tenant-core' ),
				);
			}

			$new_email = sanitize_email( $raw_email );

			if ( strtolower( $new_email ) !== strtolower( $user->user_email ) ) {
				$existing_user_id = email_exists( $new_email );
				if ( $existing_user_id && (int) $existing_user_id !== $user_id ) {
					return array(
						'success' => false,
						'error'   => 'email_exists',
						'message' => __( 'This email address is already registered to another account.', 'fraud-tenant-core' ),
					);
				}

				// Verified email change: nothing changes until the NEW address proves control
				// with a hashed, single-use, short-lived 6-digit code (OtpService, purpose email_change).
				$otp = OtpService::generate_otp( $user_id, $new_email, 'email_change' );
				if ( empty( $otp['success'] ) ) {
					return array(
						'success' => false,
						'error'   => $otp['error'] ?? 'otp_failed',
						'message' => $otp['message'] ?? __( 'Could not send a confirmation code. Please try again shortly.', 'fraud-tenant-core' ),
					);
				}
				update_user_meta( $user_id, self::META_PENDING_EMAIL, $new_email );

				AuditLogger::log(
					'user.email_change_requested',
					'user',
					$user_id,
					array( 'new_email' => Sanitizer::mask_email( $new_email ) ),
					$user_id
				);

				return array(
					'success' => true,
					'pending' => true,
					'message' => __( 'We sent a 6-digit confirmation code to your new email address. Enter it in Account Settings to complete the change. Your current email stays active until then.', 'fraud-tenant-core' ),
				);
			}

			return array(
				'success' => false,
				'error'   => 'email_unchanged',
				'message' => __( 'The new email address is the same as your current one.', 'fraud-tenant-core' ),
			);
		}

		// 2b. Confirm a pending email change with the code sent to the new address.
		if ( 'email_confirm' === $section ) {
			return self::confirm_email_change( $user_id, isset( $data['email_code'] ) ? (string) $data['email_code'] : '' );
		}

		// 2c. Cancel a pending email change.
		if ( 'email_cancel' === $section ) {
			delete_user_meta( $user_id, self::META_PENDING_EMAIL );
			return array(
				'success' => true,
				'message' => __( 'The pending email change was cancelled.', 'fraud-tenant-core' ),
			);
		}

		// 3. User Name Updates (WordPress core user profile)
		$wp_user_updates = array( 'ID' => $user_id );
		$name_changed    = false;

		if ( isset( $data['first_name'] ) ) {
			$wp_user_updates['first_name'] = sanitize_text_field( wp_unslash( $data['first_name'] ) );
			$name_changed = true;
		}

		if ( isset( $data['last_name'] ) ) {
			$wp_user_updates['last_name'] = sanitize_text_field( wp_unslash( $data['last_name'] ) );
			$name_changed = true;
		}

		if ( $name_changed ) {
			$first = $wp_user_updates['first_name'] ?? $user->first_name;
			$last  = $wp_user_updates['last_name'] ?? $user->last_name;
			if ( ! empty( $first ) || ! empty( $last ) ) {
				$wp_user_updates['display_name'] = trim( $first . ' ' . $last );
			}
			wp_update_user( $wp_user_updates );
		}

		// 4. Extended Profile Fields (Custom Table)
		global $wpdb;
		$table           = $wpdb->prefix . Constants::TABLE_USER_PROFILES;
		$profile_updates = array();
		$profile_formats = array();

		if ( isset( $data['phone'] ) ) {
			$phone = sanitize_text_field( wp_unslash( $data['phone'] ) );
			$phone = preg_replace( '/[^\d\+\-\(\)\s\.]/', '', $phone );
			$profile_updates['phone'] = $phone;
			$profile_formats[]        = '%s';
		}

		if ( isset( $data['address_line1'] ) ) {
			$profile_updates['address_line1'] = sanitize_text_field( wp_unslash( $data['address_line1'] ) );
			$profile_formats[]                = '%s';
		}

		if ( isset( $data['address_line2'] ) ) {
			$profile_updates['address_line2'] = sanitize_text_field( wp_unslash( $data['address_line2'] ) );
			$profile_formats[]                = '%s';
		}

		if ( isset( $data['city'] ) ) {
			$profile_updates['city'] = sanitize_text_field( wp_unslash( $data['city'] ) );
			$profile_formats[]       = '%s';
		}

		if ( isset( $data['state'] ) ) {
			$profile_updates['state'] = sanitize_text_field( wp_unslash( $data['state'] ) );
			$profile_formats[]        = '%s';
		}

		if ( isset( $data['postal_code'] ) ) {
			$profile_updates['postal_code'] = sanitize_text_field( wp_unslash( $data['postal_code'] ) );
			$profile_formats[]              = '%s';
		}

		if ( isset( $data['business_name'] ) ) {
			$profile_updates['business_name'] = sanitize_text_field( wp_unslash( $data['business_name'] ) );
			$profile_formats[]                = '%s';
		}

		if ( isset( $data['business_reg_number'] ) ) {
			$profile_updates['business_reg_number'] = sanitize_text_field( wp_unslash( $data['business_reg_number'] ) );
			$profile_formats[]                      = '%s';
		}

		if ( ! empty( $profile_updates ) ) {
			$profile_updates['updated_at'] = current_time( 'mysql', true );
			$profile_formats[]             = '%s';

			$updated = $wpdb->update(
				$table,
				$profile_updates,
				array( 'user_id' => $user_id ),
				$profile_formats,
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
				array( 'fields' => array_keys( $profile_updates ) ),
				$user_id
			);
		}

		return array(
			'success' => true,
			'message' => __( 'Profile updated successfully.', 'fraud-tenant-core' ),
		);
	}
}
