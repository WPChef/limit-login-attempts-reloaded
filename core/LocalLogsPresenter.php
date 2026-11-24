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
	 * @return array{
	 *     lockouts_total: int,
	 *     lockouts_now: int,
	 *     lockouts: array,
	 *     white_list_ips: string,
	 *     white_list_usernames: string,
	 *     black_list_ips: string,
	 *     black_list_usernames: string,
	 *     log: array
	 * }
	 */
	public function get_view_vars() {
		$lockouts       = Config::get( Config::OPTION_LOCKOUTS );
		$lockouts       = is_array( $lockouts ) ? $lockouts : array();
		$lockouts_total = (int) Config::get( 'lockouts_total' );

		$log = Config::get( Config::OPTION_LOGGED );
		$log = Helpers::sorted_log_by_date( $log );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		return array(
			'lockouts_total'        => $lockouts_total,
			'lockouts_now'          => count( $lockouts ),
			'lockouts'              => $lockouts,
			'white_list_ips'        => $this->lines_for_textarea( Config::get( 'whitelist' ) ),
			'white_list_usernames'  => $this->lines_for_textarea( Config::get( 'whitelist_usernames' ) ),
			'black_list_ips'        => $this->lines_for_textarea( Config::get( 'blacklist' ) ),
			'black_list_usernames'  => $this->lines_for_textarea( Config::get( 'blacklist_usernames' ) ),
			'log'                   => $log,
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
