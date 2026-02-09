<?php

namespace LLAR\Core\MfaFlow;

use LLAR\Core\Config;
use LLAR\Core\Helpers;
use LLAR\Core\Http\Http;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MFA API client: handshake and verify via existing HTTP transport.
 * Supports optional X-API-Key (e.g. from provider config).
 */
class MfaApiClient {

	/**
	 * Call POST /wp/handshake.
	 *
	 * @param array $payload  user_ip, login_url, send_email_url, optional user_group, is_pre_authenticated.
	 * @param array $options  Optional. base_url, api_key (sent as X-API-Key header).
	 * @return array { success: bool, data: array|null, error: string|null }
	 */
	public function handshake( array $payload, $options = array() ) {
		$base = isset( $options['base_url'] ) ? rtrim( (string) $options['base_url'], '/' ) : rtrim( (string) Config::get( 'mfa_api_endpoint', '' ), '/' );
		if ( '' === $base ) {
			return array( 'success' => false, 'data' => null, 'error' => 'MFA API endpoint not configured' );
		}

		$url = $base . '/wp/handshake';
		MfaFlowLogger::log_to_file( 'handshake request url=' . $url );
		$request_options = array( 'data' => $payload );
		if ( ! empty( $options['api_key'] ) ) {
			$request_options['headers'] = array( 'X-API-Key: ' . $options['api_key'] );
		}
		$response = Http::post( $url, $request_options );
		$status   = isset( $response['status'] ) ? (int) $response['status'] : 0;
		MfaFlowLogger::log_to_file( 'handshake response status=' . $status . ' error=' . ( isset( $response['error'] ) ? substr( (string) $response['error'], 0, 60 ) : '' ) );

		$result = $this->parse_response( $response, $url );
		MfaFlowLogger::increment_usage( 'handshake' );
		MfaFlowLogger::log( 'handshake', $result['success'] ? 'success' : 'fail', array(
			'status' => isset( $response['status'] ) ? (int) $response['status'] : 0,
			'error'  => $result['error'],
		) );
		return $result;
	}

	/**
	 * Call POST /wp/verify.
	 *
	 * @param string $token   Session token.
	 * @param string $secret  Session secret.
	 * @param array  $options Optional. base_url, api_key (sent as X-API-Key header).
	 * @return array { success: bool, data: array|null, error: string|null }
	 */
	public function verify( $token, $secret, $options = array() ) {
		$base = isset( $options['base_url'] ) ? rtrim( (string) $options['base_url'], '/' ) : rtrim( (string) Config::get( 'mfa_api_endpoint', '' ), '/' );
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
		if ( ! empty( $options['api_key'] ) ) {
			$request_options['headers'] = array( 'X-API-Key: ' . $options['api_key'] );
		}
		$response = Http::post( $url, $request_options );

		$result = $this->parse_response( $response, $url );
		MfaFlowLogger::increment_usage( 'verify' );
		MfaFlowLogger::log( 'verify', $result['success'] ? 'success' : 'fail', array(
			'status' => isset( $response['status'] ) ? (int) $response['status'] : 0,
			'error'  => $result['error'],
		) );
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
