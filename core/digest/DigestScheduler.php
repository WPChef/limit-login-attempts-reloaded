<?php

namespace LLAR\Core\Digest;

use LLAR\Core\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DigestScheduler {
	const HOOK_PREFIX     = 'llar_cron_digest_';
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
			$interval_seconds = self::get_schedule_interval_seconds( $digest_key, $digest_definition );

			if ( $interval_seconds <= 0 ) {
				continue;
			}

			$schedule_name               = self::get_schedule_name( $digest_key );
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
		$is_local = 'local' === Config::get( Config::OPTION_ACTIVE_APP );

		foreach ( self::get_definitions() as $digest_key => $digest_definition ) {
			$event_hook = self::get_event_hook( $digest_key );

			if ( ! $is_local ) {
				wp_clear_scheduled_hook( $event_hook, array( $digest_key ) );
				continue;
			}

			$interval_seconds = self::get_schedule_interval_seconds( $digest_key, $digest_definition );

			if ( $interval_seconds <= 0 ) {
				continue;
			}

			$next_run   = wp_next_scheduled( $event_hook, array( $digest_key ) );
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

		if ( 'local' !== Config::get( Config::OPTION_ACTIVE_APP ) ) {
			return;
		}

		if ( ! (bool) Config::get( self::get_option_key( $digest_key ) ) ) {
			return;
		}
		if ( ! self::should_dispatch_today( $digest_key ) ) {
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
		$now_local         = (int) current_time( 'timestamp' );
		$today_dispatch_ts = gmmktime(
			LLA_DIGEST_DISPATCH_HOUR_LOCAL,
			0,
			0,
			(int) gmdate( 'n', $now_local ),
			(int) gmdate( 'j', $now_local ),
			(int) gmdate( 'Y', $now_local )
		);

		if ( 'weekly' === $digest_key ) {
			$weekday           = (int) gmdate( 'N', $now_local ); // 1=Mon..7=Sun
			$days_until_monday = ( 8 - $weekday ) % 7;

			if ( 0 === $days_until_monday && $now_local < $today_dispatch_ts ) {
				return $today_dispatch_ts;
			}

			if ( 0 === $days_until_monday ) {
				$days_until_monday = 7;
			}

			return $today_dispatch_ts + ( $days_until_monday * DAY_IN_SECONDS );
		}

		if ( 'monthly' === $digest_key ) {
			$day_of_month = (int) gmdate( 'j', $now_local );

			if ( 1 === $day_of_month && $now_local < $today_dispatch_ts ) {
				return $today_dispatch_ts;
			}

			return gmmktime(
				LLA_DIGEST_DISPATCH_HOUR_LOCAL,
				0,
				0,
				(int) gmdate( 'n', $now_local ) + 1,
				1,
				(int) gmdate( 'Y', $now_local )
			);
		}

		// Daily.
		if ( $now_local < $today_dispatch_ts ) {
			return $today_dispatch_ts;
		}

		return $today_dispatch_ts + DAY_IN_SECONDS;
	}

	/**
	 * Use daily cron checks for all digest types.
	 * Weekly/monthly are calendar-gated in handle_scheduled_digest().
	 *
	 * @param string $digest_key Digest key.
	 * @param array  $digest_definition Digest definition.
	 * @return int
	 */
	private static function get_schedule_interval_seconds( $digest_key, $digest_definition ) {
		$interval_seconds = isset( $digest_definition['interval_seconds'] ) ? (int) $digest_definition['interval_seconds'] : 0;
		if ( $interval_seconds <= 0 ) {
			return 0;
		}

		return DAY_IN_SECONDS;
	}

	/**
	 * Check if digest should be dispatched on current local date.
	 *
	 * @param string $digest_key Digest key.
	 * @return bool
	 */
	private static function should_dispatch_today( $digest_key ) {
		$now_local = (int) current_time( 'timestamp' );

		if ( 'weekly' === $digest_key ) {
			return 1 === (int) gmdate( 'N', $now_local ); // Monday
		}

		if ( 'monthly' === $digest_key ) {
			return 1 === (int) gmdate( 'j', $now_local ); // First day of month
		}

		return true;
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
