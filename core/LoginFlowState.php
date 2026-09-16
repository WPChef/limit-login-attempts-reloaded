<?php
/**
 * Login Flow State Manager
 *
 * @package LimitLoginAttempts
 * @since 3.3.0
 */

namespace LLAR\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages login flow state for the current request.
 * Replaces global variables with a cleaner singleton pattern.
 */
class LoginFlowState {

	/**
	 * Singleton instance.
	 *
	 * @var LoginFlowState|null
	 */
	private static $instance = null;

	/**
	 * Whether user just got locked out in this request.
	 *
	 * @var bool
	 */
	private $just_lockedout = false;

	/**
	 * Whether credentials were non-empty in this request.
	 *
	 * @var bool
	 */
	private $nonempty_credentials = false;

	/**
	 * Whether error message was already shown.
	 *
	 * @var bool
	 */
	private $error_shown = false;

	/**
	 * Private constructor for singleton.
	 */
	private function __construct() {}

	/**
	 * Get singleton instance.
	 *
	 * @return LoginFlowState
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Reset state for persistent runtimes (Swoole, FrankenPHP).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$instance = new self();
	}

	/**
	 * Set just locked out flag.
	 *
	 * @param bool $value Value.
	 * @return void
	 */
	public function set_just_lockedout( $value ) {
		$this->just_lockedout = (bool) $value;
	}

	/**
	 * Get just locked out flag.
	 *
	 * @return bool
	 */
	public function is_just_lockedout() {
		return $this->just_lockedout;
	}

	/**
	 * Set nonempty credentials flag.
	 *
	 * @param bool $value Value.
	 * @return void
	 */
	public function set_nonempty_credentials( $value ) {
		$this->nonempty_credentials = (bool) $value;
	}

	/**
	 * Get nonempty credentials flag.
	 *
	 * @return bool
	 */
	public function has_nonempty_credentials() {
		return $this->nonempty_credentials;
	}

	/**
	 * Set error shown flag.
	 *
	 * @param bool $value Value.
	 * @return void
	 */
	public function set_error_shown( $value ) {
		$this->error_shown = (bool) $value;
	}

	/**
	 * Get error shown flag.
	 *
	 * @return bool
	 */
	public function is_error_shown() {
		return $this->error_shown;
	}
}
