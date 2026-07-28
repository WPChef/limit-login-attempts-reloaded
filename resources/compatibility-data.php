<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compatibility data for the Debug tab.
 *
 * Shows which LLAR features work with which third-party plugins.
 * Values reflect actual integration code in core/integrations/ and the
 * "Compatible With" list in readme.txt. Empty string means the feature
 * is not integrated with that plugin.
 *
 * - 'both'  : works in Local and Cloud modes.
 * - 'cloud' : works in Cloud mode only.
 * - ''      : not supported.
 *
 * @return array
 */
return array(
	'WooCommerce'     => array(
		'login'    => 'both',
		'register' => 'cloud',
		'password' => '',
		'2fa'      => '',
		'gdpr'     => '',
		'error'    => 'both',
	),
	'MemberPress'     => array(
		'login'    => 'both',
		'register' => '',
		'password' => '',
		'2fa'      => '',
		'gdpr'     => '',
		'error'    => '',
	),
	'Ultimate Member' => array(
		'login'    => 'both',
		'register' => '',
		'password' => '',
		'2fa'      => '',
		'gdpr'     => '',
		'error'    => '',
	),
	'Wordfence'       => array(
		'login'    => 'both',
		'register' => '',
		'password' => '',
		'2fa'      => '',
		'gdpr'     => '',
		'error'    => '',
	),
	'Sucuri'          => array(
		'login'    => 'both',
		'register' => '',
		'password' => '',
		'2fa'      => '',
		'gdpr'     => '',
		'error'    => '',
	),
	'WPS Hide Login'  => array(
		'login'    => 'both',
		'register' => '',
		'password' => '',
		'2fa'      => '',
		'gdpr'     => '',
		'error'    => '',
	),
);
