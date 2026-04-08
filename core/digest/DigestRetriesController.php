<?php

namespace LLAR\Core\Digest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DigestRetriesController {
	/**
	 * Save one failed attempt into daily digest storage.
	 *
	 * @return void
	 */
	public static function save_failed_attempt( $ip, $username, $login_url ) {
		$day_start_ts = self::get_day_start_ts();

		DigestStorage::increment_failed_attempts( $day_start_ts, 1 );
		DigestStorage::track_failed_attempt( $day_start_ts, $ip, $username, $login_url );
	}

	/**
	 * Save one lockout event into daily digest storage.
	 *
	 * @param string $ip Attacker IP.
	 * @param string $login_url Request URL.
	 * @return void
	 */
	public static function save_lockout( $ip, $login_url ) {
		$day_start_ts = self::get_day_start_ts();

		DigestStorage::increment_lockouts( $day_start_ts, 1 );
		DigestStorage::track_lockout( $day_start_ts, $ip, $login_url );
	}

	/**
	 * Get start-of-day timestamp in site timezone.
	 *
	 * @return int
	 */
	private static function get_day_start_ts() {
		return strtotime( current_time( 'Y-m-d 00:00:00' ) );
	}
}
