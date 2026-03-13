<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title><?php esc_html_e( 'Verify your login', 'limit-login-attempts-reloaded' ); ?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<style>
		body {
			margin: 0;
			padding: 0;
			background-color: #f4f5fb;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
			color: #111827;
		}
		.wrapper {
			width: 100%;
			background-color: #f4f5fb;
			padding: 32px 0;
		}
		.container {
			max-width: 480px;
			margin: 0 auto;
			background-color: #ffffff;
			border-radius: 16px;
			box-shadow: 0 18px 45px rgba(15, 23, 42, 0.15);
			overflow: hidden;
		}
		.header {
			padding: 24px 32px 8px;
		}
		.logo-row {
			display: flex;
			align-items: center;
			gap: 10px;
			margin-bottom: 12px;
		}
		.logo-text {
			font-size: 14px;
			font-weight: 600;
			color: #111827;
		}
		.title {
			font-size: 22px;
			font-weight: 700;
			margin: 0 0 8px;
		}
		.lead {
			font-size: 14px;
			line-height: 1.5;
			color: #4b5563;
			margin: 0 0 16px;
		}
		.code-box {
			margin: 0 32px 20px;
			border-radius: 14px;
			background-color: #f9fafb;
			text-align: center;
			padding: 18px 16px 20px;
			border: 1px solid #e5e7eb;
		}
		.code-value {
			font-size: 32px;
			letter-spacing: 6px;
			font-weight: 700;
			color: #111827;
			margin: 0 0 8px;
		}
		.code-meta {
			font-size: 12px;
			color: #6b7280;
			margin: 0;
		}
		.section {
			padding: 0 32px 20px;
		}
		.section-title {
			font-size: 13px;
			font-weight: 600;
			color: #111827;
			margin: 0 0 8px;
		}
		.meta-table {
			width: 100%;
			border-collapse: collapse;
			font-size: 13px;
			color: #4b5563;
		}
		.meta-label {
			font-weight: 500;
			padding: 2px 0;
			width: 30%;
		}
		.meta-value {
			padding: 2px 0;
			text-align: right;
		}
		.notice {
			border-top: 1px solid #e5e7eb;
			margin: 0 32px;
			padding: 16px 0 18px;
			display: flex;
		}
		.notice-content {
			font-size: 13px;
			color: #4b5563;
		}
		.notice-content strong {
			display: block;
			margin-bottom: 4px;
			color: #111827;
		}
		.notice-list {
			margin: 0;
			padding-left: 18px;
		}
		.notice-list li {
			margin-bottom: 4px;
		}
		.footer {
			border-top: 1px solid #e5e7eb;
			padding: 14px 32px 18px;
			background-color: #f9fafb;
		}
		.footer-text {
			font-size: 11px;
			color: #6b7280;
			line-height: 1.5;
			margin: 0 0 8px;
		}
		.footer-brand {
			font-size: 11px;
			color: #9ca3af;
			margin: 0;
		}
		.footer-brand a {
			color: #4f46e5;
			text-decoration: none;
		}
		@media (max-width: 520px) {
			.container {
				border-radius: 0;
			}
			.header,
			.section,
			.footer {
				padding-left: 20px;
				padding-right: 20px;
			}
			.code-box {
				margin-left: 20px;
				margin-right: 20px;
			}
			.notice {
				margin-left: 20px;
				margin-right: 20px;
			}
		}
	</style>
</head>
<body>
<div class="wrapper">
	<div class="container">
		<div class="header">
			<div class="logo-row">
				<div class="logo-text">Limit Login Attempts Reloaded</div>
			</div>
			<h1 class="title"><?php esc_html_e( 'Verify your login', 'limit-login-attempts-reloaded' ); ?></h1>
			<p class="lead">
				<?php esc_html_e( 'We received a login attempt to your WordPress site and need to confirm it is you.', 'limit-login-attempts-reloaded' ); ?>
				<?php esc_html_e( 'Enter the verification code below to complete your login.', 'limit-login-attempts-reloaded' ); ?>
			</p>
		</div>

		<div class="code-box">
			<p class="code-value"><?php echo esc_html( $code_safe ); ?></p>
			<p class="code-meta">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: minutes until the code expires. */
						__( 'This code will expire in %d minutes or when your verification session ends.', 'limit-login-attempts-reloaded' ),
						$code_ttl_minutes
					)
				);
				?>
			</p>
		</div>

		<div class="section">
			<p class="section-title"><?php esc_html_e( 'Login attempt from:', 'limit-login-attempts-reloaded' ); ?></p>
			<table class="meta-table" role="presentation">
				<tbody>
				<tr>
					<td class="meta-label"><?php esc_html_e( 'Site:', 'limit-login-attempts-reloaded' ); ?></td>
					<td class="meta-value"><?php echo esc_html( $site_domain_safe ); ?></td>
				</tr>
				<tr>
					<td class="meta-label"><?php esc_html_e( 'IP Address:', 'limit-login-attempts-reloaded' ); ?></td>
					<td class="meta-value"><?php echo esc_html( $ip_safe ); ?></td>
				</tr>
				<tr>
					<td class="meta-label"><?php esc_html_e( 'Browser:', 'limit-login-attempts-reloaded' ); ?></td>
					<td class="meta-value"><?php echo esc_html( $browser_safe ); ?></td>
				</tr>
				<tr>
					<td class="meta-label"><?php esc_html_e( 'Time:', 'limit-login-attempts-reloaded' ); ?></td>
					<td class="meta-value"><?php echo esc_html( $time_safe ); ?></td>
				</tr>
				</tbody>
			</table>
		</div>

		<div class="notice">
			<div class="notice-content">
				<strong><?php esc_html_e( 'If this was not you, secure your account.', 'limit-login-attempts-reloaded' ); ?></strong>
				<ul class="notice-list">
					<li><?php esc_html_e( 'Change your WordPress password immediately.', 'limit-login-attempts-reloaded' ); ?></li>
					<li><?php esc_html_e( 'Enable additional login protections.', 'limit-login-attempts-reloaded' ); ?></li>
					<li><?php esc_html_e( 'Review recent login activity.', 'limit-login-attempts-reloaded' ); ?></li>
				</ul>
			</div>
		</div>

		<div class="footer">
			<p class="footer-text">
				<?php esc_html_e( 'This verification email was sent by Limit Login Attempts Reloaded. Never share this code with anyone. Support will never ask for it.', 'limit-login-attempts-reloaded' ); ?>
			</p>
			<p class="footer-brand">
				<?php esc_html_e( 'Limit Login Attempts Reloaded', 'limit-login-attempts-reloaded' ); ?>
				&middot;
				<a href="https://www.limitloginattempts.com" target="_blank" rel="noopener">
					<?php esc_html_e( 'www.limitloginattempts.com', 'limit-login-attempts-reloaded' ); ?>
				</a>
			</p>
		</div>
	</div>
</div>
</body>
</html>

