<?php

namespace LLAR\Core\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface for providing options page URI.
 * Implemented by classes that need to generate links to the plugin's admin options page.
 */
interface OptionsPageUriProvider {

	/**
	 * Get the URI for the plugin's options page.
	 *
	 * @param string|false $tab Optional tab name to link to (e.g., 'settings', 'logs-local').
	 *                          Pass false to get the base options page URI.
	 * @return string Full URI to the options page, optionally with tab parameter.
	 */
	public function get_options_page_uri( $tab = false );
}
