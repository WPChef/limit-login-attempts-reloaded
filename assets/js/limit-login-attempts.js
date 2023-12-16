function activate_micro_cloud(email) {
    let url_api = 'https://api.limitloginattempts.com/checkout-staging/network';
    // let url_api = ''https://api.limitloginattempts.com/checkout/network'';

    let form_data = [];
    form_data.push({name: 'group', value: 'free'});
    form_data.push({name: 'email', value: email});

    let form_object = form_data.reduce(function(object, item) {
        object[item.name] = item.value;
        return object;
    }, {});

    let data = {
        url: url_api,
        contentType: 'application/json',
        dataType: 'json',
        data: JSON.stringify(form_object),
    }

    return ajax_callback_post(data)
}


function activate_license_key( ajaxurl, $setup_code, sec ) {

    let data = {
        action: 'app_setup',
        code:   $setup_code,
        sec:    sec,
    }

    return ajax_callback_post( ajaxurl, data )
}


function is_valid_email( email ) {

    let email_regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return email_regex.test( email );
}

function ajax_callback_post( ajaxurl = null, data ) {

    return new Promise(function( resolve, reject ) {
        jQuery.post( ajaxurl, data, function ( response ) {

            if ( ( response && ( 'success' in response ) && response.success === false ) ) {
                reject( response );
            } else if ( response.error ) {
                reject( response );
            }
            else  {
                resolve( response );
            }
        });
    });
}

( function( $ ) {

    $( document ).ready(function() {

        const poster = '#video-poster';

        $( document ).on( 'click', poster, function () {

            $( poster ).css( 'display', 'none' );
        } )

        const $account_policies = $( 'input[name="strong_account_policies"]' );
        const $checkbox_auto_update_choice = $( 'input[name="auto_update_choice"]' );
        const $auto_update_choice = $( 'a[href="llar_auto_update_choice"]' );
        const $auto_update_notice = $( '.llar-auto-update-notice' );
        const content_html = $( '#llar_popup_error_content' ).html();


        $account_policies.on( 'change', function () {

            $is_checklist = !! $( this ).prop( 'checked' );

            let data = {
                action: 'strong_account_policies',
                is_checklist: $is_checklist,
                sec: llar_vars.account_policies
            }

            ajax_callback_post( ajaxurl, data )
                .catch( function () {
                    $account_policies.prop( 'checked', false );
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
               sec: llar_vars.auto_update
            }

            ajax_callback_post( ajaxurl, data )
               .then( function () {
                   hide_auto_update_option();

               } )
               .catch( function ( response ) {
                   notice_popup_error_update.content = content;
                   notice_popup_error_update.msg = response.data.msg;
                   notice_popup_error_update.open();
               } )

        }


        function hide_auto_update_option() {

            if ( $auto_update_notice.length > 0 && $auto_update_notice.css( 'display' ) !== 'none' ) {

                $auto_update_notice.remove();
            }

            if ( ! $checkbox_auto_update_choice.is('checked') ) {

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
                sec: llar_vars.onboarding_reset
            }

            ajax_callback_post( ajaxurl, data )
                .then( function () {
                    window.location = window.location + '&tab=dashboard';
                } )

        } )

    } );

} )(jQuery)
