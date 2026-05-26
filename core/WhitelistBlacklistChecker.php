<?php
/**
 * Whitelist and Blacklist Checker Service
 *
 * @package LimitLoginAttempts
 * @since 3.3.0
 */

namespace LLAR\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks IP and username against whitelist and blacklist.
 */
class WhitelistBlacklistChecker {

	/**
	 * @var IpAddressResolver
	 */
	private $ip_resolver;

	/**
	 * @param IpAddressResolver $ip_resolver IP resolver.
	 */
	public function __construct( IpAddressResolver $ip_resolver ) {
		$this->ip_resolver = $ip_resolver;
	}

	/**
	 * Check if IP is in whitelist.
	 *
	 * @param bool   $allow Ignored (for filter compatibility).
	 * @param string $ip    IP address.
	 * @return bool
	 */
	public function check_whitelist_ips( $allow, $ip ) {
		return Helpers::ip_in_range( $ip, (array) Config::get( 'whitelist' ) );
	}

	/**
	 * Check if username is in whitelist.
	 *
	 * @param bool   $allow    Ignored (for filter compatibility).
	 * @param string $username Username.
	 * @return bool
	 */
	public function check_whitelist_usernames( $allow, $username ) {
		return in_array( $username, (array) Config::get( 'whitelist_usernames' ), true );
	}

	/**
	 * Check if IP is in blacklist.
	 *
	 * @param bool   $allow Ignored (for filter compatibility).
	 * @param string $ip    IP address.
	 * @return bool
	 */
	public function check_blacklist_ips( $allow, $ip ) {
		return Helpers::ip_in_range( $ip, (array) Config::get( 'blacklist' ) );
	}

	/**
	 * Check if username is in blacklist.
	 *
	 * @param bool   $allow    Ignored (for filter compatibility).
	 * @param string $username Username.
	 * @return bool
	 */
	public function check_blacklist_usernames( $allow, $username ) {
		return in_array( $username, (array) Config::get( 'blacklist_usernames' ), true );
	}

	/**
	 * Check if username is whitelisted.
	 *
	 * @param string $username Username.
	 * @return bool
	 */
	public function is_username_whitelisted( $username ) {
		if ( empty( $username ) ) {
			return false;
		}

		$whitelisted = apply_filters( 'limit_login_whitelist_usernames', false, $username );

		return ( $whitelisted === true );
	}

	/**
	 * Check if username is blacklisted.
	 *
	 * @param string $username Username.
	 * @return bool
	 */
	public function is_username_blacklisted( $username ) {
		if ( empty( $username ) ) {
			return false;
		}

		$blacklisted = apply_filters( 'limit_login_blacklist_usernames', false, $username );

		return ( $blacklisted === true );
	}
}
