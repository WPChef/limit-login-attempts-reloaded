<?php

namespace LLAR\Core\MfaFlow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API for MFA flow: send-code endpoint (fallback is AJAX via admin-ajax.php).
 */
class MfaRestApi {

	const REST_NAMESPACE  = 'llar/v1';
	const SEND_CODE_ROUTE = 'mfa/send-code';

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
				array(
					'success' => false,
					'message' => 'Forbidden',
				),
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
	 * Build REST URL for send-code (POST). Used in handshake as primary send_email_url.
	 * No query args; the MFA app must POST token, secret, and code in the request body.
	 *
	 * @return string
	 */
	public static function get_send_code_rest_url() {
		return rest_url( self::REST_NAMESPACE . '/' . self::SEND_CODE_ROUTE );
	}
}
