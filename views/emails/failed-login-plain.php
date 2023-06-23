<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

if ( !empty( $admin_name ) ) {
	_e( 'Hello {name},', 'limit-login-attempts-reloaded' );
} else {
	_e( 'Hello,', 'limit-login-attempts-reloaded' );
}
?>


<?php
_e( 'This notification was sent automatically via Limit Login Attempts Reloaded Plugin.', 'limit-login-attempts-reloaded' );

_e( 'This is installed on your {domain} WordPress site.', 'limit-login-attempts-reloaded' );
?>


<?php _e( 'The failed login details include:', 'limit-login-attempts-reloaded' ); ?>

* <?php _e( '{attempts_count} failed login attempts ({lockouts_count} lockout(s)) from IP {ip_address}', 'limit-login-attempts-reloaded' ); ?>

* <?php _e( 'Last user attempted: {username}', 'limit-login-attempts-reloaded' ); ?>

* <?php _e( 'IP was blocked for {blocked_duration}', 'limit-login-attempts-reloaded' ); ?>



<?php _e( 'Please visit your WordPress dashboard for additional details, investigation options, and help articles.', 'limit-login-attempts-reloaded' ); ?>

{dashboard_url}


<?php
_e( 'Experiencing frequent attacks or degraded performance? Consider ', 'limit-login-attempts-reloaded' );
_e( 'upgrading for advanced protection', 'limit-login-attempts-reloaded' );
?>

{premium_url}


<?php _e( 'Frequently Asked Questions', 'limit-login-attempts-reloaded' ); ?>


<?php _e( 'What is a failed login attempt?', 'limit-login-attempts-reloaded' ); ?>

<?php _e( 'A failed login attempt is when an IP address uses incorrect credentials to log into your website. The IP address could be a human operator, or a program designed to guess your password.', 'limit-login-attempts-reloaded' ); ?>


<?php _e( 'Why am I getting these emails?', 'limit-login-attempts-reloaded' ); ?>

<?php _e( 'You are receiving this email because there was a failed login attempt on your website {domain}. If you\'d like to opt out of these notifications, please click the “Unsubscribe” link below.', 'limit-login-attempts-reloaded' ); ?>


<?php _e( 'How dangerous is this failed login attempt?', 'limit-login-attempts-reloaded' ); ?>

<?php
_e( 'Unfortunately, we cannot determine the severity of the IP address with the free version of the plugin. If the IP continues to make attempts and is not recognized by your organization, then it\'s likely to have malicious intent. Depending on how frequent the attacks are, you may experience performance issues. In the plugin dashboard, you can investigate the frequency of the failed login attempts in the logs and take additional steps to protect your website (i.e. adding them to the block list). You can visit the ', 'limit-login-attempts-reloaded' );
_e( 'Limit Login Attempts Reloaded website', 'limit-login-attempts-reloaded' );
_e( ' for more information on our premium services, which can automatically block and detect malicious IP addresses.', 'limit-login-attempts-reloaded' );
?>

{llar_url}

--
<?php
if( LLA_Helpers::is_mu() ) {
	_e( 'This alert was sent by your website where Limit Login Attempts Reloaded free version is installed and you are listed as the admin. If you are a GoDaddy customer, the plugin is installed into a must-use (MU) folder.', 'limit-login-attempts-reloaded' );
}
?>

<?php
_e( 'Unsubscribe', 'limit-login-attempts-reloaded' );
_e( 'from these notifications.', 'limit-login-attempts-reloaded' );
?>

{unsubscribe_url}
