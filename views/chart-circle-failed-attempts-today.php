<?php
/**
 * Chart circle failed attempts today
 *
 * @var string $active_app
 * @var string $setup_code
 * @var string $is_active_app_custom
 * @var bool|mixed $api_stats
 * @var bool|string $is_exhausted
 * @var string $block_sub_group
 * @var string $upgrade_premium_url
 *
 */

use LLAR\Core\Helpers;

if ( empty( $chart_circle_data ) || ! is_array( $chart_circle_data ) ) {
	return;
}

$retries_chart_title = isset( $chart_circle_data['retries_chart_title'] ) ? $chart_circle_data['retries_chart_title'] : '';
$retries_chart_desc  = isset( $chart_circle_data['retries_chart_desc'] ) ? $chart_circle_data['retries_chart_desc'] : '';
$retries_chart_color = isset( $chart_circle_data['retries_chart_color'] ) ? $chart_circle_data['retries_chart_color'] : '#97F6C8';
$retries_count       = isset( $chart_circle_data['retries_count'] ) ? (int) $chart_circle_data['retries_count'] : 0;
?>

<div class="section-title__new">
	<?php if ( isset( $is_tab_dashboard ) && $is_tab_dashboard ) : ?>
        <span class="llar-label">
            <?php _e( 'Failed Login Attempts', 'limit-login-attempts-reloaded' ); ?>
            <span class="hint_tooltip-parent">
                <span class="dashicons dashicons-editor-help"></span>
                <div class="hint_tooltip">
                    <div class="hint_tooltip-content">
                        <?php $is_active_app_custom
	                        ? esc_attr_e( 'An IP that hasn\'t been previously denied by the cloud app, but has made an unsuccessful login attempt on your website.', 'limit-login-attempts-reloaded' )
	                       : esc_attr_e( 'An IP that has made an unsuccessful login attempt on your website.', 'limit-login-attempts-reloaded' );
                        ?>
                    </div>
                </div>
            </span>
        </span>
	<?php else : ?>
        <span class="llar-label__url">
        </span>
	<?php endif; ?>
	<?php echo ( $is_active_app_custom && ! $is_exhausted )
		? '<span class="llar-premium-label"><span class="dashicons dashicons-saved"></span>' . __( 'Cloud protection enabled', 'limit-login-attempts-reloaded' ) . '</span>'
		: ''; ?>
</div>
<div class="section-content">
    <div class="chart">
        <div class="doughnut-chart-wrap">
            <canvas id="llar-attack-velocity-chart"></canvas>
        </div>
        <span class="llar-retries-count"><?php echo esc_html( Helpers::short_number( $retries_count ) ); ?></span>
    </div>
</div>

<script type="text/javascript">
    ( function() {

        var ctx = document.getElementById( 'llar-attack-velocity-chart' ).getContext( '2d' );

        // Add a shadow on the graph
        let shadow_fill = ctx.fill;
        ctx.fill = function () {
            ctx.save();
            ctx.shadowColor = '<?php echo esc_js( $retries_chart_color ) ?>';
            ctx.shadowBlur = 10;
            ctx.shadowOffsetX = 0;
            ctx.shadowOffsetY = 3;
            shadow_fill.apply( this, arguments )
            ctx.restore();
        };

        let llar_retries_chart = new Chart( ctx, {
            type: 'doughnut',
            data: {
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
        } );

    } )();
</script>
<div class="title<?php echo $active_app !== 'local' ? ' title-big' : ''?>"><?php echo esc_html( $retries_chart_title ); ?></div>
<div class="desc"><?php echo wp_kses_post( $retries_chart_desc ); ?></div>

