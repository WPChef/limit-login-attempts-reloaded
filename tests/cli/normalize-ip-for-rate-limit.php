<?php
/**
 * CLI assertions for Helpers::normalize_ip_for_rate_limit.
 *
 * Run: php tests/cli/normalize-ip-for-rate-limit.php
 * Exit 0 on success; non-zero with a message on failure.
 */

define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );

require dirname( __DIR__, 2 ) . '/core/Helpers.php';

use LLAR\Core\Helpers;

/**
 * @param string $label
 * @param mixed  $expected
 * @param mixed  $actual
 * @return void
 */
function assert_same_value( $label, $expected, $actual ) {
	if ( $expected !== $actual ) {
		fwrite(
			STDERR,
			"FAIL: {$label}\n  expected: " . var_export( $expected, true ) .
			"\n  actual:   " . var_export( $actual, true ) . "\n"
		);
		exit( 1 );
	}
	echo "PASS: {$label}\n";
}

if ( ! method_exists( Helpers::class, 'normalize_ip_for_rate_limit' ) ) {
	fwrite( STDERR, "FAIL: Helpers::normalize_ip_for_rate_limit is missing\n" );
	exit( 1 );
}

$a = Helpers::normalize_ip_for_rate_limit( 'fd00:dead:beef::3e8' );
$b = Helpers::normalize_ip_for_rate_limit( 'fd00:dead:beef::3fb' );
$c = Helpers::normalize_ip_for_rate_limit( 'fd00:dead:cafe::1' );
$v4 = Helpers::normalize_ip_for_rate_limit( '203.0.113.50' );

assert_same_value( 'same /64 collapses', $a, $b );
assert_same_value( 'canonical /64 key', 'fd00:dead:beef::', $a );
assert_same_value( 'different /64 stays distinct from A', true, $a !== $c );
assert_same_value( 'other /64 key', 'fd00:dead:cafe::', $c );
assert_same_value( 'IPv4 unchanged', '203.0.113.50', $v4 );
assert_same_value( 'empty stays empty', '', Helpers::normalize_ip_for_rate_limit( '' ) );
assert_same_value(
	'no /64 suffix in key',
	false,
	is_string( $a ) && substr( $a, -3 ) === '/64'
);

echo "OK\n";
exit( 0 );
