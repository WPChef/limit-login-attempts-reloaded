<?php

namespace LLAR\Core\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WooCommerceIntegration extends BaseIntegration {

	/**
	 * Get the name of the plugin this integration supports
	 *
	 * @return string
	 */
	public function get_plugin_name() {
		return 'WooCommerce';
	}

	/**
	 * Check if WooCommerce plugin is active
	 *
	 * @return bool
	 */
	public static function is_plugin_active() {
		return function_exists( 'is_account_page' ) && function_exists( 'wc_add_notice' );
	}

	/**
	 * Register all hooks and filters for WooCommerce
	 *
	 * @return void
	 */
	public function register_hooks() {
		if ( ! static::is_plugin_active() ) {
			return;
		}

		// Add notices to woocommerce login page
		add_action( 'wp_head', array( $this, 'add_wc_notices' ) );

		// Protect WooCommerce registration
		add_action( 'woocommerce_register_post', array( $this, 'wc_register_post_handler' ), 10, 3 );
		add_filter( 'woocommerce_registration_errors', array( $this, 'wc_registration_errors_handler' ), 10, 3 );
	}

	/**
	 * Check if this is WooCommerce login page
	 *
	 * @return bool
	 */
	public function is_login_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Only checking for presence, not processing
		return static::is_plugin_active() && is_account_page() && isset( $_POST['username'] );
	}

	/**
	 * Get login credentials from the request
	 *
	 * @return array|null
	 */
	public function get_login_credentials() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reading POST data for validation, nonce checked by WooCommerce
		if ( ! isset( $_POST['username'] ) || ! isset( $_POST['password'] ) ) {
			return null;
		}

		return array(
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reading POST data for validation, nonce checked by WooCommerce
			'username' => sanitize_text_field( wp_unslash( $_POST['username'] ) ),
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reading POST data for validation, nonce checked by WooCommerce
			'password' => $_POST['password'], // Password should not be sanitized
		);
	}

	/**
	 * Display error message on WooCommerce login page
	 *
	 * @param string $message Error message
	 * @return void
	 */
	public function display_error( $message ) {
		if ( static::is_plugin_active() && is_account_page() ) {
			wc_add_notice( $message, 'error' );
		}
	}

	/**
	 * Check if this is WooCommerce registration page
	 *
	 * @return bool
	 */
	public function is_registration_page() {
		// WooCommerce uses standard WordPress registration fields
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Only checking for presence, not processing
		$result = static::is_plugin_active() && is_account_page() && ( isset( $_POST['user_login'] ) || isset( $_POST['user_email'] ) );
		return $result;
	}

	/**
	 * Get registration data from the request
	 *
	 * @return array|null
	 */
	public function get_registration_data() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reading POST data for validation, nonce checked by WooCommerce
		if ( empty( $_POST['user_login'] ) && empty( $_POST['user_email'] ) ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reading POST data for validation, nonce checked by WooCommerce
		$user_login = isset( $_POST['user_login'] ) ? sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reading POST data for validation, nonce checked by WooCommerce
		// Note: sanitize_email() is used here for form data retrieval, but sanitize_user() is used in wc_register_post_handler()
		// for API calls to match the original API behavior
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
	 * Display error message on WooCommerce registration page
	 *
	 * @param string $message Error message
	 * @return void
	 */
	public function display_registration_error( $message ) {
		if ( static::is_plugin_active() && is_account_page() ) {
			wc_add_notice( $message, 'error' );
		}
	}

	/**
	 * Errors on WooCommerce account page
	 */
	public function add_wc_notices() {
		global $limit_login_just_lockedout, $limit_login_nonempty_credentials, $limit_login_my_error_shown;

		if ( ! $limit_login_nonempty_credentials ) {
			return;
		}

		// Prevent duplicate error messages if already shown elsewhere
		if ( ! empty( $limit_login_my_error_shown ) ) {
			return;
		}

		/*
		* During lockout we do not want to show any other error messages (like
		* unknown user or empty password).
		*/
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Only checking POST for display logic, not processing
		if ( empty( $_POST ) && ! $this->is_login_allowed() && ! $limit_login_just_lockedout ) {
			if ( is_account_page() ) {
				wc_add_notice( $this->get_error_message(), 'error' );
				// Mark error as shown to prevent duplicate messages
				$limit_login_my_error_shown = true;
			}
		}
	}

	/**
	 * For WooCommerce registration
	 * Check registration attempt before WooCommerce processes it
	 *
	 * @param string $username Username
	 * @param string $user_email User email
	 * @param WP_Error $errors Error object
	 * @return void
	 */
	public function wc_register_post_handler( $username, $user_email, $errors ) {
		if ( ! $this->is_registration_limited() ) {
			return;
		}

		if ( empty( $username ) && empty( $user_email ) ) {
			return;
		}

		// Exit only if BOTH fields are invalid (empty or invalid)
		// Continue if at least one field is valid
		// Logic: exit if (username is invalid) AND (email is invalid)
		$username_invalid = empty( $username ) || ! validate_username( $username );
		$email_invalid = empty( $user_email ) || ! is_email( $user_email );
		if ( $username_invalid && $email_invalid ) {
			return;
		}

		// Use sanitize_user() for API calls to match original API behavior
		// Note: This differs from get_registration_data() which uses sanitize_email() for form data retrieval
		$user_login_sanitize = sanitize_user( $username );
		$user_email_sanitize = sanitize_user( $user_email );

		// Check any non-empty
		$check_combo = ! empty( $user_login_sanitize ) ? $user_login_sanitize : $user_email_sanitize;

		$response = $this->check_registration_api( $check_combo );

		// If $user_login is not empty, we will also check $user_email
		if ( ! empty( $user_login_sanitize ) && 'deny' !== $response['result'] ) {
			if ( empty( $user_email ) || ! is_email( $user_email ) ) {
				return;
			}

			$response = $this->check_registration_api( $user_email_sanitize );
		}

		if ( 'deny' === $response['result'] ) {
			// Set the marker and the error
			$this->llar_instance->user_blocking  = true;
			$this->llar_instance->error_messages = __( '<strong>Error</strong>: Registration is currently disabled.', 'limit-login-attempts-reloaded' );
		}
	}

	/**
	 * Correcting errors in the presence of a registration prohibition marker for WooCommerce
	 *
	 * @param WP_Error $errors Error object
	 * @param string $username Username
	 * @param string $user_email User email
	 * @return WP_Error
	 */
	public function wc_registration_errors_handler( $errors, $username, $user_email ) {
		// Checking the marker
		if ( $this->llar_instance->user_blocking ) {
			$errors->add( 'user_blocking', $this->llar_instance->error_messages );
		}

		return $errors;
	}
}
