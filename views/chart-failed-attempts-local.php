<?php
/**
 * Chart failed attempts - Local version
 *
 * @var string $active_app
 * @var string $is_active_app_custom
 */

use LLAR\Core\Config;
use LLAR\Core\Helpers;

$chart_local_label = '';
$chart_local_labels = array();
$chart_local_datasets = array();
$chart_local_attempts_scale_max = 0;

$chart_local_color_attempts = '#58C3FF';
$chart_local_color_gradient_attempts = '#58C3FF4D';

$date_format = trim( get_option( 'date_format' ), ' yY,._:;-/\\' );
$date_format = str_replace( 'F', 'M', $date_format );

$retries_stats = Config::get( 'retries_stats' );

if ( is_array( $retries_stats ) && $retries_stats ) {
	$key = key( $retries_stats );
	$start = is_numeric( $key ) ? date_i18n( 'Y-m-d', $key ) : $key;

	$daterange = new DatePeriod(
		new DateTime( $start ),
		new DateInterval('P1D'),
		new DateTime('-1 day')
	);

	$retries_per_day = array();
	foreach ( $retries_stats as $key => $count ) {

		$date = is_numeric( $key ) ? date_i18n( 'Y-m-d', $key ) : $key;

		if( empty( $retries_per_day[$date] ) ) {
			$retries_per_day[$date] = 0;
		}

		$retries_per_day[$date] += $count;
	}

	$chart_local_data = array();
	foreach ( $daterange as $date ) {
		$chart_local_labels[] = $date->format( $date_format );
		$chart_local_data[] = ( !empty($retries_per_day[ $date->format("Y-m-d")] ) ) ? $retries_per_day[ $date->format("Y-m-d") ] : 0;
	}
} else {

	$chart_local_labels[] = ( new DateTime())->format( $date_format );
	$chart_local_data[] = 0;
}

$chart_local_datasets[] = array(
	'label' => __( 'Failed Login Attempts', 'limit-login-attempts-reloaded' ),
	'data' => $chart_local_data,
	'backgroundColor'   => $chart_local_color_gradient_attempts,
	'borderColor'       => $chart_local_color_attempts,
	'fill'              => true,
);
?>

<div class="section-title__new section-title__new--local">
    <div class="llar-label-group llar-label-group--local">
        <span class="llar-label llar-label--local">
            <span class="llar-label__circle-blue">&bull;</span>
            <?php _e( 'Failed Login Attempts', 'limit-login-attempts-reloaded' ); ?>
            <span class="hint_tooltip-parent">
                <span class="dashicons dashicons-editor-help"></span>
                <div class="hint_tooltip">
                    <div class="hint_tooltip-content">
                        <?php esc_attr_e( 'An IP that has made an unsuccessful login attempt on your website.', 'limit-login-attempts-reloaded' ); ?>
                    </div>
                </div>
            </span>
        </span>
    </div>
</div>
<div class="section-content section-content--local">
    <div class="llar-chart-wrap llar-chart-wrap--local">
        <canvas id="llar-api-requests-chart-local" style=""></canvas>
    </div>
</div>

<script type="text/javascript">
    (function(){

        var ctx = document.getElementById('llar-api-requests-chart-local').getContext('2d');

        var llar_stat_chart_local = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode( $chart_local_labels ); ?>,
                datasets: <?php echo json_encode($chart_local_datasets); ?>,
            },
            options: {
                elements: {
                    point: {
                        pointStyle: 'circle',
                        radius: 3.5,
                        pointBackgroundColor: 'white',
                        pointBorderWidth: 1.5,
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
                                return context.raw.toLocaleString('<?php echo esc_js( Helpers::wp_locale() ); ?>');
                            }
                        }
                    },
                    legend: {
                        display: false
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
                        title: {
                            display: false,
                        },
                        beginAtZero: true,
                        position: 'left',
                        ticks: {
                            callback: function(label) {
                                if (Math.floor(label) === label) {
                                    return label.toLocaleString('<?php echo esc_js( Helpers::wp_locale() ); ?>');
                                }
                            },
                        }
                    },
                }
            }
        });

    })();
</script>
