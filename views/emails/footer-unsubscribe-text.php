<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="footer">
	<div class="text" style="font-size:13px;color:#6b7280;text-align:center;">
		<?php esc_html_e( 'Don\'t want these notifications?', 'limit-login-attempts-reloaded' ); ?>
		<a href="{unsubscribe_url}" target="_blank" rel="noopener" style="color:#6b7280;text-decoration:underline;"><?php esc_html_e( 'Unsubscribe', 'limit-login-attempts-reloaded' ); ?></a>
		<?php esc_html_e( 'from these notifications.', 'limit-login-attempts-reloaded' ); ?>
	</div>
</div>
