<?php
/**
 * Dashboard
 *
 * @var string $active_app
 * @var bool $is_active_app_custom
 * @var string $block_sub_group
 *
 */

use LLAR\Core\CloudApp;
use LLAR\Core\Config;
use LLAR\Core\Helpers;
use LLAR\Core\LimitLoginAttempts;

if ( ! defined( 'ABSPATH' ) ) exit();

$api_stats = $is_active_app_custom ? LimitLoginAttempts::$cloud_app->stats() : false;

$setup_code = Config::get( 'app_setup_code' );

$wp_locale = str_replace( '_', '-', get_locale() );
$is_tab_dashboard = true;

$url_site =  is_multisite() ? network_site_url() : site_url();

if ( ! $is_active_app_custom && empty( $setup_code ) ) {
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
        <?php if ( ! $is_active_app_custom && empty( $setup_code ) ) : ?>
		<div class="info-box-3">
            <div class="section-title__new">
                <div class="title"><?php _e( 'Enable Micro Cloud (FREE)', 'limit-login-attempts-reloaded' ); ?></div>
            </div>
            <div class="section-content">
                <div class="desc">
                    <ul class="list-unstyled">
                        <li class="star">
                            <?php _e( 'Help us secure our network by providing access to your login IP data.', 'limit-login-attempts-reloaded' ); ?>
                        </li>
                        <li class="star">
                            <?php _e( 'In return, receive access to our premium features up to 1,000 requests per month, and 100 for each subsequent month.', 'limit-login-attempts-reloaded' ); ?>
                        </li>
                        <li class="star">
                            <?php _e( 'Once the allocated requests are consumed, the premium app will switch back to the free version and reset the following month.', 'limit-login-attempts-reloaded' ); ?>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="actions">
                <div class="actions__buttons">
                    <a href="https://www.limitloginattempts.com/premium-security-zero-cost-discover-the-benefits-of-micro-cloud/"
                       title="Learn More"
                       target="_blank"
                       class="button menu__item button__transparent_orange link__style_unlink">
                        <?php _e( 'Learn More', 'limit-login-attempts-reloaded' ); ?>
                    </a>
                    <a title="Upgrade To Micro Cloud"
                       class="button menu__item button__orange button_micro_cloud link__style_unlink">
                        <?php _e( 'Get Started', 'limit-login-attempts-reloaded' ); ?>
                    </a>
                </div>
                <div class="remark">
	                <?php _e( '* A request is utilized when our cloud app validates an IP before it is able to perform a login attempt.', 'limit-login-attempts-reloaded' ); ?>
                </div>
            </div>
        </div>
        <?php require_once( LLA_PLUGIN_DIR . 'views/micro-cloud-modal.php') ?>
        <?php elseif ( ! $is_active_app_custom && ! empty( $setup_code ) ) : ?>
            <div class="info-box-3">
                <div class="section-title__new">
                    <div class="title"><?php _e( 'Premium Protection Disabled', 'limit-login-attempts-reloaded' ); ?></div>
                </div>
                <div class="section-content">
                    <div class="desc">
                        <?php _e( 'As a free user, your local server is absorbing the traffic brought on by brute force attacks, potentially slowing down your website. Upgrade to Premium today to outsource these attacks through our cloud app, and slow down future attacks with advanced throttling.', 'limit-login-attempts-reloaded' ); ?>
                    </div>
                </div>
                <div class="actions">
                    <div class="actions__buttons">
                        <a href="https://www.limitloginattempts.com/upgrade/?from=plugin-dashboard-cta"
                           title="Upgrade To Premium"
                           target="_blank"
                           class="link__style_unlink">
                            <button class="button menu__item col button__orange">
                                <?php _e( 'Upgrade to Premium', 'limit-login-attempts-reloaded' ); ?>
                            </button>
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
	</div>
	<div class="dashboard-section-3">
        <div class="info-box-1">
            <div class="info-box-icon">
                <img src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/icon-exploitation.png">
            </div>
            <div class="info-box-content">
                <div class="title">
                    <a href="<?php echo $this->get_options_page_uri('logs-'.$active_app); ?>" class="link__style_unlink">
                        <?php _e( 'Tools', 'limit-login-attempts-reloaded' ); ?>
                    </a>
                </div>
                <div class="desc">
                    <?php _e( 'View lockouts logs, block or whitelist usernames or IPs, and more.', 'limit-login-attempts-reloaded' ); ?>
                </div>
            </div>
        </div>
        <div class="info-box-1">
            <div class="info-box-icon">
                <img src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/icon-help.png">
            </div>
            <div class="info-box-content">
                <div class="title">
                    <a href="https://www.limitloginattempts.com/info.php?from=plugin-dashboard-help" class="link__style_unlink" target="_blank">
                        <?php _e( 'Help', 'limit-login-attempts-reloaded' ); ?>
                    </a>
                </div>
                <div class="desc">
                    <?php _e( 'Find the documentation and help you need.', 'limit-login-attempts-reloaded' ); ?>
                </div>
            </div>
        </div>
        <div class="info-box-1">
            <div class="info-box-icon">
                <img src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/icon-web.png">
            </div>
            <div class="info-box-content">
                <div class="title">
                    <a href="<?php echo $this->get_options_page_uri('settings'); ?>" class="link__style_unlink">
                        <?php _e( 'Global Options', 'limit-login-attempts-reloaded' ); ?>
                    </a>
                </div>
                <div class="desc">
                    <?php _e( 'Many options such as notifications, alerts, premium status, and more.', 'limit-login-attempts-reloaded' ); ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ( $is_active_app_custom ) : ?>
	<div class="dashboard-section-4">
        <?php

        $lockout_notify = explode( ',', Config::get( 'lockout_notify' ) );
        $email_checked = in_array( 'email', $lockout_notify ) ? ' checked ' : '';

        $checklist = Config::get( 'checklist' );
        $is_checklist =  $checklist === 'true' ? ' checked disabled' : '';

        $min_plan = 'Premium';
        $plans = $this->array_name_plans();
        $upgrade_premium = ( $is_active_app_custom && $plans[$block_sub_group] >= $plans[$min_plan]) ? ' checked' : '';
        $block_by_country = $block_sub_group ? $this->info_block_by_country() : false;
        $block_by_country_disabled = $block_sub_group ? '' : ' disabled';
        $is_by_country =  $block_by_country ? ' checked disabled' : $block_by_country_disabled;
        $is_auto_update_choice = (Helpers::is_auto_update_enabled() && !Helpers::is_block_automatic_update_disabled()) ? ' checked' : '';


        $app_config = Config::get( 'app_config' );
        $full_log_url = !empty( $app_config['key'] ) ? 'https://my.limitloginattempts.com/logs?key=' . esc_attr( $app_config['key'] ) : false;

        ?>
        <div class="info-box-1">
            <div class="section-title__new">
                <div class="title">
                    <?php _e( 'Successful Login Attempts', 'limit-login-attempts-reloaded' ) ?>
                </div>
            </div>
            <div class="section-content">
                <div class="llar-table-scroll-wrap llar-app-login-infinity-scroll">
                    <table class="form-table llar-table-app-login">
                        <thead>
                        <tr>
                            <th scope="col"><?php _e( "Time", 'limit-login-attempts-reloaded' ); ?></th>
                            <th scope="col"><?php _e( "Login", 'limit-login-attempts-reloaded' ); ?></th>
                            <th scope="col"><?php _e( "IP", 'limit-login-attempts-reloaded' ); ?></th>
                            <th scope="col"><?php _e( "Role", 'limit-login-attempts-reloaded' ); ?></th>
                        </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot class="table-inline-preloader">
                        <tr>
                            <td colspan="100%">
                                <div class="load-more-button"><a href="#">
							            <?php _e( "Load older events", 'limit-login-attempts-reloaded' ); ?>
                                    </a>
                                </div>
                                <div class="preloader-row">
                                    <span class="preloader-icon"></span>
                                    <span class="preloader-text">
                                        <?php echo sprintf(
                                            __( 'Loading older events, skipping ACL events. <a href="%s" target="_blank">Full logs</a>', 'limit-login-attempts-reloaded' ),
                                            $full_log_url);
                                        ?>
                                    </span>
                                </div>
                            </td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
                <script type="text/javascript">
                    ;(function($){

                        $(document).ready(function () {

                            let $log_table_body = $('.llar-table-app-login tbody'),
                                $preloader = $log_table_body.next('.table-inline-preloader'),
                                $load_more_btn = $preloader.find('.load-more-button a'),
                                loading_data = false,
                                page_offset = '',
                                page_limit = 5,
                                total_loaded = 0;

                            load_login_data();

                            $('.llar-global-reload-btn').on('click', function() {
                                page_offset = '';
                                $log_table_body.find('> tr').remove();
                                $preloader.removeClass('hidden');
                                total_loaded = 0;
                                load_login_data();
                            });

                            $load_more_btn.on('click', function(e) {
                                e.preventDefault();
                                total_loaded = 0;
                                load_login_data();
                            });

                            function load_login_data() {

                                if ( page_offset === false ) {
                                    return;
                                }

                                $preloader.addClass('loading');
                                loading_data = true;

                                $.post(ajaxurl, {
                                    action: 'app_load_successful_login',
                                    offset: page_offset,
                                    limit: page_limit,
                                    sec: '<?php echo wp_create_nonce( "llar-app-load-login" ); ?>'
                                }, function(response){

                                    $preloader.removeClass('loading');

                                    if(response.success) {

                                        if(response.data.html) {
                                            $log_table_body.append(response.data.html);
                                        }

                                        total_loaded += response.data.total_items;

                                        if(response.data.offset) {
                                            page_offset = response.data.offset;

                                            if(response.data.total_items < page_limit && total_loaded < page_limit) {

                                                load_login_data();
                                            }

                                        } else {
                                            $preloader.addClass('hidden');
                                            page_offset = false;
                                        }

                                        loading_data = false;

                                        setTimeout(function () {
                                            button_login_data_open();
                                        }, 500)

                                    }

                                });

                            }


                            function button_login_data_open() {

                                const $llar_add_login_open = $('.llar-add-login-open')

                                $llar_add_login_open.on( 'click', function () {

                                    const dashicons = $(this).find('.dashicons');
                                    const dashicons_down = 'dashicons-arrow-down-alt2';
                                    const dashicons_up = 'dashicons-arrow-up-alt2';
                                    const parent_tr = $( this ).closest( 'tr' );
                                    const hidden_row = parent_tr.next();

                                    if ( hidden_row.hasClass( 'table-row-open' ) ) {

                                        dashicons.removeClass(dashicons_up);
                                        dashicons.addClass(dashicons_down);
                                        hidden_row.removeClass( 'table-row-open' );
                                    } else {

                                        dashicons.removeClass(dashicons_down);
                                        dashicons.addClass(dashicons_up);
                                        hidden_row.addClass( 'table-row-open' );
                                    }
                                })
                            }
                        });

                    })(jQuery);
                </script>
            </div>
        </div>

        <div class="info-box-2">
            <div class="section-title__new">
                <div class="title">
                    <?php _e( 'Login Security Checklist', 'limit-login-attempts-reloaded' ) ?>
                </div>
                <div class="desc">
                    <?php _e( 'Recommended tasks to greatly improve the security of your website.', 'limit-login-attempts-reloaded' ) ?>
                </div>
            </div>
            <div class="section-content">
                <div class="list">
                    <input type="checkbox" name="lockout_notify_email"<?php echo $email_checked ?> disabled />
                    <span>
                        <?php echo __( 'Enable Lockout Email Notifications', 'limit-login-attempts-reloaded' ); ?>
                    </span>
                    <div class="desc">
                        <?php echo sprintf(
                            __( '<a class="link__style_unlink llar_turquoise" href="%s">Enable email notifications</a> to receive timely alerts and updates via email', 'limit-login-attempts-reloaded' ),
	                        $url_site . '/wp-admin/admin.php?page=limit-login-attempts&tab=settings#llar_lockout_notify'
                        ); ?>
                    </div>
                </div>
                <div class="list">
                    <input type="checkbox" name="strong_account_policies"<?php echo $is_checklist ?> />
                    <span>
                        <?php echo __( 'Implement strong account policies', 'limit-login-attempts-reloaded' ); ?>
                    </span>
                    <div class="desc">
                        <?php echo sprintf(
                            __( '<a class="link__style_unlink llar_turquoise" href="%s" target="_blank">Read our guide</a> on implementing and enforcing strong password policies in your organization.', 'limit-login-attempts-reloaded' ),
	                        'https://www.limitloginattempts.com/info.php?id=1'
                        ); ?>
                    </div>
                </div>
                <div class="list">
                    <input type="checkbox" name="block_by_country"<?php echo $is_by_country . $block_by_country_disabled?> />
                    <span>
                        <?php echo __( 'Deny/Allow countries (Premium Users)', 'limit-login-attempts-reloaded' ); ?>
                    </span>
                    <div class="desc">
                        <?php $link__allow_deny = $block_by_country
                            ? $url_site . '/wp-admin/admin.php?page=limit-login-attempts&tab=logs-custom'
                            : 'https://www.limitloginattempts.com/info.php?id=2' ?>
                        <?php echo sprintf(
                            __( '<a class="link__style_unlink llar_turquoise" href="%s" target="_blank">Allow or Deny countries</a> to ensure only legitimate users login.', 'limit-login-attempts-reloaded' ),
                            $link__allow_deny
                        ); ?>
                    </div>
                </div>
                <div class="list">
                    <input type="checkbox" name="auto_update_choice"<?php echo $is_auto_update_choice ?> disabled />
                    <span>
                        <?php echo __( 'Turn on plugin auto-updates', 'limit-login-attempts-reloaded' ); ?>
                    </span>
                    <div class="desc">
                        <?php if (!empty($is_auto_update_choice)) :
                            _e( 'Enable automatic updates to ensure that the plugin stays current with the latest software patches and features.', 'limit-login-attempts-reloaded' );
                        else :
                            _e( '<a class="link__style_unlink llar_turquoise" href="llar_auto_update_choice">Enable automatic updates</a> to ensure that the plugin stays current with the latest software patches and features.', 'limit-login-attempts-reloaded' );
                        endif; ?>
                    </div>
                </div>
                <div class="list">
                    <input type="checkbox" name="upgrade_premium" <?php echo $upgrade_premium ?> disabled />
                    <span>
                        <?php _e( 'Upgrade to Premium', 'limit-login-attempts-reloaded' ); ?>
                    </span>
                    <div class="desc">
                        <?php _e( 'Upgrade to our premium version for advanced protection.', 'limit-login-attempts-reloaded' ); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
