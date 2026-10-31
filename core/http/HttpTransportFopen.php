<?php

namespace LLAR\Core\Http;

class HttpTransportFopen implements HttpTransportInterface {

	use HttpTimeoutTrait;

	/**
	 * @param $url
	 * @param array $options
	 *
	 * @return array
	 */
	public function get( $url, $options = array() ) {

		if( !empty( $options['data'] ) ) {
			$query_str = http_build_query( $options['data'] );
			$url .= "?{$query_str}";
		}

		$headers = !empty( $options['headers'] ) ? $options['headers'] : array();

		return $this->request( $url, 'GET', $headers, array(), $this->resolve_timeout( $options ) );
	}

	/**
	 * @param $url
	 * @param array $options
	 *
	 * @return array
	 */
	public function post( $url, $options = array() ) {

		$headers = !empty( $options['headers'] ) ? $options['headers'] : array();
		$data = !empty( $options['data'] ) ? $options['data'] : array();

		return $this->request( $url, 'POST', $headers, $data, $this->resolve_timeout( $options ) );
	}

	/**
	 * Perform the request, following up to 3 redirects manually.
	 *
	 * The HTTP stream wrapper cannot validate redirect targets, so
	 * follow_location stays off and every Location hop is resolved and
	 * re-checked against Http::is_url_allowed() — a public endpoint
	 * answering 3xx must not drag the request into a private network.
	 * This mirrors what WP_Http_Streams does and what the WP transport
	 * gets for free from wp_safe_remote_get().
	 *
	 * @param string $url
	 * @param string $method
	 * @param array  $headers
	 * @param array  $data
	 * @param int    $timeout
	 *
	 * @return array
	 */
	private function request( $url, $method, $headers = array(), $data = array(), $timeout = self::DEFAULT_TIMEOUT ) {

		$method = strtoupper( trim( $method ) );

		// Core parity: WP_Http follows up to 5 redirects by default.
		for ( $redirects = 0; $redirects <= 5; $redirects++ ) {

			$response = $this->single_request( $url, $method, $headers, $data, $timeout );
			$status   = (int) $response['status'];

			$is_redirect = in_array( $status, array( 301, 302, 303, 307, 308 ), true );
			$location    = $is_redirect ? $this->find_header( $response['raw_headers'], 'Location' ) : null;

			if ( ! $location || ! class_exists( 'WP_Http' ) ) {
				unset( $response['raw_headers'] );

				return $response;
			}

			$next = WP_Http::make_absolute_url( $location, $url );

			if ( ! $next || $next === $url || ! Http::is_url_allowed( $next ) ) {
				return array(
					'data'    => null,
					'status'  => 0,
					'error'   => 'Redirect target rejected by the outbound URL policy.',
					'blocked' => true,
				);
			}

			// Never leak credentials across host boundaries on a redirect.
			if ( $this->url_host( $next ) !== $this->url_host( $url ) ) {
				$headers = $this->strip_sensitive_headers( $headers );
			}

			// 301/302/303 downgrade POST to GET (browser and WP semantics);
			// 307/308 preserve the method and the body.
			if ( 'POST' === $method && in_array( $status, array( 301, 302, 303 ), true ) ) {
				$method = 'GET';
				$data   = array();
				$headers = array_values( array_filter( $headers, function ( $header ) {
					return 0 !== stripos( $header, 'Content-Type' );
				} ) );
			}

			$url = $next;
		}

		return array(
			'data'   => null,
			'status' => 0,
			'error'  => 'Too many redirects.',
		);
	}

	/**
	 * Hostname of a URL, lowercased.
	 *
	 * @param string $url
	 *
	 * @return string
	 */
	private function url_host( $url ) {

		$host = wp_parse_url( $url, PHP_URL_HOST );

		return is_string( $host ) ? strtolower( $host ) : '';
	}

	/**
	 * Drop credential-bearing headers before a cross-host redirect hop.
	 *
	 * @param array $headers
	 *
	 * @return array
	 */
	private function strip_sensitive_headers( $headers ) {

		$sensitive = array( 'Authorization', 'Cookie' );

		return array_values( array_filter( $headers, function ( $header ) use ( $sensitive ) {
			$name = strtolower( trim( strtok( $header, ':' ) ) );

			return ! in_array( $name, array_map( 'strtolower', $sensitive ), true );
		} ) );
	}

	/**
	 * Single stream request, no redirect following.
	 *
	 * @param string $url
	 * @param string $method
	 * @param array  $headers
	 * @param array  $data
	 * @param int    $timeout
	 *
	 * @return array
	 */
	private function single_request( $url, $method, $headers = array(), $data = array(), $timeout = self::DEFAULT_TIMEOUT ) {

		$request_data = null;
		if( !empty( $data ) ) {
			$request_data = json_encode( $data, JSON_FORCE_OBJECT );
		}

		$context = stream_context_create( array(
			'http' => array(
                'method'        => $method,
                'header'        => implode( "\r\n", $headers ),
                'content'       => $request_data,
                'ignore_errors' => true,
                'timeout'       => $timeout,
                // Redirects are followed manually in request() so that each
                // hop can be validated — never let the wrapper follow them.
                'follow_location' => 0,
            )
		));

		$fp = @fopen( $url, 'rb', false, $context );

		$error = null;
		$status = null;
		$response = null;

		if ( !$fp ) {

			if( !empty( $http_response_header[0] ) ) {
				list(, $code, $message ) = explode( ' ', $http_response_header[0], 3 );
				$error = $message;
				$status = $code;

			} else {
				$last_err = error_get_last();
				$error = !empty( $last_err['message'] ) ? $last_err['message'] : 'Unknown error!';
			}

		} else {
			list(, $code ) = explode( ' ', $http_response_header[0], 3 );
			$status = $code;

			$response = stream_get_contents( $fp );
		}

		return array(
			'data'        => $response,
			'status'      => intval( $status ),
			'error'       => $error,
			'raw_headers' => isset( $http_response_header ) ? $http_response_header : array(),
		);
	}

	/**
	 * Case-insensitive lookup of a response header value.
	 *
	 * @param array  $raw_headers
	 * @param string $name
	 *
	 * @return string|null
	 */
	private function find_header( $raw_headers, $name ) {

		$name = strtolower( trim( $name ) );

		foreach ( (array) $raw_headers as $header ) {
			$pos = strpos( $header, ':' );

			if ( false !== $pos && strtolower( trim( substr( $header, 0, $pos ) ) ) === $name ) {
				return trim( substr( $header, $pos + 1 ) );
			}
		}

		return null;
	}
}
