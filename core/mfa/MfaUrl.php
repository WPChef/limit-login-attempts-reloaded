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
	 * trust theme / mu-plugin code that returns an absolute site-controlled URL.
	 * Empty or non-string filter results fall back to site_url( '/' ).
	 *
	 * Note: send-code uses ?rest_route= on this base, so WordPress rest_url /
	 * rest_url_prefix filters no longer affect the published MFA send URL.
	 *
	 * @return string Absolute URL with trailing slash.
	 */
	public static function base() {
		/**
		 * Filter the MFA public base URL (send-code REST, rescue links).
		 *
		 * @param string $base Absolute URL, typically site_url( '/' ).
		 */
		$base = apply_filters( 'llar_mfa_base_url', site_url( '/' ) );
		if ( ! is_string( $base ) || '' === $base ) {
			$base = site_url( '/' );
		}

		return trailingslashit( $base );
	}
}
