<?php
/**
 * MFA verification email content block.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="title" style="font-size:22px;font-weight:700;color:#111827;margin:0 0 8px;"><?php esc_html_e( 'Verify your login', 'limit-login-attempts-reloaded' ); ?></div>
<div class="description" style="font-size:14px;line-height:1.5;color:#4b5563;margin:0 0 20px;">
	<?php esc_html_e( 'We received a login attempt to your WordPress site and need to confirm it is you.', 'limit-login-attempts-reloaded' ); ?>
	<?php esc_html_e( 'Enter the verification code below to complete your login.', 'limit-login-attempts-reloaded' ); ?>
</div>
<div class="code-box" style="margin:0 0 20px;border-radius:14px;background-color:#f9fafb;border:1px solid #e5e7eb;text-align:center;">
	<div class="code-value" style="font-size:32px;letter-spacing:6px;font-weight:700;color:#111827;margin:13px 0 8px;"><?php echo esc_html( $code_safe ); ?></div>
	<div class="code-meta" style="font-size:12px;color:#6b7280;text-align:center;width:80%;margin:0 auto 20px;">
		<?php
		echo esc_html(
			sprintf(
				/* translators: %1$d: minutes until the code expires (WP); %2$d: session expiry minutes (cloud). */
				__( 'This code will expire in %1$d minutes. Your verification session expires %2$d minutes after the login attempt.', 'limit-login-attempts-reloaded' ),
				$code_ttl_minutes,
				30
			)
		);
		?>
	</div>
</div>
<div class="section" style="margin-bottom:20px;">
	<div class="section-title" style="font-size:13px;font-weight:600;color:#111827;margin:0 0 8px;"><?php esc_html_e( 'Login attempt from:', 'limit-login-attempts-reloaded' ); ?></div>
	<table class="meta-table" role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;border-collapse:collapse;font-size:13px;color:#4b5563;line-height:1.45;">
		<tr>
			<td class="meta-label" valign="middle" style="font-weight:500;color:#111827;vertical-align:middle;padding:6px 16px 6px 0;white-space:nowrap;"><?php esc_html_e( 'Site:', 'limit-login-attempts-reloaded' ); ?></td>
			<td class="meta-value" valign="middle" style="vertical-align:middle;text-align:right;width:99%;word-break:break-word;padding:6px 0;"><?php echo esc_html( $site_domain_safe ); ?></td>
		</tr>
		<tr>
			<td class="meta-label" valign="middle" style="font-weight:500;color:#111827;vertical-align:middle;padding:6px 16px 6px 0;white-space:nowrap;"><?php esc_html_e( 'IP Address:', 'limit-login-attempts-reloaded' ); ?></td>
			<td class="meta-value" valign="middle" style="vertical-align:middle;text-align:right;width:99%;word-break:break-word;padding:6px 0;"><?php echo esc_html( $ip_safe ); ?></td>
		</tr>
		<tr>
			<td class="meta-label" valign="middle" style="font-weight:500;color:#111827;vertical-align:middle;padding:6px 16px 6px 0;white-space:nowrap;"><?php esc_html_e( 'Location:', 'limit-login-attempts-reloaded' ); ?></td>
			<td class="meta-value" valign="middle" style="vertical-align:middle;text-align:right;width:99%;word-break:break-word;padding:6px 0;"><?php echo esc_html( $location_safe ); ?></td>
		</tr>
		<tr>
			<td class="meta-label" valign="middle" style="font-weight:500;color:#111827;vertical-align:middle;padding:6px 16px 6px 0;white-space:nowrap;"><?php esc_html_e( 'Browser:', 'limit-login-attempts-reloaded' ); ?></td>
			<td class="meta-value" valign="middle" style="vertical-align:middle;text-align:right;width:99%;word-break:break-word;padding:6px 0;"><?php echo esc_html( $browser_safe ); ?></td>
		</tr>
		<tr>
			<td class="meta-label" valign="middle" style="font-weight:500;color:#111827;vertical-align:middle;padding:6px 16px 6px 0;white-space:nowrap;"><?php esc_html_e( 'Time:', 'limit-login-attempts-reloaded' ); ?></td>
			<td class="meta-value" valign="middle" style="vertical-align:middle;text-align:right;width:99%;word-break:break-word;padding:6px 0;"><?php echo esc_html( $time_safe ); ?></td>
		</tr>
	</table>
</div>
<div class="notice" style="border-top:1px solid #e5e7eb;padding:16px 0 0;">
	<div class="notice-content" style="font-size:13px;color:#4b5563;">
		<div class="notice-title" style="font-weight:600;color:#111827;margin-bottom:8px;"><span class="notice-title-emoji" style="color:#f59e0b;font-size:18px;">&#x26A0;&#xFE0F;</span> <?php esc_html_e( 'If this was not you, secure your account.', 'limit-login-attempts-reloaded' ); ?></div>
		<ul class="notice-list" style="margin:0;padding-left:18px;list-style-type:disc;">
			<li class="notice-item" style="margin-left:5px;margin-bottom:4px;"><?php esc_html_e( 'Change your WordPress password immediately.', 'limit-login-attempts-reloaded' ); ?></li>
			<li class="notice-item" style="margin-left:5px;margin-bottom:4px;"><?php esc_html_e( 'Enable additional login protections.', 'limit-login-attempts-reloaded' ); ?></li>
			<li class="notice-item" style="margin-left:5px;margin-bottom:4px;"><?php esc_html_e( 'Review recent login activity.', 'limit-login-attempts-reloaded' ); ?></li>
		</ul>
	</div>
</div>
