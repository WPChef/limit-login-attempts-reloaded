<?php

use LLAR\Core\Config;

if( !defined( 'ABSPATH' ) ) exit();

/**
 * @var $this LLAR\Core\LimitLoginAttempts
 */

$admin_email = ( !is_multisite() ) ? get_option( 'admin_email' ) : get_site_option( 'admin_email' );
$onboarding_popup_shown = Config::get( 'onboarding_popup_shown' );
$setup_code = Config::get( 'app_setup_code' );

if( $onboarding_popup_shown || !empty( $setup_code ) ) return;

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

<?php
ob_start(); ?>
<div class="llar-onboarding__body">
    <div class="title">
        <img src="<?php echo LLA_PLUGIN_URL ?>/assets/css/images/email.png">
        <?php _e( 'Notification Settings', 'limit-login-attempts-reloaded' ); ?>
    </div>
    <div class="card mx-auto">
        <div class="field-wrap">
            <div class="field-title-add">
                <?php echo sprintf(__( 'Site URL: %s', 'limit-login-attempts-reloaded' ), esc_url(get_site_url())); ?>
            </div>
            <div class="field-email">
                <input type="text" class="input_border" id="llar-subscribe-email" placeholder="Your email" value="<?php esc_attr_e( $admin_email ); ?>">
            </div>
            <div class="field-desc-additional">
                <?php _e( 'This email will receive notifications of unauthorized access to your website. You may turn this off in your settings.', 'limit-login-attempts-reloaded' ); ?>
            </div>
            <div class="field-checkbox">
                <input type="checkbox" name="lockout_notify_email" checked value="email"/>
                <span>
                    <?php _e( 'Sign me up for the LLAR newsletter to receive important security alerts, plugin updates, and helpful guides.', 'limit-login-attempts-reloaded' ); ?>
                </span>
            </div>
        </div>
    </div>
    <div class="button_block-horizon">
        <button class="button menu__item button__orange" id="llar-subscribe-email-button">
            <?php _e( 'Continue', 'limit-login-attempts-reloaded' ) ?>
            <span class="preloader-wrapper"><span class="spinner llar-app-ajax-spinner"></span></span>
        </button>
        <button class="button next_step menu__item button__transparent_orange button-skip">
            <?php _e( 'Skip', 'limit-login-attempts-reloaded' ); ?>
        </button>
    </div>
</div>

<?php
$content_step_2 = ob_get_clean();
?>

<?php
ob_start(); ?>
<div class="llar-onboarding__body">
    <div class="title">
        <img src="<?php echo LLA_PLUGIN_URL ?>/assets/css/images/rocket-min.png">
        <?php _e( 'Limited Upgrade (Free)', 'limit-login-attempts-reloaded' ); ?>
    </div>
    <div class="card mx-auto">
        <div class="field-wrap">
            <div class="field-desc-add">
                <?php _e( 'Help us secure our network and we’ll provide you with limited access to our premium features including our login firewall, IP intelligence, and performance optimizer (up to 1,000 requests monthly)', 'limit-login-attempts-reloaded' ); ?>
            </div>
            <div class="field-desc-add">
                <?php _e( '<b>Would you like to opt-in?</b>', 'limit-login-attempts-reloaded' ); ?>
            </div>
        </div>
        <div class="llar-upgrade-subscribe">
            <div class="button_block-horizon">
                <button class="button menu__item button__transparent_orange orange-back" id="llar-limited-upgrade-subscribe">
                    <?php _e( 'Yes', 'limit-login-attempts-reloaded' ); ?>
                    <span class="preloader-wrapper"><span class="spinner llar-app-ajax-spinner"></span></span>
                </button>
                <button class="button next_step menu__item button__transparent_grey gray-back">
                    <?php _e( 'No', 'limit-login-attempts-reloaded' ); ?>
                </button>
            </div>
            <div class="explanations">
                <?php echo sprintf(
                    __( 'We\'ll send you instructions via email to complete setup. You may opt-out of this program at any time. You accept our <a class="link__style_color_inherit llar_turquoise" href="%s" target="_blank">terms of service</a> by participating in this program.', 'limit-login-attempts-reloaded' ),
                    'https://www.limitloginattempts.com/troubleshooting-guide-fixing-issues-with-non-functioning-emails-from-your-wordpress-site/'
                ); ?>
            </div>
        </div>
        <div class="llar-upgrade-subscribe_notification">
            <img src="<?php echo LLA_PLUGIN_URL ?>/assets/css/images/start.png">
            <?php _e( 'Instructions sent via email', 'limit-login-attempts-reloaded' ); ?>
        </div>
        <div class="llar-upgrade-subscribe_notification__error">
            <img src="<?php echo LLA_PLUGIN_URL ?>/assets/css/images/start.png">
            <?php _e( 'The server is not working, try again later', 'limit-login-attempts-reloaded' ); ?>
        </div>
        <div class="button_block-single">
            <button class="button next_step menu__item button__transparent_grey button-skip">
                <?php _e( 'Skip', 'limit-login-attempts-reloaded' ); ?>
            </button>
            <button class="button next_step menu__item button__transparent_orange orange-back llar-display-none">
                <?php _e( 'Continue', 'limit-login-attempts-reloaded' ); ?>
            </button>
        </div>
    </div>
</div>

<?php
$content_step_3 = ob_get_clean();
?>

<?php
ob_start(); ?>
<div class="llar-onboarding__body">
    <div class="title">
        <img src="<?php echo LLA_PLUGIN_URL ?>/assets/css/images/like-min.png">
        <?php _e( 'Thank you for completing the setup', 'limit-login-attempts-reloaded' ); ?>
    </div>
    <div class="card mx-auto">
        <div class="field-image">
            <img src="<?php echo LLA_PLUGIN_URL ?>/assets/css/images/schema-ok-min.png">
        </div>
        <div class="field-desc">
            <?php _e( 'This email will receive notifications of unauthorized access to your website. You may turn this off in your settings.', 'limit-login-attempts-reloaded' ); ?>
        </div>
        <div class="button_block-single">
            <button class="button next_step menu__item button__orange">
                <?php _e( 'Go To Dashboard', 'limit-login-attempts-reloaded' ); ?>
            </button>
        </div>
    </div>
</div>

<?php
$content_step_4 = ob_get_clean();
?>

<script>
    ;(function($){

        $(document).ready(function(){

            const ondoarding_modal = $.dialog({
                title: false,
                content: `<?php echo trim( $popup_complete_install_content ); ?>`,
                type: 'default',
                typeAnimated: true,
                draggable: false,
                animation: 'top',
                animationBounce: 1,
                offsetTop: 0,
                offsetBottom: 0,
                boxWidth: '100%',
                containerFluid: true,
                onContentReady: function () {
                    video_player_load();
                },
                bgOpacity: 0.9,
                useBootstrap: false,
                closeIcon: true,
                onClose: function() {
                    $.post(ajaxurl, {
                        action: 'dismiss_onboarding_popup',
                        sec: '<?php echo esc_js( wp_create_nonce( "llar-dismiss-onboarding-popup" ) ); ?>'
                    }, function(){});
                },
                buttons: {},
                onOpenBefore: function () {

                    const button_next = 'button.button.next_step';
                    const $setup_code_key = $('#llar-setup-code-field');
                    const $activate_button = $('#llar-app-install-btn');
                    const $spinner = $activate_button.find('.preloader-wrapper .spinner');
                    const disabled = 'llar-disabled';
                    const visibility = 'llar-visibility';

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

                        activate_license_key($setup_code)
                            .then(function(response) {
                                setTimeout(function() {
                                    next_step_line(2);
                                    $(button_next).trigger('click');
                                }, 500);
                            })
                            .catch(function(response) {

                                if (!response.success && response.data.msg) {
                                   $error.text(response.data.msg).show();

                                   setTimeout(function(){
                                       $error.text('').hide();
                                       $setup_code_key.val('');

                                   }, 3500);
                                   $spinner.removeClass(visibility);
                               }
                            });
                    })

                    $(document).on('click', button_next, function() {

                        let next_step = next_step_line();
                        const $html_body = $('.llar-onboarding__body');

                        if (next_step === 2) {
                            $html_body.replaceWith(<?php echo json_encode(trim($content_step_2), JSON_HEX_QUOT | JSON_HEX_TAG); ?>);

                            const $subscribe_email = $('#llar-subscribe-email');
                            const $subscribe_email_button = $('#llar-subscribe-email-button');
                            const $spinner = $subscribe_email_button.find('.preloader-wrapper .spinner');
                            const $is_subscribe = !!$('.field-checkbox input[name="lockout_notify_email"]').prop('checked');

                            $subscribe_email_button.on('click', function () {
                                $subscribe_email_button.addClass(disabled);
                                $spinner.addClass(visibility);

                                $.post(ajaxurl, {
                                    action: 'subscribe_email',
                                    email: $subscribe_email.val(),
                                    is_subscribe_yes: $is_subscribe,
                                    sec: '<?php echo esc_js( wp_create_nonce( "llar-subscribe-email" ) ); ?>'
                                }, function(response){

                                    $subscribe_email_button.removeClass(disabled);
                                    $(button_next).trigger('click');
                                });
                            })
                        }
                        else if (next_step === 3) {

                            $html_body.replaceWith(<?php echo json_encode(trim($content_step_3), JSON_HEX_QUOT | JSON_HEX_TAG); ?>);

                            const $limited_upgrade_subscribe = $('#llar-limited-upgrade-subscribe');
                            const $block_upgrade_subscribe = $('.llar-upgrade-subscribe');
                            const $subscribe_notification = $('.llar-upgrade-subscribe_notification');
                            const $subscribe_notification_error = $('.llar-upgrade-subscribe_notification__error');
                            const $button_next = $('.button.next_step');
                            const $button_skip = $button_next.filter('.button-skip');
                            const $spinner = $limited_upgrade_subscribe.find('.preloader-wrapper .spinner');

                            console.log($button_next);
                            console.log($button_skip);


                            $limited_upgrade_subscribe.on('click', function () {

                                let email = '<?php esc_attr_e( $admin_email ); ?>';

                                $button_next.addClass(disabled);
                                $limited_upgrade_subscribe.addClass(disabled);
                                $spinner.addClass(visibility);

                                activate_micro_cloud(email)
                                    .then(function(response) {

                                        if(response && response.setup_code) {

                                            activate_license_key(response.setup_code)
                                                .then(function(response) {

                                                    $block_upgrade_subscribe.addClass('llar-display-none');
                                                    $subscribe_notification.addClass('llar-display-block');
                                                    $button_next.removeClass(disabled);
                                                    $button_next.removeClass('llar-display-none');
                                                    $button_skip.addClass('llar-display-none');
                                                })
                                                .catch(function() {
                                                    $block_upgrade_subscribe.addClass('llar-display-none');
                                                    $subscribe_notification_error.addClass('llar-display-block')
                                                });

                                        }
                                        else {
                                            $subscribe_notification_error.addClass('llar-display-block')

                                        }
                                    })
                                    .catch(function(response) {
                                        $block_upgrade_subscribe.addClass('llar-display-none');
                                        $subscribe_notification_error.addClass('llar-display-block')
                                    });

                            });
                        }
                        else if (next_step === 4) {
                            $html_body.replaceWith(<?php echo json_encode(trim($content_step_4), JSON_HEX_QUOT | JSON_HEX_TAG); ?>);
                        }
                        else if (!next_step) {
                            ondoarding_modal.close();
                        }
                    })
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


        function next_step_line(offset = 1) {

            let step_line = $('.llar-onboarding__line .point__block');
            let active_step = step_line.filter('.active').data('step');

            if (active_step < 4) {
                step_line.filter('[data-step="' + active_step + '"]').removeClass('active');

                for (let i = 1; i <= offset; i++) {
                    active_step++;
                    step_line.filter('[data-step="' + active_step + '"]').addClass('visited');
                }

                step_line.filter('[data-step="' + active_step + '"]').addClass('active');
                return active_step;
            }
            else {
                return false;
            }
        }

        function activate_micro_cloud(email) {
            url_api = 'https://api.limitloginattempts.com/checkout-staging/network';
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


        function video_player_load() {

            let isPlyrInitialized = false;
            let video_player = $('#video-play');
            let script_loaded = false;
            let style_loaded = false;
            let player;

            let script = document.createElement('script');
            let style = document.createElement('link');
            script.src = 'https://cdn.plyr.io/3.7.8/plyr.js';
            style.href = 'https://cdn.plyr.io/3.7.8/plyr.css';
            style.rel = 'stylesheet';

            document.body.appendChild(script);
            document.body.appendChild(style);

            video_player.on('click', 'img', function () {

                if (script_loaded) {

                    script.onload = function() {
                        script_loaded = true;

                        if (!isPlyrInitialized) {

                            player = new Plyr('#player');
                            isPlyrInitialized = true;
                        }
                    };
                }
                else {

                    if (!isPlyrInitialized) {
                        player = new Plyr('#player');
                        isPlyrInitialized = true;
                    }
                }

                $('#video-poster').hide();
                video_player.find('.video__iframe').addClass('play');

            });
        }

    })(jQuery)
</script>