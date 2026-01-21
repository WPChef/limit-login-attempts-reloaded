<?php

namespace LLAR\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MfaController {

	/**
	 * Prepared roles for MFA tab (with translated and sanitized names)
	 *
	 * @var array
	 */
	public $prepared_roles = array();

	/**
	 * Editable roles data for MFA tab (for admin role check)
	 *
	 * @var array
	 */
	public $editable_roles = array();

	/**
	 * Register all hooks
	 */
	public function register() {
		// Controller is ready for future hooks if needed
	}

	/**
	 * Handle MFA settings form submission
	 *
	 * @param bool $has_capability Whether user has required capability
	 * @return bool True if settings were saved successfully
	 */
	public function handle_settings_submission( $has_capability ) {
		// Check if this is MFA settings form
		if ( ! isset( $_POST['llar_update_mfa_settings'] ) ) {
			return false;
		}

		check_admin_referer( 'limit-login-attempts-options' );

		// Check user capabilities
		if ( ! $has_capability ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'limit-login-attempts-reloaded' ) );
		}

		// Save MFA enabled/disabled - use absint() for explicit type casting
		Config::update( 'mfa_enabled', absint( isset( $_POST['mfa_enabled'] ) ) );

		// Save selected roles - use editable roles and optimize validation
		$mfa_roles = array();
		if ( isset( $_POST['mfa_roles'] ) && is_array( $_POST['mfa_roles'] ) && ! empty( $_POST['mfa_roles'] ) ) {
			// Get editable roles (cached by WordPress on request level)
			$editable_roles = get_editable_roles();
			$editable_role_keys = array_keys( $editable_roles );
			
			// Sanitize and filter roles - remove empty values and validate against editable roles
			$sanitized_roles = array_filter( 
				array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['mfa_roles'] ) ),
				'strlen' // Remove empty strings
			);
			
			// Validate against editable roles only
			$mfa_roles = array_intersect( $sanitized_roles, $editable_role_keys );
		}
		Config::update( 'mfa_roles', $mfa_roles );

		return true;
	}

	/**
	 * Prepare roles data for MFA tab
	 * Should be called before including view to ensure data is ready
	 */
	public function prepare_roles_data() {
		// Get editable roles and prepare translated names with sanitization
		$editable_roles = get_editable_roles();
		$prepared_roles = array();
		foreach ( $editable_roles as $role_key => $role_data ) {
			// Sanitize translated role name for security
			$prepared_roles[ $role_key ] = esc_html( translate_user_role( $role_data['name'] ) );
		}
		// Store for view
		$this->prepared_roles = $prepared_roles;
		$this->editable_roles = $editable_roles;
	}

	/**
	 * Get MFA settings for view
	 *
	 * @return array Array with mfa_enabled, mfa_roles, prepared_roles, editable_roles
	 */
	public function get_settings_for_view() {
		$mfa_enabled = Config::get( 'mfa_enabled', false );
		$mfa_roles = Config::get( 'mfa_roles', array() );
		
		// Ensure $mfa_roles is always an array
		if ( ! is_array( $mfa_roles ) ) {
			$mfa_roles = array();
		}

		return array(
			'mfa_enabled'    => $mfa_enabled,
			'mfa_roles'      => $mfa_roles,
			'prepared_roles' => $this->prepared_roles,
			'editable_roles' => $this->editable_roles,
		);
	}
}
