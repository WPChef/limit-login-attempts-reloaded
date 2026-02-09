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
		if ( $token === '' && isset( $_POST['llar_mfa_token'] ) ) {
			$token = sanitize_text_field( wp_unslash( $_POST['llar_mfa_token'] ) );
		}
		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		if ( $code === '' && isset( $_POST['llar_mfa_code'] ) ) {
			$code = sanitize_text_field( wp_unslash( $_POST['llar_mfa_code'] ) );
		}

		$is_mfa_callback = ( isset( $_GET['llar_mfa'] ) && ( $_GET['llar_mfa'] === '1' || $_GET['llar_mfa'] === 'true' ) ) || ( isset( $_POST['llar_mfa'] ) && $_POST['llar_mfa'] === '1' );
		$is_mfa_callback = $is_mfa_callback && $token !== '';

		if ( ! $is_mfa_callback ) {
			return;
		}

		if ( $code === '' ) {
			self::render_enter_code_form( $token );
			exit;
		}

		self::handle( $token, $code );
		exit;
	}

	/**
	 * Output minimal HTML form to enter OTP code (e.g. for email/SMS providers).
	 *
	 * @param string $token Session token.
	 */
	public static function render_enter_code_form( $token ) {
		$action = add_query_arg( array( 'llar_mfa' => '1', 'token' => $token ), wp_login_url() );
		$cancel_url = add_query_arg( 'llar_mfa_cancelled', '1', wp_login_url() );
		$title = __( 'Verification code', 'limit-login-attempts-reloaded' );
		$label = __( 'Enter the code we sent to your email', 'limit-login-attempts-reloaded' );
		$submit = __( 'Verify', 'limit-login-attempts-reloaded' );
		$cancel = __( 'Cancel', 'limit-login-attempts-reloaded' );
		header( 'Content-Type: text/html; charset=utf-8' );
		echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . esc_html( $title ) . '</title></head><body>';
		echo '<form method="post" action="' . esc_attr( $action ) . '">';
		echo '<input type="hidden" name="llar_mfa" value="1">';
		echo '<input type="hidden" name="llar_mfa_token" value="' . esc_attr( $token ) . '">';
		echo '<p><label for="llar_mfa_code">' . esc_html( $label ) . '</label></p>';
		echo '<p><input type="text" name="llar_mfa_code" id="llar_mfa_code" autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]*" maxlength="10" required></p>';
		echo '<p><button type="submit">' . esc_html( $submit ) . '</button> <a href="' . esc_attr( $cancel_url ) . '">' . esc_html( $cancel ) . '</a></p>';
		echo '</form></body></html>';
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
			MfaFlowLogger::log( 'callback', 'session_expired', array() );
			self::redirect_login( 'llar_mfa_session_expired' );
			return;
		}

		$stored_otp = $store->get_otp( $token );
		if ( $stored_otp === null || $stored_otp !== $code ) {
			$store->delete_session( $token );
			MfaFlowLogger::log( 'callback', 'code_invalid', array() );
			self::redirect_login( 'llar_mfa_code_invalid' );
			return;
		}

		$provider_id = isset( $session['provider_id'] ) ? $session['provider_id'] : 'llar';
		$provider    = MfaProviderRegistry::get( $provider_id );
		if ( ! $provider ) {
			$store->delete_session( $token );
			MfaFlowLogger::log( 'callback', 'provider_not_found', array( 'provider_id' => $provider_id ) );
			self::redirect_login( 'llar_mfa_verify_failed' );
			return;
		}
		$result = $provider->verify( $token, $session['secret'] );

		if ( ! $result['success'] || empty( $result['data']['is_verified'] ) ) {
			$store->delete_session( $token );
			MfaFlowLogger::log( 'callback', 'verify_failed', array() );
			self::redirect_login( 'llar_mfa_verify_failed' );
			return;
		}

		$user_id = ! empty( $session['user_id'] ) ? (int) $session['user_id'] : 0;
		$user    = $user_id ? get_user_by( 'id', $user_id ) : get_user_by( 'login', $session['username'] );

		if ( ! $user || ! is_a( $user, 'WP_User' ) ) {
			$store->delete_session( $token );
			MfaFlowLogger::log( 'callback', 'user_invalid', array() );
			self::redirect_login( 'llar_mfa_user_invalid' );
			return;
		}

		MfaFlowLogger::log( 'callback', 'success', array( 'user_id' => $user->ID ) );
		wp_clear_auth_cookie();
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );

		$redirect_to = ! empty( $session['redirect_to'] ) ? $session['redirect_to'] : '';
		if ( $redirect_to && self::is_safe_redirect( $redirect_to ) ) {
			wp_safe_redirect( $redirect_to );
		} else {
			wp_safe_redirect( admin_url() );
		}

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
