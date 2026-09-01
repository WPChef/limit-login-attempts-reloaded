<?php

namespace LLAR\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure risk-level math for dashboard stats (no WP i18n).
 */
class RiskLevelMath {

	/**
	 * Whether a retries_stats bucket key is older than cutoff.
	 *
	 * @param mixed $key    Bucket key.
	 * @param int   $cutoff Unix timestamp cutoff.
	 * @return bool
	 */
	public static function is_retries_stats_bucket_expired( $key, $cutoff ) {
		if ( is_numeric( $key ) ) {
			return (int) $key < $cutoff;
		}
		$ts = strtotime( (string) $key );
		if ( false === $ts ) {
			return false;
		}
		return $ts < $cutoff;
	}

	/**
	 * Remove retries_stats buckets older than 8 days.
	 *
	 * @param array $retries_stats Stats keyed by time bucket.
	 * @return array
	 */
	public static function prune_retries_stats_old_buckets( $retries_stats ) {
		if ( ! is_array( $retries_stats ) || empty( $retries_stats ) ) {
			return $retries_stats;
		}

		$cutoff = strtotime( '-8 day' );
		foreach ( $retries_stats as $key => $count ) {
			if ( self::is_retries_stats_bucket_expired( $key, $cutoff ) ) {
				unset( $retries_stats[ $key ] );
			}
		}

		return $retries_stats;
	}

	/**
	 * Resolve risk level by retries count using configured ranges.
	 *
	 * @param int   $retries_count Retries count.
	 * @param array $levels        Risk levels config.
	 * @return array
	 */
	public static function resolve_risk_level( $retries_count, $levels ) {
		$default_level = null;

		foreach ( $levels as $level ) {
			if ( isset( $level['exact'] ) && (int) $level['exact'] === $retries_count ) {
				return $level;
			}

			if ( isset( $level['max_exclusive'] ) && (int) $level['max_exclusive'] > $retries_count ) {
				return $level;
			}

			if ( ! empty( $level['default'] ) ) {
				$default_level = $level;
			}
		}

		return null !== $default_level ? $default_level : array();
	}

	/**
	 * Resolve chart color key from matched level and color map.
	 *
	 * @param array $matched_level Matched level config.
	 * @param array $risk_colors   Color map.
	 * @return string Hex color.
	 */
	public static function resolve_chart_color( $matched_level, $risk_colors ) {
		$default_color = isset( $risk_colors['green'] ) ? $risk_colors['green'] : '#97F6C8';
		if ( isset( $matched_level['color'] ) && isset( $risk_colors[ $matched_level['color'] ] ) ) {
			return $risk_colors[ $matched_level['color'] ];
		}
		return $default_color;
	}
}
