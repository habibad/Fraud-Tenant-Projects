<?php
/**
 * Checkout Orchestration Service.
 *
 * Coordinates hosted subscription checkout, duplicate protection, session reuse,
 * return verification, Customer Portal sessions, atomic concurrency locking,
 * and dual-mode environment isolation.
 *
 * @package FraudTenant\Services
 */

namespace FraudTenant\Services;

use FraudTenant\Utils\Constants;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CheckoutService {

	/**
	 * Meta keys for tracking active/pending checkout sessions per user and mode.
	 */
	public const META_PENDING_SESSION      = '_ft_pending_checkout_session'; // Legacy fallback
	public const META_PENDING_SESSION_TEST = '_ft_pending_checkout_session_test';
	public const META_PENDING_SESSION_LIVE = '_ft_pending_checkout_session_live';

	/**
	 * Allowed plan codes for paid subscription checkout.
	 */
	public const ALLOWED_PAID_PLANS = array(
		'standard_monthly',
		'standard_yearly',
	);

	/**
	 * Retrieve pending checkout session user metadata key for active or specified mode.
	 *
	 * @param string|null $mode Optional mode ('test' or 'live').
	 * @return string Meta key name.
	 */
	public static function get_pending_session_meta_key( ?string $mode = null ): string {
		$target_mode = $mode ? strtolower( trim( $mode ) ) : StripeCredentialService::get_active_mode();
		return Constants::STRIPE_MODE_LIVE === $target_mode
			? self::META_PENDING_SESSION_LIVE
			: self::META_PENDING_SESSION_TEST;
	}

	/**
	 * Initiate a hosted Stripe subscription Checkout Session for an eligible owner.
	 *
	 * Enforcement:
	 * - Only approved, eligible property owners may initiate checkout.
	 * - In production with Test Mode active, checkout is restricted to admin QA accounts.
	 * - Tenants and administrators (non-QA) are strictly blocked.
	 * - Owners with active paid subscriptions are blocked from duplicate checkout.
	 * - Only 'standard_monthly' and 'standard_yearly' may be purchased. Free plan is rejected.
	 * - Concurrent requests are serialized via atomic database lock.
	 * - Existing open sessions for the same plan are safely verified with Stripe and reused.
	 * - Sessions awaiting webhook processing block duplicate checkout.
	 * - Return URLs are 100% server-generated (browser inputs rejected).
	 *
	 * @param int         $user_id   WordPress User ID.
	 * @param string      $plan_code Plan code requested ('standard_monthly' or 'standard_yearly').
	 * @param string|null $mode      Optional operational mode ('test' or 'live').
	 * @return array{success: bool, checkout_url?: string, session_id?: string, reused?: bool, error?: string, code?: string, status?: int}
	 */
	public static function initiate_checkout( int $user_id, string $plan_code, ?string $mode = null ): array {
		$target_mode = $mode ? strtolower( trim( $mode ) ) : StripeCredentialService::get_active_mode();

		// 1. Owner eligibility check
		if ( ! MembershipService::is_eligible_owner( $user_id ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Access denied. You must be an approved property owner to subscribe to a membership plan.', 'fraud-tenant-core' ),
				'code'    => 'unauthorized_owner',
				'status'  => 403,
			);
		}

		// 1b. QA restriction check on production
		if ( StripeCredentialService::is_qa_mode_restricted() && ! StripeCredentialService::is_billing_qa_account( $user_id ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Billing is currently in QA Test Mode on this system. Subscription checkout is restricted to administrator-designated billing QA accounts.', 'fraud-tenant-core' ),
				'code'    => 'test_mode_qa_restricted',
				'status'  => 403,
			);
		}

		// 2. Active subscription duplicate check for this mode
		if ( MembershipService::has_active_subscription( $user_id ) ) {
			return array(
				'success' => false,
				'error'   => __( 'You already have an active membership subscription.', 'fraud-tenant-core' ),
				'code'    => 'active_subscription_exists',
				'status'  => 409,
			);
		}

		// 3. Plan validation
		$plan_code = sanitize_key( trim( $plan_code ) );
		if ( 'free' === $plan_code ) {
			return array(
				'success' => false,
				'error'   => __( 'The Free Plan is free and does not require paid checkout.', 'fraud-tenant-core' ),
				'code'    => 'free_plan_not_purchasable',
				'status'  => 400,
			);
		}

		if ( ! in_array( $plan_code, self::ALLOWED_PAID_PLANS, true ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Invalid membership plan selected for checkout.', 'fraud-tenant-core' ),
				'code'    => 'invalid_plan_code',
				'status'  => 400,
			);
		}

		$plan = MembershipService::get_plan_by_code( $plan_code );
		if ( ! $plan || empty( $plan->is_active ) ) {
			return array(
				'success' => false,
				'error'   => __( 'The selected membership plan is currently unavailable.', 'fraud-tenant-core' ),
				'code'    => 'plan_inactive',
				'status'  => 400,
			);
		}

		$price_id = StripeService::get_plan_price_id( $plan, $target_mode );
		if ( empty( $price_id ) || ! preg_match( '/^price_[a-zA-Z0-9_]+$/', $price_id ) ) {
			return array(
				'success' => false,
				'error'   => sprintf(
					/* translators: %s: mode name */
					__( 'This membership plan does not have a valid Stripe Price configured for %s mode.', 'fraud-tenant-core' ),
					ucfirst( $target_mode )
				),
				'code'    => 'invalid_plan_price_config',
				'status'  => 500,
			);
		}

		// 4. Atomic Concurrency Lock (owner-level database serialization)
		$lock_res = CheckoutLockService::acquire_lock( $user_id, $plan_code, 30 );
		if ( ! $lock_res['success'] ) {
			return array(
				'success' => false,
				'error'   => $lock_res['error'] ?? __( 'A checkout session creation is already in progress. Please wait a moment.', 'fraud-tenant-core' ),
				'code'    => 'checkout_in_progress',
				'status'  => $lock_res['status'] ?? 429,
			);
		}

		$lock_token = $lock_res['lock_token'] ?? '';

		try {
			// 5. Inspect the persisted logical checkout attempt for this mode.
			$pending_meta_key = self::get_pending_session_meta_key( $target_mode );
			$pending          = get_user_meta( $user_id, $pending_meta_key, true );

			if ( empty( $pending ) && Constants::STRIPE_MODE_TEST === $target_mode ) {
				$pending = get_user_meta( $user_id, self::META_PENDING_SESSION, true );
			}

			$attempt_id = '';

			if ( is_array( $pending ) && ( $pending['mode'] ?? $target_mode ) === $target_mode ) {
				$pending_session_id = (string) ( $pending['session_id'] ?? '' );
				$pending_plan       = (string) ( $pending['plan_code'] ?? '' );
				$created_at         = (int) ( $pending['created_at'] ?? 0 );
				$age                = time() - $created_at;

				if ( '' !== $pending_session_id && $age < ( 86400 - 3600 ) ) {
					// A remote session exists: its REMOTE status decides reuse (a cancel-URL visit
					// alone does not prove expiry).
					$existing_session = StripeService::get_checkout_session( $pending_session_id, $target_mode );
					if ( $existing_session['success'] && ! empty( $existing_session['data'] ) ) {
						$sd             = $existing_session['data'];
						$session_status = (string) ( $sd['status'] ?? '' );
						$pay_status     = (string) ( $sd['payment_status'] ?? '' );
						$local_cust     = StripeService::get_customer_id( $user_id, $target_mode );
						$owner_ok       = ( (string) $user_id === (string) ( $sd['client_reference_id'] ?? '' ) )
							&& ( (string) $user_id === (string) ( $sd['metadata']['wp_user_id'] ?? '' ) );
						$customer_ok    = ! empty( $local_cust ) && ( (string) ( $sd['customer'] ?? '' ) ) === $local_cust;
						$mode_ok        = ( ! empty( $sd['livemode'] ) ) === ( Constants::STRIPE_MODE_LIVE === $target_mode );
						$plan_ok        = $pending_plan === $plan_code && ( (string) ( $sd['metadata']['plan_code'] ?? '' ) ) === $plan_code;

						if ( 'open' === $session_status && $owner_ok && $customer_ok && $mode_ok && $plan_ok && ! empty( $sd['url'] ) ) {
							AuditLogger::log(
								'stripe_checkout_session_reused',
								'checkout_session',
								$user_id,
								array(
									'session_id' => $pending_session_id,
									'plan_code'  => $plan_code,
									'mode'       => $target_mode,
								),
								$user_id
							);

							return array(
								'success'      => true,
								'reused'       => true,
								'checkout_url' => (string) $sd['url'],
								'session_id'   => $pending_session_id,
							);
						}

						if ( 'complete' === $session_status && $owner_ok && in_array( $pay_status, array( 'paid', 'no_payment_required', 'unpaid' ), true ) ) {
							return array(
								'success' => false,
								'error'   => __( 'A subscription checkout has completed and is awaiting server-side verification.', 'fraud-tenant-core' ),
								'code'    => 'checkout_pending_webhook',
								'status'  => 409,
							);
						}
					} elseif ( ! $existing_session['success'] && 'resource_missing' !== ( $existing_session['code'] ?? '' ) ) {
						// Could not verify remote status (transport/API failure): fail closed rather
						// than risk a duplicate subscription.
						return array(
							'success' => false,
							'error'   => __( 'Unable to verify your previous checkout session with Stripe. Please try again shortly.', 'fraud-tenant-core' ),
							'code'    => 'checkout_state_unverifiable',
							'status'  => 503,
						);
					}
				} elseif ( '' === $pending_session_id && $pending_plan === $plan_code && $age < 3600 && ! empty( $pending['attempt_id'] ) ) {
					// Retry of the same logical attempt whose session creation outcome is unknown
					// (e.g. timeout): reuse its idempotency key so Stripe returns the same session.
					$attempt_id = sanitize_key( (string) $pending['attempt_id'] );
				}
			}

			// Any other state (expired/complete-unrelated/different plan/old) starts a new attempt.
			if ( '' === $attempt_id ) {
				$attempt_id = 'att_' . bin2hex( random_bytes( 16 ) );
			}

			// 6. Persist the logical attempt BEFORE calling Stripe.
			$attempt_payload = array(
				'session_id' => '',
				'plan_code'  => $plan->plan_code,
				'attempt_id' => $attempt_id,
				'mode'       => $target_mode,
				'created_at' => time(),
			);
			update_user_meta( $user_id, $pending_meta_key, $attempt_payload );
			if ( Constants::STRIPE_MODE_TEST === $target_mode ) {
				delete_user_meta( $user_id, self::META_PENDING_SESSION );
			}

			// 7. Fixed server-generated return URLs.
			$success_url = add_query_arg(
				array(
					'ft_tab'          => 'membership',
					'checkout_status' => 'success',
					'session_id'      => '{CHECKOUT_SESSION_ID}',
				),
				home_url( '/owner-dashboard/' )
			);

			$cancel_url = add_query_arg(
				array(
					'ft_tab'          => 'membership',
					'checkout_status' => 'cancelled',
				),
				home_url( '/owner-dashboard/' )
			);

			// 8. Create the session with the logical attempt's stable idempotency key.
			$session_res = StripeService::create_checkout_session(
				$user_id,
				$plan,
				$success_url,
				$cancel_url,
				null,
				$attempt_id,
				$target_mode
			);

			if ( ! $session_res['success'] || empty( $session_res['checkout_url'] ) || empty( $session_res['session_id'] ) ) {
				return array(
					'success' => false,
					'error'   => $session_res['error'] ?? __( 'Failed initiating Stripe checkout.', 'fraud-tenant-core' ),
					'code'    => $session_res['code'] ?? 'checkout_failed',
					'status'  => 502,
				);
			}

			// 9. Bind the remote session to the attempt.
			$attempt_payload['session_id'] = $session_res['session_id'];
			update_user_meta( $user_id, $pending_meta_key, $attempt_payload );

			return array(
				'success'      => true,
				'checkout_url' => $session_res['checkout_url'],
				'session_id'   => $session_res['session_id'],
				'reused'       => false,
				'attempt_id'   => $attempt_id,
			);

		} finally {
			// Token-bound lock release
			CheckoutLockService::release_lock( $user_id, $lock_token );
		}
	}

	/**
	 * Verify a returned Checkout Session and determine display status.
	 *
	 * Enforcement:
	 * - Session must belong to the requesting user.
	 * - Customer must match user's stored customer ID.
	 * - State is verified with Stripe API.
	 * - Direct activation requires verified payment and subscription status.
	 *
	 * @param int         $user_id    WordPress User ID.
	 * @param string      $session_id Stripe Checkout Session ID.
	 * @param string|null $mode       Optional mode.
	 * @return array{success: bool, status?: string, message?: string, plan_code?: string, error?: string, code?: string, http_status?: int}
	 */
	public static function verify_checkout_return( int $user_id, string $session_id, ?string $mode = null ): array {
		if ( ! MembershipService::is_eligible_owner( $user_id ) ) {
			return array(
				'success'     => false,
				'error'       => __( 'Access denied. You must be an approved property owner.', 'fraud-tenant-core' ),
				'code'        => 'unauthorized_owner',
				'http_status' => 403,
			);
		}

		$target_mode = $mode ? strtolower( trim( $mode ) ) : StripeCredentialService::get_active_mode();
		$session_id  = sanitize_text_field( trim( $session_id ) );

		if ( empty( $session_id ) || ! preg_match( '/^cs_[a-zA-Z0-9_]+$/', $session_id ) ) {
			return array(
				'success'     => false,
				'error'       => __( 'Invalid Checkout Session ID format.', 'fraud-tenant-core' ),
				'code'        => 'invalid_session_id',
				'http_status' => 400,
			);
		}

		$res = StripeService::get_checkout_session( $session_id, $target_mode );
		if ( ! $res['success'] || empty( $res['data'] ) ) {
			return array(
				'success'     => false,
				'error'       => $res['error'] ?? __( 'Could not retrieve checkout session from Stripe.', 'fraud-tenant-core' ),
				'code'        => $res['code'] ?? 'session_fetch_failed',
				'http_status' => 404,
			);
		}

		$session_data = $res['data'];

		// Verify session belongs to current user
		$client_ref = isset( $session_data['client_reference_id'] ) ? (int) $session_data['client_reference_id'] : 0;
		$meta_uid   = isset( $session_data['metadata']['wp_user_id'] ) ? (int) $session_data['metadata']['wp_user_id'] : 0;

		$mode_matches = ( ! empty( $session_data['livemode'] ) ) === ( Constants::STRIPE_MODE_LIVE === $target_mode );
		if ( $client_ref !== $user_id || $meta_uid !== $user_id || ! $mode_matches ) {
			AuditLogger::log(
				'stripe_checkout_api_failed',
				'checkout_session',
				$user_id,
				array(
					'session_id' => $session_id,
					'reason'     => 'foreign_session_rejected',
				),
				$user_id
			);

			return array(
				'success'     => false,
				'error'       => __( 'Access denied. This checkout session belongs to another user account.', 'fraud-tenant-core' ),
				'code'        => 'foreign_session_rejected',
				'http_status' => 403,
			);
		}

		// Verify customer match
		$stored_customer_id = StripeService::get_customer_id( $user_id, $target_mode );
		$session_customer   = is_array( $session_data['customer'] ?? null ) ? (string) ( $session_data['customer']['id'] ?? '' ) : (string) ( $session_data['customer'] ?? '' );
		if ( empty( $stored_customer_id ) || $stored_customer_id !== $session_customer ) {
			return array(
				'success'     => false,
				'error'       => __( 'Customer account mismatch detected.', 'fraud-tenant-core' ),
				'code'        => 'customer_mismatch',
				'http_status' => 403,
			);
		}

		$status     = $session_data['status'] ?? 'unknown';
		$pay_status = $session_data['payment_status'] ?? 'unpaid';
		$plan_code  = $session_data['metadata']['plan_code'] ?? '';

		if ( 'complete' === $status && 'paid' === $pay_status ) {
			// Clear pending session
			$pending_meta = self::get_pending_session_meta_key( $target_mode );
			delete_user_meta( $user_id, $pending_meta );
			delete_user_meta( $user_id, self::META_PENDING_SESSION );

			// Reconcile from Stripe's authoritative Subscription (same fully verified path the
			// webhook uses). The return URL itself grants nothing: entitlement is only granted if
			// the remote subscription is active, owned, mode-matched and price/plan-validated.
			$is_active = MembershipService::has_active_subscription( $user_id );
			$sub_ref   = $session_data['subscription'] ?? '';
			$sub_ref   = is_array( $sub_ref ) ? (string) ( $sub_ref['id'] ?? '' ) : (string) $sub_ref;
			if ( ! $is_active && '' !== $sub_ref ) {
				try {
					StripeWebhookService::sync_subscription( $sub_ref, $target_mode, 'checkout.return_reconcile' );
				} catch ( \Throwable $e ) {
					AuditLogger::log( 'stripe_checkout_return_reconcile_failed', 'checkout_session', $user_id, array( 'session_id' => $session_id, 'mode' => $target_mode ), $user_id );
				}
				$is_active = MembershipService::has_active_subscription( $user_id );
			}

			return array(
				'success'     => true,
				'status'      => $is_active ? 'active' : 'pending_verification',
				'message'     => $is_active
					? __( 'Subscription is active! All plan entitlements are now unlocked.', 'fraud-tenant-core' )
					: __( 'Payment submitted. Membership activation is pending server-side payment and subscription verification.', 'fraud-tenant-core' ),
				'plan_code'   => (string) $plan_code,
				'http_status' => 200,
			);
		}

		if ( 'open' === $status ) {
			return array(
				'success'     => true,
				'status'      => 'open',
				'message'     => __( 'Checkout session is currently open.', 'fraud-tenant-core' ),
				'plan_code'   => (string) $plan_code,
				'http_status' => 200,
			);
		}

		// Expired or cancelled
		return array(
			'success'     => true,
			'status'      => 'cancelled',
			'message'     => __( 'Checkout was cancelled or expired.', 'fraud-tenant-core' ),
			'plan_code'   => (string) $plan_code,
			'http_status' => 200,
		);
	}

	/**
	 * Initiate a Stripe Customer Portal session for an eligible owner.
	 *
	 * @param int         $user_id WordPress User ID.
	 * @param string|null $mode    Optional mode.
	 * @return array{success: bool, portal_url?: string, error?: string, code?: string, status?: int}
	 */
	public static function initiate_customer_portal( int $user_id, ?string $mode = null ): array {
		if ( ! MembershipService::is_eligible_owner( $user_id ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Access denied. You must be an approved property owner.', 'fraud-tenant-core' ),
				'code'    => 'unauthorized_owner',
				'status'  => 403,
			);
		}

		$return_url = add_query_arg(
			array( 'ft_tab' => 'membership' ),
			home_url( '/owner-dashboard/' )
		);

		$res = StripeService::create_customer_portal_session( $user_id, $return_url, $mode );
		if ( ! $res['success'] || empty( $res['portal_url'] ) ) {
			return array(
				'success' => false,
				'error'   => $res['error'] ?? __( 'Failed creating Customer Portal session.', 'fraud-tenant-core' ),
				'code'    => $res['code'] ?? 'portal_failed',
				'status'  => 500,
			);
		}

		return array(
			'success'    => true,
			'portal_url' => $res['portal_url'],
		);
	}
}
