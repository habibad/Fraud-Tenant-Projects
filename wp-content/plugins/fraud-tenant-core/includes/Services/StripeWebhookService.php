<?php
/**
 * Stripe Webhook Processing Service (Phase 9.3).
 *
 * Security & reliability model:
 * - Signature: Stripe-Signature header parsed (t= and every v1=), HMAC-SHA256 over
 *   "{t}.{raw_body}" with the mode-specific endpoint secret, constant-time comparison,
 *   300-second tolerance. No bypass in any environment. Missing secret fails closed.
 * - Mode: the secret is chosen from the payload's livemode flag; because the signature is
 *   then verified with THAT mode's secret, a forged livemode flag cannot pass verification.
 *   Records are stored with their livemode, and entitlements only honour the active mode.
 * - Authoritative state: handlers never trust the event payload for entitlement decisions.
 *   They re-fetch the Checkout Session / Subscription from the Stripe API (pinned API version)
 *   and validate mode, owner, customer, Price and plan before writing local state. This also
 *   makes processing order-independent (out-of-order events converge on the latest remote state).
 * - Lifecycle: received -> processing -> processed | failed, with INSERT IGNORE de-duplication,
 *   atomic claiming, attempt counting, stale-processing reclaim, sanitized error storage and
 *   minimized payload storage (no customer PII persisted).
 *
 * @package FraudTenant\Services
 */

namespace FraudTenant\Services;

use FraudTenant\Utils\Constants;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class StripeWebhookService {

	/**
	 * Maximum permissible timestamp drift in seconds (5 minutes).
	 */
	public const TIMESTAMP_TOLERANCE = 300;

	/**
	 * Seconds after which an event stuck in 'processing' may be reclaimed.
	 */
	public const STALE_PROCESSING_SECONDS = 300;

	/**
	 * Subscription statuses that are synchronized from Stripe.
	 */
	public const KNOWN_SUB_STATUSES = array( 'active', 'trialing', 'past_due', 'canceled', 'unpaid', 'incomplete', 'incomplete_expired', 'paused' );

	/**
	 * Handle inbound Stripe Webhook REST request.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response
	 */
	public static function handle_webhook( WP_REST_Request $request ): WP_REST_Response {
		$raw_body         = $request->get_body();
		$signature_header = (string) $request->get_header( 'stripe-signature' );

		if ( '' === $raw_body ) {
			return self::reply( 400, array( 'success' => false, 'error' => 'Empty request body' ) );
		}
		if ( '' === $signature_header ) {
			return self::reply( 400, array( 'success' => false, 'error' => 'Missing Stripe-Signature header' ) );
		}

		$event_data = json_decode( $raw_body, true );
		if ( ! is_array( $event_data ) || empty( $event_data['id'] ) || empty( $event_data['type'] ) || 'event' !== ( $event_data['object'] ?? 'event' ) ) {
			return self::reply( 400, array( 'success' => false, 'error' => 'Invalid JSON payload structure' ) );
		}

		$is_livemode = ! empty( $event_data['livemode'] );
		$target_mode = $is_livemode ? Constants::STRIPE_MODE_LIVE : Constants::STRIPE_MODE_TEST;

		$sig_res = self::verify_signature( $raw_body, $signature_header, $target_mode );
		if ( ! $sig_res['valid'] ) {
			AuditLogger::log(
				'stripe_webhook_signature_failed',
				'stripe_webhook',
				null,
				array(
					'mode'  => $target_mode,
					'error' => (string) ( $sig_res['error'] ?? 'Signature check failed' ),
				)
			);
			return self::reply( 400, array( 'success' => false, 'error' => 'Signature verification failed' ) );
		}

		$process_res = self::process_event( $event_data, $raw_body, $is_livemode );
		return self::reply( $process_res['status'], $process_res['data'] );
	}

	/**
	 * Build a REST response.
	 *
	 * @param int   $status HTTP status.
	 * @param array $data   Body.
	 * @return WP_REST_Response
	 */
	protected static function reply( int $status, array $data ): WP_REST_Response {
		return new WP_REST_Response( $data, $status );
	}

	/**
	 * Verify Stripe signature header using constant-time comparison.
	 *
	 * @param string   $raw_body         Raw HTTP request body (exact bytes received).
	 * @param string   $signature_header Stripe-Signature header value.
	 * @param string   $mode             'test' or 'live'.
	 * @param int|null $now              Optional clock override (tests).
	 * @return array{valid: bool, error?: string}
	 */
	public static function verify_signature( string $raw_body, string $signature_header, string $mode, ?int $now = null ): array {
		$secret = StripeCredentialService::get_webhook_secret( $mode );
		if ( empty( $secret ) ) {
			return array(
				'valid' => false,
				'error' => sprintf( 'Stripe webhook secret is not configured for %s mode.', ucfirst( $mode ) ),
			);
		}

		$timestamp  = null;
		$signatures = array();
		foreach ( explode( ',', $signature_header ) as $part ) {
			$kv = explode( '=', trim( $part ), 2 );
			if ( 2 !== count( $kv ) ) {
				continue;
			}
			if ( 't' === $kv[0] && ctype_digit( $kv[1] ) ) {
				$timestamp = (int) $kv[1];
			} elseif ( 'v1' === $kv[0] && preg_match( '/^[a-f0-9]{64}$/', $kv[1] ) ) {
				$signatures[] = $kv[1];
			}
		}

		if ( empty( $timestamp ) || empty( $signatures ) ) {
			return array( 'valid' => false, 'error' => 'Malformed Stripe-Signature header structure.' );
		}

		$now = $now ?? time();
		if ( abs( $now - $timestamp ) > self::TIMESTAMP_TOLERANCE ) {
			return array( 'valid' => false, 'error' => 'Webhook signature timestamp outside tolerance.' );
		}

		$expected_sig = hash_hmac( 'sha256', $timestamp . '.' . $raw_body, $secret );
		foreach ( $signatures as $provided_sig ) {
			if ( hash_equals( $expected_sig, $provided_sig ) ) {
				return array( 'valid' => true );
			}
		}

		return array( 'valid' => false, 'error' => 'Webhook signature mismatch.' );
	}

	/**
	 * Process a verified webhook event with de-duplication, atomic claiming and retry-compatible states.
	 *
	 * @param array  $event_data  Parsed event array.
	 * @param string $raw_payload Raw body string (not persisted; a minimized summary is stored instead).
	 * @param bool   $is_livemode Whether event is livemode.
	 * @return array{status: int, data: array}
	 */
	public static function process_event( array $event_data, string $raw_payload, bool $is_livemode ): array {
		global $wpdb;

		$event_id   = sanitize_text_field( (string) $event_data['id'] );
		$event_type = sanitize_text_field( (string) $event_data['type'] );
		$table      = $wpdb->prefix . Constants::TABLE_PAYMENT_EVENTS;
		$now        = current_time( 'mysql' );

		if ( ! preg_match( '/^evt_[A-Za-z0-9_]+$/', $event_id ) ) {
			return array( 'status' => 400, 'data' => array( 'received' => false, 'error' => 'Invalid event id' ) );
		}

		// 1. Idempotent registration (unique event_id; concurrent duplicates collapse to one row).
		$wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$table} (event_id, event_type, livemode, payload_json, status, attempt_count, received_at, processed_at)
				 VALUES (%s, %s, %d, %s, 'received', 0, %s, %s)",
				$event_id,
				$event_type,
				$is_livemode ? 1 : 0,
				wp_json_encode( self::minimize_event( $event_data ) ),
				$now,
				$now
			)
		);

		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, status, livemode FROM {$table} WHERE event_id = %s", $event_id ) );
		if ( ! $existing ) {
			return array( 'status' => 500, 'data' => array( 'received' => false, 'status' => 'storage_error' ) );
		}

		if ( (int) $existing->livemode !== ( $is_livemode ? 1 : 0 ) ) {
			return array( 'status' => 400, 'data' => array( 'received' => false, 'status' => 'mode_mismatch' ) );
		}

		if ( 'processed' === $existing->status ) {
			return array( 'status' => 200, 'data' => array( 'received' => true, 'status' => 'already_processed', 'event_id' => $event_id ) );
		}

		// 2. Atomic claim (also reclaims events left in 'processing' by a crashed worker).
		$stale_before = gmdate( 'Y-m-d H:i:s', strtotime( $now . ' UTC' ) - self::STALE_PROCESSING_SECONDS );
		$claimed      = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				 SET status = 'processing', locked_at = %s, attempt_count = attempt_count + 1
				 WHERE event_id = %s
				   AND ( status IN ('received', 'failed', 'pending')
				         OR ( status = 'processing' AND ( locked_at IS NULL OR locked_at < %s ) ) )",
				$now,
				$event_id,
				$stale_before
			)
		);

		if ( ! $claimed ) {
			// Another worker is processing it right now: non-2xx so Stripe retries later.
			return array( 'status' => 409, 'data' => array( 'received' => true, 'status' => 'in_progress', 'event_id' => $event_id ) );
		}

		try {
			$handler_res = self::dispatch_event( $event_type, $event_data, $is_livemode );

			$update = array(
				'status'        => 'processed',
				'processed_at'  => current_time( 'mysql' ),
				'error_message' => null,
				'locked_at'     => null,
			);
			$format = array( '%s', '%s', '%s', '%s' );
			if ( ! empty( $handler_res['user_id'] ) ) {
				$update['user_id'] = (int) $handler_res['user_id'];
				$format[]          = '%d';
			}
			$wpdb->update( $table, $update, array( 'event_id' => $event_id ), $format, array( '%s' ) );

			return array(
				'status' => 200,
				'data'   => array(
					'received' => true,
					'status'   => 'processed',
					'event_id' => $event_id,
					'type'     => $event_type,
				),
			);
		} catch ( \Throwable $e ) {
			$safe_error = self::sanitize_error( $e );
			$wpdb->update(
				$table,
				array(
					'status'        => 'failed',
					'error_message' => $safe_error,
					'locked_at'     => null,
				),
				array( 'event_id' => $event_id ),
				array( '%s', '%s', '%s' ),
				array( '%s' )
			);

			AuditLogger::log(
				'stripe_webhook_processing_failed',
				'stripe_webhook',
				(int) $existing->id,
				array(
					'event_id' => $event_id,
					'type'     => $event_type,
					'error'    => $safe_error,
				)
			);

			return array( 'status' => 500, 'data' => array( 'received' => false, 'status' => 'error', 'message' => 'Event processing failed.' ) );
		}
	}

	/**
	 * Sanitize exception text for storage: single line, no Stripe secrets, bounded length.
	 *
	 * @param \Throwable $e Exception.
	 * @return string
	 */
	protected static function sanitize_error( \Throwable $e ): string {
		$msg = preg_replace( '/(sk|rk|pk)_(test|live)_[A-Za-z0-9]+|whsec_[A-Za-z0-9]+/', '[redacted]', $e->getMessage() );
		$msg = sanitize_text_field( (string) $msg );
		return mb_substr( $msg, 0, 255 );
	}

	/**
	 * Build the minimized event record stored in ft_payment_events (no customer PII).
	 *
	 * @param array $event Event.
	 * @return array
	 */
	protected static function minimize_event( array $event ): array {
		$obj = is_array( $event['data']['object'] ?? null ) ? $event['data']['object'] : array();
		$min = array(
			'id'          => (string) ( $event['id'] ?? '' ),
			'type'        => (string) ( $event['type'] ?? '' ),
			'livemode'    => ! empty( $event['livemode'] ),
			'created'     => (int) ( $event['created'] ?? 0 ),
			'api_version' => (string) ( $event['api_version'] ?? '' ),
			'object'      => array(
				'id'     => (string) ( $obj['id'] ?? '' ),
				'object' => (string) ( $obj['object'] ?? '' ),
			),
		);
		if ( 'invoice' === ( $obj['object'] ?? '' ) ) {
			$min['summary'] = array(
				'invoice_id'   => (string) ( $obj['id'] ?? '' ),
				'subscription' => self::invoice_subscription_id( $obj ),
				'amount_paid'  => (int) ( $obj['amount_paid'] ?? 0 ),
				'amount_due'   => (int) ( $obj['amount_due'] ?? 0 ),
				'currency'     => strtolower( (string) ( $obj['currency'] ?? '' ) ),
				'status'       => (string) ( $obj['status'] ?? '' ),
				'billing_reason' => (string) ( $obj['billing_reason'] ?? '' ),
			);
		}
		return $min;
	}

	/**
	 * Dispatch event to its handler.
	 *
	 * @param string $event_type  Event type.
	 * @param array  $event_data  Event.
	 * @param bool   $is_livemode Livemode.
	 * @return array{user_id?: int}
	 * @throws \Exception On unrecoverable failure (event is marked failed and retried by Stripe).
	 */
	protected static function dispatch_event( string $event_type, array $event_data, bool $is_livemode ): array {
		$object = is_array( $event_data['data']['object'] ?? null ) ? $event_data['data']['object'] : array();
		$mode   = $is_livemode ? Constants::STRIPE_MODE_LIVE : Constants::STRIPE_MODE_TEST;

		switch ( $event_type ) {
			case 'checkout.session.completed':
			case 'checkout.session.async_payment_succeeded':
			case 'checkout.session.async_payment_failed':
			case 'checkout.session.expired':
				return self::handle_checkout_session_event( $event_type, $object, $mode );

			case 'customer.subscription.created':
			case 'customer.subscription.updated':
			case 'customer.subscription.deleted':
				if ( empty( $object['id'] ) ) {
					throw new \Exception( 'Subscription event without subscription id.' );
				}
				return self::sync_subscription( (string) $object['id'], $mode, $event_type );

			case 'invoice.paid':
			case 'invoice.payment_succeeded':
			case 'invoice.payment_failed':
				$sub_id = self::invoice_subscription_id( $object );
				if ( '' === $sub_id ) {
					return array(); // Not a subscription invoice.
				}
				return self::sync_subscription( $sub_id, $mode, $event_type );

			default:
				return array(); // Acknowledged, intentionally not handled.
		}
	}

	/**
	 * Extract subscription id from an invoice object across API versions.
	 *
	 * @param array $invoice Invoice object.
	 * @return string
	 */
	protected static function invoice_subscription_id( array $invoice ): string {
		$sub = $invoice['subscription'] ?? ( $invoice['parent']['subscription_details']['subscription'] ?? '' );
		if ( is_array( $sub ) ) {
			$sub = $sub['id'] ?? '';
		}
		$sub = (string) $sub;
		return preg_match( '/^sub_[A-Za-z0-9_]+$/', $sub ) ? $sub : '';
	}

	/**
	 * Handle checkout.session.* events by re-fetching the session from Stripe.
	 *
	 * @param string $event_type Event type.
	 * @param array  $payload    Session object from the event (only its id is trusted).
	 * @param string $mode       Mode.
	 * @return array{user_id?: int}
	 * @throws \Exception On verification failure.
	 */
	protected static function handle_checkout_session_event( string $event_type, array $payload, string $mode ): array {
		$session_id = (string) ( $payload['id'] ?? '' );
		$res        = StripeService::get_checkout_session( $session_id, $mode );
		if ( ! $res['success'] || empty( $res['data'] ) ) {
			throw new \Exception( 'Unable to retrieve Checkout Session from Stripe: ' . ( $res['code'] ?? 'unknown' ) );
		}
		$session = $res['data'];

		if ( ( ! empty( $session['livemode'] ) ) !== ( Constants::STRIPE_MODE_LIVE === $mode ) ) {
			throw new \Exception( 'Checkout Session mode mismatch.' );
		}
		if ( 'subscription' !== ( $session['mode'] ?? '' ) ) {
			return array(); // Not a membership checkout.
		}

		$user_id  = (int) ( $session['client_reference_id'] ?? 0 );
		$meta_uid = (int) ( $session['metadata']['wp_user_id'] ?? 0 );
		if ( $user_id <= 0 || $user_id !== $meta_uid || ! get_userdata( $user_id ) ) {
			throw new \Exception( 'Checkout Session owner could not be verified.' );
		}

		$customer_id = is_array( $session['customer'] ?? null ) ? (string) ( $session['customer']['id'] ?? '' ) : (string) ( $session['customer'] ?? '' );
		$stored      = StripeService::get_customer_id( $user_id, $mode );
		if ( empty( $stored ) || $stored !== $customer_id ) {
			throw new \Exception( 'Checkout Session customer does not match the owner\'s Stripe customer for this mode.' );
		}

		// Clear the pending attempt if it refers to this session (complete / expired / failed).
		$pending_key = CheckoutService::get_pending_session_meta_key( $mode );
		$pending     = get_user_meta( $user_id, $pending_key, true );
		if ( is_array( $pending ) && ( $pending['session_id'] ?? '' ) === $session_id && in_array( (string) ( $session['status'] ?? '' ), array( 'complete', 'expired' ), true ) ) {
			delete_user_meta( $user_id, $pending_key );
			if ( Constants::STRIPE_MODE_TEST === $mode ) {
				delete_user_meta( $user_id, CheckoutService::META_PENDING_SESSION );
			}
		}

		if ( 'checkout.session.expired' === $event_type ) {
			AuditLogger::log( 'stripe_checkout_session_expired', 'checkout_session', $user_id, array( 'session_id' => $session_id, 'mode' => $mode ), $user_id );
			return array( 'user_id' => $user_id );
		}

		if ( 'checkout.session.async_payment_failed' === $event_type ) {
			self::enqueue_notification(
				$user_id,
				__( 'Payment Failed', 'fraud-tenant-core' ),
				__( 'Your membership payment could not be completed. No paid features were activated. You can try again from the Membership page.', 'fraud-tenant-core' ),
				'error',
				home_url( '/owner-dashboard/?ft_tab=membership' )
			);
		}

		$sub_id = $session['subscription'] ?? '';
		if ( is_array( $sub_id ) ) {
			$sub_id = $sub_id['id'] ?? '';
		}
		if ( '' === (string) $sub_id ) {
			// Session not (yet) bound to a subscription — nothing to entitle.
			return array( 'user_id' => $user_id );
		}

		return self::sync_subscription( (string) $sub_id, $mode, $event_type );
	}

	/**
	 * Synchronize the local subscription record from Stripe's authoritative state.
	 *
	 * @param string $subscription_id Stripe subscription id.
	 * @param string $mode            Mode.
	 * @param string $source          Triggering event type (audit only).
	 * @return array{user_id: int, status: string}
	 * @throws \Exception On any verification failure (fail closed, Stripe will retry).
	 */
	public static function sync_subscription( string $subscription_id, string $mode, string $source = 'manual' ): array {
		global $wpdb;

		$res = StripeService::get_subscription( $subscription_id, $mode );
		if ( ! $res['success'] || empty( $res['data']['id'] ) ) {
			throw new \Exception( 'Unable to retrieve Subscription from Stripe: ' . ( $res['code'] ?? 'unknown' ) );
		}
		$sub = $res['data'];

		if ( ( ! empty( $sub['livemode'] ) ) !== ( Constants::STRIPE_MODE_LIVE === $mode ) ) {
			throw new \Exception( 'Subscription mode mismatch.' );
		}

		$items = $sub['items']['data'] ?? array();
		if ( ! is_array( $items ) || 1 !== count( $items ) ) {
			throw new \Exception( 'Subscription must contain exactly one item.' );
		}
		$item  = $items[0];
		$price = is_array( $item['price'] ?? null ) ? $item['price'] : array();
		if ( 1 !== (int) ( $item['quantity'] ?? 1 ) ) {
			throw new \Exception( 'Unexpected subscription quantity.' );
		}

		$plan = MembershipService::get_plan_by_price_id( (string) ( $price['id'] ?? '' ), $mode );
		if ( ! $plan ) {
			throw new \Exception( 'Subscription Price is not mapped to a membership plan in this mode.' );
		}
		$val = StripeService::validate_price_against_plan( $plan, $price, $mode );
		if ( empty( $val['valid'] ) ) {
			throw new \Exception( 'Subscription Price failed plan validation: ' . ( $val['code'] ?? 'invalid' ) );
		}

		$customer_id = is_array( $sub['customer'] ?? null ) ? (string) ( $sub['customer']['id'] ?? '' ) : (string) ( $sub['customer'] ?? '' );
		$sub_table   = $wpdb->prefix . Constants::TABLE_SUBSCRIPTIONS;
		$known       = $wpdb->get_row( $wpdb->prepare( "SELECT id, user_id FROM {$sub_table} WHERE stripe_subscription_id = %s ORDER BY id DESC LIMIT 1", $subscription_id ) );
		$meta_uid    = (int) ( $sub['metadata']['wp_user_id'] ?? 0 );

		if ( $known ) {
			$user_id = (int) $known->user_id;
			if ( $meta_uid > 0 && $meta_uid !== $user_id ) {
				throw new \Exception( 'Subscription owner metadata conflicts with the local record.' );
			}
		} else {
			$user_id = $meta_uid;
		}

		$user = $user_id > 0 ? get_userdata( $user_id ) : false;
		if ( ! $user || ! in_array( Constants::ROLE_OWNER, (array) $user->roles, true ) ) {
			throw new \Exception( 'Subscription owner could not be verified as a property owner.' );
		}

		$stored_customer = StripeService::get_customer_id( $user_id, $mode );
		if ( empty( $stored_customer ) || $stored_customer !== $customer_id ) {
			throw new \Exception( 'Subscription customer does not match the owner\'s Stripe customer for this mode.' );
		}

		$status = sanitize_key( (string) ( $sub['status'] ?? '' ) );
		if ( ! in_array( $status, self::KNOWN_SUB_STATUSES, true ) ) {
			throw new \Exception( 'Unknown subscription status.' );
		}

		// Period fields: root-level for the pinned API version, item-level for newer versions.
		$ps_ts = (int) ( $sub['current_period_start'] ?? ( $item['current_period_start'] ?? 0 ) );
		$pe_ts = (int) ( $sub['current_period_end'] ?? ( $item['current_period_end'] ?? 0 ) );
		$period_start = $ps_ts > 0 ? gmdate( 'Y-m-d H:i:s', $ps_ts ) : null;
		$period_end   = $pe_ts > 0 ? gmdate( 'Y-m-d H:i:s', $pe_ts ) : null; // NULL fails closed.
		$cancel_flag  = ! empty( $sub['cancel_at_period_end'] ) ? 1 : 0;
		$now          = current_time( 'mysql' );

		// Serialize per owner: lock the user row, then the subscription row if it exists.
		$wpdb->query( 'START TRANSACTION' );
		try {
			$wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE ID = %d FOR UPDATE", $user_id ) );
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$sub_table} WHERE stripe_subscription_id = %s ORDER BY id DESC LIMIT 1 FOR UPDATE", $subscription_id ) );

			$data = array(
				'plan_id'              => (int) $plan->id,
				'stripe_customer_id'   => $customer_id,
				'status'               => $status,
				'current_period_start' => $period_start,
				'current_period_end'   => $period_end,
				'cancel_at_period_end' => $cancel_flag,
				'livemode'             => Constants::STRIPE_MODE_LIVE === $mode ? 1 : 0,
				'updated_at'           => $now,
			);

			if ( $row ) {
				$ok        = $wpdb->update( $sub_table, $data, array( 'id' => (int) $row->id ) );
				$sub_db_id = (int) $row->id;
				$prev      = array(
					'status' => (string) $row->status,
					'cancel' => (int) $row->cancel_at_period_end,
				);
			} else {
				$data['user_id']                = $user_id;
				$data['stripe_subscription_id'] = $subscription_id;
				$data['created_at']             = $now;
				$ok                             = $wpdb->insert( $sub_table, $data );
				$sub_db_id                      = (int) $wpdb->insert_id;
				$prev                           = array( 'status' => '', 'cancel' => 0 );
			}

			if ( false === $ok ) {
				throw new \Exception( 'Database write failed while synchronizing subscription.' );
			}
			$wpdb->query( 'COMMIT' );
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			throw $e;
		}

		self::notify_transition( $user_id, $prev, $status, $cancel_flag, $period_end, (string) $plan->name );

		AuditLogger::log(
			'stripe_subscription_synced',
			'subscription',
			$sub_db_id,
			array(
				'source_event'    => $source,
				'previous_status' => $prev['status'],
				'status'          => $status,
				'plan_code'       => (string) $plan->plan_code,
				'cancel_at_period_end' => $cancel_flag,
				'mode'            => $mode,
			),
			$user_id
		);

		return array( 'user_id' => $user_id, 'status' => $status );
	}

	/**
	 * Owner notifications on meaningful subscription transitions.
	 *
	 * @param int         $user_id     Owner.
	 * @param array       $prev        Previous status/cancel flag.
	 * @param string      $status      New status.
	 * @param int         $cancel_flag New cancel_at_period_end.
	 * @param string|null $period_end  Period end (UTC).
	 * @param string      $plan_name   Plan name.
	 */
	protected static function notify_transition( int $user_id, array $prev, string $status, int $cancel_flag, ?string $period_end, string $plan_name ): void {
		$url = home_url( '/owner-dashboard/?ft_tab=membership' );
		if ( 'active' === $status && 'active' !== $prev['status'] ) {
			/* translators: %s: plan name */
			self::enqueue_notification( $user_id, __( 'Membership Activated', 'fraud-tenant-core' ), sprintf( __( 'Your %s membership is active. Standard features are now available on your account.', 'fraud-tenant-core' ), $plan_name ), 'success', $url );
		} elseif ( in_array( $status, array( 'past_due', 'unpaid' ), true ) && $status !== $prev['status'] ) {
			self::enqueue_notification( $user_id, __( 'Payment Failed', 'fraud-tenant-core' ), __( 'A membership payment failed. Standard features are paused until the payment method is updated in Manage Billing.', 'fraud-tenant-core' ), 'error', $url );
		} elseif ( in_array( $status, array( 'canceled', 'incomplete_expired' ), true ) && $status !== $prev['status'] && '' !== $prev['status'] ) {
			self::enqueue_notification( $user_id, __( 'Subscription Ended', 'fraud-tenant-core' ), __( 'Your paid membership has ended and your account is on the Free plan. Existing properties are kept.', 'fraud-tenant-core' ), 'info', $url );
		}
		if ( 'active' === $status && 1 === $cancel_flag && 0 === (int) $prev['cancel'] ) {
			$when = $period_end ? mysql2date( 'M j, Y', $period_end ) : '';
			/* translators: %s: date */
			self::enqueue_notification( $user_id, __( 'Cancellation Scheduled', 'fraud-tenant-core' ), sprintf( __( 'Your membership will not renew. Standard features remain available until %s.', 'fraud-tenant-core' ), $when ), 'warning', $url );
		}
	}

	/**
	 * Enqueue an in-app notification record.
	 *
	 * @param int    $user_id    Recipient.
	 * @param string $title      Title.
	 * @param string $message    Message.
	 * @param string $type       info|success|warning|error.
	 * @param string $action_url Optional URL.
	 */
	protected static function enqueue_notification( int $user_id, string $title, string $message, string $type = 'info', string $action_url = '' ): void {
		global $wpdb;
		if ( $user_id <= 0 ) {
			return;
		}
		$wpdb->insert(
			$wpdb->prefix . Constants::TABLE_NOTIFICATIONS,
			array(
				'user_id'    => $user_id,
				'title'      => sanitize_text_field( $title ),
				'message'    => sanitize_textarea_field( $message ),
				'type'       => sanitize_key( $type ),
				'action_url' => esc_url_raw( $action_url ),
				'is_read'    => 0,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%s' )
		);
	}
}
