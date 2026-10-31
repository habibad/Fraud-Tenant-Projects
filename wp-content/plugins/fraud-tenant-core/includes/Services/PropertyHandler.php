<?php
/**
 * Web Form Request Handler for Property Operations.
 *
 * @package FraudTenant\Services
 */

namespace FraudTenant\Services;

use FraudTenant\Auth\AuthHandler;
use FraudTenant\Services\PropertyService;
use FraudTenant\Utils\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PropertyHandler {

	/**
	 * Register web post hooks for property actions.
	 */
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'register_rewrites' ) );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );

		add_action( 'admin_post_ft_add_property', array( __CLASS__, 'handle_add_property' ) );
		add_action( 'admin_post_ft_edit_property', array( __CLASS__, 'handle_edit_property' ) );
		add_action( 'admin_post_ft_archive_property', array( __CLASS__, 'handle_archive_property' ) );
		add_action( 'admin_post_ft_unarchive_property', array( __CLASS__, 'handle_unarchive_property' ) );
		add_action( 'admin_post_ft_upload_property_doc', array( __CLASS__, 'handle_upload_doc' ) );
	}

	/**
	 * Register clean URL rewrite rules for the owner properties views.
	 */
	public static function register_rewrites(): void {
		add_rewrite_rule( '^owner-dashboard/properties/?$', 'index.php?pagename=owner-dashboard&ft_tab=properties', 'top' );
		add_rewrite_rule( '^owner-dashboard/properties/([0-9]+)/?$', 'index.php?pagename=owner-dashboard&ft_tab=properties&ft_property_id=$matches[1]', 'top' );
	}

	/**
	 * Register custom query variables.
	 *
	 * @param array $vars Array of registered query variables.
	 * @return array
	 */
	public static function register_query_vars( array $vars ): array {
		$vars[] = 'ft_tab';
		$vars[] = 'ft_property_id';
		return $vars;
	}

	/**
	 * Handle Add Property POST.
	 */
	public static function handle_add_property(): void {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( home_url( '/login/' ) );
			exit;
		}

		$user_id = get_current_user_id();

		$nonce = isset( $_POST['ft_property_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ft_property_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ft_add_property_action' ) ) {
			wp_die( esc_html__( 'Security check failed. Please refresh the page and try again.', 'fraud-tenant-core' ), 403 );
		}

		$doc_file = isset( $_FILES['property_document'] ) && is_array( $_FILES['property_document'] ) ? $_FILES['property_document'] : null;
		$result   = PropertyService::create_property( $user_id, $_POST, $doc_file );

		$fallback = home_url( '/owner-dashboard/' );
		$redirect = isset( $_POST['redirect_to'] ) ? wp_validate_redirect( wp_sanitize_redirect( $_POST['redirect_to'] ), $fallback ) : $fallback;

		if ( $result['success'] ) {
			AuthHandler::set_flash_notice( 'success', $result['message'] );
		} else {
			AuthHandler::set_flash_notice( 'error', $result['message'] );
		}

		wp_safe_redirect( $redirect );
		if ( ! defined( 'FT_DOING_TESTS' ) ) {
			exit;
		}
	}

	/**
	 * Handle Edit Property POST.
	 */
	public static function handle_edit_property(): void {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( home_url( '/login/' ) );
			exit;
		}

		$user_id     = get_current_user_id();
		$property_id = isset( $_POST['property_id'] ) ? (int) $_POST['property_id'] : 0;
		$nonce       = isset( $_POST['ft_property_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ft_property_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'ft_edit_property_' . $property_id ) ) {
			wp_die( esc_html__( 'Security check failed. Please refresh the page and try again.', 'fraud-tenant-core' ), 403 );
		}

		$result   = PropertyService::update_property( $property_id, $user_id, $_POST );
		$fallback = add_query_arg(
			array(
				'tab'         => 'properties',
				'property_id' => $property_id,
			),
			home_url( '/owner-dashboard/' )
		);
		$redirect = isset( $_POST['redirect_to'] ) ? wp_validate_redirect( wp_sanitize_redirect( $_POST['redirect_to'] ), $fallback ) : $fallback;

		if ( $result['success'] ) {
			AuthHandler::set_flash_notice( 'success', $result['message'] );
		} else {
			AuthHandler::set_flash_notice( 'error', $result['message'] );
		}

		wp_safe_redirect( $redirect );
		if ( ! defined( 'FT_DOING_TESTS' ) ) {
			exit;
		}
	}

	/**
	 * Handle Archive Property POST.
	 */
	public static function handle_archive_property(): void {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( home_url( '/login/' ) );
			exit;
		}

		$user_id     = get_current_user_id();
		$property_id = isset( $_POST['property_id'] ) ? (int) $_POST['property_id'] : 0;
		$nonce       = isset( $_POST['ft_archive_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ft_archive_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'ft_archive_prop_' . $property_id ) ) {
			wp_die( esc_html__( 'Security check failed. Please refresh the page and try again.', 'fraud-tenant-core' ), 403 );
		}

		$result   = PropertyService::archive_property( $property_id, $user_id );
		$fallback = add_query_arg( 'tab', 'properties', home_url( '/owner-dashboard/' ) );
		$redirect = isset( $_POST['redirect_to'] ) ? wp_validate_redirect( wp_sanitize_redirect( $_POST['redirect_to'] ), $fallback ) : $fallback;

		if ( $result['success'] ) {
			AuthHandler::set_flash_notice( 'success', $result['message'] );
		} else {
			AuthHandler::set_flash_notice( 'error', $result['message'] );
		}

		wp_safe_redirect( $redirect );
		if ( ! defined( 'FT_DOING_TESTS' ) ) {
			exit;
		}
	}

	/**
	 * Handle Unarchive / Restore Property POST.
	 */
	public static function handle_unarchive_property(): void {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( home_url( '/login/' ) );
			exit;
		}

		$user_id     = get_current_user_id();
		$property_id = isset( $_POST['property_id'] ) ? (int) $_POST['property_id'] : 0;
		$nonce       = isset( $_POST['ft_unarchive_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ft_unarchive_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'ft_unarchive_prop_' . $property_id ) ) {
			wp_die( esc_html__( 'Security check failed. Please refresh the page and try again.', 'fraud-tenant-core' ), 403 );
		}

		$result   = PropertyService::unarchive_property( $property_id, $user_id );
		$fallback = add_query_arg( 'tab', 'properties', home_url( '/owner-dashboard/' ) );
		$redirect = isset( $_POST['redirect_to'] ) ? wp_validate_redirect( wp_sanitize_redirect( $_POST['redirect_to'] ), $fallback ) : $fallback;

		if ( $result['success'] ) {
			AuthHandler::set_flash_notice( 'success', $result['message'] );
		} else {
			AuthHandler::set_flash_notice( 'error', $result['message'] );
		}

		wp_safe_redirect( $redirect );
		if ( ! defined( 'FT_DOING_TESTS' ) ) {
			exit;
		}
	}

	/**
	 * Handle Ownership Document Upload POST.
	 */
	public static function handle_upload_doc(): void {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( home_url( '/login/' ) );
			exit;
		}

		$user_id     = get_current_user_id();
		$property_id = isset( $_POST['property_id'] ) ? (int) $_POST['property_id'] : 0;
		$nonce       = isset( $_POST['ft_doc_upload_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ft_doc_upload_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'ft_upload_doc_' . $property_id ) ) {
			wp_die( esc_html__( 'Security check failed. Please refresh the page and try again.', 'fraud-tenant-core' ), 403 );
		}

		$file   = isset( $_FILES['ownership_document'] ) && is_array( $_FILES['ownership_document'] ) ? $_FILES['ownership_document'] : array();
		$result = PropertyService::upload_property_document( $property_id, $user_id, $file );

		$fallback = add_query_arg(
			array(
				'tab'         => 'properties',
				'property_id' => $property_id,
			),
			home_url( '/owner-dashboard/' )
		);
		$redirect = isset( $_POST['redirect_to'] ) ? wp_validate_redirect( wp_sanitize_redirect( $_POST['redirect_to'] ), $fallback ) : $fallback;

		if ( $result['success'] ) {
			AuthHandler::set_flash_notice( 'success', $result['message'] );
		} else {
			AuthHandler::set_flash_notice( 'error', $result['message'] );
		}

		wp_safe_redirect( $redirect );
		if ( ! defined( 'FT_DOING_TESTS' ) ) {
			exit;
		}
	}
}
