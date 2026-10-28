<?php
/**
 * Autoloader for Fraud Tenant Core Plugin.
 *
 * @package FraudTenant
 */

namespace FraudTenant;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Autoloader {

	/**
	 * Register the autoloader.
	 */
	public static function register(): void {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Autoload classes based on namespace prefix.
	 *
	 * @param string $class Fully qualified class name.
	 */
	public static function autoload( string $class ): void {
		$prefix = 'FraudTenant\\';
		$base_dir = dirname( __FILE__ ) . '/';

		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class, $len );
		$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}

