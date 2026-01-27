<?php

namespace LLAR\Core\Mfa;

use LLAR\Core\MfaConstants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MFA availability checks: SSL, salt, OpenSSL.
 * Single place for block reason and user-facing message.
 */
class MfaAvailability {

	/**
	 * Return reason why MFA cannot be enabled, or null if it can.
	 *
	 * @return string|null One of MfaConstants::MFA_BLOCK_REASON_* or null
	 */
	public static function get_block_reason() {
		if ( ! is_ssl() ) {
			return MfaConstants::MFA_BLOCK_REASON_SSL;
		}
		if ( null === MfaConstants::get_rate_limit_salt() ) {
			return MfaConstants::MFA_BLOCK_REASON_SALT;
		}
		if ( ! MfaConstants::is_openssl_available() ) {
			return MfaConstants::MFA_BLOCK_REASON_OPENSSL;
		}
		return null;
	}

	/**
	 * Human-readable message for a block reason.
	 *
	 * @param string $block_reason One of MfaConstants::MFA_BLOCK_REASON_*
	 * @return string
	 */
	public static function get_block_message( $block_reason ) {
		if ( MfaConstants::MFA_BLOCK_REASON_SSL === $block_reason ) {
			return __( 'SSL/HTTPS is required for 2FA functionality. Please enable SSL on your site.', 'limit-login-attempts-reloaded' );
		}
		if ( MfaConstants::MFA_BLOCK_REASON_SALT === $block_reason ) {
			return __( '2FA cannot be enabled: WordPress salt (AUTH_SALT or NONCE_SALT) or wp_salt() is required for secure rate limiting. Please define salts in wp-config.php.', 'limit-login-attempts-reloaded' );
		}
		if ( MfaConstants::MFA_BLOCK_REASON_OPENSSL === $block_reason ) {
			return __( 'OpenSSL is required for secure rescue links. Enable the OpenSSL PHP extension.', 'limit-login-attempts-reloaded' );
		}
		return __( '2FA cannot be enabled.', 'limit-login-attempts-reloaded' );
	}
}
