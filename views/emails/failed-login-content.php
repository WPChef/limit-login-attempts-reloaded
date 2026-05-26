<?php
use LLAR\Core\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

$admin_name = isset( $admin_name ) && is_string( $admin_name ) ? $admin_name : '';
?>
<p style="margin:0 0 14px;font-size:14px;line-height:1.5;color:#333333;">
	<?php ( ! empty( $admin_name ) ) ? esc_html_e( 'Hello {name},', 'limit-login-attempts-reloaded' ) : esc_html_e( 'Hello,', 'limit-login-attempts-reloaded' ); ?>
</p>
<p style="margin:0 0 10px;font-size:14px;line-height:1.5;color:#333333;">
	<?php esc_html_e( 'This notification was sent automatically via Limit Login Attempts Reloaded Plugin.', 'limit-login-attempts-reloaded' ); ?>
</p>
<p style="margin:0 0 14px;font-size:14px;line-height:1.5;color:#333333;">
	<?php echo wp_kses( __( 'This is installed on your <strong>{domain}</strong> WordPress site.', 'limit-login-attempts-reloaded' ), array( 'strong' => array() ) ); ?>
</p>
<p style="margin:0 0 8px;font-size:14px;line-height:1.5;color:#333333;">
	<?php esc_html_e( 'The failed login details include:', 'limit-login-attempts-reloaded' ); ?>
</p>
<ul style="margin:0 0 16px;padding-left:18px;font-size:14px;line-height:1.5;color:#333333;">
	<li style="margin-bottom:8px;">
		<?php esc_html_e( '{attempts_count} failed login attempts ({lockouts_count} lockout(s)) from IP', 'limit-login-attempts-reloaded' ); ?>
		<strong><a href="{ip_address_link}" target="_blank" rel="noopener">{ip_address}</a></strong>
	</li>
	<li style="margin-bottom:8px;"><?php echo wp_kses( __( 'Last user attempted: <strong>{username}</strong>', 'limit-login-attempts-reloaded' ), array( 'strong' => array() ) ); ?></li>
	<li style="margin-bottom:8px;"><?php esc_html_e( 'IP was blocked for {blocked_duration}', 'limit-login-attempts-reloaded' ); ?></li>
	<li style="margin-bottom:8px;"><?php echo wp_kses( __( 'Login address: <strong><a href="{current_url}" target="_blank" rel="noopener">{current_url_label}</a></strong>', 'limit-login-attempts-reloaded' ), array( 'strong' => array(), 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) ) ); ?></li>
</ul>
<p style="margin:0 0 16px;font-size:14px;line-height:1.5;color:#333333;">
	<?php esc_html_e( 'Please visit your WordPress dashboard for additional details, investigation options, and help articles.', 'limit-login-attempts-reloaded' ); ?>
</p>
<p style="margin:0 0 16px;font-size:14px;line-height:1.5;color:#333333;text-align:center;">
	<a href="{dashboard_url}" target="_blank" rel="noopener" style="display:inline-block;background:#50c1cd;color:#ffffff;border-radius:30px;padding:10px 20px;text-decoration:none;">
		<?php esc_html_e( 'Go to Dashboard', 'limit-login-attempts-reloaded' ); ?>
	</a>
</p>
<p style="margin:0 0 12px;font-size:14px;line-height:1.5;color:#333333;">
	<?php esc_html_e( 'Experiencing frequent attacks or degraded performance?', 'limit-login-attempts-reloaded' ); ?>
	<a href="{premium_url}" target="_blank" rel="noopener"><?php esc_html_e( 'Try Micro Cloud.', 'limit-login-attempts-reloaded' ); ?></a>
</p>
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
<?php if ( Helpers::is_mu() ) : ?>
<p style="margin:0 0 12px;font-size:13px;line-height:1.5;color:#4b5563;">
	<em><?php esc_html_e( 'This alert was sent by your website where Limit Login Attempts Reloaded free version is installed and you are listed as the admin.', 'limit-login-attempts-reloaded' ); ?></em>
</p>
<?php endif; ?>
<p style="margin:0;font-size:14px;line-height:1.5;color:#333333;">
	<a href="{unsubscribe_url}" target="_blank" rel="noopener"><?php esc_html_e( 'Unsubscribe', 'limit-login-attempts-reloaded' ); ?></a>
	<?php esc_html_e( 'from these notifications.', 'limit-login-attempts-reloaded' ); ?>
</p>
