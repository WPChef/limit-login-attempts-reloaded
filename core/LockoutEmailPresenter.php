<?php
/**
 * Lockout Email Presenter
 *
 * Builds every value the lockout email templates render: copy, URLs,
 * kses sets and inline styles. Templates stay abstract markup.
 *
 * @package LimitLoginAttempts
 * @since 3.3.10
 */

namespace LLAR\Core;

use LLAR\Core\Digest\DigestDispatcher;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * View data provider for the lockout notification email.
 */
class LockoutEmailPresenter {

	/**
	 * Inline styles for the email body, keyed by usage. All colors live
	 * here so templates carry no hardcoded values.
	 *
	 * @return array
	 */
	private static function get_styles() {
		return array(
			'paragraph_14'       => 'margin:0 0 14px;font-size:14px;line-height:1.5;color:#333333;',
			'paragraph_16'       => 'margin:0 0 16px;font-size:14px;line-height:1.5;color:#333333;',
			'paragraph_subhead'  => 'margin:0 0 8px;font-size:14px;line-height:1.5;color:#333333;',
			'paragraph_12'       => 'margin:0 0 12px;font-size:14px;line-height:1.5;color:#333333;',
			'activity_list'      => 'margin:0 0 16px;padding-left:18px;font-size:14px;line-height:1.5;color:#333333;',
			'activity_list_item' => 'margin-bottom:8px;',
			'cta_paragraph'      => 'margin:0 0 16px;font-size:14px;line-height:1.5;color:#333333;text-align:center;',
			'heading'            => 'margin:0 0 8px;font-size:16px;line-height:1.5;color:#333333;',
			'mu_notice'          => 'margin:0 0 12px;font-size:13px;line-height:1.5;color:#4b5563;',
			'button_primary'     => 'display:inline-block;background:#50c1cd;color:#ffffff;border-radius:30px;padding:10px 20px;text-decoration:none;',
			'button_premium'     => 'display:inline-block;background:#fda33b;color:#ffffff;border-radius:30px;padding:10px 20px;text-decoration:none;',
		);
	}

	/**
	 * Build the full view variables set for the lockout email body.
	 *
	 * @param array $args {
	 *     Business facts resolved by LockoutNotificationService.
	 *
	 *     @type string $ip                   Offending IP address.
	 *     @type string $user                 Attempted username.
	 *     @type int    $count                Failed attempts count.
	 *     @type string $when                 Formatted lockout duration label.
	 *     @type string $site_domain          Site domain without scheme.
	 *     @type string $dashboard_url        Plugin dashboard URI.
	 *     @type string $manage_settings_url  Settings tab URI.
	 * }
	 * @return array
	 */
	public static function get_view_vars( array $args ) {
		$ip     = (string) $args['ip'];
		$user   = (string) $args['user'];
		$count  = (int) $args['count'];
		$when   = (string) $args['when'];
		$site_domain = (string) $args['site_domain'];

		$dashboard_url       = $args['dashboard_url'];
		$manage_settings_url = $args['manage_settings_url'];
		$premium_url         = 'https://www.limitloginattempts.com/info.php?id=36';

		$styles      = self::get_styles();
		$kses_strong = array( 'strong' => array() );

		$preview_text = __(
			'Limit Login Attempts Reloaded temporarily blocked this IP after unsuccessful login attempts.',
			'limit-login-attempts-reloaded'
		);

		$greeting = __( 'Hello,', 'limit-login-attempts-reloaded' );

		$intro_html = sprintf(
			/* translators: %s: site domain */
			__( 'Limit Login Attempts Reloaded detected unsuccessful login attempts on <strong>%s</strong>.', 'limit-login-attempts-reloaded' ),
			esc_html( $site_domain )
		);

		$login_activity_heading = __( 'Login activity', 'limit-login-attempts-reloaded' );

		$failed_attempts_line_html = sprintf(
			/* translators: %d: failed attempts count */
			__( 'Failed attempts: <strong>%d</strong>', 'limit-login-attempts-reloaded' ),
			$count
		);

		$ip_address_line_html = sprintf(
			/* translators: %s: IP address */
			__( 'IP address: <strong>%s</strong>', 'limit-login-attempts-reloaded' ),
			esc_html( $ip )
		);

		$username_attempted_line_html = sprintf(
			/* translators: %s: username */
			__( 'Username attempted: <strong>%s</strong>', 'limit-login-attempts-reloaded' ),
			esc_html( $user )
		);

		$action_taken_line_html = sprintf(
			/* translators: %s: lockout duration label */
			__( 'Action taken: <strong>IP blocked for %s</strong>', 'limit-login-attempts-reloaded' ),
			esc_html( $when )
		);

		$login_page_line_html = sprintf(
			/* translators: %s: login page label */
			__( 'Login page: <strong>%s</strong>', 'limit-login-attempts-reloaded' ),
			esc_html( Helpers::get_current_url_label() )
		);

		$no_action_required = __(
			'No action is required unless you recognize this activity or would like to review additional details.',
			'limit-login-attempts-reloaded'
		);

		$dashboard_button_label = __( 'Review Login Activity', 'limit-login-attempts-reloaded' );

		$dashboard_helper_text = __(
			'You can view recent login attempts, adjust your settings, and review blocked IP addresses from your WordPress dashboard.',
			'limit-login-attempts-reloaded'
		);

		$additional_protection_heading = __( 'Want additional protection?', 'limit-login-attempts-reloaded' );

		$additional_protection_text = __(
			'If your site receives frequent login attempts, additional protection options are available within Limit Login Attempts Security. These options can help identify and block suspicious traffic before it reaches your login page.',
			'limit-login-attempts-reloaded'
		);

		$additional_protection_url   = $premium_url;
		$additional_protection_label = __( 'Learn about additional protection', 'limit-login-attempts-reloaded' );

		$about_notification_heading = __( 'About this notification', 'limit-login-attempts-reloaded' );

		$about_notification_html = sprintf(
			/* translators: %s: site domain */
			__( 'You received this message because login notifications are enabled for <strong>%s</strong>.', 'limit-login-attempts-reloaded' ),
			esc_html( $site_domain )
		);

		$manage_settings_label = __( 'Manage notification settings', 'limit-login-attempts-reloaded' );

		/* Gray footer block: manage-settings link, styled like the digest unsubscribe footer. */
		$unsubscribe_footer_text = DigestDispatcher::build_unsubscribe_footer_text(
			array( 'unsubscribe_text' => $manage_settings_label ),
			$manage_settings_url
		);

		return array(
			'preview_text'                 => $preview_text,
			'styles'                       => $styles,
			'kses_strong'                  => $kses_strong,
			'greeting'                     => $greeting,
			'intro_html'                   => $intro_html,
			'login_activity_heading'       => $login_activity_heading,
			'failed_attempts_line_html'    => $failed_attempts_line_html,
			'ip_address_line_html'         => $ip_address_line_html,
			'username_attempted_line_html' => $username_attempted_line_html,
			'action_taken_line_html'       => $action_taken_line_html,
			'login_page_line_html'         => $login_page_line_html,
			'no_action_required'           => $no_action_required,
			'dashboard_url'                => $dashboard_url,
			'dashboard_button_label'       => $dashboard_button_label,
			'dashboard_helper_text'        => $dashboard_helper_text,
			'additional_protection_heading'   => $additional_protection_heading,
			'additional_protection_text'      => $additional_protection_text,
			'additional_protection_url'       => $additional_protection_url,
			'additional_protection_label'     => $additional_protection_label,
			'about_notification_heading'      => $about_notification_heading,
			'about_notification_html'         => $about_notification_html,
			'show_mu_notice'                  => Helpers::is_mu(),
			'mu_notice'                       => __(
				'This alert was sent by your website where Limit Login Attempts Reloaded free version is installed and you are listed as the admin.',
				'limit-login-attempts-reloaded'
			),
			'unsubscribe_footer_text'         => $unsubscribe_footer_text,
		);
	}
}
