<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compatibility data for the Debug tab.
 *
 * Shows which LLAR features work with which third-party plugins.
 * Values reflect the integration code in core/integrations/, the hooks in
 * core/LimitLoginAttempts.php and the "Compatible With" list in readme.txt.
 *
 * - 'both'  : compatible (green check). Also used when a feature is not
 *             applicable to a plugin (e.g. the plugin has no registration
 *             or password recovery page of its own and keeps the WordPress
 *             core flow intact), because such a plugin cannot conflict
 *             with the feature.
 * - 'cloud' : compatible in Cloud mode only.
 * - ''      : not supported yet (custom plugin flow replaces the form).
 *
 * @return array
 */
return array(
	'WooCommerce'     => array(
		'login'    => 'both',
		'register' => 'cloud',
		'password' => 'both',
		'2fa'      => 'cloud',
		'gdpr'     => '',
		'error'    => 'both',
	),
	'MemberPress'     => array(
		'login'    => 'both',
		'register' => '',
		'password' => '',
		'2fa'      => 'cloud',
		'gdpr'     => '',
		'error'    => 'both',
	),
	'Ultimate Member' => array(
		'login'    => 'both',
		'register' => '',
		'password' => '',
		'2fa'      => '',
		'gdpr'     => '',
		'error'    => 'both',
	),
	'Wordfence'       => array(
		'login'    => 'both',
		'register' => 'both',
		'password' => 'both',
		'2fa'      => 'both',
		'gdpr'     => 'both',
		'error'    => 'both',
	),
	'Sucuri'          => array(
		'login'    => 'both',
		'register' => 'both',
		'password' => 'both',
		'2fa'      => 'both',
		'gdpr'     => 'both',
		'error'    => 'both',
	),
	'WPS Hide Login'  => array(
		'login'    => 'both',
		'register' => 'both',
		'password' => 'both',
		'2fa'      => 'both',
		'gdpr'     => 'both',
		'error'    => 'both',
	),
);
