<?php

use LLAR\Core\Helpers;
use LLAR\Core\LimitLoginAttempts;

if ( ! defined( 'ABSPATH' ) ) exit();

/**
 * Stats, lists, lockout log and copy come from the controller via
 * get_logs_local_view_vars(); this template only assembles markup.
 *
 * @var $this LimitLoginAttempts
 */

$v = $this->get_logs_local_view_vars();

$lockouts_total       = $v['lockouts_total'];
$lockouts_now         = $v['lockouts_now'];
$lockouts             = $v['lockouts'];
$white_list_ips       = $v['white_list_ips'];
$white_list_usernames = $v['white_list_usernames'];
$black_list_ips       = $v['black_list_ips'];
$black_list_usernames = $v['black_list_usernames'];
$log                  = $v['log'];
?>

<div id="llar-setting-page-logs">
    <h3 class="title_page">
        <img src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/icon-check.png">
        <?php echo esc_html( $v['statistics_title'] ); ?>
    </h3>

    <form action="<?php echo $this->get_options_page_uri('logs-local'); ?>" method="post">
        <?php wp_nonce_field( 'limit-login-attempts-options' ); ?>

        <div class="llar-settings-wrap">
            <table class="llar-form-table">
                <tr>
                    <th scope="row" valign="top">
                        <?php echo esc_html( $v['total_lockouts_label'] ); ?>
                    </th>
                    <td>
                        <?php if ( $lockouts_total > 0 ) : ?>
                            <input class="button menu__item col button__transparent_orange mx-0_5" name="reset_total"
                                   value="<?php echo esc_attr( $v['reset_counter_label'] ); ?>"
                                   type="submit"/>
                            <?php echo esc_html( $v['lockouts_since_reset'] ); ?>
                        <?php else :
                            echo esc_html( $v['no_lockouts_yet'] );
                        endif ?>
                    </td>
                </tr>
                <?php if ( $lockouts_now > 0 ) : ?>
                    <tr>
                        <th scope="row" valign="top">
                            <?php echo esc_html( $v['active_lockouts_label'] ); ?>
                        </th>
                        <td>
                            <input class="button" name="reset_current"
                                   value="<?php echo esc_attr( $v['restore_lockouts_label'] ); ?>"
                                   type="submit"/>
                            <?php echo esc_html( $v['active_lockouts_summary'] ); ?>
                        </td>
                    </tr>
                <?php endif ?>
            </table>
        </div>
    </form>

    <form action="<?php echo $this->get_options_page_uri('logs-local' ); ?>" method="post">
        <?php wp_nonce_field( 'limit-login-attempts-options' ); ?>

        <div class="llar-settings-wrap">
            <table class="llar-form-table">
                <tr>
                    <th scope="row" valign="top">
                        <?php echo esc_html( $v['safelist_label'] ); ?>
                    </th>
                    <td>
                        <div class="field-col">
                            <div class="description-secondary p-0">
                                <?php echo esc_html( $v['ip_per_line_label'] ); ?>
                            </div>
                            <div class="textarea_border mt-0_5">
                                <textarea name="lla_whitelist_ips" rows="5" cols="50"><?php echo esc_textarea( $white_list_ips ); ?></textarea>
                            </div>
                        </div>
                        <div class="field-col">
                            <div class="description-secondary p-0">
                                <?php echo esc_html( $v['username_per_line_label'] ); ?>
                            </div>
                            <div class="textarea_border mt-0_5">
                                <textarea name="lla_whitelist_usernames" rows="5" cols="50"><?php echo esc_textarea( $white_list_usernames ); ?></textarea>
                            </div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row" valign="top">
                        <?php echo esc_html( $v['denylist_label'] ); ?>
                    </th>
                    <td>
                        <div class="field-col">
                            <div class="description-secondary p-0">
                                <?php echo esc_html( $v['ip_per_line_label'] ); ?>
                            </div>
                            <div class="textarea_border mt-0_5">
                                <textarea name="lla_blacklist_ips" rows="5" cols="50"><?php echo esc_textarea( $black_list_ips ); ?></textarea>
                            </div>
                        </div>
                        <div class="field-col">
                            <div class="description-secondary p-0">
                                <?php echo esc_html( $v['username_per_line_label'] ); ?>
                                <span class="hint_tooltip-parent">
                                    <span class="dashicons dashicons-editor-help"></span>
                                    <div class="hint_tooltip">
                                        <div class="hint_tooltip-content">
                                            <?php echo esc_html( $v['username_list_hint'] ); ?>
                                        </div>
                                    </div>
                                </span>
                            </div>
                            <div class="textarea_border mt-0_5">
                                <textarea name="lla_blacklist_usernames" rows="5" cols="50"><?php echo esc_textarea( $black_list_usernames ); ?></textarea>
                            </div>
                        </div>
                        <div class="description-additional p-0 mt-0_5">
                            <?php echo wp_kses(
                                $v['denylist_upgrade_html'],
                                array(
                                    'a' => array(
                                        'href'   => true,
                                        'class'  => true,
                                        'target' => true,
                                    ),
                                )
                            ); ?>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        <p class="submit">
            <input class="button menu__item col button__orange" name="llar_update_dashboard"
                   value="<?php echo esc_attr( $v['save_settings_label'] ); ?>"
                   type="submit"/>
        </p>
    </form>

    <div class="add_block__under_table image_plus">
        <div class="row__list">
            <div class="add_block__title">
                <div class="description mt-1_5">
                    <?php echo esc_html( $v['firewall_upgrade_title'] ); ?>
                </div>
                <a href="<?php echo esc_url( $v['trial_cta_url'] ); ?>"
                   data-llar-info-id="<?php echo esc_attr( $v['trial_info_id'] ); ?>"
                   class="button menu__item button__transparent_orange mt-1_5 llar-trial-start-cta">
                    <?php echo esc_html( $v['trial_label'] ); ?>
                </a>
            </div>
            <div class="add_block__list">
                <div class="item">
                    <img src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/identify-counter-min.png">
                    <div class="name">
                        <?php echo esc_html( $v['firewall_feature_threats'] ); ?>
                    </div>
                </div>
                <div class="item">
                    <img src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/actibe-ip-min.png">
                    <div class="name">
                        <?php echo esc_html( $v['firewall_feature_databases'] ); ?>
                    </div>
                </div>
                <div class="item">
                    <img src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/unblock-min.png">
                    <div class="name">
                        <?php echo esc_html( $v['firewall_feature_unblock'] ); ?>
                    </div>
                </div>
                <div class="item">
                    <img src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/deny-ip-min.png">
                    <div class="name">
                        <?php echo esc_html( $v['firewall_feature_country'] ); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ( ! empty( $log ) ) : ?>
        <h3 class="title_page">
            <img src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/icon-pre-install.png">
            <?php echo esc_html( $v['lockout_log_title'] ); ?>
        </h3>

        <form action="<?php echo $this->get_options_page_uri('logs-local'); ?>" method="post">
            <?php wp_nonce_field( 'limit-login-attempts-options' ); ?>
            <input type="hidden" value="true" name="clear_log"/>
            <div class="description-page">
                <input class="button menu__item col button__transparent_orange mx-0_5" name="submit"
                       value="<?php echo esc_attr( $v['clear_log_label'] ); ?>"
                       type="submit"/>
                <span class="description-secondary">
                    <?php echo wp_kses(
                        $v['clear_log_upgrade_html'],
                        array(
                            'a' => array(
                                'href'   => true,
                                'class'  => true,
                                'target' => true,
                            ),
                        )
                    ); ?>
                </span>
            </div>
        </form>

        <div class="limit-login-log">
            <div class="llar-settings-wrap">
                <table class="llar-form-table">
                    <tr>
                        <th scope="col"><?php echo esc_html( $v['log_col_date'] ); ?></th>
                        <th scope="col"><?php echo esc_html( $v['log_col_ip'] ); ?></th>
                        <th scope="col"><?php echo esc_html( $v['log_col_login_as'] ); ?></th>
                        <th scope="col"><?php echo esc_html( $v['log_col_gateway'] ); ?></th>
                        <th>
                    </tr>

                    <?php foreach ( $log as $date => $user_info ) : ?>
                        <tr>
                            <td class="limit-login-date"><?php echo esc_html( date_i18n( $v['log_date_format'], $date ) ); ?></td>
                            <td class="limit-login-ip">
                                <a href="<?php echo esc_url( 'https://www.limitloginattempts.com/location/?ip=' . $user_info['ip'] ); ?>" target="_blank">
	                                <?php echo esc_html( $user_info['ip'] ); ?>
                                </a>
                            </td>
                            <td class="limit-login-max">
                                <?php echo esc_html( $user_info['username'] ) . ' (' . esc_html( (string) $user_info['counter'] ) . ' ' . esc_html( $v['lockouts_word'] ) . ')'; ?>
                            </td>
                            <td class="limit-login-gateway">
                                <?php echo esc_html( $user_info['gateway'] ); ?>
                            </td>
                            <td>
								<?php
								// Log rows keep the raw IP; lockouts may be keyed by /64.
								$lockout_until = Helpers::get_active_lockout_until( $lockouts, $user_info['ip'] );
								if ( $lockout_until > time() ) :
									?>
                                    <a href="#" class="button limit-login-unlock" data-ip="<?php echo esc_attr( $user_info['ip'] ); ?>"
                                       data-username="<?php echo esc_attr( $user_info['username'] ); ?>">
                                        <?php echo esc_html( $v['unlock_label'] ); ?>
                                    </a>
                                <?php elseif ( $user_info['unlocked'] ) : ?>
                                    <?php echo esc_html( $v['unlocked_label'] ); ?>
                                <?php endif ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                </table>
            </div>
        </div>

        <div class="add_block__under_table image_plus">
            <div class="row__list">
                <div class="add_block__title">
                    <div class="description mt-1_5">
                        <?php echo esc_html( $v['logs_upgrade_title'] ); ?>
                    </div>
                    <a href="<?php echo esc_url( $v['trial_cta_url'] ); ?>"
                   data-llar-info-id="<?php echo esc_attr( $v['trial_info_id'] ); ?>"
                   class="button menu__item button__transparent_orange mt-1_5 llar-trial-start-cta">
                        <?php echo esc_html( $v['trial_label'] ); ?>
                    </a>
                </div>
                <div class="add_block__list">
                    <div class="item">
                        <img src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/enhanced-logs-min.png">
                        <div class="name">
                            <?php echo esc_html( $v['logs_feature_enhanced'] ); ?>
                        </div>
                    </div>
                    <div class="item">
                        <img src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/new-threats-min.png">
                        <div class="name">
                            <?php echo esc_html( $v['logs_feature_threats'] ); ?>
                        </div>
                    </div>
                    <div class="item">
                        <img src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/deny-list-min.png">
                        <div class="name">
                            <?php echo esc_html( $v['logs_feature_deny'] ); ?>
                        </div>
                    </div>
                    <div class="item">
                        <img src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/unblock-min.png">
                        <div class="name">
                            <?php echo esc_html( $v['logs_feature_unblock'] ); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script>jQuery( function( $ ) {
                $( '.limit-login-log .limit-login-unlock' ).click( function()
                {
                    var btn = $( this );

                    if ( btn.hasClass('disabled') )
                        return false;
                    btn.addClass( 'disabled' );

                    $.post( ajaxurl, {
                        action: 'limit-login-unlock',
                        sec: '<?php echo esc_js( $v['unlock_nonce'] ); ?>',
                        ip: btn.data( 'ip' ),
                        username: btn.data( 'username' )
                    } )
                        .done( function( data ) {
                            if ( data === true )
                                btn.fadeOut( function(){ $( this ).parent().text( <?php echo wp_json_encode( $v['unlocked_label'] ); ?> ) });
                            else
                                fail();
                        } ).fail( fail );

                    function fail() {
                        alert( 'Connection error' );
                        btn.removeClass( 'disabled' );
                    }

                    return false;
                } );
            } )
        </script>
	<?php endif; ?>
</div>
