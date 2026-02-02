<?php

namespace LLAR\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MFA Constants
 * Facade for MFA constants defined in limit-login-attempts-reloaded.php (LLA_MFA_*).
 */
class MfaConstants {
	/** @var int Length of each rescue code (characters) */
	const CODE_LENGTH = LLA_MFA_CODE_LENGTH;

	/** @var int Number of rescue codes to generate */
	const CODE_COUNT = LLA_MFA_CODE_COUNT;

	/** @var int Maximum rescue link verification attempts per IP per hour */
	const MAX_ATTEMPTS = LLA_MFA_MAX_ATTEMPTS;

	/** @var int Rescue link TTL in seconds (5 minutes) */
	const RESCUE_LINK_TTL = LLA_MFA_RESCUE_LINK_TTL;

	/** @var int MFA temporary disable duration in seconds (1 hour) */
	const MFA_DISABLE_DURATION = LLA_MFA_DISABLE_DURATION;

	/** @var int Rate limiting period for rescue attempts (1 hour). @deprecated Use RESCUE_USE_COOLDOWN for rescue endpoint. */
	const RATE_LIMIT_PERIOD = LLA_MFA_RATE_LIMIT_PERIOD;

	/** @var int Minimum seconds between two rescue endpoint uses (cooldown). Default 60 = one use per minute. */
	const RESCUE_USE_COOLDOWN = LLA_MFA_RESCUE_USE_COOLDOWN;

	/** @var string Transient key prefix for rescue codes */
	const TRANSIENT_RESCUE_PREFIX = LLA_MFA_TRANSIENT_RESCUE_PREFIX;

	/** @var string Transient key prefix for rescue attempts rate limiting. @deprecated Rescue limit is now global via TRANSIENT_RESCUE_LAST_USE. */
	const TRANSIENT_ATTEMPTS_PREFIX = LLA_MFA_TRANSIENT_ATTEMPTS_PREFIX;

	/** @var string Transient key for last rescue endpoint use (global cooldown, one use per RESCUE_USE_COOLDOWN seconds). */
	const TRANSIENT_RESCUE_LAST_USE = LLA_MFA_TRANSIENT_RESCUE_LAST_USE;

	/** @var string Transient key for MFA temporary disable */
	const TRANSIENT_MFA_DISABLED = LLA_MFA_TRANSIENT_MFA_DISABLED;

	/** @var string Transient key for MFA checkbox state */
	const TRANSIENT_CHECKBOX_STATE = LLA_MFA_TRANSIENT_CHECKBOX_STATE;

	/** @var int Checkbox state TTL in seconds (5 minutes) */
	const CHECKBOX_STATE_TTL = LLA_MFA_CHECKBOX_STATE_TTL;

	/** @var int Max PDF/rescue HTML generations per user per period (rate limit) */
	const PDF_RATE_LIMIT_MAX = LLA_MFA_PDF_RATE_LIMIT_MAX;

	/** @var int PDF generation rate limit period in seconds (1 minute) */
	const PDF_RATE_LIMIT_PERIOD = LLA_MFA_PDF_RATE_LIMIT_PERIOD;

	/** @var string WordPress wp_salt() scheme used as fallback when AUTH_SALT/NONCE_SALT are not defined */
	const WP_SALT_SCHEME_FALLBACK = LLA_MFA_WP_SALT_SCHEME_FALLBACK;

	/** @var string Block reason: SSL/HTTPS required */
	const MFA_BLOCK_REASON_SSL = LLA_MFA_BLOCK_REASON_SSL;

	/** @var string Block reason: deterministic salt required for rate limiting */
	const MFA_BLOCK_REASON_SALT = LLA_MFA_BLOCK_REASON_SALT;

	/** @var string Block reason: OpenSSL required for secure rescue links */
	const MFA_BLOCK_REASON_OPENSSL = LLA_MFA_BLOCK_REASON_OPENSSL;

	/**
	 * Whether OpenSSL is available for secure rescue code encryption.
	 * MFA must not be enabled without OpenSSL; no base64 fallback.
	 *
	 * @return bool True if openssl_encrypt and openssl_decrypt are available
	 */
	public static function is_openssl_available() {
		static $available = null;
		if ( null === $available ) {
			$available = function_exists( 'openssl_encrypt' ) && function_exists( 'openssl_decrypt' );
		}
		return $available;
	}

	/**
	 * Return deterministic salt for rate limiting, or null if unavailable.
	 * Chain: AUTH_SALT -> NONCE_SALT -> wp_salt( WP_SALT_SCHEME_FALLBACK ).
	 *
	 * @return string|null Salt string or null if none available
	 */
	public static function get_rate_limit_salt() {
		if ( defined( 'AUTH_SALT' ) && AUTH_SALT ) {
			return AUTH_SALT;
		}
		if ( defined( 'NONCE_SALT' ) && NONCE_SALT ) {
			return NONCE_SALT;
		}
		if ( function_exists( 'wp_salt' ) ) {
			$salt = wp_salt( self::WP_SALT_SCHEME_FALLBACK );
			if ( is_string( $salt ) && '' !== $salt ) {
				return $salt;
			}
		}
		return null;
	}
}
