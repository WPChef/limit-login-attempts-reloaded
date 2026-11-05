<?php

namespace LLAR\Core\Http;

/**
 * Pin an outbound connection to IPs already validated by
 * Http::resolve_destination() — closes DNS rebinding between check and
 * connect. Used by fopen (stream context) and the WP transport (IP URL +
 * Host; Curl also gets CURLOPT_SSLOPT_PEERNAME for TLS).
 */
trait HttpDestinationPinTrait {

	/**
	 * Rewrite the URL host to a validated IP, set Host + TLS peer_name to
	 * the logical hostname. Prior Host headers are replaced, not appended.
	 *
	 * @param string     $url
	 * @param array      $headers "Name: value" lines
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
	 * IPv4 first (fewer TLS/routing surprises), any validated address otherwise.
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
