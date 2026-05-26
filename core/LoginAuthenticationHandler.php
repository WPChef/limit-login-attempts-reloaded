<?php

namespace LLAR\Core;

use Exception;
use LLAR\Core\MfaConstants;
use LLAR\Core\MfaFlow\MfaFlowLoginHandler;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress authenticate filter chain (ACL, blacklist, late lockout).
 */
class LoginAuthenticationHandler {

	/**
	 * @temporary WP 7.0 compat auth failure codes.
	 * @return array
	 */
	public static function auth_failure_codes() {
		return array( 'invalid_username', 'invalid_email', 'incorrect_password', 'authentication_failed' );
	}

	/** @var LimitLoginAttempts */
	private $plugin;

	/** @var CloudAclService */
	private $cloud_acl;

	/** @var LocalLockoutManager */
	private $local_lockout;

	/** @var IpAddressResolver */
	private $ip_resolver;

	/** @var MfaFlowLoginHandler */
	private $mfa_flow;

	/** @var LoginErrorPresenter */
	private $error_presenter;

	public function __construct(
		LimitLoginAttempts $plugin,
		CloudAclService $cloud_acl,
		LocalLockoutManager $local_lockout,
		IpAddressResolver $ip_resolver,
		MfaFlowLoginHandler $mfa_flow,
		LoginErrorPresenter $error_presenter
	) {
		$this->plugin = $plugin;
		$this->cloud_acl = $cloud_acl;
		$this->local_lockout = $local_lockout;
		$this->ip_resolver = $ip_resolver;
		$this->mfa_flow = $mfa_flow;
		$this->error_presenter = $error_presenter;
	}

	private function check_login_blocked( $username, $password, &$error_message ) {
		if ( empty( $username ) || empty( $password ) ) {
			return false;
		}

		if ( LimitLoginAttempts::$cloud_app && $response = $this->cloud_acl->get_auth_acl_response( $username ) ) {
			if ( 'deny' === $response['result'] ) {
				$time_left = ! empty( $response['time_left'] ) ? (int) $response['time_left'] : 0;
				$error_message = $this->build_lockout_error_message( $time_left );

				LimitLoginAttempts::$cloud_app->add_error( $error_message );
				$this->log_security_event( 'cloud_acl_deny', $username, $this->ip_resolver->get_address(), array( 'time_left' => $time_left ) );
				LoginFlowTransientStore::ensure_token();
				LoginFlowTransientStore::merge(
					array(
						'errors_in_early_hook'           => true,
						'llar_early_hook_error_message' => $error_message,
						'login_attempts_left'            => null,
					)
				);
				$this->plugin->all_errors_array['early_hook_errors'] = $error_message;

				if ( defined('XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
					header('HTTP/1.0 403 Forbidden' );
					exit;
				}

				remove_filter( 'login_errors', array( $this->plugin, 'fixup_error_messages' ) );
				remove_filter( 'wp_login_failed', array( $this->plugin, 'limit_login_failed' ) );
				remove_filter( 'wp_authenticate_user', array( $this->plugin, 'wp_authenticate_user' ), 99999 );

				return true;
			}
		}

		$ip = $this->ip_resolver->get_address();
		if (
			( ! $this->local_lockout->is_username_whitelisted( $username ) && ! $this->ip_resolver->is_ip_whitelisted( $ip ) )
			&& ( $this->local_lockout->is_username_blacklisted( $username ) || $this->ip_resolver->is_ip_blacklisted( $ip ) )
		) {
			$error_message = $this->build_lockout_error_message();
			$this->log_security_event( 'local_blacklist_block', $username, $ip );
			LoginFlowTransientStore::ensure_token();
			LoginFlowTransientStore::merge(
				array(
					'errors_in_early_hook' => true,
					'login_attempts_left'  => null,
				)
			);
			$this->plugin->all_errors_array['early_hook_errors'] = $error_message;

			remove_filter( 'login_errors', array( $this->plugin, 'fixup_error_messages' ) );
			remove_filter( 'wp_login_failed', array( $this->plugin, 'limit_login_failed' ) );
			remove_filter( 'wp_authenticate_user', array( $this->plugin, 'wp_authenticate_user' ), 99999 );

			if ( defined('XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
				header('HTTP/1.0 403 Forbidden' );
				exit;
			}

			return true;
		}

		return false;
	}

	/**
	 * Build lockout error message with optional time left.
	 *
	 * @param int $time_left
	 * @return string
	 */
	private function build_lockout_error_message( $time_left = 0 ) {
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

	/**
	 * Create standardized lockout WP_Error.
	 *
	 * @param string $error_message
	 * @return WP_Error
	 */
	private function create_username_blacklisted_error( $error_message ) {
		return new WP_Error( 'username_blacklisted', $error_message );
	}
	private function log_security_event( $event_type, $username, $ip, $details = array() ) {
		if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
			return;
		}

		error_log(
			'[LLAR Security] ' . wp_json_encode(
				array(
					'event'    => $event_type,
					'username' => $this->mask_username_for_log( $username ),
					'ip'       => $this->mask_ip_for_log( $ip ),
					'gateway'  => Helpers::detect_gateway(),
					'details'  => $this->sanitize_security_log_details( $details ),
				)
			)
		);
	}

	/**
	 * Allow only non-sensitive detail keys in security logs.
	 *
	 * @param array $details Raw details.
	 * @return array
	 */
	private function sanitize_security_log_details( $details ) {
		if ( empty( $details ) || ! is_array( $details ) ) {
			return array();
		}

		$allowed = apply_filters(
			'llar_security_log_detail_keys',
			array( 'time_left', 'attempts', 'reason', 'window' )
		);
		if ( ! is_array( $allowed ) ) {
			$allowed = array( 'time_left', 'attempts', 'reason', 'window' );
		}

		$safe = array();
		foreach ( $details as $key => $value ) {
			if ( in_array( (string) $key, $allowed, true ) ) {
				$safe[ $key ] = $value;
			}
		}

		return $safe;
	}

	/**
	 * Mask username in logs to reduce sensitive data exposure.
	 *
	 * @param string $username
	 * @return string
	 */
	private function mask_username_for_log( $username ) {
		$username = (string) $username;
		$length = strlen( $username );
		if ( $length <= 0 ) {
			return '';
		}
		if ( $length <= 2 ) {
			return str_repeat( '*', $length );
		}

		return substr( $username, 0, 2 ) . str_repeat( '*', $length - 2 );
	}

	/**
	 * Reduce IP precision in debug logs (privacy).
	 *
	 * @param string $ip
	 * @return string
	 */
	private function mask_ip_for_log( $ip ) {
		$ip = (string) $ip;
		if ( '' === $ip ) {
			return '';
		}

		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return preg_replace( '/\.\d+$/', '.0', $ip );
		}

		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			if ( function_exists( 'inet_pton' ) && function_exists( 'inet_ntop' ) ) {
				$binary = inet_pton( $ip );
				if ( false !== $binary && 16 === strlen( $binary ) ) {
					$masked = substr( $binary, 0, 8 ) . str_repeat( "\0", 8 );
					$masked_ip = inet_ntop( $masked );
					if ( false !== $masked_ip ) {
						return $masked_ip . '/64';
					}
				}
			}
			if ( function_exists( 'wp_hash' ) ) {
				return wp_hash( $ip );
			}
		}

		return '***';
	}

	public function authenticate_filter( $user, $username, $password )
	{
		LoginFlowTransientStore::ensure_token();
		if ( ! is_wp_error( $user ) ) {
			LoginFlowTransientStore::merge( array( 'errors_in_early_hook' => false ) );
		}

		$error_message = '';
		if ( $this->check_login_blocked( $username, $password, $error_message ) ) {
			return $this->create_username_blacklisted_error( $error_message );
		}

		if ( ! empty( $username ) && ! empty( $password ) ) {
			$ip = $this->ip_resolver->get_address();

			if ( LimitLoginAttempts::$cloud_app && $response = $this->cloud_acl->get_auth_acl_response( $username ) ) {
				if ( 'pass' === $response['result'] ) {
					remove_filter( 'login_errors', array( $this->plugin, 'fixup_error_messages' ) );

					// @temporary WP 7.0 compat — on WP 7.0+ keep all hooks active; late safety net handles recording and lockout.
					// TODO: Remove after WP 7.1 release or when auth flow is stable.
					// On older WP, preserve original hook removal logic.
					if ( ! LimitLoginAttempts::is_wp_at_least( '7.0' ) ) {
						$mfa_effectively_enabled = Config::get( 'mfa_enabled' ) && ( false === get_transient( MfaConstants::TRANSIENT_MFA_DISABLED ) );
						if ( ! $mfa_effectively_enabled ) {
							remove_filter( 'wp_login_failed', array( $this->plugin, 'limit_login_failed' ) );
						}
						remove_filter( 'wp_authenticate_user', array( $this->plugin, 'wp_authenticate_user' ), 99999 );
					}
				}
			} else {

				$ip = $this->ip_resolver->get_address();

				// Check if username is blacklisted
				if (
					( ! $this->local_lockout->is_username_whitelisted( $username ) && ! $this->ip_resolver->is_ip_whitelisted( $ip ) )
					&& ( $this->local_lockout->is_username_blacklisted( $username ) || $this->ip_resolver->is_ip_blacklisted( $ip ) )
				) {

					LoginFlowTransientStore::merge( array( 'login_attempts_left' => null ) );

					remove_filter( 'login_errors', array( $this->plugin, 'fixup_error_messages' ) );
					remove_filter( 'wp_login_failed', array( $this->plugin, 'limit_login_failed' ) );
					remove_filter( 'wp_authenticate_user', array( $this->plugin, 'wp_authenticate_user' ), 99999 );

					// Remove default WP authentication filters
					remove_filter( 'authenticate', 'wp_authenticate_username_password', 20 );
					remove_filter( 'authenticate', 'wp_authenticate_email_password', 20 );

					$user = new WP_Error();
					$err = __( '<strong>ERROR</strong>: Too many failed login attempts.', 'limit-login-attempts-reloaded' );

					$err = ! empty( $err ) ? '<span>' . $err . '</span>' : '';

					$user->add( 'username_blacklisted', $err );

					LoginFlowTransientStore::merge( array( 'errors_in_early_hook' => true ) );
					$this->plugin->all_errors_array['early_hook_errors'] = $err;

					if ( defined('XMLRPC_REQUEST') && XMLRPC_REQUEST ) {

						header('HTTP/1.0 403 Forbidden');
						exit;
					}

				} elseif ( $this->local_lockout->is_username_whitelisted( $username ) || $this->ip_resolver->is_ip_whitelisted( $ip ) ) {
					LoginFlowTransientStore::merge( array( 'llar_user_is_whitelisted' => true ) );
					// Do not run limit_login_failed for whitelist: no lockout, but lockout_check / retries would still run and hit the API.
					remove_filter( 'wp_login_failed', array( $this->plugin, 'limit_login_failed' ) );
					$mfa_effectively_enabled = Config::get( 'mfa_enabled' ) && ( false === get_transient( MfaConstants::TRANSIENT_MFA_DISABLED ) );
					if ( ! $mfa_effectively_enabled ) {
						remove_filter( 'wp_authenticate_user', array( $this->plugin, 'wp_authenticate_user' ), 99999 );
					}
					remove_filter( 'login_errors', array( $this->plugin, 'fixup_error_messages' ) );

				} elseif ( LimitLoginAttempts::$cloud_app && LimitLoginAttempts::$cloud_app->last_response_code === 403 ) {
					add_action('wp_login', array( $this->plugin, 'cloud_app_null' ), 999);
				}
			}
		}

		return $user;
	}

	public function authenticate_guard_filter( $user, $username, $password ) {

		$error_message = '';
		if ( $this->check_login_blocked( $username, $password, $error_message ) ) {
			remove_filter( 'authenticate', 'wp_authenticate_username_password', 20 );
			remove_filter( 'authenticate', 'wp_authenticate_email_password', 20 );
			return $this->create_username_blacklisted_error( $error_message );
		}

		return $user;
	}

	public function authenticate_filter_errors_fix( $user, $username, $password )
	{
		if ( ! empty( $username ) && ! empty( $password ) ) {

			if ( is_wp_error( $user ) ) {

				// @temporary WP 7.0 compat — fallback recording for auth flows where wp_login_failed is unreliable.
				// TODO: Remove after WP 7.1 release or when auth flow is stable.
				if ( LimitLoginAttempts::is_wp_at_least( '7.0' ) ) {
					$error_codes = $user->get_error_codes();
					if (
						! LocalLockoutManager::is_failed_login_recorded_in_request()
						&& array_intersect( LoginAuthenticationHandler::auth_failure_codes(), $error_codes )
					) {
						$this->local_lockout->record_failed_login_attempt( $username );
					}
				}

				// BuddyPress errors
				if ( in_array('bp_account_not_activated', $user->get_error_codes() ) ) {

					$this->plugin->other_login_errors[] = $user->get_error_message('bp_account_not_activated');
				} elseif ( in_array('wfls_captcha_verify', $user->get_error_codes() ) ) { // Wordfence errors

					$this->plugin->other_login_errors[] = $user->get_error_message( 'wfls_captcha_verify' );
				}
			}

		}
		return $user;
	}

	public function authenticate_late_lockout_check( $user, $username, $password ) {
		if ( empty( $username ) || empty( $password ) ) {
			return $user;
		}

		// Successful auth already validated by earlier guards — do not override.
		if ( $user instanceof \WP_User ) {
			return $user;
		}

		if ( is_wp_error( $user ) ) {
			$error_codes = $user->get_error_codes();

			if (
				in_array( 'too_many_retries', $error_codes, true )
				|| in_array( 'username_blacklisted', $error_codes, true )
			) {
				return $user;
			}

			if ( ! LocalLockoutManager::is_failed_login_recorded_in_request() ) {
				if ( array_intersect( LoginAuthenticationHandler::auth_failure_codes(), $error_codes ) ) {
					$this->local_lockout->record_failed_login_attempt( $username );
				}
			}
		}

		$ip = $this->ip_resolver->get_address();
		if (
			! $this->ip_resolver->is_ip_whitelisted( $ip )
			&& ! $this->local_lockout->is_username_whitelisted( $username )
			&& ! $this->local_lockout->is_limit_login_ok( $username )
		) {
			global $limit_login_my_error_shown;
			$limit_login_my_error_shown = true;

			$error = new WP_Error();
			$error->add( 'too_many_retries', $this->error_presenter->error_msg( $username ) );
			LoginFlowTransientStore::merge( array( 'errors_in_early_hook' => false ) );

			return $error;
		}

		return $user;
	}

	public function wp_authenticate_user( $user, $password )
	{
		$username = isset( $_REQUEST['log'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['log'] ) ) : '';
		if ( '' === $username && $this->plugin->integration_manager ) {
			$username = $this->plugin->integration_manager->get_login_identifier();
		}
		if ( empty( $password ) && $this->plugin->integration_manager ) {
			$integration_credentials = $this->plugin->integration_manager->get_login_credentials();
			if ( is_array( $integration_credentials ) && ! empty( $integration_credentials['password'] ) ) {
				$password = $integration_credentials['password'];
			}
		}
		$ip       = $this->ip_resolver->get_address();
		$user_login = is_a( $user, 'WP_User' ) ? $user->user_login : ( ( ! empty( $user ) && ! is_wp_error( $user ) ) ? $user : '' );
		$not_locked_out = $this->local_lockout->check_whitelist_ips( false, $ip ) || $this->local_lockout->check_whitelist_usernames( false, $user_login ) || $this->local_lockout->is_limit_login_ok( $username );

		if ( is_wp_error( $user ) ) {
			return $user;
		}

		// is_pre_authenticated must reflect actual password check: WP may pass valid $user by username before password is verified.
		$password_ok = false;
		if ( is_a( $user, 'WP_User' ) && ! empty( $password ) ) {
			$password_ok = wp_check_password( $password, $user->user_pass, $user->ID );
		}

		// If locked out, do not run MFA flow — return lockout error so blocked user cannot bypass via correct password + MFA.
		if ( ! $not_locked_out ) {
			$error = new WP_Error();
			global $limit_login_my_error_shown;
			$limit_login_my_error_shown = true;
			$error->add( 'too_many_retries', $this->error_presenter->error_msg( $username ) );
			LoginFlowTransientStore::merge( array( 'errors_in_early_hook' => false ) );
			return $error;
		}

		// Trigger MFA flow (for selected roles). Only treat as pre-authenticated if password was verified.
		if ( $username !== '' ) {
			$auth_user_for_mfa = ( $password_ok && is_a( $user, 'WP_User' ) ) ? $user : null;
			$this->mfa_flow->try_mfa_flow_redirect( $username, $password_ok, $auth_user_for_mfa );
		}

		$user_login = '';

		if ( is_a( $user, 'WP_User' ) ) {

			$user_login = $user->user_login;
		} elseif( ! empty( $user ) && !is_wp_error( $user ) ) {

			$user_login = $user;
		}

		if (
			$this->local_lockout->check_whitelist_ips( false, $ip )
			|| $this->local_lockout->check_whitelist_usernames( false, $user_login )
			|| $this->local_lockout->is_limit_login_ok( $username )
		) {
			return $user;
		}

		$error = new WP_Error();

		global $limit_login_my_error_shown;
		$limit_login_my_error_shown = true;

		if ( $this->local_lockout->is_username_blacklisted( $user_login ) || $this->ip_resolver->is_ip_blacklisted( $this->ip_resolver->get_address() ) ) {

			$err = __( '<strong>ERROR</strong>: Too many failed login attempts.', 'limit-login-attempts-reloaded' );
			$err = ! empty( $err ) ? '<span>' . $err . '</span>' : '';

			$error->add( 'username_blacklisted', $err );
			$this->plugin->all_errors_array['late_hook_errors'] = $err;
		} else {

			// This error should be the same as in "shake it" filter below
			$error->add( 'too_many_retries', $this->error_presenter->error_msg( $username ) );
		}

		LoginFlowTransientStore::merge( array( 'errors_in_early_hook' => false ) );

		return $error;
	}
	public function track_credentials( $user, $username, $password )
	{
		global $limit_login_nonempty_credentials;

		$limit_login_nonempty_credentials = ( ! empty( $username ) && ! empty( $password ) );

		return $user;
	}

}
