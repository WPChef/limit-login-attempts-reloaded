<?php

namespace LLAR\Core;

use LLAR\Core\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the view data for the Micro Cloud free trial modal (views/micro-cloud-modal.php).
 *
 * Outbound "Get Started" / "14 Day Trial" CTAs redirect to the marketing landing.
 * This modal is used on return when a valid setup_code + token are present.
 */
class MicroCloudModalPresenter {

	/**
	 * Request-scoped cache so dashboard CTA and modal share one landing URL/token.
	 *
	 * @var array
	 */
	private static $view_vars_by_tab = array();

	/**
	 * View vars for the Micro Cloud modal template.
	 *
	 * @param string $tab Current plugin tab (dashboard|settings).
	 * @return array
	 */
	public function get_view_vars( $tab = 'dashboard' ) {
		$tab = sanitize_key( $tab );
		if ( '' === $tab ) {
			$tab = 'dashboard';
		}
		if ( isset( self::$view_vars_by_tab[ $tab ] ) ) {
			return self::$view_vars_by_tab[ $tab ];
		}

		$admin_email = TrialLanding::resolve_email();
		$url_site    = parse_url( ( is_multisite() ) ? network_site_url() : site_url(), PHP_URL_HOST );

		$trial_return = TrialLanding::parse_return_request();
		$can_activate = ! empty( $trial_return['can_activate'] )
			&& 'dashboard' === $trial_return['context']
			&& empty( Config::get( 'app_setup_code' ) );

		$landing_token = '';
		$landing_url   = '';
		if ( empty( Config::get( 'app_setup_code' ) ) ) {
			$landing_token = TrialLanding::create_token( 'dashboard' );
			$landing_url   = TrialLanding::build_url(
				TrialLanding::INFO_ID_DASHBOARD,
				$admin_email,
				TrialLanding::dashboard_return_url( $tab ),
				'dashboard',
				$landing_token
			);
		}

		self::$view_vars_by_tab[ $tab ] = array(
			'should_show'       => empty( Config::get( 'app_setup_code' ) ),
			'admin_email'       => $admin_email,
			'url_site'          => $url_site,
			'landing_url'       => $landing_url,
			'auto_activate'     => array(
				'enabled'    => $can_activate,
				'setup_code' => $can_activate ? $trial_return['setup_code'] : '',
			),
			'title'             => __( 'Start your 14 day free trial', 'limit-login-attempts-reloaded' ),
			'description'       => __( 'Unlock full access to our premium features including our login firewall, IP Intelligence, and performance optimizer. No credit card required.', 'limit-login-attempts-reloaded' ),
			'description_add'   => __( 'When your 14 day free trial ends, the app automatically reverts to the free version. You may upgrade to one of our premium plans at any time to keep cloud protection.', 'limit-login-attempts-reloaded' ),
			'card_title'        => __( 'How To Activate Your Free Trial', 'limit-login-attempts-reloaded' ),
			'activating_text'   => __( 'Activating your free trial…', 'limit-login-attempts-reloaded' ),
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
			'dashboard_label'   => __( 'Great!', 'limit-login-attempts-reloaded' ),
		);

		return self::$view_vars_by_tab[ $tab ];
	}
}
