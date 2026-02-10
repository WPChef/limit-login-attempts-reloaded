<?php

namespace LLAR\Core\MfaFlow\Providers\Email;

use LLAR\Core\MfaFlow\MfaApiClient;
use LLAR\Core\MfaFlow\Providers\MfaProviderInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email MFA provider: handshake and verify via API (LLA_MFA_API_BASE_URL + LLA_MFA_API_PATH).
 * External app redirects user here; app calls site send_email_url to deliver OTP.
 */
class LlarMfaProvider implements MfaProviderInterface {

	const PROVIDER_ID = 'llar';

	/**
	 * @return string
	 */
	public function get_id() {
		return self::PROVIDER_ID;
	}

	/**
	 * @return string
	 */
	public function get_label() {
		return __( 'External app (LLAR MFA API)', 'limit-login-attempts-reloaded' );
	}

	/**
	 * @param array $payload user_ip, login_url, send_email_url, user_group, is_pre_authenticated (per API).
	 * @return array { success: bool, data: array|null, error: string|null }
	 */
	public function handshake( array $payload ) {
		$opts = $this->get_request_options();
		$api  = new MfaApiClient();
		return $api->handshake( $payload, $opts );
	}

	/**
	 * @param string $token  Session token.
	 * @param string $secret Session secret.
	 * @return array { success: bool, data: array|null, error: string|null }
	 */
	public function verify( $token, $secret ) {
		$opts = $this->get_request_options();
		$api  = new MfaApiClient();
		return $api->verify( $token, $secret, $opts );
	}

	/**
	 * Config fields for admin. Endpoint from constants (LLA_MFA_API_BASE_URL, LLA_MFA_API_PATH).
	 *
	 * @return array
	 */
	public function get_config_fields() {
		return array();
	}

	/**
	 * Build request options (base_url from LLA_MFA_API_BASE_URL + LLA_MFA_API_PATH).
	 *
	 * @return array
	 */
	private function get_request_options() {
		$base = defined( 'LLA_MFA_API_BASE_URL' ) ? rtrim( (string) LLA_MFA_API_BASE_URL, '/' ) : 'https://api.limitloginattempts.com';
		$path = defined( 'LLA_MFA_API_PATH' ) ? (string) LLA_MFA_API_PATH : '/mfa';
		if ( $path !== '' && substr( $path, 0, 1 ) !== '/' ) {
			$path = '/' . $path;
		}
		$base_url = $path !== '' ? $base . $path : $base;
		return array( 'base_url' => $base_url );
	}
}
