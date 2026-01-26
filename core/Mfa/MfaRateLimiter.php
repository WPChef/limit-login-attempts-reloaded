<?php

namespace LLAR\Core\Mfa;

use LLAR\Core\MfaConstants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MFA Rate Limiter
 * Handles rate limiting for rescue code verification attempts
 */
class MfaRateLimiter {
	/**
	 * Check if IP is rate limited
	 *
	 * @param string $client_ip Client IP address
	 * @return bool True if rate limited
	 */
	public function is_rate_limited( $client_ip ) {
		$salt_for_rate_limit = defined( 'AUTH_SALT' ) ? AUTH_SALT : ( defined( 'NONCE_SALT' ) ? NONCE_SALT : wp_generate_password( 64, true ) );
		$transient_key       = MfaConstants::TRANSIENT_ATTEMPTS_PREFIX . hash( 'sha256', $client_ip . $salt_for_rate_limit );
		$attempts            = get_transient( $transient_key );
		$attempts            = ( false !== $attempts ) ? $attempts : 0;

		return $attempts >= MfaConstants::MAX_ATTEMPTS;
	}

	/**
	 * Increment attempt counter
	 *
	 * @param string $client_ip Client IP address
	 */
	public function increment_attempts( $client_ip ) {
		$salt_for_rate_limit = defined( 'AUTH_SALT' ) ? AUTH_SALT : ( defined( 'NONCE_SALT' ) ? NONCE_SALT : wp_generate_password( 64, true ) );
		$transient_key       = MfaConstants::TRANSIENT_ATTEMPTS_PREFIX . hash( 'sha256', $client_ip . $salt_for_rate_limit );
		$attempts            = get_transient( $transient_key );
		$attempts            = ( false !== $attempts ) ? $attempts : 0;

		// Increment counter on each check (even invalid)
		set_transient( $transient_key, $attempts + 1, MfaConstants::RATE_LIMIT_PERIOD );
	}
}
