<?php

namespace LLAR\Core\Http;

class HttpTransportWp implements HttpTransportInterface {

	use HttpTimeoutTrait;

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
	 * Perform the request, following up to 5 redirects manually (core parity).
	 *
	 * wp_remote_* / wp_safe_remote_* would follow Location via
	 * wp_http_validate_url(), which still allows 169.254.169.254 on
	 * WordPress 5.0–6.5. With allow_url_fopen=0 this transport is the
	 * default — so every hop is re-resolved through Http::resolve_destination()
	 * and pinned (CURLOPT_RESOLVE via http_api_curl) the same way fopen does.
	 *
	 * @param string     $url
	 * @param string     $method
	 * @param array      $headers
	 * @param array      $data
	 * @param int        $timeout
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
			$location    = $is_redirect ? $response['location'] : null;
			unset( $response['location'] );

			// Leading backslash: this file is in LLAR\Core\Http — bare WP_Http
			// would resolve to LLAR\Core\Http\WP_Http and fatal.
			if ( ! $location || ! class_exists( '\WP_Http' ) ) {
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

			if ( $this->url_host( $next ) !== $this->url_host( $url ) ) {
				$headers = $this->strip_sensitive_headers( $headers );
			}

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
	 * Single WP HTTP request, no redirect following. Pins via CURLOPT_RESOLVE
	 * when WP's Curl transport runs (the usual path with allow_url_fopen=0).
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
	 * @param string $url
	 *
	 * @return string
	 */
	private function url_host( $url ) {

		$host = wp_parse_url( $url, PHP_URL_HOST );

		return is_string( $host ) ? Http::normalize_host( $host ) : '';
	}

	/**
	 * @param array $headers
	 *
	 * @return array
	 */
	private function strip_sensitive_headers( $headers ) {

		$sensitive = array( 'authorization', 'cookie' );

		return array_values(
			array_filter(
				$headers,
				function ( $header ) use ( $sensitive ) {
					$name = strtolower( trim( strtok( $header, ':' ) ) );

					return ! in_array( $name, $sensitive, true );
				}
			)
		);
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
