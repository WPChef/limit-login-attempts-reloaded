<?php

if( !defined( 'ABSPATH' ) ) exit();

?>

<h3><?php echo __( 'Login Firewall Settings & Logs', 'limit-login-attempts-reloaded' ); ?></h3>

<div class="limit-login-app-dashboard">

	<?php include_once( LLA_PLUGIN_DIR.'views/app-widgets/active-lockouts.php'); ?>
	<?php include_once( LLA_PLUGIN_DIR.'views/app-widgets/event-log.php'); ?>
	<?php include_once( LLA_PLUGIN_DIR.'views/app-widgets/country-access-rules.php'); ?>
	<?php include_once( LLA_PLUGIN_DIR.'views/app-widgets/acl-rules.php'); ?>

</div>