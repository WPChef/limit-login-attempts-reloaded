<?php
/**
 * Logo Block Component
 *
 * @var bool $is_active_app_custom
 */

if ( ! defined( 'ABSPATH' ) ) exit();
?>

<div class="limit-login-page-settings__logo_block">
    <img class="limit-login-page-settings__logo" src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/logo-llap.png">
    <?php if ( $is_active_app_custom || $free_requests_exhausted) : 
        $app_config = get_option( 'limit_login_app_config' );
        ?>
        <div class="link__style_unlink">
            <a href="https://my.limitloginattempts.com/" target="_blank">
                &nbsp;&nbsp;&nbsp;<?php esc_html_e( 'Account Login', 'limit-login-attempts-reloaded' ); ?>
                <div class="info-box-icon">
                    <img src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/icon-backup-big-bw.png">
                </div>
            </a>
            <?php
            if ( is_array( $app_config ) && ! empty( $app_config['key'] ) ) {
                $customer_id = substr( $app_config['key'], 0, 8 );
                ?>
                <span class="llar-customer-id">
                    <?php esc_html_e( 'Customer ID:', 'limit-login-attempts-reloaded' ); ?>
                    <?php echo esc_html( $customer_id ); ?>
                </span>
                <?php
            }
            ?>
        </div>
    <?php endif; ?>
</div>


