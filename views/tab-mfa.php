<?php
/**
 * MFA Settings Page
 *
 * @var string $active_app
 * @var bool $is_active_app_custom
 * @var string $block_sub_group
 *
 */

use LLAR\Core\Config;
use LLAR\Core\LimitLoginAttempts;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * @var $this LLAR\Core\LimitLoginAttempts
 */

// Get MFA settings from controller
$mfa_settings = array();
if ( isset( $this->mfa_controller ) && method_exists( $this->mfa_controller, 'get_settings_for_view' ) ) {
	$mfa_settings = $this->mfa_controller->get_settings_for_view();
}

// Extract settings with defaults
$mfa_enabled    = isset( $mfa_settings['mfa_enabled'] ) ? $mfa_settings['mfa_enabled'] : false;
$mfa_roles      = isset( $mfa_settings['mfa_roles'] ) ? $mfa_settings['mfa_roles'] : array();
$all_roles      = isset( $mfa_settings['prepared_roles'] ) ? $mfa_settings['prepared_roles'] : array();
$editable_roles = isset( $mfa_settings['editable_roles'] ) ? $mfa_settings['editable_roles'] : array();

?>
<div id="llar-setting-page" class="llar-admin">
    <form action="<?php echo $this->get_options_page_uri( 'mfa' ); ?>" method="post">
        <div class="llar-settings-wrap">
            <h3 class="title_page">
                <img src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/icon-gears.png">
				<?php _e( '2FA Settings', 'limit-login-attempts-reloaded' ); ?>
            </h3>
            <div class="description-page">
				<?php _e( 'Configure multi-factor authentication settings for user roles.', 'limit-login-attempts-reloaded' ); ?>
            </div>
            <div class="llar-settings-wrap">
                <table class="llar-form-table">
                    <!-- Global MFA Control -->
                    <tr>
                        <th scope="row" valign="top">
                            <?php _e( 'Enable 2FA', 'limit-login-attempts-reloaded' ); ?>
                        </th>
                        <td>
                            <input type="checkbox" 
                                   name="mfa_enabled" 
                                   value="1" 
                                   id="mfa_enabled"
                                   <?php checked( $mfa_enabled, true ); ?>/>
                            <label for="mfa_enabled">
                                <?php _e( 'Enable multi-factor authentication for selected user roles', 'limit-login-attempts-reloaded' ); ?>
                            </label>
                        </td>
                    </tr>

                    <!-- Role-based MFA -->
                    <tr>
                        <th scope="row" valign="top">
                            <?php _e( 'User Roles', 'limit-login-attempts-reloaded' ); ?>
                        </th>
                        <td>
                            <div class="llar-mfa-roles-list">
                                <?php foreach ( $all_roles as $role_key => $role_display_name ) : 
                                    // Check if role is admin (role_display_name already sanitized, but we check role_key primarily)
                                    $is_admin_role = LimitLoginAttempts::is_admin_role( $role_key );
                                    $is_checked = in_array( $role_key, $mfa_roles );
                                ?>
                                    <div class="llar-mfa-role-item">
                                        <label>
                                            <input type="checkbox" 
                                                   name="mfa_roles[]" 
                                                   value="<?php echo esc_attr( $role_key ); ?>"
                                                   <?php checked( $is_checked, true ); ?>/>
                                            <span class="llar-role-name">
                                                <?php echo $role_display_name; // Already sanitized in controller ?>
                                                <?php if ( $is_admin_role ) : ?>
                                                    <span class="llar-role-recommended"><?php echo esc_html__( '(recommended)', 'limit-login-attempts-reloaded' ); ?></span>
                                                <?php endif; ?>
                                            </span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>

                    <!-- Privacy Notice -->
                    <tr>
                        <td colspan="2">
                            <div class="description-secondary">
                                <?php echo esc_html__( 'By turning this feature ON, you consent that for the selected user groups and for all visitors without an assigned group (e.g., guests), the following data will be sent to a secure endpoint at limitloginattempts.com to facilitate multi-factor authentication: username, IP address, user group (if known), and user agent. We will use this data only for 2FA/MFA and will delete it from our servers as soon as the 2FA session ends, unless you (the admin) specify otherwise. The passwords will NOT be sent to us.', 'limit-login-attempts-reloaded' ); ?>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            <p class="submit">
                <?php wp_nonce_field( 'limit-login-attempts-options' ); ?>
                <input class="button menu__item col button__orange" 
                       name="llar_update_mfa_settings"
                       value="<?php _e( 'Save Settings', 'limit-login-attempts-reloaded' ); ?>"
                       type="submit"/>
            </p>
        </div>
    </form>
</div>
