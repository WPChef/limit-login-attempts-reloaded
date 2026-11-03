<?php
/**
 * Successful login attempts widget.
 *
 * All copy and state comes from the controller via get_login_attempts_widget_view_vars().
 *
 * @var bool $is_tab_dashboard
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

$widget = $this->get_login_attempts_widget_view_vars( isset( $is_tab_dashboard ) && $is_tab_dashboard );
?>

<?php if ( $widget['is_tab_dashboard'] ) : ?>

    <div class="section-title__new">
        <div class="title">
			<?php echo $widget['title']; ?>
        </div>
        <div class="view">
            <a class="link__style_unlink llar_turquoise" href="<?php echo $widget['view_more_url']; ?>">
		        <?php echo $widget['view_more_label']; ?>
            </a>
        </div>
    </div>

<?php else : ?>

    <div class="llar-table-header">
        <h3 class="title_page">
            <img src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/icon-help.png">
	        <?php echo $widget['title']; ?>
        </h3>
    </div>

<?php endif ?>

<div class="section-content">
    <div class="llar-table-scroll-wrap llar-app-login-infinity-scroll">
        <table class="llar-form-table llar-table-app-login">
            <thead>
                <tr>
                    <?php foreach ( $widget['headers'] as $header ) : ?>
                    <th scope="col"><?php echo $header; ?></th>
                    <?php endforeach ?>
                </tr>
            </thead>
            <tbody class="login-attempts"></tbody>
            <?php if ( ! $widget['is_tab_dashboard'] ) : ?>
                <tfoot class="table-inline-preloader">
                    <tr>
                        <td colspan="100%">
                            <div class="load-more-button"><a href="#">
                                    <?php echo $widget['load_more_label']; ?>
                                </a>
                            </div>
                            <div class="preloader-row">
                                <span class="preloader-icon"></span>
                            </div>
                        </td>
                    </tr>
                </tfoot>
            <?php endif ?>
        </table>
    </div>
</div>

<?php if ( ! $widget['is_active_app_custom'] ) : ?>

    <?php $app_custom = 'false'; ?>

    <div class="llar-blur-block">
        <div class="llar-blur-block-text">
            <img src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/icon-block.png">
            <div class="title">
                <?php echo $widget['blur_title']; ?>
            </div>
            <div class="description">
	            <?php echo $widget['blur_description']; ?>
            </div>
            <div class="footer">
	            <?php echo $widget['blur_footer']; ?>
            </div>
        </div>
    </div>
<?php else : ?>

    <?php $app_custom = 'true'; ?>
<?php endif; ?>


<script type="text/javascript">
    ;(function($){

        $(document).ready(function () {

            let $log_table_body = $('.llar-table-app-login tbody'),
                $preloader = $log_table_body.next('.table-inline-preloader'),
                $load_more_btn = $preloader.find('.load-more-button a'),
                login_button_open = '.llar-add-login-open',
                loading_data = false,
                page_offset = '',
                page_limit = '<?php echo esc_js( $widget['limit'] ) ?>',
                total_loaded = 0;

            load_login_data();

            $load_more_btn.on('click', function(e) {
                e.preventDefault();
                total_loaded = 0;
                load_login_data();
            });

            $log_table_body.on('click', login_button_open, function() {

                const dashicons = $( this ).find( '.dashicons' ),
                    dashicons_down = 'dashicons-arrow-down-alt2',
                    dashicons_up = 'dashicons-arrow-up-alt2',
                    parent_tr = $( this ).closest( 'tr' ),
                    hidden_row = parent_tr.next();

                if ( hidden_row.hasClass( 'table-row-open' ) ) {

                    dashicons.removeClass( dashicons_up );
                    dashicons.addClass( dashicons_down );
                    hidden_row.removeClass( 'table-row-open' );
                } else {

                    let iframe = hidden_row.find( '.open_street_map' );

                    if ( ! iframe.hasClass('activated') ) {

                        let latitude = iframe.data( 'latitude' ),
                            longitude = iframe.data( 'longitude' ),
                            height_hidden_row = hidden_row.height(),
                            scc_link = "https://www.openstreetmap.org/export/embed.html?bbox=" +
                                ( longitude * 0.8 ) + "%2C" + ( latitude * 0.8 ) + "%2C" +
                                ( longitude * 1.2 ) + "%2C" + ( latitude * 1.2 ) +
                                "&layer=mapnik&marker=" + latitude + "%2C" + longitude;

                        iframe.attr( 'height', height_hidden_row - 40);
                        iframe.attr( 'src', scc_link );
                        iframe.addClass('activated');
                    }

                    dashicons.removeClass( dashicons_down );
                    dashicons.addClass( dashicons_up );
                    hidden_row.addClass( 'table-row-open' );
                }
            })


            function load_login_data() {

                if ( page_offset === false ) {
                    return;
                }

                $preloader.addClass('loading');
                loading_data = true;

                $.post(ajaxurl, {
                    action:         'app_load_successful_login',
                    offset:         page_offset,
                    limit:          page_limit,
                    custom:         '<?php echo esc_js( $app_custom ); ?>',
                    url_premium:    '<?php echo esc_js( $widget['upgrade_premium_url'] ); ?>',
                    sec:            '<?php echo wp_create_nonce( "llar-app-load-login" ); ?>'
                }, function(response){

                    $preloader.removeClass('loading');

                    if(response.success) {

                        if(response.data.html) {
                            $log_table_body.append(response.data.html);
                        }

                        total_loaded += response.data.total_items;

                        if(response.data.offset) {
                            page_offset = response.data.offset;

                            if(response.data.total_items < page_limit && total_loaded < page_limit) {

                                load_login_data();
                            }

                        } else {
                            $preloader.addClass('hidden');
                            page_offset = false;
                        }

                        loading_data = false;
                    }
                });
            }

        });

    })(jQuery);
</script>