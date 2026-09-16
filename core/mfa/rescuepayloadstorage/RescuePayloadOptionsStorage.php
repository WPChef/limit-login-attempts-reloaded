<?php

namespace LLAR\Core\Mfa\RescuePayloadStorage;

use LLAR\Core\MfaConstants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rescue payload storage backed by wp_options (autoload=no).
 */
class RescuePayloadOptionsStorage implements RescuePayloadStorageInterface {
	const VALUE_PREFIX  = 'llar_mfa_rescue_payload_';
	const EXPIRY_PREFIX = 'llar_mfa_rescue_expiry_';

	/**
	 * @param string $hash_id Rescue hash token.
	 * @return string
	 */
	private function get_value_key( $hash_id ) {
		return self::VALUE_PREFIX . $hash_id;
	}

	/**
	 * @param string $hash_id Rescue hash token.
	 * @return string
	 */
	private function get_expiry_key( $hash_id ) {
		return self::EXPIRY_PREFIX . $hash_id;
	}

	/**
	 * {@inheritdoc}
	 */
	public function save( $hash_id, $encrypted, $ttl ) {
		$hash_id = (string) $hash_id;
		if ( '' === $hash_id || '' === (string) $encrypted ) {
			return false;
		}
		$expiry = time() + max( 1, (int) $ttl );
		$ok1    = update_option( $this->get_value_key( $hash_id ), (string) $encrypted, false );
		$ok2    = update_option( $this->get_expiry_key( $hash_id ), (int) $expiry, false );
		delete_transient( MfaConstants::RESCUE_MAX_EXPIRY_CACHE_KEY );
		return (bool) ( $ok1 || $ok2 );
	}

	/**
	 * {@inheritdoc}
	 */
	public function read( $hash_id ) {
		$hash_id = (string) $hash_id;
		if ( '' === $hash_id ) {
			return false;
		}
		$expiry = (int) get_option( $this->get_expiry_key( $hash_id ), 0 );
		if ( 0 >= $expiry || $expiry < time() ) {
			$this->delete( $hash_id );
			return false;
		}
		$value = get_option( $this->get_value_key( $hash_id ), false );
		return ( false === $value || '' === $value ) ? false : $value;
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete( $hash_id ) {
		$hash_id = (string) $hash_id;
		if ( '' === $hash_id ) {
			return false;
		}
		$exists  = false !== get_option( $this->get_value_key( $hash_id ), false );
		$deleted = delete_option( $this->get_value_key( $hash_id ) );
		delete_option( $this->get_expiry_key( $hash_id ) );
		delete_transient( MfaConstants::RESCUE_MAX_EXPIRY_CACHE_KEY );
		return (bool) ( $deleted || $exists );
	}

	/**
	 * {@inheritdoc}
	 */
	public function exists( $hash_id ) {
		$hash_id = (string) $hash_id;
		if ( '' === $hash_id ) {
			return false;
		}
		$value = get_option( $this->get_value_key( $hash_id ), false );
		if ( false === $value || '' === $value ) {
			return false;
		}
		$expiry = (int) get_option( $this->get_expiry_key( $hash_id ), 0 );
		if ( 0 >= $expiry || $expiry < time() ) {
			$this->delete( $hash_id );
			return false;
		}
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_max_expiry() {
		global $wpdb;
		$expiry_like = $wpdb->esc_like( self::EXPIRY_PREFIX ) . '%';
		$max_expiry  = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT MAX(CAST(option_value AS UNSIGNED)) FROM ' . $wpdb->options . ' WHERE option_name LIKE %s',
				$expiry_like
			)
		);
		if ( 0 === $max_expiry ) {
			return null;
		}
		return $max_expiry;
	}
}
