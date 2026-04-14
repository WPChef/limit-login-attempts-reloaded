<?php

namespace LLAR\Core\Digest;

use LLAR\Core\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DigestScheduler {
	const HOOK_PREFIX = 'llar_cron_digest_';
	const SCHEDULE_PREFIX = 'llar_schedule_digest_';

	/**
	 * Register scheduler hooks.
	 *
	 * @return void
	 */
	public static function bootstrap() {
		add_filter( 'cron_schedules', array( __CLASS__, 'register_cron_schedules' ) );
		add_action( 'init', array( __CLASS__, 'sync_scheduled_events' ), 20 );

		foreach ( self::get_definitions() as $digest_key => $digest_definition ) {
			if ( empty( $digest_definition['interval_seconds'] ) ) {
				continue;
			}

			add_action( self::get_event_hook( $digest_key ), array( __CLASS__, 'handle_scheduled_digest' ) );
		}
	}

	/**
	 * Add custom cron intervals for digest definitions.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public static function register_cron_schedules( $schedules ) {
		foreach ( self::get_definitions() as $digest_key => $digest_definition ) {
			$interval_seconds = isset( $digest_definition['interval_seconds'] ) ? (int) $digest_definition['interval_seconds'] : 0;

			if ( $interval_seconds <= 0 ) {
				continue;
			}

			$schedule_name = self::get_schedule_name( $digest_key );
			$schedules[ $schedule_name ] = array(
				'interval' => $interval_seconds,
				'display'  => sprintf( 'LLAR Digest %s', ucfirst( (string) $digest_key ) ),
			);
		}

		return $schedules;
	}

	/**
	 * Create or remove cron events to match digest toggles.
	 *
	 * @return void
	 */
	public static function sync_scheduled_events() {
		foreach ( self::get_definitions() as $digest_key => $digest_definition ) {
			$interval_seconds = isset( $digest_definition['interval_seconds'] ) ? (int) $digest_definition['interval_seconds'] : 0;

			if ( $interval_seconds <= 0 ) {
				continue;
			}

			$event_hook = self::get_event_hook( $digest_key );
			$next_run = wp_next_scheduled( $event_hook, array( $digest_key ) );
			$is_enabled = (bool) Config::get( self::get_option_key( $digest_key ) );

			if ( ! $is_enabled ) {
				if ( false !== $next_run ) {
					wp_clear_scheduled_hook( $event_hook, array( $digest_key ) );
				}
				continue;
			}

			if ( false === $next_run ) {
				wp_schedule_event(
					self::get_first_run_timestamp( $digest_key, $interval_seconds ),
					self::get_schedule_name( $digest_key ),
					$event_hook,
					array( $digest_key )
				);
			}
		}
	}

	/**
	 * Forward scheduled digest event to dedicated and generic hooks.
	 *
	 * @param string $digest_key Digest key.
	 * @return void
	 */
	public static function handle_scheduled_digest( $digest_key ) {
		$digest_key = sanitize_key( (string) $digest_key );

		if ( '' === $digest_key ) {
			return;
		}

		if ( ! (bool) Config::get( self::get_option_key( $digest_key ) ) ) {
			return;
		}

		do_action( 'llar_digest_dispatch', $digest_key );
		do_action( 'llar_digest_dispatch_' . $digest_key, $digest_key );
	}

	/**
	 * Compute first run timestamp.
	 *
	 * @param int    $interval_seconds Interval in seconds.
	 * @return int
	 */
	private static function get_first_run_timestamp( $digest_key, $interval_seconds ) {
		$interval_seconds = max( 60, (int) $interval_seconds );
		$now = current_time( 'timestamp' );
		$remainder = $now % $interval_seconds;

		if ( 0 === $remainder ) {
			return $now + $interval_seconds;
		}

		return $now + ( $interval_seconds - $remainder );
	}

	/**
	 * Read digest definitions with filter support.
	 *
	 * @return array
	 */
	private static function get_definitions() {
		if ( ! is_array( LLA_DIGEST_DEFINITIONS ) ) {
			return array();
		}

		$definitions = apply_filters( 'llar_digest_definitions', LLA_DIGEST_DEFINITIONS );

		return is_array( $definitions ) ? $definitions : array();
	}

	/**
	 * Build option key from digest key.
	 *
	 * @param string $digest_key Digest key.
	 * @return string
	 */
	private static function get_option_key( $digest_key ) {
		return 'digest_' . $digest_key;
	}

	/**
	 * Build event hook from digest key.
	 *
	 * @param string $digest_key Digest key.
	 * @return string
	 */
	private static function get_event_hook( $digest_key ) {
		return self::HOOK_PREFIX . $digest_key;
	}

	/**
	 * Build schedule slug from digest key.
	 *
	 * @param string $digest_key Digest key.
	 * @return string
	 */
	private static function get_schedule_name( $digest_key ) {
		return self::SCHEDULE_PREFIX . $digest_key;
	}
}
