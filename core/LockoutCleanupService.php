<?php
/**
 * Lockout Cleanup Service
 *
 * @package LimitLoginAttempts
 * @since 3.3.0
 */

namespace LLAR\Core;

use LLAR\Core\Utils\RiskLevelMath;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cleans up old lockouts and retries.
 */
class LockoutCleanupService {

	/**
	 * Clean up old lockouts and retries, and save supplied arrays.
	 *
	 * @param array|null $retries  Retries array or null to load from config.
	 * @param array|null $lockouts Lockouts array or null to load from config.
	 * @param array|null $valid    Valid-until array or null to load from config.
	 * @return void
	 */
	public function cleanup( $retries = null, $lockouts = null, $valid = null ) {
		$now               = time();
		$lockouts_provided = ! is_null( $lockouts );
		$lockouts          = $lockouts_provided ? $lockouts : Config::get( Config::OPTION_LOCKOUTS );
		$log               = Config::get( Config::OPTION_LOGGED );

		if ( is_array( $lockouts ) ) {
			$expired_keys = array();
			foreach ( $lockouts as $ip => $lockout ) {
				if ( $lockout < $now ) {
					unset( $lockouts[ $ip ] );
					$expired_keys[ $ip ] = true;
				}
			}

			// New lockouts are persisted only through this path
			// (record_failed_login_attempt passes the mutated map). Skip the
			// write only when we loaded lockouts ourselves and nothing expired.
			if ( $lockouts_provided || ! empty( $expired_keys ) ) {
				Config::update( Config::OPTION_LOCKOUTS, $lockouts );
			}

			// Log rows are keyed by the raw client IP; lockout keys may be the
			// /64 network. One pass over the log (not O(expired × log)).
			if ( ! empty( $expired_keys ) && is_array( $log ) ) {
				foreach ( $log as $log_ip => &$users ) {
					if ( ! is_array( $users ) ) {
						continue;
					}
					$norm = Helpers::normalize_ip_for_rate_limit( $log_ip );
					if ( ! isset( $expired_keys[ $log_ip ] ) && ! isset( $expired_keys[ $norm ] ) ) {
						continue;
					}
					foreach ( $users as $user_login => &$data ) {
						if ( ! is_array( $data ) ) {
							$data = array();
						}
						$data['unlocked'] = true;
					}
					unset( $data );
				}
				unset( $users );
			}
		}

		Config::update( Config::OPTION_LOGGED, $log );

		$valid   = ! is_null( $valid ) ? $valid : Config::get( 'retries_valid' );
		$retries = ! is_null( $retries ) ? $retries : Config::get( 'retries' );

		if ( ! is_array( $valid ) || ! is_array( $retries ) ) {
			return;
		}

		foreach ( $valid as $ip => $lockout ) {
			if ( $lockout < $now ) {
				unset( $valid[ $ip ] );
				unset( $retries[ $ip ] );
			}
		}

		foreach ( $retries as $ip => $retry ) {
			if ( ! isset( $valid[ $ip ] ) ) {
				unset( $retries[ $ip ] );
			}
		}

		$retries_stats = Config::get( 'retries_stats' );

		if ( $retries_stats ) {
			$stats_cutoff = strtotime( '-8 day' );
			foreach ( $retries_stats as $key => $count ) {
				if ( RiskLevelMath::is_retries_stats_bucket_expired( $key, $stats_cutoff ) ) {
					unset( $retries_stats[ $key ] );
				}
			}

			Config::update( 'retries_stats', $retries_stats );
		}

		Config::update( 'retries', $retries );
		Config::update( 'retries_valid', $valid );
	}
}
