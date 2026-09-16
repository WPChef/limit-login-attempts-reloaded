<?php
/**
 * Single template for all admin notices on the LLAR options page.
 *
 * @var string $notice_type    WordPress notice type class (e.g. 'notice-error', 'notice-warning').
 * @var string $notice_class   Plugin-specific class (e.g. 'llar-options-notice').
 * @var string $notice_content HTML content for inside the notice (inside <p>).
 * @var bool   $notice_raw     Whether $notice_content is fully rendered trusted view output
 *                             (own wrapper markup, inline JS) that must bypass wp_kses_post().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! empty( $notice_raw ) ) {
	/*
	 * Trusted view output. wp_kses_post() strips <script> tags while keeping their
	 * body, which prints the JS as plain text and breaks the notice JS handlers.
	 */
	echo $notice_content; // phpcs:ignore WordPress.Security.EscapeOutput -- trusted plugin view output with inline script.
	return;
}
?>
<div class="notice <?php echo esc_attr( $notice_type ); ?> <?php echo esc_attr( $notice_class ); ?>">
	<p>
		<?php echo wp_kses_post( $notice_content ); ?>
	</p>
</div>
