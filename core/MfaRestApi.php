<?php

namespace LLAR\Core;

use LLAR\Core\MfaFlow\MfaFlowSendCode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API for MFA flow: send-code endpoint (fallback is AJAX via admin-ajax.php).
 */
class MfaRestApi {

	const REST_NAMESPACE = 'llar/v1';
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
				'methods'             => array( 'GET', 'POST' ),
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
	 * REST callback: MFA send-code. GET uses query params; POST uses body (JSON or form).
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

		$is_get = $request->get_method() === 'GET';
		$result = MfaFlowSendCode::execute( $token, $secret, $code, $is_get );

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
	 * Build REST URL for send-code (GET). Used in handshake as primary send_email_url.
	 * The MFA app will append token and code when calling this URL.
	 *
	 * @param string $send_email_secret Secret for send_email_url (query arg).
	 * @return string
	 */
	public static function get_send_code_rest_url( $send_email_secret ) {
		$rest_url = rest_url( self::REST_NAMESPACE . '/' . self::SEND_CODE_ROUTE );
		return add_query_arg( 'secret', $send_email_secret, $rest_url );
	}
}
