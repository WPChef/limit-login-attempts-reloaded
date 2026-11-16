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

	/** error_code: the API base URL is missing or not an absolute http(s) URL. */
	const ERROR_NOT_CONFIGURED = 'mfa_api_not_configured';

	/** error_code: HTTP 200 whose body cannot be parsed into a usable response. */
	const ERROR_INVALID_RESPONSE_BODY = 'mfa_invalid_response_body';

	/**
	 * Seconds the login-blocking handshake/verify calls wait for the provider.
	 * Deliberately above the transport default (5): a slow-but-alive provider
	 * must not be declared unreachable — every such timeout would open a 60s
	 * password-only availability window.
	 */
	const REQUEST_TIMEOUT = 10;

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
	 * A base URL must be an absolute http(s) URL with a host. An empty value or
	 * a path-only value (e.g. from an empty LLA_MFA_API_BASE_URL constant) is a
	 * misconfiguration and must not silently disable 2FA through the
	 * availability window.
	 *
	 * @param string $base Base URL.
	 * @return bool
	 */
	private static function is_base_url_usable( $base ) {
		if ( ! is_string( $base ) || '' === $base ) {
			return false;
		}
		$scheme = wp_parse_url( $base, PHP_URL_SCHEME );
		$host   = wp_parse_url( $base, PHP_URL_HOST );

		return ( 'http' === $scheme || 'https' === $scheme ) && is_string( $host ) && '' !== $host;
	}

	/**
	 * Whether the default (constants-derived) API base URL is usable. Used for
	 * the settings-page misconfiguration notice.
	 *
	 * @return bool
	 */
	public static function default_base_url_is_usable() {
		return self::is_base_url_usable( self::get_default_base_url() );
	}

	/**
	 * Call POST /wp/handshake.
	 *
	 * @param array $payload  user_ip, login_url, send_email_url (required per API), user_group, is_pre_authenticated.
	 * @param array $options Optional. base_url (override constants).
	 * @return array { success: bool, data: array|null, error: string|null, error_code?: string }
	 */
	public function handshake( array $payload, $options = array() ) {
		$base = isset( $options['base_url'] ) ? rtrim( (string) $options['base_url'], '/' ) : self::get_default_base_url();
		if ( ! self::is_base_url_usable( $base ) ) {
			return array(
				'success'    => false,
				'data'       => null,
				'error'      => 'MFA API endpoint not configured',
				'error_code' => self::ERROR_NOT_CONFIGURED,
			);
		}

		$url = $base . '/wp/handshake';
		$request_options = array(
			'data'    => $payload,
			'timeout' => $this->request_timeout(),
		);
		if ( defined( 'LLA_MFA_API_TOKEN' ) && LLA_MFA_API_TOKEN !== '' ) {
			$request_options['headers'] = array( 'Authorization: Bearer ' . (string) LLA_MFA_API_TOKEN );
		}
		$response = Http::post( $url, $request_options );

		return $this->parse_response( $response, $url );
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
		$base = isset( $options['base_url'] ) ? rtrim( (string) $options['base_url'], '/' ) : self::get_default_base_url();
		if ( ! self::is_base_url_usable( $base ) ) {
			return array(
				'success'    => false,
				'data'       => null,
				'error'      => 'MFA API endpoint not configured',
				'error_code' => self::ERROR_NOT_CONFIGURED,
			);
		}

		$url             = $base . '/wp/verify';
		$request_options = array(
			'data'    => array(
				'token'  => $token,
				'secret' => $secret,
			),
			'timeout' => $this->request_timeout(),
		);
		if ( defined( 'LLA_MFA_API_TOKEN' ) && LLA_MFA_API_TOKEN !== '' ) {
			$request_options['headers'] = array( 'Authorization: Bearer ' . (string) LLA_MFA_API_TOKEN );
		}
		$response = Http::post( $url, $request_options );

		$result = $this->parse_response( $response, $url );
		$status = isset( $response['status'] ) ? (int) $response['status'] : 0;
		return $result;
	}

	/**
	 * Timeout for the login-blocking MFA API calls (handshake, verify).
	 *
	 * Filterable for site-specific tuning, clamped to 1-30 seconds so a
	 * misconfigured filter cannot stall the login flow.
	 *
	 * @return int
	 */
	private function request_timeout() {
		/**
		 * Filters the timeout for login-blocking MFA API requests.
		 *
		 * @param int $timeout Seconds. Default 10; clamped to 1-30 after filtering.
		 */
		$timeout = (int) apply_filters( 'llar_mfa_request_timeout', self::REQUEST_TIMEOUT );

		return max( 1, min( $timeout, 30 ) );
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
		$body   = isset( $response['data'] ) ? $response['data'] : '';
		$err    = isset( $response['error'] ) ? $response['error'] : null;

		if ( 200 !== $status ) {
			$decoded = is_string( $body ) ? json_decode( $body, true ) : null;
			$message = ( is_array( $decoded ) && ! empty( $decoded['message'] ) ) ? $decoded['message'] : $err;
			return array(
				'success'    => false,
				'data'       => null,
				'error'      => $message ? $message : 'Request failed',
				'status'     => $status,
				'error_code' => isset( $response['error_code'] ) ? $response['error_code'] : null,
			);
		}

		$data = is_string( $body ) ? json_decode( $body, true ) : null;
		if ( ! is_array( $data ) ) {
			return array(
				'success'    => false,
				'data'       => null,
				'error'      => 'Invalid response',
				'status'     => $status,
				'error_code' => self::ERROR_INVALID_RESPONSE_BODY,
			);
		}

		$data = Helpers::sanitize_stripslashes_deep( $data );
		return array(
			'success'    => true,
			'data'       => $data,
			'error'      => null,
			'status'     => $status,
			'error_code' => null,
		);
	}
}
