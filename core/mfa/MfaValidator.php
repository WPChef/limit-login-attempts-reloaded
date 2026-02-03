<?php

namespace LLAR\Core\Mfa;

use LLAR\Core\MfaConstants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MFA validation: capability, block reason (availability), input validation.
 * Single place for "can enable MFA" and rescue hash_id checks.
 */
class MfaValidator {

	/**
	 * Whether current user can manage MFA. Multisite: super_admin; else: manage_options.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage() {
		if ( is_multisite() ) {
			return is_super_admin();
		}
		return current_user_can( 'manage_options' );
	}

	/**
	 * Reason why MFA cannot be enabled, or null if it can.
	 * Requires OpenSSL (no base64 fallback — do not enable without proper encryption).
	 * SSL is not required but recommended; a warning is shown when MFA is used without HTTPS.
	 * Rescue endpoint rate limit uses global cooldown (no salt required).
	 *
	 * @return string|null One of MfaConstants::MFA_BLOCK_REASON_* or null
	 */
	public static function get_block_reason() {
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
			return __( 'OpenSSL is required for secure rescue links. Enable the OpenSSL PHP extension. 2FA cannot be enabled without proper encryption.', 'limit-login-attempts-reloaded' );
		}
		return __( '2FA cannot be enabled.', 'limit-login-attempts-reloaded' );
	}

	/**
	 * Validate and normalize rescue hash_id from URL. Length 64, hex only.
	 *
	 * @param string $hash_id Raw hash_id from query.
	 * @return string|false Sanitized hash_id or false if invalid.
	 */
	public static function validate_rescue_hash_id( $hash_id ) {
		$hash_id = is_string( $hash_id ) ? sanitize_text_field( $hash_id ) : '';
		$len     = MfaConstants::CODE_LENGTH;
		if ( $len !== strlen( $hash_id ) || ! preg_match( '/^[a-f0-9]{' . $len . '}$/i', $hash_id ) ) {
			return false;
		}
		return $hash_id;
	}
}
