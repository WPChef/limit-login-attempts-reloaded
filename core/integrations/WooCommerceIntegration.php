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
	public function is_plugin_active() {
		return function_exists( 'is_account_page' ) && function_exists( 'wc_add_notice' );
	}

	/**
	 * Register all hooks and filters for WooCommerce
	 *
	 * @return void
	 */
	public function register_hooks() {
		if ( ! $this->is_plugin_active() ) {
			return;
		}

		// Add notices to woocommerce login page
		add_action( 'wp_head', array( $this, 'add_wc_notices' ) );
	}

	/**
	 * Check if this is WooCommerce login page
	 *
	 * @return bool
	 */
	public function is_login_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Only checking for presence, not processing
		return $this->is_plugin_active() && is_account_page() && isset( $_POST['username'] );
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
		if ( $this->is_plugin_active() && is_account_page() ) {
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
		return $this->is_plugin_active() && is_account_page() && ( isset( $_POST['user_login'] ) || isset( $_POST['user_email'] ) );
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
		if ( $this->is_plugin_active() && is_account_page() ) {
			wc_add_notice( $message, 'error' );
		}
	}

	/**
	 * Errors on WooCommerce account page
	 */
	public function add_wc_notices() {
		global $limit_login_just_lockedout, $limit_login_nonempty_credentials;

		if ( ! $limit_login_nonempty_credentials ) {
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
			}
		}
	}
}
