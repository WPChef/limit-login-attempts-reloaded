<?php

namespace LLAR\Core\Http;

trait HttpTimeoutTrait {

	/**
	 * Resolve the request timeout in seconds.
	 *
	 * Per-call override via $options['timeout']; DEFAULT_TIMEOUT otherwise.
	 * Long-running calls (e.g. app setup/provisioning) should pass a larger
	 * value explicitly.
	 *
	 * @param array $options
	 *
	 * @return int
	 */
	protected function resolve_timeout( $options ) {

		return ! empty( $options['timeout'] ) ? max( 1, (int) $options['timeout'] ) : self::DEFAULT_TIMEOUT;
	}
}
