<?php

namespace LLAR\Core\Digest;

use LLAR\Core\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DigestRetriesController {
	/**
	 * Save one failed attempt into daily digest storage.
	 * Raw per-IP and per-username counters are stored for later aggregation.
	 *
	 * @return void
	 */
	public static function save_failed_attempt( $ip, $username ) {
		$day_start_ts = self::get_day_start_ts();
		$gateway      = Helpers::detect_gateway();

		DigestStorage::increment_failed_attempts( $day_start_ts, 1 );
		DigestStorage::track_failed_attempt( $day_start_ts, $ip, $username, $gateway );
	}

	/**
	 * Save one lockout event into daily digest storage.
	 *
	 * @param string $ip Attacker IP.
	 * @return void
	 */
	public static function save_lockout( $ip ) {
		$day_start_ts = self::get_day_start_ts();
		$gateway      = Helpers::detect_gateway();

		DigestStorage::increment_lockouts( $day_start_ts, 1 );
		DigestStorage::track_lockout( $day_start_ts, $ip, $gateway );
	}

	/**
	 * Get start-of-day timestamp in site local timezone.
	 *
	 * @return int
	 */
	private static function get_day_start_ts() {
		$now_local = (int) current_time( 'timestamp' );

		return gmmktime(
			0,
			0,
			0,
			(int) gmdate( 'n', $now_local ),
			(int) gmdate( 'j', $now_local ),
			(int) gmdate( 'Y', $now_local )
		);
	}
}
