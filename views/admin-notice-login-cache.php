<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$issue = isset( $args['issue'] ) && is_array( $args['issue'] ) ? $args['issue'] : array();
if ( empty( $issue['status'] ) ) {
	return;
}

$is_cached   = 'cached' === $issue['status'];
$age_minutes = isset( $issue['age'] ) ? (int) round( (int) $issue['age'] / 60 ) : 0;
$checked_url = isset( $issue['url'] ) ? (string) $issue['url'] : '';
?>
<div class="notice notice-warning is-dismissible llar-options-notice llar-login-cache-notice">
	<?php if ( $is_cached ) : ?>
		<p>
			<strong><?php esc_html_e( 'Limit Login Attempts Reloaded:', 'limit-login-attempts-reloaded' ); ?></strong>
			<?php
			echo wp_kses_post(
				sprintf(
				// translators: %d: age of the cached login page copy, in minutes.
					__( 'Your login page appears to be served from a page cache (cached copy is about %d minutes old). A cached login page can break the email code (MFA) login flow with the "session expired" error. Please exclude your login URL from page caching (for example, WP Engine Evercache, Perfmatters or other caching solutions). If the page was simply opened a long time ago, no action is needed.', 'limit-login-attempts-reloaded' ),
					$age_minutes
				)
			);
			?>
			<?php if ( '' !== $checked_url ) : ?>
				<br /><code><?php echo esc_html( $checked_url ); ?></code>
			<?php endif; ?>
		</p>
	<?php else : ?>
		<p>
			<strong><?php esc_html_e( 'Limit Login Attempts Reloaded:', 'limit-login-attempts-reloaded' ); ?></strong>
			<?php
			echo wp_kses_post(
				__( 'The cache-detection script embedded in your login page could not be found when the page was checked. This usually means a JavaScript optimizer is delaying or removing inline scripts, which can also break the email code (MFA) login flow. Please review JavaScript optimization settings (Perfmatters "Delay JavaScript" / "Remove Unused JS", WP Rocket "Delay JS Execution", FlyingPress) and make sure they do not affect the login page.', 'limit-login-attempts-reloaded' )
			);
			?>
		</p>
	<?php endif; ?>
</div>
