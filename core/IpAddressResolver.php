<?php

namespace LLAR\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves client IP and IP allow/deny filters.
 */
class IpAddressResolver {

	/**
	 * @return string
	 */
	public function get_address() {
		return Helpers::detect_ip_address( Config::get( 'trusted_ip_origins' ) );
	}

	/**
	 * @param string|null $ip IP or null for current request IP.
	 * @return bool
	 */
	public function is_ip_whitelisted( $ip = null ) {
		if ( is_null( $ip ) ) {
			$ip = $this->get_address();
		}

		$whitelisted = apply_filters( 'limit_login_whitelist_ip', false, $ip );

		return ( $whitelisted === true );
	}

	/**
	 * @param string|null $ip IP or null for current request IP.
	 * @return bool
	 */
	public function is_ip_blacklisted( $ip = null ) {
		if ( is_null( $ip ) ) {
			$ip = $this->get_address();
		}

		$blacklisted = apply_filters( 'limit_login_blacklist_ip', false, $ip );

		return ( $blacklisted === true );
	}
}
