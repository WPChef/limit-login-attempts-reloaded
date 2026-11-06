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
		'description' => __( 'Switches off the AI features introduced in WordPress 7.0 — quickly, cleanly, and without a single setting to configure. Just activate it and AI is disabled site-wide.', 'limit-login-attempts-reloaded' ),
		'icon'        => 'https://ps.w.org/disable-ai-for-security/assets/icon-256x256.png',
		'url'         => 'https://wordpress.org/plugins/disable-ai-for-security/',
	),
);
