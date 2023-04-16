<?php

use LLAR\Core\Config;
use LLAR\Core\Helpers;

if( !defined( 'ABSPATH' ) ) exit();

/**
 * @var $this LLAR\Core\LimitLoginAttempts
 */

$gdpr = Config::get( 'gdpr' );
$gdpr_message = Config::get( 'gdpr_message' );

$v = explode( ',', Config::get( 'lockout_notify' ) );
$email_checked = in_array( 'email', $v ) ? ' checked ' : '';
$email_html_checked = $this->get_option( 'notify_email_plain' ) ? '' : ' checked ';
$email_plain_checked = $this->get_option( 'notify_email_plain' ) ? ' checked ' : '';

$show_top_level_menu_item = Config::get( 'show_top_level_menu_item' );
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

<h3><?php echo __( 'General Settings', 'limit-login-attempts-reloaded' ); ?></h3>
<p><?php echo __( 'These settings are independent of the apps (see below).', 'limit-login-attempts-reloaded' ); ?></p>
<form action="<?php echo $this->get_options_page_uri('settings'); ?>" method="post">

    <?php wp_nonce_field( 'limit-login-attempts-options' ); ?>

    <?php if ( is_network_admin() ): ?>
    <input type="checkbox" name="allow_local_options" <?php echo Config::get( 'allow_local_options' ) ? 'checked' : '' ?> value="1"/> <?php esc_html_e( 'Let network sites use their own settings', 'limit-login-attempts-reloaded' ); ?>
        <p class="description"><?php esc_html_e('If disabled, the global settings will be forcibly applied to the entire network.') ?></p>
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
                <th scope="row"
                    valign="top"><?php echo __( 'GDPR compliance', 'limit-login-attempts-reloaded' ); ?></th>
                <td>
                    <input type="checkbox" name="gdpr" value="1" <?php if($gdpr): ?> checked <?php endif; ?>/>
				    <?php echo __( 'this makes the plugin <a href="https://gdpr-info.eu/" target="_blank">GDPR</a> compliant by showing a message on the login page. <a href="https://www.limitloginattempts.com/gdpr-qa/?from=plugin-settings-gdpr" target="_blank">Read more</a>', 'limit-login-attempts-reloaded' ); ?> <br/>
                </td>
            </tr>
            <tr>
                <th scope="row"
                    valign="top"><?php echo __( 'GDPR message', 'limit-login-attempts-reloaded' ); ?></th>
                <td>
                    <textarea name="gdpr_message" cols="60"><?php echo esc_textarea( stripslashes( $gdpr_message ) ); ?></textarea>
                    <p class="description"><?php echo __( 'You can use a shortcode here to insert links, for example, a link to your Privacy Policy page. <br>The shortcode is: [llar-link url="https://example.com" text="Privacy Policy"]', 'limit-login-attempts-reloaded' ); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row"
                    valign="top"><?php echo __( 'Notify on lockout', 'limit-login-attempts-reloaded' ); ?></th>
                <td>
                    <input type="checkbox" name="lockout_notify_email" <?php echo $email_checked; ?>
                           value="email"/> <?php echo __( 'Email to', 'limit-login-attempts-reloaded' ); ?>
                    <input type="email" name="admin_notify_email"
                           value="<?php echo esc_attr( $admin_notify_email ) ?>"
                           placeholder="<?php echo esc_attr( $admin_email_placeholder ); ?>"/> <?php echo __( 'after', 'limit-login-attempts-reloaded' ); ?>
                    <input type="text" size="3" maxlength="4"
                           value="<?php echo( Config::get( 'notify_email_after' ) ); ?>"
                           name="email_after"/> <?php echo __( 'lockouts', 'limit-login-attempts-reloaded' ); ?>
                    <br>
                    <?php _e( 'with format', 'limit-login-attempts-reloaded' ); ?>
                    <label>
                        <input type="radio" name="email_plain" <?php echo $email_html_checked; ?> value=""/>
                        <?php _e( 'HTML', 'limit-login-attempts-reloaded' ); ?>
                    </label>
                    <label>
                        <input type="radio" name="email_plain" <?php echo $email_plain_checked; ?> value="email_plain"/>
                        <?php _e( 'Plaintext', 'limit-login-attempts-reloaded' ); ?>
                    </label>
                </td>
            </tr>

            <tr>
                <th scope="row"
                    valign="top"><?php echo __( 'Display top-level menu item', 'limit-login-attempts-reloaded' ); ?></th>
                <td>
                    <input type="checkbox" name="show_top_level_menu_item" <?php checked( $show_top_level_menu_item ); ?>> <?php _e( '(Reload the page to see the changes)', 'limit-login-attempts-reloaded' ) ?>
                </td>
            </tr>
            <tr>
                <th scope="row"
                    valign="top"><?php echo __( 'Hide Dashboard Widget', 'limit-login-attempts-reloaded' ); ?></th>
                <td>
                    <input type="checkbox" name="hide_dashboard_widget" <?php checked( $hide_dashboard_widget ); ?>>
                </td>
            </tr>
            <tr>
                <th scope="row"
                    valign="top"><?php echo __( 'Display Warning Badge', 'limit-login-attempts-reloaded' ); ?></th>
                <td>
                    <input type="checkbox" name="show_warning_badge" <?php checked( $show_warning_badge ); ?>> <?php _e( '(Reload the page to see the changes)', 'limit-login-attempts-reloaded' ) ?>
                </td>
            </tr>
            <tr>
                <th scope="row"
                    valign="top"><?php echo __( 'Active App', 'limit-login-attempts-reloaded' ); ?></th>
                <td>
                    <select name="active_app" id="">
                        <option value="local" <?php selected( $active_app, 'local' ); ?>><?php echo __( 'Local', 'limit-login-attempts-reloaded' ); ?></option>
					    <?php if( $active_app_config ) : ?>
                            <option value="custom" <?php selected( $active_app, 'custom' ); ?>><?php echo esc_html( $active_app_config['name'] ); ?></option>
					    <?php endif; ?>
                    </select>
				    <?php if( $active_app === 'local' ) : ?>
                        <span class="llar-protect-notice"><?php _e( 'Get advanced protection by <a href="#" class="llar-upgrade-to-cloud">upgrading to our Cloud App.</a>', 'limit-login-attempts-reloaded' ); ?></span>
				    <?php endif; ?>
                </td>
            </tr>
        </table>

        <h3><?php echo __( 'App Settings', 'limit-login-attempts-reloaded' ); ?></h3>
        <p><?php echo __( 'The app absorbs the main load caused by brute-force attacks, analyzes login attempts, and blocks unwanted visitors. It provides other service functions as well.', 'limit-login-attempts-reloaded' ); ?></p>

        <div id="llar-apps-accordion" class="llar-accordion">
            <h3><?php echo __( 'Local App', 'limit-login-attempts-reloaded' ); ?></h3>
            <div>
                <table class="form-table">
                    <tr>
                        <th scope="row" valign="top"><?php echo __( 'Lockout', 'limit-login-attempts-reloaded' ); ?></th>
                        <td>
                            <input type="text" size="3" maxlength="4"
                                   value="<?php echo( Config::get( 'allowed_retries' ) ); ?>"
                                   name="allowed_retries"/> <?php echo __( 'allowed retries', 'limit-login-attempts-reloaded' ); ?>
                            <br/>
                            <input type="text" size="3" maxlength="4"
                                   value="<?php echo( Config::get( 'lockout_duration' ) / 60 ); ?>"
                                   name="lockout_duration"/> <?php echo __( 'minutes lockout', 'limit-login-attempts-reloaded' ); ?>
                            <br/>
                            <input type="text" size="3" maxlength="4"
                                   value="<?php echo( Config::get( 'allowed_lockouts' ) ); ?>"
                                   name="allowed_lockouts"/> <?php echo __( 'lockouts increase lockout time to', 'limit-login-attempts-reloaded' ); ?>
                            <input type="text" size="3" maxlength="4"
                                   value="<?php echo( Config::get( 'long_duration' ) / 3600 ); ?>"
                                   name="long_duration"/> <?php echo __( 'hours', 'limit-login-attempts-reloaded' ); ?> <br/>
                            <input type="text" size="3" maxlength="4"
                                   value="<?php echo( Config::get( 'valid_duration' ) / 3600 ); ?>"
                                   name="valid_duration"/> <?php echo __( 'hours until retries are reset', 'limit-login-attempts-reloaded' ); ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"
                            valign="top"><?php echo __( 'Trusted IP Origins', 'limit-login-attempts-reloaded' ); ?></th>
                        <td>
                            <div class="field-col">
                                <input type="text" class="regular-text" style="width: 100%;max-width: 431px;" name="lla_trusted_ip_origins" value="<?php echo esc_attr( $trusted_ip_origins ); ?>">
                                <p class="description"><?php _e( 'Specify the origins you trust in order of priority, separated by commas. We strongly recommend that you <b>do not</b> use anything other than REMOTE_ADDR since other origins can be easily faked. Examples: HTTP_X_FORWARDED_FOR, HTTP_CF_CONNECTING_IP, HTTP_X_SUCURI_CLIENTIP', 'limit-login-attempts-reloaded' ); ?></p>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>


            <h3><?php echo ($active_app_config) ? $active_app_config['name'] : __('Custom App', 'limit-login-attempts-reloaded' ); ?></h3>
            <div class="custom-app-tab">

                <table class="form-table">

                    <tr>
                        <th scope="row"
                            valign="top"><?php echo __( 'Setup Code', 'limit-login-attempts-reloaded' ); ?></th>
                        <td>
						    <?php if( $active_app === 'custom') : ?>
                                <a class="llar-toggle-setup-field" href="#"><?php _e( 'Edit', 'limit-login-attempts-reloaded' ); ?></a>
						    <?php endif; ?>
                            <div class="setup-code-wrap <?php echo ( $active_app === 'local' || !$active_app_config ) ? 'active' : ''; ?>">
                                <input type="text" class="regular-text" id="limit-login-app-setup-code" value="<?php echo ( !empty( $app_setup_code ) ) ? esc_attr( $app_setup_code ) : ''; ?>">
                                <button class="button" id="limit-login-app-setup"><?php echo __( 'Submit', 'limit-login-attempts-reloaded' ); ?></button>
                                <span class="spinner llar-app-ajax-spinner"></span><br>
                                <span class="llar-app-ajax-msg"></span>
                                <p class="description"><?php _e( 'Add this code to all websites in your network to sync protection (payment required for additional domains unless subscribed to Agency plan).', 'limit-login-attempts-reloaded' ) ?></p>
                            </div>

						    <?php if( $active_app === 'local' ) : ?>
                                <p class="description"><?php echo sprintf(
									    __( 'Use the <a href="%s" target="_blank">premium app</a> that we offer or follow the instructions on <a href="%s" target="_blank">how to</a> create your own one.', 'limit-login-attempts-reloaded' ),
									    'https://www.limitloginattempts.com/info.php?from=plugin-settings',
									    'https://www.limitloginattempts.com/app/?from=plugin-settings' );
								    ?></p>
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
                                <th scope="row" valign="top"><?php echo $setting_params['label']; ?></th>
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

            });

        })(jQuery);
    </script>

    <p class="submit">
        <input class="button button-primary" name="llar_update_settings" value="<?php echo __( 'Save Settings', 'limit-login-attempts-reloaded' ); ?>"
               type="submit"/>
    </p>
</form>

