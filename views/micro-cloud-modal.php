<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Free trial activation modal (return from marketing landing).
 *
 * Outbound CTAs link to info.php; this modal opens only when a valid
 * setup_code + return token are present, shows loadinfo, then success.
 *
 * @var $this LLAR\Core\AdminUiController
 * @var array|null $modal Optional pre-built view vars from the parent template.
 */

if ( empty( $modal ) || ! is_array( $modal ) ) {
	$tab   = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$modal = $this->get_micro_cloud_modal_view_vars( $tab );
}

if ( empty( $modal['should_show'] ) ) {
	return;
}

$spinner = '<span class="preloader-wrapper"><span class="spinner llar-app-ajax-spinner"></span></span>';
$auto    = ! empty( $modal['auto_activate']['enabled'] ) && ! empty( $modal['auto_activate']['setup_code'] );

ob_start();
?>
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
                    <img src="<?php echo LLA_PLUGIN_URL; ?>assets/css/images/micro-cloud-image-min.png">
                </div>
            </div>
            <div class="card mx-auto">
                <div class="card-header">
                    <div class="title">
                        <img src="<?php echo LLA_PLUGIN_URL; ?>assets/css/images/tools.png">
                        <?php echo $modal['card_title']; ?>
                    </div>
                </div>
                <div class="card-body step-loading<?php echo $auto ? '' : ' llar-display-none'; ?>">
                    <div class="llar-upgrade-subscribe_notification">
                        <div class="field-image">
                            <?php echo $spinner; ?>
                        </div>
                        <div class="description_add">
                            <?php echo esc_html( $modal['activating_text'] ); ?>
                        </div>
                    </div>
                </div>
                <div class="card-body step-second llar-display-none">
                    <div class="llar-upgrade-subscribe_notification__error llar-display-none">
                        <img src="<?php echo LLA_PLUGIN_URL; ?>assets/css/images/start.png">
                        <span class="llar-micro-cloud-error-message"><?php echo $modal['error_message']; ?></span>
                    </div>
                    <div class="llar-upgrade-subscribe_notification">
                        <div class="field-image">
                            <img src="<?php echo LLA_PLUGIN_URL; ?>assets/css/images/schema-ok-min.png">
                        </div>
                        <div class="description_add">
                            <img src="<?php echo LLA_PLUGIN_URL; ?>assets/css/images/start.png">
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
            const autoActivate = <?php echo $auto ? 'true' : 'false'; ?>;
            const autoSetupCode = <?php echo wp_json_encode( $auto ? $modal['auto_activate']['setup_code'] : '' ); ?>;

            const redirectToDashboard = function () {
                let clear_url = window.location.protocol + "//" + window.location.host + window.location.pathname;
                window.location = clear_url + '?page=limit-login-attempts&tab=dashboard';
            };

            const scrubReturnParams = function () {
                try {
                    const url = new URL( window.location.href );
                    url.searchParams.delete( 'setup_code' );
                    url.searchParams.delete( 'llar_trial_token' );
                    url.searchParams.delete( 'token' );
                    window.history.replaceState( {}, document.title, url.toString() );
                } catch ( e ) {}
            };

            let microCloudActivationInProgress = false;
            let microCloudActivationCompleted = false;

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
                    return ! microCloudActivationInProgress;
                },
                backgroundDismiss: function() {
                    if ( microCloudActivationCompleted ) {
                        redirectToDashboard();
                        return false;
                    }
                    return ! microCloudActivationInProgress;
                },
                escapeKey: function() {
                    if ( microCloudActivationCompleted ) {
                        redirectToDashboard();
                        return false;
                    }
                    return ! microCloudActivationInProgress;
                },
                buttons: {},
                onOpenBefore: function () {
                    const $card_body_loading = $( '.card-body.step-loading' );
                    const $card_body_second = $( '.card-body.step-second' );
                    const $button_dashboard = $( '#llar-button_dashboard' );
                    const $subscribe_notification = $( '.llar-upgrade-subscribe_notification' ).not( '.llar-upgrade-subscribe_notification__error' );
                    const $subscribe_notification_error = $( '.llar-upgrade-subscribe_notification__error' );
                    const $spinner_dashboard = $button_dashboard.find( '.preloader-wrapper .spinner' );
                    const disabled = 'llar-disabled';
                    const visibility = 'llar-visibility';

                    const showResult = function ( ok, message ) {
                        $card_body_loading.addClass( 'llar-display-none' );
                        $card_body_second.removeClass( 'llar-display-none' );
                        if ( ok ) {
                            $subscribe_notification_error.addClass( 'llar-display-none' );
                            $subscribe_notification.removeClass( 'llar-display-none' );
                            microCloudActivationCompleted = true;
                        } else {
                            $subscribe_notification.addClass( 'llar-display-none' );
                            $subscribe_notification_error.find( '.llar-micro-cloud-error-message' ).text( message || '' );
                            $subscribe_notification_error.removeClass( 'llar-display-none' );
                            microCloudActivationCompleted = false;
                        }
                        $( '.jconfirm-closeIcon' ).remove();
                        $button_dashboard.off( 'click.llarDashboardRedirect' ).on( 'click.llarDashboardRedirect', function () {
                            $button_dashboard.addClass( disabled );
                            $spinner_dashboard.addClass( visibility );
                            redirectToDashboard();
                        } );
                    };

                    if ( ! autoActivate || ! autoSetupCode ) {
                        return;
                    }

                    microCloudActivationInProgress = true;
                    $body.addClass( disabled );
                    $card_body_loading.removeClass( 'llar-display-none' );
                    $card_body_second.addClass( 'llar-display-none' );

                    llar_activate_license_key( autoSetupCode )
                        .then( function() {
                            showResult( true );
                        } )
                        .catch( function( response ) {
                            showResult( false, llar_micro_cloud_error_message( response ) );
                        } )
                        .finally( function() {
                            $body.removeClass( disabled );
                            microCloudActivationInProgress = false;
                            scrubReturnParams();
                        } );
                },
                onClose: function() {
                    if ( microCloudActivationInProgress ) {
                        return false;
                    }
                    if ( microCloudActivationCompleted ) {
                        redirectToDashboard();
                        return false;
                    }
                    if ( window.location.hash === '#modal_micro_cloud' ) {
                        history.pushState( '', document.title, window.location.pathname + window.location.search );
                    }
                },
            } );

            if ( autoActivate && autoSetupCode ) {
                micro_cloud_modal.open();
            }

        } );

    } )( jQuery )
</script>
