<?php

namespace LLAR\Core\Digest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DigestStorage {
	const POST_TYPE = 'llar_digest_day';
	const META_DAY_TS = '_llar_digest_day_ts';
	const META_LOCKOUTS_COUNT = '_llar_digest_lockouts_count';
	const META_FAILED_ATTEMPTS_COUNT = '_llar_digest_failed_attempts_count';
	const META_UNIQUE_ATTACKER_IPS = '_llar_digest_unique_attacker_ips';
	const META_UNIQUE_USERNAMES = '_llar_digest_unique_usernames';
	const META_MOST_ATTEMPTED_IP = '_llar_digest_most_attempted_ip';
	const META_TOP_IPS = '_llar_digest_top_ips';
	const META_TOP_USERNAMES = '_llar_digest_top_usernames';

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
	 * @param int $day_ts Start-of-day timestamp in site timezone.
	 * @return int Post ID or 0 on failure.
	 */
	public static function get_or_create_day_post( $day_ts ) {
		$slug = gmdate( 'Y-m-d', $day_ts );
		$post = get_page_by_path( $slug, OBJECT, self::POST_TYPE );

		if ( $post && ! empty( $post->ID ) ) {
			return (int) $post->ID;
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
		update_post_meta( $post_id, self::META_UNIQUE_ATTACKER_IPS, array() );
		update_post_meta( $post_id, self::META_UNIQUE_USERNAMES, array() );
		update_post_meta( $post_id, self::META_TOP_IPS, array() );
		update_post_meta( $post_id, self::META_TOP_USERNAMES, array() );
		update_post_meta( $post_id, self::META_MOST_ATTEMPTED_IP, '' );

		return (int) $post_id;
	}

	/**
	 * Increment failed attempts counter for a day.
	 *
	 * @param int $day_ts Start-of-day timestamp in site timezone.
	 * @param int $delta Increment value.
	 * @return void
	 */
	public static function increment_failed_attempts( $day_ts, $delta ) {
		$post_id = self::get_or_create_day_post( $day_ts );

		if ( ! $post_id ) {
			return;
		}

		$current_value = (int) get_post_meta( $post_id, self::META_FAILED_ATTEMPTS_COUNT, true );
		update_post_meta( $post_id, self::META_FAILED_ATTEMPTS_COUNT, $current_value + (int) $delta );
	}

	/**
	 * Increment lockouts counter for a day.
	 *
	 * @param int $day_ts Start-of-day timestamp in site timezone.
	 * @param int $delta Increment value.
	 * @return void
	 */
	public static function increment_lockouts( $day_ts, $delta ) {
		$post_id = self::get_or_create_day_post( $day_ts );

		if ( ! $post_id ) {
			return;
		}

		$current_value = (int) get_post_meta( $post_id, self::META_LOCKOUTS_COUNT, true );
		update_post_meta( $post_id, self::META_LOCKOUTS_COUNT, $current_value + (int) $delta );
	}

	/**
	 * Track failed attempt dimensions for digest email data.
	 *
	 * @param int    $day_ts Start-of-day timestamp.
	 * @param string $ip Attacker IP.
	 * @param string $username Target username.
	 * @param string $login_url Request URL.
	 * @return void
	 */
	public static function track_failed_attempt( $day_ts, $ip, $username, $login_url ) {
		$post_id = self::get_or_create_day_post( $day_ts );

		if ( ! $post_id ) {
			return;
		}

		$now_ts = current_time( 'timestamp' );
		$ip = sanitize_text_field( (string) $ip );
		$username = sanitize_user( (string) $username, true );
		$login_url = esc_url_raw( (string) $login_url );

		if ( '' !== $ip ) {
			$unique_ips = get_post_meta( $post_id, self::META_UNIQUE_ATTACKER_IPS, true );
			$unique_ips = is_array( $unique_ips ) ? $unique_ips : array();
			$unique_ips[ $ip ] = true;
			update_post_meta( $post_id, self::META_UNIQUE_ATTACKER_IPS, $unique_ips );

			$top_ips = get_post_meta( $post_id, self::META_TOP_IPS, true );
			$top_ips = is_array( $top_ips ) ? $top_ips : array();
			if ( empty( $top_ips[ $ip ] ) || ! is_array( $top_ips[ $ip ] ) ) {
				$top_ips[ $ip ] = array(
					'attempts' => 0,
					'lockouts' => 0,
					'last_seen' => 0,
					'top_url' => '',
				);
			}
			$top_ips[ $ip ]['attempts'] = (int) $top_ips[ $ip ]['attempts'] + 1;
			$top_ips[ $ip ]['last_seen'] = $now_ts;
			if ( '' !== $login_url ) {
				$top_ips[ $ip ]['top_url'] = $login_url;
			}
			update_post_meta( $post_id, self::META_TOP_IPS, $top_ips );

			$most_attempted_ip = self::detect_most_attempted_ip( $top_ips );
			update_post_meta( $post_id, self::META_MOST_ATTEMPTED_IP, $most_attempted_ip );
		}

		if ( '' !== $username ) {
			$unique_usernames = get_post_meta( $post_id, self::META_UNIQUE_USERNAMES, true );
			$unique_usernames = is_array( $unique_usernames ) ? $unique_usernames : array();
			$unique_usernames[ $username ] = true;
			update_post_meta( $post_id, self::META_UNIQUE_USERNAMES, $unique_usernames );

			$top_usernames = get_post_meta( $post_id, self::META_TOP_USERNAMES, true );
			$top_usernames = is_array( $top_usernames ) ? $top_usernames : array();
			$top_usernames[ $username ] = isset( $top_usernames[ $username ] ) ? (int) $top_usernames[ $username ] + 1 : 1;
			update_post_meta( $post_id, self::META_TOP_USERNAMES, $top_usernames );
		}
	}

	/**
	 * Track lockout event dimensions.
	 *
	 * @param int    $day_ts Start-of-day timestamp.
	 * @param string $ip Attacker IP.
	 * @param string $login_url Request URL.
	 * @return void
	 */
	public static function track_lockout( $day_ts, $ip, $login_url ) {
		$post_id = self::get_or_create_day_post( $day_ts );

		if ( ! $post_id ) {
			return;
		}

		$ip = sanitize_text_field( (string) $ip );
		$login_url = esc_url_raw( (string) $login_url );
		if ( '' === $ip ) {
			return;
		}

		$top_ips = get_post_meta( $post_id, self::META_TOP_IPS, true );
		$top_ips = is_array( $top_ips ) ? $top_ips : array();
		if ( empty( $top_ips[ $ip ] ) || ! is_array( $top_ips[ $ip ] ) ) {
			$top_ips[ $ip ] = array(
				'attempts' => 0,
				'lockouts' => 0,
				'last_seen' => 0,
				'top_url' => '',
			);
		}
		$top_ips[ $ip ]['lockouts'] = (int) $top_ips[ $ip ]['lockouts'] + 1;
		$top_ips[ $ip ]['last_seen'] = current_time( 'timestamp' );
		if ( '' !== $login_url ) {
			$top_ips[ $ip ]['top_url'] = $login_url;
		}
		update_post_meta( $post_id, self::META_TOP_IPS, $top_ips );
	}

	/**
	 * Detect most attempted IP in day aggregate.
	 *
	 * @param array $top_ips Aggregated ip data map.
	 * @return string
	 */
	private static function detect_most_attempted_ip( $top_ips ) {
		$winner_ip = '';
		$winner_attempts = -1;

		foreach ( $top_ips as $ip => $row ) {
			$attempts = isset( $row['attempts'] ) ? (int) $row['attempts'] : 0;
			if ( $attempts > $winner_attempts ) {
				$winner_attempts = $attempts;
				$winner_ip = (string) $ip;
			}
		}

		return $winner_ip;
	}
}
