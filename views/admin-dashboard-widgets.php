<?php
/**
 * Admin dashboard widgets
 */

use LLAR\Core\Config;
use LLAR\Core\LimitLoginAttempts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$active_app = ( Config::get( Config::OPTION_ACTIVE_APP ) === 'custom' && LimitLoginAttempts::$cloud_app ) ? 'custom' : 'local';
$is_active_app_custom = $active_app === 'custom';

if ( $is_active_app_custom ) {

	$is_exhausted = $this->info_is_exhausted();
	$info_has_valid_data = $this->info_has_valid_data();
	$block_sub_group = $this->info_sub_group();
	$upgrade_premium_url = $this->info_upgrade_url();
} else {

	$is_exhausted = false;
	$info_has_valid_data = false;
	$block_sub_group = '';
	$upgrade_premium_url = '';
}

// Variables provided by DashboardRiskRenderer::build_dashboard_widget_vars() via extract() in dashboard_widgets_content().
if ( ! isset( $chart_circle_data ) ) {
	return;
}

?>
<div id="llar-admin-dashboard-widgets">
	<?php if ( ! empty( $show_mfa_recovery_notice ) ) : ?>
		<div class="notice notice-error inline llar-options-notice llar-mfa-recovery-links-expired">
			<p>
				<?php
				echo wp_kses_post(
					sprintf(
						__( '⚠️ Action required: Your existing 2FA recovery links are no longer valid. On the <a href="%s">2FA settings page</a>, turn 2FA off and then back on, then follow the prompts to download the new recovery links.', 'limit-login-attempts-reloaded' ),
						esc_url( $mfa_settings_url )
					)
				);
				?>
			</p>
		</div>
	<?php endif; ?>
    <div class="llar-widget">
        <div class="widget-content">
	        <?php include_once LLA_PLUGIN_DIR . 'views/chart-circle-failed-attempts-today.php'; ?>
        </div>
    </div>
    <div class="llar-widget widget-2">
        <div class="widget-content">
	        <?php include_once LLA_PLUGIN_DIR . 'views/chart-failed-attempts.php'; ?>
        </div>
    </div>
</div>
