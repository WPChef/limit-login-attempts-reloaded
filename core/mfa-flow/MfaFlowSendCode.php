<?php

namespace LLAR\Core\MfaFlow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MFA flow: shared logic for sending verification code to user email.
 * Used by both AJAX (admin-ajax.php) and REST API endpoints.
 * Only GET is supported: token, secret (send_email_url secret), code in query.
 * After first successful send, send_email_secret is invalidated (one-time use).
 *
 * @return array { 'success' => bool, 'http_status' => int, 'message' => string|null }
 */
class MfaFlowSendCode {

	/**
	 * Execute send-code: validate send_email_secret, send email, save OTP, invalidate secret.
	 *
	 * @param string $token  Session token.
	 * @param string $secret Send_email_url secret (from query).
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
				'success'      => false,
				'http_status'  => 403,
				'message'      => 'Forbidden',
			);
		}

		$session = $store->get_session( $token );
		if ( ! $session ) {
			if ( defined( 'WP_DEBUG' ) && \WP_DEBUG ) {
				error_log( LLA_MFA_FLOW_LOG_PREFIX . 'send_code session_not_found' );
			}
			return array(
				'success'      => false,
				'http_status'  => 403,
				'message'      => 'Forbidden',
			);
		}

		$user_id = ! empty( $session['user_id'] ) ? (int) $session['user_id'] : 0;
		$user    = $user_id ? get_user_by( 'id', $user_id ) : get_user_by( 'login', isset( $session['username'] ) ? $session['username'] : '' );

		if ( ! $user || ! is_a( $user, 'WP_User' ) || empty( $user->user_email ) ) {
			if ( defined( 'WP_DEBUG' ) && \WP_DEBUG ) {
				error_log( LLA_MFA_FLOW_LOG_PREFIX . 'send_code user_not_found_no_enum' );
			}
			return array(
				'success'      => true,
				'http_status'  => 200,
				'message'      => null,
			);
		}

		$subject = __( 'Your verification code', 'limit-login-attempts-reloaded' );
		$body    = sprintf( __( 'Your verification code is: %s', 'limit-login-attempts-reloaded' ), $code );

		$sent = wp_mail( $user->user_email, $subject, $body );

		if ( $sent ) {
			if ( defined( 'WP_DEBUG' ) && \WP_DEBUG ) {
				error_log( LLA_MFA_FLOW_LOG_PREFIX . 'send_code success' );
			}
			$store->save_otp( $token, $code );
			$store->delete_send_email_secret( $token );
			return array(
				'success'      => true,
				'http_status'  => 200,
				'message'      => null,
			);
		}

		if ( defined( 'WP_DEBUG' ) && \WP_DEBUG ) {
			error_log( LLA_MFA_FLOW_LOG_PREFIX . 'send_code email_failed' );
		}
		return array(
			'success'      => false,
			'http_status'  => 500,
			'message'      => 'Failed to send email',
		);
	}
}
