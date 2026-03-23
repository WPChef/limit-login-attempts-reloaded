<?php
if( !defined( 'ABSPATH' ) ) exit;

spl_autoload_register(function($class) {

	$namespace = 'LLAR\\';

	$len = strlen( $namespace );
	if (strncmp( $namespace, $class, $len) !== 0) {
		return;
	}

	$mfa_flow_prefix = 'LLAR\\Core\\MfaFlow\\';
	$mfa_flow_len = strlen( $mfa_flow_prefix );
	if ( strncmp( $mfa_flow_prefix, $class, $mfa_flow_len ) === 0 ) {
		$class_name = substr( $class, $mfa_flow_len );
		$class_name = str_replace( '\\', '/', $class_name );
		$file = LLA_PLUGIN_DIR . 'core/mfa-flow/' . $class_name . '.php';
		if ( file_exists( $file ) ) {
			require $file;
			return;
		}
	}

	$relative_class = str_replace('\\', '/', substr( $class, $len ) );
	$relative_class = explode( '/', $relative_class );
	$class_name = array_pop( $relative_class );
	$relative_class = implode( '/', $relative_class );
	$file = LLA_PLUGIN_DIR . strtolower( $relative_class ) . '/' . $class_name . '.php';

	if ( file_exists( $file ) ) {
		require $file;
	}
});