<?php
/**
 * Cloud App Interface
 *
 * @package LimitLoginAttempts
 * @since 3.3.0
 */

namespace LLAR\Core\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface for Cloud App service.
 * Defines methods for ACL checks, lockout checks, and error handling.
 */
interface CloudAppInterface {

	/**
	 * Check Access Control List for the given payload.
	 *
	 * @param array $payload Request payload containing IP, login, gateway, etc.
	 * @return array|false Response array with 'result' and 'reason', or false on failure.
	 */
	public function acl_check( $payload );

	/**
	 * Check lockout status for the given payload.
	 *
	 * @param array $payload Request payload containing IP, login, gateway, etc.
	 * @return array|false Response array with 'result', 'attempts_left', 'time_left', or false on failure.
	 */
	public function lockout_check( $payload );

	/**
	 * Get accumulated errors from Cloud App API calls.
	 *
	 * @return array Array of error messages.
	 */
	public function get_errors();

	/**
	 * Add an error message to the Cloud App error collection.
	 *
	 * @param string $error Error message to add.
	 * @return void
	 */
	public function add_error( $error );

	/**
	 * Get the HTTP response code from the last Cloud App API call.
	 *
	 * @return int|null HTTP status code or null if no request was made.
	 */
	public function get_last_response_code();
}
