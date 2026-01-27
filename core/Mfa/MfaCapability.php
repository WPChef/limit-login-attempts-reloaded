<?php

namespace LLAR\Core\Mfa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MFA capability check: single place for "can manage MFA" logic.
 */
class MfaCapability {

	/**
	 * Whether the current user can manage MFA settings.
	 * Multisite: super_admin; else: manage_options.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage() {
		if ( is_multisite() ) {
			return is_super_admin();
		}
		return current_user_can( 'manage_options' );
	}
}
