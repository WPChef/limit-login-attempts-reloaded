<?php

namespace LLAR\Core\Http;

class Http {

	/**
	 * @var HttpTransportInterface
	 */
	private static $transport;

	/**
	 * IPv4 ranges that must never be fetched: this address, private,
	 * link-local (incl. cloud metadata 169.254.169.254), CGNAT, documentation,
	 * benchmarking, multicast and reserved space.
	 *
	 * @var array
	 */
	private static $blocked_ipv4_ranges = array(
		array( '0.0.0.0', '0.255.255.255' ),         // "this" network
		array( '10.0.0.0', '10.255.255.255' ),        // private
		array( '100.64.0.0', '100.127.255.255' ),     // CGNAT
		array( '127.0.0.0', '127.255.255.255' ),      // loopback
		array( '169.254.0.0', '169.254.255.255' ),    // link-local, cloud metadata
		array( '172.16.0.0', '172.31.255.255' ),      // private
		array( '192.0.0.0', '192.0.0.255' ),          // IETF protocol assignments
		array( '192.0.2.0', '192.0.2.255' ),          // TEST-NET-1
		array( '192.88.99.0', '192.88.99.255' ),      // 6to4 relay anycast
		array( '192.168.0.0', '192.168.255.255' ),    // private
		array( '198.18.0.0', '198.19.255.255' ),      // benchmarking
		array( '198.51.100.0', '198.51.100.255' ),    // TEST-NET-2
		array( '203.0.113.0', '203.0.113.255' ),      // TEST-NET-3
		array( '224.0.0.0', '239.255.255.255' ),      // multicast
		array( '240.0.0.0', '255.255.255.255' ),      // reserved, broadcast
	);

	/**
	 * @throws \Exception
	 */
	public static function init() {

		if( function_exists( 'fopen' ) && ini_get( 'allow_url_fopen' ) === '1' ) {
			self::$transport = new HttpTransportFopen();
		} else if( function_exists( 'wp_remote_get' ) ) {
			self::$transport = new HttpTransportWp();
		} else if( function_exists( 'curl_version' ) ) {
			self::$transport = new HttpTransportCurl();
		} else {
			throw new \Exception( 'Unable to determine HTTP transport.' );
		}

	}

	/**
	 * @param $url
	 * @param array $options
	 *
	 * @return mixed
	 */
	public static function get( $url, $options = array() ) {

		$destination = self::resolve_destination( $url );

		if ( ! $destination ) {
			return self::blocked_url_response();
		}

		$options['destination'] = $destination;

		return self::$transport->get( $url, $options );
	}

	/**
	 * @param $url
	 * @param array $options
	 *
	 * @return mixed
	 */
	public static function post( $url, $options = array() ) {

		$destination = self::resolve_destination( $url );

		if ( ! $destination ) {
			return self::blocked_url_response();
		}

		$options['destination'] = $destination;
		$options['headers'] = array_merge(
			array( 'Content-Type: application/json; charset=utf-8' ),
			!empty( $options['headers'] ) ? $options['headers'] : array()
		);

		return self::$transport->post( $url, $options );
	}

	/**
	 * Outbound URL policy (SSRF guard) — the single source of truth.
	 *
	 * @param string $url
	 *
	 * @return bool
	 */
	public static function is_url_allowed( $url ) {

		return false !== self::resolve_destination( $url );
	}

	/**
	 * Validate an outbound destination and resolve it to the exact IPs the
	 * transport should connect to.
	 *
	 * Two layers:
	 *
	 * 1. wp_http_validate_url() — scheme, embedded credentials, port
	 *    whitelist (80/443/8080), same-host allowance and the core filters
	 *    (`http_request_host_is_external`, `http_allowed_safe_ports`) that
	 *    self-hosted setups use as the escape hatch.
	 *
	 * 2. Our own range check over EVERY A and AAAA record. Core only rejects
	 *    0/8, 10/8, 127/8, 172.16/12 and 192.168/16 (still true in 6.5), so
	 *    link-local 169.254.0.0/16 — the cloud metadata endpoint — and the
	 *    rest of the IANA special-purpose space would otherwise pass. Doing
	 *    this ourselves keeps the policy independent of the WordPress version
	 *    the site happens to run.
	 *
	 * The returned IP list is what closes DNS rebinding: the transport pins
	 * the connection to these addresses (CURLOPT_RESOLVE / rewritten URL +
	 * Host + TLS peer name) instead of resolving the name a second time.
	 *
	 * The site's own host is allowed (core parity — self-hosted setups talk
	 * to themselves); no pinning is applied there.
	 *
	 * Fail-closed: anything that cannot be validated is not fetchable.
	 *
	 * @param string $url
	 *
	 * @return array|false array( 'scheme', 'host', 'port', 'ips', 'same_host' )
	 */
	public static function resolve_destination( $url ) {

		if ( ! is_string( $url ) || '' === $url ) {
			return false;
		}

		if ( ! function_exists( 'wp_http_validate_url' ) || ! wp_http_validate_url( $url ) ) {
			return false;
		}

		$parsed = wp_parse_url( $url );

		if ( empty( $parsed['host'] ) ) {
			return false;
		}

		$scheme = isset( $parsed['scheme'] ) ? strtolower( $parsed['scheme'] ) : 'http';
		$host   = strtolower( rtrim( trim( $parsed['host'], '[]' ), '.' ) );
		$port   = isset( $parsed['port'] ) ? (int) $parsed['port'] : ( 'https' === $scheme ? 443 : 80 );

		if ( '' === $host ) {
			return false;
		}

		$home_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		$same_host = ( '' !== $home_host && $host === $home_host );

		if ( $same_host ) {
			return array(
				'scheme'    => $scheme,
				'host'      => $host,
				'port'      => $port,
				'ips'       => array(),
				'same_host' => true,
			);
		}

		$ips = self::resolve_host( $host );

		if ( empty( $ips ) ) {
			return false;
		}

		foreach ( $ips as $ip ) {
			if ( self::is_non_public_ip( $ip ) ) {
				return false;
			}
		}

		return array(
			'scheme'    => $scheme,
			'host'      => $host,
			'port'      => $port,
			'ips'       => $ips,
			'same_host' => false,
		);
	}

	/**
	 * Resolve a hostname to every A and AAAA record it publishes.
	 * A single record is not enough: a name with a public A and a loopback
	 * AAAA (or several A records, one of them private) must be rejected as a
	 * whole.
	 *
	 * @param string $host
	 *
	 * @return array
	 */
	public static function resolve_host( $host ) {

		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return array( $host );
		}

		$ips = array();

		if ( function_exists( 'dns_get_record' ) ) {
			foreach ( (array) @dns_get_record( $host, DNS_A | DNS_AAAA ) as $record ) {
				if ( ! empty( $record['ip'] ) ) {
					$ips[] = $record['ip'];
				}
				if ( ! empty( $record['ipv6'] ) ) {
					$ips[] = $record['ipv6'];
				}
			}
		}

		if ( empty( $ips ) ) {
			$ip = gethostbyname( $host );

			if ( $ip !== $host ) {
				$ips[] = $ip;
			}
		}

		return array_values( array_unique( $ips ) );
	}

	/**
	 * @param string $ip
	 *
	 * @return bool
	 */
	public static function is_non_public_ip( $ip ) {

		$long = ip2long( $ip );

		if ( false !== $long ) {
			foreach ( self::$blocked_ipv4_ranges as $range ) {
				if ( $long >= ip2long( $range[0] ) && $long <= ip2long( $range[1] ) ) {
					return true;
				}
			}

			return false;
		}

		$bin = @inet_pton( $ip );

		if ( false === $bin ) {
			return true;
		}

		if ( $bin === inet_pton( '::1' ) || $bin === inet_pton( '::' ) ) {
			return true;
		}

		$first = ord( $bin[0] );

		// fc00::/7 unique local, fe80::/10 link-local.
		if ( 0xfc === $first || 0xfd === $first ) {
			return true;
		}
		if ( 0xfe === $first && 0x80 === ( ord( $bin[1] ) & 0xc0 ) ) {
			return true;
		}

		// ::/96 — "this host", loopback and the deprecated IPv4-compatible space.
		if ( substr( $bin, 0, 12 ) === str_repeat( "\0", 12 ) ) {
			return true;
		}

		// IPv4-mapped ::ffff:0:0/96 wraps an IPv4 address in the last 4 bytes.
		if ( substr( $bin, 0, 10 ) === str_repeat( "\0", 10 ) && "\xff\xff" === substr( $bin, 10, 2 ) ) {
			return self::is_non_public_ip( inet_ntop( substr( $bin, 12, 4 ) ) );
		}

		// NAT64 64:ff9b::/96 wraps an IPv4 address in the last 4 bytes.
		if ( "\x00\x64\xff\x9b" === substr( $bin, 0, 4 )
			&& substr( $bin, 4, 8 ) === str_repeat( "\0", 8 ) ) {
			return self::is_non_public_ip( inet_ntop( substr( $bin, 12, 4 ) ) );
		}

		// 6to4 2002::/16 embeds the IPv4 address in bytes 2-5.
		if ( 0x20 === ord( $bin[0] ) && 0x02 === ord( $bin[1] ) ) {
			return self::is_non_public_ip( inet_ntop( substr( $bin, 2, 4 ) ) );
		}

		return false;
	}

	/**
	 * Transport-shaped response for a destination the site must not contact.
	 * Carries a `blocked` flag so callers can tell a policy rejection from
	 * a transport failure. The error string deliberately repeats no URL:
	 * it may flow into error_log, and the URL is attacker-influenced input.
	 *
	 * @return array
	 */
	private static function blocked_url_response() {

		return array(
			'data'    => null,
			'status'  => 0,
			'error'   => 'Destination rejected by the outbound URL policy (loopback, private networks and non-standard ports are not fetchable).',
			'blocked' => true,
		);
	}
}
