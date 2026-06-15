<?php

namespace LLAR\Core\Digest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DigestStorage {
	const POST_TYPE                  = 'llar_digest_day';
	const CLEANUP_TRANSIENT_KEY      = 'llar_digest_storage_cleanup_lock';
	const META_DAY_TS                = '_llar_digest_day_ts';
	const META_LOCKOUTS_COUNT        = '_llar_digest_lockouts_count';
	const META_FAILED_ATTEMPTS_COUNT = '_llar_digest_failed_attempts_count';
	const META_IP_STATS              = '_llar_digest_ip_stats';
	const META_USERNAME_STATS        = '_llar_digest_username_stats';

	/**
	 * Register internal CPT for daily digest stats.
	 *
	 * @return void
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Digest Days', 'limit-login-attempts-reloaded' ),
					'singular_name' => __( 'Digest Day', 'limit-login-attempts-reloaded' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_admin_bar'   => false,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'has_archive'         => false,
				'query_var'           => false,
				'rewrite'             => false,
				'map_meta_cap'        => true,
				'supports'            => array( 'custom-fields' ),
			)
		);
	}

	/**
	 * Get or create daily digest post.
	 *
	 * @param int $day_ts Start-of-day timestamp in UTC.
	 * @return int Post ID or 0 on failure.
	 */
	public static function get_or_create_day_post( $day_ts ) {
		$day_ts = (int) $day_ts;
		$slug   = gmdate( 'Y-m-d', $day_ts );
		$post   = get_page_by_path( $slug, OBJECT, self::POST_TYPE );

		if ( $post && ! empty( $post->ID ) ) {
			$post_id       = (int) $post->ID;
			$stored_day_ts = (int) get_post_meta( $post_id, self::META_DAY_TS, true );
			if ( $stored_day_ts === $day_ts ) {
				return $post_id;
			}
		}

		$post_id = self::find_day_post_id_by_meta( $day_ts );
		if ( $post_id > 0 ) {
			return $post_id;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'private',
				'post_title'  => $slug,
				'post_name'   => $slug,
			),
			true
		);

		if ( is_wp_error( $post_id ) || empty( $post_id ) ) {
			return 0;
		}

		update_post_meta( $post_id, self::META_DAY_TS, (int) $day_ts );
		update_post_meta( $post_id, self::META_LOCKOUTS_COUNT, 0 );
		update_post_meta( $post_id, self::META_FAILED_ATTEMPTS_COUNT, 0 );
		update_post_meta( $post_id, self::META_IP_STATS, array() );
		update_post_meta( $post_id, self::META_USERNAME_STATS, array() );

		return (int) $post_id;
	}

	/**
	 * Increment failed attempts counter for a day.
	 *
	 * @param int $day_ts Start-of-day timestamp in UTC.
	 * @param int $delta Increment value.
	 * @return void
	 */
	public static function increment_failed_attempts( $day_ts, $delta ) {
		$post_id = self::get_or_create_day_post( $day_ts );

		if ( ! $post_id ) {
			return;
		}

		self::increment_counter_meta( $post_id, self::META_FAILED_ATTEMPTS_COUNT, $delta );
	}

	/**
	 * Increment lockouts counter for a day.
	 *
	 * @param int $day_ts Start-of-day timestamp in UTC.
	 * @param int $delta Increment value.
	 * @return void
	 */
	public static function increment_lockouts( $day_ts, $delta ) {
		self::cleanup_expired_history();

		$post_id = self::get_or_create_day_post( $day_ts );

		if ( ! $post_id ) {
			return;
		}

		self::increment_counter_meta( $post_id, self::META_LOCKOUTS_COUNT, $delta );
	}

	/**
	 * Delete digest day rows older than retention period.
	 *
	 * @return void
	 */
	private static function cleanup_expired_history() {
		if ( get_transient( self::CLEANUP_TRANSIENT_KEY ) ) {
			return;
		}

		set_transient( self::CLEANUP_TRANSIENT_KEY, 1, DAY_IN_SECONDS );

		$retention_days = defined( 'LLA_LOCKOUT_HISTORY_RETENTION_DAYS' )
			? max( 1, (int) LLA_LOCKOUT_HISTORY_RETENTION_DAYS )
			: 60;
		$cutoff_ts      = (int) current_time( 'timestamp' ) - ( $retention_days * DAY_IN_SECONDS );
		$expired_ids    = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'private',
				'fields'         => 'ids',
				'posts_per_page' => 200,
				'no_found_rows'  => true,
				'meta_key'       => self::META_DAY_TS,
				'meta_query'     => array(
					array(
						'key'     => self::META_DAY_TS,
						'value'   => (int) $cutoff_ts,
						'compare' => '<',
						'type'    => 'NUMERIC',
					),
				),
			)
		);

		foreach ( $expired_ids as $expired_id ) {
			wp_delete_post( (int) $expired_id, true );
		}
	}

	/**
	 * Track failed attempt dimensions for digest email data.
	 *
	 * @param int    $day_ts Start-of-day timestamp.
	 * @param string $ip Attacker IP.
	 * @param string $username Target username.
	 * @param string $gateway  Gateway alias.
	 * @return void
	 */
	public static function track_failed_attempt( $day_ts, $ip, $username, $gateway ) {
		$post_id = self::get_or_create_day_post( $day_ts );

		if ( ! $post_id ) {
			return;
		}

		$ip       = sanitize_text_field( (string) $ip );
		$username = sanitize_user( (string) $username, true );
		$gateway  = sanitize_key( (string) $gateway );

		if ( '' !== $ip ) {
			$ip_stats = get_post_meta( $post_id, self::META_IP_STATS, true );
			$ip_stats = is_array( $ip_stats ) ? $ip_stats : array();
			if ( empty( $ip_stats[ $ip ] ) || ! is_array( $ip_stats[ $ip ] ) ) {
				$ip_stats[ $ip ] = array(
					'attempts' => 0,
					'lockouts' => 0,
				);
			}
			$ip_stats[ $ip ]['attempts']  = (int) $ip_stats[ $ip ]['attempts'] + 1;
			$ip_stats[ $ip ]['last_seen'] = time();
			if ( '' !== $gateway ) {
				$ip_stats[ $ip ]['gateway'] = $gateway;
			}
			update_post_meta( $post_id, self::META_IP_STATS, $ip_stats );
		}

		if ( '' !== $username ) {
			$username_stats              = get_post_meta( $post_id, self::META_USERNAME_STATS, true );
			$username_stats              = is_array( $username_stats ) ? $username_stats : array();
			$username_stats[ $username ] = isset( $username_stats[ $username ] ) ? (int) $username_stats[ $username ] + 1 : 1;
			update_post_meta( $post_id, self::META_USERNAME_STATS, $username_stats );
		}
	}

	/**
	 * Track lockout event dimensions.
	 *
	 * @param int    $day_ts Start-of-day timestamp.
	 * @param string $ip Attacker IP.
	 * @param string $gateway Gateway alias.
	 * @return void
	 */
	public static function track_lockout( $day_ts, $ip, $gateway ) {
		$post_id = self::get_or_create_day_post( $day_ts );

		if ( ! $post_id ) {
			return;
		}

		$ip      = sanitize_text_field( (string) $ip );
		$gateway = sanitize_key( (string) $gateway );
		if ( '' === $ip ) {
			return;
		}

		$ip_stats = get_post_meta( $post_id, self::META_IP_STATS, true );
		$ip_stats = is_array( $ip_stats ) ? $ip_stats : array();
		if ( empty( $ip_stats[ $ip ] ) || ! is_array( $ip_stats[ $ip ] ) ) {
			$ip_stats[ $ip ] = array(
				'attempts' => 0,
				'lockouts' => 0,
			);
		}
		$ip_stats[ $ip ]['lockouts']  = (int) $ip_stats[ $ip ]['lockouts'] + 1;
		$ip_stats[ $ip ]['last_seen'] = time();
		if ( '' !== $gateway ) {
			$ip_stats[ $ip ]['gateway'] = $gateway;
		}
		update_post_meta( $post_id, self::META_IP_STATS, $ip_stats );
	}

	/**
	 * Find daily digest post ID by day timestamp meta.
	 *
	 * @param int $day_ts Start-of-day timestamp.
	 * @return int
	 */
	private static function find_day_post_id_by_meta( $day_ts ) {
		$query = new \WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'private',
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				'meta_key'       => self::META_DAY_TS,
				'meta_value'     => (int) $day_ts,
				'meta_compare'   => '=',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		if ( ! empty( $query->posts[0] ) ) {
			return (int) $query->posts[0];
		}

		return 0;
	}

	/**
	 * Atomically increment integer counter meta.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Target meta key.
	 * @param int    $delta    Increment value.
	 * @return void
	 */
	private static function increment_counter_meta( $post_id, $meta_key, $delta ) {
		global $wpdb;

		$delta = (int) $delta;
		if ( 0 === $delta ) {
			return;
		}

		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta}
				SET meta_value = meta_value + %d
				WHERE post_id = %d AND meta_key = %s",
				$delta,
				(int) $post_id,
				(string) $meta_key
			)
		);

		if ( 0 !== (int) $updated ) {
			return;
		}

		$added = add_post_meta( (int) $post_id, (string) $meta_key, $delta, true );
		if ( $added ) {
			return;
		}

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta}
				SET meta_value = meta_value + %d
				WHERE post_id = %d AND meta_key = %s",
				$delta,
				(int) $post_id,
				(string) $meta_key
			)
		);
	}
}
