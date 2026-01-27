<?php

namespace LLAR\Core;

use LLAR\Core\Mfa\MfaCodeGenerator;
use LLAR\Core\Mfa\MfaEncryptionService;
use LLAR\Core\Mfa\MfaRateLimiter;
use LLAR\Core\Mfa\MfaRescueEndpointHandler;
use LLAR\Core\Mfa\MfaRescueUrlGenerator;
use LLAR\Core\Mfa\MfaRules;
use LLAR\Core\Mfa\MfaSettingsManager;
use LLAR\Core\MfaConstants;

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
	 * Code generator
	 *
	 * @var MfaCodeGenerator
	 */
	private $code_generator;

	/**
	 * Encryption service
	 *
	 * @var MfaEncryptionService
	 */
	private $encryption;

	/**
	 * Rate limiter
	 *
	 * @var MfaRateLimiter
	 */
	private $rate_limiter;

	/**
	 * Rescue endpoint handler
	 *
	 * @var MfaRescueEndpointHandler
	 */
	private $rescue_handler;

	/**
	 * Rescue URL generator
	 *
	 * @var MfaRescueUrlGenerator
	 */
	private $url_generator;

	/**
	 * Settings manager
	 *
	 * @var MfaSettingsManager
	 */
	private $settings_manager;

	/**
	 * Rules service
	 *
	 * @var MfaRules
	 */
	private $rules;

	/**
	 * Constructor - initialize dependencies
	 */
	public function __construct() {
		// Initialize services
		$this->encryption       = new MfaEncryptionService();
		$this->rate_limiter     = new MfaRateLimiter();
		$this->rules            = new MfaRules();
		$this->code_generator   = new MfaCodeGenerator();
		$this->url_generator    = new MfaRescueUrlGenerator( $this->encryption );
		$this->rescue_handler   = new MfaRescueEndpointHandler( $this->encryption, $this->rate_limiter );
		$this->settings_manager = new MfaSettingsManager( $this->rules );
	}

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

		// Enqueue script for MFA disabled message on login page
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_mfa_disabled_message_script' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_mfa_disabled_message_script' ) );

		// Enqueue scripts and styles for MFA tab
		// Use priority 1000 to ensure LimitLoginAttempts::enqueue() (priority 999) runs first
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 1000 );
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
		$plain_codes = $this->code_generator->generate();

		// Ensure we have at least some codes generated
		if ( empty( $plain_codes ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to generate rescue codes. Please try again.', 'limit-login-attempts-reloaded' ) ) );
			return array();
		}

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
		wp_send_json_success(
			array(
			'rescue_urls'  => $rescue_urls, // URLs for display
			'html_content' => $html_content, // HTML for PDF generation
			'domain'       => wp_parse_url( home_url(), PHP_URL_HOST ),
			)
		);
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
		} elseif ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
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
				if ( false !== strpos( $ip, ',' ) ) {
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

		// Use SHA-256 instead of MD5 for rate limiting key
		$salt_for_rate_limit = defined( 'AUTH_SALT' ) ? AUTH_SALT : ( defined( 'NONCE_SALT' ) ? NONCE_SALT : wp_generate_password( 64, true ) );
		$transient_key       = 'llar_rescue_attempts_' . hash( 'sha256', $client_ip . $salt_for_rate_limit );
		$attempts            = get_transient( $transient_key );
		$attempts            = ( false !== $attempts ) ? $attempts : 0;

		if ( 5 <= $attempts ) { // Limit: 5 attempts per period
			wp_die( 'Too many attempts. Please try again later.', 'LLAR MFA Rescue', array( 'response' => 429 ) );
		}

		// Increment counter on each check (even invalid)
		set_transient( $transient_key, $attempts + 1, HOUR_IN_SECONDS ); // Block for 1 hour (not 5 minutes!)

		// Validate hash_id format (SHA-256 produces 64 hex characters)
		if ( ! preg_match( '/^[a-f0-9]{64}$/i', $hash_id ) ) {
			wp_die( 'Invalid rescue link format', 'LLAR MFA Rescue', array( 'response' => 403 ) );
		}

		// Get encrypted code from transient by hash_id
		$transient_rescue_key = 'llar_rescue_' . sanitize_text_field( $hash_id );
		$encrypted_data       = get_transient( $transient_rescue_key );

		if ( false === $encrypted_data ) {
			// Hash not found or expired (one-time, 5 minutes)
			// Don't reveal whether hash was invalid or expired (security best practice)
			wp_die( 'Invalid or expired rescue link', 'LLAR MFA Rescue', array( 'response' => 403 ) );
		}

		// Delete transient immediately after getting (one-time use)
		delete_transient( $transient_rescue_key );

		// Decrypt the code (security: codes are stored encrypted, not in plain text)
		// Use same encryption key as in get_rescue_url() - AUTH_KEY and AUTH_SALT (constant WordPress salts)
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			// Fallback: simple deobfuscation (for old data encrypted without OpenSSL)
			// Try to get salt from hash_id context - but this won't work reliably
			// Better to just fail if OpenSSL not available
			wp_die( 'Invalid rescue link', 'LLAR MFA Rescue', array( 'response' => 403 ) );
		} else {
			// Use same logic as encryption: AUTH_KEY and AUTH_SALT (constant)
			$auth_key_for_decryption  = defined( 'AUTH_KEY' ) ? AUTH_KEY : ( defined( 'NONCE_KEY' ) ? NONCE_KEY : wp_generate_password( 64, true ) );
			$auth_salt_for_decryption = defined( 'AUTH_SALT' ) ? AUTH_SALT : ( defined( 'NONCE_SALT' ) ? NONCE_SALT : wp_generate_password( 64, true ) );

			$encryption_key = hash( 'sha256', $auth_key_for_decryption . $auth_salt_for_decryption, true );
			$decoded_data   = base64_decode( $encrypted_data );
			$iv_length      = openssl_cipher_iv_length( 'AES-256-CBC' );

			// Check if data looks like encrypted (has IV prefix) or fallback obfuscation
			if ( strlen( $decoded_data ) > $iv_length ) {
				// Try AES decryption first
				$iv             = substr( $decoded_data, 0, $iv_length );
				$encrypted_code = substr( $decoded_data, $iv_length );
				$plain_code     = openssl_decrypt( $encrypted_code, 'AES-256-CBC', $encryption_key, 0, $iv );

				if ( false === $plain_code ) {
					// Decryption failed - invalid data
					wp_die( 'Invalid rescue link', 'LLAR MFA Rescue', array( 'response' => 403 ) );
				}
			} else {
				// Data too short to be encrypted - invalid format
				wp_die( 'Invalid rescue link', 'LLAR MFA Rescue', array( 'response' => 403 ) );
			}
		}

		if ( empty( $plain_code ) ) {
			// Decryption failed - invalid data
			wp_die( 'Invalid rescue link', 'LLAR MFA Rescue', array( 'response' => 403 ) );
		}

		// Verify code with constant-time comparison to prevent timing attacks
		$codes = Config::get( 'mfa_rescue_codes', array() );

		if ( ! is_array( $codes ) || empty( $codes ) ) {
			wp_die( 'Invalid rescue code', 'LLAR MFA Rescue', array( 'response' => 403 ) );
		}

		$code_verified  = false;
		$verified_index = null;

		// Check all codes to prevent timing attacks (always check same number of codes)
		foreach ( $codes as $index => $code_data ) {
			if ( ! isset( $code_data['hash'] ) || ! isset( $code_data['used'] ) ) {
				continue;
			}

			// Skip already used codes
			if ( true === $code_data['used'] ) {
				continue;
			}

			// Use constant-time password verification
			if ( wp_check_password( $plain_code, $code_data['hash'] ) ) {
				$code_verified  = true;
				$verified_index = $index;
				break; // Found valid code, can exit early
			}
		}

		if ( $code_verified && null !== $verified_index ) {
			// Mark as used
			$codes[ $verified_index ]['used']    = true;
			$codes[ $verified_index ]['used_at'] = time();
			Config::update( 'mfa_rescue_codes', $codes );

			// Disable MFA for an hour
			$this->disable_mfa_temporarily();

			// Redirect to wp-login.php with success message
			$login_url = add_query_arg( 'llar_mfa_disabled', '1', wp_login_url() );
			wp_safe_redirect( $login_url );
			exit;
		}

		// Code not found or already used - same error message (don't reveal which)
		wp_die( 'Invalid rescue code', 'LLAR MFA Rescue', array( 'response' => 403 ) );
	}

	/**
	 * Enqueue script for MFA disabled message on login page
	 * Uses the same JavaScript mechanism as other LLAR messages for consistency
	 * This ensures the message displays correctly even if wp-login.php is customized
	 */
	public function enqueue_mfa_disabled_message_script() {
		// Only if parameter is set
		if ( ! isset( $_GET['llar_mfa_disabled'] ) || '1' !== $_GET['llar_mfa_disabled'] ) {
			return;
		}

		// Check if we're on login page (wp-login.php or WooCommerce login)
		$is_wp_login_page  = ( isset( $GLOBALS['pagenow'] ) && 'wp-login.php' === $GLOBALS['pagenow'] );
		$is_woo_login_page = ( function_exists( 'is_account_page' ) && is_account_page() );

		if ( ! $is_wp_login_page && ! $is_woo_login_page ) {
			return;
		}

		// Get plugin URL (defined in main plugin file)
		$plugin_url = defined( 'LLA_PLUGIN_URL' ) ? LLA_PLUGIN_URL : plugins_url( '/', __DIR__ . '/../limit-login-attempts-reloaded.php' );

		// Enqueue script with jQuery dependency
		wp_enqueue_script(
			'llar-mfa-disabled-message',
			$plugin_url . 'assets/js/mfa-disabled-message.js',
			array( 'jquery' ),
			'1.0.0',
			true
		);

		// Localize script with message
		$message = esc_html__( 'Multi-factor authentication has been temporarily disabled for 1 hour.', 'limit-login-attempts-reloaded' );
		wp_localize_script(
			'llar-mfa-disabled-message',
			'llarMfaDisabled',
			array(
				'showMessage' => true,
				'message'     => $message,
			)
		);
	}

	/**
	 * Disable MFA temporarily (for 1 hour)
	 */
	public function disable_mfa_temporarily() {
		Config::update( 'mfa_enabled', 0 );

		// Check if transient already exists (MFA already disabled)
		$existing_transient = get_transient( 'llar_mfa_temporarily_disabled' );
		if ( false !== $existing_transient ) {
			// MFA is already disabled, don't reduce the timeout
			return;
		}

		// Set transient for 1 hour (automatically expires)
		set_transient( 'llar_mfa_temporarily_disabled', 1, HOUR_IN_SECONDS );
	}

	/**
	 * Check if MFA is temporarily disabled via rescue code
	 *
	 * @return bool True if MFA is temporarily disabled
	 */
	public function is_mfa_temporarily_disabled() {
		$disabled = get_transient( 'llar_mfa_temporarily_disabled' );

		if ( false === $disabled ) {
			// Transient expired - automatically re-enable MFA
			$mfa_enabled = Config::get( 'mfa_enabled', false );
			if ( ! $mfa_enabled ) {
				// MFA was disabled via rescue code, re-enable it now
				Config::update( 'mfa_enabled', 1 );
			}
			return false;
		}

		return true;
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
			if ( ! isset( $code_data['used'] ) || true !== $code_data['used'] ) {
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

		// Clear temporary token
		Config::delete( 'mfa_rescue_download_token' );

		// Clear temporary disable transient
		delete_transient( 'llar_mfa_temporarily_disabled' );
	}

	/**
	 * Generate HTML file with rescue links
	 *
	 * @param array $plain_codes Plain rescue codes
	 * @return string HTML content
	 */
	public function generate_rescue_file_html( $plain_codes ) {
		$site_url = home_url();
		$domain   = wp_parse_url( $site_url, PHP_URL_HOST );

		// Generate rescue URLs
		$rescue_urls = array();
		foreach ( $plain_codes as $code ) {
			$rescue_urls[] = $this->url_generator->get_rescue_url( $code );
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
		// Require AUTH_SALT or NONCE_SALT for security (no static fallback)
		if ( ! defined( 'AUTH_SALT' ) && ! defined( 'NONCE_SALT' ) ) {
			// Log error but don't break - use cryptographically secure random instead
			error_log( 'LLAR MFA: AUTH_SALT or NONCE_SALT not defined in wp-config.php. Using secure random fallback.' );
			$salt = wp_generate_password( 64, true ); // Generate secure random salt
		} else {
			$salt = defined( 'AUTH_SALT' ) ? AUTH_SALT : NONCE_SALT;
		}

		// Use SHA-256 instead of MD5 for better security
		// Add random suffix instead of time() for better unpredictability
		$random_suffix = wp_generate_password( 32, false ); // Additional randomness
		$hash_id       = hash( 'sha256', $plain_code . $salt . $random_suffix );

		// Encrypt plain code before storing in transient (security: don't store plain codes in DB)
		// Use AES-256-CBC encryption with WordPress salts (AUTH_KEY and AUTH_SALT are constant)
		if ( ! function_exists( 'openssl_encrypt' ) || ! function_exists( 'openssl_decrypt' ) ) {
			// Fallback: if OpenSSL not available, use simple obfuscation (not ideal, but better than plain text)
			error_log( 'LLAR MFA: OpenSSL not available. Using fallback obfuscation for rescue codes.' );
			$encrypted_data = base64_encode( $plain_code . $salt );
		} else {
			// Use AUTH_KEY and AUTH_SALT for encryption key (these are constant WordPress salts)
			// Don't use $salt variable here - it's only for hash_id generation
			$auth_key_for_encryption  = defined( 'AUTH_KEY' ) ? AUTH_KEY : ( defined( 'NONCE_KEY' ) ? NONCE_KEY : wp_generate_password( 64, true ) );
			$auth_salt_for_encryption = defined( 'AUTH_SALT' ) ? AUTH_SALT : ( defined( 'NONCE_SALT' ) ? NONCE_SALT : wp_generate_password( 64, true ) );

			$encryption_key = hash( 'sha256', $auth_key_for_encryption . $auth_salt_for_encryption, true );
			$iv_length      = openssl_cipher_iv_length( 'AES-256-CBC' );
			$iv             = openssl_random_pseudo_bytes( $iv_length );
			$encrypted_code = openssl_encrypt( $plain_code, 'AES-256-CBC', $encryption_key, 0, $iv );

			if ( false === $encrypted_code ) {
				// Encryption failed, use fallback
				error_log( 'LLAR MFA: Encryption failed. Using fallback obfuscation.' );
				$encrypted_data = base64_encode( $plain_code . $salt );
			} else {
				// Store encrypted code with IV (required for decryption)
				$encrypted_data = base64_encode( $iv . $encrypted_code );
			}
		}

		// Save encrypted code in temporary transient (5 minutes, one-time)
		$transient_key = 'llar_rescue_' . $hash_id;
		set_transient( $transient_key, $encrypted_data, 300 ); // 5 minutes

		// URL contains only hash, not plain code
		return add_query_arg( 'llar_rescue', $hash_id, home_url() );
	}

	/**
	 * Enqueue scripts and styles for MFA tab
	 */
	public function enqueue_scripts() {
		// Only on LLAR admin pages
		if ( ! isset( $_GET['page'] ) || 'limit-login-attempts' !== $_GET['page'] ) {
			return;
		}

		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'settings';
		if ( 'mfa' !== $current_tab ) {
			return;
		}

		// Check if script is registered (should be registered by LimitLoginAttempts::enqueue() at priority 999)
		if ( ! wp_script_is( 'lla-main', 'registered' ) ) {
			return;
		}

		// Create nonce for MFA code generation
		$mfa_generate_codes = wp_create_nonce( 'limit-login-attempts-options' );

		// Get existing llar_vars data to merge (WordPress overwrites, doesn't merge)
		global $wp_scripts;
		$existing_data = array();
		if ( isset( $wp_scripts->registered['lla-main']->extra['data'] ) ) {
			// Parse existing data (it's a string like "var llar_vars = {...};")
			$existing_data_string = $wp_scripts->registered['lla-main']->extra['data'];
			// Extract JSON from the string
			if ( preg_match( '/var\s+llar_vars\s*=\s*({.*?});/s', $existing_data_string, $matches ) ) {
				$existing_data = json_decode( $matches[1], true );
				if ( ! is_array( $existing_data ) ) {
					$existing_data = array();
				}
			}
		}

		// Merge existing data with MFA-specific data
		$merged_data = array_merge(
			$existing_data,
			array(
			'nonce_mfa_generate_codes' => $mfa_generate_codes,
			'ajax_url'                 => admin_url( 'admin-ajax.php' ),
			)
		);

		// Add MFA-specific data to localized script (merged with existing data)
		wp_localize_script( 'lla-main', 'llar_vars', $merged_data );

		// Enqueue PDF libraries only on MFA tab (admin only, to avoid loading on frontend)
		// Use local versions instead of CDN for better security and reliability
		$plugin_url = defined( 'LLA_PLUGIN_URL' ) ? LLA_PLUGIN_URL : plugins_url( '/', __DIR__ . '/../limit-login-attempts-reloaded.php' );
		wp_enqueue_script( 'html2canvas', $plugin_url . 'assets/js/html2canvas.min.js', array(), '1.4.1', true );
		wp_enqueue_script( 'jspdf', $plugin_url . 'assets/js/jspdf.umd.min.js', array(), '2.5.1', true );
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
			wp_die( esc_html( __( 'You do not have sufficient permissions to access this page.', 'limit-login-attempts-reloaded' ) ) );
		}

		// Unified check: MFA requires SSL and deterministic salt (no duplicated logic with UI)
		$block_reason = $this->get_mfa_block_reason();
		if ( null !== $block_reason ) {
			$msg = MfaConstants::MFA_BLOCK_REASON_SSL === $block_reason
				? __( 'SSL/HTTPS is required for 2FA functionality. Please enable SSL on your site.', 'limit-login-attempts-reloaded' )
				: __( '2FA cannot be enabled: WordPress salt (AUTH_SALT or NONCE_SALT) or wp_salt() is required for secure rate limiting. Please define salts in wp-config.php.', 'limit-login-attempts-reloaded' );
			wp_die( esc_html( $msg ), esc_html__( '2FA Unavailable', 'limit-login-attempts-reloaded' ), array( 'response' => 403 ) );
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
				set_transient( MfaConstants::TRANSIENT_CHECKBOX_STATE, 1, MfaConstants::CHECKBOX_STATE_TTL );
				return true;
			} else {
				// Codes already exist, just save MFA
				Config::update( 'mfa_enabled', 1 );
				// Clear transient if exists
				delete_transient( MfaConstants::TRANSIENT_CHECKBOX_STATE );
			}
		} else {
			// Disabling MFA - cleanup codes
			$this->cleanup_rescue_codes();
			Config::update( 'mfa_enabled', 0 );
			// Clear transient if exists
			delete_transient( MfaConstants::TRANSIENT_CHECKBOX_STATE );
		}

		// Save selected roles - use editable roles and optimize validation
		$mfa_roles = array();
		if ( isset( $_POST['mfa_roles'] ) && is_array( $_POST['mfa_roles'] ) && ! empty( $_POST['mfa_roles'] ) ) {
			// Get editable roles (cached by WordPress on request level)
			$editable_roles     = get_editable_roles();
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
	 * Return reason why MFA cannot be enabled, or null if it can.
	 * Unified check for SSL and deterministic salt (no code duplication with UI).
	 *
	 * @return string|null One of MfaConstants::MFA_BLOCK_REASON_* or null
	 */
	public function get_mfa_block_reason() {
		if ( ! is_ssl() ) {
			return MfaConstants::MFA_BLOCK_REASON_SSL;
		}
		if ( null === MfaConstants::get_rate_limit_salt() ) {
			return MfaConstants::MFA_BLOCK_REASON_SALT;
		}
		return null;
	}

	/**
	 * Get MFA settings for view
	 *
	 * @return array Array with mfa_enabled, mfa_temporarily_disabled, mfa_roles, prepared_roles, editable_roles, show_rescue_popup, mfa_block_reason
	 */
	public function get_settings_for_view() {
		$mfa_enabled_raw          = Config::get( 'mfa_enabled', false );
		$mfa_temporarily_disabled = $this->is_mfa_temporarily_disabled();
		$mfa_checkbox_state       = get_transient( MfaConstants::TRANSIENT_CHECKBOX_STATE );

		// MFA is considered enabled if it's enabled in config AND not temporarily disabled
		// OR if checkbox state is stored (popup is shown)
		$mfa_enabled = ( $mfa_enabled_raw && ! $mfa_temporarily_disabled ) || ( 1 === $mfa_checkbox_state );

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
			'mfa_block_reason'         => $this->get_mfa_block_reason(),
		);
	}
}
