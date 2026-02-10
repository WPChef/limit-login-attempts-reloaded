<?php

namespace LLAR\Core\MfaFlow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles MFA callback: llar_mfa=1&token=...&code=...
 * Verifies session and OTP, calls API verify, then logs user in and redirects.
 */
class CallbackHandler {

	/**
	 * Run on init: if request has llar_mfa and token, handle callback or show enter-code form.
	 */
	public static function maybe_handle() {
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		$code  = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';

		$has_llar_mfa_param   = ( isset( $_GET['llar_mfa'] ) && ( $_GET['llar_mfa'] === '1' || $_GET['llar_mfa'] === 'true' ) );
		$has_token_in_request = isset( $_GET['token'] );
		$is_mfa_callback = $token !== '' && ( $has_llar_mfa_param || $has_token_in_request );

		if ( ! $is_mfa_callback ) {
			return;
		}

		if ( $code === '' ) {
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
		if ( ! $session || empty( $session['secret'] ) || empty( $session['username'] ) ) {
			return;
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
		wp_set_auth_cookie( $user->ID, true );
		$redirect_to = ! empty( $session['redirect_to'] ) ? $session['redirect_to'] : '';
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
		$store = new SessionStore();
		$session = $store->get_session( $token );


		if ( ! $session || empty( $session['secret'] ) || empty( $session['username'] ) ) {
			$store->delete_session( $token );
			defined( 'WP_DEBUG' ) && \WP_DEBUG && error_log( LLA_MFA_FLOW_LOG_PREFIX . 'callback session_expired' );
			self::redirect_login( 'llar_mfa_session_expired' );
			return;
		}

		$stored_otp = $store->get_otp( $token );
		$code_match = ( $stored_otp !== null && $stored_otp === $code );
		if ( ! $code_match ) {
			$store->delete_session( $token );
			defined( 'WP_DEBUG' ) && \WP_DEBUG && error_log( LLA_MFA_FLOW_LOG_PREFIX . 'callback code_invalid' );
			self::redirect_login( 'llar_mfa_code_invalid' );
			return;
		}

		$provider_id = isset( $session['provider_id'] ) ? $session['provider_id'] : 'llar';
		$provider    = MfaProviderRegistry::get( $provider_id );
		if ( ! $provider ) {
			$store->delete_session( $token );
			defined( 'WP_DEBUG' ) && \WP_DEBUG && error_log( LLA_MFA_FLOW_LOG_PREFIX . 'callback provider_not_found provider_id=' . $provider_id );
			self::redirect_login( 'llar_mfa_verify_failed' );
			return;
		}
		$result = $provider->verify( $token, $session['secret'] );

		if ( ! $result['success'] || empty( $result['data']['is_verified'] ) ) {
			$store->delete_session( $token );
			defined( 'WP_DEBUG' ) && \WP_DEBUG && error_log( LLA_MFA_FLOW_LOG_PREFIX . 'callback verify_failed' );
			self::redirect_login( 'llar_mfa_verify_failed' );
			return;
		}

		$user_id = ! empty( $session['user_id'] ) ? (int) $session['user_id'] : 0;
		$user    = $user_id ? get_user_by( 'id', $user_id ) : get_user_by( 'login', $session['username'] );

		if ( ! $user || ! is_a( $user, 'WP_User' ) ) {
			$store->delete_session( $token );
			defined( 'WP_DEBUG' ) && \WP_DEBUG && error_log( LLA_MFA_FLOW_LOG_PREFIX . 'callback user_invalid' );
			self::redirect_login( 'llar_mfa_user_invalid' );
			return;
		}

		if ( empty( $session['is_pre_authenticated'] ) ) {
			$store->delete_session( $token );
			defined( 'WP_DEBUG' ) && \WP_DEBUG && error_log( LLA_MFA_FLOW_LOG_PREFIX . 'callback pre_auth_required' );
			self::redirect_login( 'llar_mfa_pre_auth_required' );
			return;
		}

		defined( 'WP_DEBUG' ) && \WP_DEBUG && error_log( LLA_MFA_FLOW_LOG_PREFIX . 'callback success user_id=' . $user->ID );
		wp_clear_auth_cookie();
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );

		$redirect_to = ! empty( $session['redirect_to'] ) ? $session['redirect_to'] : '';
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
