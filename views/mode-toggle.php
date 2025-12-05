<?php
/**
 * Mode Toggle Component
 *
 * @var string $active_app
 */

if ( ! defined( 'ABSPATH' ) ) exit();
if ( !$free_requests_exhausted ) {
    return;
}
?>

<div class="llar-mode-toggle-wrapper">
    <div class="llar-mode-toggle">
        <span class="llar-mode-toggle-item<?php echo ( $active_app === 'local' ) ? ' active' : ''; ?>">
            <?php _e( 'Local Mode', 'limit-login-attempts-reloaded' ); ?>
        </span>
        <span class="llar-mode-toggle-item<?php echo ( $active_app === 'custom' ) ? ' active' : ''; ?>">
            <?php _e( 'Cloud Mode', 'limit-login-attempts-reloaded' ); ?>
        </span>
    </div>
</div>

