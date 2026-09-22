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
}
