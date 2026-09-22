<?php

namespace LLAR\Core;

use LLAR\Core\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the view data for the Micro Cloud free trial modal (views/micro-cloud-modal.php).
 */
class MicroCloudModalPresenter {

	/**
	 * View vars for the Micro Cloud modal template.
	 *
	 * @return array
	 */
	public function get_view_vars() {
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
}
