function llar_activate_license_key( $setup_code, extra ) {

    let data = {
        action: 'app_setup',
        code:   $setup_code,
        sec: llar_vars.nonce_app_setup,
    }

    if ( extra && typeof extra === 'object' ) {
        Object.assign( data, extra );
    }

    // The return token rides along so the server can burn it after a
    // successful activation; a failed attempt leaves the link reusable.
    try {
        const trialToken = new URL( window.location.href ).searchParams.get( 'llar_trial_token' );
        if ( trialToken ) {
            data.llar_trial_token = trialToken;
        }
    } catch ( e ) {}

    return llar_ajax_callback_post_wp( ajaxurl, data )
}


function llar_is_valid_email( email ) {

    if ( email === null || typeof email === 'undefined' ) {
        return false;
    }

    email = String( email ).trim();
    if ( email === '' ) {
        return false;
    }

    let email_regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return email_regex.test( email );
}

function llar_micro_cloud_error_message( response ) {

    const fallback = 'The server is not working, try again later';

    if ( ! response || ! response.data ) {
        return fallback;
    }

    if ( typeof response.data === 'string' && response.data.trim() !== '' ) {
        return response.data;
    }

    if ( response.data.message ) {
        return response.data.message;
    }

    if ( response.data.msg ) {
        if ( typeof response.data.msg === 'string' ) {
            return response.data.msg;
        }
        if ( response.data.msg.error ) {
            return response.data.msg.error;
        }
    }

    return fallback;
}


function llar_ajax_network_error_message() {
    return ( typeof llar_vars !== 'undefined' && llar_vars.msg_network_error )
        ? llar_vars.msg_network_error
        : 'Network error';
}

function llar_ajax_callback_post( ajaxurl = null, data ) {

    return new Promise(function( resolve, reject ) {
        jQuery.post( ajaxurl, data, function ( response ) {

            // Malformed payloads (0, null, plain strings) must not resolve
            // as success nor throw inside this callback.
            if ( response === null || typeof response !== 'object' ) {
                reject( {
                    success: false,
                    data: { msg: llar_ajax_network_error_message() }
                } );
                return;
            }

            if ( response.success === false ) {
                reject( response );
            } else if ( response.error ) {
                reject( response );
            }
            else  {
                resolve( response );
            }
        } ).fail( function ( jqXHR ) {
            // Keep the same shape as application rejects so callers can read response.data.msg.
            var payload = ( jqXHR && jqXHR.responseJSON ) ? jqXHR.responseJSON : null;
            if ( payload && typeof payload === 'object' ) {
                reject( payload );
                return;
            }
            // Prefer the localized fallback over raw jqXHR textStatus ("error").
            reject( {
                success: false,
                data: {
                    msg: llar_ajax_network_error_message()
                }
            } );
        } );
    });
}

/**
 * Strict WordPress admin-ajax helper: only { success: true } resolves.
 * Use for endpoints that always return wp_send_json_success/error.
 */
function llar_ajax_callback_post_wp( ajaxurl, data ) {
    return llar_ajax_callback_post( ajaxurl, data ).then( function ( response ) {
        if (
            ! response ||
            typeof response !== 'object' ||
            Array.isArray( response ) ||
            response.success !== true
        ) {
            if (
                response &&
                typeof response === 'object' &&
                ! Array.isArray( response ) &&
                response.success === false
            ) {
                return Promise.reject( response );
            }
            return Promise.reject( {
                success: false,
                data: { msg: llar_ajax_network_error_message() }
            } );
        }
        return response;
    } );
}

( function( $ ) {

    $( document ).ready(function() {

        const poster = '#video-poster';

        $( document ).on( 'click', poster, function () {

            $( poster ).css( 'display', 'none' );
        } )

        const $account_policies = $( 'input[name="strong_account_policies"]' );
        const $checkbox_block_by_country = $( 'input[name="block_by_country"]' );
        const $checkbox_auto_update_choice = $( 'input[name="auto_update_choice"]' );
        const $auto_update_choice = $( 'a[href="#llar_auto_update_choice"]' );
        const $auto_update_notice = $( '.llar-auto-update-notice' );
        const content_html = $( '#llar_popup_error_content' ).html();
        const $upgrade_premium_message = $( '#llar-header-upgrade-premium-message' );

        $account_policies.on( 'change', function () {

            $is_checklist = !! $( this ).prop( 'checked' );

            let data = {
                action: 'strong_account_policies',
                is_checklist: $is_checklist,
                sec: llar_vars.nonce_account_policies
            }

            llar_ajax_callback_post( ajaxurl, data )
                .catch( function () {
                    $account_policies.prop( 'checked', false );
                } )

        } )

        $checkbox_block_by_country.on( 'change', function () {

            const $checkbox = $( this );
            const isChecked = !! $checkbox.prop( 'checked' );

            let data = {
                action: 'block_by_country',
                is_checklist: isChecked,
                sec: llar_vars.nonce_block_by_country
            }

            llar_ajax_callback_post( ajaxurl, data )
                .catch( function () {
                    // Restore this checkbox from this request's value only.
                    $checkbox.prop( 'checked', ! isChecked );
                } )

        } )

        $upgrade_premium_message.on( 'click', '.close', function () {

            $upgrade_premium_message.addClass( 'llar-display-none' );

            let data = {
                action: 'close_premium_message',
                sec: llar_vars.nonce_close_premium_message
            }

            llar_ajax_callback_post( ajaxurl, data )
                .catch( function () {
                    $upgrade_premium_message.addClass( 'llar-display-none' );
                } )
        } )


        $auto_update_choice.on( 'click', function ( e ) {
            e.preventDefault();

            let checked = 'no';

            if ( ! $checkbox_auto_update_choice.is( 'checked' ) ) {
                checked = 'yes';
            }

            toggle_auto_update( checked, content_html );
        } )


        $auto_update_notice.on( 'click', ' .auto-enable-update-option', function( e ) {
            e.preventDefault();

            let value = $( this ).data( 'val' );

            toggle_auto_update( value, content_html ) ;

        })


        function toggle_auto_update( value, content ) {

            let data = {
               action: 'toggle_auto_update',
               value: value,
               sec: llar_vars.nonce_auto_update
            }

            llar_ajax_callback_post( ajaxurl, data )
               .then( function () {
                   hide_auto_update_option( value );

               } )
               .catch( function ( response ) {
                   notice_popup_error_update.content = content;
                   notice_popup_error_update.msg = llar_micro_cloud_error_message( response );
                   notice_popup_error_update.open();
               } )

        }


        function hide_auto_update_option( value ) {

            if ( $auto_update_notice.length > 0 && $auto_update_notice.css( 'display' ) !== 'none' ) {

                $auto_update_notice.remove();
            }

            if ( value === 'yes' && ! $checkbox_auto_update_choice.is('checked') ) {

                let link_text = $auto_update_choice.text();
                $checkbox_auto_update_choice.prop( 'checked', true );
                $auto_update_choice.replaceWith( link_text );
            }
        }

        const notice_popup_error_update = $.dialog({
            title: false,
            content: this.content,
            lazyOpen: true,
            type: 'default',
            typeAnimated: true,
            draggable: false,
            animation: 'top',
            animationBounce: 1,
            boxWidth: '20%',
            bgOpacity: 0.9,
            useBootstrap: false,
            closeIcon: true,
            buttons: {},
            onOpenBefore: function () {
                const $card_body = $( '.card-body' );
                $card_body.text( this.msg );
            }
        } );


        const $onboarding_reset = $( '#llar_onboarding_reset' );

        $onboarding_reset.on( 'click', function ( e ) {
            e.preventDefault();

            let data = {
                action: 'onboarding_reset',
                sec: llar_vars.nonce_onboarding_reset
            }

            llar_ajax_callback_post( ajaxurl, data )
                .then( function () {
                    let clear_url = window.location.protocol + "//" + window.location.host + window.location.pathname;
                    window.location = clear_url + '?page=limit-login-attempts&tab=dashboard';
                } )
                .catch( function () {
                    // Keep current page; reset is best-effort.
                } )

        } )

    } );

} )(jQuery)
