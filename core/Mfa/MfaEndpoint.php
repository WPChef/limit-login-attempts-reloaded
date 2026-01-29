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
class MfaEndpoint implements MfaEndpointInterface {

	/** Non-informative message for 429 (do not reveal retry timing). */
	const MSG_TOO_MANY_REQUESTS = 'Too many requests.';

	/** Non-informative message for 403/500 (do not reveal internal details). */
	const MSG_ERROR = 'An error occurred.';

	/**
	 * Backup codes (decrypt, verify)
	 *
	 * @var MfaBackupCodesInterface
	 */
	private $backup_codes;

	/**
	 * Constructor.
	 *
	 * @param MfaBackupCodesInterface $backup_codes Backup codes service (decrypt, verify).
	 */
	public function __construct( MfaBackupCodesInterface $backup_codes ) {
		$this->backup_codes = $backup_codes;
	}

	/**
	 * Handle rescue endpoint request. Global cooldown: one use per RESCUE_USE_COOLDOWN seconds (default 1 min).
	 * Then validates hash_id, decrypts, verifies code, disables MFA and redirects, or wp_die.
	 *
	 * @param string $hash_id Hash ID from URL (llar_rescue query var).
	 * @return void
	 */
	public function handle( $hash_id ) {
		if ( $this->is_rescue_cooldown() ) {
			wp_die( self::MSG_TOO_MANY_REQUESTS, 'LLAR MFA Rescue', array( 'response' => 429 ) );
		}
		$this->set_rescue_cooldown();

		$hash_id = MfaValidator::validate_rescue_hash_id( $hash_id );
		if ( false === $hash_id ) {
			wp_die( self::MSG_ERROR, 'LLAR MFA Rescue', array( 'response' => 403 ) );
		}

		// Atomically consume encrypted payload for this hash_id (prevents double-spend).
		$encrypted_data = $this->consume_encrypted_payload( $hash_id );
		if ( false === $encrypted_data ) {
			wp_die( self::MSG_ERROR, 'LLAR MFA Rescue', array( 'response' => 403 ) );
		}

		$plain_code = $this->backup_codes->decrypt_code( $encrypted_data );
		if ( false === $plain_code ) {
			wp_die( self::MSG_ERROR, 'LLAR MFA Rescue', array( 'response' => 403 ) );
		}

		$codes = Config::get( 'mfa_rescue_codes', array() );
		if ( ! is_array( $codes ) || empty( $codes ) ) {
			wp_die( self::MSG_ERROR, 'LLAR MFA Rescue', array( 'response' => 403 ) );
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

		wp_die( self::MSG_ERROR, 'LLAR MFA Rescue', array( 'response' => 403 ) );
	}

	/**
	 * Atomically consume encrypted payload for a given hash_id.
	 * Uses dedicated transient per hash and low-level DELETE to prevent double-spend.
	 *
	 * @param string $hash_id Validated hash_id from URL.
	 * @return string|false Encrypted payload or false if not found/invalid.
	 */
	private function consume_encrypted_payload( $hash_id ) {
		$transient_key  = MfaConstants::TRANSIENT_RESCUE_PREFIX . $hash_id;
		$encrypted_data = get_transient( $transient_key );
		if ( false === $encrypted_data ) {
			return false;
		}

		// Basic sanity checks before touching the database.
		if ( ! is_string( $encrypted_data ) || '' === $encrypted_data ) {
			return false;
		}
		$max_len = 2048;
		if ( strlen( $encrypted_data ) > $max_len ) {
			return false;
		}
		if ( ! preg_match( '/^[A-Za-z0-9+\/=]+$/', $encrypted_data ) ) {
			return false;
		}

		// Atomic delete of the transient row; only the first request will succeed.
		global $wpdb;
		$table       = $wpdb->options;
		$option_name = '_transient_' . $transient_key;

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE option_name = %s",
				$option_name
			)
		);

		if ( 1 !== (int) $deleted ) {
			// Another request has already consumed this payload.
			return false;
		}

		// Best-effort cleanup of the timeout row; result is not critical.
		$timeout_name = '_transient_timeout_' . $transient_key;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE option_name = %s",
				$timeout_name
			)
		);

		return $encrypted_data;
	}

	/**
	 * Whether rescue endpoint is in cooldown (one use per RESCUE_USE_COOLDOWN seconds, globally).
	 *
	 * @return bool True if last use was within cooldown.
	 */
	private function is_rescue_cooldown() {
		return false !== get_transient( MfaConstants::TRANSIENT_RESCUE_LAST_USE );
	}

	/**
	 * Set rescue endpoint cooldown (transient expires after TTL seconds).
	 *
	 * @param int|null $ttl TTL in seconds; default MfaConstants::RESCUE_USE_COOLDOWN.
	 * @return void
	 */
	private function set_rescue_cooldown( $ttl = null ) {
		$ttl = ( null !== $ttl ) ? (int) $ttl : (int) MfaConstants::RESCUE_USE_COOLDOWN;
		set_transient( MfaConstants::TRANSIENT_RESCUE_LAST_USE, 1, $ttl );
	}

	/**
	 * Log rescue attempt for debugging (reason only; no hash_id in message to avoid leakage).
	 *
	 * @param string $hash_id Hash ID from request (unused in message).
	 * @param bool   $success Whether the attempt succeeded.
	 * @param string $reason  Reason code (e.g. cooldown, invalid).
	 * @return void
	 */
	private function log_rescue_attempt( $hash_id, $success, $reason ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'LLAR MFA Rescue attempt: ' . $reason . ' (success=' . ( $success ? '1' : '0' ) . ')' );
		}
	}

	private function disable_mfa_temporarily() {
		// Only set transient so MFA is disabled for LLA_MFA_DISABLE_DURATION. Do not change
		// Config 'mfa_enabled' — when transient expires, MFA is effectively on again without user action.
		if ( false === get_transient( MfaConstants::TRANSIENT_MFA_DISABLED ) ) {
			set_transient( MfaConstants::TRANSIENT_MFA_DISABLED, 1, MfaConstants::MFA_DISABLE_DURATION );
		}
	}
}
