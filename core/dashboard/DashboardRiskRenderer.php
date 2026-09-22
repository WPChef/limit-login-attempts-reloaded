<?php

namespace LLAR\Core\Dashboard;

use LLAR\Core\Config;
use LLAR\Core\Helpers;
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
	public function get_failed_attempts_circle_data( $is_active_app_custom, $is_exhausted, $block_sub_group, $setup_code, $upgrade_premium_url, $api_stats, $info_has_valid_data = false ) {
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

		$hint_tooltip = $is_active_app_custom
			? __( 'An IP that hasn\'t been previously denied by the cloud app, but has made an unsuccessful login attempt on your website.', 'limit-login-attempts-reloaded' )
			: __( 'An IP that has made an unsuccessful login attempt on your website.', 'limit-login-attempts-reloaded' );

		$premium_label = '';
		if ( $is_active_app_custom && ! empty( $info_has_valid_data ) && ! $is_exhausted ) {
			$premium_label = ( 'Micro Cloud' === $block_sub_group )
				? __( 'Free Trial', 'limit-login-attempts-reloaded' )
				: __( 'Cloud protection enabled', 'limit-login-attempts-reloaded' );
		}

		return array(
			'retries_chart_title' => $retries_chart_title,
			'retries_chart_desc'  => $retries_chart_desc,
			'retries_chart_color' => $retries_chart_color,
			'retries_count'       => (int) $retries_count,
			'hint_tooltip'        => $hint_tooltip,
			'premium_label'       => $premium_label,
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
				'Based on your level of brute force activity, we recommend <a class="llar_orange %s">starting a free 14 day trial</a> to access features to reduce failed logins and improve site performance.',
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

	/**
	 * View data for views/tab-dashboard.php: copy, URLs and checklist state.
	 *
	 * @param string $active_app           Active app slug ('local'|'custom').
	 * @param bool   $is_active_app_custom Whether the cloud app is active.
	 * @param string $block_sub_group      Cloud plan name.
	 * @param bool   $is_exhausted         Cloud quota exhausted flag.
	 *
	 * @return array
	 */
	public function build_dashboard_tab_vars( $active_app, $is_active_app_custom, $block_sub_group, $is_exhausted ) {
		$setup_code          = Config::get( 'app_setup_code' );
		$api_stats           = $is_active_app_custom ? LimitLoginAttempts::$cloud_app->stats() : false;
		$info_has_valid_data = $is_active_app_custom ? $this->plugin->info_has_valid_data() : false;
		$upgrade_premium_url = $is_active_app_custom ? $this->plugin->info_upgrade_url() : '';

		$chart_circle_data = $this->get_failed_attempts_circle_data(
			$is_active_app_custom,
			$is_exhausted,
			$block_sub_group,
			$setup_code,
			$upgrade_premium_url,
			$api_stats,
			$info_has_valid_data
		);

		$url_site = is_multisite() ? network_site_url() : site_url();

		// --- Login Security Checklist state ---
		$lockout_notify = explode( ',', Config::get( 'lockout_notify' ) );
		$email_checked  = in_array( 'email', $lockout_notify ) ? ' checked disabled' : '';
		$email_checked  = $is_active_app_custom ? ' checked disabled' : $email_checked;

		$is_checklist = Config::get( 'checklist' ) === 'true' ? ' checked disabled' : '';

		$min_paid_plan     = 'Personal';
		$min_plan          = 'Premium';
		$plans             = $this->plugin->array_name_plans();
		$current_plan_rate = isset( $plans[ $block_sub_group ] ) ? $plans[ $block_sub_group ] : 0;
		$upgrade_premium   = ( $is_active_app_custom && $current_plan_rate >= $plans[ $min_paid_plan ] ) ? ' checked' : '';

		$checked_block_by_country = Config::get( 'block_by_country' ) === 'true' ? ' checked disabled' : '';
		$block_by_country         = $block_sub_group ? $this->plugin->info_block_by_country() : false;
		$block_by_country_disabled = $block_sub_group ? '' : ' disabled';
		$is_by_country            = $block_by_country ? $checked_block_by_country : $block_by_country_disabled;

		$is_auto_update_choice = ( Helpers::is_auto_update_enabled() && ! Helpers::is_block_automatic_update_disabled() ) ? ' checked' : '';

		$app_config   = Config::get( 'app_config' );
		$full_log_url = ! empty( $app_config['key'] ) ? 'https://my.limitloginattempts.com/logs?key=' . esc_attr( $app_config['key'] ) : false;

		$list_name = __( 'Deny/Allow countries', 'limit-login-attempts-reloaded' );
		if ( ! $is_active_app_custom || ( $is_active_app_custom && ( $plans[ $block_sub_group ] === $plans[ $min_plan ] ) ) ) {
			$list_name = __( 'Deny/Allow countries (Premium+ Users)', 'limit-login-attempts-reloaded' );
		}

		$checklist = array(
			'heading' => __( 'Login Security Checklist', 'limit-login-attempts-reloaded' ),
			'desc'    => __( 'Recommended tasks to greatly improve the security of your website.', 'limit-login-attempts-reloaded' ),
			'full_log_url' => $full_log_url,
			'items'   => array(
				array(
					'name'    => 'lockout_notify_email',
					'checked' => $email_checked,
					'disabled_attr' => '',
					'label'   => __( 'Enable Email Notifications', 'limit-login-attempts-reloaded' ),
					'list_add' => '',
					'desc'     => sprintf(
						__( '<a class="link__style_unlink llar_turquoise" href="%s">Enable email notifications</a> to receive timely alerts and updates via email.', 'limit-login-attempts-reloaded' ),
						'/wp-admin/admin.php?page=limit-login-attempts&tab=settings#llar_lockout_notify'
					),
				),
				array(
					'name'    => 'strong_account_policies',
					'checked' => $is_checklist,
					'disabled_attr' => '',
					'label'   => __( 'Implement strong account policies', 'limit-login-attempts-reloaded' ),
					'list_add' => __( 'Check when done.', 'limit-login-attempts-reloaded' ),
					'desc'     => sprintf(
						__( '<a class="link__style_unlink llar_turquoise" href="%s" target="_blank">Read our guide</a> on implementing and enforcing strong password policies in your organization.', 'limit-login-attempts-reloaded' ),
						'https://www.limitloginattempts.com/info.php?id=1'
					),
				),
				array(
					'name'    => 'block_by_country',
					'checked' => $is_by_country . $block_by_country_disabled,
					'disabled_attr' => '',
					'label'   => $list_name,
					'list_add' => __( 'Check when done.', 'limit-login-attempts-reloaded' ),
					'desc'     => sprintf(
						__( '<a class="link__style_unlink llar_turquoise" href="%s" target="_blank">Allow or Deny countries</a> to ensure only legitimate users login.', 'limit-login-attempts-reloaded' ),
						$block_by_country
							? $url_site . '/wp-admin/admin.php?page=limit-login-attempts&tab=logs-custom'
							: 'https://www.limitloginattempts.com/info.php?id=2'
					),
				),
				array(
					'name'    => 'auto_update_choice',
					'checked' => $is_auto_update_choice,
					'disabled_attr' => ' disabled',
					'label'   => __( 'Turn on plugin auto-updates', 'limit-login-attempts-reloaded' ),
					'list_add' => '',
					'desc'     => ! empty( $is_auto_update_choice )
						? __( 'Enable automatic updates to ensure that the plugin stays current with the latest software patches and features.', 'limit-login-attempts-reloaded' )
						: __( '<a class="link__style_unlink llar_turquoise" href="#llar_auto_update_choice">Enable automatic updates</a> to ensure that the plugin stays current with the latest software patches and features.', 'limit-login-attempts-reloaded' ),
				),
				array(
					'name'    => 'upgrade_premium',
					'checked' => ' ' . $upgrade_premium,
					'disabled_attr' => ' disabled',
					'label'   => __( 'Upgrade to Premium', 'limit-login-attempts-reloaded' ),
					'list_add' => '',
					'desc'     => ( $is_active_app_custom && ( $current_plan_rate >= $plans[ $min_paid_plan ] ) )
						? __( 'Upgrade to our premium version for advanced protection.', 'limit-login-attempts-reloaded' )
						: sprintf(
							__( '<a class="link__style_unlink llar_turquoise" href="%s" target="_blank">Upgrade to our premium</a> version for advanced protection.', 'limit-login-attempts-reloaded' ),
							$is_active_app_custom
								? add_query_arg( 'id', '5', $this->plugin->info_upgrade_url() )
								: 'https://www.limitloginattempts.com/info.php?id=3'
						),
				),
			),
		);

		return array(
			'setup_code'           => $setup_code,
			'api_stats'            => $api_stats,
			'upgrade_premium_url'  => $upgrade_premium_url,
			'show_onboarding'      => ! $is_active_app_custom && empty( $setup_code ),
			'chart_circle_data'    => $chart_circle_data,
			'show_trial_block'           => ! $is_active_app_custom && empty( $setup_code ),
			'show_premium_disabled_block' => ! $is_active_app_custom && ! empty( $setup_code ),
			'trial_block'         => array(
				'title'     => __( 'Experience Premium Free for 14 Days', 'limit-login-attempts-reloaded' ),
				'bullets'   => array(
					__( 'No credit card required. Automatically revert to the free version when the trial is complete.', 'limit-login-attempts-reloaded' ),
					__( 'Unlock advanced security features including Cloud Protection, Block by Country, Login Firewall, and Successful Login Logs', 'limit-login-attempts-reloaded' ),
					__( 'Stop brute force attacks before they reach your login page with one of the strongest login protection systems for WordPress', 'limit-login-attempts-reloaded' ),
				),
				'cta_title' => __( '14 Day Trial', 'limit-login-attempts-reloaded' ),
				'cta_label' => __( '14 Day Trial', 'limit-login-attempts-reloaded' ),
			),
			'premium_disabled_block' => array(
				'title'        => __( 'Premium Protection Disabled', 'limit-login-attempts-reloaded' ),
				'desc'         => __( 'As a free user, your local server is absorbing the traffic brought on by brute force attacks, potentially slowing down your website. Upgrade to Premium today to outsource these attacks through our cloud app, and slow down future attacks with advanced throttling.', 'limit-login-attempts-reloaded' ),
				'url'          => 'https://www.limitloginattempts.com/upgrade/?from=plugin-dashboard-cta',
				'cta_title'    => 'Upgrade To Premium',
				'button_label' => __( 'Upgrade to Premium', 'limit-login-attempts-reloaded' ),
			),
			'quick_links'         => array(
				array(
					'icon'   => 'icon-exploitation.png',
					'href'   => $this->plugin->get_options_page_uri( 'logs-' . $active_app ),
					'target' => '',
					'title'  => __( 'Tools', 'limit-login-attempts-reloaded' ),
					'desc'   => __( 'View lockouts logs, block or whitelist usernames or IPs, and more.', 'limit-login-attempts-reloaded' ),
				),
				array(
					'icon'   => 'icon-help.png',
					'href'   => 'https://www.limitloginattempts.com/info.php?from=plugin-dashboard-help',
					'target' => ' target="_blank"',
					'title'  => __( 'Help', 'limit-login-attempts-reloaded' ),
					'desc'   => __( 'Find the documentation and help you need.', 'limit-login-attempts-reloaded' ),
				),
				array(
					'icon'   => 'icon-web.png',
					'href'   => $this->plugin->get_options_page_uri( 'settings' ),
					'target' => '',
					'title'  => __( 'Global Options', 'limit-login-attempts-reloaded' ),
					'desc'   => __( 'Many options such as notifications, alerts, premium status, and more.', 'limit-login-attempts-reloaded' ),
				),
			),
			'checklist'           => $checklist,
		);
	}
}
