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
	 * Build send_email_url and send_email_url_fallback for handshake API (no secret in URL).
	 * Providers that use email OTP return REST and optional AJAX fallback URLs; others may return empty strings.
	 *
	 * @param string|null $send_email_secret Optional; kept for interface compatibility. Secret is passed in handshake payload for POST body.
	 * @return array { send_email_url: string, send_email_url_fallback: string }
	 */
	public function build_send_email_urls( $send_email_secret );

	/**
	 * Run handshake with MFA service.
	 * Caller may pass send_email_secret in payload; provider adds send_email_url and send_email_url_fallback (no secret in URL).
	 * API payload: user_ip, login_url, send_email_url, send_email_url_fallback, send_email_secret, user_group, is_pre_authenticated.
	 *
	 * @param array $payload Request payload (user_ip, login_url, user_group, is_pre_authenticated; may include send_email_secret).
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
	 * Send verification code to the user (e.g. by email or SMS).
	 * Provider is responsible for delivery; caller handles session/OTP storage.
	 *
	 * @param \WP_User $user User to send code to.
	 * @param string  $code Verification code.
	 * @return array { success: bool, message: string|null } Message only on failure.
	 */
	public function send_code( $user, $code );

	/**
	 * Config field definitions for admin. Each item: id, label, type, placeholder, value. Empty if all from constants.
	 *
	 * @return array
	 */
	public function get_config_fields();
}
