<?php
/**
 * WP_DEBUG-only diagnostics for the MFA flow.
 *
 * phpcs:disable WordPress.Files.FileName -- PSR-4 class name matches the file.
 */

namespace LLAR\Core\MfaFlow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Never logs secrets: a session token is reduced to an 8-character prefix.
 */
class MfaFlowDebug {

	/**
	 * Log which send-code branch answered 403.
	 *
	 * @param string $reason Branch id: missing_token, missing_secret, missing_token_and_secret,
	 *                       no_send_secret, secret_mismatch, no_session.
	 * @param string $token  Session token (optional; only prefix is logged).
	 * @return void
	 */
	public static function forbidden( $reason, $token = '' ) {
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
}
