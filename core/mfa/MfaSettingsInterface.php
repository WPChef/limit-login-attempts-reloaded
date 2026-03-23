<?php

namespace LLAR\Core\Mfa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface for MFA settings service.
 * View data, cleanup, temporarily disabled state, roles data.
 *
 * Used for testability: MfaManager can depend on this interface.
 */
interface MfaSettingsInterface {

	/**
	 * Whether MFA is temporarily disabled (rescue flow).
	 *
	 * @return bool True if temporarily disabled.
	 */
	public function is_mfa_temporarily_disabled();

	/**
	 * Cleanup rescue codes and MFA transients when MFA is disabled.
	 *
	 * @return void
	 */
	public function cleanup_rescue_codes();

	/**
	 * Prepare roles for MFA tab.
	 *
	 * @return array Keys: prepared_roles, editable_roles.
	 */
	public function prepare_roles_data();

	/**
	 * MFA settings for view. Single source; uses MfaValidator for block reason.
	 *
	 * @param bool $show_rescue_popup From form submit when popup should open.
	 * @return array mfa_enabled, mfa_temporarily_disabled, mfa_roles, prepared_roles, editable_roles, show_rescue_popup, mfa_block_reason, mfa_block_message.
	 */
	public function get_settings_for_view( $show_rescue_popup = false );
}
