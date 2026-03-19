<?php
/**
 * Single template for all admin notices on the LLAR options page.
 *
 * @var string $notice_type    WordPress notice type class (e.g. 'notice-error', 'notice-warning').
 * @var string $notice_class   Plugin-specific class (e.g. 'llar-options-notice').
 * @var string $notice_content HTML content for inside the notice (inside <p>).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="notice <?php echo esc_attr( $notice_type ); ?> <?php echo esc_attr( $notice_class ); ?>">
	<p>
		<?php echo wp_kses_post( $notice_content ); ?>
	</p>
</div>
