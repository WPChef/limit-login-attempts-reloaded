<?php
/**
 * Array for plans comparison block
 *
 */

$lock = '<img src="' . LLA_PLUGIN_URL . '/assets/css/images/icon-lock-bw.png" class="icon-lock">';

$compare_list = [
    'buttons_header' => [
        'Free'          => '<a class="button menu__item button__orange" href="https://checkout.limitloginattempts.com/plan?from=plugin-premium-tab" target="_blank">' . __('Installed', 'limit-login-attempts-reloaded') . '</a>',
        'Micro Cloud'   => '<a class="button menu__item button__orange" href="https://checkout.limitloginattempts.com/plan?from=plugin-premium-tab" target="_blank">' . __('Upgrade now', 'limit-login-attempts-reloaded') . '</a>',
        'Premium'       => '<a class="button menu__item button__orange" href="https://checkout.limitloginattempts.com/plan?from=plugin-premium-tab" target="_blank">' . __('Upgrade now', 'limit-login-attempts-reloaded') . '</a>',
        'Premium +'     => '<a class="button menu__item button__orange" href="https://checkout.limitloginattempts.com/plan?from=plugin-premium-tab" target="_blank">' . __('Upgrade now', 'limit-login-attempts-reloaded') . '</a>',
        'Professional'  => '<a class="button menu__item button__orange" href="https://checkout.limitloginattempts.com/plan?from=plugin-premium-tab" target="_blank">' . __('Upgrade now', 'limit-login-attempts-reloaded') . '</a>',
    ],
    'Limit Number of Retry Attempts' => [
        'Free'          => '✓',
        'Micro Cloud'   => '✓',
        'Premium'       => '✓',
        'Premium +'     => '✓',
        'Professional'  => '✓',
    ],
    'Configurable Lockout Timing' => [
        'Free'          => '✓',
        'Micro Cloud'   => '✓',
        'Premium'       => '✓',
        'Premium +'     => '✓',
        'Professional'  => '✓',
    ],
    'Performance Optimizer' => [
        'description'   => 'Absorb failed login attempts from brute force bots in the cloud to keep your website at its optimal performance.',
        'Free'          => $lock,
        'Micro Cloud'   => '✓' . '<span>1k requests per month</span>',
        'Premium'       => '✓',
        'Premium +'     => '✓',
        'Professional'  => '✓',
    ],
    'Block By Country' => [
        'description'   => 'Disable IPs from any region to disable logins.',
        'Free'          => $lock,
        'Micro Cloud'   => '✓',
        'Premium'       => '✓',
        'Premium +'     => '✓',
        'Professional'  => '✓',
    ],
    'Access Blocklist of Malicious IPs' => [
        'description' => 'Add another layer of protection from brute force bots by accessing a global database of known IPs with malicious activity.',
        'Free'          => $lock,
        'Micro Cloud'   => '✓',
        'Premium'       => '✓',
        'Premium +'     => '✓',
        'Professional'  => '✓',
    ],
    'Auto IP Blocklist' => [
        'description' => 'Automatically add malicious IPs to your blocklist when triggered by the system.',
        'Free'          => $lock,
        'Micro Cloud'   => '✓',
        'Premium'       => '✓',
        'Premium +'     => '✓',
        'Professional'  => '✓',
    ],
    'Access Active Cloud Blocklist' => [
        'description' => 'Use system wide data from over 10,000 WordPress websites to identify and block malicious IPs. This is an active list in real-time.',
        'Free'          => $lock,
        'Micro Cloud'   => '✓',
        'Premium'       => '✓',
        'Premium +'     => '✓',
        'Professional'  => '✓',
    ],
    'Intelligent IP Blocking' => [
        'description' => 'Use active IP database via the cloud to automatically block users before they are able to make a failed login.',
        'Free'          => $lock,
        'Micro Cloud'   => '✓',
        'Premium'       => '✓',
        'Premium +'     => '✓',
        'Professional'  => '✓',
    ],
    'Synchronize Lockouts & Safelists/Blocklists' => [
        'description' => 'Lockouts & safelists/blocklists can be shared between multiple domains to enhance protection.',
        'Free'          => $lock,
        'Micro Cloud'   => '✓',
        'Premium'       => '✓',
        'Premium +'     => '✓',
        'Professional'  => '✓',
    ],
    'Premium Support' => [
        'Receive 1 on 1 technical support via email for any issues. Free support availabe in the WordPress support forum.',
        'Free'          => $lock,
        'Micro Cloud'   => '✓',
        'Premium'       => '✓',
        'Premium +'     => '✓',
        'Professional'  => '✓',
    ],
    'buttons_footer' => [
        'Free'          => '<a class="button menu__item button__orange" href="https://checkout.limitloginattempts.com/plan?from=plugin-premium-tab" target="_blank">' . __('Installed', 'limit-login-attempts-reloaded') . '</a>',
        'Micro Cloud'   => '<a class="button menu__item button__orange" href="https://checkout.limitloginattempts.com/plan?from=plugin-premium-tab" target="_blank">' . __('Upgrade now', 'limit-login-attempts-reloaded') . '</a>',
        'Premium'       => '<a class="button menu__item button__orange" href="https://checkout.limitloginattempts.com/plan?from=plugin-premium-tab" target="_blank">' . __('Upgrade now', 'limit-login-attempts-reloaded') . '</a>',
        'Premium +'     => '<a class="button menu__item button__orange" href="https://checkout.limitloginattempts.com/plan?from=plugin-premium-tab" target="_blank">' . __('Upgrade now', 'limit-login-attempts-reloaded') . '</a>',
        'Professional'  => '<a class="button menu__item button__orange" href="https://checkout.limitloginattempts.com/plan?from=plugin-premium-tab" target="_blank">' . __('Upgrade now', 'limit-login-attempts-reloaded') . '</a>',
    ],
];

return $compare_list;
