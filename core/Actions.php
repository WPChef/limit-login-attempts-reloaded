<?php

namespace LLAR\Core;

use LLAR\Core\Config;

if( !defined( 'ABSPATH' ) ) exit();

class Actions {
	/**
	 * Register all actions
	 */
	public function register() {
		add_action( 'limit_login_free_requests_exhausted', array( $this, 'handle_free_requests_exhausted' ) );
		add_action( 'limit_login_response_context_free_requests_exhausted', array( $this, 'handle_free_requests_exhausted' ) );
	}

	/**
	 * Handle free requests exhausted
	 */
	public function handle_free_requests_exhausted( $response ) {
		Config::update( 'active_app', 'local' );
		$end_of_month_timestamp = strtotime( 'last day of this month' );
		Config::update( 'free_requests_exhausted', $end_of_month_timestamp );
	}
}

