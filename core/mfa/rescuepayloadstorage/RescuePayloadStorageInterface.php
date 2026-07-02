<?php

namespace LLAR\Core\Mfa\RescuePayloadStorage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Storage contract for MFA rescue encrypted payloads.
 */
interface RescuePayloadStorageInterface {
	/**
	 * Save encrypted payload for hash id.
	 *
	 * @param string $hash_id   Rescue hash token.
	 * @param string $encrypted Encrypted payload.
	 * @param int    $ttl       TTL in seconds.
	 * @return bool
	 */
	public function save( $hash_id, $encrypted, $ttl );

	/**
	 * Read encrypted payload by hash id.
	 *
	 * @param string $hash_id Rescue hash token.
	 * @return string|false
	 */
	public function read( $hash_id );

	/**
	 * Delete payload by hash id.
	 *
	 * @param string $hash_id Rescue hash token.
	 * @return bool True when payload existed and delete succeeded.
	 */
	public function delete( $hash_id );

	/**
	 * Check payload existence by hash id.
	 *
	 * @param string $hash_id Rescue hash token.
	 * @return bool
	 */
	public function exists( $hash_id );

	/**
	 * Get max payload expiry as unix timestamp.
	 *
	 * @return int|null Null when no payloads exist.
	 */
	public function get_max_expiry();
}
