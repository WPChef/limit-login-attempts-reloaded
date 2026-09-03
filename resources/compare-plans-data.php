<?php
/**
 * Array for plans comparison block
 *
 * @var string $active_app
 * @var LLAR\Core\LimitLoginAttempts $this
 *
 */

$min_plan = $active_app === 'custom' ? 'Micro Cloud' : 'Free';

$plans = $this->array_name_plans();
$actual_plan = $active_app === 'custom' ? $this->info_sub_group() : $min_plan;

$display_plans = array( 'Free', 'Hobby', 'Business' );
$table_plan_rates = array(
	'Free'     => $plans['Free'],
	'Hobby'    => $plans['Premium'],
	'Business' => $plans['Professional'],
);

$actual_rate = isset( $plans[ $actual_plan ] ) ? $plans[ $actual_plan ] : $plans['Free'];

$attribute = array();
foreach ( $display_plans as $plan ) {
	$plan_rate = $table_plan_rates[ $plan ];

	if ( $plan_rate < $actual_rate ) {
		$attribute[ $plan ]['attr']  = '';
		$attribute[ $plan ]['title'] = '';
	} elseif ( $plan_rate === $actual_rate || ( $plan === 'Free' && $active_app === 'local' ) ) {
		$attribute[ $plan ]['attr']  = 'class="button menu__item button__transparent_orange llar-disabled"';
		$attribute[ $plan ]['title'] = __( 'Installed', 'limit-login-attempts-reloaded' );
	} elseif ( $plan === 'Hobby' ) {
		$hobby_url = ( $min_plan === 'Micro Cloud' )
			? add_query_arg( 'id', '9', $this->info_upgrade_url() )
			: 'https://www.limitloginattempts.com/info.php?id=29';
		$attribute[ $plan ]['attr']  = 'class="button menu__item button__orange" href="' . esc_url( $hobby_url ) . '" target="_blank"';
		$attribute[ $plan ]['title'] = __( 'Upgrade now', 'limit-login-attempts-reloaded' );
	} elseif ( $plan === 'Business' ) {
		$business_url = ( $min_plan === 'Micro Cloud' )
			? add_query_arg( 'id', '11', $this->info_upgrade_url() )
			: 'https://www.limitloginattempts.com/info.php?id=30';
		$attribute[ $plan ]['attr']  = 'class="button menu__item button__orange" href="' . esc_url( $business_url ) . '" target="_blank"';
		$attribute[ $plan ]['title'] = __( 'Upgrade now', 'limit-login-attempts-reloaded' );
	}
}

$lock = '<img src="' . LLA_PLUGIN_URL . 'assets/css/images/icon-lock-bw.png" class="icon-lock">';
$yes = '<span class="llar_orange">&#x2713;</span>';

$compare_list = array(
	'buttons_header'                                => array(
		'Free'     => '<a ' . $attribute['Free']['attr'] . '>' . esc_html__( $attribute['Free']['title'], 'limit-login-attempts-reloaded' ) . '</a>',
		'Hobby'    => '<a ' . $attribute['Hobby']['attr'] . '>' . esc_html__( $attribute['Hobby']['title'], 'limit-login-attempts-reloaded' ) . '</a>',
		'Business' => '<a ' . $attribute['Business']['attr'] . '>' . esc_html__( $attribute['Business']['title'], 'limit-login-attempts-reloaded' ) . '</a>',
	),
	__( 'Limit Number of Retry Attempts', 'limit-login-attempts-reloaded' )                => array(
		'Free'     => $yes,
		'Hobby'    => $yes,
		'Business' => $yes,
	),
	__( 'Configurable Lockout Timing', 'limit-login-attempts-reloaded' )                   => array(
		'Free'     => $yes,
		'Hobby'    => $yes,
		'Business' => $yes,
	),
	__( 'Login Firewall', 'limit-login-attempts-reloaded' )                                => array(
		'description' => __( "Secure your login page with our cutting-edge login firewall, defending against unauthorized access attempts and protecting your users' accounts and sensitive information.", 'limit-login-attempts-reloaded' ),
		'Free'        => $lock,
		'Hobby'       => $yes,
		'Business'    => $yes,
	),
	__( 'Performance Optimizer', 'limit-login-attempts-reloaded' )                         => array(
		'description' => __( 'Absorb failed login attempts from brute force bots in the cloud to keep your website at its optimal performance.', 'limit-login-attempts-reloaded' ),
		'Free'        => $lock,
		'Hobby'       => $yes . '<span class="description">' . esc_html__( '50k requests per month', 'limit-login-attempts-reloaded' ) . '</span>',
		'Business'    => $yes . '<span class="description">' . esc_html__( '300k requests per month', 'limit-login-attempts-reloaded' ) . '</span>',
	),
	__( 'Successful Login Logs', 'limit-login-attempts-reloaded' )                         => array(
		'description' => __( 'Ensure the security and integrity of your website by logging your successful logins.', 'limit-login-attempts-reloaded' ),
		'Free'        => $lock,
		'Hobby'       => $yes,
		'Business'    => $yes,
	),
	__( 'Block By Country', 'limit-login-attempts-reloaded' )                              => array(
		'description' => __( 'Disable IPs from any region to disable logins.', 'limit-login-attempts-reloaded' ),
		'Free'        => $lock,
		'Hobby'       => $lock,
		'Business'    => $yes,
	),
	__( 'Access Blocklist of Malicious IPs', 'limit-login-attempts-reloaded' )             => array(
		'description' => __( 'Add another layer of protection from brute force bots by accessing a global database of known IPs with malicious activity.', 'limit-login-attempts-reloaded' ),
		'Free'        => $lock,
		'Hobby'       => $lock,
		'Business'    => $yes,
	),
	__( 'Auto IP Blocklist', 'limit-login-attempts-reloaded' )                             => array(
		'description' => __( 'Automatically add malicious IPs to your blocklist when triggered by the system.', 'limit-login-attempts-reloaded' ),
		'Free'        => $lock,
		'Hobby'       => $lock,
		'Business'    => $yes,
	),
	__( 'Access Active Cloud Blocklist', 'limit-login-attempts-reloaded' )                 => array(
		'description' => __( 'Use system wide data from over 10,000 WordPress websites to identify and block malicious IPs. This is an active list in real-time.', 'limit-login-attempts-reloaded' ),
		'Free'        => $lock,
		'Hobby'       => $lock,
		'Business'    => $yes,
	),
	__( 'Intelligent IP Blocking', 'limit-login-attempts-reloaded' )                       => array(
		'description' => __( 'Use active IP database via the cloud to automatically block users before they are able to make a failed login.', 'limit-login-attempts-reloaded' ),
		'Free'        => $lock,
		'Hobby'       => $yes,
		'Business'    => $yes,
	),
	__( 'Synchronize Lockouts & Safelists/Blocklists', 'limit-login-attempts-reloaded' )   => array(
		'description' => __( 'Lockouts & safelists/blocklists can be shared between multiple domains to enhance protection.', 'limit-login-attempts-reloaded' ),
		'Free'        => $lock,
		'Hobby'       => $yes,
		'Business'    => $yes,
	),
	__( 'Premium Support', 'limit-login-attempts-reloaded' )                               => array(
		'description' => sprintf(
			__( 'Receive 1 on 1 technical support via email for any issues. Free support availabe in the <a href="%s" target="_blank">WordPress support forum</a>.', 'limit-login-attempts-reloaded' ),
			'https://wordpress.org/support/plugin/limit-login-attempts-reloaded/'
		),
		'Free'        => $lock,
		'Hobby'       => $yes,
		'Business'    => $yes,
	),
	'buttons_footer'                                => array(
		'Free'     => '<a ' . $attribute['Free']['attr'] . '>' . esc_html__( $attribute['Free']['title'], 'limit-login-attempts-reloaded' ) . '</a>',
		'Hobby'    => '<a ' . $attribute['Hobby']['attr'] . '>' . esc_html__( $attribute['Hobby']['title'], 'limit-login-attempts-reloaded' ) . '</a>',
		'Business' => '<a ' . $attribute['Business']['attr'] . '>' . esc_html__( $attribute['Business']['title'], 'limit-login-attempts-reloaded' ) . '</a>',
	),
);

return $compare_list;
