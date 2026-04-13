<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="title"><?php esc_html_e( 'Failed login attempt detected', 'limit-login-attempts-reloaded' ); ?></div>
<div class="description">
	<?php _e( 'This notification was sent automatically via Limit Login Attempts Reloaded Plugin.', 'limit-login-attempts-reloaded' ); ?><br>
	<?php _e( 'This is installed on your <b>{domain}</b> WordPress site.', 'limit-login-attempts-reloaded' ); ?>
</div>
<div style="font-size:14px;line-height:1.6;color:#4b5563;">
	<p><?php _e( 'The failed login details include:', 'limit-login-attempts-reloaded' ); ?></p>
	<ul>
		<li><?php _e( '{attempts_count} failed login attempts ({lockouts_count} lockout(s)) from IP <b><a href="{ip_address_link}" target="_blank">{ip_address}</a></b>', 'limit-login-attempts-reloaded' ); ?></li>
		<li><?php _e( 'Last user attempted: <b>{username}</b>', 'limit-login-attempts-reloaded' ); ?></li>
		<li><?php _e( 'IP was blocked for {blocked_duration}', 'limit-login-attempts-reloaded' ); ?></li>
		<li><?php _e( 'Login address: <b><a href="{current_url}" target="_blank">{current_url_label}</a></b>', 'limit-login-attempts-reloaded' ); ?></li>
	</ul>
	<p><a href="{dashboard_url}" target="_blank"><?php _e( 'Go to Dashboard', 'limit-login-attempts-reloaded' ); ?></a></p>
	<p><a href="{unsubscribe_url}" target="_blank"><?php _e( 'Unsubscribe', 'limit-login-attempts-reloaded' ); ?></a></p>
</div>
<?php
use LLAR\Core\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}
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
<?php if ( Helpers::is_mu() ) : ?>
<p style="margin:0 0 12px;font-size:13px;line-height:1.5;color:#4b5563;">
	<em><?php esc_html_e( 'This alert was sent by your website where Limit Login Attempts Reloaded free version is installed and you are listed as the admin.', 'limit-login-attempts-reloaded' ); ?></em>
</p>
<?php endif; ?>
<p style="margin:0;font-size:14px;line-height:1.5;color:#333333;">
	<a href="{unsubscribe_url}" target="_blank" rel="noopener"><?php esc_html_e( 'Unsubscribe', 'limit-login-attempts-reloaded' ); ?></a>
	<?php esc_html_e( 'from these notifications.', 'limit-login-attempts-reloaded' ); ?>
</p>
