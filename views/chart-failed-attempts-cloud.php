<?php
/**
 * Chart failed attempts - Cloud/Custom App version
 *
 * @var string $active_app
 * @var string $is_active_app_custom
 * @var bool|mixed $api_stats
 * @var bool $is_agency
 * @var array $requests
 * @var bool|string $is_exhausted
 */

use LLAR\Core\Helpers;

$chart_cloud_label = '';
$chart_cloud_labels = array();
$chart_cloud_datasets = array();
$chart_cloud_requests_scale_max = 0;
$chart_cloud_attempts_scale_max = 0;

$chart_cloud_color_attempts = '#58C3FF';
$chart_cloud_color_gradient_attempts = '#58C3FF4D';
$chart_cloud_color_requests = '#AEAEAEB2';
$chart_cloud_color_gradient_requests = '#AEAEAE33';

$stats_dates = array();
$stats_values = array();
$date_format = trim( get_option( 'date_format' ), ' yY,._:;-/\\' );
$date_format = str_replace( 'F', 'M', $date_format );

$attempts_dataset = array(
	'label'             => __( 'Failed Login Attempts', 'limit-login-attempts-reloaded' ),
	'data'              => array(),
	'backgroundColor'   => $chart_cloud_color_gradient_attempts,
	'borderColor'       => $chart_cloud_color_attempts,
	'fill'              => true,
);

$requests_dataset = array(
	'label'             => __( 'Requests', 'limit-login-attempts-reloaded' ),
	'data'              => array(),
	'backgroundColor'   => $chart_cloud_color_gradient_requests,
	'borderColor'       => $chart_cloud_color_requests,
	'fill'              => true,
	'yAxisID'           => 'requests-cloud',
);

if ( $api_stats ) {

	if ( !empty( $api_stats['attempts'] ) ) {

		foreach ( $api_stats['attempts']['at'] as $timestamp ) {

			$stats_dates[] = date( $date_format, $timestamp );
		}

		$chart_cloud_labels = $stats_dates;
		$attempts_dataset['data'] = $api_stats['attempts']['count'];
	}

	if ( !empty( $api_stats['requests'] ) ) {

		$requests_dataset['data'] = $api_stats['requests']['count'];
	}

	if ( !empty( $api_stats['attempts_y'] ) )
		$chart_cloud_attempts_scale_max = (int) $api_stats['attempts_y'];

	if ( !empty( $api_stats['requests_y'] ) )
		$chart_cloud_requests_scale_max = (int) $api_stats['requests_y'];
}

$chart_cloud_datasets[] = $attempts_dataset;
$chart_cloud_datasets[] = $requests_dataset;
?>

<div class="section-title__new section-title__new--cloud">
    <div class="llar-label-group llar-label-group--cloud">
        <span class="llar-label llar-label--cloud">
            <span class="llar-label__circle-blue">&bull;</span>
            <?php _e( 'Failed Login Attempts', 'limit-login-attempts-reloaded' ); ?>
            <span class="hint_tooltip-parent">
                <span class="dashicons dashicons-editor-help"></span>
                <div class="hint_tooltip">
                    <div class="hint_tooltip-content">
                        <?php esc_attr_e( 'An IP that hasn\'t been previously denied by the cloud app, but has made an unsuccessful login attempt on your website.', 'limit-login-attempts-reloaded' ); ?>
                    </div>
                </div>
            </span>
        </span>
        <span class="llar-label llar-label--cloud">
            <span class="llar-label__circle-grey">&bull;</span>
                <?php _e( 'Requests', 'limit-login-attempts-reloaded' ); ?>
            <span class="hint_tooltip-parent">
                <span class="dashicons dashicons-editor-help"></span>
                <div class="hint_tooltip">
                    <div class="hint_tooltip-content">
                        <?php esc_attr_e( 'A request is utilized when the cloud validates whether an IP address is allowed to attempt a login, which also includes denied logins.', 'limit-login-attempts-reloaded' ); ?>
                    </div>
                </div>
            </span>
        </span>
    </div>
    <?php if ( ( isset( $is_tab_dashboard ) && $is_tab_dashboard ) && ! $is_agency ) : ?>
     <span class="llar-label llar-label--cloud request request--cloud <?php echo  $is_exhausted  ? 'exhausted' : '' ?>">
         <?php echo ( isset( $requests['usage'], $requests['quota'] ) )
                 ? ( __( 'Monthly Usage: ', 'limit-login-attempts-reloaded' ) . $requests['usage'] . '/' . $requests['quota'] )
                 : '' ?>
     </span>
    <?php endif; ?>
</div>
<div class="section-content section-content--cloud">
    <div class="llar-chart-wrap llar-chart-wrap--cloud">
        <canvas id="llar-api-requests-chart-cloud" style=""></canvas>
    </div>
</div>

<script type="text/javascript">
    (function(){

        var ctx = document.getElementById('llar-api-requests-chart-cloud').getContext('2d');

        var llar_stat_chart_cloud = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode( $chart_cloud_labels ); ?>,
                datasets: <?php echo json_encode($chart_cloud_datasets); ?>,
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
                            display: true,
                            text: '<?php echo esc_js( __( 'Attempts', 'limit-login-attempts-reloaded' ) ); ?>',
                        },
                        beginAtZero: true,
                        position: 'left',
                        suggestedMax: <?php echo esc_js( $chart_cloud_attempts_scale_max ); ?>,
                        ticks: {
                            callback: function(label) {
                                if (Math.floor(label) === label) {
                                    return label.toLocaleString('<?php echo esc_js( Helpers::wp_locale() ); ?>');
                                }
                            },
                        }
                    },
                    'requests-cloud': {
                        display: true,
                        title: {
                            display: true,
                            text: '<?php echo esc_js( __( 'Requests', 'limit-login-attempts-reloaded' ) ); ?>',
                        },
                        position: 'right',
                        beginAtZero: true,
                        suggestedMax: <?php echo esc_js( $chart_cloud_requests_scale_max ); ?>,
                        scaleLabel: {
                            display: false
                        },
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
