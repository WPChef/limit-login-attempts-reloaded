<?php

namespace LLAR\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persists short-lived login flow state (remaining attempts, early-hook errors) without PHP sessions.
 * Uses an HttpOnly cookie token + WordPress transients (object-cache friendly when available).
 */
class LoginFlowTransientStore {

	const COOKIE_NAME = 'llar_login_flow';

	/**
	 * TTL for cookie and transient (seconds).
	 *
	 * @return int
	 */
	public static function ttl() {
		$d = Config::get( 'valid_duration' );
		$d = is_numeric( $d ) ? (int) $d : 86400;
		if ( $d < 3600 ) {
			$d = 3600;
		}

		return (int) apply_filters( 'llar_login_flow_transient_ttl', $d );
	}

	/**
	 * Current cookie token if valid, else empty string.
	 *
	 * @return string
	 */
	public static function get_token() {
		if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return '';
		}
		$t = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
		if ( strlen( $t ) < 16 || strlen( $t ) > 64 ) {
			return '';
		}

		return $t;
	}

	/**
	 * Ensure a cookie token exists (sets cookie when possible).
	 *
	 * @return string Token or empty if headers already sent and no cookie.
	 */
	public static function ensure_token() {
		$t = self::get_token();
		if ( $t !== '' ) {
			return $t;
		}
		if ( headers_sent() ) {
			return '';
		}
		$t = wp_generate_password( 32, false, false );
		$expire = time() + self::ttl();
		setcookie( self::COOKIE_NAME, $t, $expire, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
		$_COOKIE[ self::COOKIE_NAME ] = $t;

		return $t;
	}

	/**
	 * Transient key for token (token is not stored verbatim in the option name).
	 *
	 * @param string $token Raw cookie value.
	 * @return string
	 */
	private static function transient_key( $token ) {
		return 'llar_lf_' . hash_hmac( 'sha256', $token, wp_salt( 'logged_in' ) );
	}

	/**
	 * Read all stored keys for the current token.
	 *
	 * @return array
	 */
	public static function read_all() {
		$token = self::get_token();
		if ( $token === '' ) {
			return array();
		}
		$data = get_transient( self::transient_key( $token ) );

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Merge keys into stored state. Use null values to remove keys.
	 *
	 * @param array $patch Key => value or null to unset.
	 * @return void
	 */
	public static function merge( array $patch ) {
		$token = self::ensure_token();
		if ( $token === '' ) {
			return;
		}
		$data = self::read_all();
		foreach ( $patch as $k => $v ) {
			if ( null === $v ) {
				unset( $data[ $k ] );
			} else {
				$data[ $k ] = $v;
			}
		}
		set_transient( self::transient_key( $token ), $data, self::ttl() );
	}

	/**
	 * Get one key from stored state.
	 *
	 * @param string $key     State key.
	 * @param mixed  $default Default if missing or no token.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$token = self::get_token();
		if ( $token === '' ) {
			return $default;
		}
		$data = get_transient( self::transient_key( $token ) );
		if ( ! is_array( $data ) || ! array_key_exists( $key, $data ) ) {
			return $default;
		}

		return $data[ $key ];
	}
}
