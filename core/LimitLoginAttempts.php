<?php

namespace LLAR\Core;

use Exception;
use IXR_Error;
use LLAR\Core\Http\Http;
use LLAR\Core\Integrations\BaseIntegration;
use LLAR\Core\Integrations\IntegrationManager;
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
	private $auth_acl_response_cache = array();
	private $auth_acl_response_cache_max_size = 50;

	/**
	 * Request-scoped cache: hook callback -> reflection file path (avoids repeated Reflection API).
	 *
	 * @var array
	 */
	private static $hook_callback_source_file_cache = array();

	/**
	 * Request-scoped cache: normalized source file path -> plugin metadata (avoids repeated get_plugins scans).
	 *
	 * @var array
	 */
	private static $hook_source_file_plugin_cache = array();

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
	 * Guard: handshake already attempted this request (avoids double handshake from wp_authenticate_user + limit_login_failed).
	 *
	 * @var bool
	 */
	private static $mfa_flow_handshake_attempted = false;

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

		$this->hooks_init();
		$this->setup();
		$this->cloud_app_init();

		// Initialize MFA (dependency injection: MfaBackupCodes, MfaEndpoint, MfaSettings)
		$mfa_backup_codes = new \LLAR\Core\Mfa\MfaBackupCodes();
		$mfa_endpoint     = new \LLAR\Core\Mfa\MfaEndpoint( $mfa_backup_codes );
		$mfa_settings    = new \LLAR\Core\Mfa\MfaSettings();
		$this->mfa_controller = new \LLAR\Core\Mfa\MfaManager( $mfa_backup_codes, $mfa_endpoint, $mfa_settings );
		$this->mfa_controller->register();

		( new Shortcodes() )->register();
		( new Ajax() )->register();
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

		add_action( 'admin_notices', array( $this, 'show_leave_review_notice' ) );

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
		include_once( LLA_PLUGIN_DIR . 'views/admin-dashboard-widgets.php' );
	}

	/**
	 * Whether a retries_stats bucket key is older than cutoff (safe strtotime for string keys).
	 *
	 * @param mixed $key    Bucket key (unix ts or date string).
	 * @param int   $cutoff Cutoff unix timestamp.
	 *
	 * @return bool
	 */
	private function is_retries_stats_bucket_expired( $key, $cutoff ) {
		if ( is_numeric( $key ) ) {
			return (int) $key < $cutoff;
		}
		$ts = strtotime( (string) $key );
		if ( false === $ts ) {
			return false;
		}
		return $ts < $cutoff;
	}

	/**
	 * Plain-text strings for failed-attempts circle (no HTML; whitelist keys only).
	 *
	 * @param string $key Level text key, e.g. zero_title, desc_low.
	 *
	 * @return string
	 */
	private function get_risk_circle_string( $key ) {
		switch ( $key ) {
			case 'zero_title':
				return __( 'Hooray! Zero failed login attempts (past 24 hrs)', 'limit-login-attempts-reloaded' );
			case 'desc_low':
				return __( 'Your site is currently at a low risk for brute force activity.', 'limit-login-attempts-reloaded' );
			case 'desc_medium':
				return __( 'Your site is currently at a medium risk for brute force activity.', 'limit-login-attempts-reloaded' );
			case 'failed_today_title':
				return __( 'Failed Login Attempts Today', 'limit-login-attempts-reloaded' );
			default:
				return '';
		}
	}

	/**
	 * Recommendation HTML: Micro Cloud path (link text translated; markup built in code).
	 *
	 * @return string
	 */
	private function get_micro_cloud_recommendation_html() {
		return sprintf(
			__(
				'Based on your level of brute force activity, we recommend <a class="llar_orange %s">free Micro Cloud upgrade</a> to access features to reduce failed logins and improve site performance.',
				'limit-login-attempts-reloaded'
			),
			'button_micro_cloud'
		);
	}

	/**
	 * Recommendation HTML: premium upgrade URL (href escaped; rel on external target).
	 *
	 * @param string $upgrade_premium_url Premium URL.
	 * @param bool   $open_new_window     Open link in a new window.
	 *
	 * @return string
	 */
	private function get_premium_recommendation_desc( $upgrade_premium_url, $open_new_window = true ) {
		$url       = esc_url( $upgrade_premium_url );
		if ( $open_new_window ) {
			return sprintf(
				__(
					'Based on your level of brute force activity, we recommend <a href="%s" class="llar_orange" target="_blank" rel="noopener noreferrer">upgrading to premium</a> to access features to reduce failed logins and improve site performance.',
					'limit-login-attempts-reloaded'
				),
				$url
			);
		}

		return sprintf(
			__(
				'Based on your level of brute force activity, we recommend <a href="%s" class="llar_orange">upgrading to premium</a> to access features to reduce failed logins and improve site performance.',
				'limit-login-attempts-reloaded'
			),
			$url
		);
	}

	/**
	 * Get failed login attempts count for the last 24 hours in local mode.
	 *
	 * @return int
	 */
	public function get_local_retries_count_for_last_day() {
		$retries_count = 0;
		$retries_stats = Config::get( 'retries_stats' );

		if ( $retries_stats ) {
			$cutoff_ts = time() - DAY_IN_SECONDS;
			foreach ( $retries_stats as $key => $count ) {
				if ( is_numeric( $key ) && (int) $key > $cutoff_ts ) {
					$retries_count += $count;
				} elseif ( ! is_numeric( $key ) && date_i18n( 'Y-m-d' ) === $key ) {
					$retries_count += $count;
				}
			}
		}

		return (int) $retries_count;
	}

	/**
	 * Remove retries_stats buckets older than 8 days (keeps autoload option bounded).
	 *
	 * @param array $retries_stats Stats keyed by time bucket.
	 *
	 * @return array
	 */
	private function prune_retries_stats_old_buckets( $retries_stats ) {
		if ( ! is_array( $retries_stats ) || empty( $retries_stats ) ) {
			return $retries_stats;
		}

		$cutoff = strtotime( '-8 day' );
		foreach ( $retries_stats as $key => $count ) {
			if ( $this->is_retries_stats_bucket_expired( $key, $cutoff ) ) {
				unset( $retries_stats[ $key ] );
			}
		}

		return $retries_stats;
	}

	/**
	 * Build localized retries chart title with attempts count.
	 *
	 * @param int $retries_count Number of retries.
	 *
	 * @return string
	 */
	private function get_retries_chart_title_with_count( $retries_count ) {
		return sprintf(
			_n(
				'%d failed login attempt ',
				'%d failed login attempts ',
				$retries_count,
				'limit-login-attempts-reloaded'
			),
			$retries_count
		) . __( '(past 24 hrs)', 'limit-login-attempts-reloaded' );
	}

	/**
	 * Build recommendation description for elevated brute force activity.
	 *
	 * @param string $setup_code App setup code.
	 *
	 * @return string
	 */
	private function get_recommendation_desc( $setup_code ) {
		if ( ! empty( $setup_code ) ) {
			$premium_tab_url = $this->get_options_page_uri( 'premium' );
			return $this->get_premium_recommendation_desc( $premium_tab_url, false );
		}

		return $this->get_micro_cloud_recommendation_html();
	}

	/**
	 * Resolve risk level by retries count using configured ranges.
	 *
	 * @param int   $retries_count Retries count.
	 * @param array $levels        Risk levels config.
	 *
	 * @return array
	 */
	private function resolve_risk_level( $retries_count, $levels ) {
		$default_level = null;

		foreach ( $levels as $level ) {
			if ( isset( $level['exact'] ) && (int) $level['exact'] === $retries_count ) {
				return $level;
			}

			if ( isset( $level['max_exclusive'] ) && (int) $level['max_exclusive'] > $retries_count ) {
				return $level;
			}

			if ( ! empty( $level['default'] ) ) {
				$default_level = $level;
			}
		}

		return null !== $default_level ? $default_level : array();
	}

	/**
	 * Build chart title/description/color from matched risk level.
	 *
	 * @param array  $matched_level        Matched level config.
	 * @param int    $retries_count        Retries count.
	 * @param array  $risk_config          Risk config.
	 * @param string $setup_code           App setup code.
	 * @param string $upgrade_premium_url  Premium upgrade URL.
	 *
	 * @return array
	 */
	private function build_chart_display_data( $matched_level, $retries_count, $risk_config, $setup_code, $upgrade_premium_url ) {
		$risk_colors = ( isset( $risk_config['colors'] ) && is_array( $risk_config['colors'] ) ) ? $risk_config['colors'] : array();
		$default_color = isset( $risk_colors['green'] ) ? $risk_colors['green'] : '#97F6C8';

		$retries_chart_title = '';
		$retries_chart_desc = '';
		$retries_chart_color = $default_color;

		$rule_flag_keys = array( 'count_title', 'warning_title', 'recommendation', 'premium_recommendation' );
		foreach ( array( 'title', 'count_title', 'warning_title', 'desc', 'recommendation', 'premium_recommendation', 'color' ) as $rule_key ) {
			if ( ! isset( $matched_level[ $rule_key ] ) ) {
				continue;
			}
			if ( in_array( $rule_key, $rule_flag_keys, true ) ) {
				if ( true !== $matched_level[ $rule_key ] && ! $matched_level[ $rule_key ] ) {
					continue;
				}
			} elseif ( empty( $matched_level[ $rule_key ] ) ) {
				continue;
			}

			switch ( $rule_key ) {
				case 'title':
					if ( ! empty( $matched_level['title'] ) ) {
						$retries_chart_title = $this->get_risk_circle_string( $matched_level['title'] );
					}
					break;
				case 'count_title':
					$retries_chart_title = $this->get_retries_chart_title_with_count( $retries_count );
					break;
				case 'warning_title':
					$medium_upper = isset( $risk_config['bounds']['medium_upper'] ) ? (int) $risk_config['bounds']['medium_upper'] : 0;
					if ( $medium_upper <= 0 && isset( $matched_level['min_inclusive'] ) ) {
						$medium_upper = (int) $matched_level['min_inclusive'];
					}
					if ( $medium_upper <= 0 ) {
						$medium_upper = 300;
					}
					/* translators: %d: threshold count (e.g. 300) for "N+ failed login attempts" (high risk, local mode). */
					$retries_chart_title = sprintf(
						__( 'Your site has experienced %d+ failed login attempts in the past 24 hours.', 'limit-login-attempts-reloaded' ),
						$medium_upper
					);
					break;
				case 'desc':
					if ( ! empty( $matched_level['desc'] ) ) {
						$retries_chart_desc = $this->get_risk_circle_string( $matched_level['desc'] );
					}
					break;
				case 'recommendation':
					$recommendation_html = $this->get_recommendation_desc( $setup_code );
					if ( ! empty( $retries_chart_desc ) ) {
						$retries_chart_desc .= '<br><br>' . $recommendation_html;
					} else {
						$retries_chart_desc = $recommendation_html;
					}
					break;
				case 'premium_recommendation':
					$retries_chart_desc = $this->get_premium_recommendation_desc( $upgrade_premium_url );
					break;
				case 'color':
					if ( isset( $risk_colors[ $matched_level['color'] ] ) ) {
						$retries_chart_color = $risk_colors[ $matched_level['color'] ];
					}
					break;
			}
		}

		return array(
			'retries_chart_title' => $retries_chart_title,
			'retries_chart_desc'  => $retries_chart_desc,
			'retries_chart_color' => $retries_chart_color,
		);
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
		$risk_config         = llar_get_risk_config();
		$risk_levels         = ( isset( $risk_config['levels'] ) && is_array( $risk_config['levels'] ) ) ? $risk_config['levels'] : array();
		$risk_colors         = ( isset( $risk_config['colors'] ) && is_array( $risk_config['colors'] ) ) ? $risk_config['colors'] : array();
		$retries_chart_title = '';
		$retries_chart_desc  = '';
		$retries_chart_color = '';
		$retries_count       = 0;

		if ( ! $is_active_app_custom ) {
			$retries_count = $this->get_local_retries_count_for_last_day();

			$local_levels  = isset( $risk_levels['local'] ) && is_array( $risk_levels['local'] ) ? $risk_levels['local'] : array();
			$matched_level = $this->resolve_risk_level( $retries_count, $local_levels );
			$display_data = $this->build_chart_display_data( $matched_level, $retries_count, $risk_config, $setup_code, $upgrade_premium_url );
			$retries_chart_title = $display_data['retries_chart_title'];
			$retries_chart_desc = $display_data['retries_chart_desc'];
			$retries_chart_color = $display_data['retries_chart_color'];
		} else {
			// Custom Cloud: always green "no risk" indicator; show retries count only (product spec).
			if ( $api_stats && ! empty( $api_stats['attempts']['count'] ) && is_array( $api_stats['attempts']['count'] ) ) {
				$attempt_counts = array();
				foreach ( $api_stats['attempts']['count'] as $v ) {
					if ( is_numeric( $v ) ) {
						$attempt_counts[] = (int) $v;
					}
				}
				if ( ! empty( $attempt_counts ) ) {
					$retries_count = (int) end( $attempt_counts );
				}
			}

			$retries_chart_title = $this->get_risk_circle_string( 'failed_today_title' );
			$retries_chart_desc  = '';
			$retries_chart_color = isset( $risk_colors['green'] ) ? $risk_colors['green'] : '#97F6C8';
		}

		return array(
			'retries_chart_title' => $retries_chart_title,
			'retries_chart_desc'  => $retries_chart_desc,
			'retries_chart_color' => $retries_chart_color,
			'retries_count'       => (int) $retries_count,
		);
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
		if ( 'custom' === Config::get( 'active_app' ) && self::$cloud_app ) {
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

		if ( version_compare( Helpers::get_wordpress_version(), '5.5', '<' ) ) {
			Config::update( 'auto_update_choice', 0 );
		}

		// Load languages files via a later hook
		// TODO: load_plugin_textdomain() is deprecated in WordPress 6.9+. WordPress now uses automatic JIT (Just-In-Time) translation loading.
		// This function still works for backward compatibility, but should be removed in future versions.
		// JIT translation loading automatically loads translation files when needed, so explicit load_plugin_textdomain() calls are no longer necessary.
	    add_action('init', array( $this, 'load_plugin_textdomain_in_time' ) );

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
		* This action should really be changed to the 'authenticate' filter as
		* it will probably be deprecated. That is however only available in
		* later versions of WP.
		*/
		add_filter( 'authenticate', array( $this, 'authenticate_guard_filter' ), -9999, 3 );
		add_action( 'authenticate', array( $this, 'track_credentials' ), 1, 3 ); // to replace the deprecated wp_authenticate hook
		add_action( 'authenticate', array( $this, 'authenticate_filter' ), 0, 3 );

		/**
		 * BuddyPress unactivated user account message fix
		 * Wordfence error message fix
		 */
		add_action( 'authenticate', array( $this, 'authenticate_filter_errors_fix' ), 35, 3 );

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
	 * Later loading of translations load_plugin_textdomain
	 * 
	 * TODO: This method uses deprecated load_plugin_textdomain() function.
	 * WordPress 6.9+ uses automatic JIT (Just-In-Time) translation loading, which means
	 * translation files are loaded automatically when needed. This explicit call can be
	 * removed in future versions. Ensure translation files are properly named and placed
	 * in the languages directory for JIT loading to work correctly.
	 */
	public function load_plugin_textdomain_in_time()
	{
		// TODO: Remove load_plugin_textdomain() call - WordPress 6.9+ handles translations automatically via JIT loading
		load_plugin_textdomain( 'limit-login-attempts-reloaded', false, plugin_basename( __DIR__ ) . '/../languages' );
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

		if ( Config::get( 'active_app' ) === 'local' && ! $limit_login_nonempty_credentials && ! $show_mfa_return_error ) {
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

		if ( Config::get( 'active_app' ) === 'local' ) {

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
		if ( Config::get( 'active_app' ) === 'custom' && $config = Config::get( 'app_config' ) ) {

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
		return Helpers::ip_in_range( $ip, ( array ) Config::get( 'whitelist' ) );
	}

	public function check_whitelist_usernames( $allow, $username )
	{
		return in_array( $username, ( array ) Config::get( 'whitelist_usernames' ) );
	}

	public function check_blacklist_ips( $allow, $ip )
	{
		return Helpers::ip_in_range( $ip, ( array ) Config::get( 'blacklist' ) );
	}

	public function check_blacklist_usernames( $allow, $username )
	{
		return in_array( $username, ( array ) Config::get( 'blacklist_usernames' ) );
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
		LoginFlowTransientStore::ensure_token();
		if ( ! is_wp_error( $user ) ) {
			LoginFlowTransientStore::merge( array( 'errors_in_early_hook' => false ) );
		}

		$error_message = '';
		if ( $this->check_login_blocked( $username, $password, $error_message ) ) {
			return $this->create_username_blacklisted_error( $error_message );
		}

		if ( ! empty( $username ) && ! empty( $password ) ) {
			$ip = $this->get_address();

			if ( self::$cloud_app && $response = $this->get_auth_acl_response( $username ) ) {
				if ( 'pass' === $response['result'] ) {
					remove_filter( 'login_errors', array( $this, 'fixup_error_messages' ) );
					// Keep wp_login_failed when MFA is enabled (and not temporarily disabled) so limit_login_failed runs (handshake + redirect to MFA app).
					$mfa_effectively_enabled = Config::get( 'mfa_enabled' ) && ( false === get_transient( MfaConstants::TRANSIENT_MFA_DISABLED ) );
					if ( ! $mfa_effectively_enabled ) {
						remove_filter( 'wp_login_failed', array( $this, 'limit_login_failed' ) );
					}
					remove_filter( 'wp_authenticate_user', array( $this, 'wp_authenticate_user' ), 99999 );
				}
			} else {

				$ip = $this->get_address();

				// Check if username is blacklisted
				if (
					( ! $this->is_username_whitelisted( $username ) && ! $this->is_ip_whitelisted( $ip ) )
					&& ( $this->is_username_blacklisted( $username ) || $this->is_ip_blacklisted( $ip ) )
				) {

					LoginFlowTransientStore::merge( array( 'login_attempts_left' => null ) );

					remove_filter( 'login_errors', array( $this, 'fixup_error_messages' ) );
					remove_filter( 'wp_login_failed', array( $this, 'limit_login_failed' ) );
					remove_filter( 'wp_authenticate_user', array( $this, 'wp_authenticate_user' ), 99999 );

					// Remove default WP authentication filters
					remove_filter( 'authenticate', 'wp_authenticate_username_password', 20 );
					remove_filter( 'authenticate', 'wp_authenticate_email_password', 20 );

					$user = new WP_Error();
					$err = __( '<strong>ERROR</strong>: Too many failed login attempts.', 'limit-login-attempts-reloaded' );

					$err = ! empty( $err ) ? '<span>' . $err . '</span>' : '';

					$user->add( 'username_blacklisted', $err );

					LoginFlowTransientStore::merge( array( 'errors_in_early_hook' => true ) );
					$this->all_errors_array['early_hook_errors'] = $err;

					if ( defined('XMLRPC_REQUEST') && XMLRPC_REQUEST ) {

						header('HTTP/1.0 403 Forbidden');
						exit;
					}

				} elseif ( $this->is_username_whitelisted( $username ) || $this->is_ip_whitelisted( $ip ) ) {
					LoginFlowTransientStore::merge( array( 'llar_user_is_whitelisted' => true ) );
					// Do not run limit_login_failed for whitelist: no lockout, but lockout_check / retries would still run and hit the API.
					remove_filter( 'wp_login_failed', array( $this, 'limit_login_failed' ) );
					$mfa_effectively_enabled = Config::get( 'mfa_enabled' ) && ( false === get_transient( MfaConstants::TRANSIENT_MFA_DISABLED ) );
					if ( ! $mfa_effectively_enabled ) {
						remove_filter( 'wp_authenticate_user', array( $this, 'wp_authenticate_user' ), 99999 );
					}
					remove_filter( 'login_errors', array( $this, 'fixup_error_messages' ) );

				} elseif ( self::$cloud_app && self::$cloud_app->last_response_code === 403 ) {
					add_action('wp_login', array( $this, 'cloud_app_null' ), 999);
				}
			}
		}

		return $user;
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

		$error_message = '';
		if ( $this->check_login_blocked( $username, $password, $error_message ) ) {
			remove_filter( 'authenticate', 'wp_authenticate_username_password', 20 );
			remove_filter( 'authenticate', 'wp_authenticate_email_password', 20 );
			return $this->create_username_blacklisted_error( $error_message );
		}

		return $user;
	}

	/**
	 * Unified blocked-login check for cloud ACL and local blacklist.
	 *
	 * @param string $username
	 * @param string $password
	 * @param string $error_message
	 * @return bool
	 * @throws Exception
	 */
	private function check_login_blocked( $username, $password, &$error_message ) {
		if ( empty( $username ) || empty( $password ) ) {
			return false;
		}

		if ( self::$cloud_app && $response = $this->get_auth_acl_response( $username ) ) {
			if ( 'deny' === $response['result'] ) {
				$time_left = ! empty( $response['time_left'] ) ? (int) $response['time_left'] : 0;
				$error_message = $this->build_lockout_error_message( $time_left );

				self::$cloud_app->add_error( $error_message );
				$this->log_security_event( 'cloud_acl_deny', $username, $this->get_address(), array( 'time_left' => $time_left ) );
				LoginFlowTransientStore::ensure_token();
				LoginFlowTransientStore::merge(
					array(
						'errors_in_early_hook'           => true,
						'llar_early_hook_error_message' => $error_message,
						'login_attempts_left'            => null,
					)
				);
				$this->all_errors_array['early_hook_errors'] = $error_message;

				if ( defined('XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
					header('HTTP/1.0 403 Forbidden' );
					exit;
				}

				remove_filter( 'login_errors', array( $this, 'fixup_error_messages' ) );
				remove_filter( 'wp_login_failed', array( $this, 'limit_login_failed' ) );
				remove_filter( 'wp_authenticate_user', array( $this, 'wp_authenticate_user' ), 99999 );

				return true;
			}
		}

		$ip = $this->get_address();
		if (
			( ! $this->is_username_whitelisted( $username ) && ! $this->is_ip_whitelisted( $ip ) )
			&& ( $this->is_username_blacklisted( $username ) || $this->is_ip_blacklisted( $ip ) )
		) {
			$error_message = $this->build_lockout_error_message();
			$this->log_security_event( 'local_blacklist_block', $username, $ip );
			LoginFlowTransientStore::ensure_token();
			LoginFlowTransientStore::merge(
				array(
					'errors_in_early_hook' => true,
					'login_attempts_left'  => null,
				)
			);
			$this->all_errors_array['early_hook_errors'] = $error_message;

			remove_filter( 'login_errors', array( $this, 'fixup_error_messages' ) );
			remove_filter( 'wp_login_failed', array( $this, 'limit_login_failed' ) );
			remove_filter( 'wp_authenticate_user', array( $this, 'wp_authenticate_user' ), 99999 );

			if ( defined('XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
				header('HTTP/1.0 403 Forbidden' );
				exit;
			}

			return true;
		}

		return false;
	}

	/**
	 * Build lockout error message with optional time left.
	 *
	 * @param int $time_left
	 * @return string
	 */
	private function build_lockout_error_message( $time_left = 0 ) {
		$err = __( '<strong>ERROR</strong>: Too many failed login attempts.', 'limit-login-attempts-reloaded' );

		if ( 0 < $time_left ) {
			if ( 60 < $time_left ) {
				$time_left = ceil( $time_left / 60 );
				$err .= ' ' . sprintf( _n( 'Please try again in %d hour.', 'Please try again in %d hours.', $time_left, 'limit-login-attempts-reloaded' ), $time_left );
			} else {
				$err .= ' ' . sprintf( _n( 'Please try again in %d minute.', 'Please try again in %d minutes.', $time_left, 'limit-login-attempts-reloaded' ), $time_left );
			}
		}

		return '<span>' . wp_kses_post( $err ) . '</span>';
	}

	/**
	 * Create standardized lockout WP_Error.
	 *
	 * @param string $error_message
	 * @return WP_Error
	 */
	private function create_username_blacklisted_error( $error_message ) {
		return new WP_Error( 'username_blacklisted', $error_message );
	}

	/**
	 * Lightweight security event logging (enabled when WP debug log is active).
	 *
	 * @param string $event_type
	 * @param string $username
	 * @param string $ip
	 * @param array  $details
	 * @return void
	 */
	private function log_security_event( $event_type, $username, $ip, $details = array() ) {
		if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
			return;
		}

		error_log(
			'[LLAR Security] ' . wp_json_encode(
				array(
					'event'    => $event_type,
					'username' => $this->mask_username_for_log( $username ),
					'ip'       => $this->mask_ip_for_log( $ip ),
					'gateway'  => Helpers::detect_gateway(),
					'details'  => $this->sanitize_security_log_details( $details ),
				)
			)
		);
	}

	/**
	 * Allow only non-sensitive detail keys in security logs.
	 *
	 * @param array $details Raw details.
	 * @return array
	 */
	private function sanitize_security_log_details( $details ) {
		if ( empty( $details ) || ! is_array( $details ) ) {
			return array();
		}

		$allowed = apply_filters(
			'llar_security_log_detail_keys',
			array( 'time_left', 'attempts', 'reason', 'window' )
		);
		if ( ! is_array( $allowed ) ) {
			$allowed = array( 'time_left', 'attempts', 'reason', 'window' );
		}

		$safe = array();
		foreach ( $details as $key => $value ) {
			if ( in_array( (string) $key, $allowed, true ) ) {
				$safe[ $key ] = $value;
			}
		}

		return $safe;
	}

	/**
	 * Mask username in logs to reduce sensitive data exposure.
	 *
	 * @param string $username
	 * @return string
	 */
	private function mask_username_for_log( $username ) {
		$username = (string) $username;
		$length = strlen( $username );
		if ( $length <= 0 ) {
			return '';
		}
		if ( $length <= 2 ) {
			return str_repeat( '*', $length );
		}

		return substr( $username, 0, 2 ) . str_repeat( '*', $length - 2 );
	}

	/**
	 * Reduce IP precision in debug logs (privacy).
	 *
	 * @param string $ip
	 * @return string
	 */
	private function mask_ip_for_log( $ip ) {
		$ip = (string) $ip;
		if ( '' === $ip ) {
			return '';
		}

		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return preg_replace( '/\.\d+$/', '.0', $ip );
		}

		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			if ( function_exists( 'inet_pton' ) && function_exists( 'inet_ntop' ) ) {
				$binary = inet_pton( $ip );
				if ( false !== $binary && 16 === strlen( $binary ) ) {
					$masked = substr( $binary, 0, 8 ) . str_repeat( "\0", 8 );
					$masked_ip = inet_ntop( $masked );
					if ( false !== $masked_ip ) {
						return $masked_ip . '/64';
					}
				}
			}
			if ( function_exists( 'wp_hash' ) ) {
				return wp_hash( $ip );
			}
		}

		return '***';
	}

	/**
	 * Cached Cloud ACL response for current authenticate request.
	 *
	 * @param string $username
	 * @return array|false
	 * @throws Exception
	 */
	private function get_auth_acl_response( $username ) {
		$payload = array(
			'ip'      => Helpers::get_all_ips(),
			'login'   => $username,
			'gateway' => Helpers::detect_gateway(),
		);
		$cache_key = md5( wp_json_encode( $payload ) );

		if ( isset( $this->auth_acl_response_cache[ $cache_key ] ) ) {
			return $this->auth_acl_response_cache[ $cache_key ];
		}

		$response = self::$cloud_app->acl_check( $payload );
		if ( $this->auth_acl_response_cache_max_size <= count( $this->auth_acl_response_cache ) ) {
			array_shift( $this->auth_acl_response_cache );
		}
		$this->auth_acl_response_cache[ $cache_key ] = $response;

		return $response;
	}


	/**
	 * Delete the CloudApp object
	 */
	public function cloud_app_null()
	{
		self::$cloud_app = null;
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
		if ( ! empty( $username ) && ! empty( $password ) ) {

			if ( is_wp_error( $user ) ) {

				// BuddyPress errors
				if ( in_array('bp_account_not_activated', $user->get_error_codes() ) ) {

					$this->other_login_errors[] = $user->get_error_message('bp_account_not_activated');
				} elseif ( in_array('wfls_captcha_verify', $user->get_error_codes() ) ) { // Wordfence errors

					$this->other_login_errors[] = $user->get_error_message( 'wfls_captcha_verify' );
				}
			}

		}
		return $user;
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
		$plugin_data = get_plugin_data( LLA_PLUGIN_DIR . 'limit-login-attempts-reloaded.php' );

		wp_enqueue_style( 'lla-main', LLA_PLUGIN_URL . 'assets/css/limit-login-attempts.css', array(), $plugin_data['Version'] );

		if ( ! empty( $_REQUEST['page'] ) && $_REQUEST['page'] === $this->_options_page_slug ) {

			$auto_update                = wp_create_nonce( 'llar-toggle-auto-update' );
			$app_setup                  = wp_create_nonce( 'llar-app-setup' );
			$account_policies           = wp_create_nonce( 'llar-strong-account-policies' );
			$block_country              = wp_create_nonce( 'llar-block_by_country' );
			$onboarding_reset           = wp_create_nonce( 'llar-action-onboarding-reset' );
			$dismiss_onboarding_popup   = wp_create_nonce( 'llar-dismiss-onboarding-popup' );
			$activate_micro_cloud       = wp_create_nonce( 'llar-activate-micro-cloud' );
			$subscribe_email            = wp_create_nonce( 'llar-subscribe-email' );
			$close_premium_message      = wp_create_nonce( 'llar-close-premium-message' );
			wp_enqueue_script( 'lla-main', LLA_PLUGIN_URL . 'assets/js/limit-login-attempts.js', array('jquery'), $plugin_data['Version'], false );
			wp_localize_script('lla-main', 'llar_vars', array(
				'nonce_auto_update'               => $auto_update,
				'nonce_app_setup'                 => $app_setup,
				'nonce_account_policies'          => $account_policies,
				'nonce_block_by_country'          => $block_country,
				'nonce_onboarding_reset'          => $onboarding_reset,
				'nonce_dismiss_onboarding_popup'  => $dismiss_onboarding_popup,
				'nonce_activate_micro_cloud'      => $activate_micro_cloud,
				'nonce_subscribe_email'           => $subscribe_email,
				'nonce_close_premium_message'     => $close_premium_message,
			));

			global $wp_scripts, $wp_styles;
				
			if($wp_scripts && $wp_scripts->registered) {
				foreach($wp_scripts->registered as $handle => $script) {
					if(strpos($handle, 'jquery-confirm') !== false) {
						wp_dequeue_script($handle);
					}
				}
			}
				
			if($wp_styles && $wp_styles->registered) {
				foreach($wp_styles->registered as $handle => $style) {
					if(strpos($handle, 'jquery-confirm') !== false) {
						wp_dequeue_style($handle);
					}
				}
			}

			wp_enqueue_style( 'lla-jquery-confirm', LLA_PLUGIN_URL . 'assets/css/jquery-confirm.min.css' );
			wp_enqueue_script( 'lla-jquery-confirm', LLA_PLUGIN_URL . 'assets/js/jquery-confirm.min.js' );
		}

	}

	public function login_page_enqueue()
	{
		if ( ! Config::get( 'gdpr' ) || isset( $_REQUEST['interim-login'] ) ) return;

		$plugin_data = get_plugin_data( LLA_PLUGIN_DIR . 'limit-login-attempts-reloaded.php' );

		wp_enqueue_style( 'llar-login-page-styles', LLA_PLUGIN_URL . 'assets/css/login-page-styles.css', array(), $plugin_data['Version'] );
		wp_enqueue_script( 'jquery' );
	}

	/**
	 * Add admin options page
	 */
	public function network_admin_menu()
	{
		if ( ! $this->has_capability ) return;

		add_submenu_page(
			'settings.php',
			'Limit Login Attempts',
			'Limit Login Attempts' . $this->menu_alert_icon(),
			self::$capabilities,
			$this->_options_page_slug,
			array( $this, 'options_page' ) );
	}

	private function get_submenu_items()
	{
		$active_app        = Config::get( 'active_app' );
		$app_setup_code    = Config::get( 'app_setup_code' );
		$is_cloud_app_enabled = $active_app === 'custom';
		$is_local_empty_setup_code = ( $active_app === 'local' && empty( $app_setup_code ) );

		$submenu_items = array(
			array(
				'id'    => 'dashboard',
				'name'  => __( 'Dashboard', 'limit-login-attempts-reloaded' ),
				'url'   => '&tab=dashboard'
			),
			array(
				'id'    => 'settings',
				'name'  => __( 'Settings', 'limit-login-attempts-reloaded' ),
				'url'   => '&tab=settings'
			),
			array(
				'id'    => 'mfa',
				'name'  => __( '2FA', 'limit-login-attempts-reloaded' ),
				'url'   => '&tab=mfa'
			),
			$is_cloud_app_enabled
				? array(
				'id'    => 'logs-custom',
				'name'  => __( 'Login Firewall', 'limit-login-attempts-reloaded' ),
				'url'   => '&tab=logs-custom'
			)
				: array(
				'id'    => 'logs-local',
				'name'  => __( 'Logs', 'limit-login-attempts-reloaded' ),
				'url'   => '&tab=logs-local'
			),
			array(
				'id'    => 'debug',
				'name'  => __( 'Debug', 'limit-login-attempts-reloaded' ),
				'url'   => '&tab=debug'
			),
			array(
				'id'    => 'help',
				'name'  => __( 'Help', 'limit-login-attempts-reloaded' ),
				'url'   => '&tab=help'
			)
		);

		if ( ! $is_cloud_app_enabled ) {

			$slug       = '&tab=dashboard#modal_micro_cloud';
			$name_item  = $is_local_empty_setup_code ? __( 'Free Upgrade', 'limit-login-attempts-reloaded' ) : __( 'Premium', 'limit-login-attempts-reloaded' );
			$url_item   = $is_local_empty_setup_code ? $slug : '&tab=premium';

			$submenu_items[] = array(
				'id'    => 'premium',
				'name'  => __( $name_item, 'limit-login-attempts-reloaded' ),
				'url'   => $url_item,
			);
		}

		return $submenu_items;
	}

	public function admin_menu()
	{
		if ( ! $this->has_capability ) return;

		global $submenu;

		if ( Config::get( 'show_top_level_menu_item' ) ) {

			add_menu_page(
				'Limit Login Attempts',
				'Limit Login Attempts' . $this->menu_alert_icon(),
				self::$capabilities,
				$this->_options_page_slug,
				array( $this, 'options_page' ),
				'data:image/svg+xml;base64,' . base64_encode( $this->get_svg_logo_content() ),
				74
			);

			$is_cloud_app_enabled = Config::get( 'active_app' ) === 'custom';
			$submenu_items = $this->get_submenu_items();

			$index = 1;
			foreach ( $submenu_items as $item ) {
				add_submenu_page(
					$this->_options_page_slug,
					$item['name'],
					$item['name'],
					self::$capabilities,
					$this->_options_page_slug . $item['url'],
					array( $this, 'options_page' )
				);

				if ( ! empty ( $_GET['page'] ) && $_GET['page'] === $this->_options_page_slug && ! empty( $_GET['tab'] ) && $_GET['tab'] === $item['id'] ) {
					$submenu[$this->_options_page_slug][$index][4] = 'current';
				}
				$index++;
			}

			remove_submenu_page( $this->_options_page_slug, $this->_options_page_slug );

			if ( ! $is_cloud_app_enabled && isset( $submenu[$this->_options_page_slug] ) ) {
				// Premium is the last submenu item (Dashboard, Settings, 2FA, Logs, Debug, Help, Premium).
				$submenu_keys = array_keys( $submenu[$this->_options_page_slug] );
				$premium_key  = end( $submenu_keys );
				$submenu[$this->_options_page_slug][$premium_key][4] =
					! empty( $submenu[$this->_options_page_slug][$premium_key][4] )
						? $submenu[$this->_options_page_slug][$premium_key][4] . ' llar-submenu-premium-item'
						: 'llar-submenu-premium-item';
			}

		} else {

			add_options_page(
				'Limit Login Attempts',
				'Limit Login Attempts' . $this->menu_alert_icon(),
				self::$capabilities,
				$this->_options_page_slug,
				array( $this, 'options_page' )
			);
		}
	}

	public function admin_bar_menu( $admin_bar )
	{

		if ( ! $this->has_capability ) return;

		$root_item_id = 'llar-root';
		$href = $this->get_options_page_uri();

		$admin_bar->add_node( array(
			'id'    => $root_item_id,
			'title' => __( 'LLAR', 'limit-login-attempts-reloaded' ) . $this->menu_alert_icon(),
			'href'  => $href,
		) );

		$submenu_items = $this->get_submenu_items();

		foreach ( $submenu_items as $item ) {

			$admin_bar->add_node( array(
				'parent'    => $root_item_id,
				'id'        => $root_item_id . '-' . $item['id'],
				'title'     => $item['name'],
				'href'      => $href . $item['url'],
			) );
		}

	}

	public function get_svg_logo_content()
	{
		return file_get_contents( LLA_PLUGIN_DIR . 'assets/img/logo.svg' );
	}

	private function menu_alert_icon()
	{

		if (
			! empty( $_COOKIE['llar_menu_alert_icon_shown'] )
			|| Config::get( 'active_app' ) !== 'local'
			|| ! Config::get( 'show_warning_badge' )
		) {
			return '';
		}

		$retries_count = 0;
		$retries_stats = Config::get( 'retries_stats' );

		if ( $retries_stats ) {

			foreach ( $retries_stats as $key => $count ) {

				if ( is_numeric( $key ) && $key > strtotime( '-24 hours' ) ) {
					$retries_count += $count;
				} elseif ( ! is_numeric( $key ) && date_i18n( 'Y-m-d' ) === $key ) {
					$retries_count += $count;
				}
			}
		}

		if ( $retries_count < 100 ) {
			return '';
		}

		return ' <span class="update-plugins count-1 llar-alert-icon"><span class="plugin-count">1</span></span>';
	}

	public function setting_menu_alert_icon()
	{
		global $menu;

		if ( ! Config::get( 'show_top_level_menu_item' ) && ! empty( $menu[80][0] ) ) {

			$menu[80][0] .= $this->menu_alert_icon();
		}
	}

	public function network_setting_menu_alert_icon()
	{
		global $menu;

		if ( ! empty( $menu[25][0] ) ) {

			$menu[25][0] .= $this->menu_alert_icon();
		}
	}

	/**
	 * Get the correct options page URI
	 *
	 * @param bool $tab
	 * @return mixed
	 */
	public function get_options_page_uri( $tab = false )
	{
		if ( is_network_admin() ) {
			$uri = network_admin_url( 'settings.php?page=' . $this->_options_page_slug );
		} else {
			$uri = admin_url( 'admin.php?page=' . $this->_options_page_slug );
		}

		if ( ! empty( $tab ) ) {
			$uri = add_query_arg( 'tab', $tab, $uri );
		}

		return $uri;
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
	 * @return bool
	 */
	public function is_limit_login_ok()
	{
		$ip = $this->get_address();

		/* Check external whitelist filter */
		if ( $this->is_ip_whitelisted( $ip ) ) {
			return true;
		}

		/* lockout active? */
		$lockouts = Config::get( 'lockouts' );

		return ( ! is_array( $lockouts ) || ! isset( $lockouts[ $ip ] ) || time() >= $lockouts[ $ip ] );
	}


	/**
	 * Redirect browser to MFA app URL. Clears output buffers, then sends Location header or HTML fallback.
	 *
	 * @param string $url Redirect URL (already escaped).
	 */
	public static function mfa_redirect_to_url( $url ) {
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		if ( ! headers_sent() ) {
			header( 'Location: ' . $url, true, 302 );
			exit;
		}
		$url_attr = esc_attr( $url );
		$url_js   = esc_js( $url );
		echo '<!DOCTYPE html><html><head><meta charset="utf-8"><meta http-equiv="refresh" content="0;url=' . $url_attr . '"><title>Redirect</title></head><body><p><a href="' . $url_attr . '">Continue to verification</a></p><script>window.location.replace("' . $url_js . '");</script></body></html>';
		exit;
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
		// This sets $limit_login_nonempty_credentials and login_attempts_left in LoginFlowTransientStore.
		// We don't block here - MemberPress will handle blocking if needed
		apply_filters( 'authenticate', null, $log, $pwd );

		// Return errors unchanged - we're only tracking, not blocking
		return $errors;
	}

	/**
	 * Run MFA flow on login: handshake, save session, redirect to MFA app.
	 * Exits on successful redirect. Call only after password verification.
	 *
	 * @param string  $username             Value from login form (user_login or email).
	 * @param bool    $is_pre_authenticated True if password was already validated (successful login).
	 * @param WP_User $authenticated_user  Optional. User after password check (use when log field is email).
	 * @return void Exits on redirect; otherwise returns.
	 */
	private function try_mfa_flow_redirect( $username, $is_pre_authenticated = false, $authenticated_user = null ) {
		// CRITICAL: never fetch or disclose any user info unless password was verified.
		if ( ! $is_pre_authenticated ) {
			return;
		}
		$ip = $this->get_address();

		$mfa_temporarily_disabled = false !== get_transient( MfaConstants::TRANSIENT_MFA_DISABLED );
		$mfa_enabled              = (bool) Config::get( 'mfa_enabled' ) && ! $mfa_temporarily_disabled;
		$user = null;
		if ( is_a( $authenticated_user, 'WP_User' ) ) {
			$user = $authenticated_user;
		} elseif ( is_string( $username ) && '' !== $username ) {
			$user = get_user_by( 'login', $username );
			if ( ! $user && function_exists( 'is_email' ) && is_email( $username ) ) {
				$user = get_user_by( 'email', $username );
			}
		}

		$mfa_roles        = Config::get( 'mfa_roles', array() );
		$mfa_roles        = is_array( $mfa_roles ) ? $mfa_roles : array();
		$user_excluded    = $user && ! empty( $mfa_roles ) && ! array_intersect( (array) $user->roles, $mfa_roles );
		$should_trigger_mfa = $mfa_enabled && ! $user_excluded;

		if ( ! $should_trigger_mfa ) {
			return;
		}

		if ( self::$mfa_flow_handshake_attempted ) {
			return;
		}

		$provider_id = defined( 'LLA_MFA_PROVIDER' ) ? LLA_MFA_PROVIDER : 'llar';
		$provider     = \LLAR\Core\MfaFlow\MfaProviderRegistry::get( $provider_id );
		if ( ! $provider ) {
			return;
		}

		$rate_key = 'llar_mfa_flow_handshake_' . md5( $ip . ( defined( 'AUTH_SALT' ) ? AUTH_SALT : 'llar' ) );
		$rate     = get_transient( $rate_key );
		$period   = defined( 'LLA_MFA_FLOW_HANDSHAKE_RATE_LIMIT_PERIOD' ) ? (int) LLA_MFA_FLOW_HANDSHAKE_RATE_LIMIT_PERIOD : 60;
		$max      = defined( 'LLA_MFA_FLOW_HANDSHAKE_RATE_LIMIT_MAX' ) ? (int) LLA_MFA_FLOW_HANDSHAKE_RATE_LIMIT_MAX : 5;

		if ( is_array( $rate ) && isset( $rate['t'], $rate['c'] ) ) {
			if ( time() - (int) $rate['t'] >= $period ) {
				$rate = array( 'c' => 0, 't' => time() );
			}
		} else {
			$rate = array( 'c' => 0, 't' => time() );
		}

		$rate_ok = ( (int) $rate['c'] < $max );
		if ( ! $rate_ok ) {
			$rate['c'] = (int) $rate['c'] + 1;
			set_transient( $rate_key, $rate, $period );
			return;
		}

		self::$mfa_flow_handshake_attempted = true;
		$user_group = '';
		if ( $user && ! empty( $user->roles ) && is_array( $user->roles ) ) {
			$user_group = reset( $user->roles );
		}
		$redirect_to = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : '';
		$cancel_url  = add_query_arg( 'llar_mfa_cancelled', '1', wp_login_url() );
		$current_request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$current_login_url   = '';
		if ( is_string( $current_request_uri ) && '' !== $current_request_uri ) {
			$current_login_url = home_url( $current_request_uri );
		}
		$login_url = ( '' !== $current_login_url ) ? $current_login_url : wp_login_url();
		$login_url   = add_query_arg( 'llar_mfa', '1', $login_url );
		if ( '' !== $redirect_to ) {
			$login_url = add_query_arg( 'redirect_to', $redirect_to, $login_url );
		}
		$payload     = array(
			'user_ip'              => Helpers::get_all_ips(),
			'login_url'            => $login_url,
			'user_group'           => $user_group,
			'is_pre_authenticated' => (bool) $is_pre_authenticated,
		);
		if ( $user ) {
			$payload['user_id'] = (int) $user->ID;
			if ( ! empty( $user->user_email ) && is_string( $user->user_email ) ) {
				$payload['user_email'] = Helpers::obfuscate_email( $user->user_email );
			}
		}

		$result = $provider->handshake( $payload );

		$has_token         = ! empty( $result['data']['token'] );
		$has_secret        = ! empty( $result['data']['secret'] );
		$redirect_url_value = isset( $result['data']['redirect_url'] ) ? $result['data']['redirect_url'] : ( isset( $result['data']['redirectUrl'] ) ? $result['data']['redirectUrl'] : '' );
		$has_redirect      = ! empty( $redirect_url_value );

		if ( $result['success'] && $has_token && $has_secret && $has_redirect ) {
			// Save session locally so callback can enforce is_pre_authenticated.
			$store = new \LLAR\Core\MfaFlow\SessionStore();
			// Single secret from MFA app (handshake response): used for verify and for send_code endpoint authorization.
			$store->save_send_email_secret( $result['data']['token'], $result['data']['secret'] );
			$state = wp_generate_password( 32, false, false );
			$remember_me = ! empty( $_REQUEST['rememberme'] );
			$session_username = ( $user && ! empty( $user->user_login ) ) ? $user->user_login : $username;
			$store->save_session(
				$result['data']['token'],
				$result['data']['secret'],
				$session_username,
				$user ? (int) $user->ID : 0,
				$redirect_to,
				$cancel_url,
				$provider_id,
				$is_pre_authenticated,
				$remember_me
			);
			$store->save_callback_state( $state, $result['data']['token'] );
			setcookie( 'llar_mfa_state', $state, time() + 600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
			$mfa_redirect_url = esc_url_raw( $redirect_url_value );
			if ( $mfa_redirect_url ) {
				self::mfa_redirect_to_url( $mfa_redirect_url );
				exit;
			}
		}

		// When external MFA API does not respond, behave as if MFA is absent (same mechanism as rescue; 1 min).
		if ( ! $result['success'] && ! empty( $result['server_unreachable'] ) ) {
			set_transient( MfaConstants::TRANSIENT_MFA_DISABLED, 'api_unreachable', 60 );
			return;
		}

		$rate['c'] = (int) $rate['c'] + 1;
		set_transient( $rate_key, $rate, $period );
	}

	/**
	 * Record one failed login attempt: Cloud lockout_check and/or local retries, lockout, notify.
	 * Used by limit_login_failed (wp_login_failed hook).
	 *
	 * @param string $username Login username.
	 */
	private function record_failed_login_attempt( $username ) {
		LoginFlowTransientStore::ensure_token();
		LoginFlowTransientStore::merge( array( 'login_attempts_left' => 0 ) );

		$ip = $this->get_address();

		if ( self::$cloud_app && $response = self::$cloud_app->lockout_check( array(
				'ip'        => Helpers::get_all_ips(),
				'login'     => $username,
				'gateway'   => Helpers::detect_gateway()
			) ) ) {

			if ( $response['result'] === 'allow' ) {

				LoginFlowTransientStore::merge( array( 'login_attempts_left' => (int) $response['attempts_left'] ) );

			} elseif ( $response['result'] === 'deny' ) {

				global $limit_login_just_lockedout;
				$limit_login_just_lockedout = true;

				$err = __( '<strong>ERROR</strong>: Too many failed login attempts.', 'limit-login-attempts-reloaded' );

				$time_left = ( ! empty( $response['time_left'] ) ) ? $response['time_left'] : 0;

				if ( $time_left > 60 ) {

					$time_left = ceil( $time_left / 60 );
					$err .= ' ' . sprintf( _n( 'Please try again in %d hour.', 'Please try again in %d hours.', $time_left, 'limit-login-attempts-reloaded' ), $time_left );
				} else {
					$err .= ' ' . sprintf( _n( 'Please try again in %d minute.', 'Please try again in %d minutes.', $time_left, 'limit-login-attempts-reloaded' ), $time_left );
				}

				self::$cloud_app->add_error( $err );
				LoginFlowTransientStore::merge( array( 'errors_in_early_hook' => false ) );
			}

		} else {

			$ip = $this->get_address();

			/* if currently locked-out, do not add to retries */
			$lockouts = Config::get( 'lockouts' );

			if ( ! is_array( $lockouts ) ) {
				$lockouts = array();
			}

			if ( isset( $lockouts[ $ip ] ) && time() < $lockouts[ $ip ] ) {
				return;
			}

			/* Get the arrays with retries and retries-valid information */
			$retries = Config::get( 'retries' );
			$valid   = Config::get( 'retries_valid' );
			$retries_stats = Config::get( 'retries_stats' );

			if ( ! is_array( $retries ) ) {

				$retries = array();
				Config::add( 'retries', $retries );
			}

			if ( ! is_array( $valid ) ) {

				$valid = array();
				Config::add( 'retries_valid', $valid );
			}

			if ( ! is_array( $retries_stats ) ) {

				$retries_stats = array();
				Config::add( 'retries_stats', $retries_stats );
			}

			$date_key = strtotime( date( 'Y-m-d H:00:00' ) );
			if ( ! empty( $retries_stats[ $date_key ] ) ) {

				$retries_stats[ $date_key ]++;
			} else {

				$retries_stats[ $date_key ] = 1;
			}
			$retries_stats = $this->prune_retries_stats_old_buckets( $retries_stats );
			Config::update( 'retries_stats', $retries_stats );

			/* Check validity and add one to retries */
			if ( isset( $retries[ $ip ] ) && isset( $valid[ $ip ] ) && time() < $valid[ $ip ] ) {

				$retries[ $ip ] ++;
			} else {

				$retries[ $ip ] = 1;
			}
			$valid[ $ip ] = time() + Config::get( 'valid_duration' );

			/* lockout? */
			if ( $retries[ $ip ] % Config::get( 'allowed_retries' ) != 0 ) {
				/*
				* Not lockout (yet!)
				* Do housecleaning (which also saves retry/valid values).
				*/
				$this->cleanup( $retries, null, $valid );

				LoginFlowTransientStore::merge( array( 'login_attempts_left' => $this->calculate_retries_remaining() ) );

				return;
			}

			/* lockout! */
			$whitelisted = $this->is_ip_whitelisted( $ip );
			$retries_long = Config::get( 'allowed_retries' ) * Config::get( 'allowed_lockouts' );

			/*
			* Note that retries and statistics are still counted and notifications
			* done as usual for whitelisted ips , but no lockout is done.
			*/
			if ( $whitelisted ) {

				if ( $retries[ $ip ] >= $retries_long ) {

					unset( $retries[ $ip ] );
					unset( $valid[ $ip ] );
				}
			} else {

				global $limit_login_just_lockedout;
				$limit_login_just_lockedout = true;

				/* setup lockout, reset retries as needed */
				if ( ( isset($retries[ $ip ]) ? $retries[ $ip ] : 0 ) >= $retries_long ) {

					/* long lockout */
					$lockouts[ $ip ] = time() + Config::get( 'long_duration' );
					unset( $retries[ $ip ] );
					unset( $valid[ $ip ] );
				} else {

					/* normal lockout */
					$lockouts[ $ip ] = time() + Config::get( 'lockout_duration' );
				}
			}

			/* do housecleaning and save values */
			$this->cleanup( $retries, $lockouts, $valid );

			/* do any notification */
			$this->notify( $username );

			/* increase statistics */
			$total = Config::get( 'lockouts_total' );
			if ( $total === false || ! is_numeric( $total ) ) {

				Config::add( 'lockouts_total', 1 );
			} else {

				Config::update( 'lockouts_total', $total + 1 );
			}
		}
	}

	/**
	 * Action when login attempt failed
	 *
	 * @param string $username Login username.
	 */
	public function limit_login_failed( $username ) {
		$this->record_failed_login_attempt( $username );
	}

	/**
	 * Handle notification in event of lockout
	 *
	 * @param $user
	 * @return bool|void
	 */
	public function notify( $user ) {

		if ( is_object( $user ) ) {
			return false;
		}

		$this->notify_log( $user );

		$args = explode( ',', Config::get( 'lockout_notify' ) );

		if ( empty( $args ) ) {
			return;
		}

		if ( in_array( 'email', $args ) ) {
			$this->notify_email( $user );
		}
	}

	/**
	 * Email notification of lockout to admin (if configured)
	 *
	 * @param $user
	 */
	public function notify_email( $user )
	{
		$ip = $this->get_address();
		$retries = Config::get( 'retries' );

		if ( ! is_array( $retries ) ) {
			$retries = array();
		}

		/* check if we are at the right nr to do notification */
		if (
			isset( $retries[ $ip ] )
			&& ( ( (int) $retries[ $ip ] / Config::get( 'allowed_retries' ) ) % Config::get( 'notify_email_after' ) ) != 0
		) {
			return;
		}

		/* Format message. First current lockout duration */
		if ( ! isset( $retries[ $ip ] ) ) {

			/* longer lockout */
			$count    = Config::get( 'allowed_retries' )
			            * Config::get( 'allowed_lockouts' );
			$lockouts = Config::get( 'allowed_lockouts' );
			$time     = round( Config::get( 'long_duration' ) / 3600 );
			$when     = sprintf( _n( '%d hour', '%d hours', $time, 'limit-login-attempts-reloaded' ), $time );
		} else {

			/* normal lockout */
			$count    = $retries[ $ip ];
			$lockouts = floor( ( $count ) / Config::get( 'allowed_retries' ) );
			$time     = round( Config::get( 'lockout_duration' ) / 60 );
			$when     = sprintf( _n( '%d minute', '%d minutes', $time, 'limit-login-attempts-reloaded' ), $time );
		}

		if ( $custom_admin_email = Config::get( 'admin_notify_email' ) ) {

			$admin_email = $custom_admin_email;
		} else {

			$admin_email = get_site_option( 'admin_email' );
		}

		$admin_name = '';

		global $wpdb;

		$res = $wpdb->get_col( $wpdb->prepare( "
                SELECT u.display_name
                FROM $wpdb->users AS u
                LEFT JOIN $wpdb->usermeta AS m ON u.ID = m.user_id
                WHERE u.user_email = %s
                AND m.meta_key LIKE 'wp_capabilities'
                AND m.meta_value LIKE '%administrator%'",
			$admin_email
		)
		);

		if ( $res ) {
			$admin_name = $res[0];
		}

		$site_domain = str_replace( array( 'http://', 'https://' ), '', home_url() );
		$blogname = Helpers::use_local_options() ? get_option( 'blogname' ) : get_site_option( 'site_name' );
		$blogname = htmlspecialchars_decode( $blogname, ENT_QUOTES );

		$plugin_data = get_plugin_data( LLA_PLUGIN_DIR . 'limit-login-attempts-reloaded.php' );

		$subject = sprintf(
			__( 'Failed login by IP %1$s %2$s', 'limit-login-attempts-reloaded' ),
			esc_html( $ip ),
			esc_html( $site_domain )
		);

		ob_start();
		include LLA_PLUGIN_DIR . 'views/emails/failed-login.php';
		$email_body = ob_get_clean();

		// get current url with the current page and the current query string
		$current_url_label = preg_replace( '/^\/|\/$/', '', $_SERVER['REQUEST_URI'] );
		$current_url = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : get_site_url() . $_SERVER['REQUEST_URI'];

		$placeholders = array(
			'{name}'                => $admin_name,
			'{domain}'              => $site_domain,
			'{attempts_count}'      => $count,
			'{lockouts_count}'      => $lockouts,
			'{ip_address}'          => esc_html( $ip ),
			'{ip_address_link}'     => esc_url( 'https://www.limitloginattempts.com/location/?ip=' . $ip ),
			'{username}'            => $user,
			'{blocked_duration}'    => $when,
			'{dashboard_url}'       => admin_url( 'options-general.php?page=' . $this->_options_page_slug ),
			'{premium_url}'         => 'https://www.limitloginattempts.com/info.php?from=plugin-lockout-email&v=' . $plugin_data['Version'],
			'{llar_url}'            => 'https://www.limitloginattempts.com/?from=plugin-lockout-email&v=' . $plugin_data['Version'],
			'{unsubscribe_url}'     => admin_url( 'options-general.php?page=' . $this->_options_page_slug . '&tab=settings' ),
			'{current_url}'         => $current_url,
			'{current_url_label}'   => $current_url_label,
		);

		$email_body = str_replace(
			array_keys( $placeholders ),
			array_values( $placeholders ),
			$email_body
		);

		Helpers::send_mail_with_logo( $admin_email, $subject, $email_body );
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

		if ( ! $user_login ) {
			return;
		}

		$log = $option = Config::get( 'logged' );

		if ( ! is_array( $log ) ) {
			$log = array();
		}
		$ip = $this->get_address();

		/* can be written much simpler, if you do not mind php warnings */
		if ( ! isset( $log[ $ip ] ) ) {
			$log[ $ip ] = array();
		}

		if ( ! isset( $log[ $ip ][ $user_login ] ) ) {

			$log[ $ip ][ $user_login ] = array( 'counter' => 0 );
		} elseif ( ! is_array( $log[ $ip ][ $user_login ] ) ) {

			$log[ $ip ][ $user_login ] = array( 'counter' => $log[ $ip ][ $user_login ] );
		}

		$log[ $ip ][ $user_login ]['counter']++;
		$log[ $ip ][ $user_login ]['date'] = time();

		$log[ $ip ][ $user_login ]['gateway'] = Helpers::detect_gateway();

		if ( $option === false ) {

			Config::add( 'logged', $log );
		} else {

			Config::update( 'logged', $log );
		}
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
		if ( is_null( $ip ) ) {
			$ip = $this->get_address();
		}

		$whitelisted = apply_filters( 'limit_login_whitelist_ip', false, $ip );

		return ( $whitelisted === true );
	}

	public function is_username_whitelisted( $username )
	{
		if ( empty( $username ) ) {
			return false;
		}

		$whitelisted = apply_filters( 'limit_login_whitelist_usernames', false, $username );

		return ( $whitelisted === true );
	}

	public function is_ip_blacklisted( $ip = null )
	{
		if ( is_null( $ip ) ) {
			$ip = $this->get_address();
		}

		$blacklisted = apply_filters( 'limit_login_blacklist_ip', false, $ip );

		return ( $blacklisted === true );
	}

	public function is_username_blacklisted( $username )
	{
		if ( empty( $username ) ) {
			return false;
		}

		$whitelisted = apply_filters( 'limit_login_blacklist_usernames', false, $username );

		return ( $whitelisted === true );
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
		$username = isset( $_REQUEST['log'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['log'] ) ) : '';
		if ( '' === $username && $this->integration_manager ) {
			$username = $this->integration_manager->get_login_identifier();
		}
		if ( empty( $password ) && $this->integration_manager ) {
			$integration_credentials = $this->integration_manager->get_login_credentials();
			if ( is_array( $integration_credentials ) && ! empty( $integration_credentials['password'] ) ) {
				$password = $integration_credentials['password'];
			}
		}
		$ip       = $this->get_address();
		$user_login = is_a( $user, 'WP_User' ) ? $user->user_login : ( ( ! empty( $user ) && ! is_wp_error( $user ) ) ? $user : '' );
		$not_locked_out = $this->check_whitelist_ips( false, $ip ) || $this->check_whitelist_usernames( false, $user_login ) || $this->is_limit_login_ok();

		if ( is_wp_error( $user ) ) {
			return $user;
		}

		// is_pre_authenticated must reflect actual password check: WP may pass valid $user by username before password is verified.
		$password_ok = false;
		if ( is_a( $user, 'WP_User' ) && ! empty( $password ) ) {
			$password_ok = wp_check_password( $password, $user->user_pass, $user->ID );
		}

		// If locked out, do not run MFA flow — return lockout error so blocked user cannot bypass via correct password + MFA.
		if ( ! $not_locked_out ) {
			$error = new WP_Error();
			global $limit_login_my_error_shown;
			$limit_login_my_error_shown = true;
			$error->add( 'too_many_retries', $this->error_msg() );
			LoginFlowTransientStore::merge( array( 'errors_in_early_hook' => false ) );
			return $error;
		}

		// Trigger MFA flow (for selected roles). Only treat as pre-authenticated if password was verified.
		if ( $username !== '' ) {
			$auth_user_for_mfa = ( $password_ok && is_a( $user, 'WP_User' ) ) ? $user : null;
			$this->try_mfa_flow_redirect( $username, $password_ok, $auth_user_for_mfa );
		}

		$user_login = '';

		if ( is_a( $user, 'WP_User' ) ) {

			$user_login = $user->user_login;
		} elseif( ! empty( $user ) && !is_wp_error( $user ) ) {

			$user_login = $user;
		}

		if (
			$this->check_whitelist_ips( false, $ip )
			|| $this->check_whitelist_usernames( false, $user_login )
			|| $this->is_limit_login_ok()
		) {
			return $user;
		}

		$error = new WP_Error();

		global $limit_login_my_error_shown;
		$limit_login_my_error_shown = true;

		if ( $this->is_username_blacklisted( $user_login ) || $this->is_ip_blacklisted( $this->get_address() ) ) {

			$err = __( '<strong>ERROR</strong>: Too many failed login attempts.', 'limit-login-attempts-reloaded' );
			$err = ! empty( $err ) ? '<span>' . $err . '</span>' : '';

			$error->add( 'username_blacklisted', $err );
			$this->all_errors_array['late_hook_errors'] = $err;
		} else {

			// This error should be the same as in "shake it" filter below
			$error->add( 'too_many_retries', $this->error_msg() );
		}

		LoginFlowTransientStore::merge( array( 'errors_in_early_hook' => false ) );

		return $error;
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
		global $limit_login_nonempty_credentials;

		$limit_login_nonempty_credentials = ( ! empty( $username ) && ! empty( $password ) );

		return $user;
	}

	/**
	 * Construct informative error message
	 *
	 * @return string
	 */
	public function error_msg()
	{
		$ip       = $this->get_address();
		$lockouts = Config::get( 'lockouts' );
		$a        = $this->checkKey($lockouts, $ip);
		$b        = $this->checkKey($lockouts, $this->getHash($ip));

		$msg = __( '<strong>ERROR</strong>: Too many failed login attempts.', 'limit-login-attempts-reloaded' ) . ' ';

		if (
			! is_array( $lockouts )
			|| ( ! isset( $lockouts[ $ip ] ) && ! isset( $lockouts[ $this->getHash( $ip ) ] ) )
			|| ( time() >= $a && time() >= $b )
		){
			/* Huh? No timeout active? */
			$msg .= __( 'Please try again later.', 'limit-login-attempts-reloaded' );

			$this->all_errors_array['late_hook_errors'] = $msg;
			LoginFlowTransientStore::merge( array( 'errors_in_early_hook' => false ) );

			return $msg;
		}

		$when = ceil( ( ($a > $b ? $a : $b) - time() ) / 60 );
		if ( $when > 60 ) {

			$when = ceil( $when / 60 );
			$msg .= sprintf( _n( 'Please try again in %d hour.', 'Please try again in %d hours.', $when, 'limit-login-attempts-reloaded' ), $when );
		} else {

			$msg .= sprintf( _n( 'Please try again in %d minute.', 'Please try again in %d minutes.', $when, 'limit-login-attempts-reloaded' ), $when );
		}

		$this->all_errors_array['late_hook_errors'] = $msg;
		LoginFlowTransientStore::merge( array( 'errors_in_early_hook' => false ) );

		return $msg;
	}

	/**
	 * When returning from MFA with llar_mfa_error, inject an error so WordPress outputs the red #login_error block.
	 *
	 * @param \WP_Error $errors      WP_Error object passed to login_header().
	 * @param string   $redirect_to  Redirect URL.
	 * @return \WP_Error
	 */
	public function inject_mfa_return_login_error( $errors, $redirect_to ) {
		$llar_mfa_error = isset( $_GET['llar_mfa_error'] ) ? sanitize_text_field( wp_unslash( $_GET['llar_mfa_error'] ) ) : '';
		if ( $llar_mfa_error !== '' ) {
			if ( ! is_wp_error( $errors ) ) {
				$errors = new \WP_Error();
			}
			$errors->add( 'llar_mfa_return', __( '<strong>ERROR</strong>: Incorrect username or password.', 'limit-login-attempts-reloaded' ) );
		}
		return $errors;
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
		global $limit_login_just_lockedout, $limit_login_nonempty_credentials, $limit_login_my_error_shown;

		$error_msg = $this->get_message();

		$early_hook_msg = LoginFlowTransientStore::get( 'llar_early_hook_error_message', '' );
		if ( $early_hook_msg !== '' && is_string( $early_hook_msg ) ) {
			$content = $early_hook_msg;
			LoginFlowTransientStore::merge(
				array(
					'llar_early_hook_error_message' => null,
					'errors_in_early_hook'           => false,
				)
			);
		} else {
		$llar_mfa_error = isset( $_GET['llar_mfa_error'] ) ? sanitize_text_field( wp_unslash( $_GET['llar_mfa_error'] ) ) : '';
		$show_mfa_return_error = ( $llar_mfa_error !== '' );

		if ( $limit_login_nonempty_credentials ) {

			$content = '';

			if ( $this->other_login_errors ) {

				foreach ( $this->other_login_errors as $msg ) {
					$content .= ! empty( $msg ) ? $msg . '<br />' : '';
				}

			} else {

				/* Replace error message, including ours if necessary */
				if ( ! empty( $_REQUEST['log'] ) && is_email( $_REQUEST['log'] ) ) {

					$content = __( '<strong>ERROR</strong>: Incorrect email address or password.', 'limit-login-attempts-reloaded' );
				} else {

					$content = __( '<strong>ERROR</strong>: Incorrect username or password.', 'limit-login-attempts-reloaded' );
				}
			}
		} elseif ( $show_mfa_return_error ) {
			/* Same red error as failed login when returning from MFA (e.g. pre_auth_required). */
			$content = __( '<strong>ERROR</strong>: Incorrect username or password.', 'limit-login-attempts-reloaded' );
		}

		if ( ! empty( $error_msg ) ) {

			$content = $error_msg;
		}
		}

		$content = ! empty( $content ) ? '<span>' . $content . '</span>' : '';

		$this->all_errors_array['late_hook_errors'] = $content;
		LoginFlowTransientStore::merge( array( 'errors_in_early_hook' => false ) );

		return $content;
	}

	public function fixup_error_messages_wc( \WP_Error $error )
	{
		$error->add( 1, __( 'WC Error', 'limit-login-attempts-reloaded' ) );
	}

	/**
	 * Return current (error) message to show, if any
	 *
	 * @return string
	 */
	public function get_message()
	{

		if ( self::$cloud_app ) {

			$app_errors = self::$cloud_app->get_errors();
			return ! empty( $app_errors ) ? implode( '<br>', $app_errors ) : '';
		}

		/* Check external whitelist */
		if ( $this->is_ip_whitelisted() ) {
			return '';
		}

		/* Is lockout in effect? */
		if ( ! $this->is_limit_login_ok() ) {
			return $this->error_msg();
		}

		return '';
	}

	private function calculate_retries_remaining()
	{
		$remaining = 0;

		$ip      = $this->get_address();
		$retries = Config::get( 'retries' );
		$valid   = Config::get( 'retries_valid' );
		$a = $this->checkKey($retries, $ip);
		$b = $this->checkKey($retries, $this->getHash($ip));
		$c = $this->checkKey($valid, $ip);
		$d = $this->checkKey($valid, $this->getHash($ip));

		/* Should we show retries remaining? */
		if ( ! is_array( $retries ) || ! is_array( $valid ) ) {
			/* no retries at all */
			return $remaining;
		}
		if (
			( ! isset( $retries[ $ip ] ) && ! isset( $retries[ $this->getHash($ip) ] ))
			|| ( ! isset( $valid[ $ip ] ) && ! isset( $valid[ $this->getHash($ip) ] ))
			|| ( time() > $c && time() > $d )
		) {
			/* no: no valid retries */
			return $remaining;
		}
		if (
			( $a % Config::get( 'allowed_retries' ) ) == 0
			&& ( $b % Config::get( 'allowed_retries' ) ) == 0
		) {
			/* no: already been locked out for these retries */
			return $remaining;
		}

		$remaining = max( ( Config::get( 'allowed_retries' ) - ( ($a + $b) % Config::get( 'allowed_retries' ) ) ), 0 );
		return (int) $remaining;
	}

	/**
	 * Get correct remote address
	 *
	 * @return string
	 *
	 */
	public function get_address()
	{
		return Helpers::detect_ip_address( Config::get( 'trusted_ip_origins' ) );
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
		$now      = time();
		$lockouts = ! is_null( $lockouts ) ? $lockouts : Config::get( 'lockouts' );

		$log = Config::get( 'logged' );

		/* remove old lockouts */
		if ( is_array( $lockouts ) ) {
			foreach ( $lockouts as $ip => $lockout ) {
				if ( $lockout < $now ) {
					unset( $lockouts[ $ip ] );

					if( is_array( $log ) && isset( $log[ $ip ] ) ) {
						foreach ( $log[ $ip ] as $user_login => &$data ) {

							if ( !is_array( $data ) ) {
								$data = array();
							}
							$data['unlocked'] = true;
						}
					}
				}
			}
			Config::update( 'lockouts', $lockouts );
		}

		Config::update( 'logged', $log );

		/* remove retries that are no longer valid */
		$valid   = ! is_null( $valid ) ? $valid : Config::get( 'retries_valid' );
		$retries = ! is_null( $retries ) ? $retries : Config::get( 'retries' );

		if ( ! is_array( $valid ) || ! is_array( $retries ) ) {
			return;
		}

		foreach ( $valid as $ip => $lockout ) {

			if ( $lockout < $now ) {

				unset( $valid[ $ip ] );
				unset( $retries[ $ip ] );
			}
		}

		/* go through retries directly, if for some reason they've gone out of sync */
		foreach ( $retries as $ip => $retry ) {

			if ( ! isset( $valid[ $ip ] ) ) {
				unset( $retries[ $ip ] );
			}
		}

		$retries_stats = Config::get( 'retries_stats' );

		if ( $retries_stats ) {

			$stats_cutoff = strtotime( '-8 day' );
			foreach ( $retries_stats as $key => $count ) {

				if ( $this->is_retries_stats_bucket_expired( $key, $stats_cutoff ) ) {
					unset( $retries_stats[ $key ] );
				}
			}

			Config::update( 'retries_stats', $retries_stats );
		}

		Config::update( 'retries', $retries );
		Config::update( 'retries_valid', $valid );
	}

	/**
	 * Render admin options page
	 */
	public function options_page()
	{
		if ( ! empty( $_GET['tab'] ) && $_GET['tab'] === 'settings' ) {
			Config::use_local_options( ! is_network_admin() );
		}

		$this->cleanup();

		if ( ! empty( $_POST ) ) {

			check_admin_referer( 'limit-login-attempts-options' );

			if ( is_network_admin() ) {

				Config::update( 'allow_local_options', ! empty( $_POST['allow_local_options'] ) );
			} elseif ( Helpers::is_network_mode() ) {

				Config::update( 'use_local_options', empty( $_POST['use_global_options'] ) );
			}

			/* Should we clear log? */
			if ( isset( $_POST[ 'clear_log' ] ) ) {

				Config::update( 'logged', array() );
				$this->show_message( __( 'Cleared IP log', 'limit-login-attempts-reloaded' ) );
			}

			/* Should we reset counter? */
			if ( isset( $_POST[ 'reset_total' ] ) ) {

				Config::update( 'lockouts_total', 0 );
				$this->show_message( __( 'Reset lockout count', 'limit-login-attempts-reloaded' ) );
			}

			/* Should we restore current lockouts? */
			if ( isset( $_POST[ 'reset_current' ] ) ) {

				Config::update( 'lockouts', array() );
				$this->show_message( __( 'Cleared current lockouts', 'limit-login-attempts-reloaded' ) );
			}

			/* Should we update options? */
			if ( isset( $_POST[ 'llar_update_dashboard' ] ) ) {

				$white_list_ips = ( ! empty( $_POST['lla_whitelist_ips'] ) ) ? explode("\n", str_replace("\r", "", stripslashes( $_POST['lla_whitelist_ips'] ) ) ) : array();

				if ( ! empty( $white_list_ips ) ) {

					foreach( $white_list_ips as $key => $ip ) {

						if( '' == $ip ) {
							unset( $white_list_ips[ $key ] );
						}
					}
				}

				Config::update('whitelist', $white_list_ips );

				$white_list_usernames = ( ! empty( $_POST['lla_whitelist_usernames'] ) ) ? explode("\n", str_replace("\r", "", stripslashes( $_POST['lla_whitelist_usernames'] ) ) ) : array();

				if ( ! empty( $white_list_usernames ) ) {

					foreach( $white_list_usernames as $key => $ip ) {

						if ( '' == $ip ) {

							unset( $white_list_usernames[ $key ] );
						}
					}
				}

				Config::update('whitelist_usernames', $white_list_usernames );

				$black_list_ips = ( ! empty( $_POST['lla_blacklist_ips'] ) ) ? explode("\n", str_replace("\r", "", stripslashes( $_POST['lla_blacklist_ips'] ) ) ) : array();

				if ( ! empty( $black_list_ips ) ) {

					foreach( $black_list_ips as $key => $ip ) {

						$range = array_map('trim', explode( '-', $ip ) );

						if ( count( $range ) > 1 && ( float )sprintf( "%u", ip2long( $range[0] ) ) > ( float )sprintf( "%u",ip2long( $range[1] ) ) ) {

							$this->show_message( sprintf ( __( 'The %s IP range is invalid', 'limit-login-attempts-reloaded' ), $ip ) );
						}

						if ( '' == $ip ) {

							unset( $black_list_ips[ $key ] );
						}
					}
				}

				Config::update('blacklist', $black_list_ips );

				$black_list_usernames = ( ! empty( $_POST['lla_blacklist_usernames'] ) ) ? explode("\n", str_replace("\r", "", stripslashes( $_POST['lla_blacklist_usernames'] ) ) ) : array();

				if ( ! empty( $black_list_usernames ) ) {

					foreach( $black_list_usernames as $key => $ip ) {

						if ( '' == $ip ) {
							unset( $black_list_usernames[ $key ] );
						}
					}
				}
				Config::update('blacklist_usernames', $black_list_usernames );

				Config::sanitize_options();

				$this->show_message( __( 'Settings saved.', 'limit-login-attempts-reloaded' ) );

			} elseif ( isset( $_POST[ 'llar_update_settings' ] ) ) {

				/* Should we support GDPR */
				if ( isset( $_POST[ 'gdpr' ] ) ) {

					Config::update( 'gdpr', 1 );
				} else {

					Config::update( 'gdpr', 0 );
				}

				Config::update('show_top_level_menu_item', ( isset( $_POST['show_top_level_menu_item'] ) ? 1 : 0 ) );
				Config::update('show_top_bar_menu_item', ( isset( $_POST['show_top_bar_menu_item'] ) ? 1 : 0 ) );
				Config::update('hide_dashboard_widget', ( isset( $_POST['hide_dashboard_widget'] ) ? 1 : 0 ) );
				Config::update('show_warning_badge', ( isset( $_POST['show_warning_badge'] ) ? 1 : 0 ) );

				Config::update('allowed_retries',           (int)$_POST['allowed_retries'] );
				Config::update('lockout_duration',    (int)$_POST['lockout_duration'] * 60 );
				Config::update('valid_duration',      (int)$_POST['valid_duration'] * 3600 );
				Config::update('allowed_lockouts',          (int)$_POST['allowed_lockouts'] );
				Config::update('long_duration',       (int)$_POST['long_duration'] * 3600 );
				Config::update('notify_email_after',        (int)$_POST['email_after'] );
				Config::update('gdpr_message',              sanitize_textarea_field( Helpers::deslash( $_POST['gdpr_message'] ) ) );
				Config::update('custom_error_message',      sanitize_textarea_field( Helpers::deslash( $_POST['custom_error_message'] ) ) );
				Config::update('admin_notify_email',        sanitize_email( $_POST['admin_notify_email'] ) );

				Config::update('active_app', sanitize_text_field( $_POST['active_app'] ) );

				$trusted_ip_origins = ( ! empty( $_POST['lla_trusted_ip_origins'] ) )
					? array_map( 'trim', explode( ',', sanitize_text_field( $_POST['lla_trusted_ip_origins'] ) ) )
					: array();

				if ( ! in_array( 'REMOTE_ADDR', $trusted_ip_origins ) ) {

					$trusted_ip_origins[] = 'REMOTE_ADDR';
				}

				Config::update('trusted_ip_origins', $trusted_ip_origins );

				$notify_methods = array();

				if ( isset( $_POST[ 'lockout_notify_email' ] ) ) {
					$notify_methods[] = 'email';
				}
				Config::update('lockout_notify', implode( ',', $notify_methods ) );

				Config::sanitize_options();

				if ( ! empty( $_POST['llar_app_settings'] ) && self::$cloud_app ) {

					if ( ( $app_setup_code = Config::get( 'app_setup_code' ) ) && $setup_result = CloudApp::setup( strrev( $app_setup_code ) ) ) {

						if ( $setup_result['success'] && $active_app_config = $setup_result['app_config'] ) {

							foreach ( $_POST['llar_app_settings'] as $key => $value ) {

								if ( array_key_exists( $key, $active_app_config['settings'] ) ) {

									if ( ! empty( $active_app_config['settings'][$key]['options'] ) &&
									     ! in_array( $value, $active_app_config['settings'][$key]['options'] ) ) {

										continue;
									}

									$active_app_config['settings'][$key]['value'] = $value;
								}
							}

							Config::update( 'app_config', $active_app_config );
						}
					}
				}
				$this->show_message( __( 'Settings saved.', 'limit-login-attempts-reloaded' ) );
				$this->cloud_app_init();
			} elseif ( isset( $_POST['llar_update_mfa_settings'] ) ) {
				// Handle MFA settings submission via controller (capability checked inside)
				if ( $this->mfa_controller ) {
					$show_popup = $this->mfa_controller->handle_settings_submission();
					if ( ! $show_popup ) {
						$this->show_message( __( 'Settings saved.', 'limit-login-attempts-reloaded' ) );
					}
				}
			}
		}

		// Prepare roles data for MFA tab (before including view to ensure data is ready)
		// Check if we're on MFA tab (GET or POST with tab parameter, or default after form submit)
		$current_tab = 'settings';
		if ( isset( $_GET['tab'] ) && in_array( $_GET['tab'], self::$allowed_tabs ) ) {
			$current_tab = sanitize_text_field( $_GET['tab'] );
		} elseif ( isset( $_POST['llar_update_mfa_settings'] ) ) {
			// After MFA form submit, we're still on MFA tab
			$current_tab = 'mfa';
		}

		// MFA tab data comes from get_settings_for_view() (single source in MfaSettingsManager)
		include_once LLA_PLUGIN_DIR . 'views/options-page.php';
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

		$seconds_left = $this->get_mfa_rescue_links_seconds_left();
		if ( null === $seconds_left ) {
			return true;
		}

		return $seconds_left <= MfaConstants::RESCUE_NOTICE_THRESHOLD;
	}

	/**
	 * Return seconds left until the latest rescue-link transient expiration.
	 * Result is cached (see MfaConstants::RESCUE_MAX_EXPIRY_CACHE_*) to limit repeated LIKE queries on wp_options.
	 *
	 * @return int|null Null when rescue transients are absent.
	 */
	private function get_mfa_rescue_links_seconds_left() {
		$cache_key = MfaConstants::RESCUE_MAX_EXPIRY_CACHE_KEY;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached && is_numeric( $cached ) ) {
			$max_timeout = (int) $cached;
			if ( -1 === $max_timeout ) {
				return null;
			}
			if ( 0 < $max_timeout ) {
				return $max_timeout - time();
			}
		}

		global $wpdb;

		$timeout_like = $wpdb->esc_like( '_transient_timeout_' . MfaConstants::TRANSIENT_RESCUE_PREFIX ) . '%';

		$max_timeout = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT MAX(CAST(option_value AS UNSIGNED)) FROM ' . $wpdb->options . ' WHERE option_name LIKE %s',
				$timeout_like
			)
		);

		$cache_value = 0 === $max_timeout ? -1 : $max_timeout;
		set_transient( $cache_key, $cache_value, MfaConstants::RESCUE_MAX_EXPIRY_CACHE_TTL );

		if ( 0 === $max_timeout ) {
			return null;
		}

		return $max_timeout - time();
	}

	/**
	 * Return non-LLAR callbacks attached to authenticate filter.
	 *
	 * When the owning plugin cannot be resolved (empty plugin metadata), we still want to warn
	 * if the hook runs at an unusual priority (often security plugins use very early/late priorities).
	 * Callbacks with unknown origin but a typical priority are skipped to avoid noisy Debug notices:
	 * many benign cases (themes, mu-plugins, shared vendor paths) sit in the same numeric range as
	 * core and common plugins. Filter hooks below can widen the "normal" window if needed.
	 *
	 * @return array
	 */
	public static function get_foreign_authenticate_hooks() {
		global $wp_filter;

		if ( empty( $wp_filter['authenticate'] ) || ! is_object( $wp_filter['authenticate'] ) || ! isset( $wp_filter['authenticate']->callbacks ) ) {
			return array();
		}

		$allowed_callbacks = array(
			'wp_authenticate_username_password',
			'wp_authenticate_email_password',
			'wp_authenticate_spam_check',
		);

		$foreign = array();
		foreach ( $wp_filter['authenticate']->callbacks as $priority => $callbacks ) {
			if ( ! is_array( $callbacks ) ) {
				continue;
			}

			foreach ( $callbacks as $callback_data ) {
				if ( empty( $callback_data['function'] ) ) {
					continue;
				}

				$callback_name = self::normalize_hook_callback_name( $callback_data['function'] );
				if ( '' === $callback_name ) {
					continue;
				}

				$is_llar = ( 0 === strpos( $callback_name, __CLASS__ . '::' ) );
				if ( $is_llar || in_array( $callback_name, $allowed_callbacks, true ) ) {
					continue;
				}

				$plugin_meta = self::detect_plugin_for_hook_callback( $callback_data['function'] );
				$hook_priority = (int) $priority;
				// Unknown source + normal-looking priority: omit from the list (see docblock on this method).
				if ( empty( $plugin_meta ) && ! self::is_anomalous_authenticate_priority( $hook_priority ) ) {
					continue;
				}

				$foreign[] = array(
					'priority'      => $hook_priority,
					'callback'      => $callback_name,
					'accepted_args' => isset( $callback_data['accepted_args'] ) ? (int) $callback_data['accepted_args'] : 0,
					'plugin'        => $plugin_meta,
				);
			}
		}

		return $foreign;
	}

	/**
	 * Whether authenticate hook priority is unusual enough to surface unknown callbacks.
	 *
	 * Default "normal" band is [-10000, 999]: covers LLAR early hooks, core (e.g. 20), and typical
	 * third-party priorities. Outside that band we treat priority as anomalous and list the callback
	 * even when plugin metadata is missing. The optional filter can force anomalous for edge cases
	 * inside the band.
	 *
	 * @param int $priority Hook priority.
	 * @return bool
	 */
	private static function is_anomalous_authenticate_priority( $priority ) {
		$priority = (int) $priority;
		$min = (int) apply_filters( 'llar_foreign_auth_hook_normal_priority_min', -10000 );
		$max = (int) apply_filters( 'llar_foreign_auth_hook_normal_priority_max', 999 );
		if ( $priority < $min || $priority > $max ) {
			return true;
		}

		// Allow hosts to flag specific in-band priorities as worth showing without plugin resolution.
		return (bool) apply_filters( 'llar_foreign_auth_hook_force_anomalous_priority', false, $priority );
	}

	/**
	 * Convert callback to stable printable format.
	 *
	 * @param mixed $callback
	 * @return string
	 */
	private static function normalize_hook_callback_name( $callback ) {
		if ( is_string( $callback ) ) {
			return $callback;
		}

		if ( is_array( $callback ) && 2 === count( $callback ) ) {
			if ( is_object( $callback[0] ) ) {
				return get_class( $callback[0] ) . '::' . $callback[1];
			}
			if ( is_string( $callback[0] ) ) {
				return $callback[0] . '::' . $callback[1];
			}
		}

		if ( $callback instanceof \Closure ) {
			return 'Closure';
		}

		return '';
	}

	/**
	 * Resolve plugin metadata for callback, if callback comes from plugin file.
	 *
	 * @param mixed $callback
	 * @return array
	 */
	private static function detect_plugin_for_hook_callback( $callback ) {
		$source_file = self::get_hook_callback_source_file_cached( $callback );
		if ( '' === $source_file ) {
			return array();
		}

		$source_file = wp_normalize_path( $source_file );
		if ( isset( self::$hook_source_file_plugin_cache[ $source_file ] ) ) {
			return self::$hook_source_file_plugin_cache[ $source_file ];
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();
		$plugins_dir = trailingslashit( wp_normalize_path( WP_PLUGIN_DIR ) );

		if ( 0 !== strpos( $source_file, $plugins_dir ) ) {
			self::$hook_source_file_plugin_cache[ $source_file ] = array();

			return array();
		}

		$relative_file = ltrim( substr( $source_file, strlen( $plugins_dir ) ), '/' );
		$result = array();

		foreach ( $plugins as $plugin_file => $plugin_data ) {
			$plugin_file = wp_normalize_path( $plugin_file );
			$plugin_dir = dirname( $plugin_file );
			$is_main_file = ( $relative_file === $plugin_file );
			$is_inside_plugin_dir = ( '.' !== $plugin_dir && 0 === strpos( $relative_file, trailingslashit( $plugin_dir ) ) );

			if ( ! $is_main_file && ! $is_inside_plugin_dir ) {
				continue;
			}

			$slug = explode( '/', $plugin_file );
			$slug = sanitize_key( $slug[0] );

			$result = array(
				'slug'    => $slug,
				'name'    => isset( $plugin_data['Name'] ) ? $plugin_data['Name'] : '',
				'version' => isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : '',
				'file'    => $plugin_file,
			);
			break;
		}

		self::$hook_source_file_plugin_cache[ $source_file ] = $result;

		return $result;
	}

	/**
	 * Stable cache key for a hook callback (reflection is expensive per callback).
	 *
	 * @param mixed $callback Callback from WP_Hook.
	 * @return string
	 */
	private static function get_hook_callback_cache_key( $callback ) {
		if ( is_string( $callback ) ) {
			return 's:' . $callback;
		}

		if ( is_array( $callback ) && 2 === count( $callback ) ) {
			if ( is_object( $callback[0] ) ) {
				return 'o:' . spl_object_hash( $callback[0] ) . ':' . (string) $callback[1];
			}
			if ( is_string( $callback[0] ) ) {
				return 'c:' . $callback[0] . '::' . (string) $callback[1];
			}
		}

		if ( $callback instanceof \Closure ) {
			return 'f:' . spl_object_hash( $callback );
		}

		return 'u:' . md5( serialize( $callback ) );
	}

	/**
	 * Return source file for callback, with per-request cache.
	 *
	 * @param mixed $callback
	 * @return string
	 */
	private static function get_hook_callback_source_file_cached( $callback ) {
		$key = self::get_hook_callback_cache_key( $callback );
		if ( isset( self::$hook_callback_source_file_cache[ $key ] ) ) {
			return self::$hook_callback_source_file_cache[ $key ];
		}

		$file = self::get_hook_callback_source_file( $callback );
		self::$hook_callback_source_file_cache[ $key ] = $file;

		return $file;
	}

	/**
	 * Get callback source file path using reflection.
	 *
	 * @param mixed $callback
	 * @return string
	 */
	private static function get_hook_callback_source_file( $callback ) {
		try {
			if ( is_string( $callback ) && function_exists( $callback ) ) {
				$reflection = new \ReflectionFunction( $callback );
				return (string) $reflection->getFileName();
			}

			if ( is_array( $callback ) && 2 === count( $callback ) ) {
				$object_or_class = $callback[0];
				$method = $callback[1];

				if ( is_object( $object_or_class ) && method_exists( $object_or_class, $method ) ) {
					$reflection = new \ReflectionMethod( $object_or_class, $method );
					return (string) $reflection->getFileName();
				}

				if ( is_string( $object_or_class ) && method_exists( $object_or_class, $method ) ) {
					$reflection = new \ReflectionMethod( $object_or_class, $method );
					return (string) $reflection->getFileName();
				}
			}

			if ( $callback instanceof \Closure ) {
				$reflection = new \ReflectionFunction( $callback );
				return (string) $reflection->getFileName();
			}
		} catch ( \Exception $e ) {
			return '';
		}

		return '';
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

	/**
	 * returns IP with its md5 value
	 */
	private function getHash( $str )
	{
		return md5( $str );
	}

	/**
	 * @param $arr - array
	 * @param $k - key
	 * @return int array value at given index or zero
	 */
	private function checkKey( $arr, $k )
	{
		return isset( $arr[ $k ] ) ? $arr[ $k ] : 0;
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


	public function show_leave_review_notice()
	{
		$screen = get_current_screen();

		if ( isset( $_COOKIE['llar_review_notice_shown'] ) ) {

			Config::update( 'review_notice_shown', true );
			@setcookie( 'llar_review_notice_shown', '', time() - 3600, '/' );
		}

		if (
			! $this->has_capability
			|| Config::get( 'review_notice_shown' )
			|| ! in_array( $screen->base, array( 'dashboard', 'plugins', 'toplevel_page_limit-login-attempts' ) )
		) {
			return;
		}

		$activation_timestamp = Config::get( 'activation_timestamp' );

		if ( $activation_timestamp && $activation_timestamp < strtotime("-1 month") ) : ?>

            <div id="message" class="updated fade notice is-dismissible llar-notice-review">
                <div class="llar-review-image">
                    <img width="80px" src="<?php echo LLA_PLUGIN_URL?>assets/img/icon-256x256.png" alt="review-logo">
                </div>
                <div class="llar-review-info">
                    <p><?php _e('Hey <strong>Limit Login Attempts Reloaded</strong> user!', 'limit-login-attempts-reloaded'); ?></p>
                    <!--<p><?php _e('A <strong>crazy idea</strong> we wanted to share! What if we put an image from YOU on the <a href="https://wordpress.org/plugins/limit-login-attempts-reloaded/" target="_blank">LLAR page</a>?! (<a href="https://wordpress.org/plugins/hello-dolly/" target="_blank">example</a>) A drawing made by you or your child would cheer people up! Send us your drawing by <a href="mailto:wpchef.me@gmail.com" target="_blank">email</a> and we like it, we\'ll add it in the next release. Let\'s have some fun!', 'limit-login-attempts-reloaded'); ?></p> Also, -->
                    <p><?php _e('We would really like to hear your feedback about the plugin! Please take a couple minutes to write a few words <a href="https://wordpress.org/support/plugin/limit-login-attempts-reloaded/reviews/#new-post" target="_blank">here</a>. Thank you!', 'limit-login-attempts-reloaded'); ?></p>

                    <ul class="llar-buttons">
                        <li><a href="#" class="llar-review-dismiss" data-type="dismiss"><?php _e('Don\'t show again', 'limit-login-attempts-reloaded'); ?></a></li>
                        <li><i class=""></i><a href="#" class="llar-review-dismiss llar_button menu__item button__transparent_orange" data-type="later"><?php _e('Maybe later', 'limit-login-attempts-reloaded'); ?></a></li>
                        <li><a class="llar_button menu__item button__transparent_orange" target="_blank" href="https://wordpress.org/support/plugin/limit-login-attempts-reloaded/reviews/#new-post"><?php _e('Leave a review', 'limit-login-attempts-reloaded'); ?></a></li>
                    </ul>
                </div>
            </div>
            <script type="text/javascript">
                ( function( $ ){

                    $( document ).ready( function() {
                        $( '.llar-review-dismiss' ).on( 'click', function( e ) {
                            e.preventDefault();

                            const type = $( this ).data( 'type' );

                            $.post( ajaxurl, {
                                action: 'dismiss_review_notice',
                                type: type,
                                sec: '<?php echo wp_create_nonce( "llar-dismiss-review" ); ?>'
                            } );

                            $( this ).closest( '.llar-notice-review' ).remove();
                        } );

                        $( ".llar-notice-review" ).on( "click", ".notice-dismiss", function (event) {
                            createCookie( 'llar_review_notice_shown', '1', 30 );
                        } );

                        function createCookie( name, value, days ) {
                            let expires;

                            if ( days ) {
                                const date = new Date();
                                date.setTime( date.getTime() + (days * 24 * 60 * 60 * 1000 ) );
                                expires = "; expires=" + date.toGMTString();
                            } else {
                                expires = "";
                            }
                            document.cookie = encodeURIComponent( name ) + "=" + encodeURIComponent( value ) + expires + "; path=/";
                        }
                    } );

                } )(jQuery);
            </script>
		<?php endif;
	}

	public function show_enable_notify_notice()
	{
		$screen = get_current_screen();

		if ( isset( $_COOKIE['llar_enable_notify_notice_shown'] ) ) {

			Config::update( 'enable_notify_notice_shown', true );
			@setcookie( 'llar_enable_notify_notice_shown', '', time() - 3600, '/' );
		}

		$active_app = Config::get( 'active_app' );
		$notify_methods = explode( ',', Config::get( 'lockout_notify' ) );

		if (
			$active_app !== 'local'
			|| in_array( 'email', $notify_methods )
			|| ! $this->has_capability
			|| Config::get('enable_notify_notice_shown')
			|| $screen->parent_base === 'edit'
		) {

			return;
		}

		$activation_timestamp = Config::get('notice_enable_notify_timestamp');

		if ( $activation_timestamp && $activation_timestamp < strtotime("-1 month") ) {

			$review_activation_timestamp = Config::get('activation_timestamp');

			if ( $review_activation_timestamp && $review_activation_timestamp < strtotime("-1 month") ) {
				Config::update( 'activation_timestamp', time() );
			}

			?>

            <div id="message" class="updated fade notice is-dismissible llar-notice-notify">
                <div class="llar-review-image">
                    <span class="dashicons dashicons-warning"></span>
                </div>
                <div class="llar-review-info">
                    <p><?php _e('You have been upgraded to the latest version of <strong>Limit Login Attempts Reloaded</strong>.<br> ' .
					            'Due to increased security threats around the holidays, we recommend turning on email ' .
					            'notifications when you receive a failed login attempt.', 'limit-login-attempts-reloaded'); ?></p>

                    <ul class="llar-buttons">
                        <li><a class="button button-primary llar-ajax-enable-notify" target="_blank" href="#"><?php _e('Yes, turn on email notifications', 'limit-login-attempts-reloaded'); ?></a></li>
                        <li><a href="#" class="llar-notify-notice-dismiss button" data-type="later"><?php _e('Remind me a month from now', 'limit-login-attempts-reloaded'); ?></a></li>
                        <li><a href="#" class="llar-notify-notice-dismiss" data-type="dismiss"><?php _e('Don\'t show this message again', 'limit-login-attempts-reloaded'); ?></a></li>
                    </ul>
                </div>
            </div>
            <script type="text/javascript">
                ( function( $ ) {

                    $( document ).ready( function() {
                        $( '.llar-notify-notice-dismiss' ).on( 'click', function( e ) {
                            e.preventDefault();

                            const type = $( this ).data( 'type' );

                            $.post( ajaxurl, {
                                action: 'dismiss_notify_notice',
                                type: type,
                                sec: '<?php echo wp_create_nonce( "llar-dismiss-notify-notice" ); ?>'
                            } );

                            $( this ).closest( '.llar-notice-notify' ).remove();
                        } );

                        $( ".llar-notice-notify" ).on( "click", ".notice-dismiss", function ( e ) {
                            createCookie( 'llar_enable_notify_notice_shown', '1', 30 );
                        } );

                        $( ".llar-ajax-enable-notify" ).on( "click", function ( e ) {
                            e.preventDefault();

                            $.post( ajaxurl, {
                                action: 'enable_notify',
                                sec: '<?php echo wp_create_nonce( "llar-enable-notify" ); ?>'
                            }, function( response ){

                                if ( response.success ) {
                                    $( ".llar-notice-notify .llar-review-info p" ).text( 'You are all set!' );
                                    $( ".llar-notice-notify .llar-buttons" ).remove();
                                }

                            } );
                        } );

                        function createCookie( name, value, days ) {
                            let expires;

                            if ( days ) {
                                const date = new Date();
                                date.setTime( date.getTime() + ( days * 24 * 60 * 60 * 1000 ) );
                                expires = "; expires=" + date.toGMTString();
                            } else {
                                expires = "";
                            }
                            document.cookie = encodeURIComponent( name ) + "=" + encodeURIComponent( value ) + expires + "; path=/";
                        }
                    } );

                } )(jQuery);
            </script>
			<?php
		}
	}


	/**
	 * Check if the user is a cloud user and if limit_registration is enabled
	 * @return bool
	 */
	private function is_limit_registration()
	{
		if ( ! self::$cloud_app ) {
			return false;
		}

		$app_config = Config::get( 'app_config' );
		$limit_registration = isset( $app_config['settings']['limit_registration']['value'] ) ? $app_config['settings']['limit_registration']['value'] : '';

		return $limit_registration === 'on';
	}


	/**
	 * API response
	 * @param $user_data
	 *
	 * @return bool|mixed
	 * @throws Exception
	 */
	private function llar_api_response( $user_data )
	{
		return self::$cloud_app->acl_check( array(
			'ip'        => Helpers::get_all_ips(),
			'login'     => $user_data,
			'gateway'   => Helpers::detect_gateway(),
		) );
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
		// This method allows integrations to check registration via API. 
		// Only trusted integration classes may call it.
		if ( null !== $integration && $integration instanceof BaseIntegration ) {
			// Additional security check: verify the class is in the correct namespace
			// This prevents external code from extending BaseIntegration and calling this method
			$integration_class = get_class( $integration );
			$expected_namespace = 'LLAR\Core\Integrations\\';

			// Check if the class is in the expected namespace
			if ( strpos( $integration_class, $expected_namespace ) !== 0 ) {
				// Class is not in the trusted namespace, deny the request
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( sprintf( 'LLAR: Security check failed - integration class %s is not in trusted namespace', $integration_class ) );
				}
				return array( 'result' => 'deny' );
			}

			$response = $this->llar_api_response( $user_data );

			return $response;
		}

		// If no integration object is provided, deny the request
		return array( 'result' => 'deny' );

	}


	/**
	 * Register new user standard WP
	 */
	public function llar_submit_login_form_register()
	{
		if ( ! $this->is_limit_registration() ) {
			return;
		}

		if ( empty( $_POST['user_login'] ) && empty( $_POST['user_email'] ) ) {
			return;
		}

		$user_login = $_POST['user_login'];
		$user_email = $_POST['user_email'];

		// Only if both fields are empty we exit the check
		if ( ( empty( $user_login ) || ! validate_username( $user_login ) )  && ( empty( $user_email ) || ! is_email( $user_email ) ) ) {
			return;
		}

		$user_login_sanitize = sanitize_user( $_POST['user_login'] );
		$user_email_sanitize = sanitize_email( $_POST['user_email'] );

		// Check any non-empty
		$check_combo = ! empty( $user_login_sanitize ) ? $user_login_sanitize : $user_email_sanitize;

		$response = $this->llar_api_response( $check_combo );

		// If $user_login is not empty, we will also check $user_email
		if ( ! empty( $user_login_sanitize ) && $response['result'] !== 'deny' ) {

			if ( empty( $user_email ) || ! is_email( $user_email ) ) {
				return;
			}

			$response = $this->llar_api_response( $user_email_sanitize );
		}

		if ( $response['result'] === 'deny' ) {

			// Set variables to empty to prevent Wordpress from accessing the database
			$_POST['user_login'] = '';
			$_POST['user_email'] = '';

			// Set the marker and the error
			$this->user_blocking = true;
			$this->error_messages = __( 'Registration is currently disabled.', 'limit-login-attempts-reloaded' );
		}
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
		// Checking the marker and the presence of empty variables
		if ( $this->user_blocking && ( empty( $sanitized_user_login ) && empty( $user_email ) ) ) {
			$errors->remove('empty_username');
			$errors->remove('empty_email');
			$errors->add( 'user_blocking', $this->error_messages );
		}

		return $errors;
	}
}

