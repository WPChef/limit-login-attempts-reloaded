<?php

namespace LLAR\Core\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MemberPressIntegration extends BaseIntegration {

	/**
	 * Get the name of the plugin this integration supports
	 *
	 * @return string
	 */
	public function get_plugin_name() {
		return 'MemberPress';
	}

	/**
	 * Check if MemberPress plugin is active
	 *
	 * @return bool
	 */
	public static function is_plugin_active() {
		return function_exists( 'mepr_validate_login' ) || class_exists( 'MeprUser' );
	}

	/**
	 * Register all hooks and filters for MemberPress
	 *
	 * @return void
	 */
	public function register_hooks() {
		if ( ! static::is_plugin_active() ) {
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
	public function is_login_page() {
		if ( ! static::is_plugin_active() ) {
			return false;
		}

		// Check for POST request with login credentials
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Only checking for presence, not processing
		$has_post_credentials = isset( $_POST['log'] ) && isset( $_POST['pwd'] );

		// Most reliable check: MemberPress login form has specific identifier
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Only checking for presence, not processing
		if ( isset( $_POST['mepr_process_login_form'] ) ) {
			return true;
		}

		// Check if we're on MemberPress login page using MeprUser method (if available)
		// This works for both GET and POST requests
		if ( class_exists( 'MeprUser' ) && method_exists( 'MeprUser', 'is_login_page' ) ) {
			global $post;
			if ( $post && \MeprUser::is_login_page( $post ) ) {
				return true;
			}
		}

		// Check if we're on MemberPress login page via MeprOptions (if available)
		// This works for both GET and POST requests
		if ( class_exists( 'MeprOptions' ) ) {
			$mepr_options = \MeprOptions::fetch();
			if ( ! empty( $mepr_options->login_page_id ) && is_page( $mepr_options->login_page_id ) ) {
				// For GET requests, return true if we're on the login page
				// For POST requests, also require login credentials
				if ( ! $has_post_credentials ) {
					// GET request - we're on the login page
					return true;
				} else {
					// POST request with credentials - verify it's not standard WP login
					// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Only checking for presence, not processing
					if ( isset( $_SERVER['REQUEST_URI'] ) ) {
						$request_uri = $_SERVER['REQUEST_URI'];
						// Exclude standard WordPress login page
						if ( ! preg_match( '/wp-login\.php/i', $request_uri ) ) {
							return true;
						}
					} else {
						return true;
					}
				}
			}
		}

		// For POST requests only: check if we have login credentials
		// but exclude standard WordPress login page
		if ( $has_post_credentials ) {
			// Exclude standard WordPress login page more reliably
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Only checking for presence, not processing
			if ( isset( $_SERVER['REQUEST_URI'] ) ) {
				$request_uri = $_SERVER['REQUEST_URI'];
				// Check for wp-login.php in various forms (handles custom paths, multisite, etc.)
				if ( preg_match( '/wp-login\.php/i', $request_uri ) ) {
					return false;
				}
			}
			// If we have login fields but none of the MemberPress-specific checks passed,
			// and it's not standard WP login, it's likely not a MemberPress login
			return false;
		}

		// No POST credentials and not on MemberPress login page
		return false;
	}

	/**
	 * Get login credentials from the request
	 *
	 * @return array|null
	 */
	public function get_login_credentials() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reading POST data for validation, nonce checked by MemberPress
		if ( ! isset( $_POST['log'] ) || ! isset( $_POST['pwd'] ) ) {
			return null;
		}

		return array(
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reading POST data for validation, nonce checked by MemberPress
			'username' => sanitize_text_field( wp_unslash( $_POST['log'] ) ),
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reading POST data for validation, nonce checked by MemberPress
			'password' => wp_unslash( $_POST['pwd'] ), // Password should not be sanitized, but needs wp_unslash() to remove magic quotes
		);
	}

	/**
	 * Display error message on MemberPress login page
	 *
	 * @param string $message Error message
	 * @return void
	 */
	public function display_error( $message ) {
		// MemberPress handles errors through its own mechanisms
		// Errors are added through mepr_validate_login_handler
	}

	/**
	 * Check if this is MemberPress registration page
	 *
	 * @return bool
	 */
	public function is_registration_page() {
		if ( ! static::is_plugin_active() ) {
			return false;
		}

		// Most reliable check: MemberPress registration form has specific identifier
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Only checking for presence, not processing
		if ( isset( $_POST['mepr_process_signup_form'] ) || isset( $_POST['mepr_process_checkout_form'] ) ) {
			return true;
		}

		// Check if we're on MemberPress signup/checkout page via MeprOptions (if available)
		if ( class_exists( 'MeprOptions' ) ) {
			$mepr_options = MeprOptions::fetch();
			// Check if we're on signup page
			if ( ! empty( $mepr_options->signup_page_id ) && is_page( $mepr_options->signup_page_id ) ) {
				// Additional check: ensure we have registration-related POST data
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Only checking for presence, not processing
				if ( isset( $_POST['user_login'] ) || isset( $_POST['user_email'] ) ) {
					return true;
				}
			}
		}

		// Fallback: check for MemberPress-specific registration fields
		// Only if we have both user_login/user_email AND MemberPress is active
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Only checking for presence, not processing
		$has_registration_fields = isset( $_POST['user_login'] ) || isset( $_POST['user_email'] );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Only checking for presence, not processing
		$has_mepr_action = isset( $_POST['action'] ) && $_POST['action'] === 'register';

		// Require both conditions to reduce false positives
		if ( $has_registration_fields && $has_mepr_action ) {
			return true;
		}

		return false;
	}

	/**
	 * Get registration data from the request
	 *
	 * @return array|null
	 */
	public function get_registration_data() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reading POST data for validation, nonce checked by MemberPress
		if ( empty( $_POST['user_login'] ) && empty( $_POST['user_email'] ) ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reading POST data for validation, nonce checked by MemberPress
		$user_login = isset( $_POST['user_login'] ) ? sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reading POST data for validation, nonce checked by MemberPress
		// Note: sanitize_email() is used here for form data retrieval
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
	public function display_registration_error( $message ) {
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
	public function mepr_validate_login_handler( $errors, $params = array() ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- MemberPress handles nonce verification
		if ( ! isset( $_POST['log'] ) || ! isset( $_POST['pwd'] ) ) {
			return $errors;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- MemberPress handles nonce verification
		$log = sanitize_text_field( wp_unslash( $_POST['log'] ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- MemberPress handles nonce verification
		$pwd = isset( $_POST['pwd'] ) ? wp_unslash( $_POST['pwd'] ) : ''; // Password should not be sanitized, but needs wp_unslash() to remove magic quotes

		// Trigger authenticate filter to track credentials and check lockouts
		// This sets $limit_login_nonempty_credentials and $_SESSION['login_attempts_left']
		// We don't block here - MemberPress will handle blocking if needed
		// Note: Result is intentionally ignored - we only need side effects (setting global variables)
		apply_filters( 'authenticate', null, $log, $pwd );

		// Return errors unchanged - we're only tracking, not blocking
		return $errors;
	}
}
