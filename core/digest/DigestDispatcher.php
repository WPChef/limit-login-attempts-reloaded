<?php

namespace LLAR\Core\Digest;

use LLAR\Core\Config;
use LLAR\Core\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DigestDispatcher {
	/**
	 * Register digest dispatch hooks.
	 *
	 * @return void
	 */
	public static function bootstrap() {
		add_action( 'llar_digest_dispatch', array( __CLASS__, 'dispatch' ), 10, 1 );
	}

	/**
	 * Build and send digest email for a specific period key.
	 *
	 * @param string $digest_key Digest key (daily/weekly/monthly).
	 * @return void
	 */
	public static function dispatch( $digest_key ) {
		$digest_key = sanitize_key( (string) $digest_key );
		$definitions = self::get_definitions();
		if ( empty( $definitions[ $digest_key ] ) || empty( $definitions[ $digest_key ]['interval_seconds'] ) ) {
			return;
		}

		if ( ! (bool) Config::get( 'digest_' . $digest_key ) ) {
			return;
		}

		$period = self::get_period_bounds( $digest_key );
		$stats = self::get_period_stats( $period['start_ts'], $period['end_ts'] );
		$admin_email = self::get_admin_email();

		if ( '' === $admin_email ) {
			return;
		}

		$subject = self::build_subject( $digest_key, $stats['lockouts_total'] );
		$body = self::build_body( $digest_key, $period, $stats );

		Helpers::send_mail_with_logo( $admin_email, $subject, $body );
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
	 * Return reporting period bounds for digest key.
	 *
	 * @param string $digest_key Digest key.
	 * @return array
	 */
	private static function get_period_bounds( $digest_key ) {
		$definitions = self::get_definitions();
		$interval_seconds = ! empty( $definitions[ $digest_key ]['interval_seconds'] )
			? max( 1, (int) $definitions[ $digest_key ]['interval_seconds'] )
			: DAY_IN_SECONDS;
		$today_start = strtotime( current_time( 'Y-m-d 00:00:00' ) );
		$end_ts = $today_start - 1;
		$start_ts = $end_ts - $interval_seconds + 1;

		return array(
			'start_ts' => (int) $start_ts,
			'end_ts'   => (int) $end_ts,
		);
	}

	/**
	 * Aggregate daily rows into digest stats.
	 *
	 * @param int $start_ts Period start timestamp.
	 * @param int $end_ts   Period end timestamp.
	 * @return array
	 */
	private static function get_period_stats( $start_ts, $end_ts ) {
		$post_ids = get_posts(
			array(
				'post_type'      => DigestStorage::POST_TYPE,
				'post_status'    => 'private',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
				'meta_key'       => DigestStorage::META_DAY_TS,
				'meta_query'     => array(
					array(
						'key'     => DigestStorage::META_DAY_TS,
						'value'   => array( (int) $start_ts, (int) $end_ts ),
						'compare' => 'BETWEEN',
						'type'    => 'NUMERIC',
					),
				),
			)
		);

		$lockouts_total = 0;
		$attempts_total = 0;
		$unique_ips_map = array();
		$unique_usernames_map = array();

		foreach ( $post_ids as $post_id ) {
			$lockouts_total += (int) get_post_meta( $post_id, DigestStorage::META_LOCKOUTS_COUNT, true );
			$attempts_total += (int) get_post_meta( $post_id, DigestStorage::META_FAILED_ATTEMPTS_COUNT, true );

			$unique_ips = get_post_meta( $post_id, DigestStorage::META_UNIQUE_ATTACKER_IPS, true );
			$unique_ips = is_array( $unique_ips ) ? $unique_ips : array();
			foreach ( $unique_ips as $ip => $marker ) {
				$unique_ips_map[ (string) $ip ] = true;
			}

			$unique_usernames = get_post_meta( $post_id, DigestStorage::META_UNIQUE_USERNAMES, true );
			$unique_usernames = is_array( $unique_usernames ) ? $unique_usernames : array();
			foreach ( $unique_usernames as $username => $marker ) {
				$unique_usernames_map[ (string) $username ] = true;
			}
		}

		return array(
			'lockouts_total'         => (int) $lockouts_total,
			'attempts_total'         => (int) $attempts_total,
			'unique_ips_total'       => (int) count( $unique_ips_map ),
			'unique_usernames_total' => (int) count( $unique_usernames_map ),
		);
	}

	/**
	 * Build digest email subject.
	 *
	 * @param string $digest_key      Digest key.
	 * @param int    $lockouts_total  Lockouts total.
	 * @return string
	 */
	private static function build_subject( $digest_key, $lockouts_total ) {
		$site_domain = str_replace( array( 'http://', 'https://' ), '', home_url() );
		$label = ucfirst( $digest_key );

		return sprintf(
			'%1$s Security Summary for %2$s: %3$d lockouts',
			$label,
			$site_domain,
			(int) $lockouts_total
		);
	}

	/**
	 * Build digest email body with required four metrics.
	 *
	 * @param string $digest_key Digest key.
	 * @param array  $period     Period bounds.
	 * @param array  $stats      Aggregated stats.
	 * @return string
	 */
	private static function build_body( $digest_key, $period, $stats ) {
		$site_domain = str_replace( array( 'http://', 'https://' ), '', home_url() );
		$title = ucfirst( $digest_key ) . ' Login Security Summary';
		$start_label = date_i18n( 'Y-m-d H:i', (int) $period['start_ts'] );
		$end_label = date_i18n( 'Y-m-d H:i', (int) $period['end_ts'] );
		$dashboard_url = admin_url( 'options-general.php?page=limit-login-attempts' );
		$unsubscribe_url = admin_url( 'options-general.php?page=limit-login-attempts&tab=settings' );

		$rows = array(
			'Lockouts'                  => (int) $stats['lockouts_total'],
			'Failed login attempts'     => (int) $stats['attempts_total'],
			'Unique attacker IPs'       => (int) $stats['unique_ips_total'],
			'Unique targeted usernames' => (int) $stats['unique_usernames_total'],
		);

		$summary_html = '';
		foreach ( $rows as $label => $value ) {
			$summary_html .= '<li><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( (string) $value ) . '</li>';
		}

		return '<h2>' . esc_html( $title ) . '</h2>'
			. '<p>Hello,<br>This is your ' . esc_html( $digest_key ) . ' security summary for <strong>' . esc_html( $site_domain ) . '</strong>.</p>'
			. '<p><strong>Reporting period:</strong> ' . esc_html( $start_label ) . ' to ' . esc_html( $end_label ) . '</p>'
			. '<ul>' . $summary_html . '</ul>'
			. '<p><a href="' . esc_url( $dashboard_url ) . '">Go to Dashboard</a></p>'
			. '<p style="font-size:12px;color:#666;">Don\'t want these notifications? <a href="' . esc_url( $unsubscribe_url ) . '">Unsubscribe</a>.</p>';
	}

	/**
	 * Resolve admin notification email.
	 *
	 * @return string
	 */
	private static function get_admin_email() {
		$email = (string) Config::get( 'admin_notify_email' );
		if ( '' === $email ) {
			$email = (string) get_site_option( 'admin_email' );
		}

		$email = sanitize_email( $email );

		return is_email( $email ) ? $email : '';
	}
}
