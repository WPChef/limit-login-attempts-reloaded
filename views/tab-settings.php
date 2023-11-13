<?php

use LLAR\Core\Config;
use LLAR\Core\Helpers;

if( !defined( 'ABSPATH' ) ) exit();


/**
 * Temporary function to generate lorem ipsum
 *
 * @param $length
 * @return false|string
 */
function generate_lorem_ipsum($length) {

    $lorem_ipsum = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.';

    return substr($lorem_ipsum, 0, $length);
}

/**
 * @var $this LLAR\Core\LimitLoginAttempts
 */

$gdpr = Config::get( 'gdpr' );
$gdpr_message = Config::get( 'gdpr_message' );

$v = explode( ',', Config::get( 'lockout_notify' ) );
$email_checked = in_array( 'email', $v ) ? ' checked ' : '';

$show_top_level_menu_item = Config::get( 'show_top_level_menu_item' );
$show_top_bar_menu_item = Config::get( 'show_top_bar_menu_item' );
$hide_dashboard_widget = Config::get( 'hide_dashboard_widget' );
$show_warning_badge = Config::get( 'show_warning_badge' );

$admin_notify_email = Config::get( 'admin_notify_email' );
$admin_email_placeholder = ( !is_network_admin() ) ? get_option( 'admin_email' ) : get_site_option( 'admin_email' );

$trusted_ip_origins = Config::get( 'trusted_ip_origins' );
$trusted_ip_origins = ( is_array( $trusted_ip_origins ) && !empty( $trusted_ip_origins ) ) ? implode( ", ", $trusted_ip_origins ) : 'REMOTE_ADDR';

$active_app = Config::get( 'active_app' );
$app_setup_code = Config::get( 'app_setup_code' );
$active_app_config = Config::get( 'app_config' );

?>
<?php if( isset( $_GET['llar-cloud-activated'] ) && !empty( $active_app_config['messages']['setup_success'] ) ) : ?>
<div class="llar-app-notice success">
    <p><?php echo $active_app_config['messages']['setup_success']; ?></p>
</div>
<?php endif; ?>


<div id="llar-setting-page">
    <h3 class="title_page">
        <svg class="icon-page-setting">
            <use xlink:href="<?= LLA_PLUGIN_URL ?>/assets/css/images/sprite-add.svg#icon-exploitation"></use>
        </svg>
        <?php echo __( 'General Settings', 'limit-login-attempts-reloaded' ); ?>
    </h3>
    <div class="description-page">
        <?php echo __( 'These settings are independent of the apps (see below).', 'limit-login-attempts-reloaded' ); ?>
    </div>
    <form action="<?php echo $this->get_options_page_uri('settings'); ?>" method="post">

        <?php wp_nonce_field( 'limit-login-attempts-options' ); ?>

        <?php if ( is_network_admin() ): ?>
        <input type="checkbox" name="allow_local_options" <?php echo Config::get( 'allow_local_options' ) ? 'checked' : '' ?> value="1"/> <?php esc_html_e( 'Let network sites use their own settings', 'limit-login-attempts-reloaded' ); ?>
            <p class="description"><?php esc_html_e('If disabled, the global settings will be forcibly applied to the entire network.', 'limit-login-attempts-reloaded') ?></p>
        <?php elseif ( Helpers::is_network_mode() ): ?>
        <input type="checkbox" name="use_global_options" <?php echo Config::get('use_local_options' ) ? '' : 'checked' ?> value="1" class="use_global_options"/> <?php echo __( 'Use global settings', 'limit-login-attempts-reloaded' ); ?><br/>
            <script>
                jQuery(function($){
                    var first = true;
                    $('.use_global_options').change( function(){
                        var form = $('.llar-settings-wrap');
                        form.stop();

                        if ( this.checked )
                            first ? form.hide() : form.fadeOut();
                        else
                            first ? form.show() : form.fadeIn();

                        first = false;
                    }).change();
                });
            </script>
        <?php endif ?>

        <div class="llar-settings-wrap">
            <table class="form-table">
                <tr>
                    <th scope="row" valign="top"><?php echo __( 'GDPR compliance', 'limit-login-attempts-reloaded' ); ?></th>
                    <td>
                        <input type="checkbox" name="gdpr" value="1" <?php if($gdpr): ?> checked <?php endif; ?>/>
                        <?php echo __( 'this makes the plugin <a href="https://gdpr-info.eu/" class="unlink link__style_unlink" target="_blank">GDPR</a> compliant by showing a message on the login page. <a href="https://www.limitloginattempts.com/gdpr-qa/?from=plugin-settings-gdpr" class="unlink llar-label" target="_blank">Read more</a>', 'limit-login-attempts-reloaded' ); ?> <br/>
                    </td>
                </tr>
                <tr>
                    <th scope="row" valign="top"><?php echo __( 'GDPR message', 'limit-login-attempts-reloaded' ); ?>
                        <span class="hint_tooltip-parent">
                            <span class="dashicons dashicons-editor-help"></span>
                            <div class="hint_tooltip">
                                <ul class="hint_tooltip-content">
                                    <li>
                                        <?php esc_attr_e( 'This message will appear at the bottom of the login page.', 'limit-login-attempts-reloaded' ); ?>
                                    </li>
                                </ul>
                            </div>
                        </span>
                    </th>
                    <td>
                        <div class="textarea_border">
                            <textarea name="gdpr_message" cols="85"><?php echo esc_textarea( stripslashes( $gdpr_message ) ); ?></textarea>
                        </div>
                        <div class="description-additional">
                            <?php echo __( 'You can use a shortcode here to insert links, for example, a link to your Privacy Policy page. <br>The shortcode is: [llar-link url="https://example.com" text="Privacy Policy"]', 'limit-login-attempts-reloaded' ); ?>
                        </div>
                    </td>
                </tr>

                <tr>
                    <th scope="row" valign="top"><?php echo __( 'Weekly Digest', 'limit-login-attempts-reloaded' ); ?>
                        <span class="hint_tooltip-parent">
                            <span class="dashicons dashicons-editor-help"></span>
                            <div class="hint_tooltip">
                                <ul class="hint_tooltip-content">
                                    <li>
                                        <?php echo generate_lorem_ipsum(50) ?>
                                    </li>
                                </ul>
                            </div>
                        </span>
                    </th>
                    <td>
                        <input type="checkbox" name="lockout_notify_email" <?php echo $email_checked; ?>
                               value="email"/> <?php echo __( 'Email to', 'limit-login-attempts-reloaded' ); ?>
                        <input class="input_border" type="email" name="admin_notify_email"
                               value="<?php echo esc_attr( $admin_notify_email ) ?>"
                               placeholder="<?php echo esc_attr( $admin_email_placeholder ); ?>"/>
                        <div class="description-secondary">
                            <?php echo __( 'Receive a weekly digest that includes a recap of your failed logins and lockout notifications. Premium users will be able to see additional data such as countries and IPs with most failed logins.' ); ?>
                        </div>
                    </td>
                </tr>

                <tr>
                    <th scope="row" valign="top"><?php echo __( 'Notify on lockout', 'limit-login-attempts-reloaded' ); ?>
                        <span class="hint_tooltip-parent">
                            <span class="dashicons dashicons-editor-help"></span>
                            <div class="hint_tooltip">
                                <ul class="hint_tooltip-content">
                                    <li>
                                        <?php esc_attr_e( 'Email address to which lockout notifications will be sent.', 'limit-login-attempts-reloaded'  ); ?>
                                    </li>
                                </ul>
                            </div>
                        </span>
                    </th>
                    <td>
                        <input type="checkbox" name="lockout_notify_email" <?php echo $email_checked; ?>
                               value="email"/> <?php echo __( 'Email to', 'limit-login-attempts-reloaded' ); ?>
                        <input class="input_border" type="email" name="admin_notify_email"
                               value="<?php echo esc_attr( $admin_notify_email ) ?>"
                               placeholder="<?php echo esc_attr( $admin_email_placeholder ); ?>"/> <?php echo __( 'after', 'limit-login-attempts-reloaded' ); ?>
                        <input class="input_border" type="text" size="3" maxlength="4"
                               value="<?php echo( Config::get( 'notify_email_after' ) ); ?>"
                               name="email_after"/> <?php echo __( 'lockouts', 'limit-login-attempts-reloaded' ); ?>
                        <button class="button menu__item col llar-test-email-notification-btn button__transparent_orange">
                            <?php echo __( 'Test Email Notifications', 'limit-login-attempts-reloaded' ); ?>
                        </button>
                        <span class="preloader-wrapper llar-test-email-notification-loader">
                        <span class="spinner llar-app-ajax-spinner"></span>
                        <span class="msg"></span>
                    </span>
                        <div class="description-secondary"><?php echo sprintf(
                                __( 'It\'s not uncommon for web hosts to turn off emails for plugins as a security measure.<br>We\'ve <a class="llar_bold link__style_color_inherit" href="%s" target="_blank">created an article</a> to troubleshoot common email deliverability issues.', 'limit-login-attempts-reloaded' ),
                                'https://www.limitloginattempts.com/troubleshooting-guide-fixing-issues-with-non-functioning-emails-from-your-wordpress-site/'
                            ); ?>
                        </div>
                    </td>
                </tr>

                <tr>
                    <th scope="row" valign="top"><?php echo __( 'Display top menu item', 'limit-login-attempts-reloaded' ); ?>
                        <span class="hint_tooltip-parent">
                            <span class="dashicons dashicons-editor-help"></span>
                            <div class="hint_tooltip">
                                <ul class="hint_tooltip-content">
                                    <li>
                                        <?php esc_attr_e( 'The LLAR plugin displays its item on the top navigation menu, which provides a shortcut to the plugin.', 'limit-login-attempts-reloaded' ); ?>
                                    </li>
                                </ul>
                            </div>
                        </span>
                    </th>
                    <td>
                        <input type="checkbox" name="show_top_bar_menu_item" <?php checked( $show_top_bar_menu_item ); ?>> <?php _e( '(Save and reload this page to see the changes)', 'limit-login-attempts-reloaded' ) ?>
                    </td>
                </tr>

                <tr>
                    <th scope="row" valign="top"><?php echo __( 'Display left menu item', 'limit-login-attempts-reloaded' ); ?>
                        <span class="hint_tooltip-parent">
                            <span class="dashicons dashicons-editor-help"></span>
                            <div class="hint_tooltip">
                                <ul class="hint_tooltip-content">
                                    <li>
                                        <?php esc_attr_e( 'The LLAR plugin displays its item on the left navigation menu, which provides a shortcut to the plugin.', 'limit-login-attempts-reloaded' ); ?>
                                    </li>
                                </ul>
                            </div>
                        </span>
                    </th>
                    <td>
                        <input type="checkbox" name="show_top_level_menu_item" <?php checked( $show_top_level_menu_item ); ?>> <?php _e( '(Save and reload this page to see the changes)', 'limit-login-attempts-reloaded' ) ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row" valign="top"><?php echo __( 'Hide Dashboard Widget', 'limit-login-attempts-reloaded' ); ?>
                        <span class="hint_tooltip-parent">
                            <span class="dashicons dashicons-editor-help"></span>
                            <div class="hint_tooltip">
                                <ul class="hint_tooltip-content">
                                    <li>
                                        <?php esc_attr_e( 'The LLAR dashboard widget provides a quick glance of your daily failed login activity on the main WordPress dashboard. You may hide this widget by checking this box.', 'limit-login-attempts-reloaded' ); ?>
                                    </li>
                                </ul>
                            </div>
                        </span>
                    </th>
                    <td>
                        <input type="checkbox" name="hide_dashboard_widget" <?php checked( $hide_dashboard_widget ); ?>>
                    </td>
                </tr>
                <tr>
                    <th scope="row" valign="top"><?php echo __( 'Display Menu Warning Icon', 'limit-login-attempts-reloaded' ); ?>&nbsp;
                        <span class="hint_tooltip-parent">
                            <span class="dashicons dashicons-editor-help"></span>
                            <div class="hint_tooltip">
                                <ul class="hint_tooltip-content">
                                    <li>
                                        <?php esc_attr_e( 'The warning badge is a red bubble icon displayed next to the LLAR logo on the main vertical navigation menu. It displays a warning if there were more than 100 attempts for a day.', 'limit-login-attempts-reloaded' ); ?>
                                    </li>
                                </ul>
                            </div>
                        </span>
                    </th>
                    <td>
                        <input type="checkbox" name="show_warning_badge" <?php checked( $show_warning_badge ); ?>> <?php _e( '(Save and reload this page to see the changes)', 'limit-login-attempts-reloaded' ) ?>
                    </td>
                </tr>
            </table>

            <h3 class="title_page">
                <svg class="icon-page-setting p-0">
                    <use xlink:href="<?= LLA_PLUGIN_URL ?>/assets/css/images/sprite-add.svg#icon-gears"></use>
                </svg>
                <?php echo __( 'App Settings', 'limit-login-attempts-reloaded' ); ?>
            </h3>
            <div class="description-page">
                <?php echo __( 'The app absorbs the main load caused by brute-force attacks, analyzes login attempts, and blocks unwanted visitors. It provides other service functions as well.', 'limit-login-attempts-reloaded' ); ?>
            </div>
            <div class="llar-settings-wrap">
                <table class="form-table">
                    <tr>
                        <th scope="row" valign="top"><?php echo __( 'Micro Cloud', 'limit-login-attempts-reloaded' ); ?>
                            <span class="hint_tooltip-parent">
                                <span class="dashicons dashicons-editor-help"></span>
                                <div class="hint_tooltip">
                                    <ul class="hint_tooltip-content">
                                        <li>
                                            <?php echo generate_lorem_ipsum(50) ?>
                                        </li>
                                    </ul>
                                </div>
                            </span>
                        </th>
                        <td>
                            <div class="description-secondary p-0">
                                <?php echo __( 'Help us secure our network by sharing your login IP data. In return, receive limited access to our premium features up to 1,000 requests per month! Once 1,000 requests are reached each month, the premium app wil switch to FREE and reset the following month.' ) ?>
                            </div>
                            <div class="button_block">
                                <button class="button menu__item col button__transparent_orange">
                                    <?php echo __( 'Learn More', 'limit-login-attempts-reloaded' ); ?>
                                </button>
                                <button class="button menu__item col button__orange">
                                    <?php echo __( 'Get Started', 'limit-login-attempts-reloaded' ); ?>
                                </button>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row" valign="top"><?php echo __( 'Active App', 'limit-login-attempts-reloaded' ); ?>
                            <span class="hint_tooltip-parent">
                                <span class="dashicons dashicons-editor-help"></span>
                                <div class="hint_tooltip">
                                    <ul class="hint_tooltip-content">
                                        <li>
                                            <?php esc_attr_e( 'Switches from free version (local) to premium (cloud).', 'limit-login-attempts-reloaded' ); ?>
                                        </li>
                                    </ul>
                                </div>
                            </span>
                        </th>
                        <td>
                            <select class="input_border" name="active_app" id="">
                                <option value="local" <?php selected( $active_app, 'local' ); ?>>
                                    <?php echo __( 'Local', 'limit-login-attempts-reloaded' ); ?>
                                </option>
                                <?php if( $active_app_config ) : ?>
                                    <option value="custom" <?php selected( $active_app, 'custom' ); ?>>
                                        <?php echo esc_html( $active_app_config['name'] ); ?>
                                    </option>
                                <?php endif; ?>
                            </select>
                            <?php if( $active_app === 'local' ) : ?>
                                <span class="llar-protect-notice"><?php _e( 'Get advanced protection by <a href="#" class="unlink llar-upgrade-to-cloud">upgrading to our Cloud App.</a>', 'limit-login-attempts-reloaded' ); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>

            <div id="llar-apps-accordion" class="llar-accordion">
                <h3><?php echo __( 'Local App', 'limit-login-attempts-reloaded' ); ?></h3>
                <div>
                    <table class="form-table">
                        <tr>
                            <th scope="row" valign="top"><?php echo __( 'Lockout', 'limit-login-attempts-reloaded' ); ?>
                                <span class="hint_tooltip-parent">
                                    <span class="dashicons dashicons-editor-help"></span>
                                    <div class="hint_tooltip">
                                        <ul class="hint_tooltip-content">
                                            <li>
                                                <?php esc_attr_e( 'Set lockout limits based on failed attempts.', 'limit-login-attempts-reloaded' ); ?>
                                            </li>
                                        </ul>
                                    </div>
                                </span>
                            </th>
                            <td>
                                <input class="input_border" type="text" size="3" maxlength="4"
                                       value="<?php echo( Config::get( 'allowed_retries' ) ); ?>"
                                       name="allowed_retries"/>
                                <?php echo __( 'allowed retries', 'limit-login-attempts-reloaded' ); ?>
                                <span class="hint_tooltip-parent">
                                    <span class="dashicons dashicons-secondary dashicons-editor-help"></span>
                                    <div class="hint_tooltip">
                                        <ul class="hint_tooltip-content">
                                            <li>
                                                <?php esc_attr_e( 'Number of failed attempts allowed before locking out.', 'limit-login-attempts-reloaded' ); ?>
                                            </li>
                                        </ul>
                                    </div>
                                </span>
                                <br/>
                                <input class="input_border mt-0_5" type="text" size="3" maxlength="4"
                                       value="<?php echo( Config::get( 'lockout_duration' ) / 60 ); ?>"
                                       name="lockout_duration"/>
                                <?php echo __( 'minutes lockout', 'limit-login-attempts-reloaded' ); ?>
                                <span class="hint_tooltip-parent">
                                    <span class="dashicons dashicons-secondary dashicons-editor-help"></span>
                                    <div class="hint_tooltip">
                                        <ul class="hint_tooltip-content">
                                            <li>
                                                <?php esc_attr_e( 'Lockout time in minutes.', 'limit-login-attempts-reloaded' ); ?>
                                            </li>
                                        </ul>
                                    </div>
                                </span>
                                <br/>
                                <input class="input_border mt-0_5" type="text" size="3" maxlength="4"
                                       value="<?php echo( Config::get( 'allowed_lockouts' ) ); ?>"
                                       name="allowed_lockouts"/> <?php echo __( 'lockouts increase lockout time to', 'limit-login-attempts-reloaded' ); ?>
                                <input class="input_border" type="text" size="3" maxlength="4"
                                       value="<?php echo( Config::get( 'long_duration' ) / 3600 ); ?>"
                                       name="long_duration"/>
                                <?php echo __( 'hours', 'limit-login-attempts-reloaded' ); ?>
                                <span class="hint_tooltip-parent">
                                    <span class="dashicons dashicons-secondary dashicons-editor-help"></span>
                                    <div class="hint_tooltip">
                                        <ul class="hint_tooltip-content">
                                            <li>
                                                <?php esc_attr_e( 'After the specified number of lockouts the lockout time will increase by specified hours.', 'limit-login-attempts-reloaded' ); ?>
                                            </li>
                                        </ul>
                                    </div>
                                </span>
                                <br/>
                                <input class="input_border mt-0_5" type="text" size="3" maxlength="4"
                                       value="<?php echo( Config::get( 'valid_duration' ) / 3600 ); ?>"
                                       name="valid_duration"/>
                                <?php echo __( 'hours until retries are reset', 'limit-login-attempts-reloaded' ); ?>
                                <span class="hint_tooltip-parent">
                                    <span class="dashicons dashicons-secondary dashicons-editor-help"></span>
                                    <div class="hint_tooltip">
                                        <ul class="hint_tooltip-content">
                                            <li>
                                                <?php esc_attr_e( 'Time in hours before blocks are removed.', 'limit-login-attempts-reloaded' ); ?>
                                            </li>
                                        </ul>
                                    </div>
                                </span>
                                <div class="description-secondary mt-0_5 p-0">
                                    <?php echo sprintf(
                                        __( 'After a specific IP address fails to log in <b>%1$s</b> times, a lockout lasting <b>%2$s</b> minutes is activated. If additional failed attempts occur within <b>%3$s</b> hours and lead to another lockout, once their combined total hits <b>%4$s</b>, the <b>%2$s</b> minutes duration is extended to <b>%5$s</b> hours. The lockout will be lifted once <b>%3$s</b> hours have passed since the last lockout incident.', 'limit-login-attempts-reloaded' ),
                                        Config::get( 'allowed_retries' ),
                                        Config::get( 'lockout_duration' ) / 60,
                                        Config::get( 'valid_duration' ) / 3600,
                                        Config::get( 'allowed_lockouts' ),
                                        Config::get( 'long_duration' ) / 3600
                                    ); ?>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row" valign="top"><?php echo __( 'Trusted IP Origins', 'limit-login-attempts-reloaded' ); ?>
                                <span class="hint_tooltip-parent">
                                    <span class="dashicons dashicons-editor-help"></span>
                                    <div class="hint_tooltip">
                                        <ul class="hint_tooltip-content">
                                            <li>
                                                <?php esc_attr_e( 'Server variables containing IP addresses.', 'limit-login-attempts-reloaded' ); ?>
                                            </li>
                                        </ul>
                                    </div>
                                </span>
                            </th>
                            <td>
                                <div class="field-col">
                                    <input class="input_border" ype="text" class="regular-text" style="width: 100%;max-width: 431px;" name="lla_trusted_ip_origins" value="<?php echo esc_attr( $trusted_ip_origins ); ?>">
                                    <div class="description-secondary mt-0_5 p-0">
                                        <?php _e( 'Specify the origins you trust in order of priority, separated by commas. We strongly recommend that you <b>do not</b> use anything other than REMOTE_ADDR since other origins can be easily faked. Examples: HTTP_X_FORWARDED_FOR, HTTP_CF_CONNECTING_IP, HTTP_X_SUCURI_CLIENTIP', 'limit-login-attempts-reloaded' ); ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </table>
                    <div class="add_block__under_table">
                        <div class="description">
                            Why Use Our Premium Cloud App?
                        </div>
                        <div class="add_block__list">
                            <div class="item">
                                <svg class="icon-page-setting_add">
                                    <use xlink:href="<?= LLA_PLUGIN_URL ?>/assets/css/images/sprite-add.svg#icon-lock-big"></use>
                                </svg>
                                <div class="name">
                                    Absorb site load caused by attacks
                                </div>
                            </div>
                            <div class="item">
                                <svg class="icon-page-setting_add icon-corr">
                                    <use xlink:href="<?= LLA_PLUGIN_URL ?>/assets/css/images/sprite.svg#icon-ip"></use>
                                </svg>
                                <div class="name">
                                    Use intelligent IP denial/unblocking technology
                                </div>
                            </div>
                            <div class="item">
                                <svg class="icon-page-setting_add">
                                    <use xlink:href="<?= LLA_PLUGIN_URL ?>/assets/css/images/sprite-add.svg#icon-align-left"></use>
                                </svg>
                                <div class="name">
                                    Sync the allow/deny/pass lists between multiple domains
                                </div>
                            </div>
                            <div class="item">
                                <svg class="icon-page-setting_add">
                                    <use xlink:href="<?= LLA_PLUGIN_URL ?>/assets/css/images/sprite-add.svg#icon-admin-big"></use>
                                </svg>
                                <div class="name">
                                    Get premium support
                                </div>
                            </div>
                            <div class="item">
                                <svg class="icon-page-setting_add">
                                    <use xlink:href="<?= LLA_PLUGIN_URL ?>/assets/css/images/sprite-add.svg#icon-exit"></use>
                                </svg>
                                <div class="name">
                                    Run auto backups of access control lists, lockouts and logs
                                </div>
                            </div>
                            <div class="item">
                                <svg class="icon-page-setting_add">
                                    <use xlink:href="<?= LLA_PLUGIN_URL ?>/assets/css/images/sprite-add.svg#icon-folder-cross"></use>
                                </svg>
                                <div class="name">
                                    No contract - cancel anytime
                                </div>
                            </div>
                        </div>
                        <button class="button menu__item button__transparent_orange" id="">
                            <?php echo __( 'Try For FREE', 'limit-login-attempts-reloaded' ); ?>
                        </button>
                    </div>
                </div>

                <h3><?php echo ($active_app_config) ? $active_app_config['name'] : __('Custom App', 'limit-login-attempts-reloaded' ); ?></h3>
                <div class="custom-app-tab">
                    <table class="form-table">
                        <tr>
                            <th scope="row" valign="top"><?php echo __( 'Setup Code', 'limit-login-attempts-reloaded' ); ?>
                                <span class="hint_tooltip-parent">
                                    <span class="dashicons dashicons-editor-help"></span>
                                    <div class="hint_tooltip">
                                        <ul class="hint_tooltip-content">
                                            <li>
                                                <?php esc_attr_e( 'This is the code you receive via email once you subscribe to the LLAR premium cloud app. (example xxxxxxxxxxxxx=yek?putes/1v/moc.stpmettanigoltimil.ipa)', 'limit-login-attempts-reloaded' ); ?>
                                            </li>
                                        </ul>
                                    </div>
                                </span>
                            </th>
                            <td>
                                <?php if( $active_app === 'custom') : ?>
                                    <a class="unlink link__style_unlink llar-toggle-setup-field" href="#">
                                        <?php _e( 'Edit', 'limit-login-attempts-reloaded' ); ?>
                                    </a>
                                <?php endif; ?>
                                <div class="setup-code-wrap <?php echo ( $active_app === 'local' || !$active_app_config ) ? 'active' : ''; ?>">
                                    <input  class="input_border full_area" type="text" class="regular-text" id="limit-login-app-setup-code" value="<?php echo ( !empty( $app_setup_code ) ) ? esc_attr( $app_setup_code ) : ''; ?>">
                                    <button class="button menu__item button__transparent_orange" id="limit-login-app-setup">
                                        <?php echo __( 'Submit', 'limit-login-attempts-reloaded' ); ?>
                                    </button>
                                    <span class="spinner llar-app-ajax-spinner"></span><br>
                                    <span class="llar-app-ajax-msg"></span>
                                    <div class="description-secondary mt-0_5 p-0">
                                        <?php _e( 'Add this code to all websites in your network to sync protection (payment required for additional domains unless subscribed to Agency plan).', 'limit-login-attempts-reloaded' ) ?>
                                    </div>
                                </div>

                                <?php if( $active_app === 'local' ) : ?>
                                    <div class="description-secondary p-0">
                                        <?php echo sprintf(
                                            __( 'Use the <a href="%s" class="link__style_color_inherit" target="_blank">premium app</a> that we offer or follow the instructions on <a href="%s" target="_blank">how to</a> create your own one.', 'limit-login-attempts-reloaded' ),
                                            'https://www.limitloginattempts.com/info.php?from=plugin-settings',
                                            'https://www.limitloginattempts.com/app/?from=plugin-settings' );
                                        ?>
                                    </div>
                                    <div class="llar-why-use-premium-text">
                                        <div class="title"><?php _e( 'Why Use Our Premium Cloud App?', 'limit-login-attempts-reloaded' ); ?></div>
                                        <ul>
                                            <li><span class="dashicons dashicons-yes"></span><?php _e( 'Absorb site load caused by attacks', 'limit-login-attempts-reloaded' ); ?></li>
                                            <li><span class="dashicons dashicons-yes"></span><?php _e( 'Use intelligent IP blocking/unblocking technology', 'limit-login-attempts-reloaded' ); ?></li>
                                            <li><span class="dashicons dashicons-yes"></span><?php _e( 'Sync the allow/deny/pass lists between multiple domains', 'limit-login-attempts-reloaded' ); ?></li>
                                            <li><span class="dashicons dashicons-yes"></span><?php _e( 'Get premium support', 'limit-login-attempts-reloaded' ); ?></li>
                                            <li><span class="dashicons dashicons-yes"></span><?php _e( 'Run auto backups of access control lists, lockouts and logs', 'limit-login-attempts-reloaded' ); ?></li>
                                            <li><span class="dashicons dashicons-yes"></span><?php _e( 'No contract - cancel anytime', 'limit-login-attempts-reloaded' ); ?></li>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if( $active_app === 'custom' && $active_app_config ) : ?>
                            <tr class="app-form-field">
                                <th scope="row"
                                    valign="top"><?php echo __( 'Configuration', 'limit-login-attempts-reloaded' ); ?></th>
                                <td>
                                    <div class="field-col">
                                        <textarea id="limit-login-app-config" readonly="readonly" name="limit-login-app-config" cols="60" rows="5"><?php echo esc_textarea( json_encode( $active_app_config ) ); ?></textarea><br>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php if( $active_app === 'custom' && !empty( $active_app_config['settings'] ) ) : ?>
                            <?php foreach( $active_app_config['settings'] as $setting_name => $setting_params ) : ?>
                                <tr>
                                    <th scope="row" valign="top"><?php echo $setting_params['label']; ?>
                                        <i class="llar-tooltip" data-text="<?php echo esc_attr( $setting_params['description'] ); ?>">
                                            <span class="dashicons dashicons-editor-help"></span>
                                        </i></th>
                                    <td>
                                        <div class="field-col">
                                            <?php if( !empty( $setting_params['options'] ) ) : ?>
                                                <select name="llar_app_settings[<?php echo $setting_name; ?>]">
                                                    <?php foreach ( $setting_params['options'] as $option ) : ?>
                                                        <option value="<?php echo esc_attr( $option ); ?>" <?php selected( $option, $setting_params['value'] ); ?>><?php echo esc_html( $option ); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            <?php else : ?>
                                                <input type="text" class="regular-text" name="llar_app_settings[<?php echo esc_attr( $setting_name ); ?>]" value="<?php echo esc_attr( $setting_params['value'] ); ?>">
                                            <?php endif; ?>

                                            <p class="description"><?php echo esc_html( $setting_params['description'] ); ?></p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>

        <script type="text/javascript">
            (function($){

                $(document).ready(function(){

                    $( "#llar-apps-accordion" ).accordion({
                        heightStyle: "content",
                        active: <?php echo ( $active_app === 'local' ) ? 0 : 1; ?>
                    });

                    var $app_ajax_spinner = $('.llar-app-ajax-spinner'),
                        $app_ajax_msg = $('.llar-app-ajax-msg'),
                        $app_config_field = $('#limit-login-app-config');

                    if($app_config_field.val()) {
                        var pretty = JSON.stringify(JSON.parse($app_config_field.val()), undefined, 2);
                        $app_config_field.val(pretty);
                    }

                    $('#limit-login-app-setup').on('click', function(e) {
                        e.preventDefault();

                        $app_ajax_msg.text('').removeClass('success error');
                        $app_ajax_spinner.css('visibility', 'visible');

                        var setup_code = $('#limit-login-app-setup-code').val();

                        $.post(ajaxurl, {
                            action: 'app_setup',
                            code: setup_code,
                            sec: '<?php echo esc_js( wp_create_nonce( "llar-action" ) ); ?>',
                            is_network_admin: <?php echo esc_js( is_network_admin() ? 1 : 0 ); ?>
                        }, function(response){

                            if(!response.success) {

                                $app_ajax_msg.addClass('error');
                            } else {

                                $app_ajax_msg.addClass('success');

                                setTimeout(function(){

                                    window.location = window.location + '&llar-cloud-activated';

                                }, 1000);
                            }

                            if(!response.success && response.data.msg) {

                                $app_ajax_msg.text(response.data.msg);
                            }

                            $app_ajax_spinner.css('visibility', 'hidden');

                            setTimeout(function(){

                                $app_ajax_msg.text('').removeClass('success error');

                            }, 5000);
                        });

                    });

                    $('.llar-toggle-setup-field').on('click', function(e){
                        e.preventDefault();

                        $(this).hide();

                        $('.setup-code-wrap').toggleClass('active');
                        $('.app-form-field').toggleClass('active');
                    });

                    $('.llar-upgrade-to-cloud').on('click', function(e){
                        e.preventDefault();

                        $("#ui-id-3").click();

                        $([document.documentElement, document.body]).animate({
                            scrollTop: $("#llar-apps-accordion").offset().top
                        }, 500);
                    });

                    $('.llar-test-email-notification-btn').on('click', function(e) {
                        e.preventDefault();

                        const $email_input = $('input[name="admin_notify_email"]');
                        const $test_email_loader = $('.llar-test-email-notification-loader');
                        const $test_email_loader_msg = $test_email_loader.find('.msg');

                        $test_email_loader_msg.text('');

                        $test_email_loader.toggleClass('loading');

                        $.post(ajaxurl, {
                            action: 'test_email_notifications',
                            email: $email_input.val() || $email_input.attr('placeholder'),
                            sec: '<?php echo esc_js( wp_create_nonce( "llar-action" ) ); ?>',
                        }, function(res) {
                            if(res?.success) {
                                $test_email_loader_msg.addClass('success').text('<?php echo esc_js( __( 'Test email has been sent!', 'limit-login-attempts-reloaded' ) ) ?>')
                            }

                            $test_email_loader.toggleClass('loading');
                        });
                    })
                });

            })(jQuery);
        </script>

        <p class="submit">
            <input class="button menu__item col button__orange" name="llar_update_settings" value="<?php echo __( 'Save Settings', 'limit-login-attempts-reloaded' ); ?>"
                   type="submit"/>
        </p>
    </form>
</div>


