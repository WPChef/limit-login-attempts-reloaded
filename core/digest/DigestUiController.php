<?php

namespace LLAR\Core\Digest;

use LLAR\Core\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DigestUiController {
	/**
	 * Bootstrap digest defaults and one-time compatibility migration.
	 *
	 * Preserve existing Real-time state only for installs that already had
	 * an explicit lockout notify value in DB before digest options existed.
	 *
	 * @return void
	 */
	public static function bootstrap_defaults() {
		if ( Config::exists( 'digest_realtime' ) || ! Config::exists( 'lockout_notify' ) ) {
			return;
		}

		$notify_methods = explode( ',', (string) Config::get( 'lockout_notify' ) );
		$is_realtime_enabled = in_array( 'email', $notify_methods, true ) ? 1 : 0;
		Config::update( 'digest_realtime', $is_realtime_enabled );
	}

	/**
	 * Save digest checkbox values from settings request.
	 *
	 * @return void
	 */
	public static function save_settings_from_request() {
		$request = wp_unslash( $_POST );
		$digest_definitions = self::get_definitions();

		foreach ( $digest_definitions as $digest_key => $digest_definition ) {
			$option_key = self::get_option_key( $digest_key );
			Config::update( $option_key, isset( $request[ $option_key ] ) ? 1 : 0 );
		}
	}

	/**
	 * Build digest checkbox config for settings view.
	 *
	 * @return array
	 */
	public static function get_notification_checkboxes() {
		$digest_definitions = self::get_definitions();
		$checkboxes = array();

		foreach ( $digest_definitions as $digest_key => $digest_definition ) {
			$option_key = self::get_option_key( $digest_key );
			$checkboxes[] = array(
				'name' => $option_key,
				'label' => isset( $digest_definition['name'] ) ? (string) $digest_definition['name'] : $digest_key,
				'checked' => (bool) Config::get( $option_key ),
				'interval_seconds' => isset( $digest_definition['interval_seconds'] ) ? (int) $digest_definition['interval_seconds'] : 0,
			);
		}

		return $checkboxes;
	}

	/**
	 * Read digest definitions from plugin constant.
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
	 * @param string $digest_key Digest definition key (realtime/daily/weekly/monthly).
	 * @return string
	 */
	private static function get_option_key( $digest_key ) {
		return 'digest_' . $digest_key;
	}
}
