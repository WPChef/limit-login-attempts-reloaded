<?php

use LLAR\Core\CloudApp;
use LLAR\Core\Config;
use LLAR\Core\Helpers;
use LLAR\Core\LimitLoginAttempts;

if( !defined( 'ABSPATH' ) ) exit();

$active_app = Config::get( 'active_app' );
$active_app = ( $active_app === 'custom' && LimitLoginAttempts::$cloud_app ) ? 'custom' : 'local';

$wp_locale = str_replace( '_', '-', get_locale() );

$retries_chart_title = '';
$retries_chart_desc = '';
$retries_chart_color = '';

$api_stats = false;
$retries_count = 0;
if( $active_app === 'local' ) {

	$retries_stats = Config::get( 'retries_stats' );

	if( $retries_stats ) {
		foreach ( $retries_stats as $key => $count ) {
		    if( is_numeric( $key ) && $key > strtotime( '-24 hours' ) ) {
			    $retries_count += $count;
            }
		    elseif( !is_numeric( $key ) && date_i18n( 'Y-m-d' ) === $key ) {
			    $retries_count += $count;
            }
        }
	}

    if( $retries_count === 0 ) {

		$retries_chart_title = __( 'Hooray! Zero failed login attempts (past 24 hrs)', 'limit-login-attempts-reloaded' );
		$retries_chart_color = '#97F6C8';
    }
    else if ( $retries_count < 100 ) {

		$retries_chart_title = sprintf( _n( '%d failed login attempt ', '%d failed login attempts ', $retries_count, 'limit-login-attempts-reloaded' ), $retries_count );
		$retries_chart_title .= __( '(past 24 hrs)', 'limit-login-attempts-reloaded' );
		$retries_chart_desc = __( 'Your site is currently at a low risk for brute force activity', 'limit-login-attempts-reloaded' );
		$retries_chart_color = '#FFCC66';
    } else {

		$retries_chart_title = __( 'Warning: Your site has experienced over 100 failed login attempts in the past 24 hours', 'limit-login-attempts-reloaded' );
		$retries_chart_desc = sprintf(__('Your site is currently at a high risk for brute force activity. Consider <a href="%s" class="link__style_color_inherit llar_orange" target="_blank">premium protection</a> if frequent attacks persist or website performance is degraded', 'limit-login-attempts-reloaded'), 'https://www.limitloginattempts.com/info.php?from=plugin-dashboard-status');
		$retries_chart_color = '#FF6633';
    }

} else {

	$api_stats = LimitLoginAttempts::$cloud_app->stats();

	if( $api_stats && !empty( $api_stats['attempts']['count'] )) {

		$retries_count = (int) end( $api_stats['attempts']['count'] );
    }

	$retries_chart_title = __( 'Failed Login Attempts Today', 'limit-login-attempts-reloaded' );
	$retries_chart_desc = __( 'All failed login attempts have been neutralized in the cloud', 'limit-login-attempts-reloaded' );
	$retries_chart_color = '#97F6C8';
}
?>

<div id="llar-dashboard-page">
	<div class="dashboard-section-1 <?php echo esc_attr( $active_app ); ?>">
		<div class="info-box-1">
            <div class="section-title__new">
                <span class="llar-label">
                    <?php _e( 'Failed Login Attempts', 'limit-login-attempts-reloaded' ); ?>
                </span>
                <?php echo $active_app === 'custom'
                    ? '<span class="llar-premium-label"><span class="dashicons dashicons-saved"></span>' . __( 'Cloud protection enabled', 'limit-login-attempts-reloaded' ) . '</span>'
                    : ''; ?>
            </div>
            <div class="section-content">
                <div class="chart">
                    <div class="doughnut-chart-wrap"><canvas id="llar-attack-velocity-chart"></canvas></div>
                    <span class="llar-retries-count"><?php echo esc_html( Helpers::short_number( $retries_count ) ); ?></span>
                </div>
                <script type="text/javascript">
					(function(){

						var ctx = document.getElementById('llar-attack-velocity-chart').getContext('2d');

                        // Add a shadow on the graph
                        let shadow_fill = ctx.fill;
                        ctx.fill = function () {
                            ctx.save();
                            ctx.shadowColor = '<?php echo esc_js( $retries_chart_color ) ?>';
                            ctx.shadowBlur = 10;
                            ctx.shadowOffsetX = 0;
                            ctx.shadowOffsetY = 3;
                            shadow_fill.apply(this, arguments)
                            ctx.restore();
                        };

						var llar_retries_chart = new Chart(ctx, {
							type: 'doughnut',
							data: {
								// labels: ['Success', 'Warning', 'Warning', 'Fail'],
								datasets: [{
									data: [1],
									value: <?php echo esc_js( $retries_count ); ?>,
									backgroundColor: ['<?php echo esc_js( $retries_chart_color ); ?>'],
									borderWidth: 0,
								}]
							},
							options: {
                                layout: {
                                    padding: {
                                        bottom: 10,
                                    },
                                },
								responsive: true,
								cutout: 65,
								title: {
									display: false,
								},
                                plugins: {
                                    tooltip: {
                                        enabled: false,
                                    }
                                },
							}
						});

					})();
                </script>
                <div class="title"><?php echo esc_html( $retries_chart_title ); ?></div>
                <div class="desc"><?php echo $retries_chart_desc; ?></div>
            </div>
        </div>

        <div class="info-box-2">
            <div class="section-title__new">
                <span class="llar-label">
                    <span class="llar-label__circle-blue">&bull;</span>
                    <?php _e( 'Failed Login Attempts', 'limit-login-attempts-reloaded' ); ?>
                </span>
                <span class="llar-label__url">
                    <a href="<?= home_url( '/' ) ?>" class="link__style_unlink">
                        <?= wp_parse_url( home_url(), PHP_URL_HOST ) ?>
                    </a>
                </span>
            </div>
            <div class="section-content">
                <?php
                $chart2_label = '';
                $chart2_labels = array();
				$chart2_datasets = array();
                $chart2__color = '#58C3FF';
                $chart2__color_gradient = '#58C3FFB3';

                if( $active_app === 'custom' ) {

                    $stats_dates = array();
                    $stats_values = array();
                    $date_format = trim( get_option( 'date_format' ), ' yY,._:;-/\\' );
                    $date_format = str_replace( 'F', 'M', $date_format );

					$dataset = array(
						'label' => __('Failed Login Attempts', 'limit-login-attempts-reloaded'),
						'data' => [],
						'backgroundColor' => 'white',
						'borderColor' => $chart2__color,
						'fill' => false,
					);

                    if( $api_stats && !empty( $api_stats['attempts'] ) ) {

                        foreach ($api_stats['attempts']['at'] as $timest) {

                            $stats_dates[] = date( $date_format, $timest );
                        }

                        $chart2_label = __('Requests', 'limit-login-attempts-reloaded');
                        $chart2_labels = $stats_dates;

                        $dataset['data'] = $api_stats['attempts']['count'];
                    }

					$chart2_datasets[] = $dataset;

                } else {

					$date_format = trim( get_option( 'date_format' ), ' yY,._:;-/\\' );
					$date_format = str_replace( 'F', 'M', $date_format );

					$retries_stats = Config::get( 'retries_stats' );

					if( is_array( $retries_stats ) && $retries_stats ) {
                        $key = key( $retries_stats );
                        $start = is_numeric( $key ) ? date_i18n( 'Y-m-d', $key ) : $key;

						$daterange = new DatePeriod(
							new DateTime( $start ),
							new DateInterval('P1D'),
							new DateTime('-1 day')
						);

						$retries_per_day = [];
						foreach ( $retries_stats as $key => $count ) {

						    $date = is_numeric( $key ) ? date_i18n( 'Y-m-d', $key ) : $key;

						    if( empty( $retries_per_day[$date] ) ) {
							    $retries_per_day[$date] = 0;
                            }

							$retries_per_day[$date] += $count;
						}

						$chart2_data = array();
						foreach ($daterange as $date) {

							$chart2_labels[] = $date->format( $date_format );
							$chart2_data[] = (!empty($retries_per_day[$date->format("Y-m-d")])) ? $retries_per_day[$date->format("Y-m-d")] : 0;
						}
                    } else {

						$chart2_labels[] = (new DateTime())->format( $date_format );
						$chart2_data[] = 0;
                    }


                    $chart2_datasets[] = array(
						'label' => __( 'Failed Login Attempts', 'limit-login-attempts-reloaded' ),
						'data' => $chart2_data,
						'borderColor' => $chart2__color,
						'fill' => false,
                    );
				}
                ?>

                <div class="llar-chart-wrap">
                    <canvas id="llar-api-requests-chart" style=""></canvas>
                </div>

                <script type="text/javascript">
					(function(){

						let ctx = document.getElementById('llar-api-requests-chart').getContext('2d');

						// Add a gradient fill below the graph
                        const gradient = ctx.createLinearGradient(0, 0, 0, 350);
                        gradient.addColorStop(0, '<?php echo esc_js( $chart2__color_gradient ); ?>');
                        gradient.addColorStop(1, '<?php echo esc_js( '#FFFFFF00' ); ?>');

                        let new_array = <?php echo json_encode($chart2_datasets); ?>;

                        new_array[0].fill = true;
                        new_array[0].backgroundColor = gradient;

                        let llar_stat_chart = new Chart(ctx, {
							type: 'line',
							data: {
								labels: <?php echo json_encode( $chart2_labels ); ?>,
                                datasets: new_array,
							},
							options: {
                                elements: {
                                    point: {
                                        pointStyle: 'circle',
                                        radius: 3.5,
                                        pointBackgroundColor: 'white',
                                        pointBorderWidth: 1.5,
                                        pointBorderColor: '<?php echo esc_js( $chart2__color ); ?>',
                                    }
                                },
								responsive: true,
								maintainAspectRatio: false,
                                plugins: {
                                    tooltip: {
                                        mode: 'index',
                                        intersect: false,
                                        callbacks: {
                                            label: function (context) {
                                                return context.raw.toLocaleString('<?php echo esc_js( $wp_locale ); ?>');
                                            }
                                        }
                                    },
                                    legend: {
                                        display: false,
                                    }
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
													return label.toLocaleString('<?php echo esc_js( $wp_locale ); ?>');
												}
											},
										}
									}
								}
							}
						});
					})();
                </script>

            </div>
        </div>
        <?php if( $active_app === 'local' ) : ?>
		<div class="info-box-3">
            <div class="section-title__new">
                <div class="title"><?php _e( 'Enable Micro Cloud (FREE)', 'limit-login-attempts-reloaded' ); ?></div>
            </div>
            <div class="section-content">
                <div class="desc">
                    <ul class="list-unstyled">
                        <li class="star">
                            <?php _e( 'Help us secure our network by providing access to your login IP data.', 'limit-login-attempts-reloaded' ); ?>
                        </li>
                        <li class="star">
                            <?php _e( 'In return, receive access to our premium features up to 1,000 requests per month!', 'limit-login-attempts-reloaded' ); ?>
                        </li>
                        <li class="star">
                            <?php _e( 'Once 1,000 requests are reached each month, the premium app will switch back to the free version and reset the follow month.', 'limit-login-attempts-reloaded' ); ?>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="actions">
                <div class="actions__buttons">
                    <a href="https://www.limitloginattempts.com/upgrade/?from=plugin-dashboard-cta" title="Upgrade To Premium" target="_blank" class="link__style_unlink">
                        <button class="menu__item col button__transparent_orange">
                            Learn More
                        </button>
                    </a>
                    <a href="https://www.limitloginattempts.com/upgrade/?from=plugin-dashboard-cta" title="Upgrade To Premium" target="_blank" class="link__style_unlink">
                        <button class="menu__item col button__orange">
                            Get Started
                        </button>
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
	</div>
	<div class="dashboard-section-3">
        <div class="info-box-1">
            <div class="info-box-icon">
                <svg class="icon-base">
                    <use xlink:href="<?= LLA_PLUGIN_URL ?>/assets/css/images/sprite.svg#icon-lock"></use>
                </svg>
            </div>
            <div class="info-box-content">
                <div class="title"><a href="<?php echo $this->get_options_page_uri('logs-'.$active_app); ?>" class="link__style_unlink">
                        <?php _e( 'Tools', 'limit-login-attempts-reloaded' ); ?>
                    </a>
                </div>
                <div class="desc">
                    <?php _e( 'View lockouts logs, block or whitelist usernames or IPs, and more.', 'limit-login-attempts-reloaded' ); ?>
                </div>
            </div>
        </div>
        <div class="info-box-1">
            <div class="info-box-icon">
                <svg class="icon-base">
                    <use xlink:href="<?= LLA_PLUGIN_URL ?>/assets/css/images/sprite.svg#icon-help"></use>
                </svg>
            </div>
            <div class="info-box-content">
                <div class="title">
                    <a href="https://www.limitloginattempts.com/info.php?from=plugin-dashboard-help" class="link__style_unlink" target="_blank">
                        <?php _e( 'Help', 'limit-login-attempts-reloaded' ); ?>
                    </a>
                </div>
                <div class="desc">
                    <?php _e( 'Find the documentation and help you need.', 'limit-login-attempts-reloaded' ); ?>
                </div>
            </div>
        </div>
        <div class="info-box-1">
            <div class="info-box-icon">
                <svg class="icon-base">
                    <use xlink:href="<?= LLA_PLUGIN_URL ?>/assets/css/images/sprite.svg#icon-web"></use>
                </svg>
            </div>
            <div class="info-box-content">
                <div class="title">
                    <a href="<?php echo $this->get_options_page_uri('settings'); ?>" class="link__style_unlink">
                        <?php _e( 'Global Options', 'limit-login-attempts-reloaded' ); ?>
                    </a>
                </div>
                <div class="desc">
                    <?php _e( 'Many options such as notifications, alerts, premium status, and more.', 'limit-login-attempts-reloaded' ); ?>
                </div>
            </div>
        </div>
    </div>

    <?php if( $stats_global = CloudApp::stats_global() ) : ?>
	<div class="dashboard-section-4">
        <?php
		$stats_global_dates = array();
		$date_format = trim( get_option( 'date_format' ), ' yY,._:;-/\\' );
		$date_format = str_replace( 'F', 'M', $date_format );
        $chart3__color = '#FF7C06';
        $chart3__color_gradient = '#FF7C06B3';

		foreach ( $stats_global['attempts']['day']['at'] as $timest ) {

			$stats_global_dates[] = date( $date_format, $timest );
		}

		$countries_list = Helpers::get_countries_list();
        ?>
        <div class="info-box-1">
            <div class="section-title__new">
                <span class="llar-label">
                    <?php _e( 'Failed Login Attempts', 'limit-login-attempts-reloaded' ); ?>
                </span>
                <span class="llar-label llar-label__date">
                    <?php _e( 'Today', 'limit-login-attempts-reloaded' ); ?>
                </span>
                <span class="llar-label llar-label__info">
                    <?php _e( 'Global Network (Premium Users)', 'limit-login-attempts-reloaded' ); ?>
                    <div class="hint_tooltip-parent">
                    <span class="dashicons dashicons-secondary dashicons-editor-help"></span>
                    <div class="hint_tooltip">
                        <ul class="hint_tooltip-content">
                            <li>
                                <?php esc_attr_e( 'Failed logins for all users in the LLAR network.', 'limit-login-attempts-reloaded' ); ?>
                            </li>
                        </ul>
                    </div>
                </div>
                </span>
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
                                <?php echo esc_html( $country_name ); ?>
                            </td>
                            <td>
                                <?php echo esc_html( number_format_i18n( $country_data['attempts'] ) ); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <p class="countries-table-info">
                    <?php _e( 'Block by country feature available with <a href="https://www.limitloginattempts.com/info.php?from=plugin-dashboard-country" class="link__style_color_inherit llar_bold" target="_blank">premium plus plan</a>.', 'limit-login-attempts-reloaded' ) ?>
                </p>
            </div>
        </div>

        <div class="info-box-2">
            <div class="section-title__new">
                <span class="llar-label">
                    <?php _e( 'Total Failed Login Attempts', 'limit-login-attempts-reloaded' ); ?>
                </span>
                <span class="llar-label llar-label__date">
                    <span class="llar-label__circle-orange">&bull;</span>
                    <?php _e( 'Total Attempts', 'limit-login-attempts-reloaded' ); ?>
                </span>
                <span class="llar-label llar-label__info">
                    <?php _e( 'Global Network (Premium Users)', 'limit-login-attempts-reloaded' ); ?>
                    <div class="hint_tooltip-parent">
                    <span class="dashicons dashicons-editor-help"></span>
                    <div class="hint_tooltip">
                        <ul class="hint_tooltip-content">
                            <li>
                                <?php esc_attr_e( 'Failed logins for all users in the LLAR network.', 'limit-login-attempts-reloaded' ); ?>
                            </li>
                        </ul>
                    </div>
                </div>
                </span>
            </div>
            <div class="section-content">
                <div class="llar-chart-wrap">
                    <canvas id="llar-total-attacks-blocked-chart" style=""></canvas>
                </div>
                <script type="text/javascript">
                    (function(){

                        var ctx = document.getElementById('llar-total-attacks-blocked-chart').getContext('2d');

                        // Add a gradient fill below the graph
                        const gradient = ctx.createLinearGradient(0, 0, 0, 350);
                        gradient.addColorStop(0, '<?php echo esc_js( $chart3__color_gradient ); ?>');
                        gradient.addColorStop(1, '<?php echo esc_js( '#FFFFFF00' ); ?>');

                        var llar_total_attacks_blocked_chart = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: <?php echo json_encode( $stats_global_dates ); ?>,
                                datasets: [{
                                    label: '<?php echo esc_js( __( 'Total Attempts', 'limit-login-attempts-reloaded' ) ); ?>',
                                    data: <?php echo json_encode( $stats_global['attempts']['day']['count'] ); ?>,
                                    backgroundColor: gradient,
                                    borderColor: '<?php echo esc_js( $chart3__color ); ?>',
                                    fill: true
                                }]
                            },
                            options: {
                                elements: {
                                    point: {
                                        pointStyle: 'circle',
                                        radius: 3.5,
                                        pointBackgroundColor: 'white',
                                        pointBorderWidth: 1.5,
                                        pointBorderColor: '<?php echo esc_js($chart3__color); ?>',
                                    }
                                },
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    tooltip: {
                                        mode: 'index',
                                        intersect: false,
                                        callbacks: {
                                            label: function (context) {
                                                return context.raw.toLocaleString('<?php echo esc_js( $wp_locale ); ?>');
                                            }
                                        }
                                    },
                                    legend: {
                                        display: false,
                                    }
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
                                                    return label.toLocaleString('<?php echo esc_js( $wp_locale ); ?>');
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