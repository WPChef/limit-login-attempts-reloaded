<?php

namespace LLAR\Core\MfaFlow;

use LLAR\Core\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MFA flow session and OTP storage in transients.
 */
class SessionStore {

	/**
	 * Save session after handshake.
	 *
	 * @param string $token       From API.
	 * @param string $secret      From API.
	 * @param string $username   Login name.
	 * @param int    $user_id    Optional. User ID if known.
	 * @param string $redirect_to Optional. URL to redirect after login.
	 * @param string $cancel_url  Optional. URL for MFA app cancel.
	 * @param string $provider_id Optional. Provider id (e.g. 'llar').
	 * @return bool True if saved.
	 */
	public function save_session( $token, $secret, $username, $user_id = 0, $redirect_to = '', $cancel_url = '', $provider_id = '' ) {
		if ( ! is_string( $token ) || '' === $token ) {
			return false;
		}
		$ttl = (int) Config::get( 'mfa_session_ttl', 600 );
		$ttl = $ttl > 0 ? $ttl : 600;

		$data = array(
			'token'        => $token,
			'secret'       => $secret,
			'username'     => $username,
			'user_id'      => (int) $user_id,
			'redirect_to'  => is_string( $redirect_to ) ? $redirect_to : '',
			'cancel_url'   => is_string( $cancel_url ) ? $cancel_url : '',
			'provider_id'  => is_string( $provider_id ) ? $provider_id : 'llar',
			'created'      => time(),
		);

		$key = LLA_MFA_FLOW_TRANSIENT_SESSION_PREFIX . $token;
		return (bool) set_transient( $key, $data, $ttl );
	}

	/**
	 * Get session by token.
	 *
	 * @param string $token Session token.
	 * @return array|null Session data or null if not found.
	 */
	public function get_session( $token ) {
		if ( ! is_string( $token ) || '' === $token ) {
			return null;
		}
		$key = LLA_MFA_FLOW_TRANSIENT_SESSION_PREFIX . $token;
		$data = get_transient( $key );
		if ( false === $data || ! is_array( $data ) ) {
			return null;
		}
		return $data;
	}

	/**
	 * Delete session by token.
	 *
	 * @param string $token Session token.
	 */
	public function delete_session( $token ) {
		if ( ! is_string( $token ) || '' === $token ) {
			return;
		}
		delete_transient( LLA_MFA_FLOW_TRANSIENT_SESSION_PREFIX . $token );
		$this->delete_otp( $token );
	}

	/**
	 * Save OTP code for token (for callback verification).
	 *
	 * @param string $token Session token.
	 * @param string $code  OTP code.
	 * @return bool
	 */
	public function save_otp( $token, $code ) {
		if ( ! is_string( $token ) || '' === $token ) {
			return false;
		}
		$ttl = defined( 'LLA_MFA_FLOW_OTP_TTL' ) ? (int) LLA_MFA_FLOW_OTP_TTL : 180;
		$key = LLA_MFA_FLOW_TRANSIENT_OTP_PREFIX . $token;
		return (bool) set_transient( $key, (string) $code, $ttl );
	}

	/**
	 * Get OTP for token and optionally delete after read.
	 *
	 * @param string $token Session token.
	 * @return string|null OTP value or null.
	 */
	public function get_otp( $token ) {
		if ( ! is_string( $token ) || '' === $token ) {
			return null;
		}
		$key = LLA_MFA_FLOW_TRANSIENT_OTP_PREFIX . $token;
		$code = get_transient( $key );
		return ( false !== $code && is_string( $code ) ) ? $code : null;
	}

	/**
	 * Delete OTP for token.
	 *
	 * @param string $token Session token.
	 */
	public function delete_otp( $token ) {
		if ( ! is_string( $token ) || '' === $token ) {
			return;
		}
		delete_transient( LLA_MFA_FLOW_TRANSIENT_OTP_PREFIX . $token );
	}
}
