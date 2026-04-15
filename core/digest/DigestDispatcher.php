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
		$top_ips_map = array();
		$top_usernames_map = array();

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

			$top_ips = get_post_meta( $post_id, DigestStorage::META_TOP_IPS, true );
			$top_ips = is_array( $top_ips ) ? $top_ips : array();
			foreach ( $top_ips as $ip => $row ) {
				$ip = (string) $ip;
				if ( '' === $ip ) {
					continue;
				}

				if ( empty( $top_ips_map[ $ip ] ) || ! is_array( $top_ips_map[ $ip ] ) ) {
					$top_ips_map[ $ip ] = array(
						'attempts'  => 0,
						'lockouts'  => 0,
						'last_seen' => 0,
						'top_url'   => '',
					);
				}

				$top_ips_map[ $ip ]['attempts'] += isset( $row['attempts'] ) ? (int) $row['attempts'] : 0;
				$top_ips_map[ $ip ]['lockouts'] += isset( $row['lockouts'] ) ? (int) $row['lockouts'] : 0;
				$last_seen = isset( $row['last_seen'] ) ? (int) $row['last_seen'] : 0;
				if ( $last_seen > $top_ips_map[ $ip ]['last_seen'] ) {
					$top_ips_map[ $ip ]['last_seen'] = $last_seen;
				}
				if ( ! empty( $row['top_url'] ) ) {
					$top_ips_map[ $ip ]['top_url'] = (string) $row['top_url'];
				}
			}

			$top_usernames = get_post_meta( $post_id, DigestStorage::META_TOP_USERNAMES, true );
			$top_usernames = is_array( $top_usernames ) ? $top_usernames : array();
			foreach ( $top_usernames as $username => $count ) {
				$username = (string) $username;
				if ( '' === $username ) {
					continue;
				}
				$top_usernames_map[ $username ] = ( isset( $top_usernames_map[ $username ] ) ? (int) $top_usernames_map[ $username ] : 0 ) + (int) $count;
			}
		}

		uasort(
			$top_ips_map,
			function ( $a, $b ) {
				$a_attempts = isset( $a['attempts'] ) ? (int) $a['attempts'] : 0;
				$b_attempts = isset( $b['attempts'] ) ? (int) $b['attempts'] : 0;
				if ( $a_attempts === $b_attempts ) {
					return 0;
				}
				return ( $a_attempts > $b_attempts ) ? -1 : 1;
			}
		);

		arsort( $top_usernames_map );

		$most_attempted_ip = '';
		$most_attempted_ip_attempts = 0;
		foreach ( $top_ips_map as $ip => $row ) {
			$most_attempted_ip = (string) $ip;
			$most_attempted_ip_attempts = isset( $row['attempts'] ) ? (int) $row['attempts'] : 0;
			break;
		}

		return array(
			'lockouts_total'         => (int) $lockouts_total,
			'attempts_total'         => (int) $attempts_total,
			'unique_ips_total'       => (int) count( $unique_ips_map ),
			'unique_usernames_total' => (int) count( $unique_usernames_map ),
			'top_ips'                => array_slice( $top_ips_map, 0, 10, true ),
			'top_usernames'          => array_slice( $top_usernames_map, 0, 3, true ),
			'most_attempted_ip'      => $most_attempted_ip,
			'most_attempted_attempts'=> (int) $most_attempted_ip_attempts,
			'attack_threat_level'    => self::resolve_attack_threat_level( $attempts_total, $lockouts_total ),
		);
	}

	/**
	 * Resolve simple threat level for digest summary.
	 *
	 * @param int $attempts_total Attempts in period.
	 * @param int $lockouts_total Lockouts in period.
	 * @return string
	 */
	private static function resolve_attack_threat_level( $attempts_total, $lockouts_total ) {
		$attempts_total = (int) $attempts_total;
		$lockouts_total = (int) $lockouts_total;

		if ( $attempts_total >= 500 || $lockouts_total >= 50 ) {
			return 'High';
		}

		if ( $attempts_total >= 100 || $lockouts_total >= 10 ) {
			return 'Medium';
		}

		return 'Low';
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
		$definitions = self::get_definitions();
		$label = ! empty( $definitions[ $digest_key ]['name'] )
			? (string) $definitions[ $digest_key ]['name']
			: ucfirst( $digest_key );

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
		$definitions = self::get_definitions();
		$definition = ! empty( $definitions[ $digest_key ] ) && is_array( $definitions[ $digest_key ] )
			? $definitions[ $digest_key ]
			: array();

		$site_domain = str_replace( array( 'http://', 'https://' ), '', home_url() );
		$start_label = date_i18n( 'Y-m-d H:i', (int) $period['start_ts'] );
		$end_label = date_i18n( 'Y-m-d H:i', (int) $period['end_ts'] );
		$dashboard_url = admin_url( 'options-general.php?page=limit-login-attempts' );
		$unsubscribe_url = admin_url( 'options-general.php?page=limit-login-attempts&tab=settings' );

		$template_file = ! empty( $definition['email_template'] ) ? (string) $definition['email_template'] : 'digest-daily-content.php';
		$title_mode = ! empty( $definition['title_mode'] ) ? (string) $definition['title_mode'] : 'date';
		$intro_text = ! empty( $definition['intro_text'] ) ? (string) $definition['intro_text'] : 'This is your security summary from Limit Login Attempts Reloaded for';
		$show_threat_level = ! empty( $definition['show_threat_level'] );

		$email_title = self::build_email_title( $title_mode, $period, $start_label, $end_label );
		$summary_items = self::build_summary_items( $stats, $show_threat_level );
		$top_ips_rows = self::build_top_ips_rows( $stats['top_ips'] );
		$top_usernames_rows = self::build_top_usernames_rows( $stats['top_usernames'] );
		$most_attempted_ip_text = ! empty( $stats['most_attempted_ip'] )
			? (string) $stats['most_attempted_ip'] . ' (' . (int) $stats['most_attempted_attempts'] . ')'
			: 'n/a';
		$reporting_period = $start_label . ' to ' . $end_label;
		$template_path = LLA_PLUGIN_DIR . 'views/emails/' . $template_file;

		if ( ! file_exists( $template_path ) ) {
			$template_path = LLA_PLUGIN_DIR . 'views/emails/digest-daily-content.php';
		}

		ob_start();
		include $template_path;
		return (string) ob_get_clean();
	}

	/**
	 * Build digest email title based on configured mode.
	 *
	 * @param string $title_mode  Mode from definition.
	 * @param array  $period      Period bounds.
	 * @param string $start_label Formatted start datetime.
	 * @param string $end_label   Formatted end datetime.
	 * @return string
	 */
	private static function build_email_title( $title_mode, $period, $start_label, $end_label ) {
		if ( 'range' === $title_mode ) {
			return 'Weekly Login Security Summary - ' . $start_label . ' to ' . $end_label;
		}

		if ( 'month' === $title_mode ) {
			return 'Monthly Login Security Summary - ' . date_i18n( 'F Y', (int) $period['start_ts'] );
		}

		return 'Login Security Summary - ' . date_i18n( 'Y-m-d', (int) $period['end_ts'] );
	}

	/**
	 * Build summary list items for template.
	 *
	 * @param array $stats              Aggregated stats.
	 * @param bool  $show_threat_level  Whether to include threat row.
	 * @return array
	 */
	private static function build_summary_items( $stats, $show_threat_level ) {
		$items = array(
			'Lockouts'              => (int) $stats['lockouts_total'],
			'Failed login attempts' => (int) $stats['attempts_total'],
			'Unique IPs'            => (int) $stats['unique_ips_total'],
			'Most attempted IP'     => ! empty( $stats['most_attempted_ip'] )
				? (string) $stats['most_attempted_ip'] . ' (' . (int) $stats['most_attempted_attempts'] . ')'
				: 'n/a',
		);

		if ( $show_threat_level ) {
			$items['Attack Threat Level'] = (string) $stats['attack_threat_level'];
		}

		return $items;
	}

	/**
	 * Build top IP rows for template rendering.
	 *
	 * @param array $top_ips Aggregated top ip map.
	 * @return array
	 */
	private static function build_top_ips_rows( $top_ips ) {
		$rows = array();
		foreach ( $top_ips as $ip => $row ) {
			$rows[] = array(
				'ip'       => (string) $ip,
				'lockouts' => isset( $row['lockouts'] ) ? (int) $row['lockouts'] : 0,
				'attempts' => isset( $row['attempts'] ) ? (int) $row['attempts'] : 0,
				'last_seen'=> ! empty( $row['last_seen'] ) ? date_i18n( 'Y-m-d H:i', (int) $row['last_seen'] ) : '-',
				'top_url'  => ! empty( $row['top_url'] ) ? (string) $row['top_url'] : '-',
			);
		}

		return $rows;
	}

	/**
	 * Build top username rows for template rendering.
	 *
	 * @param array $top_usernames Aggregated top usernames map.
	 * @return array
	 */
	private static function build_top_usernames_rows( $top_usernames ) {
		$rows = array();
		foreach ( $top_usernames as $username => $count ) {
			$rows[] = array(
				'username' => (string) $username,
				'attempts' => (int) $count,
			);
		}

		return $rows;
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
