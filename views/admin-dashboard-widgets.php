<?php
if (!defined('ABSPATH')) exit();
?>

<div id="llar-admin-dashboard-widgets">
    <div class="llar-widget">
        <div class="widget-content">
	        <?php include_once( LLA_PLUGIN_DIR . 'views/chart-circle-failed-attempts-today.php'); ?>
        </div>
    </div>
    <div class="llar-widget widget-2">
        <div class="widget-content">
	        <?php include_once( LLA_PLUGIN_DIR . 'views/chart-failed-attempts.php'); ?>
        </div>
    </div>
</div>
