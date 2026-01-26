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
			// Generate cryptographically secure random code
			// 64 characters alphanumeric = ~384 bits of entropy
			$code = wp_generate_password( MfaConstants::CODE_LENGTH, false ); // alphanumeric

			// Create RescueCode value object
			$rescue_code = RescueCode::from_plain_code( $code );

			if ( null === $rescue_code ) {
				// Fallback: log error and skip this code
				error_log( 'LLAR MFA: Failed to hash rescue code. Skipping code generation.' );
				continue;
			}

			$codes[]       = $rescue_code->to_array();
			$plain_codes[] = $code; // Save for file (only in memory)
		}

		// Ensure we have at least some codes generated
		if ( empty( $codes ) ) {
			return array();
		}

		// Replace old codes with new ones
		Config::update( 'mfa_rescue_codes', $codes );
		return $plain_codes; // Return plain codes for file generation (only in memory)
	}
}
