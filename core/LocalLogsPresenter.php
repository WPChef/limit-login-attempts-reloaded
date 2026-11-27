<?php

namespace LLAR\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the view data for Local Logs (views/tab-logs-local.php).
 */
class LocalLogsPresenter {

	/**
	 * View vars for the local logs template.
	 *
	 * @return array
	 */
	public function get_view_vars() {
		$lockouts       = Config::get( Config::OPTION_LOCKOUTS );
		$lockouts       = is_array( $lockouts ) ? $lockouts : array();
		$lockouts_now   = count( $lockouts );
		$lockouts_total = (int) Config::get( 'lockouts_total' );

		$log = Helpers::sorted_log_by_date( Config::get( Config::OPTION_LOGGED ) );
		$log = $this->enrich_log_rows( $log, $lockouts );

		$trial_cta_url = TrialLanding::cta_landing_url( TrialLanding::INFO_ID_LOGS, 'logs-local' );
		$trial_label   = LimitLoginAttempts::trial_label();

		return array_merge(
			array(
				'lockouts_total'       => $lockouts_total,
				'lockouts_now'         => $lockouts_now,
				'white_list_ips'       => $this->lines_for_textarea( Config::get( 'whitelist' ) ),
				'white_list_usernames' => $this->lines_for_textarea( Config::get( 'whitelist_usernames' ) ),
				'black_list_ips'       => $this->lines_for_textarea( Config::get( 'blacklist' ) ),
				'black_list_usernames' => $this->lines_for_textarea( Config::get( 'blacklist_usernames' ) ),
				'log'                  => $log,
				'trial_cta_url'        => $trial_cta_url,
				'trial_info_id'        => TrialLanding::INFO_ID_LOGS,
				'trial_label'          => $trial_label,
				'unlock_nonce'         => wp_create_nonce( 'llar-unlock' ),
				'link_kses'            => array(
					'a' => array(
						'href'   => true,
						'class'  => true,
						'target' => true,
					),
				),
			),
			$this->get_copy( $lockouts_total, $lockouts_now )
		);
	}

	/**
	 * Add unlock state to each log row (view stays markup-only).
	 *
	 * @param array $log      Sorted log rows.
	 * @param array $lockouts Active lockouts map.
	 * @return array
	 */
	private function enrich_log_rows( array $log, array $lockouts ) {
		$now = time();
		foreach ( $log as $date => $user_info ) {
			$ip    = isset( $user_info['ip'] ) ? $user_info['ip'] : '';
			$until = Helpers::get_active_lockout_until( $lockouts, $ip );
			$log[ $date ]['can_unlock'] = ( $until > $now );
		}

		return $log;
	}

	/**
	 * Translated copy for the local logs template.
	 *
	 * @param int $lockouts_total Total lockouts counter.
	 * @param int $lockouts_now   Active lockouts count.
	 * @return array
	 */
	private function get_copy( $lockouts_total, $lockouts_now ) {
		return array(
			'statistics_title'           => __( 'Statistics', 'limit-login-attempts-reloaded' ),
			'total_lockouts_label'       => __( 'Total lockouts', 'limit-login-attempts-reloaded' ),
			'reset_counter_label'        => __( 'Reset Counter', 'limit-login-attempts-reloaded' ),
			'lockouts_since_reset'       => sprintf(
				_n(
					'%d lockout since last reset',
					'%d lockouts since last reset',
					$lockouts_total,
					'limit-login-attempts-reloaded'
				),
				$lockouts_total
			),
			'no_lockouts_yet'            => __( 'No lockouts yet', 'limit-login-attempts-reloaded' ),
			'active_lockouts_label'      => __( 'Active lockouts', 'limit-login-attempts-reloaded' ),
			'restore_lockouts_label'     => __( 'Restore Lockouts', 'limit-login-attempts-reloaded' ),
			'active_lockouts_summary'    => sprintf(
				__( '%d IP is currently blocked from trying to log in', 'limit-login-attempts-reloaded' ),
				$lockouts_now
			),
			'safelist_label'             => __( 'Safelist', 'limit-login-attempts-reloaded' ),
			'denylist_label'             => __( 'Denylist', 'limit-login-attempts-reloaded' ),
			'ip_per_line_label'          => __( 'One IP or IP range (1.2.3.4-5.6.7.8) per line', 'limit-login-attempts-reloaded' ),
			'username_per_line_label'    => __( 'One Username per line', 'limit-login-attempts-reloaded' ),
			// Same copy for Safelist + Denylist: each line is matched literally (no email → login mapping).
			'username_list_hint'         => __(
				"A username in this list does not also cover that account's email. To cover both, add the username and the email on separate lines. Each line is checked on its own, which avoids extra database lookups during attacks, works better with other plugins, and keeps the result predictable.",
				'limit-login-attempts-reloaded'
			),
			'denylist_upgrade_html'      => sprintf(
				__( 'Automate your denylist with IP intelligence when you <a href="%s" class="unlink link__style_unlink" target="_blank">upgrade to premium</a>.', 'limit-login-attempts-reloaded' ),
				'https://www.limitloginattempts.com/info.php?id=21'
			),
			'save_settings_label'        => __( 'Save Settings', 'limit-login-attempts-reloaded' ),
			'firewall_upgrade_title'     => __( 'Upgrade To Premium For Our Login Firewall', 'limit-login-attempts-reloaded' ),
			'firewall_feature_threats'   => __( 'Identify & Counter New Threats With IP Intelligence', 'limit-login-attempts-reloaded' ),
			'firewall_feature_databases' => __( 'Access Active Databases Of Malicious IPs To Bolster Defenses', 'limit-login-attempts-reloaded' ),
			'firewall_feature_unblock'   => __( 'Unblock The Blocked Admin With Ease', 'limit-login-attempts-reloaded' ),
			'firewall_feature_country'   => __( 'Deny IPs By Country', 'limit-login-attempts-reloaded' ),
			'lockout_log_title'          => __( 'Lockout log', 'limit-login-attempts-reloaded' ),
			'clear_log_label'            => __( 'Clear Log', 'limit-login-attempts-reloaded' ),
			'clear_log_upgrade_html'     => sprintf(
				__( '<a href="%s" class="unlink link__style_unlink" target="_blank">Upgrade today</a> to optimize or unload your DB by moving logs to the cloud.', 'limit-login-attempts-reloaded' ),
				'https://www.limitloginattempts.com/info.php?from=plugin-clear-log'
			),
			'log_col_date'               => __( 'Date', 'limit-login-attempts-reloaded' ),
			'log_col_ip'                 => _x( 'IP', 'Internet address', 'limit-login-attempts-reloaded' ),
			'log_col_login_as'           => __( 'Tried to log in as', 'limit-login-attempts-reloaded' ),
			'log_col_gateway'            => __( 'Gateway', 'limit-login-attempts-reloaded' ),
			'log_date_format'            => __( 'F d, Y H:i', 'limit-login-attempts-reloaded' ),
			'lockouts_word'              => __( 'lockouts', 'limit-login-attempts-reloaded' ),
			'unlock_label'               => __( 'Unlock', 'limit-login-attempts-reloaded' ),
			'unlocked_label'             => __( 'Unlocked', 'limit-login-attempts-reloaded' ),
			'logs_upgrade_title'         => __( 'Upgrade Today For Enhanced Logs & IP Intelligence', 'limit-login-attempts-reloaded' ),
			'logs_feature_enhanced'      => __( 'Enhanced Logs Tell You Exactly Which IPs Are Attempting Logins', 'limit-login-attempts-reloaded' ),
			'logs_feature_threats'       => __( 'Identify & Counter New Threats With Ease', 'limit-login-attempts-reloaded' ),
			'logs_feature_deny'          => __( 'Automatically Add Malicious IPs To Your Deny List', 'limit-login-attempts-reloaded' ),
			'logs_feature_unblock'       => __( 'Unblock The Blocked Admins Effortlessly', 'limit-login-attempts-reloaded' ),
		);
	}

	/**
	 * Join a config list into textarea lines; empty/non-array → ''.
	 *
	 * @param mixed $value Config value.
	 * @return string
	 */
	private function lines_for_textarea( $value ) {
		if ( ! is_array( $value ) || empty( $value ) ) {
			return '';
		}

		return implode( "\n", $value );
	}
}
