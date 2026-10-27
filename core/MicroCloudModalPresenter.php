<?php

namespace LLAR\Core;

use LLAR\Core\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the view data for the Micro Cloud free trial modal (views/micro-cloud-modal.php).
 *
 * Outbound "Get Started" / trial CTAs redirect to the marketing landing.
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

		$trial_return = TrialLanding::parse_return_request();
		$can_activate = ! empty( $trial_return['can_activate'] )
			&& 'dashboard' === $trial_return['context']
			&& empty( Config::get( 'app_setup_code' ) );

		$landing_token = '';
		$landing_url   = '';
		if ( empty( Config::get( 'app_setup_code' ) ) ) {
			$landing_token = TrialLanding::create_token( 'dashboard' );
			// Fallback href only — the trial start popup collects the email and
			// consent before leaving, so no email ever sits in the markup.
			$landing_url = TrialLanding::dashboard_landing_url( $tab, $landing_token );
		}

		self::$view_vars_by_tab[ $tab ] = array(
			'should_show'       => empty( Config::get( 'app_setup_code' ) ),
			'landing_url'       => $landing_url,
			'auto_activate'     => array(
				'enabled'    => $can_activate,
				'setup_code' => $can_activate ? $trial_return['setup_code'] : '',
			),
			'title'             => sprintf(
				/* translators: %d: trial length in days. */
				__( 'Start your %d day free trial', 'limit-login-attempts-reloaded' ),
				LimitLoginAttempts::TRIAL_DAYS
			),
			'description'       => __( 'Unlock full access to our premium features including our login firewall, IP Intelligence, and performance optimizer. No credit card required.', 'limit-login-attempts-reloaded' ),
			'description_add'   => sprintf(
				/* translators: %d: trial length in days. */
				__( 'When your %d day free trial ends, the app automatically reverts to the free version. You may upgrade to one of our premium plans at any time to keep cloud protection.', 'limit-login-attempts-reloaded' ),
				LimitLoginAttempts::TRIAL_DAYS
			),
			'card_title'        => __( 'How To Activate Your Free Trial', 'limit-login-attempts-reloaded' ),
			'activating_text'   => __( 'Activating your free trial…', 'limit-login-attempts-reloaded' ),
			'error_message'     => __( 'The server is not working, try again later', 'limit-login-attempts-reloaded' ),
			'success_text'      => __( 'Your free trial has been activated!', 'limit-login-attempts-reloaded' ),
			'dashboard_label'   => __( 'Great!', 'limit-login-attempts-reloaded' ),
			'retry_label'       => __( 'Try again', 'limit-login-attempts-reloaded' ),
		);

		return self::$view_vars_by_tab[ $tab ];
	}
}
