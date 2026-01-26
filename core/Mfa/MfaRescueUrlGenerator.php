<?php

namespace LLAR\Core\Mfa;

use LLAR\Core\MfaConstants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MFA Rescue URL Generator
 * Generates secure one-time rescue URLs
 */
class MfaRescueUrlGenerator {
	/**
	 * Encryption service
	 *
	 * @var MfaEncryptionService
	 */
	private $encryption;

	/**
	 * Constructor
	 *
	 * @param MfaEncryptionService $encryption Encryption service
	 */
	public function __construct( MfaEncryptionService $encryption ) {
		$this->encryption = $encryption;
	}

	/**
	 * Get rescue URL for a code
	 * Generates one-time hash instead of plain code (security)
	 *
	 * @param string $plain_code Plain rescue code
	 * @return string Rescue URL with hash
	 */
	public function get_rescue_url( $plain_code ) {
		// Generate one-time hash instead of plain code
		// Require AUTH_SALT or NONCE_SALT for security (no static fallback)
		if ( ! defined( 'AUTH_SALT' ) && ! defined( 'NONCE_SALT' ) ) {
			// Log error but don't break - use cryptographically secure random instead
			error_log( 'LLAR MFA: AUTH_SALT or NONCE_SALT not defined in wp-config.php. Using secure random fallback.' );
			$salt = wp_generate_password( 64, true ); // Generate secure random salt
		} else {
			$salt = defined( 'AUTH_SALT' ) ? AUTH_SALT : NONCE_SALT;
		}

		// Use SHA-256 instead of MD5 for better security
		// Add random suffix instead of time() for better unpredictability
		$random_suffix = wp_generate_password( 32, false ); // Additional randomness
		$hash_id       = hash( 'sha256', $plain_code . $salt . $random_suffix );

		// Encrypt plain code before storing in transient (security: don't store plain codes in DB)
		$encrypted_data = $this->encryption->encrypt_code( $plain_code, $salt );

		// Save encrypted code in temporary transient (5 minutes, one-time)
		$transient_key = MfaConstants::TRANSIENT_RESCUE_PREFIX . $hash_id;
		set_transient( $transient_key, $encrypted_data, MfaConstants::RESCUE_LINK_TTL );

		// URL contains only hash, not plain code
		return add_query_arg( 'llar_rescue', $hash_id, home_url() );
	}
}
