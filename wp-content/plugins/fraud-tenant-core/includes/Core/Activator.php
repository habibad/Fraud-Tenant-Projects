<?php
/**
 * Plugin Activation Handler.
 *
 * @package FraudTenant\Core
 */

namespace FraudTenant\Core;

use FraudTenant\Auth\RoleManager;
use FraudTenant\Database\MigrationManager;
use FraudTenant\Services\AuditLogger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Activator {

	/**
	 * Run activation tasks.
	 */
	public static function activate(): void {
		// 1. Run database migrations and seed default plans
		MigrationManager::migrate( true );

		// 2. Register custom roles and assign capabilities
		RoleManager::register_roles();

		// 3. Log activation event in audit trail
		AuditLogger::log(
			'plugin_activated',
			'system',
			null,
			array( 'version' => \FraudTenant\Utils\Constants::VERSION )
		);

		// 4. Flush rewrite rules cleanly
		flush_rewrite_rules();
	}
}

