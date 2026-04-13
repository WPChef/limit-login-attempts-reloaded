<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $email_title ) || ! is_string( $email_title ) ) {
	$email_title = '';
}

if ( ! isset( $email_logo_cid ) || ! is_string( $email_logo_cid ) ) {
	$email_logo_cid = '';
}
?>
<!doctype html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<meta charset="UTF-8" />
	<title><?php echo esc_html( $email_title ); ?></title>
	<style>
		body{margin:0;padding:0;background-color:#f4f5fb;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;color:#333}
		.wrapper{width:100%;background-color:#f4f5fb;padding:32px 0}
		.container{max-width:480px;margin:0 auto;background-color:#f2f0f4;border-radius:16px;box-shadow:0 18px 45px rgba(15,23,42,.15);overflow:hidden}
		.header{padding:24px 32px 16px;background-color:#f3f1f5;font-size:14px;font-weight:600;color:#111827;line-height:1.4;text-align:center}
		table.header-table{margin:0 auto;border-collapse:collapse}
		table.header-table td{vertical-align:middle;padding:0}
		td.header-logo-cell{padding-right:12px}
		.content,.footer,.brand{width:80%;margin:0 auto}
		.content{background-color:#fdfcfe;padding:24px 32px}
		.footer{border-bottom:1px solid #e5e7eb;padding:14px 32px 18px;background-color:#fdfcfe}
		.footer .text{font-size:11px;color:#6b7280;background-color:#f4f2f6;text-align:center;padding:10px 0;line-height:1.5;margin:0;text-decoration:none}
		a{color:#4c6b99;text-decoration:none}
		.brand{padding:10px 32px 18px;background-color:#f9fafb}
		.brand .text{font-size:11px;line-height:15px;color:#9ca3af;margin:0}
		.brand a{color:#2563eb;text-decoration:none}
		.title{font-size:22px;font-weight:700;color:#111827;margin:0 0 8px}
		.description{font-size:14px;line-height:1.5;color:#4b5563;margin:0 0 20px}
	</style>
</head>
<body>
<div class="wrapper">
	<div class="container">
		<div class="header">
			<table class="header-table" role="presentation" cellpadding="0" cellspacing="0" border="0">
				<tr>
					<?php if ( '' !== $email_logo_cid ) : ?>
					<td class="header-logo-cell" valign="middle">
						<img src="<?php echo esc_attr( 'cid:' . $email_logo_cid ); ?>" alt="" width="40" height="40" style="display:block;width:40px;height:40px;border:0;outline:none;text-decoration:none;">
					</td>
					<?php endif; ?>
					<td valign="middle"><?php esc_html_e( 'Limit Login Attempts Reloaded', 'limit-login-attempts-reloaded' ); ?></td>
				</tr>
			</table>
		</div>
		<div class="content">
