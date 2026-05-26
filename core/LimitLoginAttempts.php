<?php

namespace LLAR\Core;

use Exception;
use IXR_Error;
use LLAR\Core\Http\Http;
use LLAR\Core\Dashboard\DashboardRiskRenderer;
use LLAR\Core\Integrations\BaseIntegration;
use LLAR\Core\Integrations\IntegrationManager;
use LLAR\Core\MfaFlow\MfaFlowLoginHandler;
use LLAR\Core\MfaFlow\MfaRestApi;
use WP_Error;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) exit;

class LimitLoginAttempts
{
	/**
	 * Admin options page slug
	 * @var string
	 */
	private $_options_page_slug = 'limit-login-attempts';

	/**
	 * Errors messages
	 *
	 * @var array
	 */
	public $_errors = array();

	public $all_errors_array = array();

	/**
	 * custom error
	 * @var string
	 */
	public $custom_error = '';

	/**
	 * User blocking
	 * @var boolean
	 */
	public $user_blocking = false;
	public $user_empty = false;

	/**
	 * Registration error messages
	 * @var string
	 */
	public $error_messages = '';

	/**
	 * Additional login errors messages that we need to show
	 *
	 * @var array
	 */
	public $other_login_errors = array();

	/**
	 * Current app object
	 *
	 * @var CloudApp
	 */
	public static $cloud_app = null;

	/**
	 * Integration manager for third-party plugins
	 *
	 * @var IntegrationManager
	 */
	private $integration_manager = null;

	private $info_data = array();

	/**
	 * MFA manager instance (MfaManager: MfaBackupCodes, MfaEndpoint, MfaSettings, MfaValidator).
	 *
	 * @var \LLAR\Core\Mfa\MfaManager
	 */
	private $mfa_controller = null;

	/**
	 * Admin notices controller (renders notice views for options page).
	 *
	 * @var \LLAR\Core\AdminNoticesController
	 */
	private $admin_notices_controller = null;

	/** @var IpAddressResolver */
	private $ip_resolver = null;

	/** @var CloudAclService */
	private $cloud_acl = null;

	/** @var LocalLockoutManager */
	private $local_lockout = null;

	/** @var DashboardRiskRenderer */
	private $dashboard_renderer = null;

	/** @var LoginAuthenticationHandler */
	private $auth_handler = null;

	/** @var MfaFlowLoginHandler */
	private $mfa_flow_login = null;

	/** @var LoginErrorPresenter */
	private $error_presenter = null;

	/** @var RegistrationLimiter */
	private $registration_limiter = null;

	/** @var AdminUiController */
	private $admin_ui = null;

	/**
	 * Pending flash message to display on options page (e.g. "Settings saved").
	 * Rendered via AdminNoticesController when options-page is loaded.
	 *
	 * @var array|null Keys: 'msg', 'is_error'. Null when none.
	 */
	public $pending_admin_message = null;

	/**
	 * Class instance accessible in other classes
	 *
	 * @var LimitLoginAttempts
	 */
	public static $instance;

	/**
	 * Capabilities to work with a plugin
	 *
	 * @var string
	 */
	public static $capabilities = 'llar_admin';
	public $has_capability = false;


	/**
	 * Priority for the late authenticate safety net.
	 *
	 * @temporary WP 7.0 compat — remove after WP 7.1 release or when auth flow is stable.
	 */
	const LATE_AUTH_PRIORITY = 99990;

	/**
	 * @temporary WP 7.0 compat — single source of truth for auth failure WP_Error codes.
	 * TODO: Remove after WP 7.1 release or when auth flow is stable.
	 *
	 * @var array
	 */
	private static $auth_failure_codes = array( 'invalid_username', 'invalid_email', 'incorrect_password', 'authentication_failed' );

	/**
	 * Cached results of WP version checks.
	 *
	 * @var array
	 */
	private static $wp_version_cache = array();

	/**
	 * Check whether the current WordPress version is at least $version. Result is cached.
	 *
	 * @param string $version Minimum version to compare against (e.g. '6.9', '7.0').
	 * @return bool
	 */
	private static function is_wp_at_least( $version ) {
		if ( ! isset( self::$wp_version_cache[ $version ] ) ) {
			$current = preg_replace( '/[^0-9.].*/', '', Helpers::get_wordpress_version() );
			self::$wp_version_cache[ $version ] = version_compare( $current, $version, '>=' );
		}
		return self::$wp_version_cache[ $version ];
	}

	/**
	 * Reset per-request static guards for persistent PHP runtimes (Swoole, FrankenPHP).
	 * $wp_version_cache is intentionally NOT reset — WP version does not change between requests.
	 */
	public static function reset_request_guards() {
		LocalLockoutManager::reset_failed_login_recorded_in_request();
		MfaFlowLoginHandler::reset_handshake_guard();
	}

	/**
	 * Allowed tabs for options page
	 */
	public static $allowed_tabs = array( 'logs-local', 'logs-custom', 'settings', 'mfa', 'debug', 'premium', 'help' );

	/**
	 * Check if a role is an admin role
	 *
	 * @param string $role_key Role key (e.g., 'administrator')
	 * @param string $role_name Role display name (e.g., 'Administrator') - optional, deprecated, not used
	 * @return bool True if role is admin-related
	 */
	public static function is_admin_role( $role_key, $role_name = '' ) {
		// Validate input
		if ( ! is_string( $role_key ) || empty( $role_key ) ) {
			return false;
		}

		// Primary check: exact match for administrator role
		if ( 'administrator' === $role_key ) {
			return true;
		}

		// Secondary check: verify role has admin capabilities (most reliable method)
		$role = get_role( $role_key );
		if ( $role && $role->has_cap( 'manage_options' ) ) {
			return true;
		}

		// Fallback: check if role key is exactly 'admin' (common custom admin role name)
		// Note: We don't check $role_name to avoid false positives (e.g., 'admin_peter' user name)
		if ( 'admin' === strtolower( $role_key ) ) {
			return true;
		}

		return false;
	}

	private $plans = array(
		'default'       => array(
			'name'          => 'Free',
			'rate'          => 10,
		),
		'free'          => array(
			'name'          => 'Micro Cloud',
			'rate'          => 20,
		),
		'premium'       => array(
			'name'          => 'Premium',
			'rate'          => 30,
		),
		'plus'          => array(
			'name'          => 'Premium +',
			'rate'          => 40,
		),
		'pro'           => array(
			'name'          => 'Professional',
			'rate'          => 50,
		),
		'agency_pro'    => array(
			'name'          => 'Agency',
			'rate'          => 60,
		),
	);

	public function __construct()
	{
		self::$instance = $this;

		Config::init();
		Http::init();

		// Initialize integrations manager
		$this->integration_manager = new IntegrationManager( $this );

		$this->admin_notices_controller = new AdminNoticesController();
		$this->ip_resolver              = new IpAddressResolver();
		$this->cloud_acl                = new CloudAclService();
		$this->local_lockout            = new LocalLockoutManager( $this->ip_resolver, $this->cloud_acl, $this );
		$this->error_presenter          = new LoginErrorPresenter( $this, $this->cloud_acl, $this->local_lockout, $this->ip_resolver );
		$this->mfa_flow_login           = new MfaFlowLoginHandler( $this->ip_resolver );
		$this->auth_handler             = new LoginAuthenticationHandler(
			$this,
			$this->cloud_acl,
			$this->local_lockout,
			$this->ip_resolver,
			$this->mfa_flow_login,
			$this->error_presenter
		);
		$this->dashboard_renderer       = new DashboardRiskRenderer( $this, $this->local_lockout );
		$this->registration_limiter     = new RegistrationLimiter( $this );
		$this->admin_ui                 = new AdminUiController( $this );

		$this->hooks_init();
		$this->setup();
		$this->cloud_app_init();

		// Initialize MFA (dependency injection: MfaBackupCodes, MfaEndpoint, MfaSettings)
		$payload_storage = \LLAR\Core\Mfa\RescuePayloadStorage\RescuePayloadStorageSelector::get_storage();
		$mfa_backup_codes = new \LLAR\Core\Mfa\MfaBackupCodes( $payload_storage );
		$mfa_endpoint     = new \LLAR\Core\Mfa\MfaEndpoint( $mfa_backup_codes, $payload_storage );
		$mfa_settings    = new \LLAR\Core\Mfa\MfaSettings();
		$this->mfa_controller = new \LLAR\Core\Mfa\MfaManager( $mfa_backup_codes, $mfa_endpoint, $mfa_settings, $payload_storage );
		$this->mfa_controller->register();

		( new Shortcodes() )->register();
		( new Ajax() )->register();
	}

	/**
	 * Login identifier from active integration (MemberPress, Woo, etc.).
	 *
	 * @return string
	 */
	public function get_integration_login_identifier() {
		if ( $this->integration_manager ) {
			return $this->integration_manager->get_login_identifier();
		}
		return '';
	}

	/**
	 * @return AdminNoticesController
	 */
	public function get_admin_notices_controller() {
		return $this->admin_notices_controller;
	}

	/**
	 * @return LocalLockoutManager
	 */
	public function get_local_lockout() {
		return $this->local_lockout;
	}

	/**
	 * Register wp hooks and filters
	 */
	public function hooks_init()
	{
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) ,999);
		add_action( 'login_enqueue_scripts', array( $this, 'login_page_enqueue' ) );
		add_filter( 'limit_login_whitelist_ip', array( $this, 'check_whitelist_ips' ), 10, 2 );
		add_filter( 'limit_login_whitelist_usernames', array( $this, 'check_whitelist_usernames' ), 10, 2 );
		add_filter( 'limit_login_blacklist_ip', array( $this, 'check_blacklist_ips' ), 10, 2 );
		add_filter( 'limit_login_blacklist_usernames', array( $this, 'check_blacklist_usernames' ), 10, 2 );

		add_filter( 'illegal_user_logins', array( $this, 'register_user_blacklist' ), 999 );
		add_filter( 'um_custom_authenticate_error_codes', array( $this, 'ultimate_member_register_error_codes' ) );

		// TODO: Temporary turn off the holiday warning.
		//add_action( 'admin_notices', array( $this, 'show_enable_notify_notice' ) );

		add_action( 'admin_notices', array( $this, 'render_leave_review_admin_notice' ) );

		add_action( 'admin_print_scripts-toplevel_page_limit-login-attempts', array( $this, 'load_admin_scripts' ) );
		add_action( 'admin_print_scripts-settings_page_limit-login-attempts', array( $this, 'load_admin_scripts' ) );
		add_action( 'admin_print_scripts-index.php', array( $this, 'load_admin_scripts' ) );

		add_action( 'admin_init', array( $this, 'dashboard_page_redirect' ), 9999 );
		add_action( 'admin_init', array( $this, 'onboarding_redirect_to_dashboard' ), 5 );
		add_action( 'admin_init', array( $this, 'setup_cookie' ), 10 );

		add_action( 'login_footer', array( $this, 'login_page_gdpr_message' ) );

		add_action( 'login_footer', array( $this, 'login_page_render_js' ), 9999 );
		add_action( 'wp_footer', array( $this, 'login_page_render_js' ), 9999 );

		if( !Config::get( 'hide_dashboard_widget' ) )
			add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widgets' ) );

		add_action( 'login_form_register', array( $this, 'llar_submit_login_form_register' ), 10 );
		add_filter( 'registration_errors', array( $this, 'llar_submit_registration_errors' ), 10, 3 );

		register_activation_hook( LLA_PLUGIN_FILE, array( $this, 'activation' ) );

		add_action( 'upgrader_process_complete', array( $this, 'after_plugin_update' ), 10, 2 );
	}

	/**
	 * Runs when the plugin is activated
	 */
	public function activation()
	{
		Helpers::persist_stored_plugin_version();

		if ( ! Config::get( 'activation_timestamp' ) ) {

			set_transient( 'llar_dashboard_redirect', true, 30 );
		}
	}

	/**
	 * After this plugin is updated from wp-admin, persist the new file version.
	 *
	 * @param \WP_Upgrader $upgrader Upgrader instance (unused).
	 * @param array        $options  Context: action, type, plugins, etc.
	 * @return void
	 */
	public function after_plugin_update( $upgrader, $options ) {
		if ( ! isset( $options['type'], $options['action'] ) || 'update' !== $options['action'] || 'plugin' !== $options['type'] ) {
			return;
		}
		if ( empty( $options['plugins'] ) || ! is_array( $options['plugins'] ) ) {
			return;
		}
		if ( ! in_array( LLA_PLUGIN_BASENAME, $options['plugins'], true ) ) {
			return;
		}

		$old_version = (string) Config::get( 'plugin_version' );
		Helpers::persist_stored_plugin_version();
		$new_version = (string) Config::get( 'plugin_version' );

		if ( $old_version !== $new_version ) {
			/**
			 * Fires after LLAR plugin version is persisted post-update.
			 *
			 * @param string $old_version Previously stored version (may be empty).
			 * @param string $new_version Newly stored version.
			 */
			do_action( 'llar_plugin_version_updated', $old_version, $new_version );
		}
	}

	public function setup_cookie()
	{
		if ( empty( $_GET['page'] ) || $_GET['page'] !== $this->_options_page_slug ) {

			return;
		}

		$cookie_name = 'llar_menu_alert_icon_shown';

		if ( empty( $_COOKIE[$cookie_name] ) ) {
			setcookie( $cookie_name, '1', strtotime( 'tomorrow' ) );
		}
	}

	public function register_dashboard_widgets() {

		if ( ! $this->has_capability ) return;

		wp_add_dashboard_widget(
			'llar_stats_widget',
			__( 'Limit Login Attempts Reloaded', 'limit-login-attempts-reloaded' ),
			array( $this, 'dashboard_widgets_content' ),
			null,
			null,
			'normal',
			'high'
		);
	}

	public function dashboard_widgets_content()
	{
		$vars = $this->dashboard_renderer->build_dashboard_widget_vars();
		extract( $vars, EXTR_SKIP );
		include LLA_PLUGIN_DIR . 'views/admin-dashboard-widgets.php';
	}

	
	
	
	
	/**
	 * Get failed login attempts count for the last 24 hours in local mode.
	 *
	 * @return int
	 */
	public function get_local_retries_count_for_last_day() {
		return $this->local_lockout->get_local_retries_count_for_last_day();
	}

	
	
	
	
	
	/**
	 * Build data for failed attempts circle widget.
	 *
	 * Local mode: risk color bands by retries (0 / 1–99 / 100–299 / 300+). Custom Cloud: always green
	 * indicator; retries count only (no risk band styling).
	 *
	 * @param bool        $is_active_app_custom Cloud mode flag.
	 * @param bool|string $is_exhausted         Cloud exhausted flag (unused for donut styling; kept for callers).
	 * @param string      $block_sub_group      Cloud plan name (unused for donut styling; kept for callers).
	 * @param string      $setup_code           App setup code.
	 * @param string      $upgrade_premium_url  Premium upgrade URL.
	 * @param bool|array  $api_stats            Cloud API stats.
	 *
	 * @return array
	 */
	public function get_failed_attempts_circle_data( $is_active_app_custom, $is_exhausted, $block_sub_group, $setup_code, $upgrade_premium_url, $api_stats ) {
		return $this->dashboard_renderer->get_failed_attempts_circle_data( $is_active_app_custom, $is_exhausted, $block_sub_group, $setup_code, $upgrade_premium_url, $api_stats );
	}

	/**
	 * Redirect to dashboard page after installed
	 */
	public function dashboard_page_redirect()
	{
		if (
			! get_transient( 'llar_dashboard_redirect' )
			|| isset( $_GET['activate-multi'] ) || is_network_admin()
		) {
			return;
		}

		delete_transient( 'llar_dashboard_redirect' );

		wp_redirect( admin_url( 'index.php?page=' . $this->_options_page_slug ) );
		exit();
	}

	/**
	 * Redirect to dashboard when onboarding is not completed yet (so onboarding can start on any plugin page).
	 * Runs on admin_init before any output to avoid "headers already sent" when using wp_safe_redirect().
	 */
	public function onboarding_redirect_to_dashboard()
	{
		if ( empty( $_GET['page'] ) || $this->_options_page_slug !== $_GET['page'] ) {
			return;
		}
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'dashboard';
		if ( 'dashboard' === $tab ) {
			return;
		}
		if ( Config::get( 'onboarding_popup_shown' ) ) {
			return;
		}
		if ( 'custom' === Config::get( Config::OPTION_ACTIVE_APP ) && self::$cloud_app ) {
			return;
		}
		if ( ! empty( Config::get( 'app_setup_code' ) ) ) {
			return;
		}
		wp_safe_redirect( $this->get_options_page_uri( 'dashboard' ) );
		exit;
	}

	/**
	 * Hook 'plugins_loaded'
	 */
	public function setup()
	{
		if ( ! ( $activation_timestamp = Config::get( 'activation_timestamp' ) ) ) {

			// Write time when the plugin is activated
			Config::update( 'activation_timestamp', time() );
		}

		if ( ! ( $activation_timestamp = Config::get( 'notice_enable_notify_timestamp' ) ) ) {

			// Write time when the plugin is activated
			Config::update( 'notice_enable_notify_timestamp', strtotime( '-32 day' ) );
		}

		if ( ! self::is_wp_at_least( '5.5' ) ) {
			Config::update( 'auto_update_choice', 0 );
		}

		// Load translations and defaults in a WP-version-safe way.
		add_action( 'init', array( $this, 'load_plugin_textdomain_in_time' ) );

		// Reset per-request static guards for persistent runtimes (Swoole/FrankenPHP).
		add_action( 'init', array( __CLASS__, 'reset_request_guards' ), 0 );

		$this->register_mfa_providers();

		// Check if installed old plugin
		$this->check_original_installed();

		// Setup default plugin options
		//$this->sanitize_options();

		add_action( 'wp_login_failed', array( $this, 'limit_login_failed' ) );
		add_filter( 'wp_authenticate_user', array( $this, 'wp_authenticate_user' ), 99999, 2 );
		add_action( 'wp_login', array( $this, 'limit_login_success' ), 10, 2 );

		add_filter( 'shake_error_codes', array( $this, 'failure_shake' ) );
		add_filter( 'wp_login_errors', array( $this, 'inject_mfa_return_login_error' ), 10, 2 );
		add_action( 'login_errors', array( $this, 'fixup_error_messages' ) );
		// hook for the plugin UM
		add_action( 'um_submit_form_errors_hook_login', array( $this, 'um_limit_login_failed' ) );

		if ( Helpers::is_network_mode() ) {
			add_action( 'network_admin_menu', array( $this, 'network_admin_menu' ) );

			if ( Config::get( 'show_warning_badge' ) )
				add_action( 'network_admin_menu', array( $this, 'network_setting_menu_alert_icon' ) );
		}

		if ( Helpers::allow_local_options() ) {
			add_action( 'admin_menu', array( $this, 'admin_menu' ) );

			if ( Config::get( 'show_top_bar_menu_item' ) )
				add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ), 999 );

			if ( Config::get( 'show_warning_badge' ) )
				add_action( 'admin_menu', array( $this, 'setting_menu_alert_icon' ) );
		}

		// Add notices for XMLRPC request
		add_filter( 'xmlrpc_login_error', array( $this, 'xmlrpc_error_messages' ) );

		/*
		 * Primary auth chain: guard at lowest priority, then early ACL/blacklist,
		 * credentials tracking, late error fallback, and final lockout safety net.
		 */
		add_filter( 'authenticate', array( $this, 'authenticate_guard_filter' ), -9999, 3 );
		add_action( 'authenticate', array( $this, 'track_credentials' ), 1, 3 ); // to replace the deprecated wp_authenticate hook
		add_action( 'authenticate', array( $this, 'authenticate_filter' ), 0, 3 );

		/**
		 * BuddyPress unactivated user account message fix
		 * Wordfence error message fix
		 */
		add_action( 'authenticate', array( $this, 'authenticate_filter_errors_fix' ), 35, 3 );

		// @temporary WP 7.0 compat — late safety net.
		// TODO: Remove after WP 7.1 release or when auth flow is stable.
		if ( self::is_wp_at_least( '7.0' ) ) {
			add_filter( 'authenticate', array( $this, 'authenticate_late_lockout_check' ), self::LATE_AUTH_PRIORITY, 3 );
		}

		add_filter( 'plugin_action_links_' . LLA_PLUGIN_BASENAME, array( $this, 'add_action_links' ) );

		// MFA flow callback: llar_mfa=1&token=...&code=...
		add_action( 'init', array( $this, 'mfa_flow_callback' ), 1 );
		add_filter( 'query_vars', array( $this, 'add_mfa_flow_query_var' ) );
		MfaRestApi::register();

		$role = get_role( 'administrator' );

		if ( $role && ! $role->has_cap( self::$capabilities ) ) {

			$role->add_cap( self::$capabilities );
		}

		$this->has_capability = ( current_user_can('manage_options' ) || current_user_can( self::$capabilities ) );

	}


	/**
	 * Initialize i18n and plugin defaults.
	 *
	 * WordPress 6.9+ (including 7.x) uses JIT translation loading and no longer needs
	 * explicit `load_plugin_textdomain()` calls. Older WordPress versions still rely on it.
	 *
	 * @return void
	 */
	public function load_plugin_textdomain_in_time()
	{
		if ( ! self::is_wp_at_least( '6.9' ) ) {
			load_plugin_textdomain( 'limit-login-attempts-reloaded', false, basename( LLA_PLUGIN_DIR ) . '/languages' );
		}

		Config::init_defaults();
	}

	public function login_page_gdpr_message()
	{

		if ( ! Config::get( 'gdpr' ) || isset( $_REQUEST['interim-login'] ) ) return;

		?>
        <div id="llar-login-page-gdpr">
            <div class="llar-login-page-gdpr__message"><?php echo do_shortcode( stripslashes( Config::get( 'gdpr_message' ) ) ); ?></div>
            <div class="llar-login-page-gdpr__close" onclick="document.getElementById('llar-login-page-gdpr').style.display = 'none';">
                &times;
            </div>
        </div>
		<?php
	}

	public function login_page_render_js()
	{
		if ( true === LoginFlowTransientStore::get( 'llar_user_is_whitelisted', false ) ) {
			LoginFlowTransientStore::merge( array( 'llar_user_is_whitelisted' => null ) );
			return;
		}
		global $limit_login_just_lockedout, $limit_login_nonempty_credentials, $um_limit_login_failed;

		$llar_mfa_error = isset( $_GET['llar_mfa_error'] ) ? sanitize_text_field( wp_unslash( $_GET['llar_mfa_error'] ) ) : '';
		// Same error output as failed login for any MFA redirect (session_expired, code_invalid, etc.).
		$show_mfa_return_error = ( $llar_mfa_error !== '' );

		if ( Config::get( Config::OPTION_ACTIVE_APP ) === 'local' && ! $limit_login_nonempty_credentials && ! $show_mfa_return_error ) {
			return;
		}

		$custom_error = Config::get( 'custom_error_message' );
		$late_hook_errors = ! empty( $this->all_errors_array['late_hook_errors'] ) ? $this->all_errors_array['late_hook_errors'] : false;
		$is_wp_login_page = isset( $_POST['log'] );
		$is_custom_login_page = $this->integration_manager->is_custom_login_page();

		$mfa_return_message = __( '<strong>ERROR</strong>: Incorrect username or password.', 'limit-login-attempts-reloaded' );
		if ( ( $limit_login_nonempty_credentials && ( $is_wp_login_page || $is_custom_login_page || $um_limit_login_failed ) ) || $show_mfa_return_error ) :
            ?>

            <script>
                ;( function( $ ) {
                    let ajaxUrlObj = new URL( `<?php echo admin_url( 'admin-ajax.php' ); ?>` );
                    let um_limit_login_failed = `<?php echo esc_js( isset( $um_limit_login_failed ) ? $um_limit_login_failed : '' ); ?>`;
                    let late_hook_errors = <?php echo wp_json_encode( wp_kses_post( ( $late_hook_errors ) ) ) ?>;
                    let custom_error = <?php echo wp_json_encode( nl2br( esc_html( $custom_error ) ) ) ?>;
                    let llar_mfa_return_error = <?php echo $show_mfa_return_error ? 'true' : 'false'; ?>;
                    let llar_mfa_return_message = <?php echo wp_json_encode( wp_kses_post( $mfa_return_message ) ); ?>;

                    ajaxUrlObj.protocol = location.protocol;

                    $.post( ajaxUrlObj.toString(), {
                        action: 'get_remaining_attempts_message',
                        sec: '<?php echo wp_create_nonce( "llar-get-remaining-attempts-message" ); ?>'
                    }, function( response ) {
                        if ( llar_mfa_return_error ) {
                            if ( response.success && response.data ) {
                                notification_login_page( response.data + ( custom_error.length ? '<br /><br />' + custom_error : '' ) );
                            } else {
                                notification_login_page( llar_mfa_return_message + ( custom_error.length ? '<br /><br />' + custom_error : '' ) );
                            }
                            return;
                        }
                        if ( response.success && response.data ) {

                            if ( custom_error.length ) {

                                custom_error = '<br /><br />' + custom_error;
                            }
                             notification_login_page( response.data + custom_error );

                        } else if ( um_limit_login_failed ) {

                            if ( late_hook_errors === false || late_hook_errors === '' ) {

                                notification_login_page( custom_error );
                            } else {

                                if ( custom_error.length ) {
                                    custom_error = '<br /><br />' + custom_error;
                                }

                                notification_login_page( late_hook_errors + custom_error );
                            }

                        } else {

                            if ( custom_error.length ) {
                                notification_login_page(custom_error);
                            }
                        }
                    } ).fail( function() {
                        if ( llar_mfa_return_error ) {
                            notification_login_page( llar_mfa_return_message + ( custom_error.length ? '<br /><br />' + custom_error : '' ) );
                        }
                    } );

                    function notification_login_page( message ) {

                        if ( ! message.length ) {
                            return false;
                        }
                        let css = '.llar_notification_login_page { position: fixed; top: 50%; left: 50%; font-size: 120%; line-height: 1.5; width: 365px; z-index: 999999; background: #fffbe0; padding: 20px; color: rgb(121, 121, 121); text-align: center; border-radius: 10px; transform: translate(-50%, -50%); box-shadow: 10px 10px 14px 0 #72757B99;} .llar_notification_login_page h4 { color: rgb(255, 255, 255); margin-bottom: 1.5rem; } .llar_notification_login_page .close-button {position: absolute; top: 0; right: 5px; cursor: pointer; line-height: 1;}';
                        let style = document.createElement('style');
                        style.appendChild(document.createTextNode(css));
                        document.head.appendChild(style);

                        $( 'body' ).prepend( '<div class="llar_notification_login_page"><div class="close-button">&times;</div>' + message + '</div>' );

                        setTimeout(function () {
                            $('.llar_notification_login_page').hide();
                        }, 10000);

                        $('.llar_notification_login_page').on( 'click', '.close-button', function () {
                            $('.llar_notification_login_page').hide();
                        });

                        $( 'body' ).on('click', function(event) {
                            if (!$(event.target).closest('.llar_notification_login_page').length) {
                                $('.llar_notification_login_page').hide();
                            }
                        });
                    }

                } )(jQuery)
            </script>
		<?php endif;
	}

	public function add_action_links( $actions )
	{
		$actions = array_merge( array(
			'<a href="' . $this->get_options_page_uri() . '">' . __( 'Dashboard', 'limit-login-attempts-reloaded' ) . '</a>',
			'<a href="' . $this->get_options_page_uri( 'settings' ) . '">' . __( 'Settings', 'limit-login-attempts-reloaded' ) . '</a>',
		), $actions );

		if ( Config::get( Config::OPTION_ACTIVE_APP ) === 'local' ) {

			if ( empty( Config::get( 'app_setup_code' ) ) ) {

				$slug = $this->get_options_page_uri('dashboard#modal_micro_cloud');

				$actions = array_merge( array(
					'<a href="' . esc_html( $slug ) . '" style="font-weight: bold;">' . __( 'Free Upgrade', 'limit-login-attempts-reloaded' ) . '</a>',
				), $actions );
			} else {

				$url_site = 'https://www.limitloginattempts.com/info.php?from=plugin-plugins';

				$actions = array_merge( array(
					'<a href="' . esc_html( $url_site ) . '" target="_blank" style="font-weight: bold;">' . __( 'Upgrade to Premium', 'limit-login-attempts-reloaded' ) . '</a>',
				), $actions );
			}
		}

		return $actions;
	}

	/**
	 * Add llar_mfa to public query vars for MFA flow callback.
	 *
	 * @param array $vars Existing query vars.
	 * @return array
	 */
	public function add_mfa_flow_query_var( $vars ) {
		$vars[] = 'llar_mfa';
		return $vars;
	}

	/**
	 * MFA flow callback: handle llar_mfa=1&token=...&code=... and exit if handled.
	 */
	public function mfa_flow_callback() {
		\LLAR\Core\MfaFlow\CallbackHandler::maybe_handle();
	}

	public function cloud_app_init()
	{
		if ( Config::get( Config::OPTION_ACTIVE_APP ) === 'custom' && $config = Config::get( 'app_config' ) ) {

			self::$cloud_app = new CloudApp( $config );
		}
	}

	public function load_admin_scripts()
	{
		if ( ! empty( $_REQUEST['page'] ) && $_REQUEST['page'] !== $this->_options_page_slug ) {
			return;
		}

		wp_enqueue_script('jquery-ui-accordion');
		wp_enqueue_style('llar-jquery-ui', LLA_PLUGIN_URL.'assets/css/jquery-ui.css');

		wp_enqueue_script( 'llar-charts', LLA_PLUGIN_URL . 'assets/js/chart.umd.js' );
	}

	public function check_whitelist_ips( $allow, $ip )
	{
		return $this->local_lockout->check_whitelist_ips( $allow, $ip );
	}

	public function check_whitelist_usernames( $allow, $username )
	{
		return $this->local_lockout->check_whitelist_usernames( $allow, $username );
	}

	public function check_blacklist_ips( $allow, $ip )
	{
		return $this->local_lockout->check_blacklist_ips( $allow, $ip );
	}

	public function check_blacklist_usernames( $allow, $username )
	{
		return $this->local_lockout->check_blacklist_usernames( $allow, $username );
	}

	/**
	 * @param $blacklist
	 * @return array|null
	 */
	public function register_user_blacklist($blacklist)
	{

		$black_list_usernames = Config::get( 'blacklist_usernames' );

		if ( ! empty( $black_list_usernames ) && is_array( $black_list_usernames ) ) {
			$blacklist += $black_list_usernames;
		}

		return $blacklist;
	}

	/**
	 * @param $error IXR_Error
	 *
	 * @return IXR_Error
	 */
	public function xmlrpc_error_messages( $error )
	{
		if ( ! class_exists( 'IXR_Error' ) ) {
			return $error;
		}

		if ( $login_error = $this->get_message() ) {

			return new IXR_Error( 403, strip_tags( $login_error ) );
		}

		return $error;
	}


	/**
	 * @param $user
	 * @param $username
	 * @param $password
	 *
	 * @return WP_Error | WP_User
	 * @throws Exception
	 */
	public function authenticate_filter( $user, $username, $password )
	{
		return $this->auth_handler->authenticate_filter( $user, $username, $password );
	}

	/**
	 * Run ACL / blacklist checks before third-party late authenticate hooks.
	 *
	 * @param mixed  $user
	 * @param string $username
	 * @param string $password
	 * @return mixed
	 */
	public function authenticate_guard_filter( $user, $username, $password ) {
		return $this->auth_handler->authenticate_guard_filter( $user, $username, $password );
	}

	
	
	
	
	
	
	
	

	/**
	 * Delete the CloudApp object
	 */
	public function cloud_app_null()
	{
		LimitLoginAttempts::$cloud_app = null;
	}

	/**
	 * Fix displaying the errors of other plugins
	 *
	 * @param $user
	 * @param $username
	 * @param $password
	 * @return mixed
	 */
	public function authenticate_filter_errors_fix( $user, $username, $password )
	{
		return $this->auth_handler->authenticate_filter_errors_fix( $user, $username, $password );
	}

	/**
	 * Late authenticate safety net for WP 7.0+ compatibility.
	 *
	 * @temporary WP 7.0 compat — remove after WP 7.1 release or when auth flow is stable.
	 *
	 * Runs at a very high priority on the authenticate filter to catch
	 * failed logins that were not recorded by earlier hooks (e.g. when
	 * wp_login_failed does not fire or core auth runs at changed priorities)
	 * and to enforce lockout even when the wp_authenticate_user filter
	 * inside wp_authenticate_username_password is not reached.
	 *
	 * @param mixed  $user
	 * @param string $username
	 * @param string $password
	 * @return mixed
	 */
	public function authenticate_late_lockout_check( $user, $username, $password ) {
		return $this->auth_handler->authenticate_late_lockout_check( $user, $username, $password );
	}

	public function ultimate_member_register_error_codes( $codes )
	{
		if ( ! is_array( $codes ) ) {
			return $codes;
		}

		$codes[] = 'too_many_retries';
		$codes[] = 'username_blacklisted';

		return $codes;
	}

	/**
	 * Register MFA flow providers (e.g. LlarMfaProvider).
	 */
	private function register_mfa_providers() {
		\LLAR\Core\MfaFlow\MfaProviderRegistry::register( new \LLAR\Core\MfaFlow\Providers\Email\LlarMfaProvider() );
	}

	/**
	 * Check if the original plugin is installed
	 */
	private function check_original_installed()
	{
		require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
		if ( is_plugin_active('limit-login-attempts/limit-login-attempts.php') ) {

			deactivate_plugins( 'limit-login-attempts/limit-login-attempts.php', true );
			remove_action( 'plugins_loaded', 'limit_login_setup', 99999 );
		}
	}

	/**
	 * Enqueue js and css
	 */
	public function enqueue()
	{
		return $this->admin_ui->enqueue();
	}

	/**
	 * Enqueue scripts on login page
	 */
	public function login_page_enqueue()
	{
		return $this->admin_ui->login_page_enqueue();
	}

	/**
	 * Add admin options page
	 */
	public function admin_menu()
	{
		return $this->admin_ui->admin_menu();
	}

	/**
	 * Add admin bar menu item
	 *
	 * @param WP_Admin_Bar $bar WordPress admin bar object.
	 */
	public function admin_bar_menu( $bar )
	{
		return $this->admin_ui->admin_bar_menu( $bar );
	}

	/**
	 * Add network admin options page
	 */
	public function network_admin_menu()
	{
		return $this->admin_ui->network_admin_menu();
	}
	public function setting_menu_alert_icon()
	{
		$this->admin_ui->setting_menu_alert_icon();
	}
	public function network_setting_menu_alert_icon()
	{
		$this->admin_ui->network_setting_menu_alert_icon();
	}
	/**
	 * Get the correct options page URI
	 *
	 * @param bool $tab
	 * @return mixed
	 */
	public function get_options_page_uri( $tab = false )
	{
		return $this->admin_ui->get_options_page_uri( $tab );
	}

	/**
	 * Fires after successful login
	 *
	 * @param $username
	 * @param $user
	 *
	 */
	public function limit_login_success( $username, $user ) {

		if ( ! self::$cloud_app ) {
			return;
		}

		if ( ! empty( $username ) ) {

			$clean_url = '';
			if ( isset( $_SERVER['HTTP_REFERER'] ) ) {

				$referer_url = $_SERVER['HTTP_REFERER'];
				$referer_parsed = parse_url( $referer_url );

				$clean_url = isset( $referer_parsed['path']) ? $referer_parsed['path'] : '';
				$clean_url = trim( $clean_url, '/' );
			}

			$user = get_user_by('login', $username);

			$data = array(
				'ip'        => Helpers::get_all_ips(),
				'login'     => $username,
				'user_id'   => $user->ID,
				'gateway'   => Helpers::detect_gateway(),
				'roles'     => $user->roles,
				'agent'     => $_SERVER['HTTP_USER_AGENT'],
				'url'       => $clean_url,
			);

			self::$cloud_app->request( 'login', 'post', $data );
		}
	}


	
	
	/**
	 * Check if it is ok to login
	 *
	 * @param string $username Optional username from the auth hook.
	 * @return bool
	 * @throws Exception
	 */
	public function is_limit_login_ok( $username = '' )
	{
		return $this->local_lockout->is_limit_login_ok( $username );
	}


	/**
	 * Redirect browser to MFA app URL. Clears output buffers, then sends Location header or HTML fallback.
	 *
	 * @param string $url Redirect URL (already escaped).
	 */
	public static function mfa_redirect_to_url( $url ) {
		MfaFlowLoginHandler::redirect_to_url( $url );
	}

	/**
	 * For plugin UM
	 */
	public function um_limit_login_failed ()
	{
		global $um_limit_login_failed;

		do_action( 'login_errors', '' );
		$um_limit_login_failed = true;
	}

	/**
	 * For plugin MemberPress
	 * Triggers authenticate filter to allow Limit Login Attempts Reloaded
	 * to track credentials and check lockouts before MemberPress validates the password
	 * This enables the plugin to display remaining attempts messages
	 *
	 * @param array $errors Array of existing errors (MemberPress passes validate_login output first).
	 * @param array $params Login parameters (log, pwd)
	 * @return array Errors for MemberPress; when LLAR blocks login, returns that message as first error.
	 */
	public function mepr_validate_login_handler( $errors, $params = array() )
	{
		if ( ! isset( $_POST['log'] ) || ! isset( $_POST['pwd'] ) ) {
			return $errors;
		}

		$log = sanitize_text_field( wp_unslash( $_POST['log'] ) );
		$pwd = isset( $_POST['pwd'] ) ? $_POST['pwd'] : ''; // Password should not be sanitized

		// Trigger authenticate filter to track credentials and check lockouts.
		$auth_result = apply_filters( 'authenticate', null, $log, $pwd );

		if ( is_wp_error( $auth_result ) ) {
			$codes = $auth_result->get_error_codes();
			if ( in_array( 'too_many_retries', $codes, true ) ) {
				return array( $auth_result->get_error_message( 'too_many_retries' ) );
			}
			if ( in_array( 'username_blacklisted', $codes, true ) ) {
				return array( $auth_result->get_error_message( 'username_blacklisted' ) );
			}
		}

		if ( ! $this->is_limit_login_ok( $log ) ) {
			return array( $this->error_msg( $log ) );
		}

		return $errors;
	}

	
	
	/**
	 * Action when login attempt failed
	 *
	 * @param string $username Login username.
	 */
	public function limit_login_failed( $username ) {
		$this->local_lockout->limit_login_failed( $username );
	}

	/**
	 * Handle notification in event of lockout
	 *
	 * @param $user
	 * @return bool|void
	 */
	public function notify( $user ) {
		$this->local_lockout->notify( $user );
	}

	/**
	 * Email notification of lockout to admin (if configured)
	 *
	 * @param $user
	 */
	public function notify_email( $user )
	{
		$this->local_lockout->notify_email( $user );
	}

	/**
	 * Logging of lockout (if configured)
	 *
	 * @param $user_login
	 *
	 * @internal param $user
	 */
	public function notify_log( $user_login )
	{
		$this->local_lockout->notify_log( $user_login );
	}

	/**
	 * Check if IP is whitelisted.
	 *
	 * This function allow external ip whitelisting using a filter. Note that it can
	 * be called multiple times during the login process.
	 *
	 * Note that retries and statistics are still counted and notifications
	 * done as usual for whitelisted ips , but no lockout is done.
	 *
	 * Example:
	 * function my_ip_whitelist($allow, $ip) {
	 *    return ($ip == 'my-ip') ? true : $allow;
	 * }
	 * add_filter('limit_login_whitelist_ip', 'my_ip_whitelist', 10, 2);
	 *
	 * @param null $ip
	 *
	 * @return bool
	 */
	public function is_ip_whitelisted( $ip = null )
	{
		return $this->ip_resolver->is_ip_whitelisted( $ip );
	}

	public function is_username_whitelisted( $username )
	{
		return $this->local_lockout->is_username_whitelisted( $username );
	}

	public function is_ip_blacklisted( $ip = null )
	{
		return $this->ip_resolver->is_ip_blacklisted( $ip );
	}

	public function is_username_blacklisted( $username )
	{
		return $this->local_lockout->is_username_blacklisted( $username );
	}

	/**
	 * Filter: allow login attempt? (called from wp_authenticate())
	 *
	 * @param $user WP_User
	 * @param $password
	 *
	 * @return WP_Error|WP_User
	 */
	public function wp_authenticate_user( $user, $password )
	{
		return $this->auth_handler->wp_authenticate_user( $user, $password );
	}

	/**
	 * Filter: add this failure to login page "Shake it!"
	 *
	 * @param $error_codes
	 *
	 * @return array
	 */
	public function failure_shake( $error_codes )
	{
		$error_codes[] = 'too_many_retries';
		$error_codes[] = 'username_blacklisted';

		return $error_codes;
	}

	/**
	 * Keep track of if user or password are empty, to filter errors correctly
	 *
	 * @param $user
	 * @param $username
	 * @param $password
	 */
	public function track_credentials( $user, $username, $password )
	{
		return $this->auth_handler->track_credentials( $user, $username, $password );
	}

	/**
	 * Construct informative error message
	 *
	 * @param string $username Optional username from the auth hook.
	 * @return string
	 * @throws Exception
	 */
	public function error_msg( $username = '' )
	{
		return $this->error_presenter->error_msg( $username );
	}

	/**
	 * When returning from MFA with llar_mfa_error, inject an error so WordPress outputs the red #login_error block.
	 *
	 * @param \WP_Error $errors      WP_Error object passed to login_header().
	 * @param string   $redirect_to  Redirect URL.
	 * @return \WP_Error
	 */
	public function inject_mfa_return_login_error( $errors, $redirect_to ) {
		return $this->error_presenter->inject_mfa_return_login_error( $errors, $redirect_to );
	}

	/**
	 * Fix up the error message before showing it
	 *
	 * @param $content
	 *
	 * @return string
	 */
	public function fixup_error_messages( $content )
	{
		return $this->error_presenter->fixup_error_messages( $content );
	}

	public function fixup_error_messages_wc( \WP_Error $error )
	{
		return $this->error_presenter->fixup_error_messages_wc( $error );
	}

	
	/**
	 * Get correct remote address
	 *
	 * @return string
	 *
	 */
	public function get_address()
	{
		return $this->ip_resolver->get_address();
	}


	/**
	 * Clean up old lockouts and retries, and save supplied arrays
	 *
	 * @param null $retries
	 * @param null $lockouts
	 * @param null $valid
	 */
	public function cleanup( $retries = null, $lockouts = null, $valid = null )
	{
		$this->local_lockout->cleanup( $retries, $lockouts, $valid );
	}

	/**
	 * Render admin options page
	 */
	public function options_page()
	{
		$this->admin_ui->options_page();
	}
	/**
	 * Render an admin notice view by key (e.g. 'auto-update', 'mfa-no-ssl').
	 *
	 * @param string $notice_key Notice identifier.
	 * @param array  $args       Variables to pass to the notice view.
	 * @return void
	 */
	public function render_admin_notice( $notice_key, array $args = array() ) {
		if ( null === $this->admin_notices_controller ) {
			$this->admin_notices_controller = new AdminNoticesController();
		}
		$this->admin_notices_controller->render( $notice_key, $args );
	}

	/**
	 * Show warning when MFA is enabled and rescue links need attention: no rescue payload transients,
	 * or latest payload expiry is within RESCUE_NOTICE_THRESHOLD. Uses a short-lived cache for the
	 * max-expiry query to avoid scanning wp_options on every admin page load.
	 *
	 * @return bool
	 */
	public function should_show_mfa_recovery_links_expired_notice() {
		if ( ! (bool) Config::get( 'mfa_enabled' ) ) {
			return false;
		}

		$seconds_left = $this->mfa_controller->get_rescue_links_seconds_left();
		if ( null === $seconds_left ) {
			return true;
		}

		return $seconds_left <= MfaConstants::RESCUE_NOTICE_THRESHOLD;
	}

	
	
	
	
	
	
	
	/**
	 * Show error message
	 *
	 * @param $msg
	 * @param bool $is_error
	 */
	public function show_message( $msg, $is_error = false ) {
		$this->pending_admin_message = array(
			'msg'      => $msg,
			'is_error' => $is_error,
		);
	}

	
	

	private function plan_name_match( $plan = 'default' )
	{
		if ( ! array_key_exists( $plan, $this->plans ) ) {
			$plan = 'default';
		}

		return $this->plans[ $plan ]['name'];
	}


	public function array_name_plans()
	{
		$plans = [];

		foreach ( $this->plans as $plan ) {

			$plans[ $plan['name'] ] = $plan['rate'];
		}

		return $plans;
	}

	private function info()
	{
		if ( self::$cloud_app ) {
			$this->info_data = self::$cloud_app->info();
		}

		return $this->info_data;
	}

	public function info_is_exhausted()
	{
		if ( empty( $this->info_data ) ) {

			$this->info_data = $this->info();
		}

		return isset( $this->info_data['requests']['exhausted'] ) ? filter_var( $this->info_data['requests']['exhausted'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) : false;
	}


	public function info_requests()
	{
		if ( empty( $this->info_data ) ) {

			$this->info_data = $this->info();
		}

		return ( ! empty( $this->info_data ) && ! empty( $this->info_data['requests'] ) ) ? $this->info_data['requests'] : '';
	}


	public function info_sub_group()
	{
		if ( empty( $this->info_data ) ) {

			$this->info_data = $this->info();
		}

		$data = ( ! empty( $this->info_data ) && ! empty( $this->info_data['sub_group'] ) ) ? $this->info_data['sub_group'] : '';

		return $this->plan_name_match( $data );
	}


	public function info_upgrade_url()
	{
		if ( empty( $this->info_data ) ) {

			$this->info_data = $this->info();
		}

		return ( ! empty( $this->info_data ) && ! empty( $this->info_data['upgrade_url'] ) ) ? $this->info_data['upgrade_url'] : '';
	}


	public function info_block_by_country()
	{
		if ( empty( $this->info_data ) ) {

			$this->info_data = $this->info();
		}

		return ( ! empty( $this->info_data ) && ! empty( $this->info_data['block_by_country'] ) ) ? $this->info_data['block_by_country'] : '';
	}





	

	
	/**
	 * Public wrapper for llar_api_response to allow integrations to use it
	 * Only allows calls from integration classes within this plugin
	 *
	 * @param string $user_data User data to check
	 * @param BaseIntegration|null $integration Integration instance (optional, for security validation)
	 * @return array API response
	 */
	public function check_registration_api( $user_data, $integration = null ) {
		return $this->registration_limiter->check_registration_api( $user_data, $integration );
	}


	/**
	 * Register new user standard WP
	 */
	public function llar_submit_login_form_register()
	{
		$this->registration_limiter->llar_submit_login_form_register();
	}


	/**
	 * Correcting errors in the presence of a registration prohibition marker
	 * @param $errors
	 * @param $sanitized_user_login
	 * @param $user_email
	 *
	 * @return mixed
	 */
	public function llar_submit_registration_errors( $errors, $sanitized_user_login, $user_email )
	{
		return $this->registration_limiter->llar_submit_registration_errors( $errors, $sanitized_user_login, $user_email );
	}

	/**
	 * Debug tab: foreign authenticate filter callbacks.
	 *
	 * @return array
	 */
	public static function get_foreign_authenticate_hooks() {
		return AuthenticateHooksInspector::get_foreign_authenticate_hooks();
	}

	/**
	 * Admin notice: leave a review (dashboard/plugins/LLAR screens).
	 *
	 * @return void
	 */
	public function render_leave_review_admin_notice() {
		$screen = get_current_screen();
		if ( isset( $_COOKIE['llar_review_notice_shown'] ) ) {
			Config::update( 'review_notice_shown', true );
			@setcookie( 'llar_review_notice_shown', '', time() - 3600, '/' );
		}
		if (
			! $this->has_capability
			|| Config::get( 'review_notice_shown' )
			|| ! $screen
			|| ! in_array( $screen->base, array( 'dashboard', 'plugins', 'toplevel_page_limit-login-attempts' ), true )
		) {
			return;
		}
		$activation_timestamp = Config::get( 'activation_timestamp' );
		if ( ! $activation_timestamp || $activation_timestamp >= strtotime( '-1 month' ) ) {
			return;
		}
		$this->admin_notices_controller->render( 'leave-review' );
	}
}

