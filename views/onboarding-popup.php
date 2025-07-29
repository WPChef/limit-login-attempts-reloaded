<?php

use LLAR\Core\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * @var $this LLAR\Core\LimitLoginAttempts
 */

$admin_notify_email      = Config::get( 'admin_notify_email' );
$admin_email             = ! empty($admin_notify_email)
                               ? $admin_notify_email
                               : ( ( ! is_multisite() ) ? get_option( 'admin_email' ) : get_site_option( 'admin_email' ) );
$onboarding_popup_shown = Config::get( 'onboarding_popup_shown' );
$setup_code             = Config::get( 'app_setup_code' );

$url_site = parse_url( ( is_multisite() ) ? network_site_url() : site_url(), PHP_URL_HOST );

if ( $onboarding_popup_shown || ! empty( $setup_code ) ) {
	return;
}

ob_start(); ?>
<div class="llar-onboarding-popup__content">
    <div class="logo">
        <img src="<?php echo LLA_PLUGIN_URL ?>assets/img/icon-logo-menu-dark.png">
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
            <img src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/welcome.png">
			<?php _e( 'Welcome', 'limit-login-attempts-reloaded' ); ?>
        </div>
        <div class="title_description">
		    <?php _e( 'Before you start using the plugin, please complete onboarding (It only takes a minute).', 'limit-login-attempts-reloaded' ); ?>
        </div>
        <div class="card mx-auto">
            <div class="field-wrap">
                <div class="field-title">
			        <?php _e( 'Already using Premium? Add your Setup Code', 'limit-login-attempts-reloaded' ); ?>
                </div>
                <div class="field-key">
                    <input type="text" class="input_border" id="llar-setup-code-field" placeholder="<?php _e('Your Setup Code', 'limit-login-attempts-reloaded' ) ?>" value="">
                    <button class="button menu__item button__orange llar-disabled" id="llar-app-install-btn">
				        <?php _e( 'Activate', 'limit-login-attempts-reloaded' ); ?>
                        <span class="dashicons dashicons-arrow-right-alt"></span>
                        <span class="preloader-wrapper"><span class="spinner llar-app-ajax-spinner"></span></span>
                    </button>
                </div>
                <div class="field-error"></div>
                <div class="field-desc">
			        <?php _e( 'The Setup Code can be found in your email confirmation.', 'limit-login-attempts-reloaded' ); ?>
                </div>
            </div>
        </div>
        <div class="card mx-auto">
            <div class="field-wrap">
            <div class="field-wrap">
                <div class="field-title">
		            <?php _e( 'Not using Premium yet?', 'limit-login-attempts-reloaded' ); ?>
                </div>
                <div class="field-desc-add">
					<?php printf(
						__( 'With Premium, your site becomes part of a powerful, real-time threat intelligence network built on the data of %1$s over 80,000 WordPress sites. %2$s That means you’re not just blocking attackers after they strike — you’re %1$s preventing them from making legitimate login attempts. %2$s', 'limit-login-attempts-reloaded' ),
						'<span class="llar_turquoise">', '</span>' );
					?>
                </div>
                <div class="field-list-desc">
                    <div class="field-desc-item">
                        <img class="field-desc-item-icon" src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/icon-shield.png">
		                <?php _e( 'Cloud-based login protection with dynamic IP blocklists', 'limit-login-attempts-reloaded' ); ?>
                    </div>
                    <div class="field-desc-item">
                        <img class="field-desc-item-icon" src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/icon-lock.png">
		                <?php _e( '97% of brute force attacks blocked before they begin', 'limit-login-attempts-reloaded' ); ?>
                    </div>
                    <div class="field-desc-item">
                        <img class="field-desc-item-icon" src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/icon-reload.png">
		                <?php _e( 'Real-time threat updates powered by our global network', 'limit-login-attempts-reloaded' ); ?>
                    </div>
                    <div class="field-desc-item">
                        <img class="field-desc-item-icon" src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/icon-web.png">
		                <?php _e( 'Country & IP controls for greater control', 'limit-login-attempts-reloaded' ); ?>
                    </div>
                    <div class="field-desc-item">
                        <img class="field-desc-item-icon" src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/icon-dollar.png">
		                <?php _e( 'Powerful login security from just $0.10/day - built for sites of all sizes', 'limit-login-attempts-reloaded' ); ?>
                    </div>
                </div>
                <div class="button_block">
                    <a href="https://www.limitloginattempts.com/info.php?from=plugin-onboarding-plans"
                       class="button menu__item button__orange" target="_blank">
						<?php _e( 'Yes, show me plan options', 'limit-login-attempts-reloaded' ); ?>
                    </a>
                    <button class="button next_step menu__item button__transparent_orange">
						<?php _e( 'No, I don’t want advanced protection', 'limit-login-attempts-reloaded' ); ?>
                    </button>
                </div>
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
        <img src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/email.png">
		<?php _e( 'Notification Settings', 'limit-login-attempts-reloaded' ); ?>
    </div>
    <div class="card mx-auto">
        <div class="field-wrap">
            <div class="field-email">
                <input type="text" class="input_border" id="llar-subscribe-email" placeholder="<?php _e( 'Your email', 'limit-login-attempts-reloaded' ); ?>"
                       value="<?php esc_attr_e( $admin_email ); ?>">
            </div>
            <div class="field-desc-additional">
				<?php _e( 'This email will receive notifications of unauthorized access to your website. You may turn this off in your settings.', 'limit-login-attempts-reloaded' ); ?>
            </div>
            <div class="field-checkbox">
                <input type="checkbox" name="lockout_notify_email" value="email"/>
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
        <button class="button next_step menu__item button__transparent_orange button-skip" style="display: none">
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
        <img src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/rocket-min.png">
		<?php _e( 'Limited Upgrade (Free)', 'limit-login-attempts-reloaded' ); ?>
    </div>
    <div class="card mx-auto">
        <div class="field-wrap" id="llar-description-step-3">
            <div class="field-desc-add">
				<?php printf(
					__( 'Help us secure the WordPress network, and in return, we\'ll give you access to Micro Cloud - Our FREE premium plan. %1$s', 'limit-login-attempts-reloaded' ),
					'<br />' ); ?>
                <br>
				<?php printf(
					__( 'You’ll receive %1$s 1,000 monthly cloud requests %2$s to power advanced login protection tools that block more than 97%% of all attempted logins. %3$s', 'limit-login-attempts-reloaded' ),
					'<span class="llar_turquoise">', '</span>', '<br />' );
				?>
                <br>
				<?php printf(
					__( '%1$s By proceeding, you agree to participate in our threat-sharing network. %2$s %3$s', 'limit-login-attempts-reloaded' ),
					'<span class="llar_turquoise">', '</span>', '<br />' );
				?>
				<?php _e( 'You can switch back to the free version of the plugin at any time, which will deactivate Micro Cloud and stop all data sharing.', 'limit-login-attempts-reloaded' ); ?>
            </div>
            <div class="field-desc-add">
				<?php _e( '<b>Would you like to opt-in?</b>', 'limit-login-attempts-reloaded' ); ?>
            </div>
        </div>
        <div class="llar-upgrade-subscribe">
            <div class="button_block-horizon">
                <button class="button next_step menu__item button__transparent_orange" id="llar-limited-upgrade-subscribe">
		            <?php _e( 'Yes', 'limit-login-attempts-reloaded' ); ?>
                    <span class="preloader-wrapper"><span class="spinner llar-app-ajax-spinner"></span></span>
                </button>
                <button class="button next_step menu__item button__transparent_grey" id="llar-limited-upgrade-no_subscribe">
		            <?php _e( 'No', 'limit-login-attempts-reloaded' ); ?>
                    <span class="preloader-wrapper"><span class="spinner llar-app-ajax-spinner"></span></span>
                </button>
            </div>
            <div class="explanations">
				<?php printf(
					__( 'We\'ll send you instructions via email to complete setup. You may opt-out of this program at any time. You accept our %1$s terms of service %2$s by participating in this program.', 'limit-login-attempts-reloaded' ),
					'<a class="link__style_color_inherit llar_turquoise" href="https://www.limitloginattempts.com/terms/" target="_blank">', '</a>' );
				?>
            </div>
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
        <img src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/like-min.png">
		<?php _e( 'Thank you for completing the setup', 'limit-login-attempts-reloaded' ); ?>
    </div>
    <div class="card mx-auto">
        <div class="field-image">
            <img src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/schema-ok-min.png">
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
    ;( function ( $ ) {

        $( document ).ready( function () {

            const ondoarding_modal = $.dialog( {
                title: false,
                content: `<?php echo trim( $popup_complete_install_content ); ?>`,
                type: 'default',
                typeAnimated: true,
                draggable: false,
                animation: 'top',
                animationBounce: 1,
                offsetTop: 50,
                offsetBottom: 0,
                boxWidth: '95%',
                containerFluid: true,
                bgOpacity: 0.9,
                useBootstrap: false,
                closeIcon: true,
                onClose: function () {

                    let clear_url = window.location.protocol + "//" + window.location.host + window.location.pathname;
                    let target_url = clear_url + '?page=limit-login-attempts&tab=dashboard';

                    if (window.location.href === target_url) {
                        window.location.reload();
                    } else {
                        window.location = target_url;
                    }
                },
                buttons: {},
                onOpenBefore: function () {

                    const button_next = 'button.button.next_step';
                    const $setup_code_key = $( '#llar-setup-code-field' );
                    const $activate_button = $( '#llar-app-install-btn' );
                    const $spinner = $activate_button.find( '.preloader-wrapper .spinner' );
                    const disabled = 'llar-disabled';
                    const visibility = 'llar-visibility';
                    let email;

                    $setup_code_key.on( 'input', function () {

                        if ( $( this ).val().trim() !== '' ) {
                            $activate_button.removeClass( disabled );
                        } else {
                            $activate_button.addClass( disabled );
                        }
                    });

                    $activate_button.on( 'click', function ( e ) {
                        e.preventDefault();

                        if ( $activate_button.hasClass( disabled ) ) {
                            return;
                        }

                        const $error = $( '.field-error' );
                        const $setup_code = $setup_code_key.val();
                        $error.text( '' ).hide();
                        $activate_button.addClass( disabled );
                        $spinner.addClass( visibility );

                        llar_activate_license_key( $setup_code )
                            .then( function () {
                                setTimeout( function () {
                                    next_step_line( 2 );
                                    $( button_next ).trigger( 'click' );
                                }, 500 );
                            } )
                            .catch( function ( response ) {

                                if ( ! response.success && response.data.msg ) {
                                    $error.text( response.data.msg ).show();

                                    setTimeout( function () {
                                        $error.text( '' ).hide();
                                        $setup_code_key.val( '' );

                                    }, 4000 );
                                    $spinner.removeClass( visibility );
                                }
                            } );
                    } )

                    $( document ).on( 'click', button_next, function () {

                        let next_step = next_step_line();
                        const $html_onboarding_body = $( '.llar-onboarding__body' );

                        if ( next_step === 2 ) {
                            $html_onboarding_body.replaceWith( <?php echo json_encode( trim( $content_step_2 ), JSON_HEX_QUOT | JSON_HEX_TAG ); ?> );

                            const $subscribe_email = $( '#llar-subscribe-email' );
                            const $subscribe_email_button = $( '#llar-subscribe-email-button' );
                            const $spinner = $subscribe_email_button.find( '.preloader-wrapper .spinner' );

                            email = $subscribe_email.val().trim();

                            $subscribe_email.on( 'blur', function () {

                                email = $ ( this ).val().trim();

                                if ( ! llar_is_valid_email( email ) ) {
                                    $subscribe_email_button.addClass( disabled )
                                } else {
                                    $subscribe_email_button.removeClass( disabled )
                                }
                            });

                            $subscribe_email_button.on( 'click', function () {

                                const $is_subscribe = !! $( '.field-checkbox input[name="lockout_notify_email"]' ).prop( 'checked' );

                                $subscribe_email_button.addClass( disabled );
                                $spinner.addClass( visibility );

                                let data = {
                                    action: 'subscribe_email',
                                    email: email,
                                    is_subscribe_yes: $is_subscribe,
                                    sec: llar_vars.nonce_subscribe_email
                                }

                                llar_ajax_callback_post( ajaxurl, data )
                                    .then( function () {
                                        $subscribe_email_button.removeClass( disabled );
                                        $( button_next ).trigger( 'click' );
                                    } )

                            } )
                        } else if ( next_step === 3 ) {

                            $html_onboarding_body.replaceWith( <?php echo json_encode( trim( $content_step_3 ), JSON_HEX_QUOT | JSON_HEX_TAG ); ?> );

                            const $limited_upgrade_subscribe = $( '#llar-limited-upgrade-subscribe' );
                            const $limited_upgrade_no_subscribe = $( '#llar-limited-upgrade-no_subscribe' );
                            const $block_upgrade_subscribe = $( '.llar-upgrade-subscribe' );
                            const $button_next = $( '.button.next_step' );
                            const $button_skip = $button_next.filter( '.button-skip' );
                            const spinner = '.preloader-wrapper .spinner';
                            const $description = $( '#llar-description-step-3' );


                            if ( email === '' || email === null ) {
                                email = '<?php esc_attr_e( $admin_email ); ?>'
                            }

                            $limited_upgrade_no_subscribe.on( 'click', function () {

                                $(this).addClass(disabled);
                                $limited_upgrade_no_subscribe.addClass(disabled);
                                $(this).find( spinner ).addClass(visibility);
                            });

                            $limited_upgrade_subscribe.on( 'click', function () {

                                $button_next.addClass( disabled );
                                $limited_upgrade_subscribe.addClass( disabled );
                                $(this).find( spinner ).addClass( visibility );

                                llar_activate_micro_cloud( email )
                                    .then( function () {

                                        $description.addClass( 'llar-display-none' );
                                        $button_next.removeClass( disabled );
                                        $button_next.removeClass( 'llar-display-none' );
                                        $button_skip.addClass( 'llar-display-none' );
                                    })
                                    .catch( function ( response ) {

                                        $button_skip.removeClass( disabled );
                                    })
                                    .finally( function () {

                                        $block_upgrade_subscribe.addClass( 'llar-display-none' );
                                    } )

                            });
                        } else if ( next_step === 4 ) {

                            let data = {
                                action: 'dismiss_onboarding_popup',
                                sec: llar_vars.nonce_dismiss_onboarding_popup
                            }
                            llar_ajax_callback_post( ajaxurl, data )
                                .then( function () {

                                    setTimeout(function() {
                                        $html_onboarding_body.replaceWith( <?php echo json_encode( trim( $content_step_4 ), JSON_HEX_QUOT | JSON_HEX_TAG ); ?> );
                                    }, 1500);
                                } )

                        } else if ( !next_step ) {
                            ondoarding_modal.close();
                        }
                    } )
                }
            } );
        } )

        function next_step_line( offset = 1 ) {

            let step_line = $( '.llar-onboarding__line .point__block' );
            let active_step = step_line.filter( '.active' ).data( 'step' );

            if ( active_step < 4 ) {
                step_line.filter( '[data-step="' + active_step + '"]' ).removeClass( 'active' );

                for ( let i = 1; i <= offset; i++ ) {
                    active_step++;
                    step_line.filter( '[data-step="' + active_step + '"]' ).addClass( 'visited' );
                }

                step_line.filter( '[data-step="' + active_step + '"]' ).addClass( 'active' );
                return active_step;
            } else {
                return false;
            }
        }

    } )( jQuery )
</script>