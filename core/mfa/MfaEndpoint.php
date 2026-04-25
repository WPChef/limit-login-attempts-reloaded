<?php

namespace LLAR\Core\Mfa;

use LLAR\Core\Config;
use LLAR\Core\MfaConstants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MFA rescue endpoint: decrypt, verify, disable MFA. One-time
 * transient per link plus per-code "used" in config. Prefetch/preview
 * requests are ignored so they do not consume the one-time link.
 * Uses MfaValidator for hash_id validation; MfaBackupCodes for decrypt/verify.
 */
class MfaEndpoint implements MfaEndpointInterface {

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
	 * Handle rescue endpoint request. Validates format, then decrypts, verifies code,
	 * temporarily disables MFA, or wp_die.
	 *
	 * @param string $hash_id Hash ID from URL (llar_rescue query var).
	 * @return void
	 */
	public function handle( $hash_id ) {
		$hash_id = MfaValidator::validate_rescue_hash_id( $hash_id );
		if ( false === $hash_id ) {
			wp_die( self::MSG_ERROR, 'LLAR MFA Rescue', array( 'response' => 403 ) );
		}

		// Prefetch/prerender/link-preview/bot: must not consume the one-time payload.
		if ( $this->is_rescue_request_prefetch() ) {
			$this->render_rescue_confirmation_page();
			exit;
		}

		// Read payload (delete only after successful verification so failed attempts are not "burned").
		$encrypted_data = $this->read_rescue_encrypted_payload( $hash_id );
		if ( false === $encrypted_data ) {
			wp_die( self::MSG_ERROR, 'LLAR MFA Rescue', array( 'response' => 403 ) );
		}

		$plain_code = $this->backup_codes->decrypt_code( $encrypted_data );
		if ( false === $plain_code ) {
			$this->remove_rescue_payload_from_options( $hash_id );
			wp_die( self::MSG_ERROR, 'LLAR MFA Rescue', array( 'response' => 403 ) );
		}

		$codes = Config::get( 'mfa_rescue_codes', array() );
		if ( ! is_array( $codes ) || empty( $codes ) ) {
			$this->remove_rescue_payload_from_options( $hash_id );
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
			$deleted = $this->remove_rescue_payload_from_options( $hash_id );
			if ( 1 !== (int) $deleted ) {
				wp_die( self::MSG_ERROR, 'LLAR MFA Rescue', array( 'response' => 403 ) );
			}
			$rescue_code = RescueCode::from_array( $codes[ $verified_index ] );
			$rescue_code->mark_as_used();
			$codes[ $verified_index ] = $rescue_code->to_array();
			Config::update( 'mfa_rescue_codes', $codes );
			$this->disable_mfa_temporarily();
			$login_url = add_query_arg( 'llar_mfa_disabled', '1', wp_login_url() );
			wp_safe_redirect( $login_url );
			exit;
		}

		$this->remove_rescue_payload_from_options( $hash_id );
		wp_die( self::MSG_ERROR, 'LLAR MFA Rescue', array( 'response' => 403 ) );
	}

	/**
	 * Read and validate stored encrypted payload (does not remove it). Falls back to direct
	 * wp_options read when object cache misses (some hosts/stacks show the row in DB but not in cache).
	 *
	 * @param string $hash_id Validated hash_id from URL.
	 * @return string|false Encrypted payload or false.
	 */
	private function read_rescue_encrypted_payload( $hash_id ) {
		$transient_key  = MfaConstants::TRANSIENT_RESCUE_PREFIX . $hash_id;
		$encrypted_data = get_transient( $transient_key );
		if ( false === $encrypted_data ) {
			global $wpdb;
			$option_name = '_transient_' . $transient_key;
			$raw         = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT option_value FROM ' . $wpdb->options . ' WHERE option_name = %s LIMIT 1',
					$option_name
				)
			);
			if ( null !== $raw && '' !== $raw ) {
				$encrypted_data = maybe_unserialize( $raw );
			}
		}
		if ( false === $encrypted_data ) {
			return false;
		}
		if ( ! is_string( $encrypted_data ) || '' === $encrypted_data ) {
			return false;
		}
		$max_len = 2048;
		if ( strlen( $encrypted_data ) > $max_len ) {
			return false;
		}
		$v2_prefix = 'v2:';
		if ( 0 === strpos( $encrypted_data, $v2_prefix ) ) {
			$payload = substr( $encrypted_data, strlen( $v2_prefix ) );
			if ( '' === $payload || ! preg_match( '/^[A-Za-z0-9+\/=]+$/', $payload ) ) {
				return false;
			}
		} elseif ( ! preg_match( '/^[A-Za-z0-9+\/=]+$/', $encrypted_data ) ) {
			return false;
		}
		return $encrypted_data;
	}

	/**
	 * Remove the one-time payload row from the database (and timeout row). Returns rows deleted (0 or 1 for value row).
	 *
	 * @param string $hash_id Validated hash_id.
	 * @return int Deleted rows for _transient_* value row.
	 */
	private function remove_rescue_payload_from_options( $hash_id ) {
		$transient_key = MfaConstants::TRANSIENT_RESCUE_PREFIX . $hash_id;
		global $wpdb;
		$option_name = '_transient_' . $transient_key;
		$deleted     = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM ' . $wpdb->options . ' WHERE option_name = %s',
				$option_name
			)
		);
		if ( 1 === (int) $deleted ) {
			$timeout_name = '_transient_timeout_' . $transient_key;
			$wpdb->query(
				$wpdb->prepare(
					'DELETE FROM ' . $wpdb->options . ' WHERE option_name = %s',
					$timeout_name
				)
			);
			delete_transient( MfaConstants::RESCUE_MAX_EXPIRY_CACHE_KEY );
		}
		return (int) $deleted;
	}

	/**
	 * Whether the request is a speculative/automated load that must not consume the one-time rescue
	 * transient. A request is treated as prefetch/preview when ANY of the following holds:
	 *   - Sec-Purpose or Purpose header contains "prefetch" or "prerender" (Chrome/Edge speculation);
	 *   - X-Moz: prefetch (Firefox);
	 *   - No Sec-Fetch-* headers at all (link-preview / bot / scanner);
	 *   - Sec-Fetch-* present but not a real user-activated top-level navigation
	 *     (Sec-Fetch-Dest: document + Sec-Fetch-Mode: navigate + Sec-Fetch-User: ?1).
	 *
	 * @return bool
	 */
	private function is_rescue_request_prefetch() {
		if (
			defined( 'LLA_MFA_RESCUE_PREFETCH_BYPASS_ARG' )
			&& isset( $_GET[ LLA_MFA_RESCUE_PREFETCH_BYPASS_ARG ] )
		) {
			return false;
		}

		$sec_purpose = isset( $_SERVER['HTTP_SEC_PURPOSE'] ) && is_string( $_SERVER['HTTP_SEC_PURPOSE'] ) ? $_SERVER['HTTP_SEC_PURPOSE'] : '';
		$purpose     = isset( $_SERVER['HTTP_PURPOSE'] ) && is_string( $_SERVER['HTTP_PURPOSE'] ) ? $_SERVER['HTTP_PURPOSE'] : '';
		$x_moz       = isset( $_SERVER['HTTP_X_MOZ'] ) && is_string( $_SERVER['HTTP_X_MOZ'] ) ? $_SERVER['HTTP_X_MOZ'] : '';
		$sec_f_dest  = isset( $_SERVER['HTTP_SEC_FETCH_DEST'] ) && is_string( $_SERVER['HTTP_SEC_FETCH_DEST'] ) ? $_SERVER['HTTP_SEC_FETCH_DEST'] : '';
		$sec_f_mode  = isset( $_SERVER['HTTP_SEC_FETCH_MODE'] ) && is_string( $_SERVER['HTTP_SEC_FETCH_MODE'] ) ? $_SERVER['HTTP_SEC_FETCH_MODE'] : '';
		$sec_f_user  = isset( $_SERVER['HTTP_SEC_FETCH_USER'] ) && is_string( $_SERVER['HTTP_SEC_FETCH_USER'] ) ? $_SERVER['HTTP_SEC_FETCH_USER'] : '';
		$sec_f_site  = isset( $_SERVER['HTTP_SEC_FETCH_SITE'] ) && is_string( $_SERVER['HTTP_SEC_FETCH_SITE'] ) ? $_SERVER['HTTP_SEC_FETCH_SITE'] : '';

		foreach ( array( $sec_purpose, $purpose ) as $p ) {
			if ( '' !== $p && ( false !== stripos( $p, 'prefetch' ) || false !== stripos( $p, 'prerender' ) ) ) {
				return true;
			}
		}
		if ( 'prefetch' === strtolower( $x_moz ) ) {
			return true;
		}
		$has_any_sec_fetch = ( '' !== $sec_f_dest || '' !== $sec_f_mode || '' !== $sec_f_user || '' !== $sec_f_site );
		if ( ! $has_any_sec_fetch ) {
			return true;
		}
		$looks_like_user_nav = ( 'document' === strtolower( $sec_f_dest ) )
			&& ( 'navigate' === strtolower( $sec_f_mode ) )
			&& ( '?1' === $sec_f_user );
		return ! $looks_like_user_nav;
	}

	/**
	 * Render confirmation page when a request looks like a prefetch.
	 *
	 * @return void
	 */
	private function render_rescue_confirmation_page() {
		$continue_url = add_query_arg(
			LLA_MFA_RESCUE_PREFETCH_BYPASS_ARG,
			'1'
		);

		$message = 'This will disable 2FA on your website for one hour. ';
		$message .= '<a href="' . esc_url( $continue_url ) . '">Click to continue</a>';

		wp_die(
			wp_kses_post( $message ),
			'LLAR MFA Rescue',
			array( 'response' => 200 )
		);
	}

	private function disable_mfa_temporarily() {
		// Only set transient so MFA is disabled for LLA_MFA_DISABLE_DURATION. Do not change
		// Config 'mfa_enabled' — when transient expires, MFA is effectively on again without user action.
		if ( false === get_transient( MfaConstants::TRANSIENT_MFA_DISABLED ) ) {
			set_transient( MfaConstants::TRANSIENT_MFA_DISABLED, 1, MfaConstants::MFA_DISABLE_DURATION );
		}
	}

}
