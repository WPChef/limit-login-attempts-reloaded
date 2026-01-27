<?php

namespace LLAR\Core\Mfa;

use LLAR\Core\Config;
use LLAR\Core\MfaConstants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MFA rescue endpoint: rate limiting, decrypt, verify, disable MFA.
 * Uses MfaValidator for hash_id validation; MfaBackupCodes for decrypt/verify.
 */
class MfaEndpoint {

	const RESCUE_ERROR_MSG = 'Invalid or expired rescue link';

	/**
	 * Backup codes (decrypt, verify)
	 *
	 * @var MfaBackupCodes
	 */
	private $backup_codes;

	/**
	 * @param MfaBackupCodes $backup_codes Backup codes service
	 */
	public function __construct( MfaBackupCodes $backup_codes ) {
		$this->backup_codes = $backup_codes;
	}

	/**
	 * Handle rescue endpoint request.
	 *
	 * @param string $hash_id Hash ID from URL
	 */
	public function handle( $hash_id ) {
		$client_ip = $this->get_client_ip();
		if ( $this->is_rate_limited( $client_ip ) ) {
			wp_die( 'Too many attempts. Please try again later.', 'LLAR MFA Rescue', array( 'response' => 429 ) );
		}
		$this->increment_attempts( $client_ip );

		$hash_id = MfaValidator::validate_rescue_hash_id( $hash_id );
		if ( false === $hash_id ) {
			wp_die( self::RESCUE_ERROR_MSG, 'LLAR MFA Rescue', array( 'response' => 403 ) );
		}

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

		$plain_code = $this->backup_codes->decrypt_code( $encrypted_data );
		if ( false === $plain_code ) {
			wp_die( self::RESCUE_ERROR_MSG, 'LLAR MFA Rescue', array( 'response' => 403 ) );
		}

		$codes = Config::get( 'mfa_rescue_codes', array() );
		if ( ! is_array( $codes ) || empty( $codes ) ) {
			wp_die( self::RESCUE_ERROR_MSG, 'LLAR MFA Rescue', array( 'response' => 403 ) );
		}

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

	private function get_client_ip() {
		$ip_keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR' );
		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				if ( false !== strpos( $ip, ',' ) ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				return $ip;
			}
		}
		return '0.0.0.0';
	}

	private function is_rate_limited( $client_ip ) {
		$salt = MfaConstants::get_rate_limit_salt();
		if ( null === $salt ) {
			return true;
		}
		$transient_key = MfaConstants::TRANSIENT_ATTEMPTS_PREFIX . hash( 'sha256', $client_ip . $salt );
		$attempts      = get_transient( $transient_key );
		$attempts      = ( false !== $attempts ) ? $attempts : 0;
		return $attempts >= MfaConstants::MAX_ATTEMPTS;
	}

	private function increment_attempts( $client_ip ) {
		$salt = MfaConstants::get_rate_limit_salt();
		if ( null === $salt ) {
			return;
		}
		$transient_key = MfaConstants::TRANSIENT_ATTEMPTS_PREFIX . hash( 'sha256', $client_ip . $salt );
		$attempts      = get_transient( $transient_key );
		$attempts      = ( false !== $attempts ) ? $attempts : 0;
		set_transient( $transient_key, $attempts + 1, MfaConstants::RATE_LIMIT_PERIOD );
	}

	private function disable_mfa_temporarily() {
		Config::update( 'mfa_enabled', 0 );
		if ( false === get_transient( MfaConstants::TRANSIENT_MFA_DISABLED ) ) {
			set_transient( MfaConstants::TRANSIENT_MFA_DISABLED, 1, MfaConstants::MFA_DISABLE_DURATION );
		}
	}
}
