<?php

namespace LLAR\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MFA Constants
 * Centralized constants for MFA functionality
 */
class MfaConstants {
	/**
	 * Length of each rescue code (characters)
	 */
	const CODE_LENGTH = 64;

	/**
	 * Number of rescue codes to generate
	 */
	const CODE_COUNT = 10;

	/**
	 * Maximum rescue link verification attempts per IP per hour
	 */
	const MAX_ATTEMPTS = 5;

	/**
	 * Rescue link TTL in seconds (5 minutes)
	 */
	const RESCUE_LINK_TTL = 300;

	/**
	 * MFA temporary disable duration in seconds (1 hour)
	 */
	const MFA_DISABLE_DURATION = 3600; // HOUR_IN_SECONDS

	/**
	 * Rate limiting period for rescue attempts (1 hour)
	 */
	const RATE_LIMIT_PERIOD = 3600; // HOUR_IN_SECONDS

	/**
	 * Transient key prefix for rescue codes
	 */
	const TRANSIENT_RESCUE_PREFIX = 'llar_rescue_';

	/**
	 * Transient key prefix for rescue attempts rate limiting
	 */
	const TRANSIENT_ATTEMPTS_PREFIX = 'llar_rescue_attempts_';

	/**
	 * Transient key for MFA temporary disable
	 */
	const TRANSIENT_MFA_DISABLED = 'llar_mfa_temporarily_disabled';

	/**
	 * Transient key for MFA checkbox state
	 */
	const TRANSIENT_CHECKBOX_STATE = 'llar_mfa_checkbox_state';

	/**
	 * Checkbox state TTL in seconds (5 minutes)
	 */
	const CHECKBOX_STATE_TTL = 300;

	/**
	 * WordPress wp_salt() scheme used as fallback when AUTH_SALT/NONCE_SALT are not defined
	 */
	const WP_SALT_SCHEME_FALLBACK = 'auth';

	/**
	 * Block reason: SSL/HTTPS required
	 */
	const MFA_BLOCK_REASON_SSL = 'ssl';

	/**
	 * Block reason: deterministic salt required for rate limiting
	 */
	const MFA_BLOCK_REASON_SALT = 'salt';

	/**
	 * Whether OpenSSL is available for secure rescue code encryption.
	 * Without OpenSSL, MfaEncryptionService falls back to base64(plain_code . salt),
	 * which is not encryption and exposes the salt.
	 *
	 * @return bool True if openssl_encrypt and openssl_decrypt are available
	 */
	public static function is_openssl_available() {
		return function_exists( 'openssl_encrypt' ) && function_exists( 'openssl_decrypt' );
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
