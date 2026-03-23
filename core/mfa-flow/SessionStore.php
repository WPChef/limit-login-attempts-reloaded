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
	 * @param string $provider_id          Optional. Provider id (e.g. 'llar').
	 * @param bool   $is_pre_authenticated  True if password was already validated at handshake.
	 * @param bool   $remember_me           True if user checked "Remember Me" on login form.
	 * @return bool True if saved.
	 */
	public function save_session( $token, $secret, $username, $user_id = 0, $redirect_to = '', $cancel_url = '', $provider_id = '', $is_pre_authenticated = false, $remember_me = false ) {
		if ( ! is_string( $token ) || '' === $token ) {
			return false;
		}
		$ttl = defined( 'LLA_MFA_SESSION_TTL' ) ? (int) LLA_MFA_SESSION_TTL : 600;
		$ttl = $ttl > 0 ? $ttl : 600;

		$data = array(
			'token'                => $token,
			'secret'               => $secret,
			'username'             => $username,
			'user_id'              => (int) $user_id,
			'redirect_to'          => is_string( $redirect_to ) ? $redirect_to : '',
			'cancel_url'           => is_string( $cancel_url ) ? $cancel_url : '',
			'provider_id'          => is_string( $provider_id ) ? $provider_id : 'llar',
			'is_pre_authenticated' => (bool) $is_pre_authenticated,
			'remember_me'          => (bool) $remember_me,
			'created'              => time(),
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
		$key  = LLA_MFA_FLOW_TRANSIENT_SESSION_PREFIX . $token;
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
		delete_transient( LLA_MFA_FLOW_TRANSIENT_SEND_SECRET_PREFIX . $token );
		$this->delete_otp( $token );
	}

	/**
	 * Save callback state for token (CSRF protection for anonymous users).
	 * State is stored server-side and mirrored in a HttpOnly cookie.
	 *
	 * @param string $state Random state string.
	 * @param string $token MFA session token.
	 * @return bool
	 */
	public function save_callback_state( $state, $token ) {
		if ( ! is_string( $state ) || '' === $state || ! is_string( $token ) || '' === $token ) {
			return false;
		}
		$ttl = defined( 'LLA_MFA_SESSION_TTL' ) ? (int) LLA_MFA_SESSION_TTL : 600;
		$ttl = $ttl > 0 ? $ttl : 600;
		$key = ( defined( 'LLA_MFA_FLOW_TRANSIENT_STATE_PREFIX' ) ? LLA_MFA_FLOW_TRANSIENT_STATE_PREFIX : 'llar_mfa_state_' ) . $state;
		return (bool) set_transient( $key, $token, $ttl );
	}

	/**
	 * Consume callback state (one-time). Returns true if state matches token.
	 *
	 * @param string $state Cookie state value.
	 * @param string $token MFA session token from request.
	 * @return bool
	 */
	public function consume_callback_state( $state, $token ) {
		if ( ! is_string( $state ) || '' === $state || ! is_string( $token ) || '' === $token ) {
			return false;
		}
		$key   = ( defined( 'LLA_MFA_FLOW_TRANSIENT_STATE_PREFIX' ) ? LLA_MFA_FLOW_TRANSIENT_STATE_PREFIX : 'llar_mfa_state_' ) . $state;
		$value = get_transient( $key );
		if ( false === $value || ! is_string( $value ) || $value !== $token ) {
			return false;
		}
		delete_transient( $key );
		return true;
	}

	/**
	 * Resolve token by callback state without consuming it.
	 *
	 * @param string $state Cookie state value.
	 * @return string|null
	 */
	public function get_callback_token( $state ) {
		if ( ! is_string( $state ) || '' === $state ) {
			return null;
		}
		$key   = ( defined( 'LLA_MFA_FLOW_TRANSIENT_STATE_PREFIX' ) ? LLA_MFA_FLOW_TRANSIENT_STATE_PREFIX : 'llar_mfa_state_' ) . $state;
		$value = get_transient( $key );
		return ( false !== $value && is_string( $value ) && '' !== $value ) ? $value : null;
	}

	/**
	 * Save OTP hash for token (for callback verification).
	 *
	 * @param string $token Session token.
	 * @param string $code  OTP code.
	 * @return bool
	 */
	public function save_otp( $token, $code ) {
		if ( ! is_string( $token ) || '' === $token || ! is_string( $code ) || '' === $code ) {
			return false;
		}
		$ttl = defined( 'LLA_MFA_FLOW_OTP_TTL' ) ? (int) LLA_MFA_FLOW_OTP_TTL : 180;
		$key = LLA_MFA_FLOW_TRANSIENT_OTP_PREFIX . $token;
		return (bool) set_transient( $key, $this->hash_otp_code( $code ), $ttl );
	}

	/**
	 * Get OTP hash for token (read-only).
	 *
	 * @param string $token Session token.
	 * @return string|null OTP hash value or null.
	 */
	public function get_otp( $token ) {
		if ( ! is_string( $token ) || '' === $token ) {
			return null;
		}
		$key  = LLA_MFA_FLOW_TRANSIENT_OTP_PREFIX . $token;
		$hash = get_transient( $key );
		return ( false !== $hash && is_string( $hash ) ) ? $hash : null;
	}

	/**
	 * Get OTP hash for token and delete it (one-time use). Use in callback to prevent OTP reuse.
	 *
	 * @param string $token Session token.
	 * @return string|null OTP hash value or null if not found or already consumed.
	 */
	public function get_otp_once( $token ) {
		if ( ! is_string( $token ) || '' === $token ) {
			return null;
		}

		$lock_name = 'llar_mfa_otp_lock_' . hash( 'sha256', $token );
		if ( ! add_option( $lock_name, time(), '', 'no' ) ) {
			return null;
		}

		try {
			$transient_key = LLA_MFA_FLOW_TRANSIENT_OTP_PREFIX . $token;
			$hash          = get_transient( $transient_key );
			if ( false === $hash || ! is_string( $hash ) ) {
				return null;
			}

			// For DB-backed transients consume OTP atomically via low-level DELETE.
			if ( ! wp_using_ext_object_cache() ) {
				global $wpdb;

				$option_name = '_transient_' . $transient_key;
				$deleted     = $wpdb->query(
					$wpdb->prepare(
						'DELETE FROM ' . $wpdb->options . ' WHERE option_name = %s',
						$option_name
					)
				);

				if ( 1 !== (int) $deleted ) {
					return null;
				}

				// Best-effort cleanup of the timeout row.
				$timeout_name = '_transient_timeout_' . $transient_key;
				$wpdb->query(
					$wpdb->prepare(
						'DELETE FROM ' . $wpdb->options . ' WHERE option_name = %s',
						$timeout_name
					)
				);

				return $hash;
			}

			$this->delete_otp( $token );
			return $hash;
		} finally {
			delete_option( $lock_name );
		}
	}

	/**
	 * Verify provided OTP against stored one-time hash and consume it.
	 *
	 * @param string $token Session token.
	 * @param string $code  User-provided OTP code.
	 * @return bool
	 */
	public function verify_otp_once( $token, $code ) {
		if ( ! is_string( $token ) || '' === $token || ! is_string( $code ) || '' === $code ) {
			return false;
		}

		$stored_hash = $this->get_otp_once( $token );
		if ( null === $stored_hash ) {
			return false;
		}

		$expected_hash = $this->hash_otp_code( $code );
		if ( hash_equals( (string) $stored_hash, (string) $expected_hash ) ) {
			return true;
		}

		// Backward compatibility: accept plaintext OTP that may still be in transient during rollout.
		return hash_equals( (string) $stored_hash, (string) $code );
	}

	/**
	 * Hash OTP code with server-side pepper before storing/comparing.
	 *
	 * @param string $code OTP code.
	 * @return string
	 */
	private function hash_otp_code( $code ) {
		return hash_hmac( 'sha256', (string) $code, $this->get_otp_pepper() );
	}

	/**
	 * Resolve server-side pepper for OTP HMAC.
	 *
	 * @return string
	 */
	private function get_otp_pepper() {
		if ( function_exists( 'wp_salt' ) ) {
			$salt = wp_salt( 'auth' );
			if ( is_string( $salt ) && '' !== $salt ) {
				return $salt;
			}
		}
		if ( defined( 'AUTH_SALT' ) ) {
			$auth_salt = constant( 'AUTH_SALT' );
			if ( is_string( $auth_salt ) && '' !== $auth_salt ) {
				return $auth_salt;
			}
		}
		if ( defined( 'AUTH_KEY' ) ) {
			$auth_key = constant( 'AUTH_KEY' );
			if ( is_string( $auth_key ) && '' !== $auth_key ) {
				return $auth_key;
			}
		}
		return 'llar-mfa-otp-fallback-pepper';
	}

	/**
	 * Save send_email secret for token (validates POST send_code request body).
	 * Secret is the same as session secret: from MFA app handshake response, used for verify and send_code.
	 *
	 * @param string $token Session token.
	 * @param string $secret Secret from handshake response (MFA app).
	 * @return bool
	 */
	public function save_send_email_secret( $token, $secret ) {
		if ( ! is_string( $token ) || '' === $token || ! is_string( $secret ) || '' === $secret ) {
			return false;
		}
		$ttl = defined( 'LLA_MFA_SESSION_TTL' ) ? (int) LLA_MFA_SESSION_TTL : 600;
		$ttl = $ttl > 0 ? $ttl : 600;
		$key = LLA_MFA_FLOW_TRANSIENT_SEND_SECRET_PREFIX . $token;
		return (bool) set_transient( $key, $secret, $ttl );
	}

	/**
	 * Get send_email secret for token.
	 *
	 * @param string $token Session token.
	 * @return string|null Secret or null if not found.
	 */
	public function get_send_email_secret( $token ) {
		if ( ! is_string( $token ) || '' === $token ) {
			return null;
		}
		$key    = LLA_MFA_FLOW_TRANSIENT_SEND_SECRET_PREFIX . $token;
		$secret = get_transient( $key );
		return ( false !== $secret && is_string( $secret ) ) ? $secret : null;
	}

	/**
	 * Delete send_email secret for token (e.g. when session is deleted).
	 *
	 * @param string $token Session token.
	 */
	public function delete_send_email_secret( $token ) {
		if ( ! is_string( $token ) || '' === $token ) {
			return;
		}
		delete_transient( LLA_MFA_FLOW_TRANSIENT_SEND_SECRET_PREFIX . $token );
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
