<?php

namespace LLAR\Core\Mfa;

use LLAR\Core\Config;
use LLAR\Core\MfaConstants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MFA settings: view data, cleanup, temporarily disabled state.
 * Uses MfaValidator for block reason; MfaBackupCodes for rescue-popup logic.
 */
class MfaSettings implements MfaSettingsInterface {

	/**
	 * Whether MFA is temporarily disabled (rescue flow).
	 * When transient expires, we do not auto-enable MFA so admin's explicit disable is preserved.
	 *
	 * @return bool
	 */
	public function is_mfa_temporarily_disabled() {
		return false !== get_transient( MfaConstants::TRANSIENT_MFA_DISABLED );
	}

	/**
	 * Cleanup rescue codes and MFA transients when MFA is disabled.
	 *
	 * @return void
	 */
	public function cleanup_rescue_codes() {
		Config::delete( 'mfa_rescue_codes' );
		Config::delete( 'mfa_rescue_download_token' );
		$this->delete_mfa_transients();
	}

	/**
	 * Delete MFA transients via prepared statements.
	 */
	private function delete_mfa_transients() {
		global $wpdb;
		$table = $wpdb->options;

		// Cleanup general MFA-related transients.
		$like  = $wpdb->esc_like( '_transient_llar_mfa' ) . '%';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE option_name LIKE %s", $like ) );
		$like_timeout = $wpdb->esc_like( '_transient_timeout_llar_mfa' ) . '%';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE option_name LIKE %s", $like_timeout ) );

		// Cleanup rescue-link transients (one per hash_id).
		$like_rescue = $wpdb->esc_like( '_transient_' . MfaConstants::TRANSIENT_RESCUE_PREFIX ) . '%';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE option_name LIKE %s", $like_rescue ) );
		$like_rescue_timeout = $wpdb->esc_like( '_transient_timeout_' . MfaConstants::TRANSIENT_RESCUE_PREFIX ) . '%';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE option_name LIKE %s", $like_rescue_timeout ) );
	}

	/**
	 * Prepare roles for MFA tab. Caches get_editable_roles() per request to avoid repeated calls.
	 *
	 * @return array prepared_roles, editable_roles
	 */
	public function prepare_roles_data() {
		static $editable_roles = null;
		if ( null === $editable_roles ) {
			$editable_roles = get_editable_roles();
		}
		$prepared_roles = array();
		foreach ( $editable_roles as $role_key => $role_data ) {
			$prepared_roles[ $role_key ] = esc_html( translate_user_role( $role_data['name'] ) );
		}
		return array(
			'prepared_roles' => $prepared_roles,
			'editable_roles' => $editable_roles,
		);
	}

	/**
	 * MFA settings for view. Single source; uses MfaValidator for block reason.
	 *
	 * @param bool $show_rescue_popup From form submit when popup should open.
	 * @return array mfa_enabled, mfa_temporarily_disabled, mfa_roles, prepared_roles, editable_roles, show_rescue_popup, mfa_block_reason, mfa_block_message
	 */
	public function get_settings_for_view( $show_rescue_popup = false ) {
		$mfa_enabled_raw          = Config::get( 'mfa_enabled', false );
		$mfa_temporarily_disabled = $this->is_mfa_temporarily_disabled();
		$mfa_checkbox_state       = get_transient( MfaConstants::TRANSIENT_CHECKBOX_STATE );

		// When temporarily disabled, keep checkbox state aligned with persistent config so that
		// after transient expires MFA is effectively on again without user action.
		$mfa_enabled = ( $mfa_enabled_raw && ! $mfa_temporarily_disabled ) || ( 1 === $mfa_checkbox_state )
			|| ( $mfa_temporarily_disabled && $mfa_enabled_raw );

		$mfa_roles = Config::get( 'mfa_roles', array() );
		if ( ! is_array( $mfa_roles ) ) {
			$mfa_roles = array();
		}

		$roles_data    = $this->prepare_roles_data();
		$codes         = Config::get( 'mfa_rescue_codes', array() );
		// Only show rescue popup when MFA is enabled or user just enabled it (checkbox state).
		$mfa_should_show = $mfa_enabled_raw || ( 1 === $mfa_checkbox_state );
		$show_popup     = $mfa_should_show && ( $show_rescue_popup || MfaBackupCodes::should_show_rescue_popup( $codes ) );
		$mfa_block_reason = MfaValidator::get_block_reason();

		return array(
			'mfa_enabled'              => $mfa_enabled,
			'mfa_temporarily_disabled' => $mfa_temporarily_disabled,
			'mfa_roles'                => $mfa_roles,
			'prepared_roles'           => $roles_data['prepared_roles'],
			'editable_roles'           => $roles_data['editable_roles'],
			'show_rescue_popup'        => $show_popup,
			'mfa_block_reason'         => $mfa_block_reason,
			'mfa_block_message'        => $mfa_block_reason ? MfaValidator::get_block_message( $mfa_block_reason ) : '',
		);
	}
}
