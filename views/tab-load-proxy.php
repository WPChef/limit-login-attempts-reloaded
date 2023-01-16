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
    <p><?php echo __( 'Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut ' .
	                  'labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco ' .
	                  'laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in ' .
	                  'voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat ' .
	                  'non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.', 'limit-login-attempts-reloaded' ); ?></p>

    <h4><?php echo __( 'Compatibility Check', 'limit-login-attempts-reloaded' ); ?></h4>
    <div class="load-proxy-checklist">
        <ul>
            <li><span class="dashicons dashicons-<?php echo ( $checklist['wp_config_writable'] ) ? 'yes' : 'no'; ?>"></span> wp-config.php file is writable</li>
            <li><span class="dashicons dashicons-<?php echo ( $checklist['proxy_file_writable'] ) ? 'yes' : 'no'; ?>"></span> wp-content/<?php echo AdvancedServerLoadCutting::PROXY_FILE_NAME; ?> file is writable</li>
            <li><span class="dashicons dashicons-<?php echo ( $checklist['curl_available'] ) ? 'yes' : 'no'; ?>"></span> CURL Available</li>
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

        <div class="manual-installation">
            <h3><?php echo __( 'Manual Installation', 'limit-login-attempts-reloaded' ); ?></h3>
            <a href="#" class="toggle-btn"><?php echo __( 'Show', 'limit-login-attempts-reloaded' ); ?></a><br>
            <div class="textarea-wrap">
                <textarea readonly style="width: 100%;max-width: 800px;" rows="5"><?php echo AdvancedServerLoadCutting::generate_proxy_file_content(); ?></textarea>
                <p><?php echo __( 'Instruction', 'limit-login-attempts-reloaded' ); ?></p>
            </div>
        </div>

        <p class="submit">
            <input class="button button-primary"
                <?php disabled( !AdvancedServerLoadCutting::is_checks_passed() ); ?>
                name="llar_load_proxy_save" value="<?php echo __( 'Save', 'limit-login-attempts-reloaded' ); ?>"
                type="submit"/>
        </p>
    </form>
</div>

<script>
    ;(function($) {

        const $manual_installation = $('.manual-installation');

        $(document).ready(function(){
            $manual_installation.on('click', '.toggle-btn', function() {
                $manual_installation.find('.textarea-wrap').toggleClass('active');
            });
        });
    })(jQuery);
</script>
