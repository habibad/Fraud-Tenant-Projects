<?php
/**
 * Plugin Name:       Fraud Tenant Core
 * Plugin URI:        https://fraudtenant.com
 * Description:       Core business logic, custom relational database schema, user roles, security, and REST API foundation for the Fraud Tenant platform.
 * Version:           1.0.0
 * Author:            Fraud Tenant Development Team
 * License:           GPL-2.0-or-later
 * Text Domain:       fraud-tenant-core
 * Requires at least: 6.0
 * Requires PHP:      8.2
 *
 * @package FraudTenant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define Plugin Constants
define( 'FRAUD_TENANT_CORE_VERSION', '1.0.0' );
define( 'FRAUD_TENANT_CORE_FILE', __FILE__ );
define( 'FRAUD_TENANT_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'FRAUD_TENANT_CORE_URL', plugin_dir_url( __FILE__ ) );

// Require Autoloader
require_once FRAUD_TENANT_CORE_PATH . 'includes/Autoloader.php';
\FraudTenant\Autoloader::register();

// Register Activation and Deactivation Hooks
register_activation_hook( __FILE__, array( '\\FraudTenant\\Core\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\FraudTenant\\Core\\Deactivator', 'deactivate' ) );

// Bootstrap the Plugin on plugins_loaded
add_action( 'plugins_loaded', function() {
	\FraudTenant\Core\Plugin::instance();
} );

