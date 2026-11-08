<?php
/**
 * Atomic Checkout Concurrency Lock Service.
 *
 * Implements genuine atomic owner-level serialization for Stripe Checkout
 * session initiation using dedicated database unique constraints, cryptographically
 * random lock ownership tokens, safe stale-lock recovery, bounded expiration,
 * and token-bound lock releases.
 *
 * @package FraudTenant\Services
 */

namespace FraudTenant\Services;

use FraudTenant\Utils\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CheckoutLockService {

	/**
	 * Default lock TTL in seconds (bounded duration).
	 */
	public const DEFAULT_TTL = 30;

	/**
	 * Acquire an atomic owner-level checkout lock.
	 *
	 * Uses an atomic INSERT ... ON DUPLICATE KEY UPDATE query with conditional expiry takeover.
	 * If the owner already has an active, unexpired lock held by another process, acquisition fails
	 * and returns status 429. If the existing lock has expired (stale), it is atomically taken over.
	 *
	 * @param int    $owner_id    WordPress user ID (property owner).
	 * @param string $plan_code   Membership plan code being purchased.
	 * @param int    $ttl_seconds Bounded expiration in seconds (default: 30).
	 * @return array{success: bool, lock_token?: string, attempt_id?: string, error?: string, status?: int}
	 */
	public static function acquire_lock( int $owner_id, string $plan_code, int $ttl_seconds = self::DEFAULT_TTL ): array {
		global $wpdb;

		if ( $owner_id <= 0 ) {
			return array(
				'success' => false,
				'error'   => __( 'Invalid owner ID for checkout lock.', 'fraud-tenant-core' ),
				'status'  => 400,
			);
		}

		$table = $wpdb->prefix . Constants::TABLE_CHECKOUT_LOCKS;

		// Generate cryptographically random lock ownership token and logical attempt ID
		try {
			$lock_token = bin2hex( random_bytes( 24 ) );
			$attempt_id = 'att_' . bin2hex( random_bytes( 16 ) );
		} catch ( \Exception $e ) {
			$lock_token = wp_generate_password( 48, false );
			$attempt_id = 'att_' . wp_generate_password( 32, false );
		}

		// All lock timestamps are UTC on both sides of every comparison. Mixing site-local
		// time with UTC would make every lock appear expired on non-UTC sites.
		$now_ts     = time();
		$now        = gmdate( 'Y-m-d H:i:s', $now_ts );
		$expires_at = gmdate( 'Y-m-d H:i:s', $now_ts + max( 1, $ttl_seconds ) );

		// Atomic conditional acquire:
		// If no row exists: inserts new lock row.
		// If row exists: takes over lock ONLY IF expires_at < $now (stale lock recovery).
		$sql = $wpdb->prepare(
			"INSERT INTO {$table} (owner_id, lock_token, plan_code, attempt_id, expires_at, created_at, updated_at)
			VALUES (%d, %s, %s, %s, %s, %s, %s)
			ON DUPLICATE KEY UPDATE
				lock_token = IF(expires_at < %s, VALUES(lock_token), lock_token),
				plan_code  = IF(expires_at < %s, VALUES(plan_code), plan_code),
				attempt_id = IF(expires_at < %s, VALUES(attempt_id), attempt_id),
				created_at = IF(expires_at < %s, VALUES(created_at), created_at),
				updated_at = IF(expires_at < %s, VALUES(updated_at), updated_at),
				expires_at = IF(expires_at < %s, VALUES(expires_at), expires_at)",
			$owner_id,
			$lock_token,
			$plan_code,
			$attempt_id,
			$expires_at,
			$now,
			$now,
			$now,
			$now,
			$now,
			$now,
			$now,
			$now
		);

		$wpdb->query( $sql );

		// Inspect whether this request actually owns the lock token
		$current_lock = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT lock_token, attempt_id, expires_at FROM {$table} WHERE owner_id = %d",
				$owner_id
			)
		);

		if ( ! $current_lock || $current_lock->lock_token !== $lock_token ) {
			return array(
				'success' => false,
				'error'   => __( 'A checkout session creation is already in progress for your account. Please wait a moment.', 'fraud-tenant-core' ),
				'status'  => 429,
			);
		}

		return array(
			'success'    => true,
			'lock_token' => $lock_token,
			'attempt_id' => $attempt_id,
		);
	}

	/**
	 * Release an owner checkout lock.
	 *
	 * Strictly enforces token-bound ownership: only deletes the lock row if lock_token
	 * matches the token returned during acquisition. No request may release another
	 * request's lock.
	 *
	 * @param int    $owner_id   WordPress user ID.
	 * @param string $lock_token Lock token provided during acquisition.
	 * @return bool True if lock was released, false if token did not match or lock not found.
	 */
	public static function release_lock( int $owner_id, string $lock_token ): bool {
		global $wpdb;

		if ( $owner_id <= 0 || empty( $lock_token ) ) {
			return false;
		}

		$table = $wpdb->prefix . Constants::TABLE_CHECKOUT_LOCKS;

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE owner_id = %d AND lock_token = %s",
				$owner_id,
				$lock_token
			)
		);

		return ( false !== $deleted && $deleted > 0 );
	}

	/**
	 * Check if an owner has an active, unexpired lock.
	 *
	 * @param int $owner_id WordPress user ID.
	 * @return bool
	 */
	public static function is_locked( int $owner_id ): bool {
		global $wpdb;

		if ( $owner_id <= 0 ) {
			return false;
		}

		$table = $wpdb->prefix . Constants::TABLE_CHECKOUT_LOCKS;
		$now   = gmdate( 'Y-m-d H:i:s' );

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$table} WHERE owner_id = %d AND expires_at >= %s LIMIT 1",
				$owner_id,
				$now
			)
		);

		return ! empty( $exists );
	}

	/**
	 * Retrieve current lock details for an owner.
	 *
	 * @param int $owner_id WordPress user ID.
	 * @return object|null Lock row or null if none.
	 */
	public static function get_lock( int $owner_id ): ?object {
		global $wpdb;

		if ( $owner_id <= 0 ) {
			return null;
		}

		$table = $wpdb->prefix . Constants::TABLE_CHECKOUT_LOCKS;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE owner_id = %d",
				$owner_id
			)
		);

		return $row ?: null;
	}

	/**
	 * Force release any lock for an owner (administrative / test harness cleanup).
	 *
	 * @param int $owner_id WordPress user ID.
	 * @return bool
	 */
	public static function force_release_lock( int $owner_id ): bool {
		global $wpdb;

		if ( $owner_id <= 0 ) {
			return false;
		}

		$table   = $wpdb->prefix . Constants::TABLE_CHECKOUT_LOCKS;
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE owner_id = %d",
				$owner_id
			)
		);

		return false !== $deleted;
	}
}

