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
	 * Absolute base for send-code / rescue (normalized with a trailing slash).
	 * Defaults to site_url( '/' ): home_url() can drift (www / CDN / language domain)
	 * while the handshake transient lives on the siteurl host.
	 *
	 * Filter `llar_mfa_base_url` may retarget the base (headless / Bedrock / proxy).
	 * The result is published into handshake payloads and rescue emails — only
	 * trust theme / mu-plugin code that returns an absolute http(s) URL with a host.
	 * Invalid filter results fall back to site_url( '/' ).
	 *
	 * Note: send-code uses ?rest_route= on this base, so WordPress rest_url /
	 * rest_url_prefix filters no longer affect the published MFA send URL.
	 * Some WAF / security plugins treat ?rest_route= differently than /wp-json/.
	 *
	 * @return string Absolute URL with trailing slash (no query/fragment).
	 */
	public static function base() {
		/**
		 * Filter the MFA public base URL (send-code REST, rescue links).
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
	 * @param string $host Host or host:port.
	 * @return bool
	 */
	public static function is_allowed_public_host( $host ) {
		if ( ! is_string( $host ) || '' === $host ) {
			return false;
		}
		$key = strtolower( (string) preg_replace( '/:\d+$/', '', $host ) );
		return isset( self::allowed_public_hosts()[ $key ] );
	}

	/**
	 * OTP return URL after MFA: keep the path the user posted to (custom login /
	 * Woo / UM) on the request Host when that host is site or home; otherwise
	 * fall back to site origin + REQUEST_URI (never site_url( $request_uri ),
	 * which doubles a subdirectory when siteurl is e.g. /wp and REQUEST_URI
	 * already includes /wp/…).
	 *
	 * Host/port come from the matching site_url / home_url origin — never from
	 * an arbitrary port in HTTP_HOST (header is used only to pick which
	 * allowlisted host was requested).
	 *
	 * @param string $request_uri Sanitized REQUEST_URI (path + query).
	 * @return string Absolute URL.
	 */
	public static function request_login_url( $request_uri ) {
		if ( ! is_string( $request_uri ) || '' === $request_uri ) {
			return wp_login_url();
		}

		$host_header = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$host_only   = strtolower( (string) preg_replace( '/:\d+$/', '', $host_header ) );
		$matched     = self::public_origin_for_host( $host_only );
		if ( null !== $matched ) {
			return $matched . $request_uri;
		}

		$origin = self::site_origin();
		if ( null === $origin ) {
			return wp_login_url();
		}

		return $origin . $request_uri;
	}

	/**
	 * scheme://host[:port] from site_url() or home_url() for an allowlisted host.
	 * Port is taken from the matching configured URL, not from HTTP_HOST.
	 *
	 * @param string $host Hostname without port (lowercase).
	 * @return string|null
	 */
	private static function public_origin_for_host( $host ) {
		if ( ! is_string( $host ) || '' === $host ) {
			return null;
		}
		foreach ( array( site_url(), home_url() ) as $base ) {
			$origin = self::origin_from_url( $base );
			if ( null === $origin ) {
				continue;
			}
			$h = strtolower( (string) wp_parse_url( $origin, PHP_URL_HOST ) );
			if ( $h === $host ) {
				return $origin;
			}
		}
		return null;
	}

	/**
	 * scheme://host[:port] from site_url() — no path.
	 *
	 * @return string|null
	 */
	private static function site_origin() {
		return self::origin_from_url( site_url() );
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
		// Drop query/fragment so trailingslashit cannot corrupt ?a=1 into ?a=1/.
		return $scheme . '://' . $host . $port . $path;
	}
}
