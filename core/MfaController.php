<?php

namespace LLAR\Core;

use LLAR\Core\Mfa\MfaAvailability;
use LLAR\Core\Mfa\MfaCapability;
use LLAR\Core\Mfa\MfaCodeGenerator;
use LLAR\Core\Mfa\MfaEncryptionService;
use LLAR\Core\Mfa\MfaRateLimiter;
use LLAR\Core\Mfa\MfaRescueEndpointHandler;
use LLAR\Core\Mfa\MfaRescuePdfService;
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
	 * Rescue PDF HTML service
	 *
	 * @var MfaRescuePdfService
	 */
	private $pdf_service;

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
		$this->pdf_service      = new MfaRescuePdfService( $this->url_generator );
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

		// Lazy-load PDF libs only when rescue popup is shown (admin_footer)
		add_action( 'admin_footer', array( $this, 'enqueue_pdf_libs_if_needed' ), 20 );
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
		$this->check_user_capabilities();

		if ( ! check_ajax_referer( 'limit-login-attempts-options', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh the page and try again.', 'limit-login-attempts-reloaded' ) ) );
			return;
		}

		// Rate limit PDF/rescue HTML generation per user per minute
		$user_id = get_current_user_id();
		$rate_key = 'llar_mfa_pdf_gen_' . $user_id;
		$rate_data = get_transient( $rate_key );
		if ( false !== $rate_data && is_array( $rate_data ) ) {
			$elapsed = time() - (int) $rate_data['t'];
			if ( $elapsed < MfaConstants::PDF_RATE_LIMIT_PERIOD && (int) $rate_data['c'] >= MfaConstants::PDF_RATE_LIMIT_MAX ) {
				wp_send_json_error( array( 'message' => __( 'Too many generations. Please try again in a minute.', 'limit-login-attempts-reloaded' ) ) );
				return;
			}
			if ( $elapsed >= MfaConstants::PDF_RATE_LIMIT_PERIOD ) {
				$rate_data = array( 'c' => 0, 't' => time() );
			}
		} else {
			$rate_data = array( 'c' => 0, 't' => time() );
		}
		$rate_data['c'] = (int) $rate_data['c'] + 1;
		set_transient( $rate_key, $rate_data, MfaConstants::PDF_RATE_LIMIT_PERIOD );

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

		// Generate HTML content for PDF (delegated to PDF service; path validation inside)
		try {
			$html_content = $this->pdf_service->generate_html( $plain_codes );
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
	 * Whether current user can manage MFA settings (delegates to MfaCapability).
	 *
	 * @return bool
	 */
	public function user_can_manage_mfa() {
		return MfaCapability::current_user_can_manage();
	}

	/**
	 * Enforce capability check; exits with JSON error if not allowed (for AJAX).
	 */
	private function check_user_capabilities() {
		if ( ! $this->user_can_manage_mfa() ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'limit-login-attempts-reloaded' ) ) );
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
	 * Delegates to MfaRescueEndpointHandler (single place for rate limit, decrypt, verify).
	 */
	public function handle_rescue_endpoint() {
		$hash_id = get_query_var( 'llar_rescue' );
		if ( empty( $hash_id ) ) {
			return;
		}
		$this->rescue_handler->handle( $hash_id );
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
	 * Cleanup rescue codes when MFA is disabled.
	 * Uses prepared statements for bulk transient deletion.
	 */
	public function cleanup_rescue_codes() {
		Config::delete( 'mfa_rescue_codes' );
		Config::delete( 'mfa_rescue_download_token' );
		Config::update( 'mfa_rescue_pending_links', array() );

		$this->delete_mfa_transients();
	}

	/**
	 * Delete all MFA-related transients via prepared statements (no raw LIKE in SQL).
	 */
	private function delete_mfa_transients() {
		global $wpdb;
		$table = $wpdb->options;
		$like  = $wpdb->esc_like( '_transient_llar_mfa' ) . '%';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE option_name LIKE %s", $like ) );
		$like_timeout = $wpdb->esc_like( '_transient_timeout_llar_mfa' ) . '%';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE option_name LIKE %s", $like_timeout ) );
	}

	/**
	 * Generate HTML file with rescue links (delegates to PDF service).
	 *
	 * @param array $plain_codes Plain rescue codes
	 * @return string HTML content
	 */
	public function generate_rescue_file_html( $plain_codes ) {
		return $this->pdf_service->generate_html( $plain_codes );
	}

	/**
	 * Get rescue URL for a code
	 * Delegates to MfaRescueUrlGenerator (single source of truth for rescue URL logic).
	 *
	 * @param string $plain_code Plain rescue code
	 * @return string Rescue URL with hash
	 */
	public function get_rescue_url( $plain_code ) {
		return $this->url_generator->get_rescue_url( $plain_code );
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

		// Fire action when PDF libs are needed (rescue popup) so admin_footer enqueues them lazily
		if ( $this->should_show_rescue_popup() ) {
			do_action( 'llar_mfa_generate_codes' );
		}
	}

	/**
	 * Lazy-load PDF libraries in admin_footer only when rescue codes UI is shown.
	 * Enqueues html2canvas and jspdf when llar_mfa_generate_codes action was fired.
	 */
	public function enqueue_pdf_libs_if_needed() {
		if ( ! did_action( 'llar_mfa_generate_codes' ) ) {
			return;
		}
		$plugin_url = defined( 'LLA_PLUGIN_URL' ) ? LLA_PLUGIN_URL : plugins_url( '/', __DIR__ . '/../limit-login-attempts-reloaded.php' );
		wp_enqueue_script( 'html2canvas', $plugin_url . 'assets/js/html2canvas.min.js', array(), '1.4.1', true );
		wp_enqueue_script( 'jspdf', $plugin_url . 'assets/js/jspdf.umd.min.js', array(), '2.5.1', true );
	}

	/**
	 * Handle MFA settings form submission.
	 * Uses user_can_manage_mfa() as single source of truth for capability.
	 *
	 * @return bool True if popup should be shown, false otherwise
	 */
	public function handle_settings_submission() {
		// Check if this is MFA settings form
		if ( ! isset( $_POST['llar_update_mfa_settings'] ) ) {
			return false;
		}

		check_admin_referer( 'limit-login-attempts-options' );

		if ( ! $this->user_can_manage_mfa() ) {
			wp_die( esc_html( __( 'You do not have sufficient permissions to access this page.', 'limit-login-attempts-reloaded' ) ) );
		}

		$block_reason = MfaAvailability::get_block_reason();
		if ( null !== $block_reason ) {
			$msg = MfaAvailability::get_block_message( $block_reason );
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

		// Save selected roles: validate against editable keys and ensure each role exists
		$mfa_roles = array();
		if ( isset( $_POST['mfa_roles'] ) && is_array( $_POST['mfa_roles'] ) && ! empty( $_POST['mfa_roles'] ) ) {
			$editable_roles     = get_editable_roles();
			$editable_role_keys = array_keys( $editable_roles );

			$sanitized_roles = array_filter(
				array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['mfa_roles'] ) ),
				'strlen'
			);

			$mfa_roles = array_intersect( $sanitized_roles, $editable_role_keys );
			// Ensure each role still exists in current WordPress configuration
			$mfa_roles = array_filter( $mfa_roles, function ( $role ) {
				return (bool) get_role( $role );
			} );
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
	 * Return reason why MFA cannot be enabled, or null (delegates to MfaAvailability).
	 *
	 * @return string|null One of MfaConstants::MFA_BLOCK_REASON_* or null
	 */
	public function get_mfa_block_reason() {
		return MfaAvailability::get_block_reason();
	}

	/**
	 * Human-readable message for a block reason (delegates to MfaAvailability).
	 *
	 * @param string $block_reason One of MfaConstants::MFA_BLOCK_REASON_*
	 * @return string
	 */
	public function get_mfa_block_message( $block_reason ) {
		return MfaAvailability::get_block_message( $block_reason );
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

		// Single source: mfa_block_reason drives all "cannot enable" logic (SSL, salt, OpenSSL)
		$mfa_block_reason = $this->get_mfa_block_reason();
		return array(
			'mfa_enabled'              => $mfa_enabled,
			'mfa_temporarily_disabled' => $mfa_temporarily_disabled,
			'mfa_roles'                => $mfa_roles,
			'prepared_roles'           => $this->prepared_roles,
			'editable_roles'           => $this->editable_roles,
			'show_rescue_popup'        => $this->show_rescue_popup,
			'mfa_block_reason'         => $mfa_block_reason,
			'mfa_block_message'        => $mfa_block_reason ? $this->get_mfa_block_message( $mfa_block_reason ) : '',
		);
	}
}
