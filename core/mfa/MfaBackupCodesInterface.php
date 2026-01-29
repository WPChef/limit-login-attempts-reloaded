<?php

namespace LLAR\Core\Mfa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface for MFA backup/rescue codes service.
 * Implementations: generation, encryption, rescue URLs, PDF HTML.
 *
 * Used for testability: MfaManager can depend on this interface.
 */
interface MfaBackupCodesInterface {

	/**
	 * Encrypt plain code for storage. OpenSSL only; returns false if unavailable.
	 *
	 * @param string $plain_code Plain rescue code.
	 * @param string $salt       Salt (e.g. AUTH_SALT).
	 * @return string|false Base64-encoded ciphertext or false if OpenSSL unavailable/failure.
	 */
	public function encrypt_code( $plain_code, $salt = '' );

	/**
	 * Decrypt stored blob. OpenSSL only; returns false if unavailable or invalid.
	 *
	 * @param string $encrypted_data Base64-encoded (iv + ciphertext).
	 * @return string|false Plain code or false.
	 */
	public function decrypt_code( $encrypted_data );

	/**
	 * Generate rescue codes. Stores hashes in Config, returns plain codes.
	 *
	 * @return array Plain codes.
	 * @throws \Exception When hashing fails.
	 */
	public function generate();

	/**
	 * Build rescue URL for a plain code and store encrypted payload. OpenSSL required.
	 *
	 * @param string $plain_code Plain rescue code.
	 * @return string Rescue URL.
	 * @throws \Exception When OpenSSL unavailable or encryption fails.
	 */
	public function get_rescue_url( $plain_code );

	/**
	 * Generate HTML for rescue PDF. Caller must pass rescue_urls (from get_rescue_url for each code).
	 *
	 * @param array $rescue_urls List of rescue URLs.
	 * @return string HTML.
	 * @throws \Exception When template path invalid.
	 */
	public function generate_pdf_html( $rescue_urls );
}
