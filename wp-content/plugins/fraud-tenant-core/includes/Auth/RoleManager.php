<?php
/**
 * Role and Capability Management.
 *
 * @package FraudTenant\Auth
 */

namespace FraudTenant\Auth;

use FraudTenant\Utils\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RoleManager {

	/**
	 * Register custom roles and assign capabilities.
	 * Called during activation and schema updates.
	 */
	public static function register_roles(): void {
		// 1. Property Owner Role
		$owner = get_role( Constants::ROLE_OWNER );
		if ( ! $owner ) {
			add_role(
				Constants::ROLE_OWNER,
				__( 'Property Owner', 'fraud-tenant-core' ),
				Capabilities::get_owner_caps()
			);
		} else {
			foreach ( Capabilities::get_owner_caps() as $cap => $grant ) {
				$owner->add_cap( $cap, $grant );
			}
		}

		// 2. Tenant Role
		$tenant = get_role( Constants::ROLE_TENANT );
		if ( ! $tenant ) {
			add_role(
				Constants::ROLE_TENANT,
				__( 'Tenant', 'fraud-tenant-core' ),
				Capabilities::get_tenant_caps()
			);
		} else {
			foreach ( Capabilities::get_tenant_caps() as $cap => $grant ) {
				$tenant->add_cap( $cap, $grant );
			}
		}

		// 3. Grant administrative capabilities to Administrator role
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( Capabilities::get_admin_caps() as $cap ) {
				$admin->add_cap( $cap, true );
			}
		}
	}

	/**
	 * Remove custom capabilities from administrator role on full plugin uninstall.
	 * Custom roles are preserved on routine deactivation to avoid breaking user accounts.
	 */
	public static function remove_capabilities(): void {
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( Capabilities::get_admin_caps() as $cap ) {
				$admin->remove_cap( $cap );
			}
		}
	}
}

