<?php

namespace LLAR\Core\MfaFlow;

use LLAR\Core\Helpers;
use LLAR\Core\LimitLoginAttempts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles MFA callback: llar_mfa=1&token=...&code=...
 * Verifies session and OTP, calls API verify, then logs user in and redirects.
 */
class CallbackHandler {
	/**
	 * Record successful login in Cloud App for MFA-based auth.
	 *
	 * Note: MFA callback logs the user in via cookies and does not trigger `wp_login`.
	 *
	 * @param WP_User $user
	 * @param string  $username
	 *
	 * @return void
	 */
	private static function record_successful_login( $user, $username ) {
		if ( empty( $username ) ) {
			return;
		}

		if ( ! $user || ! is_object( $user ) || empty( $user->ID ) ) {
			return;
		}

		if ( ! LimitLoginAttempts::$cloud_app ) {
			return;
		}

		$clean_url = '';
		if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
			$referer_url    = $_SERVER['HTTP_REFERER'];
			$referer_parsed = parse_url( $referer_url );
			$clean_url      = isset( $referer_parsed['path'] ) ? $referer_parsed['path'] : '';
			$clean_url      = trim( $clean_url, '/' );
		}

		$gateway = Helpers::detect_gateway();
		$data    = array(
			'ip'        => Helpers::get_all_ips(),
			'login'     => $username,
			'user_id'   => (int) $user->ID,
			'gateway'   => $gateway,
			'roles'     => isset( $user->roles ) ? $user->roles : array(),
			'agent'     => isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '',
			'url'       => $clean_url,
		);

		LimitLoginAttempts::$cloud_app->request( 'login', 'post', $data );
	}

	/**
	 * Run on init: if request has llar_mfa and token, handle callback or show enter-code form.
	 *
	 * @return void
	 */
	public static function maybe_handle() {
		// Do not treat send-code endpoint as MFA callback (it uses token in POST body).
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
		if ( $action === 'llar_mfa_flow_send_code' ) {
			return;
		}
		if ( function_exists( 'rest_url' ) && isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], 'rest_route=' ) !== false && strpos( $_SERVER['REQUEST_URI'], 'llar/v1/mfa/send-code' ) !== false ) {
			return;
		}

		$token            = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		$token_from_query = $token;
		$code  = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		if ( '' === $code && isset( $_POST['code'] ) ) {
			$code = sanitize_text_field( wp_unslash( $_POST['code'] ) );
		}

		$has_llar_mfa_param = ( isset( $_GET['llar_mfa'] ) && ( $_GET['llar_mfa'] === '1' || $_GET['llar_mfa'] === 'true' ) );
		$cookie_state       = isset( $_COOKIE['llar_mfa_state'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['llar_mfa_state'] ) ) : '';
		if ( '' === $token && $has_llar_mfa_param && '' !== $cookie_state ) {
			$store          = new SessionStore();
			$resolved_token = $store->get_callback_token( $cookie_state );
			if ( is_string( $resolved_token ) && '' !== $resolved_token ) {
				$token = $resolved_token;
			}
		}
		$is_mfa_callback    = $token !== '' && $has_llar_mfa_param;

		if ( ! $is_mfa_callback ) {
			return;
		}

		if ( $code === '' ) {
			// If callback arrived without explicit token in URL, treat it as local MFA screen entry.
			// Do not consume callback state yet; wait for a code submit.
			if ( '' === $token_from_query ) {
				return;
			}
			// Return from external MFA app with token only: try API verify; if not verified, redirect to login (no on-site code form).
			self::try_verify_and_login( $token );
			self::redirect_login( 'llar_mfa_session_expired' );
			exit;
		}

		self::handle( $token, $code );
		exit;
	}

	/**
	 * When we have token but no code (return from external app): call API verify; if is_verified, log user in and redirect.
	 *
	 * @param string $token Session token.
	 * @return void Exits on success; returns otherwise.
	 */
	private static function try_verify_and_login( $token ) {
		$store   = new SessionStore();
		$session = $store->get_session( $token );
		$cookie  = isset( $_COOKIE['llar_mfa_state'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['llar_mfa_state'] ) ) : '';
		if ( ! $session || empty( $session['secret'] ) || empty( $session['username'] ) ) {
			return;
		}
		$verify = $store->consume_callback_state( $cookie, $token );
		setcookie( 'llar_mfa_state', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
		if ( ! $verify ) {
			$store->delete_session( $token );
			self::redirect_login( 'llar_mfa_session_expired' );
			exit;
		}
		if ( empty( $session['is_pre_authenticated'] ) ) {
			return;
		}
		$provider_id = isset( $session['provider_id'] ) ? $session['provider_id'] : 'llar';
		$provider    = MfaProviderRegistry::get( $provider_id );
		if ( ! $provider ) {
			return;
		}
		$result = $provider->verify( $token, $session['secret'] );
		if ( ! $result['success'] || empty( $result['data']['is_verified'] ) ) {
			return;
		}
		$user_id = ! empty( $session['user_id'] ) ? (int) $session['user_id'] : 0;
		$user    = $user_id ? get_user_by( 'id', $user_id ) : get_user_by( 'login', $session['username'] );
		if ( ! $user || ! is_a( $user, 'WP_User' ) ) {
			return;
		}
		wp_clear_auth_cookie();
		wp_set_current_user( $user->ID );
		$remember_me = ! empty( $session['remember_me'] );
		wp_set_auth_cookie( $user->ID, $remember_me );
		self::record_successful_login( $user, $session['username'] );
		$redirect_to  = ! empty( $session['redirect_to'] ) ? $session['redirect_to'] : '';
		$redirect_url = ( $redirect_to && self::is_safe_redirect( $redirect_to ) ) ? $redirect_to : admin_url();
		wp_safe_redirect( $redirect_url );
		$store->delete_session( $token );
		exit;
	}

	/**
	 * Handle callback: load session, verify OTP and API, then login and redirect.
	 *
	 * @param string $token Session token.
	 * @param string $code  User-entered OTP code.
	 */
	public static function handle( $token, $code ) {
		$store   = new SessionStore();
		$session = $store->get_session( $token );
		$cookie  = isset( $_COOKIE['llar_mfa_state'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['llar_mfa_state'] ) ) : '';
		if ( ! $session || empty( $session['secret'] ) || empty( $session['username'] ) ) {
			$store->delete_session( $token );
			self::redirect_login( 'llar_mfa_session_expired' );
			return;
		}
		$verify = $store->consume_callback_state( $cookie, $token );
		setcookie( 'llar_mfa_state', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
		if ( ! $verify ) {
			$store->delete_session( $token );
			self::redirect_login( 'llar_mfa_session_expired' );
			return;
		}

		if ( ! $store->verify_otp_once( $token, $code ) ) {
			$store->delete_session( $token );
			self::redirect_login( 'llar_mfa_code_invalid' );
			return;
		}

		$provider_id = isset( $session['provider_id'] ) ? $session['provider_id'] : 'llar';
		$provider    = MfaProviderRegistry::get( $provider_id );
		if ( ! $provider ) {
			$store->delete_session( $token );
			self::redirect_login( 'llar_mfa_verify_failed' );
			return;
		}
		$result = $provider->verify( $token, $session['secret'] );

		if ( ! $result['success'] || empty( $result['data']['is_verified'] ) ) {
			$store->delete_session( $token );
			self::redirect_login( 'llar_mfa_verify_failed' );
			return;
		}

		$user_id = ! empty( $session['user_id'] ) ? (int) $session['user_id'] : 0;
		$user    = $user_id ? get_user_by( 'id', $user_id ) : get_user_by( 'login', $session['username'] );

		if ( ! $user || ! is_a( $user, 'WP_User' ) ) {
			$store->delete_session( $token );
			self::redirect_login( 'llar_mfa_user_invalid' );
			return;
		}

		if ( empty( $session['is_pre_authenticated'] ) ) {
			$store->delete_session( $token );
			self::redirect_login( 'llar_mfa_pre_auth_required' );
			return;
		}

		wp_clear_auth_cookie();
		wp_set_current_user( $user->ID );
		$remember_me = ! empty( $session['remember_me'] );
		wp_set_auth_cookie( $user->ID, $remember_me );
		self::record_successful_login( $user, $session['username'] );

		$redirect_to  = ! empty( $session['redirect_to'] ) ? $session['redirect_to'] : '';
		$redirect_url = ( $redirect_to && self::is_safe_redirect( $redirect_to ) ) ? $redirect_to : admin_url();
		wp_safe_redirect( $redirect_url );
		$store->delete_session( $token );
		exit;
	}

	/**
	 * Redirect to login with optional message key.
	 *
	 * @param string $msg_key Optional. Query arg for message.
	 */
	private static function redirect_login( $msg_key = '' ) {
		$url = wp_login_url();
		if ( $msg_key ) {
			$url = add_query_arg( 'llar_mfa_error', $msg_key, $url );
		}
		wp_safe_redirect( $url );
	}

	/**
	 * Check if redirect URL is safe (same host or allowed).
	 *
	 * @param string $url Redirect URL.
	 * @return bool
	 */
	private static function is_safe_redirect( $url ) {
		$allowed = wp_validate_redirect( $url, false );
		return ( $allowed !== false );
	}

}
