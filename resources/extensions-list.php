<?php
/**
 * Array of extensions available on the Extensions tab
 *
 * @return array
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	array(
		'slug'        => 'disable-ai-for-security',
		'file'        => 'disable-ai-for-security/disable-ai-for-security.php',
		'name'        => 'Disable AI for Security',
		// Short description copied from the plugin / wordpress.org directory.
		'description' => __( 'Turns off the WordPress AI connectors for security. Works instantly with no settings — AI is fully disabled for every user role.', 'limit-login-attempts-reloaded' ),
		'icon'        => 'https://ps.w.org/disable-ai-for-security/assets/icon-256x256.png',
		'url'         => 'https://wordpress.org/plugins/disable-ai-for-security/',
	),
);
