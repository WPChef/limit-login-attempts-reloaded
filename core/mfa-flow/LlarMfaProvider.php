<?php

namespace LLAR\Core\MfaFlow;

use LLAR\Core\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * LLAR MFA provider: handshake and verify via api.limitloginattempts.com (openapi 5).
 * Config from mfa_provider_config: endpoint, api_key.
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
	 * @param array $payload user_ip, login_url, send_email_url, optional user_group, is_pre_authenticated.
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
	 * Config fields for admin: endpoint, api_key.
	 *
	 * @return array
	 */
	public function get_config_fields() {
		$config = is_array( Config::get( 'mfa_provider_config', array() ) ) ? Config::get( 'mfa_provider_config', array() ) : array();
		$default_endpoint = Config::get( 'mfa_api_endpoint', 'https://api.limitloginattempts.com/mfa' );
		return array(
			array(
				'id'          => 'endpoint',
				'label'       => __( 'API endpoint', 'limit-login-attempts-reloaded' ),
				'type'        => 'text',
				'placeholder' => $default_endpoint,
				'value'       => isset( $config['endpoint'] ) ? $config['endpoint'] : $default_endpoint,
			),
			array(
				'id'          => 'api_key',
				'label'       => __( 'API key (X-API-Key)', 'limit-login-attempts-reloaded' ),
				'type'        => 'password',
				'placeholder' => '',
				'value'       => isset( $config['api_key'] ) ? $config['api_key'] : '',
			),
		);
	}

	/**
	 * Build request options (base_url, api_key) from config.
	 *
	 * @return array
	 */
	private function get_request_options() {
		$config   = is_array( Config::get( 'mfa_provider_config', array() ) ) ? Config::get( 'mfa_provider_config', array() ) : array();
		$default  = rtrim( (string) Config::get( 'mfa_api_endpoint', 'https://api.limitloginattempts.com/mfa' ), '/' );
		$base_url = isset( $config['endpoint'] ) && is_string( $config['endpoint'] ) && $config['endpoint'] !== '' ? rtrim( $config['endpoint'], '/' ) : $default;
		$api_key  = isset( $config['api_key'] ) && is_string( $config['api_key'] ) ? $config['api_key'] : '';
		return array(
			'base_url' => $base_url,
			'api_key'  => $api_key,
		);
	}
}
