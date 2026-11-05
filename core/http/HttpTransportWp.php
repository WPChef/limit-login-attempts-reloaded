<?php

namespace LLAR\Core\Http;

class HttpTransportWp implements HttpTransportInterface {

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

		$headers     = ! empty( $options['headers'] ) ? $options['headers'] : array();
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

		$headers     = ! empty( $options['headers'] ) ? $options['headers'] : array();
		$data        = ! empty( $options['data'] ) ? $options['data'] : array();
		$destination = ! empty( $options['destination'] ) ? $options['destination'] : null;

		return $this->request( $url, 'POST', $headers, $data, $this->resolve_timeout( $options ), $destination );
	}

	/**
	 * Single WP HTTP request, no redirect following.
	 *
	 * Pinning matches fopen: connect to the validated IP, send Host for the
	 * logical name. That closes DNS rebinding on Streams/fsockopen as well as
	 * Curl. When Curl is available we also force it (avoids Streams) and set
	 * CURLOPT_SSLOPT_PEERNAME so TLS still verifies the logical hostname.
	 *
	 * Manual redirect policy lives in HttpRedirectFollowerTrait.
	 *
	 * @param string     $url
	 * @param string     $method
	 * @param array      $headers
	 * @param array      $data
	 * @param int        $timeout
	 * @param array|null $destination
	 *
	 * @return array
	 */
	private function single_request( $url, $method, $headers = array(), $data = array(), $timeout = self::DEFAULT_TIMEOUT, $destination = null ) {

		$body = null;

		if ( 'POST' === $method ) {
			$body = ! empty( $data ) ? Http::encode_payload( $data ) : null;

			if ( ! empty( $data ) && false === $body ) {
				return Http::encoding_failure_response();
			}
		} elseif ( ! empty( $data ) ) {
			$query = http_build_query( $data );
			$url  .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . $query;
		}

		list( $request_url, $headers, $ssl_options ) = $this->apply_destination_pin( $url, $headers, $destination );

		$args = array(
			'method'      => $method,
			'headers'     => $this->format_headers( $headers ),
			'body'        => $body,
			'timeout'     => $timeout,
			// Never let core follow Location — its validator still allows
			// link-local metadata on older WordPress.
			'redirection' => 0,
		);

		$peer_name       = ! empty( $ssl_options['peer_name'] ) ? $ssl_options['peer_name'] : null;
		$pinning         = ! empty( $destination['ips'] );
		$curl_hook       = null;
		$transports_hook = null;

		if ( $pinning ) {
			// Prefer Curl when present so Streams is not the path that
			// reconnects; IP+Host pin still applies if only Streams remains.
			$transports_hook = function ( $transports ) {
				$transports = (array) $transports;
				if ( in_array( 'curl', $transports, true ) ) {
					return array( 'curl' );
				}

				return $transports;
			};
			add_filter( 'http_api_transports', $transports_hook, 999 );

			if ( $peer_name ) {
				// URL authority is an IP after pin — tell Curl which name to
				// verify on the certificate (PHP 7.0.7+ / libcurl peername).
				$curl_hook = function ( $handle ) use ( $peer_name ) {
					if ( ! is_resource( $handle ) && ! ( is_object( $handle ) && 'CurlHandle' === get_class( $handle ) ) ) {
						return;
					}
					if ( defined( 'CURLOPT_SSLOPT_PEERNAME' ) ) {
						curl_setopt( $handle, CURLOPT_SSLOPT_PEERNAME, $peer_name );
					}
				};
				add_action( 'http_api_curl', $curl_hook, 10, 1 );
			}
		}

		try {
			$response = wp_remote_request( $request_url, $args );
		} finally {
			if ( null !== $curl_hook ) {
				remove_action( 'http_api_curl', $curl_hook, 10 );
			}
			if ( null !== $transports_hook ) {
				remove_filter( 'http_api_transports', $transports_hook, 999 );
			}
		}

		return $this->prepare_response( $response );
	}

	/**
	 * @param mixed $response
	 *
	 * @return array
	 */
	private function prepare_response( $response ) {

		$return = array(
			'data'     => null,
			'status'   => 0,
			'error'    => null,
			'location' => null,
		);

		if ( is_wp_error( $response ) ) {
			$return['error'] = $response->get_error_message();

			return $return;
		}

		$return['data']     = wp_remote_retrieve_body( $response );
		$return['status']   = intval( wp_remote_retrieve_response_code( $response ) );
		$return['location'] = wp_remote_retrieve_header( $response, 'location' );

		if ( is_array( $return['location'] ) ) {
			$return['location'] = reset( $return['location'] );
		}

		$return['location'] = is_string( $return['location'] ) && '' !== $return['location']
			? $return['location']
			: null;

		return $return;
	}

	/**
	 * @param array $headers
	 *
	 * @return array
	 */
	private function format_headers( $headers = array() ) {

		$formatted_headers = array();

		if ( ! empty( $headers ) ) {
			foreach ( $headers as $header ) {
				$parts = explode( ':', $header, 2 );

				if ( 2 !== count( $parts ) ) {
					continue;
				}

				$formatted_headers[ trim( $parts[0] ) ] = trim( $parts[1] );
			}
		}

		return $formatted_headers;
	}
}
