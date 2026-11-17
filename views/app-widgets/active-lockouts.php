<?php
if ( !defined( 'ABSPATH' ) ) exit();
?>

<div class="llar-table-header">
    <h3 class="title_page">
        <img src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/icon-exploitation.png">
        <?php echo __( 'Active Lockouts', 'limit-login-attempts-reloaded' ); ?>
    </h3>
    <span class="right-link">
        <button class="button menu__item col button__transparent_orange llar-global-reload-btn">
            <span class="dashicons dashicons-image-rotate"></span>
            <?php _e( "Reload", 'limit-login-attempts-reloaded' ); ?></button>
    </span>
</div>

<div class="llar-preloader-wrap">
    <div class="llar-table-scroll-wrap llar-app-lockouts-infinity-scroll">
        <table class="llar-form-table llar-table-app-lockouts">
            <thead>
                <tr>
                    <th scope="col"><?php _e( "IP", 'limit-login-attempts-reloaded' ); ?></th>
                    <th scope="col"><?php _e( "Login", 'limit-login-attempts-reloaded' ); ?></th>
                    <th scope="col"><?php _e( "Count", 'limit-login-attempts-reloaded' ); ?></th>
                    <th scope="col"><?php _e( "Expires in (minutes)", 'limit-login-attempts-reloaded' ); ?></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<script type="text/javascript">
	;(function($){

		$(document).ready(function () {

			let $log_table = $('.llar-table-app-lockouts'),
			    $log_table_body = $log_table.find('tbody'),
                $preloader_wrap = $log_table.closest('.llar-preloader-wrap'),
                $log_table_empty = $log_table_body.html(),
                $infinity_box = $('.llar-app-lockouts-infinity-scroll'),
                loading_data = false,
                page_offset = '',
                page_limit = 10,
                total_loaded = 0,
                emptyLockoutsLabel = <?php echo wp_json_encode( __( 'No lockouts yet.', 'limit-login-attempts-reloaded' ) ); ?>;

            /**
             * When the API ends with empty pages + a cursor (no rows ever
             * arrived), the Presenter correctly skips empty HTML on those
             * pages. Once the list is exhausted, restore the empty-state row
             * if the table still has no data rows.
             */
            function ensureEmptyStateIfNeeded() {
                if ($log_table_body.children('tr').not('.empty-row').length > 0) {
                    return;
                }
                if ($log_table_body.children('tr.empty-row').length > 0) {
                    return;
                }
                $log_table_body.append(
                    '<tr class="empty-row"><td colspan="4" style="text-align: center">' +
                    emptyLockoutsLabel +
                    '</td></tr>'
                );
            }

            $infinity_box.on('scroll', function (){
                if (!loading_data && $infinity_box.get(0).scrollTop + $infinity_box.get(0).clientHeight >= $infinity_box.get(0).scrollHeight - 1) {
                    total_loaded = 0;
                    load_lockouts_data();
                }
            });

            $log_table.on('llar:refresh', function () {
                page_offset = '';
                total_loaded = 0;
                $log_table_body.html($log_table_empty);
                load_lockouts_data();
            });

			load_lockouts_data();

            $('.llar-global-reload-btn').on('click', function() {
                page_offset = '';
                total_loaded = 0;
                $log_table_body.html($log_table_empty);
                load_lockouts_data();
            });

			function load_lockouts_data() {

                if (page_offset === false) {
                    return;
                }

                loading_data = true;

                $preloader_wrap.addClass('loading');

				$.post(ajaxurl, {
					action: 'app_load_lockouts',
                    offset: page_offset,
                    limit: page_limit,
					sec: '<?php echo wp_create_nonce( "llar-app-load-lockouts" ); ?>'
				}, function(response){

                    $preloader_wrap.removeClass('loading');

                    if (!response.success) {

                        if (response.data.error_notice) {
                            $('.limit-login-app-dashboard').find('.llar-app-notice').remove();
                            $('.limit-login-app-dashboard').prepend(response.data.error_notice);
                        }

                        loading_data = false;
                        return;
                    }

                    $log_table_body.find('tr.empty-row').remove();
                    $log_table_body.append(response.data.html);

                    total_loaded += response.data.total_items;

                    switch (true) {

                        // No offset means the whole list has been loaded.
                        case !response.data.offset:
                            page_offset = false;
                            ensureEmptyStateIfNeeded();
                            break;

                        // A partial batch with an offset means there is more data,
                        // keep requesting until the page limit is filled up.
                        case response.data.total_items < page_limit && total_loaded < page_limit:
                            page_offset = response.data.offset;
                            load_lockouts_data();
                            return; // keep loading_data locked while the extra request is in flight

                        default:
                            page_offset = response.data.offset;
                    }

                    loading_data = false;
				});

			}
		});

	})(jQuery);
</script>