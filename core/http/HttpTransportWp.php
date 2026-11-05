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
	 * Pinning strategy (must preserve TLS identity on HTTPS):
	 * - Hostname destination + Curl available → keep hostname URL, pin via
	 *   CURLOPT_RESOLVE, force Curl transport for this request only.
	 * - Hostname destination + HTTPS + no Curl (Streams-only) → fail closed;
	 *   Streams cannot set a TLS peer name for an IP-rewritten URL.
	 * - Hostname destination + HTTP + no Curl → IP URL + Host (fopen-style).
	 * - Literal IP destination → URL as-is (no DNS rebinding window).
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

		$request_url     = $url;
		$curl_hook       = null;
		$transports_hook = null;
		$pinning         = ! empty( $destination['ips'] );
		$host_is_literal = $pinning && filter_var( $destination['host'], FILTER_VALIDATE_IP );
		$curl_available  = function_exists( 'curl_init' );
		$is_https        = ( is_array( $destination ) && ! empty( $destination['scheme'] ) )
			? ( 'https' === $destination['scheme'] )
			: ( 0 === stripos( (string) $url, 'https://' ) );

		if ( $pinning && ! $host_is_literal ) {
			if ( $curl_available ) {
				// Keep the logical hostname in the URL so Curl verifies the
				// certificate against it; pin the dial address with RESOLVE.
				$resolve = $this->build_curl_resolve( $destination );

				// Force Curl for this pinned request only. Streams would
				// re-resolve the hostname and reopen a DNS-rebinding window;
				// the filter is removed in finally below, so site-wide
				// transport order is otherwise untouched.
				$transports_hook = function ( $transports ) {
					$transports = (array) $transports;
					if ( in_array( 'curl', $transports, true ) ) {
						return array( 'curl' );
					}

					return $transports;
				};
				add_filter( 'http_api_transports', $transports_hook, 999 );

				if ( ! empty( $resolve ) ) {
					$curl_hook = function ( $handle ) use ( $resolve ) {
						if ( is_resource( $handle ) || ( is_object( $handle ) && 'CurlHandle' === get_class( $handle ) ) ) {
							curl_setopt( $handle, CURLOPT_RESOLVE, $resolve );
						}
					};
					add_action( 'http_api_curl', $curl_hook, 10, 1 );
				}
			} elseif ( $is_https ) {
				// Streams/fsockopen cannot pin HTTPS safely (no peer_name for
				// an IP-rewritten URL). Fail closed rather than TOCTOU or
				// certificate-mismatch against the IP.
				return array(
					'data'    => null,
					'status'  => 0,
					'error'   => 'Cannot pin HTTPS destination without the Curl transport.',
					'blocked' => true,
				);
			} else {
				// Plain HTTP over Streams: IP authority + Host is safe (no TLS).
				list( $request_url, $headers ) = $this->apply_destination_pin( $url, $headers, $destination );
			}
		}

		$args = array(
			'method'      => $method,
			'headers'     => $this->format_headers( $headers ),
			'body'        => $body,
			'timeout'     => $timeout,
			// Never let core follow Location — its validator still allows
			// link-local metadata on older WordPress.
			'redirection' => 0,
		);

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
