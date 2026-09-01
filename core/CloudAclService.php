<?php

namespace LLAR\Core;

use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cached Cloud ACL checks during authenticate.
 */
class CloudAclService {

	/**
	 * @var array
	 */
	private $auth_acl_response_cache = array();

	/**
	 * @var int
	 */
	private $auth_acl_response_cache_max_size = 50;

	/**
	 * @param string $username Login identifier.
	 * @return array|false
	 * @throws Exception
	 */
	public function get_auth_acl_response( $username ) {
		if ( ! LimitLoginAttempts::$cloud_app ) {
			return false;
		}

		$payload = array(
			'ip'      => Helpers::get_all_ips(),
			'login'   => $username,
			'gateway' => Helpers::detect_gateway(),
		);
		$cache_key = md5( wp_json_encode( $payload ) );

		if ( isset( $this->auth_acl_response_cache[ $cache_key ] ) ) {
			return $this->auth_acl_response_cache[ $cache_key ];
		}

		$response = LimitLoginAttempts::$cloud_app->acl_check( $payload );
		if ( $this->auth_acl_response_cache_max_size <= count( $this->auth_acl_response_cache ) ) {
			array_shift( $this->auth_acl_response_cache );
		}
		$this->auth_acl_response_cache[ $cache_key ] = $response;

		return $response;
	}

	/**
	 * Whether cloud ACL allows login for username.
	 *
	 * @param string $username Username.
	 * @return bool
	 * @throws Exception
	 */
	public function is_cloud_login_allowed( $username = '' ) {
		if ( ! LimitLoginAttempts::$cloud_app ) {
			return true;
		}

		$response = $this->get_auth_acl_response( $username );
		if ( ! $response ) {
			return true;
		}

		return ( 'pass' === $response['result'] || 'allow' === $response['result'] );
	}
}
