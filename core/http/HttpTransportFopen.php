<?php

namespace LLAR\Core\Http;

class HttpTransportFopen implements HttpTransportInterface {

	use HttpTimeoutTrait;
	use HttpRedirectFollowerTrait;

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

	/**
	 * Pin the connection to a validated address instead of letting the
	 * stream wrapper resolve the name again (DNS rebinding). Rewrites the
	 * URL host to the IP, sets Host + TLS peer_name to the logical hostname.
	 *
	 * Prior Host headers are replaced, not appended — redirect hops must
	 * not stack Host (RFC 7230: more than one Host → 400).
	 *
	 * Port in the rewritten URL and in Host use the same scheme-default
	 * rule (omit only the scheme's default port), so https:80 / http:443
	 * stay consistent.
	 *
	 * @param string     $url
	 * @param array      $headers
	 * @param array|null $destination
	 *
	 * @return array{0:string,1:array,2:array} request_url, headers, ssl_options
	 */
	private function apply_destination_pin( $url, $headers, $destination ) {

		if ( empty( $destination['ips'] ) ) {
			return array( $url, $headers, array() );
		}

		$ip          = $this->preferred_ip( $destination['ips'] );
		$ip_host     = false !== strpos( $ip, ':' ) ? '[' . $ip . ']' : $ip;
		$request_url = $this->build_pinned_url( $url, $destination, $ip_host );

		$default_port = ( 'https' === $destination['scheme'] ) ? 443 : 80;
		$port_suffix  = ( (int) $destination['port'] === $default_port ) ? '' : ':' . $destination['port'];

		// Replace any prior Host — never stack across redirect hops.
		$headers   = array_values(
			array_filter(
				$headers,
				function ( $header ) {
					return 0 !== stripos( ltrim( $header ), 'Host:' );
				}
			)
		);
		$headers[] = 'Host: ' . $destination['host'] . $port_suffix;

		return array( $request_url, $headers, array( 'peer_name' => $destination['host'] ) );
	}

	/**
	 * Rebuild the request URL with the pinned IP as authority, keeping
	 * path/query/fragment from the logical URL. No regex on authority.
	 *
	 * @param string $url
	 * @param array  $destination
	 * @param string $ip_host IPv4 or bracketed IPv6 literal
	 *
	 * @return string
	 */
	private function build_pinned_url( $url, $destination, $ip_host ) {

		$parsed       = wp_parse_url( $url );
		$scheme       = $destination['scheme'];
		$default_port = ( 'https' === $scheme ) ? 443 : 80;
		$authority    = $ip_host;

		if ( (int) $destination['port'] !== $default_port ) {
			$authority .= ':' . $destination['port'];
		}

		$out = $scheme . '://' . $authority;

		if ( is_array( $parsed ) ) {
			if ( ! empty( $parsed['path'] ) ) {
				$out .= $parsed['path'];
			}
			if ( isset( $parsed['query'] ) ) {
				$out .= '?' . $parsed['query'];
			}
			if ( isset( $parsed['fragment'] ) ) {
				$out .= '#' . $parsed['fragment'];
			}
		}

		return $out;
	}

	/**
	 * Pick the address to connect to: IPv4 first (fewer TLS/routing
	 * surprises), any validated address otherwise.
	 *
	 * @param array $ips
	 *
	 * @return string
	 */
	private function preferred_ip( $ips ) {

		foreach ( $ips as $ip ) {
			if ( false === strpos( $ip, ':' ) ) {
				return $ip;
			}
		}

		return reset( $ips );
	}
}
