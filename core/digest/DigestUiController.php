<?php

namespace LLAR\Core\Digest;

use LLAR\Core\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DigestUiController {
	/**
	 * Save digest checkbox values from settings request.
	 *
	 * @return void
	 */
	public static function save_settings_from_request() {
		$request = wp_unslash( $_POST );
		$digest_option_keys = array(
			'digest_realtime',
			'digest_daily',
			'digest_weekly',
			'digest_monthly',
		);

		foreach ( $digest_option_keys as $option_key ) {
			Config::update( $option_key, isset( $request[ $option_key ] ) ? 1 : 0 );
		}
	}

	/**
	 * Build digest checkbox config for settings view.
	 *
	 * @return array
	 */
	public static function get_notification_checkboxes() {
		return array(
			array(
				'name' => 'digest_realtime',
				'label' => __( 'Real-time', 'limit-login-attempts-reloaded' ),
				'checked' => (bool) Config::get( 'digest_realtime' ),
				'interval_seconds' => 0,
			),
			array(
				'name' => 'digest_daily',
				'label' => __( 'Daily', 'limit-login-attempts-reloaded' ),
				'checked' => (bool) Config::get( 'digest_daily' ),
				'interval_seconds' => DAY_IN_SECONDS,
			),
			array(
				'name' => 'digest_weekly',
				'label' => __( 'Weekly', 'limit-login-attempts-reloaded' ),
				'checked' => (bool) Config::get( 'digest_weekly' ),
				'interval_seconds' => WEEK_IN_SECONDS,
			),
			array(
				'name' => 'digest_monthly',
				'label' => __( 'Monthly', 'limit-login-attempts-reloaded' ),
				'checked' => (bool) Config::get( 'digest_monthly' ),
				'interval_seconds' => MONTH_IN_SECONDS,
			),
		);
	}
}
