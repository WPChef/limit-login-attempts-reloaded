<?php

namespace LLAR\Core\Mfa;

use LLAR\Core\LimitLoginAttempts;
use LLAR\Core\MfaConstants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MFA Business Rules
 * Centralized business logic for MFA functionality
 */
class MfaRules {
	/**
	 * Check if rescue popup should be shown
	 *
	 * @param array $codes Array of rescue codes
	 * @return bool True if popup should be shown
	 */
	public static function should_show_rescue_popup( $codes ) {
		if ( ! is_array( $codes ) ) {
			$codes = array();
		}

		// Show popup if no codes exist
		if ( empty( $codes ) ) {
			return true;
		}

		// Check if all codes are used
		return self::all_codes_used( $codes );
	}

	/**
	 * Check if all codes are used
	 *
	 * @param array $codes Array of rescue codes
	 * @return bool True if all codes are used
	 */
	private static function all_codes_used( $codes ) {
		foreach ( $codes as $code_data ) {
			$rescue_code = RescueCode::from_array( $code_data );
			if ( ! $rescue_code->is_used() ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Check if role is admin role
	 * Uses LimitLoginAttempts::is_admin_role() for consistency
	 *
	 * @param string $role_key Role key
	 * @return bool True if role is admin
	 */
	public static function is_admin_role( $role_key ) {
		return LimitLoginAttempts::is_admin_role( $role_key );
	}

	/**
	 * Check if role has admin capability
	 *
	 * @param string $role_key Role key
	 * @param string $capability Capability to check (default: manage_options)
	 * @return bool True if role has capability
	 */
	public static function role_has_capability( $role_key, $capability = 'manage_options' ) {
		$role = get_role( $role_key );
		return $role && $role->has_cap( $capability );
	}
}
