<?php

namespace LLAR\Core;

use Exception;
use LLAR\Core\Integrations\BaseIntegration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cloud registration ACL limits.
 */
class RegistrationLimiter {

	/**
	 * @var LimitLoginAttempts
	 */
	private $plugin;

	/**
	 * @param LimitLoginAttempts $plugin Plugin facade (user_blocking flags).
	 */
	public function __construct( LimitLoginAttempts $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * @return bool
	 */
	private function is_limit_registration() {
		if ( ! LimitLoginAttempts::$cloud_app ) {
			return false;
		}
		$app_config         = Config::get( 'app_config' );
		$limit_registration = isset( $app_config['settings']['limit_registration']['value'] ) ? $app_config['settings']['limit_registration']['value'] : '';
		return 'on' === $limit_registration;
	}

	/**
	 * @param mixed $user_data User login or email.
	 * @return bool|mixed
	 * @throws Exception
	 */
	private function llar_api_response( $user_data ) {
		return LimitLoginAttempts::$cloud_app->acl_check(
			array(
				'ip'      => Helpers::get_all_ips(),
				'login'   => $user_data,
				'gateway' => Helpers::detect_gateway(),
			)
		);
	}

	/**
	 * @param string               $user_data     User data.
	 * @param BaseIntegration|null $integration   Integration instance.
	 * @return array
	 */
	public function check_registration_api( $user_data, $integration = null ) {
		if ( null !== $integration && $integration instanceof BaseIntegration ) {
			$integration_class  = get_class( $integration );
			$expected_namespace = 'LLAR\Core\Integrations\\';
			if ( 0 !== strpos( $integration_class, $expected_namespace ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( sprintf( 'LLAR: Security check failed - integration class %s is not in trusted namespace', $integration_class ) );
				}
				return array( 'result' => 'deny' );
			}
			return $this->llar_api_response( $user_data );
		}
		return array( 'result' => 'deny' );
	}

	/**
	 * @return void
	 */
	public function llar_submit_login_form_register() {
		if ( ! $this->is_limit_registration() ) {
			return;
		}
		if ( empty( $_POST['user_login'] ) && empty( $_POST['user_email'] ) ) {
			return;
		}
		$user_login = $_POST['user_login'];
		$user_email = $_POST['user_email'];
		if ( ( empty( $user_login ) || ! validate_username( $user_login ) ) && ( empty( $user_email ) || ! is_email( $user_email ) ) ) {
			return;
		}
		$user_login_sanitize = sanitize_user( $_POST['user_login'] );
		$user_email_sanitize = sanitize_email( $_POST['user_email'] );
		$check_combo         = ! empty( $user_login_sanitize ) ? $user_login_sanitize : $user_email_sanitize;
		$response            = $this->llar_api_response( $check_combo );
		if ( ! empty( $user_login_sanitize ) && 'deny' !== $response['result'] ) {
			if ( empty( $user_email ) || ! is_email( $user_email ) ) {
				return;
			}
			$response = $this->llar_api_response( $user_email_sanitize );
		}
		if ( 'deny' === $response['result'] ) {
			$_POST['user_login']  = '';
			$_POST['user_email']  = '';
			$this->plugin->user_blocking  = true;
			$this->plugin->error_messages = __( 'Registration is currently disabled.', 'limit-login-attempts-reloaded' );
		}
	}

	/**
	 * @param \WP_Error $errors               Errors.
	 * @param string    $sanitized_user_login Login.
	 * @param string    $user_email           Email.
	 * @return mixed
	 */
	public function llar_submit_registration_errors( $errors, $sanitized_user_login, $user_email ) {
		if ( $this->plugin->user_blocking && ( empty( $sanitized_user_login ) && empty( $user_email ) ) ) {
			$errors->remove( 'empty_username' );
			$errors->remove( 'empty_email' );
			$errors->add( 'user_blocking', $this->plugin->error_messages );
		}
		return $errors;
	}
}
