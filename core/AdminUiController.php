<?php

namespace LLAR\Core;

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
		if ( ! $this->plugin->has_capability ) return;

		global $submenu;

		if ( Config::get( 'show_top_level_menu_item' ) ) {

			add_menu_page(
				'Limit Login Attempts',
				'Limit Login Attempts' . $this->menu_alert_icon(),
				LimitLoginAttempts::$capabilities,
				$this->options_page_slug,
				array( $this, 'options_page' ),
				'data:image/svg+xml;base64,' . base64_encode( $this->plugin->get_svg_logo_content() ),
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
				Config::update('admin_notify_email',        sanitize_email( $_POST['admin_notify_email'] ) );

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

}
