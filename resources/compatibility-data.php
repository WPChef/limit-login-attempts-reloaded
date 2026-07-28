<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compatibility data for the Debug tab.
 *
 * Shows which LLAR features work with which third-party plugins.
 * Data sourced from readme.txt and codebase integration analysis.
 *
 * @return array
 */
return array(
	'WooCommerce' => array(
		'login'    => 'both',
		'register' => 'cloud',
		'password' => '',
		'2fa'      => 'cloud',
		'gdpr'     => 'both',
		'error'    => 'both',
	),
	'MemberPress' => array(
		'login'    => 'both',
		'register' => '',
		'password' => '',
		'2fa'      => 'cloud',
		'gdpr'     => 'both',
		'error'    => 'both',
	),
	'Ultimate Member' => array(
		'login'    => 'both',
		'register' => '',
		'password' => '',
		'2fa'      => 'cloud',
		'gdpr'     => 'both',
		'error'    => 'both',
	),
	'Wordfence' => array(
		'login'    => 'both',
		'register' => '',
		'password' => '',
		'2fa'      => 'cloud',
		'gdpr'     => 'both',
		'error'    => 'both',
	),
	'BuddyPress' => array(
		'login'    => 'both',
		'register' => '',
		'password' => '',
		'2fa'      => 'cloud',
		'gdpr'     => 'both',
		'error'    => 'both',
	),
	'Sucuri' => array(
		'login'    => 'both',
		'register' => '',
		'password' => '',
		'2fa'      => 'cloud',
		'gdpr'     => 'both',
		'error'    => 'both',
	),
	'WPS Hide Login' => array(
		'login'    => 'both',
		'register' => '',
		'password' => '',
		'2fa'      => 'cloud',
		'gdpr'     => 'both',
		'error'    => 'both',
	),
);
