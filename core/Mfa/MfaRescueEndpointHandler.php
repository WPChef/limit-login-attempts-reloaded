<?php

namespace LLAR\Core\Mfa;

use LLAR\Core\Config;
use LLAR\Core\MfaConstants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MFA Rescue Endpoint Handler
 * Handles public rescue code verification endpoint
 */
class MfaRescueEndpointHandler {
	/**
	 * Encryption service
	 *
	 * @var MfaEncryptionService
	 */
	private $encryption;

	/**
	 * Rate limiter
	 *
	 * @var MfaRateLimiter
	 */
	private $rate_limiter;

	/**
	 * Constructor
	 *
	 * @param MfaEncryptionService $encryption Encryption service
	 * @param MfaRateLimiter $rate_limiter Rate limiter
	 */
	public function __construct( MfaEncryptionService $encryption, MfaRateLimiter $rate_limiter ) {
		$this->encryption   = $encryption;
		$this->rate_limiter = $rate_limiter;
	}

	/** Generic error message to avoid revealing format or state. */
	const RESCUE_ERROR_MSG = 'Invalid or expired rescue link';

	/**
	 * Handle rescue endpoint request
	 *
	 * @param string $hash_id Hash ID from URL
	 * @return bool True if code verified and MFA disabled
	 */
	public function handle( $hash_id ) {
		$client_ip = $this->get_client_ip();

		if ( $this->rate_limiter->is_rate_limited( $client_ip ) ) {
			wp_die( 'Too many attempts. Please try again later.', 'LLAR MFA Rescue', array( 'response' => 429 ) );
		}

		$this->rate_limiter->increment_attempts( $client_ip );

		// Validate hash_id: length and format (SHA-256 = 64 hex chars). Consistent sanitization.
		$hash_id = is_string( $hash_id ) ? sanitize_text_field( $hash_id ) : '';
		if ( 64 !== strlen( $hash_id ) || ! preg_match( '/^[a-f0-9]{64}$/i', $hash_id ) ) {
			wp_die( self::RESCUE_ERROR_MSG, 'LLAR MFA Rescue', array( 'response' => 403 ) );
		}

		// Constant-time lookup: iterate all pending keys, use hash_equals (no early break)
		$pending = Config::get( 'mfa_rescue_pending_links' );
		if ( ! is_array( $pending ) ) {
			wp_die( self::RESCUE_ERROR_MSG, 'LLAR MFA Rescue', array( 'response' => 403 ) );
		}
		$matched_key = null;
		foreach ( array_keys( $pending ) as $key ) {
			if ( is_string( $key ) && hash_equals( $hash_id, $key ) ) {
				$matched_key = $key;
			}
		}
		if ( null === $matched_key ) {
			wp_die( self::RESCUE_ERROR_MSG, 'LLAR MFA Rescue', array( 'response' => 403 ) );
		}
		$encrypted_data = $pending[ $matched_key ];
		unset( $pending[ $matched_key ] );
		Config::update( 'mfa_rescue_pending_links', $pending );

		$plain_code = $this->encryption->decrypt_code( $encrypted_data );
		if ( false === $plain_code ) {
			wp_die( self::RESCUE_ERROR_MSG, 'LLAR MFA Rescue', array( 'response' => 403 ) );
		}

		$codes = Config::get( 'mfa_rescue_codes', array() );
		if ( ! is_array( $codes ) || empty( $codes ) ) {
			wp_die( self::RESCUE_ERROR_MSG, 'LLAR MFA Rescue', array( 'response' => 403 ) );
		}

		// Constant-time verification: iterate all codes, no early break. verify() uses wp_check_password (constant-time).
		$code_verified  = false;
		$verified_index = null;
		foreach ( $codes as $index => $code_data ) {
			$rescue_code = RescueCode::from_array( $code_data );
			if ( $rescue_code->is_used() ) {
				continue;
			}
			if ( $rescue_code->verify( $plain_code ) ) {
				$code_verified  = true;
				$verified_index = $index;
			}
		}

		if ( $code_verified && null !== $verified_index ) {
			$rescue_code = RescueCode::from_array( $codes[ $verified_index ] );
			$rescue_code->mark_as_used();
			$codes[ $verified_index ] = $rescue_code->to_array();
			Config::update( 'mfa_rescue_codes', $codes );
			$this->disable_mfa_temporarily();
			$login_url = add_query_arg( 'llar_mfa_disabled', '1', wp_login_url() );
			wp_safe_redirect( $login_url );
			exit;
		}

		wp_die( self::RESCUE_ERROR_MSG, 'LLAR MFA Rescue', array( 'response' => 403 ) );
	}

	/**
	 * Get client IP address
	 *
	 * @return string Client IP
	 */
	private function get_client_ip() {
		$ip_keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR' );

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				// For X-Forwarded-For take first IP
				if ( false !== strpos( $ip, ',' ) ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				return $ip;
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Disable MFA temporarily (for 1 hour)
	 */
	private function disable_mfa_temporarily() {
		Config::update( 'mfa_enabled', 0 );

		// Check if transient already exists (MFA already disabled)
		$existing_transient = get_transient( MfaConstants::TRANSIENT_MFA_DISABLED );
		if ( false !== $existing_transient ) {
			// MFA is already disabled, don't reduce the timeout
			return;
		}

		// Set transient for 1 hour (automatically expires)
		set_transient( MfaConstants::TRANSIENT_MFA_DISABLED, 1, MfaConstants::MFA_DISABLE_DURATION );
	}
}
