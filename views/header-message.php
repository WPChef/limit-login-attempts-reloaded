<?php

use LLAR\Core\Config;

if ( ! defined( 'ABSPATH' ) ) {
    exit();
}
?>

<div class="header_massage">
    <?php
    if ( $is_active_app_custom && $block_sub_group === 'Micro Cloud' ) :

	$notifications_message_shown = (int) Config::get( 'notifications_message_shown' );
	$upgrade_premium_url = $this->info_upgrade_url();

    if ( $is_exhausted ) :

        if ( time() > $notifications_message_shown ) : ?>
            <div id="llar-header-upgrade-premium-message" class="exhausted">
                <p>
                    <span class="dashicons dashicons-superhero"></span>
                    <?php
					echo sprintf(
                        __( 'You have exhausted your monthly quota of free Micro Cloud requests. The plugin has now reverted to the free version. <a href="%s" class="link__style_color_inherit" target="_blank">Upgrade to the premium</a> version today to maintain cloud protection and advanced features.', 'limit-login-attempts-reloaded' ),
                        str_replace('id=0', 'id=4', $upgrade_premium_url) );
                    ?>
                </p>
                <div class="close">
                    <span class="dashicons dashicons-no-alt"></span>
                </div>
            </div>
        <?php endif; ?>

    <?php else : ?>
        <div id="llar-header-upgrade-mc-message">
            <p>
                <span class="dashicons dashicons-superhero"></span>
				<?php
				echo sprintf(
					__( 'Enjoying Micro Cloud? To prevent interruption of the cloud app, <a href="%s" class="link__style_color_inherit" target="_blank">Upgrade to Premium</a> today', 'limit-login-attempts-reloaded' ),
					str_replace('id=0', 'id=4', $upgrade_premium_url) );
				?>
            </p>
        </div>

        <?php endif; ?>

    <?php endif; ?>
</div>
