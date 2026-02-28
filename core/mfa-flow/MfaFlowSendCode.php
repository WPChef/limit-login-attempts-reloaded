<?php

namespace LLAR\Core\MfaFlow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MFA flow: shared logic for sending verification code via the session's provider.
 * Used by both AJAX (admin-ajax.php) and REST API endpoints.
 * Endpoints accept POST with token, secret (send_email_secret), code in request body.
 * After first successful send, send_email_secret is invalidated (one-time use).
 * Actual delivery (email, SMS, etc.) is delegated to the provider registered for the session.
 *
 * @return array { 'success' => bool, 'http_status' => int, 'message' => string|null }
 */
class MfaFlowSendCode {

	/**
	 * Execute send-code: validate secret, resolve provider from session, send via provider, save OTP, invalidate secret.
	 *
	 * @param string $token  Session token.
	 * @param string $secret Send_code secret (from request body).
	 * @param string $code   Verification code to send and store.
	 * @return array { 'success' => bool, 'http_status' => int, 'message' => string|null }
	 */
	public static function execute( $token, $secret, $code ) {
		$store = new SessionStore();

		$stored_secret = $store->get_send_email_secret( $token );
		if ( null === $stored_secret || ! hash_equals( (string) $stored_secret, (string) $secret ) ) {
			if ( defined( 'WP_DEBUG' ) && \WP_DEBUG ) {
				error_log( LLA_MFA_FLOW_LOG_PREFIX . 'send_code invalid_secret' );
			}
			return array(
				'success'     => false,
				'http_status' => 403,
				'message'     => 'Forbidden',
			);
		}

		$session = $store->get_session( $token );
		if ( ! $session ) {
			if ( defined( 'WP_DEBUG' ) && \WP_DEBUG ) {
				error_log( LLA_MFA_FLOW_LOG_PREFIX . 'send_code session_not_found' );
			}
			return array(
				'success'     => false,
				'http_status' => 403,
				'message'     => 'Forbidden',
			);
		}

		$user_id = ! empty( $session['user_id'] ) ? (int) $session['user_id'] : 0;
		$user    = $user_id ? get_user_by( 'id', $user_id ) : get_user_by( 'login', isset( $session['username'] ) ? $session['username'] : '' );
		if ( ! $user || ! is_a( $user, 'WP_User' ) ) {
			if ( defined( 'WP_DEBUG' ) && \WP_DEBUG ) {
				error_log( LLA_MFA_FLOW_LOG_PREFIX . 'send_code user_not_found_no_enum' );
			}
			return array(
				'success'     => true,
				'http_status' => 200,
				'message'     => null,
			);
		}

		$provider_id = isset( $session['provider_id'] ) ? $session['provider_id'] : 'llar';
		$provider    = MfaProviderRegistry::get( $provider_id );
		if ( ! $provider ) {
			if ( defined( 'WP_DEBUG' ) && \WP_DEBUG ) {
				error_log( LLA_MFA_FLOW_LOG_PREFIX . 'send_code provider_not_found provider_id=' . $provider_id );
			}
			return array(
				'success'     => false,
				'http_status' => 500,
				'message'     => 'Provider not available',
			);
		}

		$result = $provider->send_code( $user, $code );
		if ( ! empty( $result['success'] ) ) {
			if ( defined( 'WP_DEBUG' ) && \WP_DEBUG ) {
				error_log( LLA_MFA_FLOW_LOG_PREFIX . 'send_code success' );
			}
			$store->save_otp( $token, $code );
			$store->delete_send_email_secret( $token );
			return array(
				'success'     => true,
				'http_status' => 200,
				'message'     => null,
			);
		}

		if ( defined( 'WP_DEBUG' ) && \WP_DEBUG ) {
			error_log( LLA_MFA_FLOW_LOG_PREFIX . 'send_code delivery_failed' );
		}
		$message = isset( $result['message'] ) && is_string( $result['message'] ) ? $result['message'] : 'Failed to send code';
		return array(
			'success'     => false,
			'http_status' => 500,
			'message'     => $message,
		);
	}
}
