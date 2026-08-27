<?php

namespace LLAR\Core;

use Exception;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LoginErrorPresenter {

	private $plugin;
	private $cloud_acl;
	private $local_lockout;
	private $ip_resolver;

	public function __construct(
		LimitLoginAttempts $plugin,
		CloudAclService $cloud_acl,
		LocalLockoutManager $local_lockout,
		IpAddressResolver $ip_resolver
	) {
		$this->plugin = $plugin;
		$this->cloud_acl = $cloud_acl;
		$this->local_lockout = $local_lockout;
		$this->ip_resolver = $ip_resolver;
	}

	public function build_lockout_error_message( $time_left = 0 ) {
		$err = __( '<strong>ERROR</strong>: Too many failed login attempts.', 'limit-login-attempts-reloaded' );

		if ( 0 < $time_left ) {
			if ( 60 < $time_left ) {
				$time_left = ceil( $time_left / 60 );
				$err .= ' ' . sprintf( _n( 'Please try again in %d hour.', 'Please try again in %d hours.', $time_left, 'limit-login-attempts-reloaded' ), $time_left );
			} else {
				$err .= ' ' . sprintf( _n( 'Please try again in %d minute.', 'Please try again in %d minutes.', $time_left, 'limit-login-attempts-reloaded' ), $time_left );
			}
		}

		return '<span>' . wp_kses_post( $err ) . '</span>';
	}

	private function resolve_login_username( $username = '' ) {
		if ( '' !== $username ) {
			return $username;
		}

		if ( isset( $_REQUEST['log'] ) ) {
			return sanitize_text_field( wp_unslash( $_REQUEST['log'] ) );
		}

		$identifier = $this->plugin->get_integration_login_identifier();
		if ( '' !== $identifier ) {
			return $identifier;
		}

		return '';
	}

	public function error_msg( $username = '' )
	{
		if ( LimitLoginAttempts::$cloud_app ) {
			$app_errors = LimitLoginAttempts::$cloud_app->get_errors();
			if ( ! empty( $app_errors ) ) {
				$msg = is_array( $app_errors ) ? implode( ' ', $app_errors ) : (string) $app_errors;
				$this->plugin->all_errors_array['late_hook_errors'] = $msg;
				LoginFlowTransientStore::merge( array( 'errors_in_early_hook' => false ) );

				return $msg;
			}

			$resolved_username = $this->resolve_login_username( $username );
			if ( '' !== $resolved_username ) {
				$response = $this->cloud_acl->get_auth_acl_response( $resolved_username );
				if ( $response && 'deny' === $response['result'] ) {
					$time_left = ! empty( $response['time_left'] ) ? (int) $response['time_left'] : 0;
					$msg       = wp_strip_all_tags( $this->build_lockout_error_message( $time_left ) );
					$this->plugin->all_errors_array['late_hook_errors'] = $msg;
					LoginFlowTransientStore::merge( array( 'errors_in_early_hook' => false ) );

					return $msg;
				}
			}
		}

		// Cloud is off or unreachable — fall back to the local lockouts timer so failover messages match the lockout state.

		$ip       = $this->ip_resolver->get_address();
		$lockouts = Config::get( Config::OPTION_LOCKOUTS );
		$a        = $this->local_lockout->check_key($lockouts, $ip);
		$b        = $this->local_lockout->check_key($lockouts, $this->local_lockout->get_hash($ip));

		$msg = __( '<strong>ERROR</strong>: Too many failed login attempts.', 'limit-login-attempts-reloaded' ) . ' ';

		if (
			! is_array( $lockouts )
			|| ( ! isset( $lockouts[ $ip ] ) && ! isset( $lockouts[ $this->local_lockout->get_hash( $ip ) ] ) )
			|| ( time() >= $a && time() >= $b )
		){
			/* Huh? No timeout active? */
			$msg .= __( 'Please try again later.', 'limit-login-attempts-reloaded' );

			$this->plugin->all_errors_array['late_hook_errors'] = $msg;
			LoginFlowTransientStore::merge( array( 'errors_in_early_hook' => false ) );

			return $msg;
		}

		$when = ceil( ( ($a > $b ? $a : $b) - time() ) / 60 );
		if ( $when > 60 ) {

			$when = ceil( $when / 60 );
			$msg .= sprintf( _n( 'Please try again in %d hour.', 'Please try again in %d hours.', $when, 'limit-login-attempts-reloaded' ), $when );
		} else {

			$msg .= sprintf( _n( 'Please try again in %d minute.', 'Please try again in %d minutes.', $when, 'limit-login-attempts-reloaded' ), $when );
		}

		$this->plugin->all_errors_array['late_hook_errors'] = $msg;
		LoginFlowTransientStore::merge( array( 'errors_in_early_hook' => false ) );

		return $msg;
	}

	/**
	 * When returning from MFA with llar_mfa_error, inject an error so WordPress outputs the red #login_error block.
	 *
	 * @param \WP_Error $errors      WP_Error object passed to login_header().
	 * @param string   $redirect_to  Redirect URL.
	 * @return \WP_Error
	 */
	public function inject_mfa_return_login_error( $errors, $redirect_to ) {
		$llar_mfa_error = isset( $_GET['llar_mfa_error'] ) ? sanitize_text_field( wp_unslash( $_GET['llar_mfa_error'] ) ) : '';
		if ( $llar_mfa_error !== '' ) {
			if ( ! is_wp_error( $errors ) ) {
				$errors = new \WP_Error();
			}
			$errors->add( 'llar_mfa_return', __( '<strong>ERROR</strong>: Incorrect username or password.', 'limit-login-attempts-reloaded' ) );
		}
		return $errors;
	}

	/**
	 * Fix up the error message before showing it
	 *
	 * @param $content
	 *
	 * @return string
	 */
	public function fixup_error_messages( $content )
	{
		global $limit_login_just_lockedout, $limit_login_nonempty_credentials, $limit_login_my_error_shown;

		$error_msg = $this->get_message();

		$early_hook_msg = LoginFlowTransientStore::get( 'llar_early_hook_error_message', '' );
		if ( $early_hook_msg !== '' && is_string( $early_hook_msg ) ) {
			$content = $early_hook_msg;
			LoginFlowTransientStore::merge(
				array(
					'llar_early_hook_error_message' => null,
					'errors_in_early_hook'           => false,
				)
			);
		} else {
		$llar_mfa_error = isset( $_GET['llar_mfa_error'] ) ? sanitize_text_field( wp_unslash( $_GET['llar_mfa_error'] ) ) : '';
		$show_mfa_return_error = ( $llar_mfa_error !== '' );

		if ( $limit_login_nonempty_credentials ) {

			$content = '';

			if ( $this->plugin->other_login_errors ) {

				foreach ( $this->plugin->other_login_errors as $msg ) {
					$content .= ! empty( $msg ) ? $msg . '<br />' : '';
				}

			} else {

				/* Replace error message, including ours if necessary */
				if ( ! empty( $_REQUEST['log'] ) && is_email( $_REQUEST['log'] ) ) {

					$content = __( '<strong>ERROR</strong>: Incorrect email address or password.', 'limit-login-attempts-reloaded' );
				} else {

					$content = __( '<strong>ERROR</strong>: Incorrect username or password.', 'limit-login-attempts-reloaded' );
				}
			}
		} elseif ( $show_mfa_return_error ) {
			/* Same red error as failed login when returning from MFA (e.g. pre_auth_required). */
			$content = __( '<strong>ERROR</strong>: Incorrect username or password.', 'limit-login-attempts-reloaded' );
		}

		if ( ! empty( $error_msg ) ) {

			$content = $error_msg;
		}
		}

		$content = ! empty( $content ) ? '<span>' . $content . '</span>' : '';

		$this->plugin->all_errors_array['late_hook_errors'] = $content;
		LoginFlowTransientStore::merge( array( 'errors_in_early_hook' => false ) );

		return $content;
	}

	public function fixup_error_messages_wc( \WP_Error $error )
	{
		$error->add( 1, __( 'WC Error', 'limit-login-attempts-reloaded' ) );
	}

	/**
	 * Return current (error) message to show, if any
	 *
	 * @return string
	 */
	public function get_message()
	{

		if ( LimitLoginAttempts::$cloud_app ) {

			$app_errors = LimitLoginAttempts::$cloud_app->get_errors();
			return ! empty( $app_errors ) ? implode( '<br>', $app_errors ) : '';
		}

		/* Check external whitelist */
		if ( $this->ip_resolver->is_ip_whitelisted() ) {
			return '';
		}

		/* Is lockout in effect? */
		if ( ! $this->local_lockout->is_limit_login_ok() ) {
			return $this->error_msg();
		}

		return '';
	}
}
