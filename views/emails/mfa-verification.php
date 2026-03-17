<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$logo_src = '';
if ( defined( 'LLA_PLUGIN_DIR' ) ) {
	$logo_path = LLA_PLUGIN_DIR . 'assets/img/icon-logo-menu.png';
	if ( file_exists( $logo_path ) ) {
		$logo_content = file_get_contents( $logo_path );
		if ( $logo_content !== false ) {
			$logo_src = 'data:image/png;base64,' . base64_encode( $logo_content );
		}
	}
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
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 12px;
			padding: 24px 32px 16px;
			background-color: #f3f1f5;
			font-size: 14px;
			font-weight: 600;
			color: #111827;
			line-height: 1.4;
			min-height: 48px;
		}
		.header-logo {
			display: inline-block;
			width: 40px;
			height: 40px;
			vertical-align: middle;
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
		.meta-table {
			display: grid;
			grid-template-columns: auto 1fr;
			gap: 2px 16px;
			font-size: 13px;
			color: #4b5563;
		}
		.meta-label {
			font-weight: 500;
			color: #111827;
		}
		.meta-value {
			text-align: right;
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
			<div class="header"><?php if ( $logo_src !== '' ) : ?><img src="<?php echo esc_attr( $logo_src ); ?>" alt="" class="header-logo" width="40" height="40"><?php endif; ?><?php esc_html_e( 'Limit Login Attempts Reloaded', 'limit-login-attempts-reloaded' ); ?></div>
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
					<div class="meta-table">
						<div class="meta-label"><?php esc_html_e( 'Site:', 'limit-login-attempts-reloaded' ); ?></div>
						<div class="meta-value"><?php echo esc_html( $site_domain_safe ); ?></div>
						<div class="meta-label"><?php esc_html_e( 'IP Address:', 'limit-login-attempts-reloaded' ); ?></div>
						<div class="meta-value"><?php echo esc_html( $ip_safe ); ?></div>
						<div class="meta-label"><?php esc_html_e( 'Location:', 'limit-login-attempts-reloaded' ); ?></div>
						<div class="meta-value"><?php echo esc_html( $location_safe ); ?></div>
						<div class="meta-label"><?php esc_html_e( 'Browser:', 'limit-login-attempts-reloaded' ); ?></div>
						<div class="meta-value"><?php echo esc_html( $browser_safe ); ?></div>
						<div class="meta-label"><?php esc_html_e( 'Time:', 'limit-login-attempts-reloaded' ); ?></div>
						<div class="meta-value"><?php echo esc_html( $time_safe ); ?></div>
					</div>
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
							'a'      => array( 'href' => array(), 'target' => array(), 'rel' => array() ),
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
