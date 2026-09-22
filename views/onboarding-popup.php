<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Onboarding popup.
 *
 * All copy and state comes from the controller via get_onboarding_popup_view_vars().
 *
 * @var $this LLAR\Core\AdminUiController
 */

$popup = $this->get_onboarding_popup_view_vars();

if ( ! $popup['should_show'] ) {
	return;
}

$spinner = '<span class="preloader-wrapper"><span class="spinner llar-app-ajax-spinner"></span></span>';

ob_start(); ?>
<div class="llar-onboarding-popup__content">
    <div class="logo">
        <img src="<?php echo esc_url( LLA_PLUGIN_URL ); ?>assets/img/icon-logo-menu-dark.png">
    </div>
    <div class="llar-onboarding__line">
        <?php foreach ( $popup['steps'] as $step_index => $step_label ) : ?>
        <div class="point__block<?php echo 0 === $step_index ? ' visited active' : ''; ?>" data-step="<?php echo (int) $step_index + 1; ?>">
            <div class="point"></div>
            <div class="description">
				<?php echo esc_html( $step_label ); ?>
            </div>
        </div>
        <?php endforeach ?>
    </div>
    <div class="llar-onboarding__body">
        <div class="title">
            <img src="<?php echo esc_url( LLA_PLUGIN_URL ); ?>assets/css/images/welcome.png">
			<?php echo esc_html( $popup['step1']['title'] ); ?>
        </div>
        <div class="title_description">
		    <?php echo esc_html( $popup['step1']['subtitle'] ); ?>
        </div>
        <div class="card mx-auto">
            <div class="field-wrap">
                <div class="field-title">
			        <?php echo esc_html( $popup['step1']['setup_title'] ); ?>
                </div>
                <div class="field-key">
                    <input type="text" class="input_border" id="llar-setup-code-field" placeholder="<?php echo esc_attr( $popup['step1']['setup_placeholder'] ); ?>" value="">
                    <button class="button menu__item button__orange llar-disabled" id="llar-app-install-btn">
				        <?php echo esc_html( $popup['step1']['setup_button'] ); ?>
                        <span class="dashicons dashicons-arrow-right-alt"></span>
                        <?php echo $spinner; ?>
                    </button>
                </div>
                <div class="field-error"></div>
                <div class="field-desc">
			        <?php echo esc_html( $popup['step1']['setup_desc'] ); ?>
                </div>
            </div>
        </div>
        <div class="card mx-auto">
            <div class="field-wrap">
            <div class="field-wrap">
                <div class="field-title">
		            <?php echo esc_html( $popup['step1']['premium_title'] ); ?>
                </div>
                <div class="field-desc-add">
					<?php echo $popup['step1']['premium_pitch']; ?>
                </div>
                <div class="field-list-desc">
                    <?php foreach ( $popup['step1']['premium_features'] as $feature ) : ?>
                    <div class="field-desc-item">
                        <img class="field-desc-item-icon" src="<?php echo esc_url( LLA_PLUGIN_URL ); ?>assets/css/images/<?php echo $feature['icon']; ?>">
		                <?php echo esc_html( $feature['text'] ); ?>
                    </div>
                    <?php endforeach ?>
                </div>
                <div class="button_block">
                    <a href="<?php echo esc_url( $popup['step1']['plans_url'] ); ?>"
                       class="button menu__item button__orange" target="_blank">
						<?php echo esc_html( $popup['step1']['plans_cta'] ); ?>
                    </a>
                    <button class="button next_step menu__item button__transparent_orange">
						<?php echo esc_html( $popup['step1']['skip_cta'] ); ?>
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
        <img src="<?php echo esc_url( LLA_PLUGIN_URL ); ?>assets/css/images/email.png">
		<?php echo esc_html( $popup['step2']['title'] ); ?>
    </div>
    <div class="card mx-auto">
        <div class="field-wrap">
            <div class="field-email">
                <input type="text" class="input_border" id="llar-subscribe-email" placeholder="<?php echo esc_attr( $popup['step2']['email_placeholder'] ); ?>"
                       value="<?php echo esc_attr( $popup['admin_email'] ); ?>">
            </div>
            <div class="field-desc-additional">
				<?php echo esc_html( $popup['step2']['desc'] ); ?>
            </div>
            <div class="field-checkbox">
                <input type="checkbox" name="lockout_notify_email" value="email"/>
                <span>
                    <?php echo esc_html( $popup['step2']['checkbox_label'] ); ?>
                </span>
            </div>
        </div>
    </div>
    <div class="button_block-horizon">
        <button class="button menu__item button__orange" id="llar-subscribe-email-button">
			<?php echo esc_html( $popup['step2']['continue_label'] ); echo $spinner; ?>
        </button>
        <button class="button next_step menu__item button__transparent_orange button-skip" style="display: none">
			<?php echo esc_html( $popup['step2']['skip_label'] ); ?>
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
        <img src="<?php echo esc_url( LLA_PLUGIN_URL ); ?>assets/css/images/rocket-min.png">
		<?php echo esc_html( $popup['step3']['title'] ); ?>
    </div>
    <div class="title_description">
		<?php echo esc_html( $popup['step3']['subtitle'] ); ?>
    </div>
    <div class="card mx-auto">
        <div class="field-wrap" id="llar-description-step-3">
            <div class="field-desc-add">
				<?php echo $popup['step3']['pitch']; ?>
                <br><br>
				<?php echo $popup['step3']['paragraphs_html']; ?>
            </div>
            <div class="field-desc-add">
				<b><?php echo esc_html( $popup['step3']['cta'] ); ?></b>
            </div>
        </div>
        <div class="llar-upgrade-subscribe">
            <div class="button_block-horizon">
                <button class="button next_step menu__item button__transparent_orange" id="llar-limited-upgrade-subscribe">
		            <?php echo esc_html( $popup['step3']['yes_label'] ); echo $spinner; ?>
                </button>
                <button class="button next_step menu__item button__transparent_grey" id="llar-limited-upgrade-no_subscribe">
		            <?php echo esc_html( $popup['step3']['no_label'] ); echo $spinner; ?>
                </button>
            </div>
            <div class="explanations">
				<?php echo $popup['step3']['terms']; ?>
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
        <img src="<?php echo esc_url( LLA_PLUGIN_URL ); ?>assets/css/images/like-min.png">
		<?php echo esc_html( $popup['step4']['title'] ); ?>
    </div>
    <div class="card mx-auto">
        <div class="field-image">
            <img src="<?php echo esc_url( LLA_PLUGIN_URL ); ?>assets/css/images/schema-ok-min.png">
        </div>
        <div class="button_block-single">
            <button class="button next_step menu__item button__orange">
				<?php echo esc_html( $popup['step4']['button_label'] ); echo $spinner; ?>
            </button>
        </div>
    </div>
</div>

<?php
$content_step_4 = ob_get_clean();
add_filter( 'wp_kses_allowed_html', function( $tags, $context ) {
	if ( 'post' === $context ) {
		$tags['form'] = array(
			'action' => true,
			'method' => true,
			'id'     => true,
			'class'  => true,
		);

		$tags['input'] = array(
			'type'        => true,
			'name'        => true,
			'value'       => true,
			'id'          => true,
			'class'       => true,
			'placeholder' => true,
			'checked'     => true,
			'disabled'    => true,
			'readonly'    => true,
		);
	}

	return $tags;
}, 10, 2 );

?>

<script>
    ;( function ( $ ) {

        const disabled = 'llar-disabled';
        const hidden = 'llar-hidden';
        const visibility = 'llar-visibility';
        const $button_go_to_dashboard = '.button.next_step.menu__item.button__orange';
        const $button_go_to_dashboard_spinner = '.preloader-wrapper, .preloader-wrapper .spinner';

        $( document ).ready( function () {
            const $body = $( 'body' );
            const $onboarding_panel = $( '.dashboard-section-4' );

            let onboardingCompleted = false;


            const ondoarding_modal = $.dialog( {
                title: false,
                content: `<?php echo wp_kses_post( trim( $popup_complete_install_content ) ); ?>`,
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
                closeIcon: function() {
                    // If onboarding is completed, prevent closing and reload page
                    if ( onboardingCompleted ) {
                        window.location.reload();
                        return false; // Prevent closing
                    }
                    // Allow closing if onboarding is not completed
                    return true;
                },
                backgroundDismiss: false,
                escapeKey: function() {
                    // Prevent closing by ESC key when onboarding is completed
                    if ( onboardingCompleted ) {
                        window.location.reload();
                        return false; // Prevent closing
                    }
                    // Allow closing if onboarding is not completed
                    return true;
                },
                onClose: function () {
                    $body.removeClass( disabled );
                    if ( ! onboardingCompleted ) {
                        llar_ajax_callback_post( ajaxurl, {
                            action: 'dismiss_onboarding_popup',
                            sec: llar_vars.nonce_dismiss_onboarding_popup
                        } ).catch( function() {
                            $body.removeClass( disabled );
                        } ).finally( function() {
                            $body.removeClass( disabled );
                        } );
                    }

                    $body.css('overflow', '');
                },
                buttons: {},
                onContentReady: function () {
                    this.$contentPane.attr('tabindex', '-1').focus();
                },
                onOpenBefore: function () {

                    const button_next = 'button.button.next_step';
                    const $setup_code_key = $( '#llar-setup-code-field' );
                    const $activate_button = $( '#llar-app-install-btn' );
                    const $spinner = $activate_button.find( '.preloader-wrapper .spinner' );
                    const spinner = '.preloader-wrapper .spinner';
                    let email;

                    $body.css('overflow', 'hidden');

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
                        const $closeIcon = $( '.jconfirm-closeIcon' );
                        $error.text( '' ).hide();
                        $activate_button.addClass( disabled );
                        $spinner.addClass( visibility );
                        $body.addClass( disabled );
                        $closeIcon.addClass( hidden );
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
                                    $body.removeClass( disabled );
                                    $closeIcon.removeClass( hidden );
                                    setTimeout( function () {
                                        $error.text( '' ).hide();
                                        $setup_code_key.val( '' );

                                    }, 4000 );
                                    $spinner.removeClass( visibility );
                                }
                            } )
                            .finally( function() {
                                $body.removeClass( disabled );
                            } );
                    } )

                    $( document ).on( 'click', button_next, function () {
                        let next_step = next_step_line();
                        const $html_onboarding_body = $( '.llar-onboarding__body' );

                        if ( next_step === 2 ) {
                            $html_onboarding_body.replaceWith( <?php echo wp_json_encode( trim( $content_step_2 ), JSON_HEX_QUOT | JSON_HEX_TAG ); ?> );

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
                                $body.addClass( disabled );
                                llar_ajax_callback_post( ajaxurl, data )
                                    .then( function () {
                                        $subscribe_email_button.removeClass( disabled );
                                        $( button_next ).trigger( 'click' );
                                    
                                    } )
                                    .catch( function() {
                                        $body.removeClass( disabled );
                                    } )
                                    .finally( function() {
                                        $body.removeClass( disabled );
                                    } )
                            } )
                        } else if ( next_step === 3 ) {

                            $html_onboarding_body.replaceWith( <?php echo wp_json_encode( trim( $content_step_3 ), JSON_HEX_QUOT | JSON_HEX_TAG ); ?> );

                            const $limited_upgrade_subscribe = $( '#llar-limited-upgrade-subscribe' );
                            const $limited_upgrade_no_subscribe = $( '#llar-limited-upgrade-no_subscribe' );
                            const $block_upgrade_subscribe = $( '.llar-upgrade-subscribe' );
                            const $button_next = $( '.button.next_step' );
                            const $button_skip = $button_next.filter( '.button-skip' );
                            const $description = $( '#llar-description-step-3' );


                            if ( email === '' || email === null ) {
                                email = '<?php echo esc_js( $popup['admin_email'] ); ?>'
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

                                $body.addClass( disabled );
                                llar_activate_micro_cloud( email )
                                    .then( function () {
                                        $description.addClass( 'llar-display-none' );
                                        $button_next.removeClass( disabled );
                                        $button_next.removeClass( 'llar-display-none' );
                                        $button_skip.addClass( 'llar-display-none' );
                                    })
                                    .catch( function ( response ) {
                                        $body.removeClass( disabled );
                                        $button_skip.removeClass( disabled );

                                        $.alert( {
                                            title: false,
                                            content: $( '<div/>' ).text( llar_micro_cloud_error_message( response ) ).html(),
                                            type: 'red',
                                        } );
                                    })
                                    .finally( function () {
                                        $body.removeClass( disabled );
                                        $block_upgrade_subscribe.addClass( 'llar-display-none' );
                                        onboardingCompleted = true;
                                        thank_you_for_completing_setup();
                                    } )

                            });
                        } else if ( next_step === 4 && !$body.hasClass( disabled ) ) {
                            thank_you_for_completing_setup();

                        } else if ( !next_step ) {
                            if ( onboardingCompleted ) {
                                $( $button_go_to_dashboard ).find( $button_go_to_dashboard_spinner ).addClass( visibility ).show();
                                window.location.reload();
                            } else {
                                ondoarding_modal.close();
                            }
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

        function thank_you_for_completing_setup() {
            const $html_onboarding_body = $( '.llar-onboarding__body' );
            let data = {
                action: 'dismiss_onboarding_popup',
                sec: llar_vars.nonce_dismiss_onboarding_popup
            }
            $( '.jconfirm-closeIcon' ).remove();
            llar_ajax_callback_post( ajaxurl, data )
            .then( function () {
                onboardingCompleted = true;
                $html_onboarding_body.replaceWith( <?php echo wp_json_encode( trim( $content_step_4 ), JSON_HEX_QUOT | JSON_HEX_TAG ); ?> );
                $( $button_go_to_dashboard ).on( 'click', function ( e ) {
                    e.preventDefault();
                    $( this ).find( $button_go_to_dashboard_spinner ).addClass( visibility ).show();
                    window.location.reload();
                    return false;
                } );
            } )
        }

    } )( jQuery )
</script>