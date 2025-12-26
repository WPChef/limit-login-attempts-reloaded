<?php
/**
 * Premium Page
 *
 * @var bool $is_active_app_custom
 * @var string $block_sub_group
 *
 */

use LLAR\Core\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

$setup_code = Config::get( 'app_setup_code' );
$is_local_no_empty_setup_code = ( ! $is_active_app_custom && ! empty( $setup_code ) );

$min_plan = 'Premium';
$plans = $this->array_name_plans();
$is_premium = ( $is_active_app_custom && $plans[$block_sub_group] >= $plans[$min_plan] );
?>

<div id="llar-setting-page-premium" class="llar-premium-page-wrapper">

    <?php if ( !$is_premium ) : ?>
    <div class="llar-notification-premium">

        <svg class="llar-cloud-left" xmlns="http://www.w3.org/2000/svg" width="50" height="34" viewBox="0 0 50 34" fill="none">
            <path opacity="0.7" d="M38.9531 12.3636C39.025 11.8567 39.0625 11.3405 39.0625 10.8182C39.0625 4.85273 34.1562 0 28.125 0C24.2531 0 20.6781 2.06164 18.7437 5.27927C17.7687 4.85582 16.7156 4.63636 15.625 4.63636C11.3187 4.63636 7.8125 8.10436 7.8125 12.3636C7.8125 12.5213 7.81563 12.6758 7.82813 12.8273C3.26875 14.1347 0 18.3322 0 23.1818C0 29.1473 4.90625 34 10.9375 34H39.0625C45.0938 34 50 29.1473 50 23.1818C50 17.1824 45.0812 12.3142 38.9531 12.3636Z" fill="#ECFAFB"/>
        </svg>
        <div class="llar-notification-premium-text">
		    <?php _e( 'New users receive <span>37% OFF</span> their first year when they upgrade to Premium', 'limit-login-attempts-reloaded' ); ?>
        </div>
        <svg class="llar-cloud-right" xmlns="http://www.w3.org/2000/svg" width="50" height="34" viewBox="0 0 50 34" fill="none">
            <path opacity="0.7" d="M38.9531 12.3636C39.025 11.8567 39.0625 11.3405 39.0625 10.8182C39.0625 4.85273 34.1562 0 28.125 0C24.2531 0 20.6781 2.06164 18.7437 5.27927C17.7687 4.85582 16.7156 4.63636 15.625 4.63636C11.3187 4.63636 7.8125 8.10436 7.8125 12.3636C7.8125 12.5213 7.81563 12.6758 7.82813 12.8273C3.26875 14.1347 0 18.3322 0 23.1818C0 29.1473 4.90625 34 10.9375 34H39.0625C45.0938 34 50 29.1473 50 23.1818C50 17.1824 45.0812 12.3142 38.9531 12.3636Z" fill="#ECFAFB"/>
        </svg>

    </div>
	<?php endif ?>
    <div class="llar-premium-page-promo mt-1_5">
        <a href='https://wordpress.org/support/plugin/limit-login-attempts-reloaded/reviews/?filter=5' class="rating-badge" target="_blank"></a>
        <div class="section-1">
            <div class="text">
                <div class="title">
                    <?php if ( $block_sub_group && $block_sub_group === 'Micro Cloud' ) : ?>
                        <?php _e( 'Limit Login Attempts Reloaded <strong>Micro Cloud</strong>', 'limit-login-attempts-reloaded' ); ?>
                    <?php else : ?>
	                    <?php _e( 'Limit Login Attempts Reloaded <strong>Premium</strong>', 'limit-login-attempts-reloaded' ); ?>
                    <?php endif; ?>
                </div>
                <ul class="links mt-1_5">
                    <li class="button tags tags_add">
                        <a href="https://www.limitloginattempts.com/info.php?id=16" class="link__style_unlink gdpr-information-link" target="_blank">
                            <?php _e( 'Full feature list', 'limit-login-attempts-reloaded' ); ?>
                        </a>
                    </li>
                    <li class="button tags tags_add">
                        <a href="https://www.limitloginattempts.com/info.php?id=17" class="link__style_unlink gdpr-information-link" target="_blank">
                            <?php _e( 'Pre-sales FAQs', 'limit-login-attempts-reloaded' ); ?>
                        </a>
                    </li>
                    <li class="button tags tags_add">
                        <a href="https://www.limitloginattempts.com/info.php?id=18" class="link__style_unlink gdpr-information-link" target="_blank">
                            <?php _e( 'Ask a pre-sales question', 'limit-login-attempts-reloaded' ); ?>
                        </a>
                    </li>
                    <li class="button tags tags_add">
                        <a href="https://www.limitloginattempts.com/info.php?id=19" class="link__style_unlink gdpr-information-link" target="_blank">
                            <?php _e( 'Support', 'limit-login-attempts-reloaded' ); ?>
                        </a>
                    </li>
                </ul>
            </div>
            <?php if ( ! $is_premium ) : ?>
                <div class="action">
                    <a class="button menu__item button__orange" href="<?php echo esc_url( ( $block_sub_group === 'Micro Cloud' )
                        ? add_query_arg('id', '8', $this->info_upgrade_url())
                        : 'https://www.limitloginattempts.com/info.php?id=23' ); ?>" target="_blank">
                        <?php _e( 'Get It Here', 'limit-login-attempts-reloaded' ); ?>
                    </a>
                </div>
	        <?php endif; ?>
        </div>
        <div class="section-1__internal">
            <?php if( $active_app === 'local' ) : ?>
                <?php _e( 'You are currently using the free version of <strong>Limit Login Attempts Reloaded</strong>.', 'limit-login-attempts-reloaded' ); ?>
                <span class="llar_turquoise">
                    <?php _e( 'If you purchased a premium plan, check your email for setup instructions (Setup Code included)', 'limit-login-attempts-reloaded' ); ?>
                </span>
            <?php elseif( $block_sub_group ) : ?>
                <?php if( $block_sub_group === 'Micro Cloud' ) : ?>
                    <?php _e( 'You are currently using Micro Cloud, which provides access to premium cloud app on a limited basis. To prevent interruption, upgrade to one of our paid plans below.', 'limit-login-attempts-reloaded' ); ?>
                <?php else : ?>
                    <?php _e( 'You are currently using the premium version of Limit Login Attempts Reloaded.', 'limit-login-attempts-reloaded' ); ?>
	            <?php endif ?>
            <?php endif ?>
        </div>
    </div>

    <?php if( $active_app === 'local' ) : ?>
        <div class="description-page">
            <h3 class="llar_typography-secondary">
                <?php _e( 'Why Should I Consider Premium?', 'limit-login-attempts-reloaded' ); ?>
            </h3>
            <div class="description-secondary">
                <?php _e( 'Although the free version offers basic protection, the premium version includes an important feature called <b>IP Intelligence</b>. With IP intelligence, your website will be able to identify malicious IPs before they attempt a login, and absorb them into the cloud to save system resources. Your site will not only be more secure, but will operate at its optimal performance.', 'limit-login-attempts-reloaded' ); ?>
            </div>
        </div>
    <?php endif ?>

    <h3 class="title_page">
        <img src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/icon-magnifier.png">
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

        if ( $is_local_no_empty_setup_code ) {
	        $key = array_search('Micro Cloud', $features);

	        if ($key !== false) {
		        unset($features[$key]);
	        }
        }

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
                            <div class="category">
	                            <?php echo ($category === 'buttons_header' || $category === 'buttons_footer') ? '' : $category ?>
                            </div>
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

