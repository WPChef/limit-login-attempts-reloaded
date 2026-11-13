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
