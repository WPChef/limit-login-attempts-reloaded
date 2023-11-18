<?php
/**
 * Array for plans comparison block
 *
 */

$compare_list = [
    'buttons_header' => [
        'Free'          => '<a class="button menu__item button__orange" href="https://checkout.limitloginattempts.com/plan?from=plugin-premium-tab" target="_blank">' . __('Installed', 'limit-login-attempts-reloaded') . '</a>',
        'Micro Cloud'   => '<a class="button menu__item button__orange" href="https://checkout.limitloginattempts.com/plan?from=plugin-premium-tab" target="_blank">' . __('Upgrade now', 'limit-login-attempts-reloaded') . '</a>',
        'Premium'       => '<a class="button menu__item button__orange" href="https://checkout.limitloginattempts.com/plan?from=plugin-premium-tab" target="_blank">' . __('Upgrade now', 'limit-login-attempts-reloaded') . '</a>',
        'Premium +'     => '<a class="button menu__item button__orange" href="https://checkout.limitloginattempts.com/plan?from=plugin-premium-tab" target="_blank">' . __('Upgrade now', 'limit-login-attempts-reloaded') . '</a>',
        'Professional'  => '<a class="button menu__item button__orange" href="https://checkout.limitloginattempts.com/plan?from=plugin-premium-tab" target="_blank">' . __('Upgrade now', 'limit-login-attempts-reloaded') . '</a>',
    ],
    'Limit Number of Retry Attempts' => [
        'Free'          => 'Yes',
        'Micro Cloud'   => 'Yes',
        'Premium'       => 'Yes',
        'Premium +'     => 'Yes',
        'Professional'  => 'Yes',
    ],
    'Configurable Lockout Timing' => [
        'Free'          => 'Yes',
        'Micro Cloud'   => 'Yes',
        'Premium'       => 'Yes',
        'Premium +'     => 'Yes',
        'Professional'  => 'Yes',
    ],
    'Performance Optimizer' => [
        'description'   => 'Absorb failed login attempts from brute force bots in the cloud to keep your website at its optimal performance.',
        'Free'          => 'Lock',
        'Micro Cloud'   => 'Yes',
        'Premium'       => 'Yes',
        'Premium +'     => 'Yes',
        'Professional'  => 'Yes',
    ],
    'Block By Country' => [
        'description'   => 'Disable IPs from any region to disable logins.',
        'Free'          => 'Lock',
        'Micro Cloud'   => 'Yes',
        'Premium'       => 'Yes',
        'Premium +'     => 'Yes',
        'Professional'  => 'Yes',
    ],
    'Access Blocklist of Malicious IPs' => [
        'description' => 'Add another layer of protection from brute force bots by accessing a global database of known IPs with malicious activity.',
        'Free' => 'Yes',
        'Premium' => 'Yes',
        'Premium +' => 'Yes',
        'Professional' => 'Yes',
    ],
    'Auto IP Blocklist' => [
        'description' => 'Automatically add malicious IPs to your blocklist when triggered by the system.',
        'Free' => 'Yes',
        'Premium' => 'Yes',
        'Premium +' => 'Yes',
        'Professional' => 'Yes',
    ],
    'Access Active Cloud Blocklist' => [
        'description' => 'Use system wide data from over 10,000 WordPress websites to identify and block malicious IPs. This is an active list in real-time.',
        'Free' => 'Yes',
        'Premium' => 'Yes',
        'Premium +' => 'Yes',
        'Professional' => 'Yes',
    ],
    'Intelligent IP Blocking' => [
        'description' => 'Use active IP database via the cloud to automatically block users before they are able to make a failed login.',
        'Free' => 'Yes',
        'Premium' => 'Yes',
        'Premium +' => 'Yes',
        'Professional' => 'Yes',
    ],
    'Synchronize Lockouts & Safelists/Blocklists' => [
        'description' => 'Lockouts & safelists/blocklists can be shared between multiple domains to enhance protection.',
        'Free' => 'Yes',
        'Premium' => 'Yes',
        'Premium +' => 'Yes',
        'Professional' => 'Yes',
    ],
    'Premium Support' => [
        'Receive 1 on 1 technical support via email for any issues. Free support availabe in the WordPress support forum.',
        'Free' => 'Yes',
        'Premium' => 'Yes',
        'Premium +' => 'Yes',
        'Professional' => 'Yes',
    ],
    'buttons_footer' => [
        'Free' => '<button class="menu__item button__transparent_orange" data-bs-toggle="modal" data-bs-target="#download_modal_window" type="button">Download</button>',
        'Premium' => '<a href="#plans_menu_nav" class="link__style_unlink"><button class="menu__item button__orange">Subscribe</button></a>',
        'Premium +' => '<a href="#plans_menu_nav" class="link__style_unlink"><button class="menu__item button__orange">Subscribe</button></a>',
        'Professional' => '<a href="#plans_menu_nav" class="link__style_unlink"><button class="menu__item button__orange">Subscribe</button></a>',
    ],
];

return $compare_list;
