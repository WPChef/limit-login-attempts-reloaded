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
    <div class="llar-onboarding-popup__content">
        <div class="logo">
            <img src="<?php echo LLA_PLUGIN_URL ?>/assets/img/icon-logo-menu.png">
        </div>
        <div class="llar-onboarding__line">
            <div class="point__block visited active" data-step="1">
                <div class="point"></div>
                <div class="description">
                    <?php _e( 'Welcome', 'limit-login-attempts-reloaded' ); ?>
                </div>
            </div>
            <div class="point__block" data-step="2">
                <div class="point"></div>
                <div class="description">
                    <?php _e( 'Notifications', 'limit-login-attempts-reloaded' ); ?>
                </div>
            </div>
            <div class="point__block" data-step="3">
                <div class="point"></div>
                <div class="description">
                    <?php _e( 'Limited Upgrade', 'limit-login-attempts-reloaded' ); ?>
                </div>
            </div>
            <div class="point__block" data-step="4">
                <div class="point"></div>
                <div class="description">
                    <?php _e( 'Completion', 'limit-login-attempts-reloaded' ); ?>
                </div>
            </div>
        </div>
        <div class="llar-onboarding__body">
            <div class="title">
                <img src="<?php echo LLA_PLUGIN_URL ?>/assets/css/images/welcome.png">
                <?php _e( 'Welcome', 'limit-login-attempts-reloaded' ); ?>
            </div>
            <div class="card mx-auto">
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
            <div class="card mx-auto">
                <div class="field-wrap">
                    <div class="field-title">
                        <?php _e( 'Not A Premium User?', 'limit-login-attempts-reloaded' ); ?>
                    </div>
                    <div class="field-desc-add">
                        <?php _e( 'We <b>highly recommend</b> upgrading to premium for the best protection against brute force attacks and unauthorized logins', 'limit-login-attempts-reloaded' ); ?>
                    </div>
                    <ul class="field-list">
                        <li class="item">
                            <?php _e( 'Detect, counter, and deny unauthorized logins with IP Intelligence', 'limit-login-attempts-reloaded' ); ?>
                        </li>
                        <li class="item">
                            <?php _e( 'Absorb failed login activity to improve site performance', 'limit-login-attempts-reloaded' ); ?>
                        </li>
                        <li class="item">
                            <?php _e( 'Block IPs by country, premium support, and much more!', 'limit-login-attempts-reloaded' ); ?>
                        </li>
                    </ul>
                    <div class="field-video" id="video-play">
                        <img src="<?php echo LLA_PLUGIN_URL ?>/assets/css/images/video-bg.webp" id="video-poster">
                        <div class="video__iframe">
                            <div id="player" data-plyr-provider="youtube" data-plyr-embed-id="IsotthPWCPA"></div>
                        </div>
                    </div>
                    <div class="button_block">
                        <a href="https://www.limitloginattempts.com/info.php?from=plugin-onboarding-plans" class="button menu__item button__orange" target="_blank">
                            <?php _e( 'Yes, show me plan options', 'limit-login-attempts-reloaded' ); ?>
                        </a>
                        <button class="button next_step menu__item button__transparent_orange">
                            <?php _e( 'No, I don’t want advanced protection', 'limit-login-attempts-reloaded' ); ?>
                        </button>
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
                offsetTop: 0,
                offsetBottom: 0,
                boxWidth: '50%',
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