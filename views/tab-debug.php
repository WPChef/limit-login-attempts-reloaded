<?php

use LLAR\Core\Helpers;
use LLAR\Core\Config;
use LLAR\Core\LimitLoginAttempts;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

$active_app = Config::get( Config::OPTION_ACTIVE_APP );
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

		<div class="llar-settings-wrap llar-compat-wrap">
			<h3 class="title_page"><?php echo esc_html__( 'Compatibility', 'limit-login-attempts-reloaded' ); ?></h3>
			<p class="description-secondary"><?php echo esc_html__( 'Compatibility of LLAR features with third-party plugins.', 'limit-login-attempts-reloaded' ); ?></p>

			<div class="llar-compat-card">
				<table class="llar-compat-table">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Plugin', 'limit-login-attempts-reloaded' ); ?></th>
							<th><?php echo esc_html__( 'Login protection', 'limit-login-attempts-reloaded' ); ?></th>
							<th><?php echo esc_html__( 'Registration protection', 'limit-login-attempts-reloaded' ); ?></th>
							<th><?php echo esc_html__( 'Password recovery protection', 'limit-login-attempts-reloaded' ); ?></th>
							<th><?php echo esc_html__( '2FA', 'limit-login-attempts-reloaded' ); ?></th>
							<th><?php echo esc_html__( 'Custom GDPR message', 'limit-login-attempts-reloaded' ); ?></th>
							<th><?php echo esc_html__( 'Custom error message', 'limit-login-attempts-reloaded' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php $compat_data = Helpers::get_compatibility_data(); ?>
					<?php foreach ( $compat_data as $compat_plugin => $compat_features ) : ?>
						<tr>
							<th scope="row" class="llar-compat-plugin"><?php echo esc_html( $compat_plugin ); ?></th>
							<?php
							$compat_columns = array( 'login', 'register', 'password', '2fa', 'gdpr', 'error' );
							foreach ( $compat_columns as $compat_column ) :
								$compat_value = isset( $compat_features[ $compat_column ] ) ? $compat_features[ $compat_column ] : '';
								?>
								<td class="llar-compat-cell">
									<?php if ( 'both' === $compat_value ) : ?>
										<span class="llar-compat-icon llar-compat-icon--both" title="<?php esc_attr_e( 'Compatible', 'limit-login-attempts-reloaded' ); ?>"></span>
									<?php elseif ( 'cloud' === $compat_value ) : ?>
										<span class="llar-compat-icon llar-compat-icon--cloud" title="<?php esc_attr_e( 'Compatible in Cloud mode only', 'limit-login-attempts-reloaded' ); ?>"></span>
									<?php endif; ?>
								</td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<p class="description-secondary llar-compat-legend">
				<span class="llar-compat-icon llar-compat-icon--both" aria-hidden="true"></span><?php esc_html_e( 'Compatible', 'limit-login-attempts-reloaded' ); ?>
				<span class="llar-compat-icon llar-compat-icon--cloud" aria-hidden="true"></span><?php esc_html_e( 'Compatible in Cloud mode only', 'limit-login-attempts-reloaded' ); ?>
			</p>
		</div>
	</div>

