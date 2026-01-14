<?php

namespace LLAR\Core\Integrations;

use LLAR\Core\LimitLoginAttempts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IntegrationManager {

	/**
	 * @var LimitLoginAttempts
	 */
	private $llar_instance;

	/**
	 * @var IntegrationInterface[]
	 */
	private $integrations = array();

	/**
	 * @param LimitLoginAttempts $llar_instance
	 */
	public function __construct( LimitLoginAttempts $llar_instance ) {
		$this->llar_instance = $llar_instance;
		$this->register_integrations();
	}

	/**
	 * Register all available integrations
	 *
	 * @return void
	 */
	private function register_integrations() {
		$integration_classes = array(
			'MemberPressIntegration',
			'WooCommerceIntegration',
			// Other integrations can be added here in the future:
			// 'UltimateMemberIntegration',
		);

		// Allow filtering the list of integration classes
		$integration_classes = apply_filters( 'llar_integration_classes', $integration_classes );

		foreach ( $integration_classes as $class_name ) {
			$full_class_name = 'LLAR\Core\Integrations\\' . $class_name;

			// Ensure class is loaded before calling static method
			// Try autoloader first, then fallback to manual require
			if ( ! class_exists( $full_class_name ) ) {
				// Fallback: try to load the class file manually if autoloader failed
				$class_file = $this->get_integration_file_path( $class_name );
				if ( $class_file && file_exists( $class_file ) ) {
					require_once $class_file;
				}

				// If still not loaded, skip this integration
				if ( ! class_exists( $full_class_name ) ) {
					// Log error for debugging (only if WP_DEBUG is enabled)
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( sprintf( 'LLAR: Failed to load integration class %s', $full_class_name ) );
					}
					continue;
				}
			}

			// Check if plugin is active using static method before creating instance
			if ( ! $full_class_name::is_plugin_active() ) {
				continue;
			}

			// Only create instance if plugin is active
			$integration = new $full_class_name( $this->llar_instance );
			$this->integrations[] = $integration;
			$integration->register_hooks();
		}
	}

	/**
	 * Get file path for integration class
	 *
	 * @param string $class_name Class name without namespace
	 * @return string|false File path or false if not found
	 */
	private function get_integration_file_path( $class_name ) {
		$base_dir = dirname( __FILE__ );
		$file_path = $base_dir . '/' . $class_name . '.php';

		return file_exists( $file_path ) ? $file_path : false;
	}

	/**
	 * Get all active integrations
	 *
	 * @return IntegrationInterface[]
	 */
	public function get_active_integrations() {
		return $this->integrations;
	}

	/**
	 * Check if any integration's login page is being used
	 *
	 * @return bool
	 */
	public function is_custom_login_page() {
		foreach ( $this->integrations as $integration ) {
			if ( $integration->is_login_page() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get login credentials from any active integration
	 *
	 * @return array|null
	 */
	public function get_login_credentials() {
		foreach ( $this->integrations as $integration ) {
			$credentials = $integration->get_login_credentials();
			if ( $credentials ) {
				return $credentials;
			}
		}

		return null;
	}

	/**
	 * Display error on the appropriate login page
	 *
	 * @param string $message
	 * @return void
	 */
	public function display_error( $message ) {
		foreach ( $this->integrations as $integration ) {
			if ( $integration->is_login_page() ) {
				$integration->display_error( $message );
				break;
			}
		}
	}

	/**
	 * Get integration by plugin name
	 *
	 * @param string $plugin_name
	 * @return IntegrationInterface|null
	 */
	public function get_integration( $plugin_name ) {
		foreach ( $this->integrations as $integration ) {
			if ( $integration->get_plugin_name() === $plugin_name ) {
				return $integration;
			}
		}

		return null;
	}

	/**
	 * Check if any integration's registration page is being used
	 *
	 * @return bool
	 */
	public function is_custom_registration_page() {
		foreach ( $this->integrations as $integration ) {
			if ( $integration->is_registration_page() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get registration data from any active integration
	 *
	 * @return array|null
	 */
	public function get_registration_data() {
		foreach ( $this->integrations as $integration ) {
			$data = $integration->get_registration_data();
			if ( $data ) {
				return $data;
			}
		}

		return null;
	}

	/**
	 * Display error on the appropriate registration page
	 *
	 * @param string $message
	 * @return void
	 */
	public function display_registration_error( $message ) {
		foreach ( $this->integrations as $integration ) {
			if ( $integration->is_registration_page() ) {
				$integration->display_registration_error( $message );
				break;
			}
		}
	}
}
