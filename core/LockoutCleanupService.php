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
		$now      = time();
		$lockouts = ! is_null( $lockouts ) ? $lockouts : Config::get( Config::OPTION_LOCKOUTS );
		$log      = Config::get( Config::OPTION_LOGGED );

		if ( is_array( $lockouts ) ) {
			foreach ( $lockouts as $ip => $lockout ) {
				if ( $lockout < $now ) {
					unset( $lockouts[ $ip ] );

					if ( is_array( $log ) && isset( $log[ $ip ] ) ) {
						foreach ( $log[ $ip ] as $user_login => &$data ) {
							if ( ! is_array( $data ) ) {
								$data = array();
							}
							$data['unlocked'] = true;
						}
					}
				}
			}
			Config::update( Config::OPTION_LOCKOUTS, $lockouts );
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
