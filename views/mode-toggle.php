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
		<?php if ( 'local' === $active_app ) : ?>
			<span class="llar-mode-toggle-item active">
				<?php _e( 'Local Mode', 'limit-login-attempts-reloaded' ); ?>
			</span>
		<?php else : ?>
			<a href="#" class="llar-mode-toggle-item">
				<?php _e( 'Local Mode', 'limit-login-attempts-reloaded' ); ?>
			</a>
		<?php endif; ?>

		<?php if ( 'custom' === $active_app ) : ?>
			<span class="llar-mode-toggle-item active">
				<?php _e( 'Cloud Mode', 'limit-login-attempts-reloaded' ); ?>
			</span>
		<?php else : ?>
			<a href="#" class="llar-mode-toggle-item">
				<?php _e( 'Cloud Mode', 'limit-login-attempts-reloaded' ); ?>
			</a>
		<?php endif; ?>
	</div>
</div>
