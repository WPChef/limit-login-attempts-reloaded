<?php
/**
 * Array for plans comparison block
 *
 * @var string $active_app
 * @var array  $display_plans
 * @var LLAR\Core\LimitLoginAttempts $this
 *
 */

$min_plan         = 'custom' === $active_app ? 'Micro Cloud' : 'Free';
$trial_plan_name  = $this->trial_plan_name();

$plans       = $this->array_name_plans();
$actual_plan = 'custom' === $active_app ? $this->info_sub_group() : $min_plan;

$actual_rate = isset( $plans[ $actual_plan ] ) ? $plans[ $actual_plan ] : $plans['Free'];

if ( 'local' === $active_app ) {
	$upgrade_urls = array(
		'Personal' => 'https://www.limitloginattempts.com/info.php?id=29',
		'Business' => 'https://www.limitloginattempts.com/info.php?id=30',
	);
} elseif ( $trial_plan_name === $actual_plan ) {
	$upgrade_urls = array(
		'Personal' => add_query_arg( 'id', '39', $this->info_upgrade_url() ),
		'Business' => add_query_arg( 'id', '40', $this->info_upgrade_url() ),
	);
} elseif ( 'Micro Cloud' === $actual_plan ) {
	$upgrade_urls = array(
		'Personal' => add_query_arg( 'id', '31', $this->info_upgrade_url() ),
		'Business' => add_query_arg( 'id', '32', $this->info_upgrade_url() ),
	);
} else {
	// Paid premium plans: upgrade CTA only for Business (or none if already on/above it).
	$upgrade_urls = array(
		'Business' => add_query_arg( 'id', '33', $this->info_upgrade_url() ),
	);
}

$buttons_row = array();
foreach ( $display_plans as $plan ) {

	if ( $trial_plan_name === $plan ) {
		// Trial column: "Active" for trial users, a start CTA for eligible
		// first-time users, and empty otherwise. It is never installable or
		// upgradable from other plans.
		if ( $trial_plan_name === $actual_plan ) {
			$buttons_row[ $plan ] = '<a class="button menu__item button__transparent_orange llar-disabled">' . esc_html__( 'Active', 'limit-login-attempts-reloaded' ) . '</a>';
		} elseif ( empty( \LLAR\Core\Config::get( 'app_setup_code' ) ) ) {
			$buttons_row[ $plan ] = '<a href="' . esc_url( \LLAR\Core\TrialLanding::cta_landing_url( \LLAR\Core\TrialLanding::INFO_ID_PREMIUM, 'premium' ) ) . '" data-llar-info-id="' . esc_attr( \LLAR\Core\TrialLanding::INFO_ID_PREMIUM ) . '" class="button menu__item button__transparent_orange llar-trial-start-cta">' . esc_html( \LLAR\Core\LimitLoginAttempts::trial_label() ) . '</a>';
		} else {
			$buttons_row[ $plan ] = '';
		}
		continue;
	}

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

$pricing_presenter = new \LLAR\Core\PremiumPlansPresenter();
$pricing_row       = $pricing_presenter->build_pricing_row( $display_plans, $actual_rate, $plans );

$lock = '<img src="' . LLA_PLUGIN_URL . 'assets/css/images/icon-lock-bw.png" class="icon-lock">';
$yes  = '<span class="llar_orange">&#x2713;</span>';

// Every paid plan.
$yes_row          = array_fill_keys( $display_plans, $yes );
$paid_row         = $yes_row;
$paid_row['Free'] = $lock;

// Micro Cloud, Premium +, Professional, Business, Agency.
$full_row             = $paid_row;
$full_row['Personal'] = $lock;
$full_row['Premium']  = $lock;

// Micro Cloud, Professional, Business, Agency.
$top_row                = $full_row;
$top_row['Premium +']   = $lock;

$performance_optimizer_row = $paid_row;
/* translators: %s: line break. */
$performance_optimizer_row['Micro Cloud']    = $yes . '<span class="description">' . sprintf( esc_html__( '1k for first month%s(100 per month after)', 'limit-login-attempts-reloaded' ), '<br>' ) . '</span>';
$performance_optimizer_row['Personal']       = $yes . '<span class="description">' . esc_html__( '50k requests per month', 'limit-login-attempts-reloaded' ) . '</span>';
$performance_optimizer_row['Premium']        = $yes . '<span class="description">' . esc_html__( '100k requests per month', 'limit-login-attempts-reloaded' ) . '</span>';
$performance_optimizer_row['Premium +']      = $yes . '<span class="description">' . esc_html__( '200k requests per month', 'limit-login-attempts-reloaded' ) . '</span>';
$performance_optimizer_row['Professional']   = $yes . '<span class="description">' . esc_html__( '300k requests per month', 'limit-login-attempts-reloaded' ) . '</span>';
$performance_optimizer_row['Business']       = $yes . '<span class="description">' . esc_html__( '300k requests per month', 'limit-login-attempts-reloaded' ) . '</span>';
$performance_optimizer_row['Agency']         = $yes . '<span class="description">' . esc_html__( '300k requests per month', 'limit-login-attempts-reloaded' ) . '</span>';

$compare_list = array(
	'buttons_header' => $buttons_row,
	'pricing'        => $pricing_row,
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

// The trial column mirrors the Business column for every feature row
// (no pricing card and no plan description — those stay Business-only).
foreach ( $compare_list as $category => $row ) {
	if ( in_array( $category, array( 'buttons_header', 'buttons_footer', 'pricing' ), true ) || ! isset( $row['Business'] ) ) {
		continue;
	}

	$compare_list[ $category ][ $trial_plan_name ] = $row['Business'];
}

return $compare_list;
