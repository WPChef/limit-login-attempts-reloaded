<?php

namespace LLAR\Core\MfaFlow;

use LLAR\Core\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MFA provider: authorization by email (OTP sent to user email, user enters code on site).
 * No external API; session and OTP stored locally, verify is local.
 */
class EmailMfaProvider implements MfaProviderInterface {

	const PROVIDER_ID = 'email';

	const OTP_LENGTH = 6;

	/**
	 * @return string
	 */
	public function get_id() {
		return self::PROVIDER_ID;
	}

	/**
	 * @return string
	 */
	public function get_label() {
		return __( 'Email (OTP to user email)', 'limit-login-attempts-reloaded' );
	}

	/**
	 * Generate token/secret, save session, send OTP by email, return redirect URL to enter-code page.
	 * Payload may contain: username, user_id, redirect_to, cancel_url (from caller).
	 *
	 * @param array $payload Request payload.
	 * @return array { success: bool, data: array|null (token, secret, redirect_url), error: string|null }
	 */
	public function handshake( array $payload ) {
		$username    = isset( $payload['username'] ) ? (string) $payload['username'] : '';
		$user_id    = isset( $payload['user_id'] ) ? (int) $payload['user_id'] : 0;
		$redirect_to = isset( $payload['redirect_to'] ) ? (string) $payload['redirect_to'] : '';
		$cancel_url  = isset( $payload['cancel_url'] ) ? (string) $payload['cancel_url'] : '';

		if ( $username === '' ) {
			return array( 'success' => false, 'data' => null, 'error' => 'Username required for email provider' );
		}

		$user = $user_id ? get_user_by( 'id', $user_id ) : get_user_by( 'login', $username );
		if ( ! $user || ! is_a( $user, 'WP_User' ) || empty( $user->user_email ) ) {
			return array( 'success' => false, 'data' => null, 'error' => 'User or email not found' );
		}

		$token  = $this->generate_token();
		$secret = $this->generate_token();
		$code   = $this->generate_otp_code();

		$store = new SessionStore();
		$store->save_session( $token, $secret, $username, $user->ID, $redirect_to, $cancel_url, self::PROVIDER_ID );
		$store->save_otp( $token, $code );

		$subject = __( 'Your verification code', 'limit-login-attempts-reloaded' );
		$body    = sprintf( __( 'Your verification code is: %s', 'limit-login-attempts-reloaded' ), $code );
		$sent    = wp_mail( $user->user_email, $subject, $body );

		if ( ! $sent ) {
			$store->delete_session( $token );
			MfaFlowLogger::log( 'email_provider', 'send_failed', array() );
			return array( 'success' => false, 'data' => null, 'error' => 'Failed to send email' );
		}

		MfaFlowLogger::increment_usage( 'send_code' );
		MfaFlowLogger::log( 'email_provider', 'handshake_sent', array() );

		$redirect_url = add_query_arg( array(
			'llar_mfa' => '1',
			'token'    => $token,
		), wp_login_url() );

		return array(
			'success' => true,
			'data'    => array(
				'token'        => $token,
				'secret'       => $secret,
				'redirect_url' => $redirect_url,
				'session_saved' => true,
			),
			'error'   => null,
		);
	}

	/**
	 * Local verify: no external API; OTP already checked in CallbackHandler. Return success.
	 *
	 * @param string $token  Session token.
	 * @param string $secret Session secret.
	 * @return array { success: bool, data: array (is_verified), error: string|null }
	 */
	public function verify( $token, $secret ) {
		$store   = new SessionStore();
		$session = $store->get_session( $token );
		if ( ! $session || ! isset( $session['secret'] ) || $session['secret'] !== $secret ) {
			return array( 'success' => false, 'data' => null, 'error' => 'Session invalid' );
		}
		return array(
			'success' => true,
			'data'    => array( 'is_verified' => true ),
			'error'   => null,
		);
	}

	/**
	 * No config fields for email provider (uses wp_mail).
	 *
	 * @return array
	 */
	public function get_config_fields() {
		return array();
	}

	/**
	 * @return string
	 */
	private function generate_token() {
		return bin2hex( random_bytes( 16 ) );
	}

	/**
	 * @return string
	 */
	private function generate_otp_code() {
		$digits = self::OTP_LENGTH;
		$max    = (int) str_pad( '9', $digits, '9' );
		$num    = wp_rand( 0, $max );
		return str_pad( (string) $num, $digits, '0', STR_PAD_LEFT );
	}
}
