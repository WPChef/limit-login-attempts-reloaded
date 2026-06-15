<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}
?>
<strong><?php esc_html_e( 'What you should do next', 'limit-login-attempts-reloaded' ); ?></strong>
<p><?php esc_html_e( 'If you recognize an IP address or see repeated attempts against a specific username, open your dashboard to review the IP details and adjust allowlist or denylist rules if needed.', 'limit-login-attempts-reloaded' ); ?></p>
<p><strong><?php esc_html_e( 'Noticing consistent attack patterns?', 'limit-login-attempts-reloaded' ); ?></strong><br>
	<a href="https://www.limitloginattempts.com"><?php esc_html_e( 'Premium', 'limit-login-attempts-reloaded' ); ?></a> <?php esc_html_e( 'gives you deeper visibility and stronger protection with advanced IP intelligence, block by country, detailed login logs and monitoring, and automatic malicious IP detection.', 'limit-login-attempts-reloaded' ); ?>
</p>
<?php include LLA_PLUGIN_DIR . 'views/emails/footer-unsubscribe-text.php'; ?>
