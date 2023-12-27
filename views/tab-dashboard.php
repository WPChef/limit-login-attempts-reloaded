<?php

use LLAR\Core\CloudApp;
use LLAR\Core\Config;
use LLAR\Core\Helpers;
use LLAR\Core\LimitLoginAttempts;

if( !defined( 'ABSPATH' ) ) exit();

$active_app = Config::get( 'active_app' );
$active_app = ( $active_app === 'custom' && LimitLoginAttempts::$cloud_app ) ? 'custom' : 'local';
?>

<div id="llar-dashboard-page">
	<div class="dashboard-header">
		<h1><?php _e( 'Limit Login Attempts Reloaded Dashboard', 'limit-login-attempts-reloaded' ); ?></h1>
	</div>
	<div class="dashboard-section-1 <?php echo esc_attr( $active_app ); ?>">
		<div class="info-box-1">
            <div class="section-title"><?php _e( 'Failed Login Attempts', 'limit-login-attempts-reloaded' ); ?>
                <i class="llar-tooltip" data-text="<?php esc_attr_e( 'An IP that hasn\'t been previously denied by the cloud app, but has made an unsuccessful login attempt on your website.', 'limit-login-attempts-reloaded' ); ?>">
                    <span class="dashicons dashicons-editor-help"></span>
                </i>
                <?php echo $active_app === 'custom' ? '<span class="llar-premium-label"><span class="dashicons dashicons-yes-alt"></span>' . __( 'Cloud protection enabled', 'limit-login-attempts-reloaded' ) . '</span>' : ''; ?></div>
            <div class="section-content">
	            <?php include_once( LLA_PLUGIN_DIR . 'views/chart-circle-failed-attempts-today.php'); ?>
            </div>
        </div>
        <div class="info-box-2">
            <div class="section-content">
	            <?php include_once( LLA_PLUGIN_DIR . 'views/chart-failed-attempts.php'); ?>
            </div>
        </div>
        <?php if( $active_app === 'local' ) : ?>
		<div class="info-box-3">
            <div class="section-content">
                <div class="title"><?php _e( 'Premium Protection Disabled', 'limit-login-attempts-reloaded' ); ?></div>
                <div class="desc"><?php _e( 'As a free user, your local server is absorbing the traffic brought on by brute force attacks, potentially slowing down your website. Upgrade to Premium today to outsource these attacks through our cloud app, and slow down future attacks with advanced throttling.', 'limit-login-attempts-reloaded' ); ?></div>
                <div class="actions">
                    <a href="https://www.limitloginattempts.com/info.php?from=plugin-dashboard-cta" target="_blank" class="button button-primary"><?php _e( 'Upgrade to Premium', 'limit-login-attempts-reloaded' ); ?></a><br>
                </div>
            </div>
        </div>
        <?php endif; ?>
	</div>
	<div class="dashboard-section-3">
        <div class="info-box-1">
            <div class="info-box-icon">
                <span class="dashicons dashicons-admin-tools"></span>
            </div>
            <div class="info-box-content">
                <div class="title"><a href="<?php echo $this->get_options_page_uri('logs-'.$active_app); ?>"><?php _e( 'Tools', 'limit-login-attempts-reloaded' ); ?></a></div>
                <div class="desc"><?php _e( 'View lockouts logs, block or whitelist usernames or IPs, and more.', 'limit-login-attempts-reloaded' ); ?></div>
            </div>
        </div>
        <div class="info-box-1">
            <div class="info-box-icon">
                <span class="dashicons dashicons-sos"></span>
            </div>
            <div class="info-box-content">
                <div class="title"><a href="https://www.limitloginattempts.com/info.php?from=plugin-dashboard-help" target="_blank"><?php _e( 'Help', 'limit-login-attempts-reloaded' ); ?></a></div>
                <div class="desc"><?php _e( 'Find the documentation and help you need.', 'limit-login-attempts-reloaded' ); ?></div>
            </div>
        </div>
        <div class="info-box-1">
            <div class="info-box-icon">
                <span class="dashicons dashicons-admin-generic"></span>
            </div>
            <div class="info-box-content">
                <div class="title"><a href="<?php echo $this->get_options_page_uri('settings'); ?>"><?php _e( 'Global Options', 'limit-login-attempts-reloaded' ); ?></a></div>
                <div class="desc"><?php _e( 'Many options such as notifications, alerts, premium status, and more.', 'limit-login-attempts-reloaded' ); ?></div>
            </div>
        </div>
    </div>
    <?php if( $stats_global = CloudApp::stats_global() ) : ?>
	<div class="dashboard-section-4">
        <?php
		$stats_global_dates = array();
		$date_format = trim( get_option( 'date_format' ), ' yY,._:;-/\\' );
		$date_format = str_replace( 'F', 'M', $date_format );

		foreach ( $stats_global['attempts']['day']['at'] as $timest ) {

			$stats_global_dates[] = date( $date_format, $timest );
		}
		
		$countries_list = Helpers::get_countries_list();
        ?>
        <div class="info-box-1">
            <div class="section-title">
                <span><?php _e( 'Failed Login Attempts By Country', 'limit-login-attempts-reloaded' ); ?></span>
                <span class="section-title-info"><?php _e( 'Global Network (Premium Users)', 'limit-login-attempts-reloaded' ); ?>
                <i class="llar-tooltip" data-text="<?php esc_attr_e( 'Failed logins for all users in the LLAR network.', 'limit-login-attempts-reloaded' ); ?>">
                    <span class="dashicons dashicons-editor-help"></span>
                </i></span>
            </div>
            <div class="section-content">
                <table class="lockouts-by-country-table">
                    <tr>
                        <th><?php _e( 'Country', 'limit-login-attempts-reloaded' ); ?></th>
                        <th><?php _e( 'Attempts', 'limit-login-attempts-reloaded' ); ?></th>
                    </tr>
                    <?php foreach( $stats_global['countries'] as $country_data ) :

                        $country_code = ( array_key_exists( $country_data['code'], $countries_list ) ) ? $country_data['code'] : 'ZZ';
                        $country_name = apply_filters( 'llar_country_name', $countries_list[$country_code], $country_code );
                        ?>
                        <tr>
                            <td>
                                <?php if( $country_code !== 'ZZ' ) : ?>
                                <img class="flag-icon" src="<?php echo LLA_PLUGIN_URL; ?>/assets/img/flags/<?php echo esc_attr( $country_data['code'] ); ?>.png">
                                <?php endif; ?>
                            <?php echo esc_html( $country_name ); ?></td>
                            <td><?php echo esc_html( number_format_i18n( $country_data['attempts'] ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <p class="countries-table-info"><?php _e( 'today', 'limit-login-attempts-reloaded' ); ?></p>
                <p class="countries-table-info-right"><?php _e( 'Block by country feature available with <a href="https://www.limitloginattempts.com/info.php?from=plugin-dashboard-country" target="_blank">premium plus plan</a>.', 'limit-login-attempts-reloaded' ) ?></p>
            </div>
        </div>

        <div class="info-box-2">
            <div class="section-title">
				<span><?php _e( 'Total Failed Login Attempts', 'limit-login-attempts-reloaded' ); ?></span>
                <span class="section-title-info"><?php _e( 'Global Network (Premium Users)', 'limit-login-attempts-reloaded' ); ?></span>
            </div>
            <div class="section-content">
                <div class="llar-chart-wrap">
                    <canvas id="llar-total-attacks-blocked-chart" style=""></canvas>
                </div>
                <script type="text/javascript">
                    (function(){

                        var ctx = document.getElementById('llar-total-attacks-blocked-chart').getContext('2d');
                        var llar_total_attacks_blocked_chart = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: <?php echo json_encode( $stats_global_dates ); ?>,
                                datasets: [{
                                    label: '<?php echo esc_js( __( 'Total Attempts', 'limit-login-attempts-reloaded' ) ); ?>',
                                    data: <?php echo json_encode( $stats_global['attempts']['day']['count'] ); ?>,
                                    backgroundColor: 'rgb(255, 159, 64)',
                                    borderColor: 'rgb(255, 159, 64)',
                                    fill: false
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    tooltip: {
                                        mode: 'index',
                                        intersect: false,
                                        callbacks: {
                                            label: function (context) {
                                                return context.raw.toLocaleString('<?php echo esc_js( Helpers::wp_locale() ); ?>');
                                            }
                                        }
                                    },
                                },
                                hover: {
                                    mode: 'nearest',
                                    intersect: true
                                },
                                scales: {
                                    x: {
                                        display: true,
                                        scaleLabel: {
                                            display: false
                                        }
                                    },
                                    y: {
                                        display: true,
                                        scaleLabel: {
                                            display: false
                                        },
                                        beginAtZero: true,
                                        ticks: {
                                            callback: function(label, index, labels) {
                                                if (Math.floor(label) === label) {
                                                    return label.toLocaleString('<?php echo esc_js( Helpers::wp_locale() ); ?>');
                                                }
                                            },
                                        }
                                    }
                                },
                            }
                        });

                    })();
                </script>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once( LLA_PLUGIN_DIR . 'views/onboarding-popup.php')?>