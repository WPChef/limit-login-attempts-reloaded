<?php

namespace LLAR\Core;

use LLAR\Core\Digest\DigestUiController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin menus, assets, and options page handling.
 */
class AdminUiController {

	/** @var LimitLoginAttempts */
	private $plugin;

	/** @var string */
	private $options_page_slug = 'limit-login-attempts';

	public function __construct( LimitLoginAttempts $plugin ) {
		$this->plugin = $plugin;
	}

	public function enqueue()
	{
		$plugin_data = get_plugin_data( LLA_PLUGIN_DIR . 'limit-login-attempts-reloaded.php' );

		wp_enqueue_style( 'lla-main', LLA_PLUGIN_URL . 'assets/css/limit-login-attempts.css', array(), $plugin_data['Version'] );

		if ( ! empty( $_REQUEST['page'] ) && $_REQUEST['page'] === $this->options_page_slug ) {

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
		if ( ! $this->plugin->has_capability ) return;

		add_submenu_page(
			'settings.php',
			'Limit Login Attempts',
			'Limit Login Attempts' . $this->menu_alert_icon(),
			LimitLoginAttempts::$capabilities,
			$this->options_page_slug,
			array( $this, 'options_page' ) );
	}

	private function get_submenu_items()
	{
		$active_app        = Config::get( Config::OPTION_ACTIVE_APP );
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
			$name_item  = $is_local_empty_setup_code ? __( 'Free Trial', 'limit-login-attempts-reloaded' ) : __( 'Premium', 'limit-login-attempts-reloaded' );
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
		if ( ! $this->plugin->has_capability ) return;

		global $submenu;

		if ( Config::get( 'show_top_level_menu_item' ) ) {

			add_menu_page(
				'Limit Login Attempts',
				'Limit Login Attempts' . $this->menu_alert_icon(),
				LimitLoginAttempts::$capabilities,
				$this->options_page_slug,
				array( $this, 'options_page' ),
				'data:image/svg+xml;base64,' . base64_encode( $this->get_svg_logo_content() ),
				74
			);

			$is_cloud_app_enabled = Config::get( Config::OPTION_ACTIVE_APP ) === 'custom';
			$submenu_items = $this->get_submenu_items();

			$index = 1;
			foreach ( $submenu_items as $item ) {
				add_submenu_page(
					$this->options_page_slug,
					$item['name'],
					$item['name'],
					LimitLoginAttempts::$capabilities,
					$this->options_page_slug . $item['url'],
					array( $this, 'options_page' )
				);

				if ( ! empty ( $_GET['page'] ) && $_GET['page'] === $this->options_page_slug && ! empty( $_GET['tab'] ) && $_GET['tab'] === $item['id'] ) {
					$submenu[$this->options_page_slug][$index][4] = 'current';
				}
				$index++;
			}

			remove_submenu_page( $this->options_page_slug, $this->options_page_slug );

			if ( ! $is_cloud_app_enabled && isset( $submenu[$this->options_page_slug] ) ) {
				// Premium is the last submenu item (Dashboard, Settings, 2FA, Logs, Debug, Help, Premium).
				$submenu_keys = array_keys( $submenu[$this->options_page_slug] );
				$premium_key  = end( $submenu_keys );
				$submenu[$this->options_page_slug][$premium_key][4] =
					! empty( $submenu[$this->options_page_slug][$premium_key][4] )
						? $submenu[$this->options_page_slug][$premium_key][4] . ' llar-submenu-premium-item'
						: 'llar-submenu-premium-item';
			}

		} else {

			add_options_page(
				'Limit Login Attempts',
				'Limit Login Attempts' . $this->menu_alert_icon(),
				LimitLoginAttempts::$capabilities,
				$this->options_page_slug,
				array( $this, 'options_page' )
			);
		}
	}

	public function admin_bar_menu( $admin_bar )
	{

		if ( ! $this->plugin->has_capability ) return;

		$root_item_id = 'llar-root';
		$href = $this->plugin->get_options_page_uri();

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
			|| Config::get( Config::OPTION_ACTIVE_APP ) !== 'local'
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
			$uri = network_admin_url( 'settings.php?page=' . $this->options_page_slug );
		} else {
			$uri = admin_url( 'admin.php?page=' . $this->options_page_slug );
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

		if ( ! LimitLoginAttempts::$cloud_app ) {
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

			LimitLoginAttempts::$cloud_app->request( 'login', 'post', $data );
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
		if ( ! empty( $_GET['tab'] ) && $_GET['tab'] === 'settings' ) {
			Config::use_local_options( ! is_network_admin() );
		}

		$this->plugin->get_local_lockout()->cleanup();

		if ( ! empty( $_POST ) ) {

			check_admin_referer( 'limit-login-attempts-options' );

			if ( is_network_admin() ) {

				Config::update( 'allow_local_options', ! empty( $_POST['allow_local_options'] ) );
			} elseif ( Helpers::is_network_mode() ) {

				Config::update( 'use_local_options', empty( $_POST['use_global_options'] ) );
			}

			/* Should we clear log? */
			if ( isset( $_POST[ 'clear_log' ] ) ) {

				Config::update( Config::OPTION_LOGGED, array() );
				$this->plugin->show_message( __( 'Cleared IP log', 'limit-login-attempts-reloaded' ) );
			}

			/* Should we reset counter? */
			if ( isset( $_POST[ 'reset_total' ] ) ) {

				Config::update( 'lockouts_total', 0 );
				$this->plugin->show_message( __( 'Reset lockout count', 'limit-login-attempts-reloaded' ) );
			}

			/* Should we restore current lockouts? */
			if ( isset( $_POST[ 'reset_current' ] ) ) {

				Config::update( Config::OPTION_LOCKOUTS, array() );
				$this->plugin->show_message( __( 'Cleared current lockouts', 'limit-login-attempts-reloaded' ) );
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

							$this->plugin->show_message( sprintf ( __( 'The %s IP range is invalid', 'limit-login-attempts-reloaded' ), $ip ) );
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

				$this->plugin->show_message( __( 'Settings saved.', 'limit-login-attempts-reloaded' ) );

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
				$admin_notify_email = isset( $_POST['admin_notify_email'] ) ? sanitize_email( wp_unslash( $_POST['admin_notify_email'] ) ) : '';
				if ( empty( $admin_notify_email ) ) {
					$this->plugin->show_message( __( 'Please enter a valid admin notification email.', 'limit-login-attempts-reloaded' ), true );
					$admin_notify_email = Config::get( 'admin_notify_email' );
				}
				Config::update('admin_notify_email',        $admin_notify_email );
				DigestUiController::save_settings_from_request();

				Config::update( Config::OPTION_ACTIVE_APP, sanitize_text_field( $_POST['active_app'] ) );

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

				if ( ! empty( $_POST['llar_app_settings'] ) && LimitLoginAttempts::$cloud_app ) {

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
				$this->plugin->show_message( __( 'Settings saved.', 'limit-login-attempts-reloaded' ) );
				$this->cloud_app_init();
			} elseif ( isset( $_POST['llar_update_mfa_settings'] ) ) {
				// Handle MFA settings submission via controller (capability checked inside)
				if ( $this->mfa_controller ) {
					$show_popup = $this->mfa_controller->handle_settings_submission();
					if ( ! $show_popup ) {
						$this->plugin->show_message( __( 'Settings saved.', 'limit-login-attempts-reloaded' ) );
					}
				}
			}
		}

		// Prepare roles data for MFA tab (before including view to ensure data is ready)
		// Check if we're on MFA tab (GET or POST with tab parameter, or default after form submit)
		$current_tab = 'settings';
		if ( isset( $_GET['tab'] ) && in_array( $_GET['tab'], LimitLoginAttempts::$allowed_tabs ) ) {
			$current_tab = sanitize_text_field( $_GET['tab'] );
		} elseif ( isset( $_POST['llar_update_mfa_settings'] ) ) {
			// After MFA form submit, we're still on MFA tab
			$current_tab = 'mfa';
		}

		$lockout_notify_items = explode( ',', (string) Config::get( 'lockout_notify' ) );
		$email_checked = in_array( 'email', $lockout_notify_items, true );
		$digest_notification_checkboxes = DigestUiController::get_notification_checkboxes();

		// MFA tab data comes from get_settings_for_view() (single source in MfaSettingsManager)
		include_once LLA_PLUGIN_DIR . 'views/options-page.php';
	}

	/**
	 * View data for views/tab-dashboard.php.
	 *
	 * All copy, URLs and state flags for the dashboard tab come from here;
	 * the view only assembles markup.
	 *
	 * @param string $active_app          Active app slug ('local'|'custom').
	 * @param bool   $is_active_app_custom Whether the cloud app is active.
	 * @param string $block_sub_group     Cloud plan name.
	 * @param bool   $is_exhausted        Cloud quota exhausted flag.
	 *
	 * @return array
	 */
	public function get_dashboard_view_vars( $active_app, $is_active_app_custom, $block_sub_group, $is_exhausted ) {
		$setup_code         = Config::get( 'app_setup_code' );
		$api_stats          = $is_active_app_custom ? LimitLoginAttempts::$cloud_app->stats() : false;
		$info_has_valid_data = $is_active_app_custom ? $this->plugin->info_has_valid_data() : false;
		$upgrade_premium_url = $is_active_app_custom ? $this->plugin->info_upgrade_url() : '';

		$chart_circle_data = $this->plugin->get_failed_attempts_circle_data(
			$is_active_app_custom,
			$is_exhausted,
			$block_sub_group,
			$setup_code,
			$upgrade_premium_url,
			$api_stats,
			$info_has_valid_data
		);

		$url_site = is_multisite() ? network_site_url() : site_url();

		// --- Login Security Checklist state ---
		$lockout_notify = explode( ',', Config::get( 'lockout_notify' ) );
		$email_checked  = in_array( 'email', $lockout_notify ) ? ' checked disabled' : '';
		$email_checked  = $is_active_app_custom ? ' checked disabled' : $email_checked;

		$is_checklist = Config::get( 'checklist' ) === 'true' ? ' checked disabled' : '';

		$min_paid_plan     = 'Personal';
		$min_plan          = 'Premium';
		$plans             = $this->plugin->array_name_plans();
		$current_plan_rate = isset( $plans[ $block_sub_group ] ) ? $plans[ $block_sub_group ] : 0;
		$upgrade_premium   = ( $is_active_app_custom && $current_plan_rate >= $plans[ $min_paid_plan ] ) ? ' checked' : '';

		$checked_block_by_country = Config::get( 'block_by_country' ) === 'true' ? ' checked disabled' : '';
		$block_by_country         = $block_sub_group ? $this->plugin->info_block_by_country() : false;
		$block_by_country_disabled = $block_sub_group ? '' : ' disabled';
		$is_by_country            = $block_by_country ? $checked_block_by_country : $block_by_country_disabled;

		$is_auto_update_choice = ( Helpers::is_auto_update_enabled() && ! Helpers::is_block_automatic_update_disabled() ) ? ' checked' : '';

		$app_config   = Config::get( 'app_config' );
		$full_log_url = ! empty( $app_config['key'] ) ? 'https://my.limitloginattempts.com/logs?key=' . esc_attr( $app_config['key'] ) : false;

		$list_name = __( 'Deny/Allow countries', 'limit-login-attempts-reloaded' );
		if ( ! $is_active_app_custom || ( $is_active_app_custom && ( $plans[ $block_sub_group ] === $plans[ $min_plan ] ) ) ) {
			$list_name = __( 'Deny/Allow countries (Premium+ Users)', 'limit-login-attempts-reloaded' );
		}

		$checklist = array(
			'heading' => __( 'Login Security Checklist', 'limit-login-attempts-reloaded' ),
			'desc'    => __( 'Recommended tasks to greatly improve the security of your website.', 'limit-login-attempts-reloaded' ),
			'full_log_url' => $full_log_url,
			'items'   => array(
				array(
					'name'    => 'lockout_notify_email',
					'checked' => $email_checked,
					'disabled_attr' => '',
					'label'   => __( 'Enable Email Notifications', 'limit-login-attempts-reloaded' ),
					'list_add' => '',
					'desc'     => sprintf(
						__( '<a class="link__style_unlink llar_turquoise" href="%s">Enable email notifications</a> to receive timely alerts and updates via email.', 'limit-login-attempts-reloaded' ),
						'/wp-admin/admin.php?page=limit-login-attempts&tab=settings#llar_lockout_notify'
					),
				),
				array(
					'name'    => 'strong_account_policies',
					'checked' => $is_checklist,
					'disabled_attr' => '',
					'label'   => __( 'Implement strong account policies', 'limit-login-attempts-reloaded' ),
					'list_add' => __( 'Check when done.', 'limit-login-attempts-reloaded' ),
					'desc'     => sprintf(
						__( '<a class="link__style_unlink llar_turquoise" href="%s" target="_blank">Read our guide</a> on implementing and enforcing strong password policies in your organization.', 'limit-login-attempts-reloaded' ),
						'https://www.limitloginattempts.com/info.php?id=1'
					),
				),
				array(
					'name'    => 'block_by_country',
					'checked' => $is_by_country . $block_by_country_disabled,
					'disabled_attr' => '',
					'label'   => $list_name,
					'list_add' => __( 'Check when done.', 'limit-login-attempts-reloaded' ),
					'desc'     => sprintf(
						__( '<a class="link__style_unlink llar_turquoise" href="%s" target="_blank">Allow or Deny countries</a> to ensure only legitimate users login.', 'limit-login-attempts-reloaded' ),
						$block_by_country
							? $url_site . '/wp-admin/admin.php?page=limit-login-attempts&tab=logs-custom'
							: 'https://www.limitloginattempts.com/info.php?id=2'
					),
				),
				array(
					'name'    => 'auto_update_choice',
					'checked' => $is_auto_update_choice,
					'disabled_attr' => ' disabled',
					'label'   => __( 'Turn on plugin auto-updates', 'limit-login-attempts-reloaded' ),
					'list_add' => '',
					'desc'     => ! empty( $is_auto_update_choice )
						? __( 'Enable automatic updates to ensure that the plugin stays current with the latest software patches and features.', 'limit-login-attempts-reloaded' )
						: __( '<a class="link__style_unlink llar_turquoise" href="#llar_auto_update_choice">Enable automatic updates</a> to ensure that the plugin stays current with the latest software patches and features.', 'limit-login-attempts-reloaded' ),
				),
				array(
					'name'    => 'upgrade_premium',
					'checked' => ' ' . $upgrade_premium,
					'disabled_attr' => ' disabled',
					'label'   => __( 'Upgrade to Premium', 'limit-login-attempts-reloaded' ),
					'list_add' => '',
					'desc'     => ( $is_active_app_custom && ( $current_plan_rate >= $plans[ $min_paid_plan ] ) )
						? __( 'Upgrade to our premium version for advanced protection.', 'limit-login-attempts-reloaded' )
						: sprintf(
							__( '<a class="link__style_unlink llar_turquoise" href="%s" target="_blank">Upgrade to our premium</a> version for advanced protection.', 'limit-login-attempts-reloaded' ),
							$is_active_app_custom
								? add_query_arg( 'id', '5', $this->plugin->info_upgrade_url() )
								: 'https://www.limitloginattempts.com/info.php?id=3'
						),
				),
			),
		);

		return array(
			'setup_code'           => $setup_code,
			'api_stats'            => $api_stats,
			'upgrade_premium_url'  => $upgrade_premium_url,
			'show_onboarding'      => ! $is_active_app_custom && empty( $setup_code ),
			'chart_circle_data'    => $chart_circle_data,
			'show_trial_block'           => ! $is_active_app_custom && empty( $setup_code ),
			'show_premium_disabled_block' => ! $is_active_app_custom && ! empty( $setup_code ),
			'trial_block'         => array(
				'title'     => __( 'Experience Premium Free for 14 Days', 'limit-login-attempts-reloaded' ),
				'bullets'   => array(
					__( 'No credit card required. Automatically revert to the free version when the trial is complete.', 'limit-login-attempts-reloaded' ),
					__( 'Unlock advanced security features including Cloud Protection, Block by Country, Login Firewall, and Successful Login Logs', 'limit-login-attempts-reloaded' ),
					__( 'Stop brute force attacks before they reach your login page with one of the strongest login protection systems for WordPress', 'limit-login-attempts-reloaded' ),
				),
				'cta_title' => __( '14 Day Trial', 'limit-login-attempts-reloaded' ),
				'cta_label' => __( '14 Day Trial', 'limit-login-attempts-reloaded' ),
			),
			'premium_disabled_block' => array(
				'title'        => __( 'Premium Protection Disabled', 'limit-login-attempts-reloaded' ),
				'desc'         => __( 'As a free user, your local server is absorbing the traffic brought on by brute force attacks, potentially slowing down your website. Upgrade to Premium today to outsource these attacks through our cloud app, and slow down future attacks with advanced throttling.', 'limit-login-attempts-reloaded' ),
				'url'          => 'https://www.limitloginattempts.com/upgrade/?from=plugin-dashboard-cta',
				'cta_title'    => 'Upgrade To Premium',
				'button_label' => __( 'Upgrade to Premium', 'limit-login-attempts-reloaded' ),
			),
			'quick_links'         => array(
				array(
					'icon'   => 'icon-exploitation.png',
					'href'   => $this->get_options_page_uri( 'logs-' . $active_app ),
					'target' => '',
					'title'  => __( 'Tools', 'limit-login-attempts-reloaded' ),
					'desc'   => __( 'View lockouts logs, block or whitelist usernames or IPs, and more.', 'limit-login-attempts-reloaded' ),
				),
				array(
					'icon'   => 'icon-help.png',
					'href'   => 'https://www.limitloginattempts.com/info.php?from=plugin-dashboard-help',
					'target' => ' target="_blank"',
					'title'  => __( 'Help', 'limit-login-attempts-reloaded' ),
					'desc'   => __( 'Find the documentation and help you need.', 'limit-login-attempts-reloaded' ),
				),
				array(
					'icon'   => 'icon-web.png',
					'href'   => $this->get_options_page_uri( 'settings' ),
					'target' => '',
					'title'  => __( 'Global Options', 'limit-login-attempts-reloaded' ),
					'desc'   => __( 'Many options such as notifications, alerts, premium status, and more.', 'limit-login-attempts-reloaded' ),
				),
			),
			'checklist'           => $checklist,
		);
	}

	/**
	 * View data for views/onboarding-popup.php.
	 *
	 * @return array
	 */
	public function get_onboarding_popup_view_vars() {
		$admin_notify_email = Config::get( 'admin_notify_email' );
		$admin_email        = ! empty( $admin_notify_email )
			? $admin_notify_email
			: ( ( ! is_multisite() ) ? get_option( 'admin_email' ) : get_site_option( 'admin_email' ) );

		return array(
			'should_show' => ! Config::get( 'onboarding_popup_shown' ) && ! Config::get( 'app_setup_code' ),
			'admin_email' => $admin_email,
			'steps'       => array(
				__( 'Welcome', 'limit-login-attempts-reloaded' ),
				__( 'Notifications', 'limit-login-attempts-reloaded' ),
				__( 'Free Trial', 'limit-login-attempts-reloaded' ),
				__( 'Completion', 'limit-login-attempts-reloaded' ),
			),
			'step1'       => array(
				'title'            => __( 'Welcome', 'limit-login-attempts-reloaded' ),
				'subtitle'         => __( 'Before you start using the plugin, please complete onboarding (It only takes a minute).', 'limit-login-attempts-reloaded' ),
				'setup_title'      => __( 'Already using Premium? Add your Setup Code', 'limit-login-attempts-reloaded' ),
				'setup_placeholder' => __( 'Your Setup Code', 'limit-login-attempts-reloaded' ),
				'setup_button'     => __( 'Activate', 'limit-login-attempts-reloaded' ),
				'setup_desc'       => __( 'The Setup Code can be found in your email confirmation.', 'limit-login-attempts-reloaded' ),
				'premium_title'    => __( 'Not using Premium yet?', 'limit-login-attempts-reloaded' ),
				'premium_pitch'    => sprintf(
					/* translators: %1$s: opening span tag, %2$s: closing span tag */
					esc_html__( 'With Premium, your site becomes part of a powerful, real-time threat intelligence network built on the data of %1$s over 80,000 WordPress sites. %2$s That means you\'re not just blocking attackers after they strike — you\'re %1$s preventing them from making legitimate login attempts. %2$s', 'limit-login-attempts-reloaded' ),
					'<span class="llar_turquoise">',
					'</span>'
				),
				'premium_features' => array(
					array( 'icon' => 'icon-shield.png', 'text' => __( 'Cloud-based login protection with dynamic IP blocklists', 'limit-login-attempts-reloaded' ) ),
					array( 'icon' => 'icon-lock.png', 'text' => __( '97% of brute force attacks blocked before they begin', 'limit-login-attempts-reloaded' ) ),
					array( 'icon' => 'icon-reload.png', 'text' => __( 'Real-time threat updates powered by our global network', 'limit-login-attempts-reloaded' ) ),
					array( 'icon' => 'icon-web.png', 'text' => __( 'Country & IP controls for greater control', 'limit-login-attempts-reloaded' ) ),
					array( 'icon' => 'icon-dollar.png', 'text' => __( 'Powerful login security from just $0.10/day - built for sites of all sizes', 'limit-login-attempts-reloaded' ) ),
				),
				'plans_url'        => 'https://www.limitloginattempts.com/info.php?from=plugin-onboarding-plans',
				'plans_cta'        => __( 'Yes, show me plan options', 'limit-login-attempts-reloaded' ),
				'skip_cta'         => __( 'No thank you, let\'s continue', 'limit-login-attempts-reloaded' ),
			),
			'step2'       => array(
				'title'            => __( 'Notification Settings', 'limit-login-attempts-reloaded' ),
				'email_placeholder' => __( 'Your email', 'limit-login-attempts-reloaded' ),
				'desc'             => __( 'This email will receive notifications of unauthorized access to your website. You may turn this off in your settings.', 'limit-login-attempts-reloaded' ),
				'checkbox_label'   => __( 'Sign me up for the LLAR newsletter to receive important security alerts, plugin updates, and helpful guides.', 'limit-login-attempts-reloaded' ),
				'continue_label'   => __( 'Continue', 'limit-login-attempts-reloaded' ),
				'skip_label'       => __( 'Skip', 'limit-login-attempts-reloaded' ),
			),
			'step3'       => array(
				'title'            => __( 'Unlock Premium FREE for 14 Days', 'limit-login-attempts-reloaded' ),
				'subtitle'         => __( 'No Credit Card Required', 'limit-login-attempts-reloaded' ),
				'pitch'            => wp_kses_post(
					sprintf(
						__( 'Unlock advanced security features including %1$sCloud Protection, Block by Country, Login Firewall, Successful Login Logs, and much more!%2$s', 'limit-login-attempts-reloaded' ),
						'<strong>',
						'</strong>'
					)
				),
				'paragraphs_html'  => esc_html__( 'These powerful tools help stop brute force attacks before they happen, protecting your WordPress login from malicious bots and automated attacks.', 'limit-login-attempts-reloaded' )
					. "                <br><br>\n\t\t\t\t" . esc_html__( 'Experience the strongest login protection available for WordPress and see the difference premium security can make.', 'limit-login-attempts-reloaded' )
					. "                <br><br>\n\t\t\t\t" . esc_html__( 'You can return to the free version at any time.', 'limit-login-attempts-reloaded' ),
				'cta'              => __( 'Would you like to start your free trial?', 'limit-login-attempts-reloaded' ),
				'yes_label'        => __( 'Yes', 'limit-login-attempts-reloaded' ),
				'no_label'         => __( 'No', 'limit-login-attempts-reloaded' ),
				'terms'            => sprintf(
					/* translators: %1$s: opening link tag, %2$s: closing link tag */
					esc_html__( 'We\'ll send you instructions via email to complete setup. You may opt-out of this program at any time. You accept our %1$s terms of service %2$s by participating in this program.', 'limit-login-attempts-reloaded' ),
					'<a class="link__style_color_inherit llar_turquoise" href="https://www.limitloginattempts.com/terms/" target="_blank">',
					'</a>'
				),
			),
			'step4'       => array(
				'title'        => __( 'Thank you for completing the setup', 'limit-login-attempts-reloaded' ),
				'button_label' => __( 'Go To Dashboard', 'limit-login-attempts-reloaded' ),
			),
		);
	}

	/**
	 * View data for views/micro-cloud-modal.php.
	 *
	 * @return array
	 */
	public function get_micro_cloud_modal_view_vars() {
		$admin_email = ( ! is_multisite() ) ? get_option( 'admin_email' ) : get_site_option( 'admin_email' );
		$url_site    = parse_url( ( is_multisite() ) ? network_site_url() : site_url(), PHP_URL_HOST );

		return array(
			'should_show'       => ! Config::get( 'app_setup_code' ),
			'admin_email'       => $admin_email,
			'url_site'          => $url_site,
			'title'             => __( 'Start your 14 day free trial', 'limit-login-attempts-reloaded' ),
			'description'       => __( 'Unlock full access to our premium features including our login firewall, IP Intelligence, and performance optimizer. No credit card required.', 'limit-login-attempts-reloaded' ),
			'description_add'   => __( 'When your 14 day free trial ends, the app automatically reverts to the free version. You may upgrade to one of our premium plans at any time to keep cloud protection.', 'limit-login-attempts-reloaded' ),
			'card_title'        => __( 'How To Activate Your Free Trial', 'limit-login-attempts-reloaded' ),
			'email_desc'        => __( 'Please enter the email that will receive activation confirmation', 'limit-login-attempts-reloaded' ),
			'email_placeholder' => __( 'Your email', 'limit-login-attempts-reloaded' ),
			'consent'           => sprintf(
				__( 'I consent to registering my domain name <b>%s</b> with the Limit Login Attempts Reloaded cloud service.', 'limit-login-attempts-reloaded' ),
				$url_site
			),
			'continue_label'    => __( 'Continue', 'limit-login-attempts-reloaded' ),
			'terms'             => sprintf(
				__( 'By signing up you agree to our <a href="%s" class="llar_turquoise">terms of service</a> and <a href="%s" class="llar_turquoise">privacy policy.</a>', 'limit-login-attempts-reloaded' ),
				'https://www.limitloginattempts.com/terms/',
				'https://www.limitloginattempts.com/privacy-policy/'
			),
			'error_message'     => __( 'The server is not working, try again later', 'limit-login-attempts-reloaded' ),
			'success_text'      => __( 'Your free trial has been activated!', 'limit-login-attempts-reloaded' ),
			'dashboard_label'   => __( 'Go To Dashboard', 'limit-login-attempts-reloaded' ),
		);
	}

	/**
	 * View data for views/app-widgets/login-attempts.php.
	 *
	 * @param bool $is_tab_dashboard Whether the widget renders on the dashboard tab.
	 *
	 * @return array
	 */
	public function get_login_attempts_widget_view_vars( $is_tab_dashboard ) {
		$active_app            = ( Config::get( Config::OPTION_ACTIVE_APP ) === 'custom' && LimitLoginAttempts::$cloud_app ) ? 'custom' : 'local';
		$is_active_app_custom  = $active_app === 'custom';
		$upgrade_premium_url   = $is_active_app_custom ? $this->plugin->info_upgrade_url() : '';

		return array(
			'is_tab_dashboard'      => $is_tab_dashboard,
			'is_active_app_custom'  => $is_active_app_custom,
			'upgrade_premium_url'   => $upgrade_premium_url,
			'limit'                 => $is_tab_dashboard ? 5 : 10,
			'title'                 => __( 'Successful Login Attempts', 'limit-login-attempts-reloaded' ),
			'view_more_label'       => __( ' View more', 'limit-login-attempts-reloaded' ),
			'view_more_url'         => '/wp-admin/admin.php?page=limit-login-attempts&tab=logs-custom',
			'headers'               => array(
				__( 'Time', 'limit-login-attempts-reloaded' ),
				__( 'Login', 'limit-login-attempts-reloaded' ),
				__( 'IP', 'limit-login-attempts-reloaded' ),
				__( 'Role', 'limit-login-attempts-reloaded' ),
				'',
			),
			'load_more_label'       => __( 'Load older events', 'limit-login-attempts-reloaded' ),
			'blur_title'            => __( 'View a complete history of successful logins for your WordPress account', 'limit-login-attempts-reloaded' ),
			'blur_description'      => __( 'All logs are stored in the cloud to ensure malicious users are unable to delete or manipulate site login data.', 'limit-login-attempts-reloaded' ),
			'blur_footer'           => sprintf(
				__( 'This feature is only available for<br><a class="link__style_unlink llar_turquoise" href="%s">Premium</a> users.', 'limit-login-attempts-reloaded' ),
				'/wp-admin/admin.php?page=limit-login-attempts&tab=premium'
			),
		);
	}

	/**
	 * Render an admin notice view by key (e.g. 'auto-update', 'mfa-no-ssl').
	 *
	 * @param string $notice_key Notice identifier.
	 * @param array  $args       Variables to pass to the notice view.
	 * @return void
	 */

	/**
	 * Magic method: delegate method calls to the plugin instance
	 * for methods used in views (options-page.php, tab-*.php, etc.)
	 * that were originally on LimitLoginAttempts.
	 *
	 * @param string $name      Method name.
	 * @param array  $arguments Method arguments.
	 * @return mixed
	 */
	public function __call( $name, $arguments ) {
		if ( method_exists( $this->plugin, $name ) ) {
			return call_user_func_array( array( $this->plugin, $name ), $arguments );
		}
		trigger_error(
			sprintf( 'Call to undefined method %s::%s()', __CLASS__, esc_html( $name ) ),
			E_USER_ERROR
		);
	}

	/**
	 * Magic isset: delegate to plugin instance for properties accessible via __get.
	 *
	 * @param string $name Property name.
	 * @return bool
	 */
	public function __isset( $name ) {
		return property_exists( $this, $name ) || property_exists( $this->plugin, $name );
	}

	/**
	 * Magic setter: delegate property writes to the plugin instance.
	 *
	 * @param string $name  Property name.
	 * @param mixed  $value Property value.
	 * @return void
	 */
	public function __set( $name, $value ) {
		if ( property_exists( $this, $name ) ) {
			$ref = new \ReflectionProperty( $this, $name );
			$ref->setAccessible( true );
			$ref->setValue( $this, $value );
			return;
		}
		$this->plugin->$name = $value;
	}

	/**
	 * Magic getter: delegate to plugin instance for methods/properties
	 * used in views that were originally on LimitLoginAttempts.
	 *
	 * Uses reflection to access private properties of both this controller
	 * and the plugin instance.
	 *
	 * @param string $name Property name.
	 * @return mixed
	 */
	public function __get( $name ) {
		// First check own properties (e.g. $plugin reference for views)
		if ( property_exists( $this, $name ) ) {
			$ref = new \ReflectionProperty( $this, $name );
			$ref->setAccessible( true );
			return $ref->getValue( $this );
		}
		// Then delegate to plugin instance
		if ( property_exists( $this->plugin, $name ) ) {
			$ref = new \ReflectionProperty( $this->plugin, $name );
			$ref->setAccessible( true );
			return $ref->getValue( $this->plugin );
		}
		trigger_error(
			sprintf( 'Undefined property: %s::$%s', __CLASS__, esc_html( $name ) ),
			E_USER_NOTICE
		);
	}

}
