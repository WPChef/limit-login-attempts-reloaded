<?php
/**
 * Admin dashboard widgets
 *
 */

use LLAR\Core\Config;
use LLAR\Core\LimitLoginAttempts;

if ( ! defined( 'ABSPATH' ) ) exit();

$active_app = ( Config::get( 'active_app' ) === 'custom' && LimitLoginAttempts::$cloud_app ) ? 'custom' : 'local';
$is_active_app_custom = $active_app === 'custom';

if ( $is_active_app_custom ) {

	$is_exhausted = $this->info_is_exhausted();
	$block_sub_group = $this->info_sub_group();
	$upgrade_premium_url = $this->info_upgrade_url();
} else {

	$is_exhausted = false;
	$block_sub_group = '';
	$upgrade_premium_url = '';
}

$api_stats = $is_active_app_custom ? LimitLoginAttempts::$cloud_app->stats() : false;
$setup_code = Config::get( 'app_setup_code' );
$chart_circle_data = $this->get_failed_attempts_circle_data(
	$is_active_app_custom,
	$is_exhausted,
	$block_sub_group,
	$setup_code,
	$upgrade_premium_url,
	$api_stats
);

$show_mfa_recovery_notice = $this->should_show_mfa_recovery_links_expired_notice();
$mfa_settings_url         = $this->get_options_page_uri( 'mfa' );
?>

<div id="llar-admin-dashboard-widgets">
	<?php if ( $show_mfa_recovery_notice ) : ?>
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
	        <?php include_once( LLA_PLUGIN_DIR . 'views/chart-circle-failed-attempts-today.php'); ?>
        </div>
    </div>
    <div class="llar-widget widget-2">
        <div class="widget-content">
	        <?php include_once( LLA_PLUGIN_DIR . 'views/chart-failed-attempts.php'); ?>
        </div>
    </div>
</div>
