<?php

namespace LLAR\Core;

use LLAR\Core\Interfaces\OptionsPageUriProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles lockout notifications (email and logging).
 */
class LockoutNotificationService {

	/**
	 * @var IpAddressResolver
	 */
	private $ip_resolver;

	/**
	 * @var OptionsPageUriProvider
	 */
	private $options_page_provider;

	/**
	 * @param IpAddressResolver      $ip_resolver           IP resolver.
	 * @param OptionsPageUriProvider $options_page_provider Options page URI provider.
	 */
	public function __construct( IpAddressResolver $ip_resolver, OptionsPageUriProvider $options_page_provider ) {
		$this->ip_resolver = $ip_resolver;
		$this->options_page_provider = $options_page_provider;
	}

	/**
	 * Handle notification in event of lockout.
	 *
	 * @param mixed $user Username string or user object.
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

		if ( in_array( 'email', $args, true ) ) {
			$this->notify_email( $user );
		}
	}

	/**
	 * Email notification of lockout to admin (if configured).
	 *
	 * @param string $user Login username.
	 * @return void
	 */
	public function notify_email( $user ) {
		$ip      = $this->ip_resolver->get_address();
		$retries = Config::get( 'retries' );

		if ( ! is_array( $retries ) ) {
			$retries = array();
		}

		if (
			isset( $retries[ $ip ] )
			&& ( ( (int) $retries[ $ip ] / Config::get( 'allowed_retries' ) ) % Config::get( 'notify_email_after' ) ) != 0
		) {
			return;
		}

		if ( ! isset( $retries[ $ip ] ) ) {
			$count    = Config::get( 'allowed_retries' ) * Config::get( 'allowed_lockouts' );
			$lockouts = Config::get( 'allowed_lockouts' );
			$time     = round( Config::get( 'long_duration' ) / 3600 );
			$when     = sprintf( _n( '%d hour', '%d hours', $time, 'limit-login-attempts-reloaded' ), $time );
		} else {
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

		$res = $wpdb->get_col(
			$wpdb->prepare(
				"
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
		$blogname    = Helpers::use_local_options() ? get_option( 'blogname' ) : get_site_option( 'site_name' );
		$blogname    = htmlspecialchars_decode( $blogname, ENT_QUOTES );

		$plugin_data = get_plugin_data( LLA_PLUGIN_DIR . 'limit-login-attempts-reloaded.php' );

		$subject = sprintf(
			__( 'Failed login by IP %1$s %2$s', 'limit-login-attempts-reloaded' ),
			esc_html( $ip ),
			esc_html( $site_domain )
		);

		ob_start();
		include LLA_PLUGIN_DIR . 'views/emails/failed-login.php';
		$email_body = ob_get_clean();

		$current_url_label = preg_replace( '/^\/|\/$/', '', $_SERVER['REQUEST_URI'] );
		$current_url       = '';
		if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
			$referer = filter_var( $_SERVER['HTTP_REFERER'], FILTER_VALIDATE_URL );
			if ( false !== $referer ) {
				$current_url = esc_url_raw( $referer );
			}
		}
		if ( empty( $current_url ) ) {
			$current_url = get_site_url() . $_SERVER['REQUEST_URI'];
		}

		$placeholders = array(
			'{name}'              => $admin_name,
			'{domain}'            => $site_domain,
			'{attempts_count}'    => $count,
			'{lockouts_count}'    => $lockouts,
			'{ip_address}'        => esc_html( $ip ),
			'{ip_address_link}'   => esc_url( 'https://www.limitloginattempts.com/location/?ip=' . $ip ),
			'{username}'          => $user,
			'{blocked_duration}'  => $when,
			'{dashboard_url}'     => $this->options_page_provider->get_options_page_uri(),
			'{premium_url}'       => 'https://www.limitloginattempts.com/info.php?from=plugin-lockout-email&v=' . $plugin_data['Version'],
			'{llar_url}'          => 'https://www.limitloginattempts.com/?from=plugin-lockout-email&v=' . $plugin_data['Version'],
			'{unsubscribe_url}'   => $this->options_page_provider->get_options_page_uri( 'settings' ),
			'{current_url}'       => $current_url,
			'{current_url_label}' => $current_url_label,
		);

		$email_body = str_replace(
			array_keys( $placeholders ),
			array_values( $placeholders ),
			$email_body
		);

		Helpers::send_mail_with_logo( $admin_email, $subject, $email_body );
	}

	/**
	 * Logging of lockout (if configured).
	 *
	 * @param string $user_login Login username.
	 * @return void
	 */
	public function notify_log( $user_login ) {
		if ( ! $user_login ) {
			return;
		}

		$log    = $option = Config::get( Config::OPTION_LOGGED );
		$log    = is_array( $log ) ? $log : array();
		$ip     = $this->ip_resolver->get_address();

		if ( ! isset( $log[ $ip ] ) ) {
			$log[ $ip ] = array();
		}

		if ( ! isset( $log[ $ip ][ $user_login ] ) ) {
			$log[ $ip ][ $user_login ] = array( 'counter' => 0 );
		} elseif ( ! is_array( $log[ $ip ][ $user_login ] ) ) {
			$log[ $ip ][ $user_login ] = array( 'counter' => $log[ $ip ][ $user_login ] );
		}

		$log[ $ip ][ $user_login ]['counter']++;
		$log[ $ip ][ $user_login ]['date']    = time();
		$log[ $ip ][ $user_login ]['gateway'] = Helpers::detect_gateway();

		if ( $option === false ) {
			Config::add( 'logged', $log );
		} else {
			Config::update( Config::OPTION_LOGGED, $log );
		}
	}
}
