<?php

namespace LLAR\Core\Http;

class Http {

	/**
	 * error_code of a transport result whose payload could not be serialised.
	 *
	 * Request-derived payload fields can be attacker-influenced, so a failed
	 * encode must be distinguished from a genuine outage: it has to fail the
	 * caller closed instead of being treated as "service unavailable".
	 */
	const ERROR_PAYLOAD_ENCODING = 'payload_encoding_failed';

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

		return self::$transport->get( $url, $options );
	}

	/**
	 * Serialise an outbound payload.
	 *
	 * Returns false when the data cannot be encoded (e.g. invalid UTF-8 from
	 * request headers) so transports can fail the request instead of sending
	 * an empty body. Deliberately json_encode() rather than wp_json_encode():
	 * the wp wrapper sanitises and retries, which would hide the failure.
	 *
	 * @param mixed $data Payload.
	 * @return string|false
	 */
	public static function encode_payload( $data ) {

		return json_encode( $data, JSON_FORCE_OBJECT );
	}

	/**
	 * The single shape every transport must return when the request payload
	 * cannot be encoded. Callers treat this as a hard failure, never as
	 * "service unavailable", so keep transports on this factory instead of
	 * hand-rolling the array.
	 *
	 * @return array
	 */
	public static function encoding_failure_response() {

		return array(
			'data'       => null,
			'status'     => 0,
			'error'      => 'Request payload encoding failed',
			'error_code' => self::ERROR_PAYLOAD_ENCODING,
		);
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