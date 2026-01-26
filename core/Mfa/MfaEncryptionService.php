<?php

namespace LLAR\Core\Mfa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MFA Encryption Service
 * Handles encryption/decryption of rescue codes for transient storage
 */
class MfaEncryptionService {
	/**
	 * Encrypt plain code for storage in transient
	 *
	 * @param string $plain_code Plain rescue code
	 * @param string $salt Salt for hash_id generation (not used in encryption)
	 * @return string Encrypted data (base64 encoded)
	 */
	public function encrypt_code( $plain_code, $salt = '' ) {
		if ( ! function_exists( 'openssl_encrypt' ) || ! function_exists( 'openssl_decrypt' ) ) {
			// Fallback: if OpenSSL not available, use simple obfuscation
			error_log( 'LLAR MFA: OpenSSL not available. Using fallback obfuscation for rescue codes.' );
			return base64_encode( $plain_code . $salt );
		}

		// Use AUTH_KEY and AUTH_SALT for encryption key (constant WordPress salts)
		$auth_key_for_encryption  = defined( 'AUTH_KEY' ) ? AUTH_KEY : ( defined( 'NONCE_KEY' ) ? NONCE_KEY : wp_generate_password( 64, true ) );
		$auth_salt_for_encryption = defined( 'AUTH_SALT' ) ? AUTH_SALT : ( defined( 'NONCE_SALT' ) ? NONCE_SALT : wp_generate_password( 64, true ) );

		$encryption_key = hash( 'sha256', $auth_key_for_encryption . $auth_salt_for_encryption, true );
		$iv_length      = openssl_cipher_iv_length( 'AES-256-CBC' );
		$iv             = openssl_random_pseudo_bytes( $iv_length );
		$encrypted_code = openssl_encrypt( $plain_code, 'AES-256-CBC', $encryption_key, 0, $iv );

		if ( false === $encrypted_code ) {
			// Encryption failed, use fallback
			error_log( 'LLAR MFA: Encryption failed. Using fallback obfuscation.' );
			return base64_encode( $plain_code . $salt );
		}

		// Store encrypted code with IV (required for decryption)
		return base64_encode( $iv . $encrypted_code );
	}

	/**
	 * Decrypt code from transient
	 *
	 * @param string $encrypted_data Encrypted data (base64 encoded)
	 * @return string|false Plain code or false on failure
	 */
	public function decrypt_code( $encrypted_data ) {
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			// OpenSSL not available - cannot decrypt
			return false;
		}

		// Use same logic as encryption: AUTH_KEY and AUTH_SALT (constant)
		$auth_key_for_decryption  = defined( 'AUTH_KEY' ) ? AUTH_KEY : ( defined( 'NONCE_KEY' ) ? NONCE_KEY : wp_generate_password( 64, true ) );
		$auth_salt_for_decryption = defined( 'AUTH_SALT' ) ? AUTH_SALT : ( defined( 'NONCE_SALT' ) ? NONCE_SALT : wp_generate_password( 64, true ) );

		$encryption_key = hash( 'sha256', $auth_key_for_decryption . $auth_salt_for_decryption, true );
		$decoded_data   = base64_decode( $encrypted_data );
		$iv_length      = openssl_cipher_iv_length( 'AES-256-CBC' );

		// Check if data looks like encrypted (has IV prefix) or fallback obfuscation
		if ( strlen( $decoded_data ) <= $iv_length ) {
			// Data too short to be encrypted - invalid format
			return false;
		}

		// Try AES decryption
		$iv             = substr( $decoded_data, 0, $iv_length );
		$encrypted_code = substr( $decoded_data, $iv_length );
		$plain_code     = openssl_decrypt( $encrypted_code, 'AES-256-CBC', $encryption_key, 0, $iv );

		if ( false === $plain_code ) {
			// Decryption failed
			return false;
		}

		return $plain_code;
	}
}
