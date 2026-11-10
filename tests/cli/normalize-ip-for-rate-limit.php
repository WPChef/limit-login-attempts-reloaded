<?php
/**
 * CLI assertions for Helpers::normalize_ip_for_rate_limit.
 *
 * Run: php tests/cli/normalize-ip-for-rate-limit.php
 * Exit 0 on success; non-zero with a message on failure.
 *
 * @package LimitLoginAttempts
 */

define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );

require dirname( __DIR__, 2 ) . '/core/Helpers.php';

use LLAR\Core\Helpers;

/**
 * Fail the script when values differ.
 *
 * @param string $label    Assertion label.
 * @param mixed  $expected Expected value.
 * @param mixed  $actual   Actual value.
 * @return void
 */
function llar_assert_same( $label, $expected, $actual ) {
	if ( $expected !== $actual ) {
		fwrite( STDERR, 'FAIL: ' . $label . PHP_EOL );
		fwrite( STDERR, '  expected: ' . print_r( $expected, true ) . PHP_EOL );
		fwrite( STDERR, '  actual:   ' . print_r( $actual, true ) . PHP_EOL );
		exit( 1 );
	}
	fwrite( STDOUT, 'PASS: ' . $label . PHP_EOL );
}

if ( ! method_exists( Helpers::class, 'normalize_ip_for_rate_limit' ) ) {
	fwrite( STDERR, "FAIL: Helpers::normalize_ip_for_rate_limit is missing\n" );
	exit( 1 );
}

$a  = Helpers::normalize_ip_for_rate_limit( 'fd00:dead:beef::3e8' );
$b  = Helpers::normalize_ip_for_rate_limit( 'fd00:dead:beef::3fb' );
$c  = Helpers::normalize_ip_for_rate_limit( 'fd00:dead:cafe::1' );
$v4 = Helpers::normalize_ip_for_rate_limit( '203.0.113.50' );

llar_assert_same( 'same /64 collapses', $a, $b );
llar_assert_same( 'canonical /64 key', 'fd00:dead:beef::', $a );
llar_assert_same( 'different /64 stays distinct from A', true, $a !== $c );
llar_assert_same( 'other /64 key', 'fd00:dead:cafe::', $c );
llar_assert_same( 'IPv4 unchanged', '203.0.113.50', $v4 );
llar_assert_same( 'empty stays empty', '', Helpers::normalize_ip_for_rate_limit( '' ) );
llar_assert_same(
	'no /64 suffix in key',
	false,
	is_string( $a ) && substr( $a, -3 ) === '/64'
);

// IPv4-mapped IPv6 must NOT collapse to "::" (dual-stack REMOTE_ADDR).
llar_assert_same(
	'mapped IPv4 ::ffff:203.0.113.50',
	'203.0.113.50',
	Helpers::normalize_ip_for_rate_limit( '::ffff:203.0.113.50' )
);
llar_assert_same(
	'mapped IPv4 ::ffff:198.51.100.7',
	'198.51.100.7',
	Helpers::normalize_ip_for_rate_limit( '::ffff:198.51.100.7' )
);
llar_assert_same( 'loopback ::1 stays ::1', '::1', Helpers::normalize_ip_for_rate_limit( '::1' ) );
llar_assert_same(
	'compact vs expanded same /64',
	Helpers::normalize_ip_for_rate_limit( 'fd00:dead:beef:0:0:0:0:3e8' ),
	Helpers::normalize_ip_for_rate_limit( 'FD00:DEAD:BEEF::3e8' )
);

// PoC-scale: 20 IIDs inside one /64 must collapse to a single rate-limit key.
$keys = array();
for ( $i = 0x3e8; $i <= 0x3fb; $i++ ) {
	$keys[ Helpers::normalize_ip_for_rate_limit( sprintf( 'fd00:dead:beef::%x', $i ) ) ] = true;
}
llar_assert_same( '20 rotated IIDs share one key', 1, count( $keys ) );
llar_assert_same( '20 rotated IIDs key is /64 network', 'fd00:dead:beef::', array_key_first( $keys ) );

fwrite( STDOUT, "OK\n" );
exit( 0 );
