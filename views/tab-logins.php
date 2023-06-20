<?php

use LLAR\Core\Config;

if( !defined( 'ABSPATH' ) ) exit();

$user_email = Config::get( 'free_user_email' )?: ( !is_multisite() ? get_option( 'admin_email' ) : get_site_option( 'admin_email' ) );

$key = Config::get( 'cloud_key' );
$log_logins_enable = Config::get( 'log_logins_enable' );

$is_key_option_selected = !empty( $key );
?>

<div id="llar-logins-page">
    <h3><?php _e( 'Log-Ins', 'limit-login-attempts-reloaded' ); ?></h3>
    <p><?php _e( 'Lorem ipsum dolor sit amet, consectetur adipisicing elit. A assumenda consequuntur culpa doloremque dolorum error est, id illo incidunt nostrum officia perspiciatis quis quo, reiciendis repudiandae ullam velit veritatis voluptatem.', 'limit-login-attempts-reloaded' ); ?></p>

    <form action="<?php echo $this->get_options_page_uri( 'logins' ); ?>" method="post">

	    <?php wp_nonce_field( 'limit-login-attempts-options' ); ?>

        <table class="form-table">
            <tr>
                <td>
                    <div class="auth-type-option">
                        <label>
                            <input type="radio" name="logins_auth_type" value="email"
                                <?php checked( !$is_key_option_selected ); ?>>
                            <?php _e( 'Email', 'limit-login-attempts-reloaded' ); ?>
                        </label>
                        <input type="text" name="logins_auth_type_email"
                               value="<?php echo esc_attr( $user_email ); ?>"
                                <?php disabled( $is_key_option_selected ); ?>>
                        <p class="description"><?php _e( 'Lorem ipsum dolor sit amet, consectetur adipisicing elit.', 'limit-login-attempts-reloaded' ); ?></p>
                    </div>
                    <div class="auth-type-option">
                        <label>
                            <input type="radio" name="logins_auth_type" value="key"
                                <?php checked( $is_key_option_selected ); ?>>
                            <?php _e( 'Key', 'limit-login-attempts-reloaded' ); ?>
                        </label>
                        <input type="text" name="logins_auth_type_key"
                               value="<?php echo esc_attr( $key ); ?>"
                                <?php disabled( !$is_key_option_selected ); ?>>
                        <p class="description"><?php _e( 'Lorem ipsum dolor sit amet, consectetur adipisicing elit.', 'limit-login-attempts-reloaded' ); ?></p>
                    </div>
                </td>
            </tr>
            <tr>
                <td>
                    <label>
                        <input type="checkbox" name="log_logins_enable" <?php checked( $log_logins_enable ); ?>>
                        <?php _e( 'Consent and Enable', 'limit-login-attempts-reloaded' ) ?>
                    </label>
                    <p class="description"><?php _e( 'Lorem ipsum dolor sit amet, consectetur adipisicing elit.', 'limit-login-attempts-reloaded' ); ?></p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input class="button button-primary" name="llar_update_logins_page" value="<?php echo __( 'Save', 'limit-login-attempts-reloaded' ); ?>"
                   type="submit"/>
        </p>
    </form>

    <?php if( $key ) : ?>
        <h3><?php _e( 'Mobile App', 'limit-login-attempts-reloaded' ); ?></h3>
        <p><?php _e( 'Lorem ipsum dolor sit amet, consectetur adipisicing elit. A assumenda consequuntur culpa doloremque dolorum error est, id illo incidunt nostrum officia perspiciatis quis quo, reiciendis repudiandae ullam velit veritatis voluptatem.', 'limit-login-attempts-reloaded' ); ?></p>

        <div id="llar-qr-code"></div>

    <div class="mobile-app-buttons">
        <a href="#" class="app-store">
            <img src="<?php echo LLA_PLUGIN_URL; ?>assets/img/apple-app-store-btn.svg">
        </a>
        <a href="#" class="google-play">
            <img src="<?php echo LLA_PLUGIN_URL; ?>assets/img/google-play-badge.png">
        </a>
    </div>
    <?php endif; ?>
</div>

<script type="text/javascript">
    (function($){

        const $wrapper = $('#llar-logins-page');

        $(document).ready(function() {

            new QRCode(document.getElementById('llar-qr-code'), "<?php echo $key; ?>");

            $wrapper.on('change', 'input[name="logins_auth_type"]', function() {
                $wrapper.find('input[name="logins_auth_type_email"], input[name="logins_auth_type_key"]').attr('disabled', true);

                $(this).closest('.auth-type-option').find('input[type="text"]').attr('disabled', false)
            })
        });

    })(jQuery);
</script>
