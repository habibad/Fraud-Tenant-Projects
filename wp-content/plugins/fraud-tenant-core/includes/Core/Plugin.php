<?php
/**
 * Main Plugin Orchestrator.
 *
 * @package FraudTenant\Core
 */

namespace FraudTenant\Core;

use FraudTenant\API\RestServer;
use FraudTenant\Auth\AuthHandler;
use FraudTenant\Database\MigrationManager;
use FraudTenant\Services\ContactEnquiryHandler;
use FraudTenant\Utils\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin {

	/**
	 * Plugin singleton instance.
	 *
	 * @var Plugin|null
	 */
	protected static ?Plugin $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to prevent direct instantiation.
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Wire WordPress hooks.
	 */
	protected function init_hooks(): void {
		// Initialize REST API
		RestServer::init();

		// Register public contact enquiry handler
		ContactEnquiryHandler::register();

		// Register authentication web request handlers
		AuthHandler::register();

		// Check for database schema updates in admin context
		add_action( 'admin_init', array( $this, 'maybe_migrate' ) );
	}

	/**
	 * Run migration if the code version is newer than the database version.
	 */
	public function maybe_migrate(): void {
		$db_version = get_option( Constants::DB_VERSION_OPTION, '0.0.0' );
		if ( version_compare( $db_version, Constants::DB_VERSION, '<' ) ) {
			MigrationManager::migrate();
		}
	}
}

