<?php

use LLAR\Core\Config;
use LLAR\Core\Helpers;
use LLAR\Core\LimitLoginAttempts;

if (!defined('ABSPATH')) exit();

$active_app = ( Config::get( 'active_app' ) === 'custom' && LimitLoginAttempts::$cloud_app ) ? 'custom' : 'local';

$retries_chart_title = '';
$retries_chart_desc = '';
$retries_chart_color = '';

$api_stats = false;
$retries_count = 0;
if( $active_app === 'local' ) {

	$retries_stats = Config::get( 'retries_stats' );

	if ( $retries_stats ) {
		foreach ( $retries_stats as $key => $count ) {
			if( is_numeric( $key ) && $key > strtotime( '-24 hours' ) ) {
				$retries_count += $count;
			}
            elseif ( ! is_numeric( $key ) && date_i18n( 'Y-m-d' ) === $key ) {
				$retries_count += $count;
			}
		}
	}

	if( $retries_count === 0 ) {

		$retries_chart_title = __( 'Hooray! Zero failed login attempts (past 24 hrs)', 'limit-login-attempts-reloaded' );
		$retries_chart_color = '#66CC66';
	}
	else if ( $retries_count < 100 ) {

		$retries_chart_title = sprintf( _n( '%d failed login attempt ', '%d failed login attempts ', $retries_count, 'limit-login-attempts-reloaded' ), $retries_count );
		$retries_chart_title .= __( '(past 24 hrs)', 'limit-login-attempts-reloaded' );
		$retries_chart_desc = __( 'Your site is currently at a low risk for brute force activity', 'limit-login-attempts-reloaded' );
		$retries_chart_color = '#FFCC66';
	} else {

		$retries_chart_title = __( 'Warning: Your site has experienced over 100 failed login attempts in the past 24 hours', 'limit-login-attempts-reloaded' );
		$retries_chart_desc = sprintf(__('Your site is currently at a high risk for brute force activity. Consider <a href="%s" target="_blank">premium protection</a> if frequent attacks persist or website performance is degraded', 'limit-login-attempts-reloaded'), 'https://www.limitloginattempts.com/info.php?from=plugin-dashboard-status');
		$retries_chart_color = '#FF6633';
	}

} else {

	$api_stats = LimitLoginAttempts::$cloud_app->stats();

	if( $api_stats && !empty( $api_stats['attempts']['count'] )) {

		$retries_count = (int) end( $api_stats['attempts']['count'] );
	}

	$retries_chart_title = __( 'Failed Login Attempts Today', 'limit-login-attempts-reloaded' );
	$retries_chart_desc = __( 'All failed login attempts have been neutralized in the cloud', 'limit-login-attempts-reloaded' );
	$retries_chart_color = '#66CC66';
}
?>

<div class="chart">
    <div class="doughnut-chart-wrap"><canvas id="llar-attack-velocity-chart"></canvas></div>
    <span class="llar-retries-count"><?php echo esc_html( Helpers::short_number( $retries_count ) ); ?></span>
</div>
<script type="text/javascript">
    (function(){

        var ctx = document.getElementById('llar-attack-velocity-chart').getContext('2d');
        var llar_retries_chart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                // labels: ['Success', 'Warning', 'Warning', 'Fail'],
                datasets: [{
                    data: [1],
                    value: <?php echo esc_js( $retries_count ); ?>,
                    backgroundColor: ['<?php echo esc_js( $retries_chart_color ); ?>'],
                    borderWidth: [0],
                }]
            },
            options: {
                responsive: true,
                cutout: 50,
                title: {
                    display: false,
                },
                plugins: {
                    tooltip: {
                        enabled: false
                    }
                }
            }
        });

    })();
</script>
<div class="title"><?php echo esc_html( $retries_chart_title ); ?></div>
<div class="desc"><?php echo $retries_chart_desc ?></div>
