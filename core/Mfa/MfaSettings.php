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
	 *
	 * @return bool
	 */
	public function is_mfa_temporarily_disabled() {
		$disabled = get_transient( MfaConstants::TRANSIENT_MFA_DISABLED );
		if ( false === $disabled ) {
			$mfa_enabled = Config::get( 'mfa_enabled', false );
			if ( ! $mfa_enabled ) {
				Config::update( 'mfa_enabled', 1 );
			}
			return false;
		}
		return true;
	}

	/**
	 * Cleanup rescue codes and MFA transients when MFA is disabled.
	 *
	 * @return void
	 */
	public function cleanup_rescue_codes() {
		Config::delete( 'mfa_rescue_codes' );
		Config::delete( 'mfa_rescue_download_token' );
		Config::update( 'mfa_rescue_pending_links', array() );
		$this->delete_mfa_transients();
	}

	/**
	 * Delete MFA transients via prepared statements.
	 */
	private function delete_mfa_transients() {
		global $wpdb;
		$table = $wpdb->options;
		$like  = $wpdb->esc_like( '_transient_llar_mfa' ) . '%';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE option_name LIKE %s", $like ) );
		$like_timeout = $wpdb->esc_like( '_transient_timeout_llar_mfa' ) . '%';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE option_name LIKE %s", $like_timeout ) );
	}

	/**
	 * Prepare roles for MFA tab.
	 *
	 * @return array prepared_roles, editable_roles
	 */
	public function prepare_roles_data() {
		$editable_roles = get_editable_roles();
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

		$mfa_enabled = ( $mfa_enabled_raw && ! $mfa_temporarily_disabled ) || ( 1 === $mfa_checkbox_state );

		$mfa_roles = Config::get( 'mfa_roles', array() );
		if ( ! is_array( $mfa_roles ) ) {
			$mfa_roles = array();
		}

		$roles_data    = $this->prepare_roles_data();
		$codes         = Config::get( 'mfa_rescue_codes', array() );
		$show_popup    = $show_rescue_popup || MfaBackupCodes::should_show_rescue_popup( $codes );
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
