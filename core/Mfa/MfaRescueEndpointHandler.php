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

	/**
	 * Handle rescue endpoint request
	 *
	 * @param string $hash_id Hash ID from URL
	 * @return bool True if code verified and MFA disabled
	 */
	public function handle( $hash_id ) {
		// Get client IP for rate limiting
		$client_ip = $this->get_client_ip();

		// Check rate limiting
		if ( $this->rate_limiter->is_rate_limited( $client_ip ) ) {
			wp_die( 'Too many attempts. Please try again later.', 'LLAR MFA Rescue', array( 'response' => 429 ) );
		}

		// Increment attempt counter
		$this->rate_limiter->increment_attempts( $client_ip );

		// Validate hash_id format (SHA-256 produces 64 hex characters)
		if ( ! preg_match( '/^[a-f0-9]{64}$/i', $hash_id ) ) {
			wp_die( 'Invalid rescue link format', 'LLAR MFA Rescue', array( 'response' => 403 ) );
		}

		// Get encrypted code from transient by hash_id
		$transient_rescue_key = MfaConstants::TRANSIENT_RESCUE_PREFIX . sanitize_text_field( $hash_id );
		$encrypted_data        = get_transient( $transient_rescue_key );

		if ( false === $encrypted_data ) {
			// Hash not found or expired (one-time, 5 minutes)
			// Don't reveal whether hash was invalid or expired (security best practice)
			wp_die( 'Invalid or expired rescue link', 'LLAR MFA Rescue', array( 'response' => 403 ) );
		}

		// Delete transient immediately after getting (one-time use)
		delete_transient( $transient_rescue_key );

		// Decrypt the code
		$plain_code = $this->encryption->decrypt_code( $encrypted_data );

		if ( false === $plain_code ) {
			// Decryption failed - invalid data
			wp_die( 'Invalid rescue link', 'LLAR MFA Rescue', array( 'response' => 403 ) );
		}

		// Verify code with constant-time comparison to prevent timing attacks
		$codes = Config::get( 'mfa_rescue_codes', array() );

		if ( ! is_array( $codes ) || empty( $codes ) ) {
			wp_die( 'Invalid rescue code', 'LLAR MFA Rescue', array( 'response' => 403 ) );
		}

		$code_verified  = false;
		$verified_index = null;

		// Check all codes to prevent timing attacks (always check same number of codes)
		foreach ( $codes as $index => $code_data ) {
			$rescue_code = RescueCode::from_array( $code_data );

			// Skip already used codes
			if ( $rescue_code->is_used() ) {
				continue;
			}

			// Use constant-time password verification
			if ( $rescue_code->verify( $plain_code ) ) {
				$code_verified  = true;
				$verified_index = $index;
				break; // Found valid code, can exit early
			}
		}

		if ( $code_verified && null !== $verified_index ) {
			// Mark as used
			$rescue_code = RescueCode::from_array( $codes[ $verified_index ] );
			$rescue_code->mark_as_used();
			$codes[ $verified_index ] = $rescue_code->to_array();

			Config::update( 'mfa_rescue_codes', $codes );

			// Disable MFA for an hour
			$this->disable_mfa_temporarily();

			// Redirect to wp-login.php with success message
			$login_url = add_query_arg( 'llar_mfa_disabled', '1', wp_login_url() );
			wp_safe_redirect( $login_url );
			exit;
		}

		// Code not found or already used - same error message (don't reveal which)
		wp_die( 'Invalid rescue code', 'LLAR MFA Rescue', array( 'response' => 403 ) );
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
