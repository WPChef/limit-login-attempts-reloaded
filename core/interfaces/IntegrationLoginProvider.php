<?php
/**
 * Integration Login Provider Interface
 *
 * @package LimitLoginAttempts
 * @since 3.3.0
 */

namespace LLAR\Core\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface for providing integration login identifiers.
 * Implemented by classes that handle third-party plugin integrations (e.g., WooCommerce, BuddyPress).
 */
interface IntegrationLoginProvider {

	/**
	 * Get the login identifier from active integrations.
	 * Used when standard WordPress login fields are not available.
	 *
	 * @return string Login identifier (username or email) from integration, or empty string if none.
	 */
	public function get_integration_login_identifier();
}
