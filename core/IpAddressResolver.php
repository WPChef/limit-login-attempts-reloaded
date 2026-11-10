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
	 * Rate-limit / lockout table key for the current (or given) client IP.
	 *
	 * @param string|null $ip Raw IP, or null for the current request.
	 * @return string
	 */
	public function get_rate_limit_key( $ip = null ) {
		if ( is_null( $ip ) ) {
			$ip = $this->get_address();
		}

		return Helpers::normalize_ip_for_rate_limit( $ip );
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

		return ( true === $whitelisted );
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

		return ( true === $blacklisted );
	}
}
