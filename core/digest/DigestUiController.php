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
		$request            = wp_unslash( $_POST );
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
		$checkboxes         = array();

		foreach ( $digest_definitions as $digest_key => $digest_definition ) {
			$option_key   = self::get_option_key( $digest_key );
			$checkboxes[] = array(
				'name'             => $option_key,
				'label'            => self::get_digest_label( $digest_key ),
				'checked'          => (bool) Config::get( $option_key ),
				'interval_seconds' => isset( $digest_definition['interval_seconds'] ) ? (int) $digest_definition['interval_seconds'] : 0,
			);
		}

		return $checkboxes;
	}

	/**
	 * Translated digest label for UI and email subject.
	 *
	 * @param string $digest_key Digest definition key (daily/weekly/monthly).
	 * @return string
	 */
	public static function get_digest_label( $digest_key ) {
		$digest_key = sanitize_key( (string) $digest_key );

		switch ( $digest_key ) {
			case 'daily':
				return __( 'Daily', 'limit-login-attempts-reloaded' );
			case 'weekly':
				return __( 'Weekly', 'limit-login-attempts-reloaded' );
			case 'monthly':
				return __( 'Monthly', 'limit-login-attempts-reloaded' );
		}

		return ucfirst( $digest_key );
	}

	/**
	 * Translated digest email preheader for inbox preview snippet.
	 *
	 * @param string $digest_key Digest definition key (daily/weekly/monthly).
	 * @return string
	 */
	public static function get_digest_preview_text( $digest_key ) {
		$digest_key = sanitize_key( (string) $digest_key );

		switch ( $digest_key ) {
			case 'daily':
				return __( 'Daily digest of lockouts, top IPs, and what to review next.', 'limit-login-attempts-reloaded' );
			case 'weekly':
				return __( 'Weekly digest of lockouts, top IPs, and what to review next.', 'limit-login-attempts-reloaded' );
			case 'monthly':
				return __( 'Monthly digest of lockouts, top IPs, and what to review next.', 'limit-login-attempts-reloaded' );
		}

		return '';
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
	 * @param string $digest_key Digest definition key (daily/weekly/monthly).
	 * @return string
	 */
	private static function get_option_key( $digest_key ) {
		return 'digest_' . $digest_key;
	}
}
