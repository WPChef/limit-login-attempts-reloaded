<?php

namespace LLAR\Core\MfaFlow;

use LLAR\Core\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Logs MFA Flow events (API calls, callback, send_code) when WP_DEBUG is on.
 * Tracks usage counts for handshake, verify, send_code in Config for monitoring.
 * No sensitive data (tokens, codes, passwords) should be passed to log().
 */
class MfaFlowLogger {

	const PREFIX = 'LLAR MFA Flow:';

	/**
	 * Log a single event. Only writes when WP_DEBUG is defined and true.
	 *
	 * @param string $event   Event name (e.g. handshake, verify, callback, send_code).
	 * @param string $outcome Outcome (e.g. success, fail, session_expired).
	 * @param array  $extra   Optional extra context (no sensitive data).
	 */
	public static function log( $event, $outcome, $extra = array() ) {
		if ( ! ( defined( 'WP_DEBUG' ) && \WP_DEBUG ) ) {
			return;
		}
		$msg = self::PREFIX . ' ' . $event . ' ' . $outcome;
		if ( ! empty( $extra ) ) {
			$msg .= ' ' . \wp_json_encode( $extra );
		}
		error_log( $msg );
	}

	/**
	 * Increment usage counter for monitoring. Keys: handshake, verify, send_code.
	 *
	 * @param string $key Counter key.
	 */
	public static function increment_usage( $key ) {
		$allowed = array( 'handshake', 'verify', 'send_code' );
		if ( ! in_array( $key, $allowed, true ) ) {
			return;
		}
		$stats = Config::get( 'mfa_flow_stats', array() );
		if ( ! is_array( $stats ) ) {
			$stats = array( 'handshake' => 0, 'verify' => 0, 'send_code' => 0 );
		}
		foreach ( array( 'handshake', 'verify', 'send_code' ) as $k ) {
			if ( ! isset( $stats[ $k ] ) || ! is_numeric( $stats[ $k ] ) ) {
				$stats[ $k ] = 0;
			}
		}
		$stats[ $key ] = (int) $stats[ $key ] + 1;
		Config::update( 'mfa_flow_stats', $stats );
	}

	/**
	 * Log one line to WordPress debug log (when WP_DEBUG). No sensitive data.
	 *
	 * @param string $line Message (one line).
	 */
	public static function log_to_file( $line ) {
		if ( ! ( defined( 'WP_DEBUG' ) && \WP_DEBUG ) ) {
			return;
		}
		error_log( self::PREFIX . ' ' . $line );
	}
}
