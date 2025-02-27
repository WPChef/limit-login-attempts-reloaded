<?php

defined('ABSPATH') || exit;

use LLAR\Core\Config;

// Get active plugin mode
$active_app = Config::get('active_app');
$active_app = ($active_app === 'custom' && class_exists('LLAR\Core\LimitLoginAttempts') && \LLAR\Core\LimitLoginAttempts::$cloud_app) ? 'custom' : 'local';

// Get plugin settings
$app_config = get_option('limit_login_app_config', array());
$mfa_settings = isset($app_config['mfa_roles']) ? $app_config['mfa_roles'] : array();

// Get list of roles and sort them
$roles = wp_roles()->roles;
ksort($roles);

$role_groups = array(
    'Administrators' => array(),
    'Editors'        => array(),
    'Authors'        => array(),
    'Contributors'   => array(),
    'Subscribers'    => array(),
    'Other'          => array(),
);

foreach ($roles as $role_key => $role_data) {
    if (strpos($role_key, 'administrator') !== false) {
        $role_groups['Administrators'][$role_key] = $role_data['name'];
    } elseif (strpos($role_key, 'editor') !== false) {
        $role_groups['Editors'][$role_key] = $role_data['name'];
    } elseif (strpos($role_key, 'author') !== false) {
        $role_groups['Authors'][$role_key] = $role_data['name'];
    } elseif (strpos($role_key, 'contributor') !== false) {
        $role_groups['Contributors'][$role_key] = $role_data['name'];
    } elseif (strpos($role_key, 'subscriber') !== false) {
        $role_groups['Subscribers'][$role_key] = $role_data['name'];
    } else {
        $role_groups['Other'][$role_key] = $role_data['name'];
    }
}
?>

<div id="llar-setting-page-mfa" class="llar-settings-wrap">
	<div class="llar-table-header">
    <h3 class="title_page">
        <img src="<?php echo plugin_dir_url(LLA_PLUGIN_FILE) . 'assets/css/images/icon-help.png'; ?>">
        <?php _e('Multi-Factor Authentication (MFA)', 'limit-login-attempts-reloaded'); ?>
		
    </h3>
    </div>

    <form method="post" action="">
        <?php wp_nonce_field('llar_save_mfa_settings', 'llar_mfa_nonce'); ?>
        <div class="section-content">
			<div class="llar-table-scroll-wrap llar-app-login-infinity-scroll">
				<table class="llar-form-table llar-table-app-login">
					<thead>
						<tr>
							<th scope="col"> <?php _e('User Role Group', 'limit-login-attempts-reloaded'); ?> </th>
							<th scope="col"> <?php _e('Role', 'limit-login-attempts-reloaded'); ?> </th>
							<th scope="col"> <?php _e('MFA Mode', 'limit-login-attempts-reloaded'); ?> </th>
						</tr>
					</thead>
					<tbody class="login-attempts">
						<?php foreach ($role_groups as $group_name => $group_roles) : ?>
							<?php if (!empty($group_roles)) : ?>
								<tr class="role-group">
									<td rowspan="<?php echo count($group_roles); ?>" class="group-title">
										<strong><?php echo esc_html__($group_name, 'limit-login-attempts-reloaded'); ?></strong>
									</td>
									<?php $first = true; ?>
									<?php foreach ($group_roles as $role_key => $role_name) : ?>
										<?php if (!$first) : ?><tr><?php endif; ?>
											<td class="role-name"> <?php echo esc_html($role_name); ?> </td>
											<td class="mfa-mode">
												<select name="llar_mfa_roles[<?php echo esc_attr($role_key); ?>]" class="input_border">
													<option value="off" <?php selected(isset($mfa_settings[$role_key]) ? $mfa_settings[$role_key] : 'off', 'off'); ?>><?php _e('Off', 'limit-login-attempts-reloaded'); ?></option>
													<option value="soft" <?php selected(isset($mfa_settings[$role_key]) ? $mfa_settings[$role_key] : 'off', 'soft'); ?>><?php _e('Soft', 'limit-login-attempts-reloaded'); ?></option>
													<option value="hard" <?php selected(isset($mfa_settings[$role_key]) ? $mfa_settings[$role_key] : 'off', 'hard'); ?>><?php _e('Hard', 'limit-login-attempts-reloaded'); ?></option>
												</select>
											</td>
										</tr>
										<?php $first = false; ?>
									<?php endforeach; ?>
							<?php endif; ?>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>

        <p class="submit">
            <button type="submit" class="button menu__item col button__orange"> <?php _e('Save Changes', 'limit-login-attempts-reloaded'); ?> </button>
        </p>
    </form>
	
</div>
