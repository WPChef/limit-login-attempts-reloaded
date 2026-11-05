<?php

namespace LLAR\Core\Http;

class HttpTransportWp implements HttpTransportInterface {

	use HttpTimeoutTrait;
	use HttpRedirectFollowerTrait;

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
	 * Single WP HTTP request, no redirect following. Pins via CURLOPT_RESOLVE
	 * when WP's Curl transport runs (the usual path with allow_url_fopen=0).
	 *
	 * Manual redirect policy lives in HttpRedirectFollowerTrait — WP must not
	 * auto-follow via wp_safe_remote_* (core still allows 169.254 on older WP).
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

		$args = array(
			'method'      => $method,
			'headers'     => $this->format_headers( $headers ),
			'body'        => $body,
			'timeout'     => $timeout,
			// Never let core follow Location — its validator still allows
			// link-local metadata on older WordPress.
			'redirection' => 0,
		);

		$resolve = $this->build_curl_resolve( $destination );
		$pin     = null;

		if ( ! empty( $resolve ) ) {
			// Scoped to this wp_remote_request only (added/removed around the
			// call). Nested WP HTTP from filters during the same request would
			// inherit the pin — keep resolve host-specific so foreign URLs are
			// unaffected. Pin applies when WP selects Curl; Streams fallback
			// has no CURLOPT_RESOLVE (redirect SSRF still blocked above).
			$pin = function ( $handle ) use ( $resolve ) {
				if ( is_resource( $handle ) || ( is_object( $handle ) && 'CurlHandle' === get_class( $handle ) ) ) {
					curl_setopt( $handle, CURLOPT_RESOLVE, $resolve );
				}
			};
			add_action( 'http_api_curl', $pin, 10, 1 );
		}

		try {
			$response = wp_remote_request( $url, $args );
		} finally {
			if ( null !== $pin ) {
				remove_action( 'http_api_curl', $pin, 10 );
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
	 * CURLOPT_RESOLVE entries for a validated destination.
	 *
	 * @param array|null $destination
	 *
	 * @return array
	 */
	private function build_curl_resolve( $destination ) {

		if ( empty( $destination['ips'] ) || empty( $destination['host'] ) ) {
			return array();
		}

		$resolve = array();

		foreach ( $destination['ips'] as $ip ) {
			$pinned    = ( false !== strpos( $ip, ':' ) ) ? '[' . $ip . ']' : $ip;
			$resolve[] = $destination['host'] . ':' . $destination['port'] . ':' . $pinned;
		}

		return $resolve;
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
