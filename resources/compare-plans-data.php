<?php
/**
 * Array for plans comparison block
 *
 * @var string $active_app
 *
 */


$lock = '<img src="' . LLA_PLUGIN_URL . '/assets/css/images/icon-lock-bw.png" class="icon-lock">';
$yes = '<span class="llar_orange">&#x2713;</span>';
$class_button_local = $active_app === 'local' ? ' button__transparent_orange llar-disabled' : ' button__orange';
$class_button_custom = $active_app === 'custom' ? ' button__transparent_orange llar-disabled' : ' button__orange';

$compare_list = array(
    'buttons_header'                                => array(
        'Free'          => '<a class="button menu__item' . $class_button_local . '" href="https://checkout.limitloginattempts.com/plan?from=plugin-premium-tab" target="_blank">' . __('Installed', 'limit-login-attempts-reloaded') . '</a>',
        'Micro Cloud'   => '<a class="button menu__item button__orange" href="https://checkout.limitloginattempts.com/plan?from=plugin-premium-tab" target="_blank">' . __('Get Started (Free)', 'limit-login-attempts-reloaded') . '</a>',
        'Premium'       => '<a class="button menu__item' . $class_button_custom . '" href="https://checkout.limitloginattempts.com/plan?from=plugin-premium-tab" target="_blank">' . __('Upgrade now', 'limit-login-attempts-reloaded') . '</a>',
        'Premium +'     => '<a class="button menu__item button__orange" href="https://checkout.limitloginattempts.com/plan?from=plugin-premium-tab" target="_blank">' . __('Upgrade now', 'limit-login-attempts-reloaded') . '</a>',
        'Professional'  => '<a class="button menu__item button__orange" href="https://checkout.limitloginattempts.com/plan?from=plugin-premium-tab" target="_blank">' . __('Upgrade now', 'limit-login-attempts-reloaded') . '</a>',
    ),
    'Limit Number of Retry Attempts'                => array(
        'Free'          => $yes,
        'Micro Cloud'   => $yes,
        'Premium'       => $yes,
        'Premium +'     => $yes,
        'Professional'  => $yes,
    ),
    'Configurable Lockout Timing'                   => array(
        'Free'          => $yes,
        'Micro Cloud'   => $yes,
        'Premium'       => $yes,
        'Premium +'     => $yes,
        'Professional'  => $yes,
    ),
    'Login Firewall'                                => array(
        'description'   =>  __( "Secure your login page with our cutting-edge login firewall, defending against unauthorized access attempts and protecting your users' accounts and sensitive information.", 'limit-login-attempts-reloaded' ),
        'Free'          => $lock,
        'Micro Cloud'   => $yes,
        'Premium'       => $yes,
        'Premium +'     => $yes,
        'Professional'  => $yes,
    ),
    'Performance Optimizer'                         => array(
        'description'   =>  __( 'Absorb failed login attempts from brute force bots in the cloud to keep your website at its optimal performance.', 'limit-login-attempts-reloaded' ),
        'Free'          => $lock,
        'Micro Cloud'   => $yes . '<span class="description">1k requests per month</span>',
        'Premium'       => $yes . '<span class="description">100k requests per month</span>',
        'Premium +'     => $yes . '<span class="description">200k requests per month</span>',
        'Professional'  => $yes . '<span class="description">300k requests per month</span>',
    ),
    'Block By Country'                              => array(
        'description'   =>  __( 'Disable IPs from any region to disable logins.', 'limit-login-attempts-reloaded' ),
        'Free'          => $lock,
        'Micro Cloud'   => $lock,
        'Premium'       => $lock,
        'Premium +'     => $yes,
        'Professional'  => $yes,
    ),
    'Access Blocklist of Malicious IPs'             => array(
        'description'   =>  __( 'Add another layer of protection from brute force bots by accessing a global database of known IPs with malicious activity.', 'limit-login-attempts-reloaded' ),
        'Free'          => $lock,
        'Micro Cloud'   => $lock,
        'Premium'       => $lock,
        'Premium +'     => $yes,
        'Professional'  => $yes,
    ),
    'Auto IP Blocklist'                             => array(
        'description'   =>  __( 'Automatically add malicious IPs to your blocklist when triggered by the system.', 'limit-login-attempts-reloaded' ),
        'Free'          => $lock,
        'Micro Cloud'   => $lock,
        'Premium'       => $lock,
        'Premium +'     => $lock,
        'Professional'  => $yes,
    ),
    'Access Active Cloud Blocklist'                 => array(
        'description'   =>  __( 'Use system wide data from over 10,000 WordPress websites to identify and block malicious IPs. This is an active list in real-time.', 'limit-login-attempts-reloaded' ),
        'Free'          => $lock,
        'Micro Cloud'   => $lock,
        'Premium'       => $lock,
        'Premium +'     => $lock,
        'Professional'  => $yes,
    ),
    'Intelligent IP Blocking'                       => array(
        'description'   =>  __( 'Use active IP database via the cloud to automatically block users before they are able to make a failed login.', 'limit-login-attempts-reloaded' ),
        'Free'          => $lock,
        'Micro Cloud'   => $yes,
        'Premium'       => $yes,
        'Premium +'     => $yes,
        'Professional'  => $yes,
    ),
    'Synchronize Lockouts & Safelists/Blocklists'   => array(
        'description'   =>  __( 'Lockouts & safelists/blocklists can be shared between multiple domains to enhance protection.', 'limit-login-attempts-reloaded' ),
        'Free'          => $lock,
        'Micro Cloud'   => $yes,
        'Premium'       => $yes,
        'Premium +'     => $yes,
        'Professional'  => $yes,
    ),
    'Premium Support'                               => array(
        'description'   =>  __( 'Receive 1 on 1 technical support via email for any issues. Free support availabe in the <a href="https://wordpress.org/support/plugin/limit-login-attempts-reloaded/" target="_blank">WordPress support forum</a>.', 'limit-login-attempts-reloaded' ),
        'Free'          => $lock,
        'Micro Cloud'   => $yes,
        'Premium'       => $yes,
        'Premium +'     => $yes,
        'Professional'  => $yes,
    ),
    'buttons_footer'                                => array(
        'Free'          => '<a class="button menu__item' . $class_button_local . '" href="https://checkout.limitloginattempts.com/plan?from=plugin-premium-tab" target="_blank">' . __('Installed', 'limit-login-attempts-reloaded') . '</a>',
        'Micro Cloud'   => '<a class="button menu__item button__orange" href="https://checkout.limitloginattempts.com/plan?from=plugin-premium-tab" target="_blank">' . __('Get Started (Free)', 'limit-login-attempts-reloaded') . '</a>',
        'Premium'       => '<a class="button menu__item' . $class_button_custom . '" href="https://checkout.limitloginattempts.com/plan?from=plugin-premium-tab" target="_blank">' . __('Upgrade now', 'limit-login-attempts-reloaded') . '</a>',
        'Premium +'     => '<a class="button menu__item button__orange" href="https://checkout.limitloginattempts.com/plan?from=plugin-premium-tab" target="_blank">' . __('Upgrade now', 'limit-login-attempts-reloaded') . '</a>',
        'Professional'  => '<a class="button menu__item button__orange" href="https://checkout.limitloginattempts.com/plan?from=plugin-premium-tab" target="_blank">' . __('Upgrade now', 'limit-login-attempts-reloaded') . '</a>',
    ),
);

return $compare_list;