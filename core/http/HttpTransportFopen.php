<?php

namespace LLAR\Core\Http;

class HttpTransportFopen implements HttpTransportInterface {

	use HttpTimeoutTrait;
	use HttpRedirectFollowerTrait;
	use HttpDestinationPinTrait;

	/**
	 * @param $url
	 * @param array $options
	 *
	 * @return array
	 */
	public function get( $url, $options = array() ) {

		if ( ! empty( $options['data'] ) ) {
			$query_str = http_build_query( $options['data'] );
			$url      .= "?{$query_str}";
		}

		$headers = ! empty( $options['headers'] ) ? $options['headers'] : array();

		$destination = ! empty( $options['destination'] ) ? $options['destination'] : null;

		return $this->request( $url, 'GET', $headers, array(), $this->resolve_timeout( $options ), $destination );
	}

	/**
	 * @param $url
	 * @param array $options
	 *
	 * @return array
	 */
	public function post( $url, $options = array() ) {

		$headers = ! empty( $options['headers'] ) ? $options['headers'] : array();
		$data    = ! empty( $options['data'] ) ? $options['data'] : array();

		$destination = ! empty( $options['destination'] ) ? $options['destination'] : null;

		return $this->request( $url, 'POST', $headers, $data, $this->resolve_timeout( $options ), $destination );
	}

	/**
	 * Single stream request, no redirect following.
	 *
	 * @param string     $url
	 * @param string     $method
	 * @param array      $headers
	 * @param array      $data
	 * @param int        $timeout
	 * @param array|null $destination validated destination to pin the connection to
	 *
	 * @return array
	 */
	private function single_request( $url, $method, $headers = array(), $data = array(), $timeout = self::DEFAULT_TIMEOUT, $destination = null ) {

		$request_data = null;
		if ( ! empty( $data ) ) {
			$request_data = Http::encode_payload( $data );

			// Unserialisable payload (e.g. invalid UTF-8 from request headers):
			// fail the request instead of sending it out with an empty body.
			if ( false === $request_data ) {
				return Http::encoding_failure_response();
			}
		}

		list( $request_url, $headers, $ssl_options ) = $this->apply_destination_pin( $url, $headers, $destination );

		$context = stream_context_create(
			array(
				'http' => array(
					'method'          => $method,
					'header'          => implode( "\r\n", $headers ),
					'content'         => $request_data,
					'ignore_errors'   => true,
					'timeout'         => $timeout,
					// Redirects are followed manually in request() so that each
					// hop can be validated — never let the wrapper follow them.
					'follow_location' => 0,
				),
				'ssl'  => $ssl_options,
			)
		);

		$fp = @fopen( $request_url, 'rb', false, $context );

		$error    = null;
		$status   = null;
		$response = null;

		if ( ! $fp ) {

			if ( ! empty( $http_response_header[0] ) ) {
				list(, $code, $message ) = explode( ' ', $http_response_header[0], 3 );
				$error                   = $message;
				$status                  = $code;

			} else {
				$last_err = error_get_last();
				$error    = ! empty( $last_err['message'] ) ? $last_err['message'] : 'Unknown error!';
			}
		} else {
			list(, $code ) = explode( ' ', $http_response_header[0], 3 );
			$status        = $code;

			$response = stream_get_contents( $fp );
		}

		return array(
			'data'        => $response,
			'status'      => intval( $status ),
			'error'       => $error,
			'raw_headers' => isset( $http_response_header ) ? $http_response_header : array(),
		);
	}
}
