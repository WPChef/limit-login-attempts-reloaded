<?php

if( !defined( 'ABSPATH' ) ) exit();

?>

<div id="llar-setting-page-premium" class="llar-premium-page-wrapper">
    <div class="llar-premium-page-promo">
        <div class="section-1">
            <div class="text">
                <div class="title">
                    <?php _e( 'Limit Login Attempts Reloaded <strong>Premium</strong>', 'limit-login-attempts-reloaded' ); ?>
                </div>
                <ul class="links mt-1_5">
                    <li class="button tags tags_add">
                        <a href="https://www.limitloginattempts.com/features/?from=plugin-premium-tab" class="link__style_unlink gdpr-information-link" target="_blank">
                            <?php _e( 'Full feature list', 'limit-login-attempts-reloaded' ); ?>
                        </a>
                    </li>
                    <li class="button tags tags_add">
                        <a href="https://www.limitloginattempts.com/services/pre-sales-questions/?from=plugin-premium-tab" class="link__style_unlink gdpr-information-link" target="_blank">
                            <?php _e( 'Pre-sales FAQs', 'limit-login-attempts-reloaded' ); ?>
                        </a>
                    </li>
                    <li class="button tags tags_add">
                        <a href="https://www.limitloginattempts.com/contact-us/?from=plugin-premium-tab" class="link__style_unlink gdpr-information-link" target="_blank">
                            <?php _e( 'Ask a pre-sales question', 'limit-login-attempts-reloaded' ); ?>
                        </a>
                    </li>
                    <li class="button tags tags_add">
                        <a href="https://www.limitloginattempts.com/contact-us/?from=plugin-premium-tab" class="link__style_unlink gdpr-information-link" target="_blank">
                            <?php _e( 'Support', 'limit-login-attempts-reloaded' ); ?>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="action">
                <a class="button menu__item button__orange" href="https://checkout.limitloginattempts.com/plan?from=plugin-premium-tab" target="_blank">
                    <?php _e( 'Get It Here', 'limit-login-attempts-reloaded' ); ?>
                </a>
                <div class="label">
                    <span class="dashicons dashicons-external"></span>
                    <?php _e( 'Goes to LLAR checkout page', 'limit-login-attempts-reloaded' ); ?>
                </div>
            </div>
        </div>
        <div class="section-1__internal">
            <?php _e( 'You are currently using the free version of <strong>Limit Login Attempts Reloaded</strong>.', 'limit-login-attempts-reloaded' ); ?>
            <a href="https://www.limitloginattempts.com/activate/?from=plugin-premium-tab" class="link__style_unlink llar_turquoise" target="_blank">
                <?php _e( 'If you purchased a premium plan, check your email for setup instructions (key included)', 'limit-login-attempts-reloaded' ); ?>
            </a>
        </div>
    </div>

    <div class="description-page">
        <h2 class="llar_typography-secondary">
            <?php _e( 'Why Should I Consider Premium?', 'limit-login-attempts-reloaded' ); ?>
        </h2>
        <div class="description-secondary">
            <?php _e( 'Although the free version offers great protection, the premium version includes an important feature called <b>IP Intelligence</b>. With IP intelligence, your website will be able to identify malicious IPs before they attempt a login, and absorb them into the cloud to save system resources. Your site will not only be more secure, but will operate at its optimal performance.', 'limit-login-attempts-reloaded' ); ?>
        </div>
    </div>

    <h3 class="title_page">
        <img src="<?= LLA_PLUGIN_URL ?>/assets/css/images/icon-magnifier.png">
        <?php _e( 'Features comparison', 'limit-login-attempts-reloaded' ); ?>
    </h3>

    <div class="llar-premium-plans-table">
        <table>
            <tr>
                <th class="feature"></th>
                <th><img src="<?php echo esc_attr( LLA_PLUGIN_URL . '/assets/img/icon-256x256.png' ); ?>" alt=""><div class="plan-name"><?php _e( 'Free', 'limit-login-attempts-reloaded' ); ?></div></th>
                <th><img src="<?php echo esc_attr( LLA_PLUGIN_URL . '/assets/img/icon-256x256.png' ); ?>" alt=""><div class="plan-name"><?php _e( 'Premium', 'limit-login-attempts-reloaded' ); ?></div></th>
                <th><img src="<?php echo esc_attr( LLA_PLUGIN_URL . '/assets/img/icon-256x256.png' ); ?>" alt=""><div class="plan-name"><?php _e( 'Premium+', 'limit-login-attempts-reloaded' ); ?></div></th>
                <th><img src="<?php echo esc_attr( LLA_PLUGIN_URL . '/assets/img/icon-256x256.png' ); ?>" alt=""><div class="plan-name"><?php _e( 'Professional', 'limit-login-attempts-reloaded' ); ?></div></th>
            </tr>
            <tr class="table-actions">
                <td class="feature"></td>
                <td><span class="installed-label"><span class="dashicons dashicons-yes"></span> <?php _e( 'Installed', 'limit-login-attempts-reloaded' ); ?></span></td>
                <td><a class="button button-primary" href="https://checkout.limitloginattempts.com/plan?from=plugin-premium-tab" target="_blank"><?php _e( 'Upgrade now', 'limit-login-attempts-reloaded' ); ?></a></td>
                <td><a class="button button-primary" href="https://checkout.limitloginattempts.com/plan?from=plugin-premium-tab" target="_blank"><?php _e( 'Upgrade now', 'limit-login-attempts-reloaded' ); ?></a></td>
                <td><a class="button button-primary" href="https://checkout.limitloginattempts.com/plan?from=plugin-premium-tab" target="_blank"><?php _e( 'Upgrade now', 'limit-login-attempts-reloaded' ); ?></a></td>
            </tr>
            <tr>
                <td class="feature">
                    <div class="name"><?php _e( 'Limit Number of Retry Attempts', 'limit-login-attempts-reloaded' ); ?></div>
                </td>
                <td><span class="dashicons dashicons-yes"></span></td>
                <td><span class="dashicons dashicons-yes"></span></td>
                <td><span class="dashicons dashicons-yes"></span></td>
                <td><span class="dashicons dashicons-yes"></span></td>
            </tr>
            <tr>
                <td class="feature">
                    <div class="name"><?php _e( 'Configurable Lockout Timing', 'limit-login-attempts-reloaded' ); ?></div>
                </td>
                <td><span class="dashicons dashicons-yes"></span></td>
                <td><span class="dashicons dashicons-yes"></span></td>
                <td><span class="dashicons dashicons-yes"></span></td>
                <td><span class="dashicons dashicons-yes"></span></td>
            </tr>
            <tr>
                <td class="feature">
                    <div class="name"><?php _e( 'Performance Optimizer', 'limit-login-attempts-reloaded' ); ?></div>
                    <div class="desc"><?php _e( 'Absorb failed login attempts from brute force bots in the cloud to ' .
                            'keep your website at its optimal performance.', 'limit-login-attempts-reloaded' ); ?></div>
                </td>
                <td><span class="dashicons dashicons-no-alt"></span></td>
                <td>
                    <span class="dashicons dashicons-yes"></span>
                    <div class="feature-value"><?php _e( '100k requests per month', 'limit-login-attempts-reloaded' ); ?></div>
                </td>
                <td>
                    <span class="dashicons dashicons-yes"></span>
                    <div class="feature-value"><?php _e( '200k requests per month', 'limit-login-attempts-reloaded' ); ?></div>
                </td>
                <td>
                    <span class="dashicons dashicons-yes"></span>
                    <div class="feature-value"><?php _e( '300k requests per month', 'limit-login-attempts-reloaded' ); ?></div>
                </td>
            </tr>
            <tr>
                <td class="feature">
                    <div class="name"><?php _e( 'Block By Country', 'limit-login-attempts-reloaded' ); ?></div>
                    <div class="desc"><?php _e( 'Disable IPs from any region to disable logins.', 'limit-login-attempts-reloaded' ); ?></div>
                </td>
                <td><span class="dashicons dashicons-no-alt"></span></td>
                <td><span class="dashicons dashicons-no-alt"></span></td>
                <td><span class="dashicons dashicons-yes"></span></td>
                <td><span class="dashicons dashicons-yes"></span></td>
            </tr>
            <tr>
                <td class="feature">
                    <div class="name"><?php _e( 'Access Blocklist of Malicious IPs', 'limit-login-attempts-reloaded' ); ?></div>
                    <div class="desc"><?php _e( 'Add another layer of protection from brute force bots by accessing a global database of known IPs with malicious activity.', 'limit-login-attempts-reloaded' ); ?></div>
                </td>
                <td><span class="dashicons dashicons-no-alt"></span></td>
                <td><span class="dashicons dashicons-no-alt"></span></td>
                <td><span class="dashicons dashicons-yes"></span></td>
                <td><span class="dashicons dashicons-yes"></span></td>
            </tr>
            <tr>
                <td class="feature">
                    <div class="name"><?php _e( 'Auto IP Blocklist', 'limit-login-attempts-reloaded' ); ?></div>
                    <div class="desc"><?php _e( 'Automatically add malicious IPs to your blocklist when triggered by the system.', 'limit-login-attempts-reloaded' ); ?></div>
                </td>
                <td><span class="dashicons dashicons-no-alt"></span></td>
                <td><span class="dashicons dashicons-no-alt"></span></td>
                <td><span class="dashicons dashicons-no-alt"></span></td>
                <td><span class="dashicons dashicons-yes"></span></td>
            </tr>
            <tr>
                <td class="feature">
                    <div class="name"><?php _e( 'Access Active Cloud Blocklist', 'limit-login-attempts-reloaded' ); ?></div>
                    <div class="desc"><?php _e( 'Use system wide data from over 10,000 WordPress websites to identify and block malicious IPs. This is an active list in real-time.', 'limit-login-attempts-reloaded' ); ?></div>
                </td>
                <td><span class="dashicons dashicons-no-alt"></span></td>
                <td><span class="dashicons dashicons-no-alt"></span></td>
                <td><span class="dashicons dashicons-no-alt"></span></td>
                <td><span class="dashicons dashicons-yes"></span></td>
            </tr>            
            <tr>
                <td class="feature">
                    <div class="name"><?php _e( 'Intelligent IP Blocking', 'limit-login-attempts-reloaded' ); ?></div>
                    <div class="desc"><?php _e( 'Use active IP database via the cloud to automatically block users ' .
                            'before they are able to make a failed login.', 'limit-login-attempts-reloaded' ); ?></div>
                </td>
                <td><span class="dashicons dashicons-no-alt"></span></td>
                <td><span class="dashicons dashicons-yes"></span></td>
                <td><span class="dashicons dashicons-yes"></span></td>
                <td><span class="dashicons dashicons-yes"></span></td>
            </tr>
            <tr>
                <td class="feature">
                    <div class="name"><?php _e( 'Synchronize Lockouts & Safelists/Blocklists', 'limit-login-attempts-reloaded' ); ?></div>
                    <div class="desc"><?php _e( 'Lockouts & safelists/blocklists can be shared between multiple domains to enhance protection.', 'limit-login-attempts-reloaded' ); ?></div>
                </td>
                <td><span class="dashicons dashicons-no-alt"></span></td>
                <td><span class="dashicons dashicons-yes"></span></td>
                <td><span class="dashicons dashicons-yes"></span></td>
                <td><span class="dashicons dashicons-yes"></span></td>
            </tr>
            <tr>
                <td class="feature">
                    <div class="name"><?php _e( 'Premium Support', 'limit-login-attempts-reloaded' ); ?></div>
                    <div class="desc"><?php _e( 'Receive 1 on 1 technical support via email for any issues. Free support availabe in the <a href="https://wordpress.org/support/plugin/limit-login-attempts-reloaded/" target="_blank">WordPress support forum</a>.', 'limit-login-attempts-reloaded' ); ?></div>
                </td>
                <td><span class="dashicons dashicons-no-alt"></span></td>
                <td><span class="dashicons dashicons-yes"></span></td>
                <td><span class="dashicons dashicons-yes"></span></td>
                <td><span class="dashicons dashicons-yes"></span></td>
            </tr>
            <tr class="table-actions">
                <td class="feature"></td>
                <td><span class="installed-label"><span class="dashicons dashicons-yes"></span> <?php _e( 'Installed', 'limit-login-attempts-reloaded' ); ?></span></td>
                <td><a class="button button-primary" href="https://checkout.limitloginattempts.com/plan?from=plugin-premium-tab" target="_blank"><?php _e( 'Upgrade now', 'limit-login-attempts-reloaded' ); ?></a></td>
                <td><a class="button button-primary" href="https://checkout.limitloginattempts.com/plan?from=plugin-premium-tab" target="_blank"><?php _e( 'Upgrade now', 'limit-login-attempts-reloaded' ); ?></a></td>
                <td><a class="button button-primary" href="https://checkout.limitloginattempts.com/plan?from=plugin-premium-tab" target="_blank"><?php _e( 'Upgrade now', 'limit-login-attempts-reloaded' ); ?></a></td>
            </tr>
        </table>
    </div>
</div>


<?php
$features = [
'Features',
'Free',
'Premium',
'Premium +',
'Professional',
];

// test svg integrated inline
$lock = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
<path fill-rule="evenodd" clip-rule="evenodd" d="M7.46075 7.8885C7.46075 5.38174 9.49288 3.34961 11.9996 3.34961C14.5064 3.34961 16.5385 5.38174 16.5385 7.8885V8.24669C17.9083 8.70059 18.9812 9.77917 19.4275 11.1526C19.6501 11.8376 19.6499 12.6576 19.6497 13.9742C19.6497 14.0191 19.6496 14.0646 19.6496 14.1107C19.6496 14.1568 19.6497 14.2023 19.6497 14.2472C19.6499 15.5639 19.6501 16.3839 19.4275 17.0689C18.9785 18.4507 17.8952 19.534 16.5133 19.983C15.8283 20.2056 15.0084 20.2054 13.6917 20.2052C13.6468 20.2052 13.6013 20.2052 13.5552 20.2052H10.4441C10.398 20.2052 10.3525 20.2052 10.3076 20.2052C8.99091 20.2054 8.17095 20.2056 7.48594 19.983C6.10413 19.534 5.02077 18.4507 4.57179 17.0689C4.34922 16.3839 4.34937 15.5639 4.34963 14.2472C4.34963 14.2023 4.34964 14.1568 4.34964 14.1107C4.34964 14.0646 4.34963 14.0191 4.34963 13.9742C4.34937 12.6576 4.34922 11.8376 4.57179 11.1526C5.01804 9.77917 6.09097 8.70059 7.46075 8.24669V7.8885ZM8.76075 8.03836C9.20336 8.01622 9.72798 8.01624 10.3726 8.01627H13.6266C14.2713 8.01624 14.7959 8.01622 15.2385 8.03836V7.8885C15.2385 6.09971 13.7884 4.64961 11.9996 4.64961C10.2109 4.64961 8.76075 6.09971 8.76075 7.8885V8.03836ZM10.4441 9.31628C9.29663 9.31628 8.67666 9.31854 8.21874 9.39569C8.09299 9.41687 7.98538 9.44305 7.88766 9.4748C6.90162 9.79518 6.12855 10.5683 5.80817 11.5543C5.65766 12.0175 5.64964 12.6125 5.64964 14.1107C5.64964 15.609 5.65766 16.2039 5.80817 16.6671C6.12855 17.6532 6.90162 18.4263 7.88766 18.7466C8.35086 18.8971 8.94583 18.9052 10.4441 18.9052H13.5552C15.0535 18.9052 15.6484 18.8971 16.1116 18.7466C17.0977 18.4263 17.8707 17.6532 18.1911 16.6671C18.3416 16.2039 18.3496 15.609 18.3496 14.1107C18.3496 12.6125 18.3416 12.0175 18.1911 11.5543C17.8707 10.5683 17.0977 9.79518 16.1116 9.4748C16.0139 9.44305 15.9063 9.41687 15.7806 9.39569C15.3226 9.31854 14.7027 9.31628 13.5552 9.31628H10.4441ZM9.79409 12.5552C9.79409 11.3371 10.7815 10.3496 11.9996 10.3496C13.2177 10.3496 14.2052 11.3371 14.2052 12.5552C14.2052 13.547 13.5505 14.386 12.6496 14.6634V17.2218C12.6496 17.5808 12.3586 17.8718 11.9996 17.8718C11.6407 17.8718 11.3496 17.5808 11.3496 17.2218V14.6634C10.4488 14.386 9.79409 13.547 9.79409 12.5552ZM11.9996 11.6496C11.4995 11.6496 11.0941 12.055 11.0941 12.5552C11.0941 13.0553 11.4995 13.4607 11.9996 13.4607C12.4998 13.4607 12.9052 13.0553 12.9052 12.5552C12.9052 12.055 12.4998 11.6496 11.9996 11.6496Z" fill="#2A2F40"/>
</svg>';

$compare_list = require LLA_PLUGIN_DIR . '/resources/compare-plans-data.php';
?>

<section id="compare_section" class="compare__block compare_plans">
    <div class="content container-xxl">
        <table class="table table_background">
            <thead>
            <tr>
                <?php foreach ($features as $item) : ?>
                    <td scope="col"><?= $item ?></td>
                <?php endforeach; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($compare_list as $category => $list) : ?>
                <tr>
                    <td>
                        <?= $category !== 'buttons' ? $category : '' ?>
                    </td>
                    <?php foreach ($features as $item) :
                        if ($item === 'Features') :
                            continue;
                        endif; ?>
                        <?php
                        $shouldBeBlack = ($item === 'Free' && ($list[$item] === 'Yes'));
                        $class = $shouldBeBlack ? 'black' : (($item !== 'Free' && ($list[$item] === 'Yes')) ? 'orange' : 'black');
                        $class = $category === 'buttons' ? $class . ' text-center' : $class;

                        $string = $list[$item] === 'Yes' ? '&#x2713;' : ($list[$item] === 'Lock' ? $lock : $list[$item]);
                        ?>
                        <td class="<?= $class ?>">
                            <?= $string ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

