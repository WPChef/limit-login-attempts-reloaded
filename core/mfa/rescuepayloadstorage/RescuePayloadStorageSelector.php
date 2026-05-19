<?php

namespace LLAR\Core\Mfa\RescuePayloadStorage;

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

		switch ( true ) {
			case defined( 'LLA_MFA_FORCE_TRANSIENT_PROVIDER' ) && LLA_MFA_FORCE_TRANSIENT_PROVIDER:
			case self::is_rescue_link_open_request():
				// Payload may exist only in transients (e.g. links generated with transient storage); prefer transient on consume even when external object cache would switch writes to options elsewhere.
				$selected = $transient_storage;
				break;
			case ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() )
				|| self::is_rescue_generation_request():
				$selected = $options_storage;
				break;
			default:
				$selected = $transient_storage;
				break;
		}

		$filtered = apply_filters( 'llar_mfa_rescue_payload_storage', $selected, $transient_storage, $options_storage );
		switch ( true ) {
			case $filtered instanceof RescuePayloadStorageInterface:
				$selected = $filtered;
				break;
			case is_string( $filtered ):
				$choice = strtolower( trim( $filtered ) );
				switch ( true ) {
					case 'options' === $choice:
						$selected = $options_storage;
						break;
					case 'transient' === $choice:
						$selected = $transient_storage;
						break;
				}
				break;
		}

		self::$selected = $selected;
		return self::$selected;
	}

	/**
	 * Whether current request is rescue-links generation.
	 *
	 * @return bool
	 */
	private static function is_rescue_generation_request() {
		if ( ! function_exists( 'wp_doing_ajax' ) || ! wp_doing_ajax() ) {
			return false;
		}
		$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';
		return 'llar_mfa_generate_rescue_codes' === $action;
	}

	/**
	 * Whether this HTTP request is opening a rescue link (consumption), not admin AJAX generation.
	 *
	 * @return bool
	 */
	private static function is_rescue_link_open_request() {
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return false;
		}
		if ( isset( $_GET['llar_rescue'] ) && is_string( $_GET['llar_rescue'] ) && '' !== $_GET['llar_rescue'] ) {
			return true;
		}
		return false;
	}
}
