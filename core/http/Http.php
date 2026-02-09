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
		// Prefer WP transport so outgoing requests (e.g. MFA handshake) go through wp_remote_* and appear in HTTP logs.
		if ( function_exists( 'wp_remote_get' ) ) {
			self::$transport = new HttpTransportWp();
		} elseif ( function_exists( 'fopen' ) && ini_get( 'allow_url_fopen' ) === '1' ) {
			self::$transport = new HttpTransportFopen();
		} elseif ( function_exists( 'curl_version' ) ) {
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

		return self::$transport->get( $url, $options );
	}

	/**
	 * @param $url
	 * @param array $options
	 *
	 * @return mixed
	 */
	public static function post( $url, $options = array() ) {

		$options['headers'] = array_merge(
			array( 'Content-Type: application/json; charset=utf-8' ),
			!empty( $options['headers'] ) ? $options['headers'] : array()
		);

		return self::$transport->post( $url, $options );
	}
}