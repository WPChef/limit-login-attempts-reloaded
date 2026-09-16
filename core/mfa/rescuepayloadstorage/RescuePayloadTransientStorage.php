<?php

namespace LLAR\Core\Mfa\RescuePayloadStorage;

use LLAR\Core\MfaConstants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rescue payload storage backed by WordPress transients.
 */
class RescuePayloadTransientStorage implements RescuePayloadStorageInterface {
	/**
	 * @param string $hash_id Rescue hash token.
	 * @return string
	 */
	private function get_transient_key( $hash_id ) {
		return MfaConstants::TRANSIENT_RESCUE_PREFIX . $hash_id;
	}

	/**
	 * {@inheritdoc}
	 */
	public function save( $hash_id, $encrypted, $ttl ) {
		$hash_id = (string) $hash_id;
		if ( '' === $hash_id || '' === (string) $encrypted ) {
			return false;
		}
		return (bool) set_transient( $this->get_transient_key( $hash_id ), (string) $encrypted, (int) $ttl );
	}

	/**
	 * {@inheritdoc}
	 */
	public function read( $hash_id ) {
		$hash_id = (string) $hash_id;
		if ( '' === $hash_id ) {
			return false;
		}
		$transient_key = $this->get_transient_key( $hash_id );
		$data          = get_transient( $transient_key );
		if ( false !== $data ) {
			return $data;
		}

		global $wpdb;
		$option_name = '_transient_' . $transient_key;
		$raw         = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT option_value FROM ' . $wpdb->options . ' WHERE option_name = %s LIMIT 1',
				$option_name
			)
		);
		if ( null !== $raw && '' !== $raw ) {
			$data = maybe_unserialize( $raw );
		}
		return false === $data ? false : $data;
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete( $hash_id ) {
		$hash_id = (string) $hash_id;
		if ( '' === $hash_id ) {
			return false;
		}
		$transient_key = $this->get_transient_key( $hash_id );
		$existed       = $this->exists( $hash_id );

		delete_transient( $transient_key );

		global $wpdb;
		$option_name  = '_transient_' . $transient_key;
		$timeout_name = '_transient_timeout_' . $transient_key;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $wpdb->options . ' WHERE option_name = %s', $option_name ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $wpdb->options . ' WHERE option_name = %s', $timeout_name ) );

		delete_transient( MfaConstants::RESCUE_MAX_EXPIRY_CACHE_KEY );

		return $existed;
	}

	/**
	 * {@inheritdoc}
	 */
	public function exists( $hash_id ) {
		$hash_id = (string) $hash_id;
		if ( '' === $hash_id ) {
			return false;
		}
		$transient_key = $this->get_transient_key( $hash_id );
		if ( false !== get_transient( $transient_key ) ) {
			return true;
		}

		global $wpdb;
		$option_name = '_transient_' . $transient_key;
		$one         = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT 1 FROM ' . $wpdb->options . ' WHERE option_name = %s LIMIT 1',
				$option_name
			)
		);
		return null !== $one;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_max_expiry() {
		global $wpdb;
		$timeout_like = $wpdb->esc_like( '_transient_timeout_' . MfaConstants::TRANSIENT_RESCUE_PREFIX ) . '%';
		$max_timeout  = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT MAX(CAST(option_value AS UNSIGNED)) FROM ' . $wpdb->options . ' WHERE option_name LIKE %s',
				$timeout_like
			)
		);
		if ( 0 === $max_timeout ) {
			return null;
		}
		return $max_timeout;
	}
}
