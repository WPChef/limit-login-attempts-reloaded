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
	 * Perform the request, following up to 5 redirects manually (core parity).
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
	 * @param array|null $destination validated destination from Http::resolve_destination()
	 *
	 * @return array
	 */
	private function request( $url, $method, $headers = array(), $data = array(), $timeout = self::DEFAULT_TIMEOUT, $destination = null ) {

		$method = strtoupper( trim( $method ) );

		if ( empty( $destination ) ) {
			$destination = Http::resolve_destination( $url );

			if ( null === $destination ) {
				return array(
					'data'   => null,
					'status' => 0,
					'error'  => 'Could not resolve host.',
				);
			}

			if ( false === $destination ) {
				return Http::policy_rejection( 'Destination rejected by the outbound URL policy.' );
			}
		}

		$visited = array();

		// Core parity: WP_Http follows up to 5 redirects by default.
		for ( $redirects = 0; $redirects <= 5; $redirects++ ) {

			$key = $this->redirect_key( $url );

			if ( isset( $visited[ $key ] ) ) {
				return array(
					'data'   => null,
					'status' => 0,
					'error'  => 'Redirect loop detected.',
				);
			}

			$visited[ $key ] = true;

			$response = $this->single_request( $url, $method, $headers, $data, $timeout, $destination );
			$status   = (int) $response['status'];

			$is_redirect = in_array( $status, array( 301, 302, 303, 307, 308 ), true );
			$location    = $is_redirect ? $this->find_header( $response['raw_headers'], 'Location' ) : null;

			// Leading backslash: this file is in LLAR\Core\Http — bare WP_Http
			// would resolve to LLAR\Core\Http\WP_Http and fatal.
			if ( ! $location || ! class_exists( '\WP_Http' ) ) {
				unset( $response['raw_headers'] );

				return $response;
			}

			$next = \WP_Http::make_absolute_url( $location, $url );

			if ( ! $next ) {
				return Http::policy_rejection( 'Redirect target rejected by the outbound URL policy.' );
			}

			$next_key = $this->redirect_key( $next );

			if ( isset( $visited[ $next_key ] ) ) {
				return array(
					'data'   => null,
					'status' => 0,
					'error'  => 'Redirect loop detected.',
				);
			}

			// Re-resolve (not just re-validate): every hop is pinned to its
			// own validated IPs, so a redirect cannot be used to re-resolve
			// the name into a private address either.
			$next_destination = Http::resolve_destination( $next );

			if ( null === $next_destination ) {
				return array(
					'data'   => null,
					'status' => 0,
					'error'  => 'Could not resolve host.',
				);
			}

			if ( false === $next_destination ) {
				return Http::policy_rejection( 'Redirect target rejected by the outbound URL policy.' );
			}

			// Never leak credentials across host boundaries on a redirect.
			if ( $this->url_host( $next ) !== $this->url_host( $url ) ) {
				$headers = $this->strip_sensitive_headers( $headers );
			}

			// 301/302/303 downgrade POST to GET (browser and WP semantics);
			// 307/308 preserve the method and the body.
			if ( 'POST' === $method && in_array( $status, array( 301, 302, 303 ), true ) ) {
				$method  = 'GET';
				$data    = array();
				$headers = array_values(
					array_filter(
						$headers,
						function ( $header ) {
							return 0 !== stripos( $header, 'Content-Type' );
						}
					)
				);
			}

			$url         = $next;
			$destination = $next_destination;
		}

		return array(
			'data'   => null,
			'status' => 0,
			'error'  => 'Too many redirects.',
		);
	}

	/**
	 * Normalized redirect identity: scheme/host/port/path/query, no fragment.
	 *
	 * @param string $url
	 *
	 * @return string
	 */
	private function redirect_key( $url ) {

		$parsed = wp_parse_url( $url );

		if ( ! is_array( $parsed ) || empty( $parsed['host'] ) ) {
			return (string) $url;
		}

		$scheme = isset( $parsed['scheme'] ) ? strtolower( $parsed['scheme'] ) : 'http';
		$host   = Http::normalize_host( $parsed['host'] );
		$port   = isset( $parsed['port'] ) ? (int) $parsed['port'] : ( 'https' === $scheme ? 443 : 80 );
		$path   = isset( $parsed['path'] ) ? $parsed['path'] : '';
		$query  = isset( $parsed['query'] ) ? $parsed['query'] : '';

		return $scheme . '://' . $host . ':' . $port . $path . ( '' !== $query ? '?' . $query : '' );
	}

	/**
	 * Hostname of a URL, normalized.
	 *
	 * @param string $url
	 *
	 * @return string
	 */
	private function url_host( $url ) {

		$host = wp_parse_url( $url, PHP_URL_HOST );

		return is_string( $host ) ? Http::normalize_host( $host ) : '';
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

		return array_values(
			array_filter(
				$headers,
				function ( $header ) use ( $sensitive ) {
					$name = strtolower( trim( strtok( $header, ':' ) ) );

					return ! in_array( $name, array_map( 'strtolower', $sensitive ), true );
				}
			)
		);
	}

	/**
	 * Single stream request, no redirect following.
	 *
	 * @param string $url
	 * @param string $method
	 * @param array  $headers
	 * @param array  $data
	 * @param int    $timeout
	 * @param array|null $destination validated destination to pin the connection to
	 *
	 * @return array
	 */
	private function single_request( $url, $method, $headers = array(), $data = array(), $timeout = self::DEFAULT_TIMEOUT, $destination = null ) {

		$request_data = null;
		if ( ! empty( $data ) ) {
			$request_data = json_encode( $data, JSON_FORCE_OBJECT );
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
