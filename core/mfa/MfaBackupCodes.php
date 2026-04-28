<?php

namespace LLAR\Core\Mfa;

use LLAR\Core\MfaConstants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MFA backup/rescue codes: generation, encryption (OpenSSL only), URLs, PDF HTML.
 * No base64 fallback — do not enable MFA without proper encryption.
 */
class MfaBackupCodes implements MfaBackupCodesInterface {
	const CIPHER_METHOD = 'AES-256-CBC';
	const PAYLOAD_VERSION = 'v2:';
	const BASE62_ALPHABET = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

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
		$keys = $this->get_crypto_keys();
		if ( false === $keys ) {
			return false;
		}
		$iv_length = openssl_cipher_iv_length( self::CIPHER_METHOD );
		if ( false === $iv_length || $iv_length <= 0 ) {
			return false;
		}
		$iv = openssl_random_pseudo_bytes( $iv_length );
		if ( false === $iv || strlen( $iv ) !== (int) $iv_length ) {
			return false;
		}
		$ciphertext = openssl_encrypt( $plain_code, self::CIPHER_METHOD, $keys['enc'], OPENSSL_RAW_DATA, $iv );
		if ( false === $ciphertext || '' === $ciphertext ) {
			return false;
		}
		$mac = hash_hmac( 'sha256', $iv . $ciphertext, $keys['mac'], true );
		if ( false === $mac || '' === $mac ) {
			return false;
		}
		$encoded = base64_encode( $iv . $ciphertext . $mac );
		if ( false === $encoded ) {
			return false;
		}
		return self::PAYLOAD_VERSION . $encoded;
	}

	/**
	 * Decrypt v2 payload with HMAC verification.
	 *
	 * @param string $encrypted_data Versioned payload.
	 * @return string|false
	 */
	private function decrypt_v2_code( $encrypted_data ) {
		$payload = substr( $encrypted_data, strlen( self::PAYLOAD_VERSION ) );
		$decoded = base64_decode( $payload, true );
		if ( false === $decoded ) {
			return false;
		}
		$keys = $this->get_crypto_keys();
		if ( false === $keys ) {
			return false;
		}
		$iv_length = openssl_cipher_iv_length( self::CIPHER_METHOD );
		if ( false === $iv_length || $iv_length <= 0 ) {
			return false;
		}
		$mac_length = 32;
		if ( strlen( $decoded ) <= ( $iv_length + $mac_length ) ) {
			return false;
		}

		$iv         = substr( $decoded, 0, $iv_length );
		$mac        = substr( $decoded, -$mac_length );
		$ciphertext = substr( $decoded, $iv_length, -$mac_length );
		$calc_mac   = hash_hmac( 'sha256', $iv . $ciphertext, $keys['mac'], true );
		if ( ! hash_equals( $mac, $calc_mac ) ) {
			return false;
		}

		$plain = openssl_decrypt( $ciphertext, self::CIPHER_METHOD, $keys['enc'], OPENSSL_RAW_DATA, $iv );
		if ( false === $plain ) {
			return false;
		}
		return $plain;
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
		if ( ! is_string( $encrypted_data ) || '' === $encrypted_data ) {
			return false;
		}

		// New authenticated format.
		if ( 0 === strpos( $encrypted_data, self::PAYLOAD_VERSION ) ) {
			return $this->decrypt_v2_code( $encrypted_data );
		}

		// Legacy format fallback: base64(iv + openssl_encrypt output with options=0).
		$legacy_key = $this->get_legacy_encryption_key();
		if ( false === $legacy_key ) {
			return false;
		}
		$decoded   = base64_decode( $encrypted_data, true );
		$iv_length = openssl_cipher_iv_length( self::CIPHER_METHOD );
		if ( false === $decoded || strlen( $decoded ) <= $iv_length ) {
			return false;
		}
		$iv         = substr( $decoded, 0, $iv_length );
		$ciphertext = substr( $decoded, $iv_length );
		// Pre-v2 code used hash('sha256', AUTH_KEY . AUTH_SALT, true), not derived enc key.
		$plain = openssl_decrypt( $ciphertext, self::CIPHER_METHOD, $legacy_key, 0, $iv );
		return false === $plain ? false : $plain;
	}

	/**
	 * Build encryption and MAC keys from WordPress salts.
	 *
	 * @return array|false
	 */
	private function get_crypto_keys() {
		$auth_key  = defined( 'AUTH_KEY' ) ? AUTH_KEY : ( defined( 'NONCE_KEY' ) ? NONCE_KEY : '' );
		$auth_salt = defined( 'AUTH_SALT' ) ? AUTH_SALT : ( defined( 'NONCE_SALT' ) ? NONCE_SALT : '' );
		if ( '' === $auth_key || '' === $auth_salt ) {
			return false;
		}

		$master_key = hash( 'sha256', $auth_key . $auth_salt, true );
		return array(
			'enc' => hash_hmac( 'sha256', 'llar-mfa-rescue-enc', $master_key, true ),
			'mac' => hash_hmac( 'sha256', 'llar-mfa-rescue-mac', $master_key, true ),
		);
	}

	/**
	 * Legacy AES key (pre-encrypt-then-MAC format).
	 *
	 * @return string|false 32-byte key or false.
	 */
	private function get_legacy_encryption_key() {
		$auth_key  = defined( 'AUTH_KEY' ) ? AUTH_KEY : ( defined( 'NONCE_KEY' ) ? NONCE_KEY : '' );
		$auth_salt = defined( 'AUTH_SALT' ) ? AUTH_SALT : ( defined( 'NONCE_SALT' ) ? NONCE_SALT : '' );
		if ( '' === $auth_key || '' === $auth_salt ) {
			return false;
		}
		return hash( 'sha256', $auth_key . $auth_salt, true );
	}

	/**
	 * Generate rescue codes and return plain values.
	 * Persisting hashes is handled by MFA settings submit flow after confirmation.
	 *
	 * @return array Plain codes
	 */
	public function generate() {
		$plain_codes = array();
		for ( $i = 0; MfaConstants::CODE_COUNT > $i; $i++ ) {
			$code = wp_generate_password( MfaConstants::CODE_LENGTH, false );
			$plain_codes[] = $code;
		}
		return $plain_codes;
	}

	/**
	 * Generate cryptographically strong base62 token.
	 *
	 * @param int $length Token length.
	 * @return string
	 */
	private function generate_base62_token( $length ) {
		$length   = (int) $length;
		$token    = '';
		$alphabet = self::BASE62_ALPHABET;

		// Rejection sampling avoids modulo bias (62 * 4 = 248).
		while ( strlen( $token ) < $length && function_exists( 'openssl_random_pseudo_bytes' ) ) {
			$bytes = openssl_random_pseudo_bytes( max( 16, $length * 2 ) );
			if ( false === $bytes || '' === $bytes ) {
				break;
			}
			$bytes_len = strlen( $bytes );
			for ( $i = 0; $i < $bytes_len && strlen( $token ) < $length; $i++ ) {
				$val = ord( $bytes[ $i ] );
				if ( 248 <= $val ) {
					continue;
				}
				$token .= $alphabet[ $val % 62 ];
			}
		}

		if ( strlen( $token ) < $length ) {
			$fallback = wp_generate_password( $length, false, false );
			$token   .= preg_replace( '/[^A-Za-z0-9]/', '', (string) $fallback );
		}

		return substr( $token, 0, $length );
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
		$hash_id   = $this->generate_base62_token( MfaConstants::RESCUE_TOKEN_LENGTH );
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
