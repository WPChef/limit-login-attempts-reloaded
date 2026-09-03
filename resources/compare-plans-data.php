<?php
/**
 * Array for plans comparison block
 *
 * @var string $active_app
 * @var array  $display_plans
 * @var LLAR\Core\LimitLoginAttempts $this
 *
 */

$min_plan = 'custom' === $active_app ? 'Micro Cloud' : 'Free';

$plans       = $this->array_name_plans();
$actual_plan = 'custom' === $active_app ? $this->info_sub_group() : $min_plan;

$actual_rate = isset( $plans[ $actual_plan ] ) ? $plans[ $actual_plan ] : $plans['Free'];

$upgrade_urls = array(
	'Hobby'    => ( 'Micro Cloud' === $min_plan )
		? add_query_arg( 'id', '9', $this->info_upgrade_url() )
		: 'https://www.limitloginattempts.com/info.php?id=29',
	'Business' => ( 'Micro Cloud' === $min_plan )
		? add_query_arg( 'id', '11', $this->info_upgrade_url() )
		: 'https://www.limitloginattempts.com/info.php?id=30',
);

$buttons_row = array();
foreach ( $display_plans as $plan ) {
	$plan_rate = isset( $plans[ $plan ] ) ? $plans[ $plan ] : $plans['Free'];

	if ( $plan_rate < $actual_rate ) {
		$buttons_row[ $plan ] = '';
	} elseif ( $plan_rate === $actual_rate || ( 'Free' === $plan && 'local' === $active_app ) ) {
		$buttons_row[ $plan ] = '<a class="button menu__item button__transparent_orange llar-disabled">' . esc_html__( 'Installed', 'limit-login-attempts-reloaded' ) . '</a>';
	} elseif ( isset( $upgrade_urls[ $plan ] ) ) {
		$buttons_row[ $plan ] = '<a class="button menu__item button__orange" href="' . esc_url( $upgrade_urls[ $plan ] ) . '" target="_blank">' . esc_html__( 'Upgrade now', 'limit-login-attempts-reloaded' ) . '</a>';
	} else {
		$buttons_row[ $plan ] = '';
	}
}

$lock = '<img src="' . LLA_PLUGIN_URL . 'assets/css/images/icon-lock-bw.png" class="icon-lock">';
$yes  = '<span class="llar_orange">&#x2713;</span>';

// Every paid plan.
$yes_row          = array_fill_keys( $display_plans, $yes );
$paid_row         = $yes_row;
$paid_row['Free'] = $lock;

// Micro Cloud, Premium Plus, Pro, Business, Agency.
$full_row            = $paid_row;
$full_row['Hobby']   = $lock;
$full_row['Premium'] = $lock;

// Micro Cloud, Pro, Business, Agency.
$top_row                 = $full_row;
$top_row['Premium Plus'] = $lock;

$performance_optimizer_row = $paid_row;
/* translators: %s: line break. */
$performance_optimizer_row['Micro Cloud']  = $yes . '<span class="description">' . sprintf( esc_html__( '1k for first month%s(100 per month after)', 'limit-login-attempts-reloaded' ), '<br>' ) . '</span>';
$performance_optimizer_row['Hobby']        = $yes . '<span class="description">' . esc_html__( '50k requests per month', 'limit-login-attempts-reloaded' ) . '</span>';
$performance_optimizer_row['Premium']      = $yes . '<span class="description">' . esc_html__( '100k requests per month', 'limit-login-attempts-reloaded' ) . '</span>';
$performance_optimizer_row['Premium Plus'] = $yes . '<span class="description">' . esc_html__( '200k requests per month', 'limit-login-attempts-reloaded' ) . '</span>';
$performance_optimizer_row['Pro']          = $yes . '<span class="description">' . esc_html__( '300k requests per month', 'limit-login-attempts-reloaded' ) . '</span>';
$performance_optimizer_row['Business']     = $yes . '<span class="description">' . esc_html__( '300k requests per month', 'limit-login-attempts-reloaded' ) . '</span>';
$performance_optimizer_row['Agency']       = $yes . '<span class="description">' . esc_html__( '300k requests per month', 'limit-login-attempts-reloaded' ) . '</span>';

$compare_list = array(
	'buttons_header' => $buttons_row,
	__( 'Limit Number of Retry Attempts', 'limit-login-attempts-reloaded' ) => $yes_row,
	__( 'Configurable Lockout Timing', 'limit-login-attempts-reloaded' ) => $yes_row,
	__( 'Login Firewall', 'limit-login-attempts-reloaded' ) => array(
		'description' => __( "Secure your login page with our cutting-edge login firewall, defending against unauthorized access attempts and protecting your users' accounts and sensitive information.", 'limit-login-attempts-reloaded' ),
	) + $paid_row,
	__( 'Performance Optimizer', 'limit-login-attempts-reloaded' ) => array(
		'description' => __( 'Absorb failed login attempts from brute force bots in the cloud to keep your website at its optimal performance.', 'limit-login-attempts-reloaded' ),
	) + $performance_optimizer_row,
	__( 'Successful Login Logs', 'limit-login-attempts-reloaded' ) => array(
		'description' => __( 'Ensure the security and integrity of your website by logging your successful logins.', 'limit-login-attempts-reloaded' ),
	) + $paid_row,
	__( 'Block By Country', 'limit-login-attempts-reloaded' ) => array(
		'description' => __( 'Disable IPs from any region to disable logins.', 'limit-login-attempts-reloaded' ),
	) + $full_row,
	__( 'Access Blocklist of Malicious IPs', 'limit-login-attempts-reloaded' ) => array(
		'description' => __( 'Add another layer of protection from brute force bots by accessing a global database of known IPs with malicious activity.', 'limit-login-attempts-reloaded' ),
	) + $full_row,
	__( 'Auto IP Blocklist', 'limit-login-attempts-reloaded' ) => array(
		'description' => __( 'Automatically add malicious IPs to your blocklist when triggered by the system.', 'limit-login-attempts-reloaded' ),
	) + $top_row,
	__( 'Access Active Cloud Blocklist', 'limit-login-attempts-reloaded' ) => array(
		'description' => __( 'Use system wide data from over 10,000 WordPress websites to identify and block malicious IPs. This is an active list in real-time.', 'limit-login-attempts-reloaded' ),
	) + $top_row,
	__( 'Intelligent IP Blocking', 'limit-login-attempts-reloaded' ) => array(
		'description' => __( 'Use active IP database via the cloud to automatically block users before they are able to make a failed login.', 'limit-login-attempts-reloaded' ),
	) + $paid_row,
	__( 'Synchronize Lockouts & Safelists/Blocklists', 'limit-login-attempts-reloaded' ) => array(
		'description' => __( 'Lockouts & safelists/blocklists can be shared between multiple domains to enhance protection.', 'limit-login-attempts-reloaded' ),
	) + $paid_row,
	__( 'Premium Support', 'limit-login-attempts-reloaded' ) => array(
		'description' => sprintf(
			/* translators: %s: WordPress support forum URL. */
			__( 'Receive 1 on 1 technical support via email for any issues. Free support availabe in the <a href="%s" target="_blank">WordPress support forum</a>.', 'limit-login-attempts-reloaded' ),
			'https://wordpress.org/support/plugin/limit-login-attempts-reloaded/'
		),
	) + $paid_row,
	'buttons_footer' => $buttons_row,
);

return $compare_list;
