<?php
/**
 * Dashboard
 *
 * All copy, URLs and checklist state come from the controller via
 * get_dashboard_view_vars(); this template only assembles markup.
 *
 * @var string $active_app
 * @var bool $is_active_app_custom
 * @var string $block_sub_group
 * @var bool|string $is_exhausted
 *
 */

if ( ! defined( 'ABSPATH' ) ) exit();

$dashboard = $this->get_dashboard_view_vars( $active_app, $is_active_app_custom, $block_sub_group, $is_exhausted );

$setup_code        = $dashboard['setup_code'];
$api_stats         = $dashboard['api_stats'];
$chart_circle_data = $dashboard['chart_circle_data'];

$wp_locale = str_replace( '_', '-', get_locale() );
$is_tab_dashboard = true;

if ( $dashboard['show_onboarding'] ) {
    require_once( LLA_PLUGIN_DIR . 'views/onboarding-popup.php');
}
?>

<div id="llar-dashboard-page">
	<div class="dashboard-section-1 <?php echo esc_attr( $active_app ); ?>">
		<div class="info-box-1">
            <?php include_once( LLA_PLUGIN_DIR . 'views/chart-circle-failed-attempts-today.php'); ?>
        </div>

        <div class="info-box-2">
            <?php include_once( LLA_PLUGIN_DIR . 'views/chart-failed-attempts.php'); ?>
        </div>
        <?php if ( $dashboard['show_trial_block'] ) : ?>
		<div class="info-box-3">
            <div class="section-title__new">
                <div class="title"><?php echo $dashboard['trial_block']['title']; ?></div>
            </div>
            <div class="section-content">
                <div class="desc">
                    <ul class="list-unstyled">
                        <?php foreach ( $dashboard['trial_block']['bullets'] as $bullet ) : ?>
                        <li class="star">
                            <?php echo $bullet; ?>
                        </li>
                        <?php endforeach ?>
                    </ul>
                </div>
            </div>
            <div class="actions">
                <div class="actions__buttons actions__buttons--centered">
                    <a title="<?php echo esc_attr( $dashboard['trial_block']['cta_title'] ); ?>"
                       class="button menu__item button__orange button_micro_cloud link__style_unlink">
                        <?php echo $dashboard['trial_block']['cta_label']; ?>
                    </a>
                </div>
            </div>
        </div>
        <?php require_once( LLA_PLUGIN_DIR . 'views/micro-cloud-modal.php') ?>
        <?php elseif ( $dashboard['show_premium_disabled_block'] ) : ?>
            <div class="info-box-3">
                <div class="section-title__new">
                    <div class="title"><?php echo $dashboard['premium_disabled_block']['title']; ?></div>
                </div>
                <div class="section-content">
                    <div class="desc">
                        <?php echo $dashboard['premium_disabled_block']['desc']; ?>
                    </div>
                </div>
                <div class="actions">
                    <div class="actions__buttons">
                        <a href="<?php echo $dashboard['premium_disabled_block']['url']; ?>"
                           title="<?php echo esc_attr( $dashboard['premium_disabled_block']['cta_title'] ); ?>"
                           target="_blank"
                           class="link__style_unlink">
                            <button class="button menu__item col button__orange">
                                <?php echo $dashboard['premium_disabled_block']['button_label']; ?>
                            </button>
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
	</div>
	<div class="dashboard-section-3">
        <?php foreach ( $dashboard['quick_links'] as $link ) : ?>
        <div class="info-box-1">
            <div class="info-box-icon">
                <img src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/<?php echo $link['icon']; ?>">
            </div>
            <div class="info-box-content">
                <div class="title">
                    <a href="<?php echo $link['href']; ?>" class="link__style_unlink"<?php echo $link['target']; ?>>
                        <?php echo $link['title']; ?>
                    </a>
                </div>
                <div class="desc">
                    <?php echo $link['desc']; ?>
                </div>
            </div>
        </div>
        <?php endforeach ?>
    </div>

	<div class="dashboard-section-4">
        <div class="info-box-1">
	        <?php include_once( LLA_PLUGIN_DIR.'views/app-widgets/login-attempts.php'); ?>
        </div>

        <div class="info-box-2">
            <div class="section-title__new">
                <div class="title">
                    <?php echo $dashboard['checklist']['heading']; ?>
                </div>
                <div class="desc">
                    <?php echo $dashboard['checklist']['desc']; ?>
                </div>
            </div>
            <div class="section-content">
                <?php foreach ( $dashboard['checklist']['items'] as $item ) : ?>
                <div class="list">
                    <input type="checkbox" name="<?php echo $item['name']; ?>"<?php echo $item['checked']; ?><?php echo $item['disabled_attr']; ?> />
                    <span>
                        <?php echo $item['label']; ?>
                    </span>
                    <?php if ( $item['list_add'] ) : ?>
	                <span class="list-add"><?php echo $item['list_add']; ?></span>
                    <?php endif ?>
                    <div class="desc">
                        <?php echo $item['desc']; ?>
                    </div>
                </div>
                <?php endforeach ?>
            </div>
        </div>
    </div>
</div>
