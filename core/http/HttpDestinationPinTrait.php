<?php

namespace LLAR\Core\Http;

/**
 * Pin an outbound connection to IPs already validated by
 * Http::resolve_destination() — closes DNS rebinding between check and
 * connect.
 *
 * - fopen / WP Streams (plain HTTP): IP authority + Host + peer_name.
 * - Curl / WP+Curl (HTTPS or HTTP): keep hostname URL, pin via
 *   CURLOPT_RESOLVE (TLS verifies the logical name, not the dial IP).
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
	 * Rebuild the URL authority to the validated destination host/port.
	 *
	 * CURLOPT_RESOLVE matches the URL host literally. normalize_host() strips
	 * trailing dots / case / brackets into destination['host'], so leaving a
	 * raw FQDN like `evil.com.` in the request URL makes the pin miss and Curl
	 * re-resolves (DNS rebinding). Aligning the URL host closes that gap while
	 * keeping a hostname authority for TLS.
	 *
	 * @param string     $url
	 * @param array|null $destination
	 *
	 * @return string
	 */
	private function align_url_host_with_destination( $url, $destination ) {

		if ( empty( $destination['host'] ) || empty( $destination['scheme'] ) ) {
			return $url;
		}

		$host = $destination['host'];
		if ( filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$host = '[' . $host . ']';
		}

		return $this->build_pinned_url( $url, $destination, $host );
	}

	/**
	 * @param string $url
	 * @param array  $destination
	 * @param string $ip_host IPv4, bracketed IPv6, or hostname authority
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

	/**
	 * CURLOPT_RESOLVE entries for a validated destination.
	 * IPv6 addresses must be bracketed (host:port:[addr]), otherwise curl
	 * misparses the address and the pin is ineffective. Hostname stays in the
	 * request URL so TLS verifies the certificate against the logical name.
	 *
	 * @param array|null $destination
	 *
	 * @return array
	 */
	private function build_curl_resolve( $destination ) {

		if ( empty( $destination['ips'] ) || empty( $destination['host'] ) ) {
			return array();
		}

		// Literal IP hosts need no RESOLVE — the URL already targets that address.
		if ( filter_var( $destination['host'], FILTER_VALIDATE_IP ) ) {
			return array();
		}

		$resolve = array();
		$host    = $destination['host'];

		// Curl expects bracketed host labels for IPv6-looking names in RESOLVE
		// when the URL host is bracketed; our normalize_host strips brackets.
		foreach ( $destination['ips'] as $ip ) {
			$pinned    = ( false !== strpos( $ip, ':' ) ) ? '[' . $ip . ']' : $ip;
			$resolve[] = $host . ':' . $destination['port'] . ':' . $pinned;
		}

		return $resolve;
	}
}
