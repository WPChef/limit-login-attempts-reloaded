<?php

namespace LLAR\Core\Mfa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface for MFA rescue endpoint handler.
 * Handles rescue link requests: rate limiting, decrypt, verify, disable MFA.
 *
 * Used for testability: MfaManager can depend on this interface.
 */
interface MfaEndpointInterface {

	/**
	 * Handle rescue endpoint request. May redirect, wp_die, or exit.
	 *
	 * @param string $hash_id Hash ID from URL (llar_rescue query var).
	 * @return void
	 */
	public function handle( $hash_id );
}
