<?php

namespace LLAR\Core\Http;

class Http {

	/**
	 * error_code of a transport result whose payload could not be serialised.
	 *
	 * Request-derived payload fields can be attacker-influenced, so a failed
	 * encode must be distinguished from a genuine outage: it has to fail the
	 * caller closed instead of being treated as "service unavailable".
	 */
	const ERROR_PAYLOAD_ENCODING = 'payload_encoding_failed';

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

		switch ( true ) {
			case function_exists( 'fopen' ) && ini_get( 'allow_url_fopen' ) === '1':
				self::$transport = new HttpTransportFopen();
				break;

			case function_exists( 'wp_remote_get' ):
				self::$transport = new HttpTransportWp();
				break;

			case function_exists( 'curl_version' ):
				self::$transport = new HttpTransportCurl();
				break;

			default:
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

		if ( null === $destination ) {
			return self::unresolvable_response();
		}

		if ( false === $destination ) {
			return self::policy_rejection();
		}

		$options['destination'] = $destination;

		return self::$transport->get( $url, $options );
	}

	/**
	 * Serialise an outbound payload.
	 *
	 * Returns false when the data cannot be encoded (e.g. invalid UTF-8 from
	 * request headers) so transports can fail the request instead of sending
	 * an empty body. Deliberately json_encode() rather than wp_json_encode():
	 * the wp wrapper sanitises and retries, which would hide the failure.
	 *
	 * @param mixed $data Payload.
	 * @return string|false
	 */
	public static function encode_payload( $data ) {

		return json_encode( $data, JSON_FORCE_OBJECT );
	}

	/**
	 * The single shape every transport must return when the request payload
	 * cannot be encoded. Callers treat this as a hard failure, never as
	 * "service unavailable", so keep transports on this factory instead of
	 * hand-rolling the array.
	 *
	 * @return array
	 */
	public static function encoding_failure_response() {

		return array(
			'data'       => null,
			'status'     => 0,
			'error'      => 'Request payload encoding failed',
			'error_code' => self::ERROR_PAYLOAD_ENCODING,
		);
	}

	/**
	 * @param $url
	 * @param array $options
	 *
	 * @return mixed
	 */
	public static function post( $url, $options = array() ) {

		$destination = self::resolve_destination( $url );

		if ( null === $destination ) {
			return self::unresolvable_response();
		}

		if ( false === $destination ) {
			return self::policy_rejection();
		}

		$options['destination'] = $destination;
		$options['headers']     = array_merge(
			array( 'Content-Type: application/json; charset=utf-8' ),
			! empty( $options['headers'] ) ? $options['headers'] : array()
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

		return is_array( self::resolve_destination( $url ) );
	}

	/**
	 * Normalize a hostname: strip brackets, trailing dots, lowercase.
	 *
	 * @param string $host
	 *
	 * @return string
	 */
	public static function normalize_host( $host ) {

		if ( ! is_string( $host ) || '' === $host ) {
			return '';
		}

		return strtolower( rtrim( trim( $host, '[]' ), '.' ) );
	}

	/**
	 * Whether $host is the site's own host (core same-host policy).
	 *
	 * @param string $host already-normalized or raw
	 *
	 * @return bool
	 */
	public static function is_same_host( $host ) {

		$host = self::normalize_host( $host );
		$home = self::normalize_host( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );

		return '' !== $host && '' !== $home && $host === $home;
	}

	/**
	 * Transport-shaped policy rejection. Shared by the facade and transports
	 * so callers always see `blocked => true` with a stable error string.
	 *
	 * @param string|null $error
	 *
	 * @return array
	 */
	public static function policy_rejection( $error = null ) {

		return array(
			'data'    => null,
			'status'  => 0,
			'error'   => $error
				? $error
				: 'Destination rejected by the outbound URL policy (loopback, private networks and non-standard ports are not fetchable).',
			'blocked' => true,
		);
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
	 * @return array|false|null array on allow, false on policy/invalid, null on NXDOMAIN
	 */
	public static function resolve_destination( $url ) {

		if ( ! is_string( $url ) || '' === $url ) {
			return false;
		}

		if ( ! function_exists( 'wp_http_validate_url' ) ) {
			return false;
		}

		$parsed = wp_parse_url( $url );

		if ( empty( $parsed['host'] ) ) {
			return false;
		}

		$scheme = isset( $parsed['scheme'] ) ? strtolower( $parsed['scheme'] ) : 'http';
		$host   = self::normalize_host( $parsed['host'] );
		$port   = isset( $parsed['port'] ) ? (int) $parsed['port'] : ( 'https' === $scheme ? 443 : 80 );

		if ( '' === $host ) {
			return false;
		}

		$core_ok = (bool) wp_http_validate_url( $url );

		if ( ! $core_ok ) {
			// WordPress rejects IPv6 literals (strpbrk on ':'). Validate those
			// ourselves so public AAAA destinations stay reachable; everything
			// else that fails core is rejected before our DNS lookup.
			// Caveat: some WP HTTP transports may still refuse a bracketed
			// IPv6 authority URL — that fails closed as a transport error,
			// not a security bypass. Prefer Curl (CURLOPT_RESOLVE) for
			// hostname→AAAA pins.
			if ( ! filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
				return false;
			}

			$safe_ports = apply_filters( 'http_allowed_safe_ports', array( 80, 443, 8080 ), $host, $url );
			if ( ! is_array( $safe_ports ) || ! in_array( $port, $safe_ports, true ) ) {
				return false;
			}

			$host_is_external = (bool) apply_filters( 'http_request_host_is_external', false, $host, $url );
			if ( self::is_non_public_ip( $host ) && ! $host_is_external ) {
				return false;
			}

			return array(
				'scheme'    => $scheme,
				'host'      => $host,
				'port'      => $port,
				'ips'       => array( $host ),
				'same_host' => false,
			);
		}

		if ( self::is_same_host( $host ) ) {
			return array(
				'scheme'    => $scheme,
				'host'      => $host,
				'port'      => $port,
				'ips'       => array(),
				'same_host' => true,
			);
		}

		// One plugin-side resolution for pinning + our range check.
		// null = resolved empty despite core OK (rare resolver mismatch).
		$ips = self::resolve_host( $host );

		if ( empty( $ips ) ) {
			return null;
		}

		// Core's escape hatch for self-hosted/internal endpoints. Core only
		// consults it for its own short private list; our range check is
		// wider, so it has to honour the same filter — otherwise a site that
		// deliberately points an internal app/MFA endpoint at a container
		// name or a link-local address would be locked out with no way back.
		$host_is_external = (bool) apply_filters( 'http_request_host_is_external', false, $host, $url );

		foreach ( $ips as $ip ) {
			if ( self::is_non_public_ip( $ip ) && ! $host_is_external ) {
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

		if ( false === $bin || 16 !== strlen( $bin ) ) {
			return true;
		}

		if ( inet_pton( '::1' ) === $bin || inet_pton( '::' ) === $bin ) {
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

		// Prefix blocks (IANA special-purpose / documentation / discard / Teredo /
		// local-use NAT64). Checked before IPv4-embedded unwraps so a special
		// prefix cannot slip through as "public wrapper + public v4".
		$prefixes = array(
			array( '100::', 64 ),          // discard-only
			array( '2001:db8::', 32 ),     // documentation
			array( '2001::', 32 ),         // Teredo
			array( '64:ff9b:1::', 48 ),    // local-use NAT64
		);

		foreach ( $prefixes as $prefix ) {
			if ( self::ipv6_has_prefix( $bin, $prefix[0], $prefix[1] ) ) {
				return true;
			}
		}

		// ::/96 — "this host", loopback and the deprecated IPv4-compatible space.
		if ( substr( $bin, 0, 12 ) === str_repeat( "\0", 12 ) ) {
			return true;
		}

		// IPv4-mapped ::ffff:0:0/96 wraps an IPv4 address in the last 4 bytes.
		if ( substr( $bin, 0, 10 ) === str_repeat( "\0", 10 ) && "\xff\xff" === substr( $bin, 10, 2 ) ) {
			return self::is_non_public_ip( inet_ntop( substr( $bin, 12, 4 ) ) );
		}

		// NAT64 well-known prefix 64:ff9b::/96 wraps an IPv4 address in the last 4 bytes.
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
	 * True when $bin (16-byte IPv6) falls under $prefix/$bits.
	 *
	 * @param string $bin
	 * @param string $prefix IPv6 textual prefix
	 * @param int    $bits
	 *
	 * @return bool
	 */
	private static function ipv6_has_prefix( $bin, $prefix, $bits ) {

		$prefix_bin = @inet_pton( $prefix );

		if ( false === $prefix_bin || 16 !== strlen( $bin ) || $bits < 1 || $bits > 128 ) {
			return false;
		}

		$bytes = (int) floor( $bits / 8 );
		$rest  = $bits % 8;

		if ( $bytes > 0 && substr( $bin, 0, $bytes ) !== substr( $prefix_bin, 0, $bytes ) ) {
			return false;
		}

		if ( 0 === $rest ) {
			return true;
		}

		$mask = ( 0xff << ( 8 - $rest ) ) & 0xff;

		return ( ord( $bin[ $bytes ] ) & $mask ) === ( ord( $prefix_bin[ $bytes ] ) & $mask );
	}

	/**
	 * Transport-shaped response for NXDOMAIN / empty resolution.
	 * No `blocked` flag — CloudApp::setup() treats this as "endpoint is not
	 * responding", not as a destination-policy violation.
	 *
	 * @return array
	 */
	private static function unresolvable_response() {

		return array(
			'data'   => null,
			'status' => 0,
			'error'  => 'Could not resolve host.',
		);
	}
}
