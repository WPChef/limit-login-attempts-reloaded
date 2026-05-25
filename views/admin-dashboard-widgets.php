<?php
/**
 * Admin dashboard widgets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
