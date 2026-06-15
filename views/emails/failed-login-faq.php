<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}
?>
<h3 style="margin:0 0 8px;font-size:16px;line-height:1.5;color:#333333;">
	<?php esc_html_e( 'Frequently Asked Questions', 'limit-login-attempts-reloaded' ); ?>
</h3>
<p style="margin:0 0 14px;font-size:14px;line-height:1.5;color:#333333;">
	<strong><?php esc_html_e( 'What is a failed login attempt?', 'limit-login-attempts-reloaded' ); ?></strong><br>
	<?php esc_html_e( 'A failed login attempt is when an IP address uses incorrect credentials to log into your website. The IP address could be a human operator, or a program designed to guess your password.', 'limit-login-attempts-reloaded' ); ?>
</p>
<p style="margin:0 0 14px;font-size:14px;line-height:1.5;color:#333333;">
	<strong><?php esc_html_e( 'Why am I getting these emails?', 'limit-login-attempts-reloaded' ); ?></strong><br>
	<?php echo wp_kses( __( 'You are receiving this email because there was a failed login attempt on your website {domain}. If you\'d like to opt out of these notifications, please click the "Unsubscribe" link below.', 'limit-login-attempts-reloaded' ), array() ); ?>
</p>
<p style="margin:0 0 14px;font-size:14px;line-height:1.5;color:#333333;">
	<strong><?php esc_html_e( 'How dangerous is this failed login attempt?', 'limit-login-attempts-reloaded' ); ?></strong><br>
	<?php esc_html_e( 'Unfortunately, we cannot determine the severity of the IP address with the free version of the plugin. If the IP continues to make attempts and is not recognized by your organization, then it\'s likely to have malicious intent. Depending on how frequent the attacks are, you may experience performance issues. In the plugin dashboard, you can investigate the frequency of the failed login attempts in the logs and take additional steps to protect your website (i.e. adding them to the block list). You can visit the ', 'limit-login-attempts-reloaded' ); ?>
	<a href="{llar_url}" target="_blank" rel="noopener" style="color:#fda33b;text-decoration:underline;"><?php esc_html_e( 'Limit Login Attempts Reloaded website', 'limit-login-attempts-reloaded' ); ?></a>
	<?php esc_html_e( ' for more information on our premium services, which can automatically block and detect malicious IP addresses.', 'limit-login-attempts-reloaded' ); ?>
</p>
