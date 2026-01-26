<?php

namespace LLAR\Core\Mfa;

use LLAR\Core\Config;
use LLAR\Core\MfaConstants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MFA Settings Manager
 * Handles MFA settings operations
 */
class MfaSettingsManager {
	/**
	 * Rules service
	 *
	 * @var MfaRules
	 */
	private $rules;

	/**
	 * Constructor
	 *
	 * @param MfaRules $rules Rules service
	 */
	public function __construct( MfaRules $rules ) {
		$this->rules = $rules;
	}

	/**
	 * Check if MFA is temporarily disabled
	 *
	 * @return bool True if MFA is temporarily disabled
	 */
	public function is_mfa_temporarily_disabled() {
		$disabled = get_transient( MfaConstants::TRANSIENT_MFA_DISABLED );

		if ( false === $disabled ) {
			// Transient expired - automatically re-enable MFA
			$mfa_enabled = Config::get( 'mfa_enabled', false );
			if ( ! $mfa_enabled ) {
				// MFA was disabled via rescue code, re-enable it now
				Config::update( 'mfa_enabled', 1 );
			}
			return false;
		}

		return true;
	}

	/**
	 * Cleanup rescue codes when MFA is disabled
	 */
	public function cleanup_rescue_codes() {
		// Delete all rescue codes
		Config::delete( 'mfa_rescue_codes' );

		// Clear temporary token
		Config::delete( 'mfa_rescue_download_token' );

		// Clear temporary disable transient
		delete_transient( MfaConstants::TRANSIENT_MFA_DISABLED );
	}

	/**
	 * Get MFA settings for view
	 *
	 * @return array Array with mfa_enabled, mfa_temporarily_disabled, mfa_roles, prepared_roles, editable_roles, show_rescue_popup
	 */
	public function get_settings_for_view() {
		$mfa_enabled_raw          = Config::get( 'mfa_enabled', false );
		$mfa_temporarily_disabled = $this->is_mfa_temporarily_disabled();
		$mfa_checkbox_state       = get_transient( MfaConstants::TRANSIENT_CHECKBOX_STATE );

		// MFA is considered enabled if it's enabled in config AND not temporarily disabled
		// OR if checkbox state is stored (popup is shown)
		$mfa_enabled = ( $mfa_enabled_raw && ! $mfa_temporarily_disabled ) || ( 1 === $mfa_checkbox_state );

		$mfa_roles = Config::get( 'mfa_roles', array() );

		// Ensure $mfa_roles is always an array
		if ( ! is_array( $mfa_roles ) ) {
			$mfa_roles = array();
		}

		// Get editable roles and prepare translated names with sanitization
		$editable_roles = get_editable_roles();
		$prepared_roles = array();
		foreach ( $editable_roles as $role_key => $role_data ) {
			// Sanitize translated role name for security
			$prepared_roles[ $role_key ] = esc_html( translate_user_role( $role_data['name'] ) );
		}

		// Check if rescue popup should be shown
		$codes             = Config::get( 'mfa_rescue_codes', array() );
		$show_rescue_popup = $this->rules->should_show_rescue_popup( $codes );

		return array(
			'mfa_enabled'              => $mfa_enabled,
			'mfa_temporarily_disabled' => $mfa_temporarily_disabled,
			'mfa_roles'                => $mfa_roles,
			'prepared_roles'           => $prepared_roles,
			'editable_roles'           => $editable_roles,
			'show_rescue_popup'        => $show_rescue_popup,
		);
	}

	/**
	 * Prepare roles data for MFA tab
	 * Should be called before including view to ensure data is ready
	 *
	 * @return array Prepared roles data
	 */
	public function prepare_roles_data() {
		// Get editable roles and prepare translated names with sanitization
		$editable_roles = get_editable_roles();
		$prepared_roles = array();
		foreach ( $editable_roles as $role_key => $role_data ) {
			// Sanitize translated role name for security
			$prepared_roles[ $role_key ] = esc_html( translate_user_role( $role_data['name'] ) );
		}
		return array(
			'prepared_roles' => $prepared_roles,
			'editable_roles' => $editable_roles,
		);
	}
}
