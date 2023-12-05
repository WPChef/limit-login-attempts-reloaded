<?php

use LLAR\Core\Config;

if( !defined( 'ABSPATH' ) ) exit();

$active_app = Config::get( 'active_app' );
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
            <?php if( $active_app === 'local' ) : ?>
                <?php _e( 'You are currently using the free version of <strong>Limit Login Attempts Reloaded</strong>.', 'limit-login-attempts-reloaded' ); ?>
                <a href="https://www.limitloginattempts.com/activate/?from=plugin-premium-tab" class="link__style_unlink llar_turquoise" target="_blank">
                    <?php _e( 'If you purchased a premium plan, check your email for setup instructions (key included)', 'limit-login-attempts-reloaded' ); ?>
                </a>
            <?php elseif( $active_app === 'custom' ) : ?>
                <?php _e( 'You are currently using the premium version of Limit Login Attempts Reloaded.', 'limit-login-attempts-reloaded' ); ?>
            <?php endif ?>
        </div>
    </div>

    <?php if( $active_app === 'local' ) : ?>
        <div class="description-page">
            <h2 class="llar_typography-secondary">
                <?php _e( 'Why Should I Consider Premium?', 'limit-login-attempts-reloaded' ); ?>
            </h2>
            <div class="description-secondary">
                <?php _e( 'Although the free version offers great protection, the premium version includes an important feature called <b>IP Intelligence</b>. With IP intelligence, your website will be able to identify malicious IPs before they attempt a login, and absorb them into the cloud to save system resources. Your site will not only be more secure, but will operate at its optimal performance.', 'limit-login-attempts-reloaded' ); ?>
            </div>
        </div>
    <?php endif ?>

    <h3 class="title_page">
        <img src="<?php echo LLA_PLUGIN_URL ?>/assets/css/images/icon-magnifier.png">
        <?php _e( 'Features comparison', 'limit-login-attempts-reloaded' ); ?>
    </h3>

    <?php
        $features = array(
            'Features',
            'Free',
            'Micro Cloud',
            'Premium',
            'Premium +',
            'Professional',
        );


        $compare_list = require LLA_PLUGIN_DIR . '/resources/compare-plans-data.php';
    ?>

    <section class="llar-premium-plans-table">
        <div class="content">
            <table class="table table_background">
                <thead>
                <tr>
                    <?php foreach ($features as $item) : ?>
                        <td scope="col"><?php echo $item ?></td>
                    <?php endforeach; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($compare_list as $category => $list) : ?>
                    <tr>
                        <td>
                            <?php echo ($category === 'buttons_header' || $category === 'buttons_footer') ? '' : $category ?>
                            <div class="description">
                                <?php echo !empty($list['description']) ? $list['description'] : '' ?>
                            </div>
                        </td>
                        <?php foreach ($features as $item) :
                            if ($item === 'Features' || !isset($list[$item])) :
                                continue;
                            endif;
                            ?>
                            <td class="inner_fields">
                                <?php echo $list[$item] ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php require_once( LLA_PLUGIN_DIR . 'views/micro-cloud-modal.php')?>

