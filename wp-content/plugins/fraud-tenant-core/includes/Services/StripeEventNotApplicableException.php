<?php
/**
 * Non-retryable Stripe webhook outcome.
 *
 * @package FraudTenant\Services
 */

namespace FraudTenant\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thrown when a signature-verified Stripe event refers to an object (Checkout Session or
 * Subscription) that cannot belong to any owner on this site: a session created by another
 * environment that shares the Stripe account, an owner account that no longer exists, or a
 * customer that is not the owner's customer for this mode.
 *
 * Nothing is granted or changed. Webhook delivery records the event as "ignored" and
 * acknowledges it (2xx) so Stripe stops retrying something no retry can fix; a manual
 * "Sync from Stripe" or the reconciliation job still reports it as an error.
 */
class StripeEventNotApplicableException extends \Exception {
}
