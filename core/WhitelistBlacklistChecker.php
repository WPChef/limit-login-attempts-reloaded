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
		$username = trim( (string) $username );
		if ( '' === $username ) {
			return false;
		}

		$whitelist_usernames = (array) Config::get( 'whitelist_usernames' );
		foreach ( $whitelist_usernames as $whitelist_username ) {
			if ( 0 === strcasecmp( $username, trim( (string) $whitelist_username ) ) ) {
				return true;
			}
		}

		return false;
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
		$username = trim( (string) $username );
		if ( '' === $username ) {
			return false;
		}

		$blacklist_usernames = (array) Config::get( 'blacklist_usernames' );
		foreach ( $blacklist_usernames as $blacklist_username ) {
			if ( 0 === strcasecmp( $username, trim( (string) $blacklist_username ) ) ) {
				return true;
			}
		}

		return false;
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

	/**
	 * Determine if submitted login identifier maps to local allowed usernames.
	 *
	 * Supports direct username, canonical user_login and email-based login.
	 *
	 * @param string   $username Submitted login value (username or email).
	 * @param \WP_User $user     Optional authenticated user object.
	 * @return bool
	 */
	public function is_local_allowlisted_username( $username, $user = null ) {
		$username = trim( (string) $username );
		if ( '' !== $username && $this->is_username_whitelisted( $username ) ) {
			return true;
		}

		if ( is_a( $user, 'WP_User' ) && ! empty( $user->user_login ) && $this->is_username_whitelisted( $user->user_login ) ) {
			return true;
		}

		if ( '' === $username || ! function_exists( 'is_email' ) || ! is_email( $username ) ) {
			return false;
		}

		$user_by_email = get_user_by( 'email', $username );
		if ( ! $user_by_email || ! is_a( $user_by_email, 'WP_User' ) ) {
			return false;
		}

		return $this->is_username_whitelisted( $user_by_email->user_login );
	}

	/**
	 * Determine if the submitted login identifier is on the local denylist.
	 *
	 * Matches the submitted string only (case-insensitive via the blacklist
	 * filter). Does not map email → user_login: a deny-listed username must be
	 * entered as that username; logging in with the account email is allowed
	 * unless the email itself is on the denylist. Case variants of a deny-listed
	 * username are still blocked. The $user argument is kept for call-site
	 * compatibility and is unused.
	 *
	 * @param string        $username Submitted login value (username or email).
	 * @param \WP_User|null $user     Unused; retained for signature compatibility.
	 * @return bool
	 */
	public function is_local_blacklisted_username( $username, $user = null ) {
		$username = trim( (string) $username );
		if ( '' === $username ) {
			return false;
		}

		return $this->is_username_blacklisted( $username );
	}
}
