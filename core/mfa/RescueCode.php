<?php

namespace LLAR\Core\Mfa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rescue Code Value Object
 * Encapsulates rescue code data and behavior
 */
class RescueCode {
	/**
	 * Hashed code (stored in database)
	 *
	 * @var string
	 */
	private $hash;

	/**
	 * Whether code has been used
	 *
	 * @var bool
	 */
	private $used;

	/**
	 * Timestamp when code was used (null if not used)
	 *
	 * @var int|null
	 */
	private $used_at;

	/**
	 * Create RescueCode from plain code. Returns null if hashing fails.
	 *
	 * @param string $plain_code Plain rescue code.
	 * @return self|null RescueCode instance or null on failure.
	 */
	public static function from_plain_code( $plain_code ) {
		$hash = wp_hash_password( $plain_code );

		// Validate hash was generated successfully
		if ( empty( $hash ) || 20 > strlen( $hash ) ) {
			return null;
		}

		return new self( $hash, false, null );
	}

	/**
	 * Create RescueCode from stored data (hash, used, used_at).
	 *
	 * @param array $data Stored code data.
	 * @return self RescueCode instance.
	 */
	public static function from_array( $data ) {
		$hash    = isset( $data['hash'] ) ? $data['hash'] : '';
		$used    = isset( $data['used'] ) ? (bool) $data['used'] : false;
		$used_at = isset( $data['used_at'] ) ? (int) $data['used_at'] : null;

		return new self( $hash, $used, $used_at );
	}

	/**
	 * Constructor
	 *
	 * @param string $hash Hashed code
	 * @param bool $used Whether code is used
	 * @param int|null $used_at Timestamp when used
	 */
	private function __construct( $hash, $used, $used_at ) {
		$this->hash    = $hash;
		$this->used    = $used;
		$this->used_at = $used_at;
	}

	/**
	 * Verify plain code against this hash
	 *
	 * @param string $plain_code Plain code to verify
	 * @return bool True if code matches
	 */
	public function verify( $plain_code ) {
		if ( $this->used ) {
			return false;
		}

		return wp_check_password( $plain_code, $this->hash );
	}

	/**
	 * Mark code as used and set used_at to current time.
	 *
	 * @return void
	 */
	public function mark_as_used() {
		$this->used    = true;
		$this->used_at = time();
	}

	/**
	 * Check if code is used
	 *
	 * @return bool
	 */
	public function is_used() {
		return $this->used;
	}

	/**
	 * Get used timestamp
	 *
	 * @return int|null
	 */
	public function get_used_at() {
		return $this->used_at;
	}

	/**
	 * Convert to array for storage
	 *
	 * @return array
	 */
	public function to_array() {
		return array(
			'hash'    => $this->hash,
			'used'    => $this->used,
			'used_at' => $this->used_at,
		);
	}
}
