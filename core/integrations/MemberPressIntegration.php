<?php

namespace LLAR\Core\Integrations;

if ( ! defined( 'ABSPATH' ) ) exit;

class MemberPressIntegration extends BaseIntegration
{
	/**
	 * Get the name of the plugin this integration supports
	 *
	 * @return string
	 */
	public function get_plugin_name(): string
	{
		return 'MemberPress';
	}

	/**
	 * Check if MemberPress plugin is active
	 *
	 * @return bool
	 */
	public function is_plugin_active(): bool
	{
		return function_exists( 'mepr_validate_login' ) || class_exists( 'MeprUser' );
	}

	/**
	 * Register all hooks and filters for MemberPress
	 *
	 * @return void
	 */
	public function register_hooks(): void
	{
		if ( ! $this->is_plugin_active() ) {
			return;
		}

		// hook for the plugin MemberPress
		add_filter( 'mepr_validate_login', array( $this, 'mepr_validate_login_handler' ), 10, 2 );
	}

	/**
	 * Check if this is MemberPress login page
	 *
	 * @return bool
	 */
	public function is_login_page(): bool
	{
		// MemberPress can determine its login page in different ways
		// Check for standard login fields
		return isset( $_POST['log'] ) && isset( $_POST['pwd'] );
	}

	/**
	 * Get login credentials from the request
	 *
	 * @return array|null
	 */
	public function get_login_credentials(): ?array
	{
		if ( ! isset( $_POST['log'] ) || ! isset( $_POST['pwd'] ) ) {
			return null;
		}

		return array(
			'username' => sanitize_text_field( wp_unslash( $_POST['log'] ) ),
			'password' => $_POST['pwd'], // Password should not be sanitized
		);
	}

	/**
	 * Display error message on MemberPress login page
	 *
	 * @param string $message Error message
	 * @return void
	 */
	public function display_error( string $message ): void
	{
		// MemberPress handles errors through its own mechanisms
		// Errors are added through mepr_validate_login_handler
	}

	/**
	 * Check if this is MemberPress registration page
	 *
	 * @return bool
	 */
	public function is_registration_page(): bool
	{
		// Check for standard WordPress registration fields
		// MemberPress may use different fields, but this is a common pattern
		return isset( $_POST['user_login'] ) || isset( $_POST['user_email'] ) || 
		       ( isset( $_POST['action'] ) && $_POST['action'] === 'register' );
	}

	/**
	 * Get registration data from the request
	 *
	 * @return array|null
	 */
	public function get_registration_data(): ?array
	{
		if ( empty( $_POST['user_login'] ) && empty( $_POST['user_email'] ) ) {
			return null;
		}

		$user_login = isset( $_POST['user_login'] ) ? sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) : '';
		$user_email = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '';

		// Only return if at least one field is present
		if ( empty( $user_login ) && empty( $user_email ) ) {
			return null;
		}

		return array(
			'username' => $user_login,
			'email'    => $user_email,
		);
	}

	/**
	 * Display error message on MemberPress registration page
	 *
	 * @param string $message Error message
	 * @return void
	 */
	public function display_registration_error( string $message ): void
	{
		// MemberPress handles registration errors through WordPress registration_errors filter
		// Errors should be added via the registration validation hooks
	}

	/**
	 * For plugin MemberPress
	 * Triggers authenticate filter to allow Limit Login Attempts Reloaded
	 * to track credentials and check lockouts before MemberPress validates the password
	 * This enables the plugin to display remaining attempts messages
	 *
	 * @param array $errors Array of existing errors
	 * @param array $params Login parameters (log, pwd)
	 * @return array Unchanged errors array (we don't block, only track)
	 */
	public function mepr_validate_login_handler( $errors, $params = array() )
	{
		if ( ! isset( $_POST['log'] ) || ! isset( $_POST['pwd'] ) ) {
			return $errors;
		}

		$log = sanitize_text_field( wp_unslash( $_POST['log'] ) );
		$pwd = isset( $_POST['pwd'] ) ? $_POST['pwd'] : ''; // Password should not be sanitized

		// Trigger authenticate filter to track credentials and check lockouts
		// This sets $limit_login_nonempty_credentials and $_SESSION['login_attempts_left']
		// We don't block here - MemberPress will handle blocking if needed
		apply_filters( 'authenticate', null, $log, $pwd );

		// Return errors unchanged - we're only tracking, not blocking
		return $errors;
	}
}

