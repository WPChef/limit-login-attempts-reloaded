<?php

namespace LLAR\Core\Mfa\RescuePayloadStorage;

use LLAR\Core\MfaConstants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Selects the best rescue payload storage for current environment.
 */
class RescuePayloadStorageSelector {
	/**
	 * @var RescuePayloadStorageInterface|null
	 */
	private static $selected = null;

	/**
	 * Select storage provider.
	 *
	 * @return RescuePayloadStorageInterface
	 */
	public static function get_storage() {
		if ( null !== self::$selected ) {
			return self::$selected;
		}

		$transient_storage = new RescuePayloadTransientStorage();
		$options_storage   = new RescuePayloadOptionsStorage();

		$selected = $transient_storage;
		if (
			(
				function_exists( 'wp_using_ext_object_cache' )
				&& wp_using_ext_object_cache()
				&& ! self::transient_is_consistent( $transient_storage )
			)
			|| self::transient_links_are_expiring_or_expired( $transient_storage )
		) {
			$selected = $options_storage;
		}

		$filtered = apply_filters( 'llar_mfa_rescue_payload_storage', $selected, $transient_storage, $options_storage );
		if ( $filtered instanceof RescuePayloadStorageInterface ) {
			$selected = $filtered;
		} elseif ( is_string( $filtered ) ) {
			$choice = strtolower( trim( $filtered ) );
			if ( 'options' === $choice ) {
				$selected = $options_storage;
			} elseif ( 'transient' === $choice ) {
				$selected = $transient_storage;
			}
		}

		self::$selected = $selected;
		return self::$selected;
	}

	/**
	 * Check transient read-after-write consistency.
	 *
	 * @param RescuePayloadTransientStorage $storage Transient storage.
	 * @return bool
	 */
	private static function transient_is_consistent( RescuePayloadTransientStorage $storage ) {
		$probe_hash = 'probe_' . wp_generate_password( 16, false, false );
		$probe_data = 'ok_' . wp_generate_password( 16, false, false );
		$saved      = $storage->save( $probe_hash, $probe_data, 30 );
		$read       = $storage->read( $probe_hash );
		$exists     = $storage->exists( $probe_hash );
		$storage->delete( $probe_hash );

		return $saved && $probe_data === $read && $exists;
	}

	/**
	 * Check if transient rescue links are expiring soon or already expired.
	 *
	 * @param RescuePayloadTransientStorage $storage Transient storage.
	 * @return bool
	 */
	private static function transient_links_are_expiring_or_expired( RescuePayloadTransientStorage $storage ) {
		$max_expiry = $storage->get_max_expiry();
		if ( null === $max_expiry ) {
			return false;
		}

		$threshold = time() + (int) MfaConstants::RESCUE_NOTICE_THRESHOLD;
		return (int) $max_expiry <= $threshold;
	}
}
