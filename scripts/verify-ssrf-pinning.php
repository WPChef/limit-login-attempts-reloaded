<?php
/**
 * Local regression harness for PR #344 pinning fixes.
 * Run: php scripts/verify-ssrf-pinning.php
 *
 * Stubs the WP helpers the transport touches; does not open sockets.
 */

namespace LLAR\Core\Http {

	if ( ! function_exists( __NAMESPACE__ . '\\wp_parse_url' ) ) {
		// Fall through to global stubs below.
	}
}

namespace {

	if ( ! function_exists( 'wp_parse_url' ) ) {
		function wp_parse_url( $url, $component = -1 ) {
			$parts = parse_url( $url );
			if ( false === $parts ) {
				return -1 === $component ? false : null;
			}
			if ( -1 === $component ) {
				return $parts;
			}
			$map = array(
				PHP_URL_SCHEME   => 'scheme',
				PHP_URL_HOST     => 'host',
				PHP_URL_PORT     => 'port',
				PHP_URL_PATH     => 'path',
				PHP_URL_QUERY    => 'query',
				PHP_URL_FRAGMENT => 'fragment',
			);
			$key = isset( $map[ $component ] ) ? $map[ $component ] : null;
			return ( $key && isset( $parts[ $key ] ) ) ? $parts[ $key ] : null;
		}
	}

	require_once dirname( __DIR__ ) . '/core/http/HttpTimeoutTrait.php';
	require_once dirname( __DIR__ ) . '/core/http/HttpTransportInterface.php';
	require_once dirname( __DIR__ ) . '/core/http/HttpTransportFopen.php';
	require_once dirname( __DIR__ ) . '/core/http/HttpTransportCurl.php';

	use LLAR\Core\Http\HttpTransportFopen;
	use LLAR\Core\Http\HttpTransportCurl;

	$failures = 0;

	function assert_true( $cond, $msg ) {
		global $failures;
		if ( ! $cond ) {
			echo "FAIL: $msg\n";
			$failures++;
		} else {
			echo "OK:   $msg\n";
		}
	}

	$fopen = new HttpTransportFopen();
	$ref   = new ReflectionClass( $fopen );
	$pin   = $ref->getMethod( 'apply_destination_pin' );
	$pin->setAccessible( true );

	$dest_a = array(
		'scheme' => 'https',
		'host'   => 'a.example',
		'port'   => 443,
		'ips'    => array( '203.0.113.10' ),
	);
	$dest_b = array(
		'scheme' => 'https',
		'host'   => 'b.example',
		'port'   => 443,
		'ips'    => array( '203.0.113.20' ),
	);

	list( $url1, $headers1 ) = $pin->invoke( $fopen, 'https://a.example/setup', array(), $dest_a );
	list( $url2, $headers2 ) = $pin->invoke( $fopen, 'https://b.example/next', $headers1, $dest_b );

	$host_headers = array_values( array_filter( $headers2, function ( $h ) {
		return 0 === stripos( ltrim( $h ), 'Host:' );
	} ) );

	assert_true( 1 === count( $host_headers ), 'redirect hop keeps a single Host header' );
	assert_true( 'Host: b.example' === $host_headers[0], 'Host matches the current hop hostname' );
	assert_true( false !== strpos( $url2, '203.0.113.20' ), 'URL pinned to hop B address' );

	// Scheme-aware port: https on :80 must keep :80 in both URL and Host.
	$dest_https_80 = array(
		'scheme' => 'https',
		'host'   => 'odd.example',
		'port'   => 80,
		'ips'    => array( '203.0.113.30' ),
	);
	list( $url80, $headers80 ) = $pin->invoke( $fopen, 'https://odd.example:80/x', array(), $dest_https_80 );
	assert_true( false !== strpos( $url80, '203.0.113.30:80' ), 'https:80 keeps port in rewritten URL' );
	assert_true( in_array( 'Host: odd.example:80', $headers80, true ), 'https:80 keeps port in Host' );

	// http:443 likewise.
	$dest_http_443 = array(
		'scheme' => 'http',
		'host'   => 'odd2.example',
		'port'   => 443,
		'ips'    => array( '203.0.113.40' ),
	);
	list( $url443, $headers443 ) = $pin->invoke( $fopen, 'http://odd2.example:443/x', array(), $dest_http_443 );
	assert_true( false !== strpos( $url443, '203.0.113.40:443' ), 'http:443 keeps port in rewritten URL' );
	assert_true( in_array( 'Host: odd2.example:443', $headers443, true ), 'http:443 keeps port in Host' );

	// Curl IPv6 CURLOPT_RESOLVE formatting via reflection of the resolve list builder.
	$curl = new HttpTransportCurl();
	$cref = new ReflectionClass( $curl );
	$build = $cref->getMethod( 'build_curl_resolve' );
	$build->setAccessible( true );

	$resolve = $build->invoke( $curl, array(
		'scheme' => 'https',
		'host'   => 'dual.example',
		'port'   => 443,
		'ips'    => array( '203.0.113.50', '2001:db8::1' ),
	) );

	assert_true( in_array( 'dual.example:443:203.0.113.50', $resolve, true ), 'IPv4 resolve entry plain' );
	assert_true( in_array( 'dual.example:443:[2001:db8::1]', $resolve, true ), 'IPv6 resolve entry bracketed' );

	echo $failures ? "\n$failures failure(s)\n" : "\nAll checks passed.\n";
	exit( $failures ? 1 : 0 );
}
