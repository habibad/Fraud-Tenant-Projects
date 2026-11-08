<?php
/**
 * Stripe Settings — reuses the existing dual-mode credential screen (encrypted storage, masking,
 * connection tests, plan price mappings). Secrets are never echoed back.
 *
 * @package FraudTenant\Admin
 */

defined( 'ABSPATH' ) || exit;

\FraudTenant\Admin\StripeSettingsAdminController::render();
