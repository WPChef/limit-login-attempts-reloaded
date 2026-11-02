<?php

namespace LLAR\Core\Http;

class HttpTransportCurl implements HttpTransportInterface {

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
			curl_setopt( $handle, CURLOPT_POST, true );
			curl_setopt( $handle, CURLOPT_POSTFIELDS, json_encode( $data, JSON_FORCE_OBJECT ) );
		}

		if ( ! empty( $headers ) ) {
			curl_setopt( $handle, CURLOPT_HTTPHEADER, $headers );
		}

		$response        = curl_exec( $handle );
		$response_status = curl_getinfo( $handle, CURLINFO_HTTP_CODE );
		$response_error  = ( false === $response ) ? curl_error( $handle ) : null;

		curl_close( $handle );

		return array(
			'data'   => $response,
			'status' => intval( $response_status ),
			'error'  => $response_error,
		);
	}

	/**
	 * CURLOPT_RESOLVE entries for a validated destination.
	 * IPv6 addresses must be bracketed (host:port:[addr]), otherwise curl
	 * misparses the address and the pin is ineffective.
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
}
