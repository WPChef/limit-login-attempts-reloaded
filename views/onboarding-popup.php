<?php

if( !defined( 'ABSPATH' ) ) exit();

/**
 * @var $this Limit_Login_Attempts
 */

$admin_email = ( !is_multisite() ) ? get_option( 'admin_email' ) : get_site_option( 'admin_email' );
$onboarding_popup_shown = $this->get_option( 'onboarding_popup_shown' );

if( $onboarding_popup_shown ) return;

ob_start(); ?>
<div class="llar-onboarding-popup-content">
	<div class="title">Please tell us where Limit Login Attempts Reloaded should send security notifications for your website?</div>
	<div class="field-wrap">
		<input type="email" id="llar-subscribe-email" placeholder="you@example.com" value="<?php esc_attr_e( $admin_email ); ?>">
		<div class="field-desc">We do not use this email address for any other purpose unless you opt-in to receive other mailings. You can turn off alerts in the settings.</div>
	</div>
	<div class="security-alerts-options">
		<div class="info">Would you also like to join our WordPress security mailing list to receive WordPress security alerts and Limit Login Attempts Reloaded news?</div>
		<div class="buttons">
			<span data-val="yes">Yes</span>
			<span data-val="no">No</span>
		</div>
	</div>
</div>
<?php
$popup_complete_install_content = ob_get_clean();
?>
<?php
ob_start(); ?>
<div class="llar-onboarding-popup-content llar-app-setup-popup">
    <div class="title">Activate Premium</div>
    <div class="desc">Enter your setup key to enable cloud protection, enhanced logs, intelligent IP management and 25+ features.</div>
    <div class="field-wrap">
        <div class="field">
            <input type="text" id="llar-setup-code-field" placeholder="Enter Setup Code">
            <span class="error"></span>
        </div>
        <div class="button-col">
            <button class="button button-primary" id="llar-app-install-btn">
                Install
                <span class="preloader-wrapper"><span class="spinner llar-app-ajax-spinner"></span></span>
            </button>
        </div>
    </div>
    <div class="divider-line"><span>Or</span></div>
    <div class="bottom-buttons">
        <div class="text">If you don't have one, you can purchase one now.</div>
        <div class="buttons">
            <a href="https://www.limitloginattempts.com/pricing/?from=plugin-welcome" target="_blank"
               class="button button-primary size-medium">Upgrade To Premium <span>Starting from $8/month</span></a>
            <a href="https://www.limitloginattempts.com/features/?from=plugin-welcome" target="_blank"
               class="button button-secondary">Learn More</a>
            <button class="button-link" id="llar-popup-no-thanks-btn">No thanks</button>
        </div>
    </div>
</div>
<?php
$popup_app_setup_content = ob_get_clean();
?>
?>
<script>
    (function($){

        $(document).ready(function(){
            const app_setup_popup = $.confirm({
                title: '<?php _e( 'Please Complete Limit Login Attempts Reloaded Installation', 'limit-login-attempts-reloaded' ) ?>',
                content: `<?php echo trim( $popup_app_setup_content ); ?>`,
                type: 'default',
                typeAnimated: true,
                draggable: false,
                boxWidth: '40%',
                useBootstrap: false,
                lazyOpen: true,
                buttons: false,
                closeIcon: false
            });

            $.confirm({
                title: '<?php _e( 'Complete Limit Login Attempts Reloaded Installation', 'limit-login-attempts-reloaded' ) ?>',
                content: `<?php echo trim( $popup_complete_install_content ); ?>`,
                type: 'default',
                typeAnimated: true,
                draggable: false,
                boxWidth: '40%',
                useBootstrap: false,
				buttons: {
                    continue: {
                        text: 'Continue',
                        btnClass: 'btn-blue',
                        keys: ['enter'],
                        action: function(){

                            $.post(ajaxurl, {
                                action: 'subscribe_email',
                                email: $('body').find('#llar-subscribe-email').val(),
                                is_subscribe_yes: !!$('body').find('.security-alerts-options .buttons span[data-val="yes"].llar-act').length,
                                sec: '<?php echo esc_js( wp_create_nonce( "llar-action" ) ); ?>'
                            }, function(){

                                app_setup_popup.open();
                            });
                        }
                    }
				}
            });


            $('body').on('click', '.security-alerts-options .buttons span', function() {
                const $this = $(this);
                $this.parent().find('span').removeClass('llar-act');
                $this.addClass('llar-act');
            });

            $('body').on('click', '#llar-app-install-btn', function(e) {
                e.preventDefault();

                const $this = $(this);
                const $error = $this.closest('.field-wrap').find('.error');

                if($this.hasClass('button-disabled')) {
                    return;
                }

                const setup_code = $this.closest('.field-wrap').find('input').val();

                $error.text('').hide();
                $this.addClass('button-disabled');

                $.post(ajaxurl, {
                    action: 'app_setup',
                    code: setup_code,
                    sec: '<?php echo esc_js( wp_create_nonce( "llar-action" ) ); ?>'
                }, function(response){

                    if(response.success) {
                        setTimeout(function(){

                            window.location = window.location + '&activated';

                        }, 500);
                    }

                    if(!response.success && response.data.msg) {

                        $error.text(response.data.msg).show();
                    }

                    $this.removeClass('button-disabled');

                });

            });

            $('body').on('click', '#llar-popup-no-thanks-btn', function(e) {
                e.preventDefault();

                app_setup_popup.close();
            });
        })

    })(jQuery)
</script>