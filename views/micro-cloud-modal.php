<?php

if( !defined( 'ABSPATH' ) ) exit();

/**
 * Micro Cloud free trial modal.
 *
 * All copy and state comes from the controller via get_micro_cloud_modal_view_vars().
 *
 * @var $this LLAR\Core\AdminUiController
 */

$modal = $this->get_micro_cloud_modal_view_vars();

if ( ! $modal['should_show'] ) {
	return;
}

$spinner = '<span class="preloader-wrapper"><span class="spinner llar-app-ajax-spinner"></span></span>';

ob_start(); ?>
    <div class="micro_cloud_modal__content">
        <div class="micro_cloud_modal__body">
            <div class="micro_cloud_modal__body_header">
                <div class="left_side">
                    <div class="title">
                        <?php echo $modal['title']; ?>
                    </div>
                    <div class="description">
                        <?php echo $modal['description']; ?>
                    </div>
                    <div class="description-add">
                        <?php echo $modal['description_add']; ?>
                    </div>
                </div>
                <div class="right_side">
                    <img src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/micro-cloud-image-min.png">
                </div>
            </div>
            <div class="card mx-auto">
                <div class="card-header">
                    <div class="title">
                        <img src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/tools.png">
                        <?php echo $modal['card_title']; ?>
                    </div>
                </div>
                <div class="card-body step-first">
                    <div class="description">
                        <?php echo $modal['email_desc']; ?>
                    </div>
                    <div class="field-wrap">
                        <div class="field-email">
                            <input type="text" class="input_border" id="llar-subscribe-email"
                                   placeholder="<?php echo $modal['email_placeholder']; ?>"
                                   value="<?php echo esc_attr( $modal['admin_email'] ); ?>">
                        </div>
                    </div>
                    <div class="field-checkbox">
                        <input type="checkbox" id="mc_consent_registering"/>
                        <span>
                            <?php echo $modal['consent']; ?>
                        </span>
                    </div>
                    <div class="button_block-single">
                        <button class="button menu__item button__orange" id="llar-button_subscribe-email">
                            <?php echo $modal['continue_label']; echo $spinner; ?>
                        </button>
                        <div class="description_add">
                            <?php echo $modal['terms']; ?>
                        </div>
                    </div>
                </div>
                <div class="card-body step-second llar-display-none">
                    <div class="llar-upgrade-subscribe_notification__error llar-display-none">
                        <img src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/start.png">
                        <span class="llar-micro-cloud-error-message"><?php echo $modal['error_message']; ?></span>
                    </div>
                    <div class="llar-upgrade-subscribe_notification">
                        <div class="field-image">
                            <img src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/schema-ok-min.png">
                        </div>
                        <div class="description_add">
                            <img src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/start.png">
	                        <?php echo $modal['success_text']; ?>
                        </div>
                    </div>
                    <div class="button_block-single">
                        <button class="button next_step menu__item button__orange" id="llar-button_dashboard">
                            <?php echo $modal['dashboard_label']; echo $spinner; ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php
$micro_cloud_popup_content = ob_get_clean();
?>

<script>
    ;( function( $ ) {

        $( document ).ready( function() {

            const $body = $( 'body' );

            const redirectToDashboard = function () {
                let clear_url = window.location.protocol + "//" + window.location.host + window.location.pathname;
                window.location = clear_url + '?page=limit-login-attempts&tab=dashboard';
            };

            const $button_micro_cloud = $( '.button.button_micro_cloud, a.button_micro_cloud' );

            let microCloudActivationInProgress = false;
            let microCloudActivationCompleted = false;

            $button_micro_cloud.on( 'click', function () {
                micro_cloud_modal.open();
            } )

            const micro_cloud_modal = $.dialog( {
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
                closeIcon: function() {
                    if ( microCloudActivationCompleted ) {
                        redirectToDashboard();
                        return false;
                    }
                    return true;
                },
                backgroundDismiss: function() {
                    if ( microCloudActivationCompleted ) {
                        redirectToDashboard();
                        return false;
                    }
                    return true;
                },
                escapeKey: function() {
                    if ( microCloudActivationCompleted ) {
                        redirectToDashboard();
                        return false;
                    }
                    return true;
                },
                buttons: {},
                onOpenBefore: function () {

                    const $subscribe_email = $( '#llar-subscribe-email' );
                    const $button_subscribe_email = $( '#llar-button_subscribe-email' );
                    const $card_body_first = $( '.card-body.step-first' );
                    const $card_body_second = $( '.card-body.step-second' );
                    const $button_dashboard = $( '#llar-button_dashboard' );
                    const $consent_registering = $( '#mc_consent_registering' );
                    const $subscribe_notification = $( '.llar-upgrade-subscribe_notification' );
                    const $subscribe_notification_error = $( '.llar-upgrade-subscribe_notification__error' );
                    const $spinner = $button_subscribe_email.find( '.preloader-wrapper .spinner' );
                    const $spinner_dashboard = $button_dashboard.find( '.preloader-wrapper .spinner' );
                    const disabled = 'llar-disabled';
                    const visibility = 'llar-visibility';

                    let email = $subscribe_email.val().trim();

                    $button_subscribe_email.addClass( disabled );

                    $subscribe_email.on( 'input', function () {
                        $consent_registering.prop( 'checked', false );
                        $consent_registering.trigger( 'change' );
                    } );

                    $subscribe_email.on( 'blur', function() {

                        email = $( this ).val().trim();

                        if ( email === '' || email === null || ! llar_is_valid_email( email ) ) {
                            $consent_registering.prop( 'disabled', true );
                        } else {
                            $consent_registering.prop( 'disabled', false );
                        }
                    } );

                    $consent_registering.on( 'change', function () {

                        const is_checked = $( this ).prop( 'checked' );

                        if( is_checked ) {
                            $button_subscribe_email.removeClass( disabled );
                        } else {
                            $button_subscribe_email.addClass( disabled );
                        }
                    } );

                    $button_subscribe_email.on( 'click', function ( e ) {
                        e.preventDefault();

                        if ( $button_subscribe_email.hasClass( disabled ) ) {
                            return;
                        }

                        $button_subscribe_email.addClass( disabled );
                        $spinner.addClass( visibility );
                        $body.addClass( disabled );
                        microCloudActivationInProgress = true;
                        llar_activate_micro_cloud( email )
                            .then( function() {

                                microCloudActivationCompleted = true;
                                $button_subscribe_email.removeClass( disabled );
                            } )
                            .catch( function( response ) {

                                microCloudActivationCompleted = false;
                                const errorMessage = llar_micro_cloud_error_message( response );
                                $subscribe_notification_error.find( '.llar-micro-cloud-error-message' ).text( errorMessage );
                                $subscribe_notification_error.removeClass( 'llar-display-none' );
                                $subscribe_notification.addClass( 'llar-display-none' );
                            } )
                            .finally( function() {
                                $card_body_first.addClass( 'llar-display-none' );
                                $card_body_second.removeClass( 'llar-display-none' );
                                $body.removeClass( disabled );
                                microCloudActivationInProgress = false;
                                $( '.jconfirm-closeIcon' ).remove();
                                $button_dashboard.off( 'click.llarDashboardRedirect' ).on( 'click.llarDashboardRedirect', function () {
                                    $button_dashboard.addClass( disabled );
                                    $spinner_dashboard.addClass( visibility );
                                    redirectToDashboard();
                                } );
                            } );
                    } )
                },
                onClose: function() {
                    if ( microCloudActivationInProgress ) {
                        return false; // Prevent closing during activation
                    }
                    // Redirect to dashboard after successful activation
                    if ( microCloudActivationCompleted ) {
                        redirectToDashboard();
                        return false;
                    }
                    // Remove hash from URL
                    if (window.location.hash === '#modal_micro_cloud') {
                        history.pushState('', document.title, window.location.pathname + window.location.search);
                    }
                },
            } );


            micro_cloude_hash( window.location.hash );

            $( window ).on( 'hashchange', function() {
                micro_cloude_hash( window.location.hash );
            } );

            function micro_cloude_hash( current_hash ) {

                const target_hash = '#modal_micro_cloud';

                if ( current_hash && current_hash === target_hash ) {
                    $button_micro_cloud.click();
                }
            }

        } )

    } )( jQuery )
</script>