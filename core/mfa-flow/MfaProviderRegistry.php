<?php

namespace LLAR\Core\MfaFlow;

use LLAR\Core\MfaFlow\Providers\MfaProviderInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registry of MFA providers. Default provider 'llar' (email) is registered on init.
 */
class MfaProviderRegistry {

	/**
	 * @var MfaProviderInterface[]
	 */
	private static $providers = array();

	/**
	 * Register a provider.
	 *
	 * @param MfaProviderInterface $provider Provider instance.
	 */
	public static function register( MfaProviderInterface $provider ) {
		$id = $provider->get_id();
		if ( is_string( $id ) && $id !== '' ) {
			self::$providers[ $id ] = $provider;
		}
	}

	/**
	 * Get provider by id.
	 *
	 * @param string $id Provider id.
	 * @return MfaProviderInterface|null
	 */
	public static function get( $id ) {
		return isset( self::$providers[ $id ] ) ? self::$providers[ $id ] : null;
	}

	/**
	 * Get all registered providers.
	 *
	 * @return MfaProviderInterface[]
	 */
	public static function get_all() {
		return self::$providers;
	}
}
