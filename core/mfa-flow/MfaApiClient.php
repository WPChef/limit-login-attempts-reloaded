<?php

namespace LLAR\Core\MfaFlow;

use LLAR\Core\Helpers;
use LLAR\Core\Http\Http;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MFA API client: handshake and verify via existing HTTP transport.
 * Uses LLA_MFA_API_BASE_URL + LLA_MFA_API_PATH constants; options may override base_url.
 */
class MfaApiClient {

	/**
	 * Default MFA API base URL (base + path from constants).
	 *
	 * @return string
	 */
	private static function get_default_base_url() {
		$base = defined( 'LLA_MFA_API_BASE_URL' ) ? rtrim( (string) LLA_MFA_API_BASE_URL, '/' ) : 'https://api.limitloginattempts.com';
		$path = defined( 'LLA_MFA_API_PATH' ) ? (string) LLA_MFA_API_PATH : '/mfa';
		if ( $path !== '' && substr( $path, 0, 1 ) !== '/' ) {
			$path = '/' . $path;
		}
		return $path !== '' ? $base . $path : $base;
	}

	/**
	 * Call POST /wp/handshake.
	 *
	 * @param array $payload  user_ip, login_url, send_email_url (required per API), user_group, is_pre_authenticated.
	 * @param array $options Optional. base_url (override constants).
	 * @return array { success: bool, data: array|null, error: string|null }
	 */
	public function handshake( array $payload, $options = array() ) {
		$base = isset( $options['base_url'] ) && (string) $options['base_url'] !== '' ? rtrim( (string) $options['base_url'], '/' ) : self::get_default_base_url();
		if ( '' === $base ) {
			return array( 'success' => false, 'data' => null, 'error' => 'MFA API endpoint not configured' );
		}

		$url = $base . '/wp/handshake';
		if ( defined( 'WP_DEBUG' ) && \WP_DEBUG ) {
			error_log( LLA_MFA_FLOW_LOG_PREFIX . 'handshake request url=' . $url );
		}
		$response = Http::post( $url, array( 'data' => $payload ) );
		$status   = isset( $response['status'] ) ? (int) $response['status'] : 0;
		if ( defined( 'WP_DEBUG' ) && \WP_DEBUG ) {
			error_log( LLA_MFA_FLOW_LOG_PREFIX . 'handshake response status=' . $status . ' error=' . ( isset( $response['error'] ) ? substr( (string) $response['error'], 0, 60 ) : '' ) );
		}

		$result = $this->parse_response( $response, $url );
		if ( defined( 'WP_DEBUG' ) && \WP_DEBUG ) {
			error_log( LLA_MFA_FLOW_LOG_PREFIX . 'handshake ' . ( $result['success'] ? 'success' : 'fail' ) . ' status=' . $status . ( $result['error'] ? ' error=' . substr( $result['error'], 0, 60 ) : '' ) );
		}
		return $result;
	}

	/**
	 * Call POST /wp/verify.
	 *
	 * @param string $token   Session token.
	 * @param string $secret  Session secret.
	 * @param array  $options Optional. base_url (override constants).
	 * @return array { success: bool, data: array|null, error: string|null }
	 */
	public function verify( $token, $secret, $options = array() ) {
		$base = isset( $options['base_url'] ) && (string) $options['base_url'] !== '' ? rtrim( (string) $options['base_url'], '/' ) : self::get_default_base_url();
		if ( '' === $base ) {
			return array( 'success' => false, 'data' => null, 'error' => 'MFA API endpoint not configured' );
		}

		$url = $base . '/wp/verify';
		$request_options = array(
			'data' => array(
				'token'  => $token,
				'secret' => $secret,
			),
		);
		$response = Http::post( $url, $request_options );

		$result = $this->parse_response( $response, $url );
		$status = isset( $response['status'] ) ? (int) $response['status'] : 0;
		if ( defined( 'WP_DEBUG' ) && \WP_DEBUG ) {
			error_log( LLA_MFA_FLOW_LOG_PREFIX . 'verify ' . ( $result['success'] ? 'success' : 'fail' ) . ' status=' . $status . ( $result['error'] ? ' error=' . substr( $result['error'], 0, 60 ) : '' ) );
		}
		return $result;
	}

	/**
	 * Parse transport response into success/data/error.
	 *
	 * @param array  $response With keys data, status, error.
	 * @param string $url      Request URL for logging.
	 * @return array { success: bool, data: array|null, error: string|null }
	 */
	private function parse_response( $response, $url ) {
		$status = isset( $response['status'] ) ? (int) $response['status'] : 0;
		$body = isset( $response['data'] ) ? $response['data'] : '';
		$err = isset( $response['error'] ) ? $response['error'] : null;

		if ( 200 !== $status ) {
			$decoded = is_string( $body ) ? json_decode( $body, true ) : null;
			$message = ( is_array( $decoded ) && ! empty( $decoded['message'] ) ) ? $decoded['message'] : $err;
			return array( 'success' => false, 'data' => null, 'error' => $message ? $message : 'Request failed' );
		}

		$data = is_string( $body ) ? json_decode( $body, true ) : null;
		if ( ! is_array( $data ) ) {
			return array( 'success' => false, 'data' => null, 'error' => 'Invalid response' );
		}

		$data = Helpers::sanitize_stripslashes_deep( $data );
		return array( 'success' => true, 'data' => $data, 'error' => null );
	}
}
