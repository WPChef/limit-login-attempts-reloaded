<?php

namespace LLAR\Core\MfaFlow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API for MFA flow: send-code endpoint (fallback is AJAX via admin-ajax.php).
 */
class MfaRestApi {

	const REST_NAMESPACE = 'llar/v1';
	const SEND_CODE_ROUTE = 'mfa/send-code';
	const TEST_SESSION_ROUTE = 'mfa/test-handshake-session';

	/**
	 * Register REST routes on rest_api_init.
	 */
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 */
	public static function register_routes() {
		// Optional: create test session (token=test-token, secret=test-secret) when LLA_MFA_FLOW_TEST_REDIRECT is set.
		if ( defined( 'LLA_MFA_FLOW_TEST_REDIRECT' ) && LLA_MFA_FLOW_TEST_REDIRECT ) {
			register_rest_route(
				self::REST_NAMESPACE,
				self::TEST_SESSION_ROUTE,
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'test_handshake_session_callback' ),
					'permission_callback' => '__return_true',
					'args'                => array(),
				)
			);
		}

		register_rest_route(
			self::REST_NAMESPACE,
			self::SEND_CODE_ROUTE,
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'send_code_callback' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'token'  => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'secret' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'code'   => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * REST callback: MFA send-code. POST only (token, secret, code in request body).
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public static function send_code_callback( $request ) {
		$token  = $request->get_param( 'token' );
		$secret = $request->get_param( 'secret' );
		$code   = $request->get_param( 'code' );
		$code   = is_string( $code ) ? $code : '';

		if ( '' === $token || '' === $secret ) {
			if ( defined( 'WP_DEBUG' ) && \WP_DEBUG ) {
				error_log( LLA_MFA_FLOW_LOG_PREFIX . 'send_code invalid_request' );
			}
			return new \WP_REST_Response(
				array( 'success' => false, 'message' => 'Forbidden' ),
				403
			);
		}

		$result = MfaFlowSendCode::execute( $token, $secret, $code );

		$status = isset( $result['http_status'] ) ? (int) $result['http_status'] : 200;
		$body   = array(
			'success' => (bool) $result['success'],
		);
		if ( ! empty( $result['message'] ) ) {
			$body['message'] = $result['message'];
		}

		return new \WP_REST_Response( $body, $status );
	}

	/**
	 * Create a test session (simulates handshake) so send_code can be called with token=test-token, secret=test-secret.
	 * Only registered when LLA_MFA_FLOW_TEST_REDIRECT is defined. Use first admin user for email.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public static function test_handshake_session_callback( $request ) {
		$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
		$user   = ! empty( $admins[0] ) ? $admins[0] : null;
		if ( ! $user || ! is_a( $user, 'WP_User' ) ) {
			return new \WP_REST_Response( array( 'success' => false, 'message' => 'No admin user' ), 500 );
		}
		$store = new SessionStore();
		$store->save_send_email_secret( 'test-token', 'test-secret' );
		$store->save_session( 'test-token', 'test-secret', $user->user_login, (int) $user->ID, '', '', 'llar', true );
		return new \WP_REST_Response( array( 'success' => true, 'message' => 'Session created. POST send-code with body: token=test-token&secret=test-secret&code=123456' ), 200 );
	}

	/**
	 * Build REST URL for send-code (POST). Used in handshake as primary send_email_url.
	 * No query args; the MFA app must POST token, secret, and code in the request body.
	 *
	 * @return string
	 */
	public static function get_send_code_rest_url() {
		return rest_url( self::REST_NAMESPACE . '/' . self::SEND_CODE_ROUTE );
	}
}
