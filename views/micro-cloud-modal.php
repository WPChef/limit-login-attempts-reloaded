<?php


use LLAR\Core\Config;

if( !defined( 'ABSPATH' ) ) exit();

/**
 * @var $this LLAR\Core\LimitLoginAttempts
 */

//$admin_email = ( !is_multisite() ) ? get_option( 'admin_email' ) : get_site_option( 'admin_email' );
//$onboarding_popup_shown = Config::get( 'onboarding_popup_shown' );
//$setup_code = Config::get( 'app_setup_code' );

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
                    <div class="field-wrap">
                        <div class="field-title">
                            <?php _e( 'Add your license key', 'limit-login-attempts-reloaded' ); ?>
                        </div>
                        <div class="field-key">
                            <input type="text" class="input_border" id="llar-setup-code-field" placeholder="Your key" value="">
                            <button class="button menu__item button__orange llar-disabled" id="llar-app-install-btn">
                                <?php _e( 'Activate', 'limit-login-attempts-reloaded' ); ?>
                                <span class="dashicons dashicons-arrow-right-alt"></span>
                                <span class="preloader-wrapper"><span class="spinner llar-app-ajax-spinner"></span></span>
                            </button>
                        </div>
                        <div class="field-error"></div>
                        <div class="field-desc">
                            <?php _e( 'The license key can be found in your email if you have subscribed to premium', 'limit-login-attempts-reloaded' ); ?>
                        </div>
                    </div>
                </div>
                <div class="button_block-single">
                    <button class="button next_step menu__item button__transparent_grey button-skip">
                        <?php _e( 'Skip', 'limit-login-attempts-reloaded' ); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php
$popup_complete_install_content = ob_get_clean();
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
                content: `<?php echo trim( $popup_complete_install_content ); ?>`,
                lazyOpen: true,
                type: 'default',
                typeAnimated: true,
                draggable: false,
                animation: 'top',
                animationBounce: 1,
                offsetTop: 50,
                offsetBottom: 0,
                boxWidth: 1280,
                containerFluid: true,
                bgOpacity: 0.9,
                useBootstrap: false,
                closeIcon: true,
                onClose: function() {
                    //$.post(ajaxurl, {
                    //    action: 'dismiss_onboarding_popup',
                    //    sec: '<?php //echo esc_js( wp_create_nonce( "llar-dismiss-onboarding-popup" ) ); ?>//'
                    //}, function(){});
                },
                buttons: {},
                onOpenBefore: function () {

                    const button_next = 'button.button.next_step';
                    const $setup_code_key = $('#llar-setup-code-field');
                    const $activate_button = $('#llar-app-install-btn');
                    const $spinner = $activate_button.find('.preloader-wrapper .spinner');
                    const disabled = 'llar-disabled';
                    const visibility = 'llar-visibility';

                    console.log('!!!!!!!!!!!!!!');

                    $setup_code_key.on('input', function() {

                        if ($(this).val().trim() !== '') {
                            $activate_button.removeClass(disabled);
                        } else {
                            $activate_button.addClass(disabled);
                        }
                    });

                    $activate_button.on('click', function (e) {
                        e.preventDefault();

                        if($activate_button.hasClass(disabled)) {
                            return;
                        }

                        const $error = $('.field-error');

                        $error.text('').hide();
                        $activate_button.addClass(disabled);
                        $spinner.addClass(visibility);
                        const $setup_code = $setup_code_key.val();

                        // activate_license_key($setup_code)
                        //     .then(function(response) {
                        //         setTimeout(function() {
                        //             next_step_line(2);
                        //             $(button_next).trigger('click');
                        //         }, 500);
                        //     })
                        //     .catch(function(response) {
                        //
                        //         if (!response.success && response.data.msg) {
                        //            $error.text(response.data.msg).show();
                        //
                        //            setTimeout(function(){
                        //                $error.text('').hide();
                        //                $setup_code_key.val('');
                        //
                        //            }, 3500);
                        //            $spinner.removeClass(visibility);
                        //        }
                        //     });
                    })

                    $(document).on('click', button_next, function() {

                        // let next_step = next_step_line();
                        const $html_body = $('.llar-onboarding__body');
                    })
                }
            });
        })

    })(jQuery)
</script>