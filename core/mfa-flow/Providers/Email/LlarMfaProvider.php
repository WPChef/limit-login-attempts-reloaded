<?php

namespace LLAR\Core\MfaFlow\Providers\Email;

use LLAR\Core\MfaFlow\MfaApiClient;
use LLAR\Core\MfaFlow\Providers\MfaProviderInterface;
use LLAR\Core\MfaFlow\MfaRestApi;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email MFA provider: handshake and verify via API (LLA_MFA_API_BASE_URL + LLA_MFA_API_PATH).
 * Builds send_email_url and send_email_url_fallback from send_email_secret for the handshake payload.
 * External app redirects user here; app calls site send_email_url to deliver OTP.
 */
class LlarMfaProvider implements MfaProviderInterface {

	const PROVIDER_ID = 'llar';
	const AJAX_ACTION  = 'llar_mfa_flow_send_code';

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
	 * Build send_email_url (REST) and send_email_url_fallback (AJAX) for handshake API.
	 * No secret in URL; caller must send secret in POST body. send_email_secret stays in payload for the API to use.
	 *
	 * @param string|null $send_email_secret Unused (kept for interface); secret is passed in handshake payload for POST body.
	 * @return array { send_email_url: string, send_email_url_fallback: string }
	 */
	public function build_send_email_urls( $send_email_secret = null ) {
		$send_email_url = MfaRestApi::get_send_code_rest_url();
		$send_email_url_fallback = add_query_arg( 'action', self::AJAX_ACTION, admin_url( 'admin-ajax.php' ) );
		return array(
			'send_email_url'          => $send_email_url,
			'send_email_url_fallback' => $send_email_url_fallback,
		);
	}

	/**
	 * @param array $payload user_ip, login_url, user_group, is_pre_authenticated; send_email_secret (used by API in POST body; URLs have no secret).
	 * @return array { success: bool, data: array|null, error: string|null }
	 */
	public function handshake( array $payload ) {
		if ( isset( $payload['send_email_secret'] ) ) {
			$urls = $this->build_send_email_urls();
			$payload = array_merge( $payload, $urls );
		}
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
