<?php

namespace LLAR\Core\MfaFlow\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MFA provider interface: handshake, verify, and config fields for admin.
 */
interface MfaProviderInterface {

	/**
	 * Unique provider id (e.g. 'llar').
	 *
	 * @return string
	 */
	public function get_id();

	/**
	 * Human-readable label for admin UI.
	 *
	 * @return string
	 */
	public function get_label();

	/**
	 * Run handshake with MFA service. Payload per API: user_ip, login_url, send_email_url, user_group, is_pre_authenticated.
	 *
	 * @param array $payload Request payload.
	 * @return array { success: bool, data: array|null (token, secret, redirect_url), error: string|null }
	 */
	public function handshake( array $payload );

	/**
	 * Verify session with MFA service.
	 *
	 * @param string $token  Session token.
	 * @param string $secret Session secret.
	 * @return array { success: bool, data: array|null (is_verified), error: string|null }
	 */
	public function verify( $token, $secret );

	/**
	 * Config field definitions for admin. Each item: id, label, type, placeholder, value. Empty if all from constants.
	 *
	 * @return array
	 */
	public function get_config_fields();
}
