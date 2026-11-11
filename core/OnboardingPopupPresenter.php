<?php

namespace LLAR\Core;

use LLAR\Core\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the view data for the onboarding popup (views/onboarding-popup.php).
 */
class OnboardingPopupPresenter {

	/**
	 * View vars for the onboarding popup template.
	 *
	 * @return array
	 */
	public function get_view_vars() {
		$admin_notify_email = Config::get( 'admin_notify_email' );
		$admin_email        = ! empty( $admin_notify_email )
			? $admin_notify_email
			: ( ( ! is_multisite() ) ? get_option( 'admin_email' ) : get_site_option( 'admin_email' ) );

		$trial_return = TrialLanding::parse_return_request();
		$open_trial   = ! empty( $trial_return['open_trial_step'] );
		$should_show  = ( ! Config::get( 'onboarding_popup_shown' ) && ! Config::get( 'app_setup_code' ) )
			|| ( $open_trial && empty( Config::get( 'app_setup_code' ) ) );

		$trial_token = '';
		if ( $should_show ) {
			$trial_token = TrialLanding::create_token( 'onboarding' );
		}

		return array(
			'should_show'   => $should_show,
			'admin_email'   => $admin_email,
			'trial_return'  => array(
				'open_step_2'  => $open_trial && empty( Config::get( 'app_setup_code' ) ),
				'can_activate' => ! empty( $trial_return['can_activate'] ) && 'onboarding' === $trial_return['context'],
				'setup_code'   => ! empty( $trial_return['can_activate'] ) && 'onboarding' === $trial_return['context']
					? $trial_return['setup_code']
					: '',
			),
			'trial_landing' => array(
				'info_id'    => TrialLanding::INFO_ID_ONBOARDING,
				'return_url' => TrialLanding::onboarding_return_url( $trial_token ),
				'base_url'   => TrialLanding::LANDING_BASE,
			),
			'steps'         => array(
				__( 'Welcome', 'limit-login-attempts-reloaded' ),
				__( 'Completion', 'limit-login-attempts-reloaded' ),
			),
			'step1'         => array(
				'title'             => __( 'Welcome to Limit Login Attempts Reloaded!', 'limit-login-attempts-reloaded' ),
				'subtitle'          => __( 'Great choice! You\'re joining millions of WordPress sites that rely on Limit Login Attempts Reloaded to protect against brute-force attacks.', 'limit-login-attempts-reloaded' )
					. "\n\n"
					. __( 'Let\'s get your site protected!', 'limit-login-attempts-reloaded' ),
				'setup_title'       => __( 'Already using Premium? Add your Setup Code', 'limit-login-attempts-reloaded' ),
				'setup_placeholder' => __( 'Your Setup Code', 'limit-login-attempts-reloaded' ),
				'setup_button'      => __( 'Activate', 'limit-login-attempts-reloaded' ),
				'setup_desc'        => __( 'The Setup Code can be found in your email confirmation.', 'limit-login-attempts-reloaded' ),
				'notify_title'      => __( 'Notification Settings', 'limit-login-attempts-reloaded' ),
				'email_label'       => __( 'Your Email', 'limit-login-attempts-reloaded' ),
				'email_placeholder' => __( 'Your email', 'limit-login-attempts-reloaded' ),
				'notify_desc'       => __( 'This email will receive notifications of unauthorized access to your website. You may turn this off in your settings.', 'limit-login-attempts-reloaded' ),
				'checkbox_label'    => __( 'Sign me up for the LLAR newsletter to receive important security alerts, plugin updates, and helpful guides.', 'limit-login-attempts-reloaded' ),
				'continue_label'    => __( 'Continue', 'limit-login-attempts-reloaded' ),
			),
			'step2'         => array(
				'title'              => __( 'Experience Premium Protection for Free', 'limit-login-attempts-reloaded' ),
				'subtitle'           => __( 'No commitment. No credit card required.', 'limit-login-attempts-reloaded' ),
				'pitch'              => sprintf(
					/* translators: %d: trial length in days. */
					__( 'Enjoy %d days of advanced cloud protection, including Login Firewall, Country Blocking, detailed login logs, and more.', 'limit-login-attempts-reloaded' ),
					LimitLoginAttempts::TRIAL_DAYS
				),
				'email_label'        => __( 'Your Email', 'limit-login-attempts-reloaded' ),
				'email_placeholder'  => __( 'Your email', 'limit-login-attempts-reloaded' ),
				'consent_label'      => __( 'I agree to share my email and website domain with Limit Login Attempts Reloaded to activate cloud protection.', 'limit-login-attempts-reloaded' ),
				'yes_label'          => __( 'Get Free Premium Protection', 'limit-login-attempts-reloaded' ),
				'after_trial_note'   => sprintf(
					/* translators: %d: trial length in days. */
					__( 'After %d days, your site continues with free protection unless you choose to upgrade.', 'limit-login-attempts-reloaded' ),
					LimitLoginAttempts::TRIAL_DAYS
				),
				'skip_label'         => __( 'Continue with Basic Protection', 'limit-login-attempts-reloaded' ),
				'terms'              => sprintf(
					/* translators: %1$s: opening link tag, %2$s: closing link tag */
					esc_html__( 'We\'ll send you instructions via email to complete setup. You may opt-out of this program at any time. You accept our %1$s terms of service %2$s by participating in this program.', 'limit-login-attempts-reloaded' ),
					'<a class="link__style_color_inherit llar_turquoise" href="https://www.limitloginattempts.com/terms/" target="_blank">',
					'</a>'
				),
			),
			'errors'        => array(
				'complete_failed'  => __( 'Could not finish onboarding. Please try again.', 'limit-login-attempts-reloaded' ),
				'subscribe_failed' => __( 'Could not save notification settings. Please try again.', 'limit-login-attempts-reloaded' ),
			),
		);
	}
}
