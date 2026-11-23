<?php

namespace LLAR\Core\MfaFlow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MFA flow: shared logic for sending verification code via the session's provider.
 * Used by both AJAX (admin-ajax.php) and REST API endpoints.
 * Endpoints accept POST with token, secret (send_email_secret), code in request body.
 * The same secret can be used to send the code multiple times (resend) until the session expires.
 * Actual delivery (email, SMS, etc.) is delegated to the provider registered for the session.
 *
 * @return array { 'success' => bool, 'http_status' => int, 'message' => string|null }
 */
class MfaFlowSendCode {

	/**
	 * Execute send-code: validate secret, resolve provider from session, send via provider, save OTP.
	 *
	 * @param string $token   Session token.
	 * @param string $secret  Send_code secret (from request body).
	 * @param string $code    Verification code to send and store.
	 * @param array  $context Optional. Keys: ip, browser, location (from request body).
	 * @return array { 'success' => bool, 'http_status' => int, 'message' => string|null }
	 */
	/**
	 * Log send-code Forbidden branch under WP_DEBUG (no secrets; token prefix only).
	 *
	 * @param string $reason Branch id: missing_token, missing_secret, no_send_secret,
	 *                       secret_mismatch, no_session, …
	 * @param string $token  Session token (optional; only prefix is logged).
	 * @return void
	 */
	public static function debug_forbidden( $reason, $token = '' ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}
		$prefix = '';
		if ( is_string( $token ) && '' !== $token ) {
			$prefix = substr( $token, 0, 8 );
		}
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- gated by WP_DEBUG.
		error_log(
			sprintf(
				'LLAR MFA send-code Forbidden: %s (token_prefix=%s)',
				is_string( $reason ) ? $reason : 'unknown',
				$prefix
			)
		);
	}

	public static function execute( $token, $secret, $code, $context = array() ) {
		$store = new SessionStore();

		$stored_secret = $store->get_send_email_secret( $token );
		if ( null === $stored_secret ) {
			self::debug_forbidden( 'no_send_secret', $token );
			return array(
				'success'     => false,
				'http_status' => 403,
				'message'     => 'Forbidden',
			);
		}
		if ( ! hash_equals( (string) $stored_secret, (string) $secret ) ) {
			self::debug_forbidden( 'secret_mismatch', $token );
			return array(
				'success'     => false,
				'http_status' => 403,
				'message'     => 'Forbidden',
			);
		}

		$session = $store->get_session( $token );
		if ( ! $session ) {
			self::debug_forbidden( 'no_session', $token );
			return array(
				'success'     => false,
				'http_status' => 403,
				'message'     => 'Forbidden',
			);
		}

		$user_id = ! empty( $session['user_id'] ) ? (int) $session['user_id'] : 0;
		$user    = $user_id ? get_user_by( 'id', $user_id ) : get_user_by( 'login', isset( $session['username'] ) ? $session['username'] : '' );
		if ( ! $user || ! is_a( $user, 'WP_User' ) ) {
			return array(
				'success'     => true,
				'http_status' => 200,
				'message'     => null,
			);
		}

		$provider_id = isset( $session['provider_id'] ) ? $session['provider_id'] : 'llar';
		$provider    = MfaProviderRegistry::get( $provider_id );
		if ( ! $provider ) {
			return array(
				'success'     => false,
				'http_status' => 500,
				'message'     => 'Provider not available',
			);
		}

		$context = is_array( $context ) ? $context : array();
		$result  = $provider->send_code( $user, $code, $context );
		if ( ! empty( $result['success'] ) ) {
			$store->save_otp( $token, $code );
			return array(
				'success'     => true,
				'http_status' => 200,
				'message'     => null,
			);
		}

		$message = isset( $result['message'] ) && is_string( $result['message'] ) ? $result['message'] : 'Failed to send code';
		return array(
			'success'     => false,
			'http_status' => 500,
			'message'     => $message,
		);
	}
}
