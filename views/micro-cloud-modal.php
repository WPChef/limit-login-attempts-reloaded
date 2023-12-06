<?php


use LLAR\Core\Config;

if( !defined( 'ABSPATH' ) ) exit();

/**
 * @var $this LLAR\Core\LimitLoginAttempts
 */

//$admin_email = ( !is_multisite() ) ? get_option( 'admin_email' ) : get_site_option( 'admin_email' );
//$onboarding_popup_shown = Config::get( 'onboarding_popup_shown' );
//$setup_code = Config::get( 'app_setup_code' );

$admin_email = ( !is_multisite() ) ? get_option( 'admin_email' ) : get_site_option( 'admin_email' );

$url_site = esc_url(get_site_url());

ob_start(); ?>
    <div class="micro_cloud_modal__content">
        <div class="micro_cloud_modal__body">
            <div class="micro_cloud_modal__body_header">
                <div class="left_side">
                    <div class="title">
                        <?php _e( 'Get Started with Micro Cloud for FREE', 'limit-login-attempts-reloaded' ); ?>
                    </div>
                    <div class="description">
                        <?php _e( 'Help us secure our network and we’ll provide you with limited access to our premium features including our login firewall, IP Intelligence, and performance optimizer.', 'limit-login-attempts-reloaded' ); ?>
                    </div>
                    <div class="description-add">
                        <?php _e( 'Please note that some domains have very high brute force activity, which may cause Micro Cloud to run out of resources in under 24 hours. We will send an email when resources are fully utilized and the app reverts back to the free version. You may upgrade to one of our premium plans to prevent the app from reverting.', 'limit-login-attempts-reloaded' ); ?>
                    </div>
                </div>
                <div class="right_side">
                    <img src="<?php echo LLA_PLUGIN_URL ?>/assets/css/images/micro-cloud-image-min.png">
                </div>
            </div>
            <div class="card mx-auto">
                <div class="card-header">
                    <div class="title">
                        <img src="<?php echo LLA_PLUGIN_URL ?>/assets/css/images/tools.png">
                        <?php _e( 'How To Activate Micro Cloud', 'limit-login-attempts-reloaded' ); ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="url_site">
                        <?php echo sprintf(__( 'Site URL: <a href="%s" class="llar_orange">%s</a>', 'limit-login-attempts-reloaded' ), $url_site, $url_site); ?>
                    </div>
                    <div class="description">
                        <?php _e( 'Please enter the email that will receive setup instructions', 'limit-login-attempts-reloaded' ); ?>
                    </div>
                    <div class="field-wrap">
                        <div class="field-email">
                            <input type="text" class="input_border" id="llar-subscribe-email" placeholder="Your email" value="<?php esc_attr_e( $admin_email ); ?>">
                        </div>
                    </div>
                    <div class="button_block-single">
                        <button class="button menu__item button__orange" id="llar-button_subscribe-email">
                            <?php _e( 'Continue', 'limit-login-attempts-reloaded' ); ?>
                            <span class="preloader-wrapper"><span class="spinner llar-app-ajax-spinner"></span></span>
                        </button>
                        <div class="description_add">
                            <?php _e( 'By signing up you agree to our terms of service and privacy policy.', 'limit-login-attempts-reloaded' ); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php
$micro_cloud_popup_content = ob_get_clean();
?>

<script>
    ;(function($){

        $(document).ready(function(){

            const $button_micro_cloud = $('.button.button_micro_cloud');

            $button_micro_cloud.on('click', function () {
                console.log('@@@@@@@@@@@@@@@@@@');
                ondoarding_modal.open();
            })

            const ondoarding_modal = $.dialog({
                title: false,
                content: `<?php echo trim( $micro_cloud_popup_content ); ?>`,
                lazyOpen: true,
                type: 'default',
                typeAnimated: true,
                draggable: false,
                animation: 'top',
                animationBounce: 1,
                offsetTop: 50,
                boxWidth: 1280,
                bgOpacity: 0.9,
                useBootstrap: false,
                closeIcon: true,
                buttons: {},
                onOpenBefore: function () {

                    const $subscribe_email = $('#llar-subscribe-email');
                    const $button_subscribe_email = $('#llar-button_subscribe-email');
                    const $spinner = $button_subscribe_email.find('.preloader-wrapper .spinner');
                    const disabled = 'llar-disabled';
                    const visibility = 'llar-visibility';
                    let real_email = '<?php esc_attr_e( $admin_email ); ?>';;

                    $subscribe_email.on('blur', function() {

                        email = $(this).val();

                        if (!is_valid_email(email)) {
                            $button_subscribe_email.addClass(disabled)
                        }
                        else {
                            $button_subscribe_email.removeClass(disabled)
                            real_email = email;
                        }
                    });

                    $button_subscribe_email.on('click', function (e) {
                        e.preventDefault();
                        console.log('###############');

                        if($button_subscribe_email.hasClass(disabled)) {
                            return;
                        }

                        $button_subscribe_email.addClass(disabled);
                        $spinner.addClass(visibility);

                        activate_micro_cloud(real_email)
                            .then(function(response) {

                                if(response && response.setup_code) {

                                    activate_license_key(response.setup_code)
                                        .then(function(response) {

                                            // $block_upgrade_subscribe.addClass('llar-display-none');
                                            // $subscribe_notification.addClass('llar-display-block');
                                            // $button_next.removeClass(disabled);
                                            // $button_next.removeClass('llar-display-none');
                                            // $button_skip.addClass('llar-display-none');
                                        })
                                        .catch(function() {
                                            // $block_upgrade_subscribe.addClass('llar-display-none');
                                            // $subscribe_notification_error.addClass('llar-display-block')
                                        });

                                }
                                else {
                                    // $subscribe_notification_error.addClass('llar-display-block')

                                }
                            })
                            .catch(function(response) {
                                // $block_upgrade_subscribe.addClass('llar-display-none');
                                // $subscribe_notification_error.addClass('llar-display-block')
                            });

                    })

                    // $(document).on('click', button_next, function() {
                    //
                    //     // let next_step = next_step_line();
                    //     const $html_body = $('.llar-onboarding__body');
                    // })
                }
            });
        })

        function activate_license_key($setup_code) {

            return new Promise(function(resolve, reject) {
                $.post(ajaxurl, {
                    action: 'app_setup',
                    code:   $setup_code,
                    sec:    '<?php echo esc_js( wp_create_nonce( "llar-app-setup" ) ); ?>'
                }, function(response) {

                    if (response.success) {
                        resolve(response);
                    } else {
                        reject(response);
                    }
                });
            });
        }

        function activate_micro_cloud(email) {
            let url_api = 'https://api.limitloginattempts.com/checkout-staging/network';
            // url_api = ''https://api.limitloginattempts.com/checkout/network'';

            let form_data = [];
            form_data.push({name: 'group', value: 'free'});
            form_data.push({name: 'email', value: email});

            let form_object = form_data.reduce(function(object, item) {
                object[item.name] = item.value;
                return object;
            }, {});


            return new Promise(function(resolve, reject) {
                $.post({
                    url: url_api,
                    contentType: 'application/json',
                    dataType: 'json',
                    data: JSON.stringify(form_object),
                }, function (response) {

                    if (response) {
                        resolve(response);
                    } else {
                        reject(response);
                    }
                });
            });
        }

        function is_valid_email(email) {
            let email_regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return email_regex.test(email);
        }

    })(jQuery)
</script>