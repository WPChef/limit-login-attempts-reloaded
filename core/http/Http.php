<?php

namespace LLAR\Core\Http;

class Http {

	/**
	 * @var HttpTransportInterface
	 */
	private static $transport;

	/**
	 * @throws \Exception
	 */
	public static function init() {

		if( function_exists( 'fopen' ) && ini_get( 'allow_url_fopen' ) === '1' ) {
			self::$transport = new HttpTransportFopen();
		} else if( function_exists( 'wp_remote_get' ) ) {
			self::$transport = new HttpTransportWp();
		} else if( function_exists( 'curl_version' ) ) {
			self::$transport = new HttpTransportCurl();
		} else {
			throw new \Exception( 'Unable to determine HTTP transport.' );
		}

	}

	/**
	 * @param $url
	 * @param array $options
	 *
	 * @return mixed
	 */
	public static function get( $url, $options = array() ) {

		if ( ! self::validate_url( $url ) ) {
			return self::blocked_url_response( $url );
		}

		return self::$transport->get( $url, $options );
	}

	/**
	 * @param $url
	 * @param array $options
	 *
	 * @return mixed
	 */
	public static function post( $url, $options = array() ) {

		if ( ! self::validate_url( $url ) ) {
			return self::blocked_url_response( $url );
		}

		$options['headers'] = array_merge(
			array( 'Content-Type: application/json; charset=utf-8' ),
			!empty( $options['headers'] ) ? $options['headers'] : array()
		);

		return self::$transport->post( $url, $options );
	}

	/**
	 * Outbound URL gate (SSRF): only public http(s) endpoints are fetchable.
	 * wp_http_validate_url() rejects loopback and private/reserved IP ranges
	 * (after DNS resolution) and non-standard schemes/ports, the same policy
	 * wp_safe_remote_get() enforces. Applies to every transport (fopen, WP,
	 * curl) since they all funnel through this facade.
	 *
	 * @param string $url
	 *
	 * @return bool
	 */
	private static function validate_url( $url ) {

		if ( ! is_string( $url ) || '' === $url ) {
			return false;
		}

		if ( function_exists( 'wp_http_validate_url' ) ) {
			return false !== wp_http_validate_url( $url );
		}

		// WordPress HTTP API unavailable (should not happen in practice).
		return false;
	}

	/**
	 * Transport-shaped response for a destination the site must not contact.
	 *
	 * @param string $url
	 *
	 * @return array
	 */
	private static function blocked_url_response( $url ) {

		return array(
			'data'   => null,
			'status' => 0,
			'error'  => 'Blocked non-public destination: ' . $url,
		);
	}
}