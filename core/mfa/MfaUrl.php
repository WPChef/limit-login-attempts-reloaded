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
	 * Handshake / OTP return login URL.
	 *
	 * MFA API requires login_url hostname to equal send_email_url hostname
	 * exactly. send_email_url is built on self::base() (?rest_route=), so the
	 * authority here is always origin_from_url( base() ) — never home when it
	 * differs, never raw HTTP_HOST (request port under forced https).
	 *
	 * Path: request URI when Host equals the base host (custom / Hide Login on
	 * the same host); otherwise path+query from wp_login_url() remounted onto
	 * the base origin (avoids site_url( $uri ) doubling a subdirectory).
	 *
	 * @param string $request_uri Sanitized REQUEST_URI (path + query).
	 * @return string Absolute URL.
	 */
	public static function request_login_url( $request_uri ) {
		$origin = self::origin_from_url( self::base() );
		if ( null === $origin ) {
			$origin = self::origin_from_url( site_url() );
		}
		if ( null === $origin ) {
			return wp_login_url();
		}

		$base_host   = strtolower( (string) wp_parse_url( $origin, PHP_URL_HOST ) );
		$base_scheme = strtolower( (string) wp_parse_url( self::base(), PHP_URL_SCHEME ) );
		$host_header = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$host_only   = strtolower( (string) preg_replace( '/:\d+$/', '', $host_header ) );

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

		// Never downgrade https base (or TLS request) to http behind a proxy.
		if ( is_ssl() || 'https' === $base_scheme ) {
			$login_url = set_url_scheme( $login_url, 'https' );
		}

		return $login_url;
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
