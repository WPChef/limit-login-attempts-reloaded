<?php

namespace LLAR\Core\Http;

interface HttpTransportInterface {

	// Default HTTP timeout in seconds. Callers may override per request via
	// the $options['timeout'] key.
	const DEFAULT_TIMEOUT = 5;

	public function get( $url, $options = array() );
	public function post( $url, $options = array() );
}