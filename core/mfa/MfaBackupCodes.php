<?php

namespace LLAR\Core\Mfa;

use LLAR\Core\Config;
use LLAR\Core\MfaConstants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MFA backup/rescue codes: generation, encryption (OpenSSL only), URLs, PDF HTML.
 * No base64 fallback — do not enable MFA without proper encryption.
 */
class MfaBackupCodes implements MfaBackupCodesInterface {

	/**
	 * Encrypt plain code for storage. OpenSSL only; returns false if unavailable.
	 *
	 * @param string $plain_code Plain rescue code
	 * @param string $salt       Salt (e.g. AUTH_SALT)
	 * @return string|false Base64-encoded ciphertext or false if OpenSSL unavailable/failure
	 */
	public function encrypt_code( $plain_code, $salt = '' ) {
		if ( ! function_exists( 'openssl_encrypt' ) || ! function_exists( 'openssl_decrypt' ) ) {
			return false;
		}
		$auth_key  = defined( 'AUTH_KEY' ) ? AUTH_KEY : ( defined( 'NONCE_KEY' ) ? NONCE_KEY : '' );
		$auth_salt = defined( 'AUTH_SALT' ) ? AUTH_SALT : ( defined( 'NONCE_SALT' ) ? NONCE_SALT : '' );
		if ( '' === $auth_key || '' === $auth_salt ) {
			return false;
		}
		$encryption_key = hash( 'sha256', $auth_key . $auth_salt, true );
		$iv_length      = openssl_cipher_iv_length( 'AES-256-CBC' );
		$iv             = openssl_random_pseudo_bytes( $iv_length );
		$encrypted      = openssl_encrypt( $plain_code, 'AES-256-CBC', $encryption_key, 0, $iv );
		if ( false === $encrypted ) {
			return false;
		}
		return base64_encode( $iv . $encrypted );
	}

	/**
	 * Decrypt stored blob. OpenSSL only; returns false if unavailable or invalid.
	 *
	 * @param string $encrypted_data Base64-encoded (iv + ciphertext)
	 * @return string|false Plain code or false
	 */
	public function decrypt_code( $encrypted_data ) {
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return false;
		}
		$auth_key  = defined( 'AUTH_KEY' ) ? AUTH_KEY : ( defined( 'NONCE_KEY' ) ? NONCE_KEY : '' );
		$auth_salt = defined( 'AUTH_SALT' ) ? AUTH_SALT : ( defined( 'NONCE_SALT' ) ? NONCE_SALT : '' );
		if ( '' === $auth_key || '' === $auth_salt ) {
			return false;
		}
		$encryption_key = hash( 'sha256', $auth_key . $auth_salt, true );
		$decoded        = base64_decode( $encrypted_data, true );
		$iv_length      = openssl_cipher_iv_length( 'AES-256-CBC' );
		if ( false === $decoded || strlen( $decoded ) <= $iv_length ) {
			return false;
		}
		$iv         = substr( $decoded, 0, $iv_length );
		$ciphertext = substr( $decoded, $iv_length );
		$plain      = openssl_decrypt( $ciphertext, 'AES-256-CBC', $encryption_key, 0, $iv );
		return false === $plain ? false : $plain;
	}

	/**
	 * Generate rescue codes. Stores hashes in Config, returns plain codes.
	 *
	 * @return array Plain codes
	 * @throws \Exception When hashing fails
	 */
	public function generate() {
		$codes       = array();
		$plain_codes = array();
		for ( $i = 0; MfaConstants::CODE_COUNT > $i; $i++ ) {
			$code        = wp_generate_password( MfaConstants::CODE_LENGTH, false );
			$rescue_code = RescueCode::from_plain_code( $code );
			if ( null === $rescue_code ) {
				throw new \Exception( __( 'Failed to hash rescue code. Generation aborted.', 'limit-login-attempts-reloaded' ) );
			}
			$codes[]       = $rescue_code->to_array();
			$plain_codes[] = $code;
		}
		if ( ! empty( $codes ) ) {
			Config::update( 'mfa_rescue_codes', $codes );
		}
		return $plain_codes;
	}

	/**
	 * Build rescue URL for a plain code and store encrypted payload. OpenSSL required.
	 *
	 * @param string $plain_code Plain rescue code
	 * @return string Rescue URL
	 * @throws \Exception When OpenSSL unavailable or encryption fails
	 */
	public function get_rescue_url( $plain_code ) {
		$salt = ( defined( 'AUTH_SALT' ) && AUTH_SALT ) ? AUTH_SALT : ( ( defined( 'NONCE_SALT' ) && NONCE_SALT ) ? NONCE_SALT : '' );
		if ( '' === $salt ) {
			$salt = wp_generate_password( 64, true );
		}
		$hash_id   = hash( 'sha256', $plain_code . $salt . wp_generate_password( 32, false ) );
		$encrypted = $this->encrypt_code( $plain_code, $salt );
		if ( false === $encrypted ) {
			throw new \Exception( __( 'Encryption unavailable. OpenSSL is required for rescue links.', 'limit-login-attempts-reloaded' ) );
		}
		// Store encrypted payload in a dedicated transient per hash_id.
		// This allows atomic, single-use consumption on the endpoint side.
		$transient_key = MfaConstants::TRANSIENT_RESCUE_PREFIX . $hash_id;
		set_transient( $transient_key, $encrypted, MfaConstants::RESCUE_LINK_TTL );
		return add_query_arg( 'llar_rescue', $hash_id, home_url() );
	}

	/**
	 * Generate HTML for rescue PDF. Validates template path under LLA_PLUGIN_DIR/views.
	 * Caller must pass rescue_urls (from get_rescue_url) to avoid double-creating pending links.
	 *
	 * @param array $rescue_urls List of rescue URLs (from get_rescue_url for each code)
	 * @return string HTML
	 * @throws \Exception When template path invalid
	 */
	public function generate_pdf_html( $rescue_urls ) {
		$domain        = wp_parse_url( home_url(), PHP_URL_HOST );
		$allowed_dir   = realpath( LLA_PLUGIN_DIR . 'views' );
		$template_path = realpath( LLA_PLUGIN_DIR . 'views/mfa-rescue-pdf.php' );
		if ( false === $allowed_dir || false === $template_path || 0 !== strpos( $template_path, $allowed_dir ) ) {
			throw new \Exception( __( 'Invalid file path for PDF template.', 'limit-login-attempts-reloaded' ) );
		}
		ob_start();
		include $template_path;
		return ob_get_clean();
	}

	/**
	 * Whether there are no codes or all are used (show rescue popup).
	 *
	 * @param array $codes mfa_rescue_codes from Config
	 * @return bool
	 */
	public static function should_show_rescue_popup( $codes ) {
		if ( ! is_array( $codes ) || empty( $codes ) ) {
			return true;
		}
		foreach ( $codes as $code_data ) {
			$rescue_code = RescueCode::from_array( $code_data );
			if ( ! $rescue_code->is_used() ) {
				return false;
			}
		}
		return true;
	}
}
