<?php

namespace LLAR\Core\Http;

/**
 * Shared manual redirect follower for transports that must not rely on
 * opaque auto-follow (stream wrapper / wp_remote_* / curl FOLLOWLOCATION).
 *
 * Using class must implement single_request() with the same signature and
 * return either `location` or `raw_headers` (fopen-style) on 3xx responses.
 */
trait HttpRedirectFollowerTrait {

	/**
	 * Follow up to 5 redirects (WP_Http default). Every Location hop is
	 * re-resolved through Http::resolve_destination() and re-pinned by the
	 * transport's single_request().
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
			$location    = null;

			if ( $is_redirect ) {
				if ( ! empty( $response['location'] ) && is_string( $response['location'] ) ) {
					$location = $response['location'];
				} elseif ( ! empty( $response['raw_headers'] ) ) {
					$location = $this->find_header( $response['raw_headers'], 'Location' );
				}
			}

			unset( $response['location'], $response['raw_headers'] );

			// Leading backslash: transports live in LLAR\Core\Http — bare
			// WP_Http would resolve to LLAR\Core\Http\WP_Http and fatal.
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

			// Re-resolve (not just re-validate): every hop is pinned to its
			// own validated IPs, so a redirect cannot rebind into a private
			// address either.
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
	 * Case-insensitive lookup of a response header value (fopen raw_headers).
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
