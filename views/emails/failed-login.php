<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$email_title    = __( 'Login blocked', 'limit-login-attempts-reloaded' );
$email_logo_cid = 'logo';

include LLA_PLUGIN_DIR . 'views/emails/header.php';
include LLA_PLUGIN_DIR . 'views/emails/failed-login-content.php';
include LLA_PLUGIN_DIR . 'views/emails/footer.php';