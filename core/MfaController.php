<?php

namespace LLAR\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MfaController {

	/**
	 * Register all hooks
	 */
	public function register() {
		// WordPress AJAX for generating rescue codes
		add_action( 'wp_ajax_llar_mfa_generate_rescue_codes', array( $this, 'ajax_generate_rescue_codes' ) );
		
		// Register query var for public endpoint
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		
		// Handle public rescue endpoint
		add_action( 'template_redirect', array( $this, 'handle_rescue_endpoint' ) );
		
		// WP Cron for automatic MFA re-enable
		add_action( 'llar_mfa_rescue_timeout', array( $this, 'enable_mfa_after_timeout' ) );
	}

	/**
	 * Add query var for rescue code (standard WordPress way)
	 *
	 * @param array $vars Existing query vars
	 * @return array Modified query vars
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'llar_rescue';
		return $vars;
	}

	/**
	 * Generate 10 rescue codes (64 characters each, alphanumeric)
	 * Returns plain codes for file generation, stores hashes in Config
	 *
	 * @return array Plain codes (for file generation only)
	 */
	public function generate_rescue_codes() {
		$codes = array();
		$plain_codes = array(); // Temporary array for file

		for ( $i = 0; $i < 10; $i++ ) {
			$code = wp_generate_password( 64, false ); // alphanumeric
			$hash = wp_hash_password( $code );
			$codes[] = array(
				'hash'    => $hash,
				'used'    => false,
				'used_at' => null,
			);
			$plain_codes[] = $code; // Save for file (only in memory)
		}

		// Replace old codes with new ones
		Config::update( 'mfa_rescue_codes', $codes );
		return $plain_codes; // Return plain codes for file generation (only in memory)
	}

	/**
	 * WordPress AJAX callback for generating rescue codes
	 */
	public function ajax_generate_rescue_codes() {
		// Check user capabilities
		$this->check_user_capabilities();

		// Check nonce (standard WordPress way)
		check_ajax_referer( 'limit-login-attempts-options', 'nonce' );

		// Generate codes (returns plain codes)
		$plain_codes = $this->generate_rescue_codes();

		// Generate rescue URLs for display
		$rescue_urls = array();
		foreach ( $plain_codes as $code ) {
			$rescue_urls[] = $this->get_rescue_url( $code );
		}

		// Generate HTML content for PDF generation
		$html_content = $this->generate_rescue_file_html( $plain_codes );

		// Generate token for download confirmation (optional, for logging)
		$download_token = wp_generate_password( 32, false );
		Config::update( 'mfa_rescue_download_token', $download_token );

		// Enable MFA
		Config::update( 'mfa_enabled', 1 );

		// Plain codes are removed from memory after this block
		// URLs and HTML content are returned in response

		// Standard WordPress way to send JSON response
		wp_send_json_success( array(
			'rescue_urls'  => $rescue_urls, // URLs for display
			'html_content' => $html_content, // HTML for PDF generation
			'domain'       => wp_parse_url( home_url(), PHP_URL_HOST ),
		) );
	}

	/**
	 * Check user capabilities (supports Multisite)
	 */
	private function check_user_capabilities() {
		// Support Multisite: in network sites manage_options is super admin right
		if ( is_multisite() ) {
			if ( ! is_super_admin() ) {
				wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
			}
		} else {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
			}
		}
	}

	/**
	 * Get client IP address (with proxy and CDN support)
	 *
	 * @return string Client IP address
	 */
	private function get_client_ip() {
		$ip_keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				// For X-Forwarded-For take first IP
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				return $ip;
			}
		}
		return '';
	}

	/**
	 * Handle public rescue endpoint
	 * Uses standard WordPress query vars mechanism
	 */
	public function handle_rescue_endpoint() {
		// Get hash_id instead of plain code (security)
		$hash_id = get_query_var( 'llar_rescue' );
		if ( empty( $hash_id ) ) {
			return; // Query var not set, exit
		}

		// Rate limiting: protection against brute force attacks (required for production)
		// Increased blocking period to 1 hour for better security
		$client_ip = $this->get_client_ip();
		$transient_key = 'llar_rescue_attempts_' . md5( $client_ip );
		$attempts = get_transient( $transient_key ) ?: 0;

		if ( $attempts >= 5 ) { // Limit: 5 attempts per period
			wp_die( 'Too many attempts. Please try again later.', 'LLAR MFA Rescue', array( 'response' => 429 ) );
		}

		// Increment counter on each check (even invalid)
		set_transient( $transient_key, $attempts + 1, HOUR_IN_SECONDS ); // Block for 1 hour (not 5 minutes!)

		// Get plain code from transient by hash_id
		$transient_rescue_key = 'llar_rescue_' . sanitize_text_field( $hash_id );
		$plain_code = get_transient( $transient_rescue_key );

		if ( false === $plain_code ) {
			// Hash not found or expired (one-time, 5 minutes)
			wp_die( 'Invalid or expired rescue link', 'LLAR MFA Rescue', array( 'response' => 403 ) );
		}

		// Delete transient immediately after getting (one-time use)
		delete_transient( $transient_rescue_key );

		// Verify code
		$codes = Config::get( 'mfa_rescue_codes', array() );
		
		if ( ! is_array( $codes ) ) {
			$codes = array();
		}

		foreach ( $codes as $index => $code_data ) {
			if ( ! isset( $code_data['used'] ) || $code_data['used'] ) {
				continue;
			}
			if ( wp_check_password( $plain_code, $code_data['hash'] ) ) {
				// Mark as used
				$codes[ $index ]['used'] = true;
				$codes[ $index ]['used_at'] = time();
				Config::update( 'mfa_rescue_codes', $codes );

				// Disable MFA for an hour
				$this->disable_mfa_temporarily();

				// Redirect with message (standard WordPress way)
				wp_safe_redirect( add_query_arg( 'llar_rescue_success', '1', home_url() ) );
				exit;
			}
		}

		// Code not found
		wp_die( 'Invalid rescue code', 'LLAR MFA Rescue', array( 'response' => 403 ) );
	}

	/**
	 * Disable MFA temporarily (for 1 hour)
	 */
	public function disable_mfa_temporarily() {
		Config::update( 'mfa_enabled', 0 );

		// If MFA is already disabled by another code, just update timeout
		$current_timeout = Config::get( 'mfa_temporarily_disabled_until' );
		$new_timeout = time() + HOUR_IN_SECONDS;

		if ( $current_timeout && $current_timeout > $new_timeout ) {
			// Already disabled for longer period, don't reduce it
			return;
		}

		Config::update( 'mfa_temporarily_disabled_until', $new_timeout );

		// Always reschedule event to new time (clear old before creating new)
		wp_clear_scheduled_hook( 'llar_mfa_rescue_timeout' );
		wp_schedule_single_event( $new_timeout, 'llar_mfa_rescue_timeout' );
	}

	/**
	 * Check if MFA is temporarily disabled via rescue code
	 *
	 * @return bool True if MFA is temporarily disabled
	 */
	public function is_mfa_temporarily_disabled() {
		$disabled_until = Config::get( 'mfa_temporarily_disabled_until' );
		if ( $disabled_until && $disabled_until > time() ) {
			return true;
		}
		// If timeout expired, clear it
		if ( $disabled_until && $disabled_until <= time() ) {
			Config::delete( 'mfa_temporarily_disabled_until' );
		}
		return false;
	}

	/**
	 * Enable MFA after timeout (WP Cron callback)
	 */
	public function enable_mfa_after_timeout() {
		// Re-enable MFA after timeout
		Config::update( 'mfa_enabled', 1 );
		Config::update( 'mfa_temporarily_disabled_until', null );
	}

	/**
	 * Check if rescue popup should be shown
	 *
	 * @return bool True if popup should be shown
	 */
	public function should_show_rescue_popup() {
		$codes = Config::get( 'mfa_rescue_codes', array() );
		
		if ( ! is_array( $codes ) ) {
			$codes = array();
		}
		
		// Show popup if no codes exist or all codes are used
		if ( empty( $codes ) ) {
			return true;
		}
		// Check if all codes are used
		$all_used = true;
		foreach ( $codes as $code_data ) {
			if ( ! isset( $code_data['used'] ) || ! $code_data['used'] ) {
				$all_used = false;
				break;
			}
		}
		return $all_used;
	}

	/**
	 * Cleanup rescue codes when MFA is disabled
	 */
	public function cleanup_rescue_codes() {
		// Delete all rescue codes
		Config::delete( 'mfa_rescue_codes' );

		// Cancel WP Cron event if exists
		wp_clear_scheduled_hook( 'llar_mfa_rescue_timeout' );

		// Clear temporary token
		Config::delete( 'mfa_rescue_download_token' );

		// Clear temporary disable
		Config::delete( 'mfa_temporarily_disabled_until' );
	}

	/**
	 * Generate HTML file with rescue links
	 *
	 * @param array $plain_codes Plain rescue codes
	 * @return string HTML content
	 */
	public function generate_rescue_file_html( $plain_codes ) {
		$site_url = home_url();
		$domain = wp_parse_url( $site_url, PHP_URL_HOST );

		// Generate HTML optimized for PDF generation
		// Use inline styles for better compatibility with html2pdf.js
		// Use explicit dark colors (#000000) to ensure text is visible in PDF
		// A4 size: 210mm x 297mm (8.27in x 11.69in)
		// Content width for A4 with margins: ~750px
		$html = '<div style="font-family: Arial, Helvetica, sans-serif; padding: 30px 40px; width: 750px; max-width: 750px; margin: 0 auto; background-color: #ffffff; color: #000000; box-sizing: border-box;">' . "\n";
		$html .= '<h1 style="color: #000000 !important; font-size: 24px; font-weight: bold; margin: 0 0 25px 0; padding-bottom: 12px; border-bottom: 2px solid #4ACAD8; text-align: left;">' . esc_html( $domain ) . ' LLAR 2FA Rescue Links</h1>' . "\n";
		$html .= '<ol style="margin: 0; padding-left: 30px; line-height: 1.8; list-style-type: decimal; color: #000000 !important; text-align: left;">' . "\n";

		foreach ( $plain_codes as $index => $code ) {
			$rescue_url = $this->get_rescue_url( $code );
			$html .= '<li style="margin-bottom: 15px; padding: 12px; background-color: #f6fbff; border-radius: 4px; word-break: break-all; border: 1px solid #e0f0f5; color: #000000 !important; text-align: left;">';
			$html .= '<span style="color: #0066cc !important; text-decoration: underline; font-size: 13px; display: block; font-weight: normal; text-align: left;">' . esc_html( $rescue_url ) . '</span>';
			$html .= '</li>' . "\n";
		}

		$html .= '</ol>' . "\n";
		$html .= '<div style="margin-top: 30px; padding: 15px; background-color: #fff9e6; border-left: 4px solid #ff7c06; border-radius: 4px; text-align: left;">' . "\n";
		$html .= '<p style="margin: 0; color: #000000 !important; font-size: 13px; line-height: 1.6; text-align: left;">';
		$html .= '<strong style="color: #000000 !important; font-weight: bold;">Important:</strong> By clicking a link above, 2FA will be fully disabled on <strong style="color: #000000 !important; font-weight: bold;">' . esc_html( $domain ) . '</strong> for 1 hour. Each link can only be used once.';
		$html .= '</p>' . "\n";
		$html .= '</div>' . "\n";
		$html .= '</div>';

		return $html;
	}

	/**
	 * Get rescue URL for a code
	 * Generates one-time hash instead of plain code (security)
	 *
	 * @param string $plain_code Plain rescue code
	 * @return string Rescue URL with hash
	 */
	public function get_rescue_url( $plain_code ) {
		// Generate one-time hash instead of plain code
		// Use AUTH_SALT if defined, otherwise use a fallback
		$salt = defined( 'AUTH_SALT' ) ? AUTH_SALT : ( defined( 'NONCE_SALT' ) ? NONCE_SALT : 'llar_rescue_salt' );
		$hash_id = md5( $plain_code . $salt . time() );

		// Save plain code in temporary transient (5 minutes, one-time)
		$transient_key = 'llar_rescue_' . $hash_id;
		set_transient( $transient_key, $plain_code, 300 ); // 5 minutes

		// URL contains only hash, not plain code
		return add_query_arg( 'llar_rescue', $hash_id, home_url() );
	}
}
