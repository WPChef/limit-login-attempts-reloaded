<?php

namespace LLAR\Core;

use LLAR\Core\Config;
use LLAR\Core\LimitLoginAttempts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the view data for the successful login attempts widget
 * (views/app-widgets/login-attempts.php) rendered on the dashboard
 * tab and the custom logs tab.
 */
class LoginAttemptsWidgetPresenter {

	/**
	 * @var LimitLoginAttempts
	 */
	private $plugin;

	/**
	 * @param LimitLoginAttempts $plugin Plugin facade.
	 */
	public function __construct( LimitLoginAttempts $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * View vars for the login attempts widget template.
	 *
	 * @param bool $is_tab_dashboard Whether the widget renders on the dashboard tab.
	 *
	 * @return array
	 */
	public function get_view_vars( $is_tab_dashboard ) {
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
}
