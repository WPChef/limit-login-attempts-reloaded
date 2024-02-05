<?php

use LLAR\Core\Config;
use LLAR\Core\Helpers;
use LLAR\Core\LimitLoginAttempts;

if ( ! defined( 'ABSPATH' ) ) exit();

$active_tab = "dashboard";
$active_app = Config::get( 'active_app' );

if ( ! empty( $_GET["tab"]) && in_array( $_GET["tab"], array( 'logs-local', 'logs-custom', 'settings', 'debug', 'premium', 'help' ) ) ) {

	if ( ! LimitLoginAttempts::$cloud_app && $_GET['tab'] === 'logs-custom' ) {

		$active_tab = 'logs-local';
	} else {

		$active_tab = sanitize_text_field( $_GET["tab"] );
	}
}

$auto_update_choice = Config::get( 'auto_update_choice' );
$actual_plan = $active_app === 'custom' ? $this->info_sub_group() : '';
?>

<?php if ( $active_app !== 'local' && $actual_plan === 'Micro Cloud' ) : ?>

	<?php $upgrade_premium_url = $this->info_upgrade_url(); ?>
    <div id="llar-header-upgrade-message">
        <p>
            <span class="dashicons dashicons-superhero"></span>
	        <?php echo sprintf(
	                __( 'Enjoying Micro Cloud? To prevent interruption of the cloud app, <a href="%s" class="link__style_color_inherit" target="_blank">Upgrade to Premium</a> today', 'limit-login-attempts-reloaded' ),
		        $upgrade_premium_url );
	        ?>
        </p>
    </div>
<?php endif; ?>

<?php if ( ( $auto_update_choice || $auto_update_choice === null ) && !Helpers::is_auto_update_enabled() ) : ?>
<div class="notice notice-error llar-auto-update-notice">
    <p>
        <?php _e( 'Do you want Limit Login Attempts Reloaded to provide the latest version automatically?', 'limit-login-attempts-reloaded' ); ?>
        <a href="#" class="auto-enable-update-option" data-val="yes">
            <?php _e( 'Yes, enable auto-update', 'limit-login-attempts-reloaded' ); ?>
        </a>
        |
        <a href="#" class="auto-enable-update-option" data-val="no">
            <?php _e( 'No thanks', 'limit-login-attempts-reloaded' ); ?>
        </a>
    </p>
</div>
<?php endif; ?>

<div id="llar_popup_error_content" style="display: none">
    <div class="popup_error_content__content">
        <div class="popup_error_content__body">
            <div class="card mx-auto">
                <div class="card-body">
                </div>
            </div>
        </div>
    </div>
</div>

<div class="wrap limit-login-page-settings">

    <div class="limit-login-page-settings__logo_block">
        <img class="limit-login-page-settings__logo" src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/logo-llap.png">

	    <?php if ( $active_app !== 'local' ) : ?>
            <a href="https://my.limitloginattempts.com/" class="link__style_unlink" target="_blank">
                Account Login
                <div class="info-box-icon">
                    <img src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/icon-backup-big-bw.png">
                </div>
            </a>
	    <?php endif; ?>

    </div>

    <?php $nav_tab_active = ' nav-tab-active'; ?>
    <div class="nav-tab-wrapper">
        <a href="<?php echo $this->get_options_page_uri( 'dashboard' ); ?>"
           class="nav-tab<?php echo $active_tab === 'dashboard' ? $nav_tab_active : '' ?>">
            <?php _e( 'Dashboard', 'limit-login-attempts-reloaded' ); ?>
        </a>
        <a href="<?php echo $this->get_options_page_uri( 'settings' ); ?>"
           class="nav-tab<?php echo $active_tab === 'settings' ? $nav_tab_active : '' ?>">
            <?php _e( 'Settings', 'limit-login-attempts-reloaded' ); ?>
        </a>

        <?php if( $active_app === 'custom' ) : ?>
            <a href="<?php echo $this->get_options_page_uri( 'logs-custom' ); ?>"
               class="nav-tab<?php echo $active_tab === 'logs-custom' ? $nav_tab_active : '' ?>">
                <?php _e( 'Login Firewall', 'limit-login-attempts-reloaded' ); ?>
            </a>
        <?php else : ?>
            <a href="<?php echo $this->get_options_page_uri( 'logs-local' ); ?>"
               class="nav-tab<?php echo $active_tab === 'logs-local' ? $nav_tab_active : '' ?>">
                <?php _e( 'Logs', 'limit-login-attempts-reloaded' ); ?>
            </a>
		<?php endif; ?>

        <a href="<?php echo $this->get_options_page_uri( 'debug' ); ?>"
           class="nav-tab<?php echo $active_tab === 'debug' ? $nav_tab_active : '' ?>">
            <?php _e( 'Debug', 'limit-login-attempts-reloaded' ); ?>
        </a>
        <a href="<?php echo $this->get_options_page_uri( 'help' ); ?>"
           class="nav-tab<?php echo $active_tab === 'help' ? $nav_tab_active : '' ?>">
            <?php _e( 'Help', 'limit-login-attempts-reloaded' ); ?>
        </a>
        <a href="<?php echo $this->get_options_page_uri( 'premium' ); ?>"
           class="nav-tab<?php echo $active_tab === 'premium' ? $nav_tab_active : '' ?>">
            <?php _e( 'Premium / Extensions', 'limit-login-attempts-reloaded' ); ?>
        </a>

        <?php if ( $active_tab === 'logs-custom' ) : ?>
            <a class="unlink llar-label llar-failover-link" href="<?php echo $this->get_options_page_uri( 'logs-local' ); ?>">
                <?php _e( 'Failover', 'limit-login-attempts-reloaded' ); ?>
                <span class="hint_tooltip-parent">
                    <span class="dashicons dashicons-editor-help"></span>
                    <div class="hint_tooltip">
                        <div class="hint_tooltip-content">
                            <?php _e( 'Automatic switch to free version when premium stops working (usually due to non-payment or exceeding monthly resource budget).', 'limit-login-attempts-reloaded' ); ?>
                        </div>
                    </div>
                </span>
            </a>
        <?php endif; ?>
    </div>

    <?php include_once( LLA_PLUGIN_DIR.'views/tab-'.$active_tab.'.php' ); ?>
</div>