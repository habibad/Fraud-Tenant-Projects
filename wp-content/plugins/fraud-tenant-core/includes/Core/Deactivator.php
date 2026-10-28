<?php
/**
 * Plugin Deactivation Handler.
 *
 * @package FraudTenant\Core
 */

namespace FraudTenant\Core;

use FraudTenant\Services\AuditLogger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Deactivator {

	/**
	 * Run deactivation tasks.
	 * Note: Never drops tables or deletes user records on routine deactivation.
	 */
	public static function deactivate(): void {
		// Log deactivation event in audit trail before flushing
		AuditLogger::log(
			'plugin_deactivated',
			'system',
			null,
			array( 'version' => \FraudTenant\Utils\Constants::VERSION )
		);

		// Flush rewrite rules cleanly
		flush_rewrite_rules();
	}
}

