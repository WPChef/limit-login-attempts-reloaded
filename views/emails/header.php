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

if ( ! isset( $email_css_text ) || ! is_string( $email_css_text ) ) {
	$email_css_text = '';
}
?>
<!doctype html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<meta charset="UTF-8" />
	<title><?php echo esc_html( $email_title ); ?></title>
	<style><?php echo $email_css_text; ?></style>
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
