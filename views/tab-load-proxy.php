<?php

use LLAR\Core\AdvancedServerLoadCutting;
use LLAR\Core\Config;

if( !defined( 'ABSPATH' ) ) exit();

/**
 * @var $this LLAR\Core\LimitLoginAttempts
 */

$checklist = AdvancedServerLoadCutting::compatibility_checks();
?>
<div class="load-proxy-page">
    <h3><?php echo __( 'Load Proxy', 'limit-login-attempts-reloaded' ); ?></h3>
    <p><?php echo __( 'Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.', 'limit-login-attempts-reloaded' ); ?></p>

    <h4><?php echo __( 'Compatibility Check', 'limit-login-attempts-reloaded' ); ?></h4>
    <div class="load-proxy-checklist">
        <ul>
            <li><span class="dashicons dashicons-<?php echo ( $checklist['root_folder_writable'] ) ? 'yes' : 'no'; ?>"></span> <?php _e( 'root folder is writable', 'limit-login-attempts-reloaded' ); ?></li>
            <li><span class="dashicons dashicons-<?php echo ( $checklist['wp_config_writable'] ) ? 'yes' : 'no'; ?>"></span> <?php _e( 'wp-config.php file is writable', 'limit-login-attempts-reloaded' ); ?></li>
            <li><span class="dashicons dashicons-<?php echo ( $checklist['proxy_file_writable'] ) ? 'yes' : 'no'; ?>"></span> <?php echo sprintf( __( 'wp-content/%s file is writable', 'limit-login-attempts-reloaded' ), AdvancedServerLoadCutting::PROXY_FILE_NAME ); ?></li>
            <?php if( $checklist['fopen_available'] ) : ?>
            <li><span class="dashicons dashicons-yes"></span> <?php _e( 'fopen (allow_url_fopen == true) is available', 'limit-login-attempts-reloaded' ); ?></li>
	        <?php elseif( $checklist['curl_available'] ) : ?>
            <li><span class="dashicons dashicons-yes"></span> <?php _e( 'CURL is available', 'limit-login-attempts-reloaded' ); ?></li>
            <?php else: ?>
            <li><span class="dashicons dashicons-no"></span> <?php _e( 'fopen (allow_url_fopen == true) or CURL is available', 'limit-login-attempts-reloaded' ); ?></li>
            <?php endif; ?>
        </ul>
    </div>

    <form action="<?php echo $this->get_options_page_uri('load-proxy'); ?>" method="post">

		<?php wp_nonce_field( 'limit-login-attempts-options' ); ?>


        <table class="form-table">
            <tr>
                <th scope="row" valign="top"><?php echo __( 'Enable Load Proxy', 'limit-login-attempts-reloaded' ); ?></th>
                <td>
                    <input type="checkbox" name="load_proxy_enabled" value="1"
                        <?php disabled( !AdvancedServerLoadCutting::is_checks_passed() ); ?>
                        <?php checked( Config::get( 'load_proxy_enabled' ) ); ?>
                    />
                </td>
            </tr>
        </table>

        <p class="submit">
            <input class="button button-primary"
			    <?php disabled( !AdvancedServerLoadCutting::is_checks_passed() ); ?>
                   name="llar_load_proxy_save" value="<?php echo __( 'Save', 'limit-login-attempts-reloaded' ); ?>"
                   type="submit"/>
        </p>

        <div class="manual-installation">
            <h3><?php _e( 'Manual Installation', 'limit-login-attempts-reloaded' ); ?> <a href="#" class="toggle-btn"><?php echo __( 'Show', 'limit-login-attempts-reloaded' ); ?></a></h3>
            <div class="steps-wrap">
                <div class="step">
                    <h2><?php _e( 'Step 1', 'limit-login-attempts-reloaded' ); ?></h2>
                    <textarea readonly style="width: 100%;" rows="6"><?php echo AdvancedServerLoadCutting::generate_proxy_file_content(); ?></textarea>
                    <p><?php _e( 'Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.', 'limit-login-attempts-reloaded' ); ?></p>
                </div>
                <div class="step">
                    <h2><?php _e( 'Step 2', 'limit-login-attempts-reloaded' ); ?></h2>
                    <textarea readonly style="width: 100%;" rows="2"><?php echo AdvancedServerLoadCutting::get_include_file_line(); ?></textarea>
                    <p><?php _e( 'Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.', 'limit-login-attempts-reloaded' ); ?></p>
                </div>
            </div>
        </div>

    </form>
</div>

<script>
    ;(function($) {

        const $manual_installation = $('.manual-installation');

        $(document).ready(function(){
            $manual_installation.on('click', '.toggle-btn', function() {
                const $this = $(this),
                      $steps_wrap = $manual_installation.find('.steps-wrap');

                $steps_wrap.toggleClass('active');
                if($steps_wrap.hasClass('active')) {
                    $this.text('Hide');
                } else {
                    $this.text('Show');
                }
            });
        });
    })(jQuery);
</script>
