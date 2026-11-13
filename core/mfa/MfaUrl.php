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
	 * Absolute base (trailing slash) for send-code / rescue.
	 * Defaults to site_url( '/' ): home_url() can drift (www / CDN / language domain)
	 * while the handshake transient lives on the siteurl host. Filterable for
	 * headless / Bedrock / proxy layouts.
	 *
	 * @return string
	 */
	public static function base() {
		/**
		 * Filter the MFA public base URL (send-code REST, rescue links).
		 *
		 * @param string $base Absolute URL, typically site_url( '/' ).
		 */
		return (string) apply_filters( 'llar_mfa_base_url', site_url( '/' ) );
	}
}
