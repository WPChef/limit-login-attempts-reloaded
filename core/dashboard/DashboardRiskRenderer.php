<?php

namespace LLAR\Core\Dashboard;

use LLAR\Core\Config;
use LLAR\Core\LimitLoginAttempts;
use LLAR\Core\LocalLockoutManager;
use LLAR\Core\Utils\RiskLevelMath;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin dashboard risk circle and chart data.
 */
class DashboardRiskRenderer {

	/**
	 * @var LimitLoginAttempts
	 */
	private $plugin;

	/**
	 * @var LocalLockoutManager
	 */
	private $local_lockout;

	/**
	 * @param LimitLoginAttempts  $plugin       Plugin facade (info_* helpers).
	 * @param LocalLockoutManager $local_lockout Local retries stats.
	 */
	public function __construct( LimitLoginAttempts $plugin, LocalLockoutManager $local_lockout ) {
		$this->plugin        = $plugin;
		$this->local_lockout = $local_lockout;
	}

	/**
	 * Variables for views/admin-dashboard-widgets.php.
	 *
	 * @return array
	 */
	public function build_dashboard_widget_vars() {
		$active_app = ( Config::get( Config::OPTION_ACTIVE_APP ) === 'custom' && LimitLoginAttempts::$cloud_app ) ? 'custom' : 'local';
		$is_active_app_custom = ( 'custom' === $active_app );

		if ( $is_active_app_custom ) {
			$is_exhausted          = $this->plugin->info_is_exhausted();
			$block_sub_group       = $this->plugin->info_sub_group();
			$upgrade_premium_url   = $this->plugin->info_upgrade_url();
		} else {
			$is_exhausted        = false;
			$block_sub_group     = '';
			$upgrade_premium_url = '';
		}

		$api_stats    = $is_active_app_custom ? LimitLoginAttempts::$cloud_app->stats() : false;
		$setup_code   = Config::get( 'app_setup_code' );
		$chart_circle = $this->get_failed_attempts_circle_data(
			$is_active_app_custom,
			$is_exhausted,
			$block_sub_group,
			$setup_code,
			$upgrade_premium_url,
			$api_stats
		);

		return array(
			'active_app'                 => $active_app,
			'is_active_app_custom'       => $is_active_app_custom,
			'is_exhausted'               => $is_exhausted,
			'block_sub_group'            => $block_sub_group,
			'upgrade_premium_url'        => $upgrade_premium_url,
			'api_stats'                  => $api_stats,
			'setup_code'                 => $setup_code,
			'chart_circle_data'          => $chart_circle,
			'show_mfa_recovery_notice'   => $this->plugin->should_show_mfa_recovery_links_expired_notice(),
			'mfa_settings_url'           => $this->plugin->get_options_page_uri( 'mfa' ),
		);
	}

	/**
	 * @param bool        $is_active_app_custom Cloud mode.
	 * @param bool|string $is_exhausted         Exhausted flag.
	 * @param string      $block_sub_group      Plan name.
	 * @param string      $setup_code           Setup code.
	 * @param string      $upgrade_premium_url  Premium URL.
	 * @param bool|array  $api_stats            API stats.
	 * @return array
	 */
	public function get_failed_attempts_circle_data( $is_active_app_custom, $is_exhausted, $block_sub_group, $setup_code, $upgrade_premium_url, $api_stats ) {
		$risk_config         = llar_get_risk_config();
		$risk_levels         = ( isset( $risk_config['levels'] ) && is_array( $risk_config['levels'] ) ) ? $risk_config['levels'] : array();
		$risk_colors         = ( isset( $risk_config['colors'] ) && is_array( $risk_config['colors'] ) ) ? $risk_config['colors'] : array();
		$retries_chart_title = '';
		$retries_chart_desc  = '';
		$retries_chart_color = '';
		$retries_count       = 0;

		if ( ! $is_active_app_custom ) {
			$retries_count = $this->local_lockout->get_local_retries_count_for_last_day();
			$local_levels  = isset( $risk_levels['local'] ) && is_array( $risk_levels['local'] ) ? $risk_levels['local'] : array();
			$matched_level = RiskLevelMath::resolve_risk_level( $retries_count, $local_levels );
			$display_data  = $this->build_chart_display_data( $matched_level, $retries_count, $risk_config, $setup_code, $upgrade_premium_url );
			$retries_chart_title = $display_data['retries_chart_title'];
			$retries_chart_desc  = $display_data['retries_chart_desc'];
			$retries_chart_color = $display_data['retries_chart_color'];
		} else {
			if ( $api_stats && ! empty( $api_stats['attempts']['count'] ) && is_array( $api_stats['attempts']['count'] ) ) {
				$attempt_counts = array();
				foreach ( $api_stats['attempts']['count'] as $v ) {
					if ( is_numeric( $v ) ) {
						$attempt_counts[] = (int) $v;
					}
				}
				if ( ! empty( $attempt_counts ) ) {
					$retries_count = (int) end( $attempt_counts );
				}
			}
			$retries_chart_title = $this->get_risk_circle_string( 'failed_today_title' );
			$retries_chart_desc  = '';
			$retries_chart_color = isset( $risk_colors['green'] ) ? $risk_colors['green'] : '#97F6C8';
		}

		return array(
			'retries_chart_title' => $retries_chart_title,
			'retries_chart_desc'  => $retries_chart_desc,
			'retries_chart_color' => $retries_chart_color,
			'retries_count'       => (int) $retries_count,
		);
	}

	/**
	 * @param string $key Text key.
	 * @return string
	 */
	private function get_risk_circle_string( $key ) {
		switch ( $key ) {
			case 'zero_title':
				return __( 'Hooray! Zero failed login attempts (past 24 hrs)', 'limit-login-attempts-reloaded' );
			case 'desc_low':
				return __( 'Your site is currently at a low risk for brute force activity.', 'limit-login-attempts-reloaded' );
			case 'desc_medium':
				return __( 'Your site is currently at a medium risk for brute force activity.', 'limit-login-attempts-reloaded' );
			case 'failed_today_title':
				return __( 'Failed Login Attempts Today', 'limit-login-attempts-reloaded' );
			default:
				return '';
		}
	}

	/**
	 * @return string
	 */
	private function get_micro_cloud_recommendation_html() {
		return sprintf(
			__(
				'Based on your level of brute force activity, we recommend <a class="llar_orange %s">free Micro Cloud upgrade</a> to access features to reduce failed logins and improve site performance.',
				'limit-login-attempts-reloaded'
			),
			'button_micro_cloud'
		);
	}

	/**
	 * @param string $upgrade_premium_url URL.
	 * @param bool   $open_new_window       New window.
	 * @return string
	 */
	private function get_premium_recommendation_desc( $upgrade_premium_url, $open_new_window = true ) {
		$url = esc_url( $upgrade_premium_url );
		if ( $open_new_window ) {
			return sprintf(
				__(
					'Based on your level of brute force activity, we recommend <a href="%s" class="llar_orange" target="_blank" rel="noopener noreferrer">upgrading to premium</a> to access features to reduce failed logins and improve site performance.',
					'limit-login-attempts-reloaded'
				),
				$url
			);
		}
		return sprintf(
			__(
				'Based on your level of brute force activity, we recommend <a href="%s" class="llar_orange">upgrading to premium</a> to access features to reduce failed logins and improve site performance.',
				'limit-login-attempts-reloaded'
			),
			$url
		);
	}

	/**
	 * @param int $retries_count Count.
	 * @return string
	 */
	private function get_retries_chart_title_with_count( $retries_count ) {
		return sprintf(
			_n(
				'%d failed login attempt ',
				'%d failed login attempts ',
				$retries_count,
				'limit-login-attempts-reloaded'
			),
			$retries_count
		) . __( '(past 24 hrs)', 'limit-login-attempts-reloaded' );
	}

	/**
	 * @param string $setup_code Setup code.
	 * @return string
	 */
	private function get_recommendation_desc( $setup_code ) {
		if ( ! empty( $setup_code ) ) {
			return $this->get_premium_recommendation_desc( $this->plugin->get_options_page_uri( 'premium' ), false );
		}
		return $this->get_micro_cloud_recommendation_html();
	}

	/**
	 * @param array  $matched_level       Level config.
	 * @param int    $retries_count       Retries.
	 * @param array  $risk_config         Full config.
	 * @param string $setup_code          Setup code.
	 * @param string $upgrade_premium_url Premium URL.
	 * @return array
	 */
	private function build_chart_display_data( $matched_level, $retries_count, $risk_config, $setup_code, $upgrade_premium_url ) {
		$risk_colors   = ( isset( $risk_config['colors'] ) && is_array( $risk_config['colors'] ) ) ? $risk_config['colors'] : array();
		$default_color = isset( $risk_colors['green'] ) ? $risk_colors['green'] : '#97F6C8';
		$retries_chart_title = '';
		$retries_chart_desc  = '';
		$retries_chart_color = $default_color;
		$rule_flag_keys      = array( 'count_title', 'warning_title', 'recommendation', 'premium_recommendation' );

		foreach ( array( 'title', 'count_title', 'warning_title', 'desc', 'recommendation', 'premium_recommendation', 'color' ) as $rule_key ) {
			if ( ! isset( $matched_level[ $rule_key ] ) ) {
				continue;
			}
			if ( in_array( $rule_key, $rule_flag_keys, true ) ) {
				if ( true !== $matched_level[ $rule_key ] && ! $matched_level[ $rule_key ] ) {
					continue;
				}
			} elseif ( empty( $matched_level[ $rule_key ] ) ) {
				continue;
			}
			switch ( $rule_key ) {
				case 'title':
					if ( ! empty( $matched_level['title'] ) ) {
						$retries_chart_title = $this->get_risk_circle_string( $matched_level['title'] );
					}
					break;
				case 'count_title':
					$retries_chart_title = $this->get_retries_chart_title_with_count( $retries_count );
					break;
				case 'warning_title':
					$medium_upper = isset( $risk_config['bounds']['medium_upper'] ) ? (int) $risk_config['bounds']['medium_upper'] : 0;
					if ( $medium_upper <= 0 && isset( $matched_level['min_inclusive'] ) ) {
						$medium_upper = (int) $matched_level['min_inclusive'];
					}
					if ( $medium_upper <= 0 ) {
						$medium_upper = 300;
					}
					$retries_chart_title = sprintf(
						__( 'Your site has experienced %d+ failed login attempts in the past 24 hours.', 'limit-login-attempts-reloaded' ),
						$medium_upper
					);
					break;
				case 'desc':
					if ( ! empty( $matched_level['desc'] ) ) {
						$retries_chart_desc = $this->get_risk_circle_string( $matched_level['desc'] );
					}
					break;
				case 'recommendation':
					$recommendation_html = $this->get_recommendation_desc( $setup_code );
					if ( ! empty( $retries_chart_desc ) ) {
						$retries_chart_desc .= '<br><br>' . $recommendation_html;
					} else {
						$retries_chart_desc = $recommendation_html;
					}
					break;
				case 'premium_recommendation':
					$retries_chart_desc = $this->get_premium_recommendation_desc( $upgrade_premium_url );
					break;
				case 'color':
					$retries_chart_color = RiskLevelMath::resolve_chart_color( $matched_level, $risk_colors );
					break;
			}
		}

		return array(
			'retries_chart_title' => $retries_chart_title,
			'retries_chart_desc'  => $retries_chart_desc,
			'retries_chart_color' => $retries_chart_color,
		);
	}
}
