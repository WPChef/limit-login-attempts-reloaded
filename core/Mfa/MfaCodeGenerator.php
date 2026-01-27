<?php

namespace LLAR\Core\Mfa;

use LLAR\Core\Config;
use LLAR\Core\MfaConstants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MFA Code Generator
 * Handles generation of rescue codes
 */
class MfaCodeGenerator {
	/**
	 * Generate rescue codes
	 * Returns plain codes for file generation, stores hashes in Config
	 *
	 * @return array Plain codes (for file generation only)
	 */
	public function generate() {
		$codes       = array();
		$plain_codes = array(); // Temporary array for file

		for ( $i = 0; MfaConstants::CODE_COUNT > $i; $i++ ) {
			$code = wp_generate_password( MfaConstants::CODE_LENGTH, false );
			$rescue_code = RescueCode::from_plain_code( $code );

			if ( null === $rescue_code ) {
				// Abort whole process; hashing failure is unrecoverable
				throw new \Exception( __( 'Failed to hash rescue code. Generation aborted.', 'limit-login-attempts-reloaded' ) );
			}

			$codes[]       = $rescue_code->to_array();
			$plain_codes[] = $code;
		}

		if ( empty( $codes ) ) {
			return array();
		}

		// Replace old codes with new ones
		Config::update( 'mfa_rescue_codes', $codes );
		return $plain_codes; // Return plain codes for file generation (only in memory)
	}
}
