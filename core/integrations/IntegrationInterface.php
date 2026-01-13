<?php

namespace LLAR\Core\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface IntegrationInterface {

	/**
	 * Get the name of the plugin this integration supports
	 *
	 * @return string
	 */
	public function get_plugin_name();

	/**
	 * Register all hooks and filters for this integration
	 *
	 * @return void
	 */
	public function register_hooks();

	/**
	 * Check if this is the plugin's login page
	 *
	 * @return bool
	 */
	public function is_login_page();

	/**
	 * Get login credentials from the request
	 * Should return array with 'username' and 'password' keys
	 *
	 * @return array|null
	 */
	public function get_login_credentials();

	/**
	 * Display error message on login page
	 *
	 * @param string $message Error message
	 * @return void
	 */
	public function display_error( $message );

	/**
	 * Check if this is the plugin's registration page
	 *
	 * @return bool
	 */
	public function is_registration_page();

	/**
	 * Get registration data from the request
	 * Should return array with 'username' and 'email' keys
	 *
	 * @return array|null
	 */
	public function get_registration_data();

	/**
	 * Display error message on registration page
	 *
	 * @param string $message Error message
	 * @return void
	 */
	public function display_registration_error( $message );
}
