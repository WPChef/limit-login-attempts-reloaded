<?php

namespace LLAR\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MfaController {

	/**
	 * Flag to show rescue popup (set when MFA is enabled without codes)
	 *
	 * @var bool
	 */
	public $show_rescue_popup = false;

	/**
	 * Prepared roles for MFA tab (with translated and sanitized names)
	 *
	 * @var array
	 */
	public $prepared_roles = array();

	/**
	 * Editable roles data for MFA tab (for admin role check)
	 *
	 * @var array
	 */
	public $editable_roles = array();

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

		// Enqueue scripts and styles for MFA tab
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 20 );
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
		// Check user capabilities first
		$this->check_user_capabilities();

		// Check nonce (standard WordPress way)
		// Use check_ajax_referer with die=false to handle errors gracefully
		if ( ! check_ajax_referer( 'limit-login-attempts-options', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh the page and try again.', 'limit-login-attempts-reloaded' ) ) );
			return;
		}

		// Generate codes (returns plain codes)
		try {
			$plain_codes = $this->generate_rescue_codes();
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => __( 'Failed to generate codes: ', 'limit-login-attempts-reloaded' ) . $e->getMessage() ) );
			return;
		}

		if ( empty( $plain_codes ) || ! is_array( $plain_codes ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to generate rescue codes. Please try again.', 'limit-login-attempts-reloaded' ) ) );
			return;
		}

		// Generate rescue URLs for display
		$rescue_urls = array();
		foreach ( $plain_codes as $code ) {
			$rescue_urls[] = $this->get_rescue_url( $code );
		}

		// Generate HTML content for PDF generation
		try {
			$html_content = $this->generate_rescue_file_html( $plain_codes );
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => __( 'Failed to generate PDF content: ', 'limit-login-attempts-reloaded' ) . $e->getMessage() ) );
			return;
		}

		// Generate token for download confirmation (optional, for logging)
		$download_token = wp_generate_password( 32, false );
		Config::update( 'mfa_rescue_download_token', $download_token );

		// Enable MFA
		Config::update( 'mfa_enabled', 1 );
		
		// Clear transient checkbox state since MFA is now saved
		delete_transient( 'llar_mfa_checkbox_state' );

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

		// Generate rescue URLs
		$rescue_urls = array();
		foreach ( $plain_codes as $code ) {
			$rescue_urls[] = $this->get_rescue_url( $code );
		}

		// Load PDF template with variables
		ob_start();
		include LLA_PLUGIN_DIR . 'views/mfa-rescue-pdf.php';
		$html = ob_get_clean();

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

	/**
	 * Enqueue scripts and styles for MFA tab
	 */
	public function enqueue_scripts() {
		// Only on LLAR admin pages
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'limit-login-attempts' ) {
			return;
		}

		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'settings';
		if ( $current_tab !== 'mfa' ) {
			return;
		}

		// Create nonce for MFA code generation
		$mfa_generate_codes = wp_create_nonce( 'limit-login-attempts-options' );

		// Add MFA-specific data to localized script
		// Note: wp_localize_script will add/merge data with existing llar_vars
		wp_localize_script( 'lla-main', 'llar_vars', array(
			'nonce_mfa_generate_codes' => $mfa_generate_codes,
			'ajax_url'                 => admin_url( 'admin-ajax.php' ),
		) );

		// Enqueue PDF libraries only on MFA tab (admin only, to avoid loading on frontend)
		wp_enqueue_script( 'html2canvas', 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js', array(), '1.4.1', true );
		wp_enqueue_script( 'jspdf', 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js', array(), '2.5.1', true );
	}

	/**
	 * Handle MFA settings form submission
	 *
	 * @param bool $has_capability Whether user has required capability
	 * @return bool True if popup should be shown, false otherwise
	 */
	public function handle_settings_submission( $has_capability ) {
		// Check if this is MFA settings form
		if ( ! isset( $_POST['llar_update_mfa_settings'] ) ) {
			return false;
		}

		check_admin_referer( 'limit-login-attempts-options' );

		// Check user capabilities
		if ( ! $has_capability ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'limit-login-attempts-reloaded' ) );
		}

		// Handle MFA enabled/disabled
		if ( isset( $_POST['mfa_enabled'] ) && $_POST['mfa_enabled'] ) {
			// Check if rescue popup should be shown
			if ( $this->should_show_rescue_popup() ) {
				// Don't save MFA yet, show popup via JavaScript
				// MFA will be saved after file download via WordPress AJAX
				// Set flag for JavaScript
				$this->show_rescue_popup = true;
				// Store checkbox state in transient so it persists after page reload
				set_transient( 'llar_mfa_checkbox_state', 1, 300 ); // 5 minutes
				return true;
			} else {
				// Codes already exist, just save MFA
				Config::update( 'mfa_enabled', 1 );
				// Clear transient if exists
				delete_transient( 'llar_mfa_checkbox_state' );
			}
		} else {
			// Disabling MFA - cleanup codes
			$this->cleanup_rescue_codes();
			Config::update( 'mfa_enabled', 0 );
			// Clear transient if exists
			delete_transient( 'llar_mfa_checkbox_state' );
		}

		// Save selected roles - use editable roles and optimize validation
		$mfa_roles = array();
		if ( isset( $_POST['mfa_roles'] ) && is_array( $_POST['mfa_roles'] ) && ! empty( $_POST['mfa_roles'] ) ) {
			// Get editable roles (cached by WordPress on request level)
			$editable_roles = get_editable_roles();
			$editable_role_keys = array_keys( $editable_roles );
			
			// Sanitize and filter roles - remove empty values and validate against editable roles
			$sanitized_roles = array_filter( 
				array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['mfa_roles'] ) ),
				'strlen' // Remove empty strings
			);
			
			// Validate against editable roles only
			$mfa_roles = array_intersect( $sanitized_roles, $editable_role_keys );
		}
		Config::update( 'mfa_roles', $mfa_roles );

		return false;
	}

	/**
	 * Prepare roles data for MFA tab
	 * Should be called before including view to ensure data is ready
	 */
	public function prepare_roles_data() {
		// Get editable roles and prepare translated names with sanitization
		$editable_roles = get_editable_roles();
		$prepared_roles = array();
		foreach ( $editable_roles as $role_key => $role_data ) {
			// Sanitize translated role name for security
			$prepared_roles[ $role_key ] = esc_html( translate_user_role( $role_data['name'] ) );
		}
		// Store for view
		$this->prepared_roles = $prepared_roles;
		$this->editable_roles = $editable_roles;
	}

	/**
	 * Get MFA settings for view
	 *
	 * @return array Array with mfa_enabled, mfa_temporarily_disabled, mfa_roles, prepared_roles, editable_roles, show_rescue_popup
	 */
	public function get_settings_for_view() {
		$mfa_enabled_raw = Config::get( 'mfa_enabled', false );
		$mfa_temporarily_disabled = $this->is_mfa_temporarily_disabled();
		$mfa_checkbox_state = get_transient( 'llar_mfa_checkbox_state' );
		
		// MFA is considered enabled if it's enabled in config AND not temporarily disabled
		// OR if checkbox state is stored (popup is shown)
		$mfa_enabled = ( $mfa_enabled_raw && ! $mfa_temporarily_disabled ) || ( $mfa_checkbox_state === 1 );

		$mfa_roles = Config::get( 'mfa_roles', array() );
		
		// Ensure $mfa_roles is always an array
		if ( ! is_array( $mfa_roles ) ) {
			$mfa_roles = array();
		}

		return array(
			'mfa_enabled'              => $mfa_enabled,
			'mfa_temporarily_disabled' => $mfa_temporarily_disabled,
			'mfa_roles'                => $mfa_roles,
			'prepared_roles'           => $this->prepared_roles,
			'editable_roles'           => $this->editable_roles,
			'show_rescue_popup'        => $this->show_rescue_popup,
		);
	}
}
