<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $llar_mfa_otp_logo_cid ) || ! is_string( $llar_mfa_otp_logo_cid ) ) {
	$llar_mfa_otp_logo_cid = '';
}

$email_title    = __( 'Verify your login', 'limit-login-attempts-reloaded' );
$email_logo_cid = $llar_mfa_otp_logo_cid;

include LLA_PLUGIN_DIR . 'views/emails/header.php';
include LLA_PLUGIN_DIR . 'views/emails/mfa-verification-content.php';
include LLA_PLUGIN_DIR . 'views/emails/footer.php';
