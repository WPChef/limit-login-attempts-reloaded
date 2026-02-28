<?php

namespace LLAR\Core\Mfa;

use LLAR\Core\Config;
use LLAR\Core\MfaConstants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MFA main orchestrator: hooks, AJAX, settings submission, view data.
 * Uses MfaBackupCodes, MfaEndpoint, MfaSettings, MfaValidator (4 dependencies).
 */
class MfaManager {

	public $show_rescue_popup = false;
	public $prepared_roles    = array();
	public $editable_roles    = array();

	/** @var MfaBackupCodesInterface */
	private $backup_codes;
	/** @var MfaEndpointInterface */
	private $endpoint;
	/** @var MfaSettingsInterface */
	private $settings;

	/**
	 * Constructor. Dependencies are injected for testability and single responsibility.
	 *
	 * @param MfaBackupCodesInterface $backup_codes Backup/rescue codes service.
	 * @param MfaEndpointInterface    $endpoint    Rescue endpoint handler.
	 * @param MfaSettingsInterface   $settings    MFA settings service.
	 */
	public function __construct( MfaBackupCodesInterface $backup_codes, MfaEndpointInterface $endpoint, MfaSettingsInterface $settings ) {
		$this->backup_codes = $backup_codes;
		$this->endpoint     = $endpoint;
		$this->settings     = $settings;
	}

	/**
	 * Register hooks: AJAX, query vars, template_redirect, enqueue scripts.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp_ajax_llar_mfa_generate_rescue_codes', array( $this, 'ajax_generate_rescue_codes' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle_rescue_endpoint' ) );
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_mfa_disabled_message_script' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_mfa_disabled_message_script' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 1000 );
		add_action( 'admin_footer', array( $this, 'enqueue_pdf_libs_if_needed' ), 20 );
	}

	/**
	 * Add llar_rescue to public query vars for rescue links.
	 *
	 * @param array $vars Existing query vars.
	 * @return array Query vars with llar_rescue.
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'llar_rescue';
		return $vars;
	}

	/**
	 * Handle rescue endpoint: pass hash_id from query var to endpoint handler.
	 *
	 * @return void
	 */
	public function handle_rescue_endpoint() {
		$hash_id = get_query_var( 'llar_rescue' );
		if ( empty( $hash_id ) ) {
			return;
		}
		$this->endpoint->handle( $hash_id );
	}

	/**
	 * Whether current user can manage MFA (capability check).
	 *
	 * @return bool True if user can manage MFA.
	 */
	public function user_can_manage_mfa() {
		return MfaValidator::current_user_can_manage();
	}

	/**
	 * Whether MFA is temporarily disabled (e.g. after rescue flow).
	 *
	 * @return bool True if temporarily disabled.
	 */
	public function is_mfa_temporarily_disabled() {
		return $this->settings->is_mfa_temporarily_disabled();
	}

	/**
	 * Whether to show rescue codes popup (no codes or all used).
	 * Only when MFA is enabled or user just enabled it (checkbox state transient).
	 *
	 * @return bool True if popup should be shown.
	 */
	public function should_show_rescue_popup() {
		$mfa_enabled    = Config::get( 'mfa_enabled', false );
		$checkbox_state = get_transient( MfaConstants::TRANSIENT_CHECKBOX_STATE );
		if ( ! $mfa_enabled && 1 !== (int) $checkbox_state ) {
			return false;
		}
		return MfaBackupCodes::should_show_rescue_popup( Config::get( 'mfa_rescue_codes', array() ) );
	}

	/**
	 * Cleanup rescue codes and related transients (when MFA disabled).
	 *
	 * @return void
	 */
	public function cleanup_rescue_codes() {
		$this->settings->cleanup_rescue_codes();
	}

	/**
	 * Build rescue URL for a plain code. OpenSSL required.
	 *
	 * @param string $plain_code Plain rescue code.
	 * @return string Rescue URL.
	 * @throws \Exception When OpenSSL unavailable or encryption fails.
	 */
	public function get_rescue_url( $plain_code ) {
		return $this->backup_codes->get_rescue_url( $plain_code );
	}

	/**
	 * Generate HTML for rescue PDF from plain codes (builds URLs then PDF HTML).
	 *
	 * @param array $plain_codes List of plain rescue codes.
	 * @return string HTML for PDF.
	 * @throws \Exception When encryption or template fails.
	 */
	public function generate_rescue_file_html( $plain_codes ) {
		$rescue_urls = array();
		foreach ( (array) $plain_codes as $code ) {
			$rescue_urls[] = $this->backup_codes->get_rescue_url( $code );
		}
		return $this->backup_codes->generate_pdf_html( $rescue_urls );
	}

	/**
	 * MFA settings data for view (tab template).
	 *
	 * @return array mfa_enabled, mfa_temporarily_disabled, mfa_roles, prepared_roles, editable_roles, show_rescue_popup, mfa_block_reason, mfa_block_message.
	 */
	public function get_settings_for_view() {
		return $this->settings->get_settings_for_view( $this->show_rescue_popup );
	}

	/**
	 * Load and prepare roles data; assign to $this->prepared_roles and $this->editable_roles.
	 *
	 * @return void
	 */
	public function prepare_roles_data() {
		$data                 = $this->settings->prepare_roles_data();
		$this->prepared_roles = $data['prepared_roles'];
		$this->editable_roles = $data['editable_roles'];
	}

	/**
	 * Process MFA settings form submission. Capability and block-reason checks, then update Config.
	 *
	 * @return bool True if rescued-popup flow triggered (codes needed); false if saved or not submitted.
	 */
	public function handle_settings_submission() {
		if ( ! isset( $_POST['llar_update_mfa_settings'] ) ) {
			return false;
		}
		check_admin_referer( 'limit-login-attempts-options' );

		if ( ! $this->user_can_manage_mfa() ) {
			wp_die( esc_html( __( 'You do not have sufficient permissions to access this page.', 'limit-login-attempts-reloaded' ) ) );
		}

		$block_reason = MfaValidator::get_block_reason();
		if ( null !== $block_reason ) {
			$msg = MfaValidator::get_block_message( $block_reason );
			wp_die( esc_html( $msg ), esc_html__( '2FA Unavailable', 'limit-login-attempts-reloaded' ), array( 'response' => 403 ) );
		}

		if ( isset( $_POST['mfa_enabled'] ) && $_POST['mfa_enabled'] ) {
			if ( $this->should_show_rescue_popup() ) {
				$this->show_rescue_popup = true;
				set_transient( MfaConstants::TRANSIENT_CHECKBOX_STATE, 1, MfaConstants::CHECKBOX_STATE_TTL );
				return true;
			}
			Config::update( 'mfa_enabled', 1 );
			delete_transient( MfaConstants::TRANSIENT_CHECKBOX_STATE );
		} else {
			$this->cleanup_rescue_codes();
			Config::update( 'mfa_enabled', 0 );
			delete_transient( MfaConstants::TRANSIENT_CHECKBOX_STATE );
		}

		$mfa_roles = $this->get_sanitized_mfa_roles_from_post();
		Config::update( 'mfa_roles', $mfa_roles );
		return false;
	}

	/**
	 * Get sanitized MFA roles array from POST data.
	 *
	 * @return array List of role keys.
	 */
	private function get_sanitized_mfa_roles_from_post() {
		$mfa_roles = array();
		if ( isset( $_POST['mfa_roles'] ) && is_array( $_POST['mfa_roles'] ) && ! empty( $_POST['mfa_roles'] ) ) {
			$editable_roles     = get_editable_roles();
			$editable_role_keys = array_keys( $editable_roles );
			$sanitized_roles    = array_filter(
				array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['mfa_roles'] ) ),
				'strlen'
			);
			$mfa_roles          = array_intersect( $sanitized_roles, $editable_role_keys );
			$mfa_roles          = array_filter(
				$mfa_roles,
				function ( $role ) {
					return (bool) get_role( $role );
				}
			);
		}
		return $mfa_roles;
	}

	/**
	 * AJAX handler: generate rescue codes, build URLs and PDF HTML, update config. Sends JSON.
	 *
	 * @return void Exits with wp_send_json_*.
	 */
	public function ajax_generate_rescue_codes() {
		if ( ! $this->user_can_manage_mfa() ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'limit-login-attempts-reloaded' ) ) );
		}
		if ( ! check_ajax_referer( 'limit-login-attempts-options', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh the page and try again.', 'limit-login-attempts-reloaded' ) ) );
		}

		$user_id   = get_current_user_id();
		$rate_key  = 'llar_mfa_pdf_gen_' . $user_id;
		$rate_data = get_transient( $rate_key );
		if ( false !== $rate_data && is_array( $rate_data ) ) {
			$elapsed = time() - (int) $rate_data['t'];
			if ( $elapsed < MfaConstants::PDF_RATE_LIMIT_PERIOD && (int) $rate_data['c'] >= MfaConstants::PDF_RATE_LIMIT_MAX ) {
				wp_send_json_error( array( 'message' => __( 'Too many generations. Please try again in a minute.', 'limit-login-attempts-reloaded' ) ) );
			}
			if ( $elapsed >= MfaConstants::PDF_RATE_LIMIT_PERIOD ) {
				$rate_data = array(
					'c' => 0,
					't' => time(),
				);
			}
		} else {
			$rate_data = array(
				'c' => 0,
				't' => time(),
			);
		}
		$rate_data['c'] = (int) $rate_data['c'] + 1;
		set_transient( $rate_key, $rate_data, MfaConstants::PDF_RATE_LIMIT_PERIOD );

		try {
			$plain_codes = $this->backup_codes->generate();
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => __( 'Failed to generate codes: ', 'limit-login-attempts-reloaded' ) . $e->getMessage() ) );
		}

		if ( empty( $plain_codes ) || ! is_array( $plain_codes ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to generate rescue codes. Please try again.', 'limit-login-attempts-reloaded' ) ) );
		}

		$rescue_urls = array();
		foreach ( $plain_codes as $code ) {
			try {
				$rescue_urls[] = $this->backup_codes->get_rescue_url( $code );
			} catch ( \Exception $e ) {
				wp_send_json_error( array( 'message' => __( 'Encryption unavailable. OpenSSL is required for rescue links.', 'limit-login-attempts-reloaded' ) ) );
			}
		}

		try {
			$html_content = $this->backup_codes->generate_pdf_html( $rescue_urls );
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => __( 'Failed to generate PDF content: ', 'limit-login-attempts-reloaded' ) . $e->getMessage() ) );
		}

		Config::update( 'mfa_rescue_download_token', wp_generate_password( 32, false ) );
		// MFA is enabled only when user clicks Save Settings (after confirming rescue codes in popup).

		wp_send_json_success(
			array(
				'rescue_urls'  => $rescue_urls,
				'html_content' => $html_content,
				'domain'       => wp_parse_url( home_url(), PHP_URL_HOST ),
			)
		);
	}

	/**
	 * Enqueue script for "MFA temporarily disabled" message on wp-login and Woo account page.
	 *
	 * @return void
	 */
	public function enqueue_mfa_disabled_message_script() {
		if ( ! isset( $_GET['llar_mfa_disabled'] ) || '1' !== $_GET['llar_mfa_disabled'] ) {
			return;
		}
		$is_wp_login  = ( isset( $GLOBALS['pagenow'] ) && 'wp-login.php' === $GLOBALS['pagenow'] );
		$is_woo_login = ( function_exists( 'is_account_page' ) && is_account_page() );
		if ( ! $is_wp_login && ! $is_woo_login ) {
			return;
		}
		$plugin_url = defined( 'LLA_PLUGIN_URL' ) ? LLA_PLUGIN_URL : plugins_url( '/', __DIR__ . '/../limit-login-attempts-reloaded.php' );
		wp_enqueue_script( 'llar-mfa-disabled-message', $plugin_url . 'assets/js/mfa-disabled-message.js', array( 'jquery' ), '1.0.0', true );
		wp_localize_script(
			'llar-mfa-disabled-message',
			'llarMfaDisabled',
			array(
				'showMessage' => true,
				'message'     => esc_html__( 'Multi-factor authentication has been temporarily disabled.', 'limit-login-attempts-reloaded' ),
			)
		);
	}

	/**
	 * Enqueue MFA tab scripts and localize nonce/URL when on MFA tab.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( ! isset( $_GET['page'] ) || 'limit-login-attempts' !== $_GET['page'] ) {
			return;
		}
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'settings';
		if ( 'mfa' !== $current_tab ) {
			return;
		}
		if ( ! wp_script_is( 'lla-main', 'registered' ) ) {
			return;
		}
		$mfa_generate_codes = wp_create_nonce( 'limit-login-attempts-options' );
		global $wp_scripts;
		$existing_data = array();
		if ( isset( $wp_scripts->registered['lla-main']->extra['data'] ) ) {
			$str = $wp_scripts->registered['lla-main']->extra['data'];
			if ( preg_match( '/var\s+llar_vars\s*=\s*({.*?});/s', $str, $m ) ) {
				$existing_data = json_decode( $m[1], true );
				if ( ! is_array( $existing_data ) ) {
					$existing_data = array();
				}
			}
		}
		$merged = array_merge(
			$existing_data,
			array(
				'nonce_mfa_generate_codes' => $mfa_generate_codes,
				'ajax_url'                 => admin_url( 'admin-ajax.php' ),
			)
		);
		wp_localize_script( 'lla-main', 'llar_vars', $merged );
		// Fire action so PDF lib (jsPDF) is enqueued on MFA tab for "Download as PDF".
		do_action( 'llar_mfa_generate_codes' );
	}

	/**
	 * Enqueue jsPDF when rescue codes popup is shown (after llar_mfa_generate_codes).
	 *
	 * @return void
	 */
	public function enqueue_pdf_libs_if_needed() {
		if ( ! did_action( 'llar_mfa_generate_codes' ) ) {
			return;
		}
		$plugin_url = defined( 'LLA_PLUGIN_URL' ) ? LLA_PLUGIN_URL : plugins_url( '/', __DIR__ . '/../limit-login-attempts-reloaded.php' );
		wp_enqueue_script( 'jspdf', $plugin_url . 'assets/js/jspdf.umd.min.js', array(), '2.5.1', true );
	}
}
