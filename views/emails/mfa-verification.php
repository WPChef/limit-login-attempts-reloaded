<?php
/**
 * MFA verification email HTML. Dynamic values must be output only via esc_html(), esc_attr(), or wp_kses().
 * Context strings are sanitized in LlarMfaProvider::send_code() before include; escaping here prevents HTML injection if the template changes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Embedded logo: multipart/related CID (set in LlarMfaProvider::send_code). Empty = no image.
 *
 * @var string
 */
if ( ! isset( $llar_mfa_otp_logo_cid ) || ! is_string( $llar_mfa_otp_logo_cid ) ) {
	$llar_mfa_otp_logo_cid = '';
}

?>
<!doctype html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<meta charset="UTF-8" />
	<title><?php esc_html_e( 'Verify your login', 'limit-login-attempts-reloaded' ); ?></title>
	<style>
		body {
			margin: 0;
			padding: 0;
			background-color: #f4f5fb;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
			color: #333;
		}
		.wrapper {
			width: 100%;
			background-color: #f4f5fb;
			padding: 32px 0;
		}
		.container {
			max-width: 480px;
			margin: 0 auto;
			background-color: #f2f0f4;
			border-radius: 16px;
			box-shadow: 0 18px 45px rgba(15, 23, 42, 0.15);
			overflow: hidden;
		}
		.header {
			padding: 24px 32px 16px;
			background-color: #f3f1f5;
			font-size: 14px;
			font-weight: 600;
			color: #111827;
			line-height: 1.4;
			text-align: center;
		}
		table.header-table {
			margin: 0 auto;
			border-collapse: collapse;
		}
		table.header-table td {
			vertical-align: middle;
			padding: 0;
		}
		td.header-logo-cell {
			padding-right: 12px;
		}
		.content, .footer, .brand {
			width: 80%;
			margin: 0 auto;
		}
		.content {
			background-color: #fdfcfe;
			padding: 24px 32px;
		}
		.title {
			font-size: 22px;
			font-weight: 700;
			color: #111827;
			margin: 0 0 8px;
		}
		.description {
			font-size: 14px;
			line-height: 1.5;
			color: #4b5563;
			margin: 0 0 20px;
		}
		.code-box {
			margin: 0 0 20px;
			border-radius: 14px;
			background-color: #f9fafb;
			border: 1px solid #e5e7eb;
			text-align: center;
		}
		.code-value {
			font-size: 32px;
			letter-spacing: 6px;
			font-weight: 700;
			color: #111827;
			margin: 13px 0 8px;
		}
		.code-meta {
			margin-top: 20px;
			font-size: 12px;
			color: #6b7280;
			text-align: center;
			width: 80%;
			margin: 0 auto 20px;
		}
		.section {
			margin-bottom: 20px;
		}
		.section-title {
			font-size: 13px;
			font-weight: 600;
			color: #111827;
			margin: 0 0 8px;
		}
		/* Table layout: grid is unreliable in email clients (misaligned label/value rows). */
		table.meta-table {
			width: 100%;
			border-collapse: collapse;
			font-size: 13px;
			color: #4b5563;
			line-height: 1.45;
		}
		table.meta-table td {
			vertical-align: middle;
			padding: 6px 0;
		}
		table.meta-table td.meta-label {
			font-weight: 500;
			color: #111827;
			padding-right: 16px;
			white-space: nowrap;
		}
		table.meta-table td.meta-value {
			text-align: right;
			width: 99%;
			word-break: break-word;
		}
		.notice {
			border-top: 1px solid #e5e7eb;
			padding: 16px 0 0;
		}
		.notice-content {
			font-size: 13px;
			color: #4b5563;
		}
		.notice-title {
			font-weight: 600;
			color: #111827;
			margin-bottom: 8px;
		}
		.notice-title-emoji {
			color: #f59e0b;
			font-size: 18px;
		}
		.notice-list {
			margin: 0;
			padding-left: 18px;
			list-style-type: disc;
		}
		.notice-list li::marker {
			color: #92949b;
		}
		.notice-item {
			margin-left: 5px;
			margin-bottom: 4px;
		}
		.footer {
			border-bottom: 1px solid #e5e7eb;
			padding: 14px 32px 18px;
			background-color: #fdfcfe;
		}
		.footer .text {
			font-size: 11px;
			color: #6b7280;
			background-color: #f4f2f6;
			text-align: center;
			padding: 10px 0;
			line-height: 1.5;
			margin: 0;
			text-decoration: none;
		}
		a {
			color: #4c6b99;
			text-decoration: none;
		}
		.brand {
			padding: 10px 32px 18px;
			background-color: #f9fafb;
		}
		.brand .text {
			font-size: 11px;
			line-height: 15px;
			color: #9ca3af;
			margin: 0;
		}
		.brand a {
			color: #2563eb;
			text-decoration: none;
		}
	</style>
</head>
<body>
	<div class="wrapper">
		<div class="container">
			<div class="header">
				<table class="header-table" role="presentation" cellpadding="0" cellspacing="0" border="0">
					<tr>
						<?php if ( '' !== $llar_mfa_otp_logo_cid ) : ?>
						<td class="header-logo-cell" valign="middle">
							<img src="<?php echo esc_attr( 'cid:' . $llar_mfa_otp_logo_cid ); ?>" alt="" width="40" height="40" style="display:block;width:40px;height:40px;border:0;outline:none;text-decoration:none;">
						</td>
						<?php endif; ?>
						<td valign="middle">
							<?php esc_html_e( 'Limit Login Attempts Reloaded', 'limit-login-attempts-reloaded' ); ?>
						</td>
					</tr>
				</table>
			</div>
			<div class="content">
				<div class="title"><?php esc_html_e( 'Verify your login', 'limit-login-attempts-reloaded' ); ?></div>
				<div class="description">
					<?php esc_html_e( 'We received a login attempt to your WordPress site and need to confirm it is you.', 'limit-login-attempts-reloaded' ); ?>
					<?php esc_html_e( 'Enter the verification code below to complete your login.', 'limit-login-attempts-reloaded' ); ?>
				</div>
				<div class="code-box">
					<div class="code-value"><?php echo esc_html( $code_safe ); ?></div>
					<div class="code-meta">
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
				<div class="section">
					<div class="section-title"><?php esc_html_e( 'Login attempt from:', 'limit-login-attempts-reloaded' ); ?></div>
					<table class="meta-table" role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
						<tr>
							<td class="meta-label" valign="middle" style="vertical-align:middle;padding:6px 16px 6px 0;"><?php esc_html_e( 'Site:', 'limit-login-attempts-reloaded' ); ?></td>
							<td class="meta-value" valign="middle" style="vertical-align:middle;text-align:right;padding:6px 0;"><?php echo esc_html( $site_domain_safe ); ?></td>
						</tr>
						<tr>
							<td class="meta-label" valign="middle" style="vertical-align:middle;padding:6px 16px 6px 0;"><?php esc_html_e( 'IP Address:', 'limit-login-attempts-reloaded' ); ?></td>
							<td class="meta-value" valign="middle" style="vertical-align:middle;text-align:right;padding:6px 0;"><?php echo esc_html( $ip_safe ); ?></td>
						</tr>
						<tr>
							<td class="meta-label" valign="middle" style="vertical-align:middle;padding:6px 16px 6px 0;"><?php esc_html_e( 'Location:', 'limit-login-attempts-reloaded' ); ?></td>
							<td class="meta-value" valign="middle" style="vertical-align:middle;text-align:right;padding:6px 0;"><?php echo esc_html( $location_safe ); ?></td>
						</tr>
						<tr>
							<td class="meta-label" valign="middle" style="vertical-align:middle;padding:6px 16px 6px 0;"><?php esc_html_e( 'Browser:', 'limit-login-attempts-reloaded' ); ?></td>
							<td class="meta-value" valign="middle" style="vertical-align:middle;text-align:right;padding:6px 0;"><?php echo esc_html( $browser_safe ); ?></td>
						</tr>
						<tr>
							<td class="meta-label" valign="middle" style="vertical-align:middle;padding:6px 16px 6px 0;"><?php esc_html_e( 'Time:', 'limit-login-attempts-reloaded' ); ?></td>
							<td class="meta-value" valign="middle" style="vertical-align:middle;text-align:right;padding:6px 0;"><?php echo esc_html( $time_safe ); ?></td>
						</tr>
					</table>
				</div>
				<div class="notice">
					<div class="notice-content">
						<div class="notice-title"><span class="notice-title-emoji">&#x26A0;&#xFE0F;</span> <?php esc_html_e( 'If this was not you, secure your account.', 'limit-login-attempts-reloaded' ); ?></div>
						<ul class="notice-list">
							<li class="notice-item"><?php esc_html_e( 'Change your WordPress password immediately.', 'limit-login-attempts-reloaded' ); ?></li>
							<li class="notice-item"><?php esc_html_e( 'Enable additional login protections.', 'limit-login-attempts-reloaded' ); ?></li>
							<li class="notice-item"><?php esc_html_e( 'Review recent login activity.', 'limit-login-attempts-reloaded' ); ?></li>
						</ul>
					</div>
				</div>
			</div>
			<div class="footer">
				<div class="text">
					<?php
					echo wp_kses(
						__( 'This verification email was sent by<wbr> <wbr><strong><a href="https://www.limitloginattempts.com" target="_blank" rel="noopener">Limit&nbsp;Login&nbsp;Attempts&nbsp;Reloaded</a></strong>.<br>Never share this code with anyone.<wbr> Support will never ask for it.', 'limit-login-attempts-reloaded' ),
						array(
							'strong' => array(),
							'a'      => array(
						'href'   => array(),
						'target' => array(),
						'rel'    => array(),
						),
							'br'     => array(),
							'wbr'    => array(),
						)
					);
					?>
				</div>
			</div>
			<div class="brand">
				<div class="text">
					<strong><?php esc_html_e( 'Limit Login Attempts Reloaded', 'limit-login-attempts-reloaded' ); ?></strong><br>
					<?php esc_html_e( 'WordPress Security Plugin', 'limit-login-attempts-reloaded' ); ?><br>
					<a href="https://www.limitloginattempts.com" target="_blank" rel="noopener">www.limitloginattempts.com</a>
				</div>
			</div>
		</div>
	</div>
</body>
</html>
