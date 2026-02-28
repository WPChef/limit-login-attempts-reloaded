<?php

use LLAR\Core\Helpers;
use LLAR\Core\Config;
use LLAR\Core\LimitLoginAttempts;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

$active_app = Config::get( 'active_app' );
$active_app = ( $active_app === 'custom' && LimitLoginAttempts::$cloud_app ) ? 'custom' : 'local';
$setup_code = Config::get( 'app_setup_code' );



$debug_info = Helpers::get_debug_info();
$plugin_data = get_plugin_data( LLA_PLUGIN_FILE );
?>


<div id="llar-setting-page-debug">
    <div class="llar-settings-wrap">
        <table class="llar-form-table">
            <tr>
                <th scope="row" valign="top"><?php echo esc_html__( 'Debug Info', 'limit-login-attempts-reloaded' ); ?></th>
                <td>
                    <div class="textarea_border">
                        <textarea cols="70" rows="10" onclick="this.select();"
								  readonly><?php echo esc_textarea( $debug_info ); ?></textarea>
                    </div>
                    <div class="description-secondary">
						<?php esc_html_e( 'When submitting a support ticket, please include the contents of the window shown above.', 'limit-login-attempts-reloaded' ); ?>
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row" valign="top"><?php echo esc_html__( 'Version', 'limit-login-attempts-reloaded' ); ?></th>
                <td>
                    <div><?php echo esc_html( $plugin_data['Version'] ); ?></div>
                </td>
            </tr>
			<?php /* LLAR_DEBUG_MFA_WP_MAIL_START */ ?>
            <tr>
                <th scope="row" valign="top"><?php echo esc_html__( 'MFA wp_mail log', 'limit-login-attempts-reloaded' ); ?></th>
                <td>
                    <?php
                    $mfa_log_path = defined( 'LLA_PLUGIN_DIR' ) ? LLA_PLUGIN_DIR . 'logs/mfa-wp-mail.log' : '';
                    $mfa_log_url  = defined( 'LLA_PLUGIN_FILE' ) ? plugin_dir_url( LLA_PLUGIN_FILE ) . 'logs/mfa-wp-mail.log' : '';
                    ?>
                    <a href="<?php echo esc_url( $mfa_log_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Open log file', 'limit-login-attempts-reloaded' ); ?></a>
                    <?php if ( $mfa_log_path ) : ?>
                        <span class="description-secondary"> (<?php echo esc_html( $mfa_log_path ); ?>)</span>
                    <?php endif; ?>
                </td>
            </tr>
			<?php /* LLAR_DEBUG_MFA_WP_MAIL_END */ ?>
			<?php if ( $active_app === 'local' && empty( $setup_code ) ) : ?>
                <tr>
                    <th scope="row" valign="top"><?php echo esc_html__( 'Start Over', 'limit-login-attempts-reloaded' ); ?>
                        <span class="hint_tooltip-parent">
                            <span class="dashicons dashicons-editor-help"></span>
                            <div class="hint_tooltip">
                                <div class="hint_tooltip-content">
                                    <?php esc_attr_e( 'You can start over the onboarding process by clicking this button. All existing data will remain unchanged.', 'limit-login-attempts-reloaded' ); ?>
                                </div>
                            </div>
                        </span>
                    </th>
                    <td>
                        <div class="button_block-single">
                            <button class="button menu__item button__transparent_orange" id="llar_onboarding_reset">
                                <?php esc_html_e( 'Reset', 'limit-login-attempts-reloaded' ); ?>
                            </button>
                        </div>
                    </td>
                </tr>
			<?php endif; ?>
        </table>
    </div>
</div>

