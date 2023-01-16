<?php
if( !defined( 'ABSPATH' ) ) exit;

spl_autoload_register(function($class) {

	$namespace = 'LLAR\\';

	$len = strlen( $namespace );
	if (strncmp( $namespace, $class, $len) !== 0) {
		return;
	}

	$relative_class = substr( $class, $len );
	$file = LLA_PLUGIN_DIR . str_replace('\\', '/', $relative_class ) . '.php';

	if ( file_exists( $file ) ) {
		require $file;
	}
});