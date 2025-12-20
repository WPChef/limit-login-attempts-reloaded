<?php
/**
 * Mode Toggle Component
 *
 * @var string $active_app
 */

if ( ! defined( 'ABSPATH' ) ) exit();

if ( ! $free_requests_exhausted ) {
	return;
}
?>

<div class="llar-mode-toggle-wrapper">
	<div class="llar-mode-toggle">
		<span class="llar-mode-toggle-item active" data-mode="local" <?php echo ( 'local' !== $active_app ) ? 'style="display: none;"' : ''; ?>>
			<?php _e( 'Local Mode', 'limit-login-attempts-reloaded' ); ?>
		</span>
		<a href="#" class="llar-mode-toggle-item" data-mode="local" <?php echo ( 'local' === $active_app ) ? 'style="display: none;"' : ''; ?>>
			<?php _e( 'Local Mode', 'limit-login-attempts-reloaded' ); ?>
		</a>

		<span class="llar-mode-toggle-item active" data-mode="cloud" <?php echo ( 'custom' !== $active_app ) ? 'style="display: none;"' : ''; ?>>
			<?php _e( 'Cloud Mode', 'limit-login-attempts-reloaded' ); ?>
		</span>
		<a href="#" class="llar-mode-toggle-item" data-mode="cloud" <?php echo ( 'custom' === $active_app ) ? 'style="display: none;"' : ''; ?>>
			<?php _e( 'Cloud Mode', 'limit-login-attempts-reloaded' ); ?>
		</a>
	</div>
</div>
