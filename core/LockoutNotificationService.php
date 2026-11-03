<?php
/**
 * Lockout Notification Service
 *
 * @package LimitLoginAttempts
 * @since 3.3.0
 */

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

		$notify_email_after = (int) Config::get( 'notify_email_after' );
		$notify_email_after = max( 1, $notify_email_after );

		/* check if we are at the right nr to do notification */
		if (
			isset( $retries[ $ip ] )
			&& 0 != ( (int) floor( (int) $retries[ $ip ] / Config::get( 'allowed_retries' ) ) % $notify_email_after )
		) {
			return;
		}

		/* Format message. First current lockout duration */
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

		$site_domain = str_replace( array( 'http://', 'https://' ), '', home_url() );

		$subject = sprintf(
			/* translators: 1: site domain, 2: IP address */
			__( '%1$s - Login blocked from %2$s', 'limit-login-attempts-reloaded' ),
			esc_html( $site_domain ),
			esc_html( $ip )
		);

		/* All copy, URLs and inline styles come from the presenter; this
		 * method only resolves the business facts and sends. */
		$view = LockoutEmailPresenter::get_view_vars(
			array(
				'ip'                  => $ip,
				'user'                => $user,
				'count'               => $count,
				'when'                => $when,
				'site_domain'         => $site_domain,
				'dashboard_url'       => $this->options_page_provider->get_options_page_uri(),
				'manage_settings_url' => $this->options_page_provider->get_options_page_uri( 'settings' ),
			)
		);
		$preview_text = $view['preview_text'];

		ob_start();
		include LLA_PLUGIN_DIR . 'views/emails/email-preview-text.php';
		include LLA_PLUGIN_DIR . 'views/emails/failed-login-content.php';
		$email_body = ob_get_clean();

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
