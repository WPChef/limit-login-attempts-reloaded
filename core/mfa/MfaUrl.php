<?php

namespace LLAR\Core\Mfa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public base URL for MFA endpoints called by the external MFA app or rescue links.
 */
class MfaUrl {

	/**
	 * Absolute base for rescue PDF / default MFA public URL (trailing slash).
	 * Defaults to site_url( '/' ). Filter `llar_mfa_base_url` may retarget it.
	 * Handshake send-code / login_url use public_base_for_request() instead so
	 * the cookie Host stays aligned when login is on home ≠ siteurl.
	 *
	 * @return string Absolute URL with trailing slash (no query/fragment).
	 */
	public static function base() {
		/**
		 * Filter the MFA public base URL (rescue links; handshake fallback).
		 *
		 * @param string $base Absolute URL, typically site_url( '/' ).
		 */
		$base = apply_filters( 'llar_mfa_base_url', site_url( '/' ) );
		$base = self::sanitize_base( $base );
		if ( null === $base ) {
			$base = site_url( '/' );
		}

		return trailingslashit( $base );
	}

	/**
	 * Handshake public base: same host as the login request when that Host is
	 * siteurl or home (port/scheme from the matching configured URL — never
	 * from HTTP_HOST). Otherwise fall back to base() (site_url / filter).
	 *
	 * login_url and send_email_url must both use this so MFA API hostname
	 * equality holds and the host-only state cookie is sent on return.
	 *
	 * @return string Absolute URL with trailing slash.
	 */
	public static function public_base_for_request() {
		$host_only = self::request_host_without_port();
		if ( '' !== $host_only ) {
			foreach ( array( site_url( '/' ), home_url( '/' ) ) as $candidate ) {
				$h = wp_parse_url( $candidate, PHP_URL_HOST );
				if ( ! is_string( $h ) || '' === $h ) {
					continue;
				}
				if ( strtolower( $h ) !== $host_only ) {
					continue;
				}
				$clean = self::sanitize_base( $candidate );
				if ( null === $clean ) {
					continue;
				}
				return trailingslashit( $clean );
			}
		}

		return self::base();
	}

	/**
	 * REST / send-code public base: origin from public_base_for_request(), path from
	 * raw get_option( 'home' ) (not siteurl subdirectory, not filtered home_url
	 * language prefixes). REST rewrites live under the home path.
	 *
	 * @return string Absolute URL with trailing slash.
	 */
	public static function rest_public_base() {
		$origin = self::origin_from_url( self::public_base_for_request() );
		if ( null === $origin ) {
			$origin = self::origin_from_url( self::base() );
		}
		if ( null === $origin ) {
			return trailingslashit( home_url( '/' ) );
		}

		$home_opt = get_option( 'home' );
		$path     = '/';
		if ( is_string( $home_opt ) && '' !== $home_opt ) {
			$parsed = wp_parse_url( $home_opt, PHP_URL_PATH );
			if ( is_string( $parsed ) && '' !== $parsed ) {
				$path = $parsed;
			}
		}

		$base = $origin . '/' . ltrim( $path, '/' );
		if ( is_ssl() ) {
			$base = set_url_scheme( $base, 'https' );
		}

		return trailingslashit( $base );
	}

	/**
	 * Hosts allowed for MFA public redirects / OTP return (site_url + home_url).
	 *
	 * @return array<string, true> Lowercase host => true.
	 */
	public static function allowed_public_hosts() {
		$hosts = array();
		foreach ( array( site_url(), home_url() ) as $base ) {
			$h = wp_parse_url( $base, PHP_URL_HOST );
			if ( is_string( $h ) && '' !== $h ) {
				$hosts[ strtolower( $h ) ] = true;
			}
		}
		return $hosts;
	}

	/**
	 * Handshake / OTP return login URL.
	 *
	 * Authority from public_base_for_request() (same host as send_email_url).
	 * Path: request URI when Host matches that base; otherwise path+query from
	 * wp_login_url() remounted onto the base origin (no site_url( $uri )
	 * subdirectory doubling; never raw HTTP_HOST port).
	 *
	 * @param string $request_uri Sanitized REQUEST_URI (path + query).
	 * @return string Absolute URL.
	 */
	public static function request_login_url( $request_uri ) {
		$base = self::public_base_for_request();
		$origin = self::origin_from_url( $base );
		if ( null === $origin ) {
			$origin = self::origin_from_url( site_url() );
		}
		if ( null === $origin ) {
			return wp_login_url();
		}

		$base_host   = strtolower( (string) wp_parse_url( $origin, PHP_URL_HOST ) );
		$base_scheme = strtolower( (string) wp_parse_url( $base, PHP_URL_SCHEME ) );
		$host_only   = self::request_host_without_port();

		$uri = is_string( $request_uri ) ? $request_uri : '';
		if (
			'' !== $base_host
			&& '' !== $host_only
			&& $host_only === $base_host
			&& isset( $uri[0] )
			&& '/' === $uri[0]
		) {
			$login_url = $origin . $uri;
		} else {
			$fallback  = wp_login_url();
			$path      = (string) wp_parse_url( $fallback, PHP_URL_PATH );
			$query     = (string) wp_parse_url( $fallback, PHP_URL_QUERY );
			$login_url = $origin . $path . ( '' !== $query ? '?' . $query : '' );
		}

		if ( is_ssl() || 'https' === $base_scheme ) {
			$login_url = set_url_scheme( $login_url, 'https' );
		}

		return $login_url;
	}

	/**
	 * @return string Lowercase hostname from HTTP_HOST without port (may be empty).
	 */
	private static function request_host_without_port() {
		$host_header = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		if ( '' === $host_header ) {
			return '';
		}
		// Strip :port; keep bracketed IPv6 host via parse_url.
		$parsed = wp_parse_url( 'http://' . $host_header, PHP_URL_HOST );
		if ( ! is_string( $parsed ) || '' === $parsed ) {
			return strtolower( (string) preg_replace( '/:\d+$/', '', $host_header ) );
		}
		return strtolower( $parsed );
	}

	/**
	 * @param string $url Absolute URL.
	 * @return string|null scheme://host[:port]
	 */
	private static function origin_from_url( $url ) {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) ) {
			return null;
		}
		$scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : '';
		$host   = isset( $parts['host'] ) ? (string) $parts['host'] : '';
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || '' === $host ) {
			return null;
		}
		$port = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
		return $scheme . '://' . $host . $port;
	}

	/**
	 * Accept only absolute http(s) URLs with a host; strip query/fragment.
	 *
	 * @param mixed $url Candidate base from filter or default.
	 * @return string|null Sanitized origin+path without trailing concerns, or null.
	 */
	private static function sanitize_base( $url ) {
		if ( ! is_string( $url ) || '' === $url ) {
			return null;
		}
		$clean = esc_url_raw( $url, array( 'http', 'https' ) );
		if ( ! is_string( $clean ) || '' === $clean ) {
			return null;
		}
		$parts = wp_parse_url( $clean );
		if ( ! is_array( $parts ) ) {
			return null;
		}
		$scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : '';
		$host   = isset( $parts['host'] ) ? (string) $parts['host'] : '';
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || '' === $host ) {
			return null;
		}
		$path = isset( $parts['path'] ) ? (string) $parts['path'] : '/';
		$port = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
		return $scheme . '://' . $host . $port . $path;
	}
}
