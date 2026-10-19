<?php

namespace LLAR\Core\Http;

interface HttpTransportInterface {
	const DEFAULT_TIMEOUT = 5;

	public function get( $url, $options = array() );
	public function post( $url, $options = array() );
}