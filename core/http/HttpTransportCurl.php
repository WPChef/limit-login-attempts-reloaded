<?php

namespace LLAR\Core\Http;

class HttpTransportCurl implements HttpTransportInterface {

	use HttpTimeoutTrait;
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
	 * @param $url
	 * @param $method
	 * @param array $headers
	 * @param array $data
	 * @param int $timeout
	 * @param array|null $destination validated destination from Http::resolve_destination()
	 *
	 * @return array
	 */
	private function request( $url, $method, $headers = array(), $data = array(), $timeout = self::DEFAULT_TIMEOUT, $destination = null ) {

		// Align URL host to the validated/normalized destination host so
		// CURLOPT_RESOLVE cannot miss on trailing-dot FQDNs (evil.com.).
		if ( ! empty( $destination['host'] ) ) {
			$url = $this->align_url_host_with_destination( $url, $destination );
		}

		$handle = curl_init( $url );

		curl_setopt( $handle, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $handle, CURLOPT_CONNECTTIMEOUT, $timeout );
		curl_setopt( $handle, CURLOPT_TIMEOUT, $timeout );
		// Explicit: never follow redirects. CURLOPT_RESOLVE only pins the
		// original host; a followed Location could land on a private IP.
		curl_setopt( $handle, CURLOPT_FOLLOWLOCATION, false );

		// Pin the connection to an address that was validated above instead
		// of letting curl resolve the name again (DNS rebinding).
		$resolve = $this->build_curl_resolve( $destination );
		if ( ! empty( $resolve ) ) {
			curl_setopt( $handle, CURLOPT_RESOLVE, $resolve );
		}

		if ( 'POST' === $method ) {
			$request_data = ! empty( $data ) ? Http::encode_payload( $data ) : null;

			// Unserialisable payload (e.g. invalid UTF-8 from request headers):
			// fail the request instead of sending it out with an empty body.
			if ( ! empty( $data ) && false === $request_data ) {
				curl_close( $handle );

				return Http::encoding_failure_response();
			}

			curl_setopt( $handle, CURLOPT_POST, true );
			curl_setopt( $handle, CURLOPT_POSTFIELDS, $request_data );
		}

		if ( ! empty( $headers ) ) {
			curl_setopt( $handle, CURLOPT_HTTPHEADER, $headers );
		}

		// Capture Location without CURLOPT_HEADER (keeps body clean). Needed
		// when HttpTransportWp delegates pinned hops into this transport and
		// still follows redirects via HttpRedirectFollowerTrait.
		$location = null;
		curl_setopt(
			$handle,
			CURLOPT_HEADERFUNCTION,
			function ( $ch, $line ) use ( &$location ) {
				if ( 0 === stripos( $line, 'Location:' ) ) {
					$location = trim( substr( $line, strlen( 'Location:' ) ) );
				}

				return strlen( $line );
			}
		);

		$response        = curl_exec( $handle );
		$response_status = curl_getinfo( $handle, CURLINFO_HTTP_CODE );
		$response_error  = ( false === $response ) ? curl_error( $handle ) : null;

		curl_close( $handle );

		return array(
			'data'     => $response,
			'status'   => intval( $response_status ),
			'error'    => $response_error,
			'location' => ( is_string( $location ) && '' !== $location ) ? $location : null,
		);
	}
}
