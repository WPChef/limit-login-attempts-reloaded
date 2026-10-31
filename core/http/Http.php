<?php

namespace LLAR\Core\Http;

class Http {

	/**
	 * @var HttpTransportInterface
	 */
	private static $transport;

	/**
	 * @throws \Exception
	 */
	public static function init() {

		if( function_exists( 'fopen' ) && ini_get( 'allow_url_fopen' ) === '1' ) {
			self::$transport = new HttpTransportFopen();
		} else if( function_exists( 'wp_remote_get' ) ) {
			self::$transport = new HttpTransportWp();
		} else if( function_exists( 'curl_version' ) ) {
			self::$transport = new HttpTransportCurl();
		} else {
			throw new \Exception( 'Unable to determine HTTP transport.' );
		}

	}

	/**
	 * @param $url
	 * @param array $options
	 *
	 * @return mixed
	 */
	public static function get( $url, $options = array() ) {

		if ( ! self::is_url_allowed( $url ) ) {
			return self::blocked_url_response();
		}

		return self::$transport->get( $url, $options );
	}

	/**
	 * @param $url
	 * @param array $options
	 *
	 * @return mixed
	 */
	public static function post( $url, $options = array() ) {

		if ( ! self::is_url_allowed( $url ) ) {
			return self::blocked_url_response();
		}

		$options['headers'] = array_merge(
			array( 'Content-Type: application/json; charset=utf-8' ),
			!empty( $options['headers'] ) ? $options['headers'] : array()
		);

		return self::$transport->post( $url, $options );
	}

	/**
	 * Outbound URL policy (SSRF guard) — the single source of truth.
	 *
	 * Only public http(s) endpoints are fetchable: wp_http_validate_url()
	 * rejects loopback and private/reserved IP ranges (after DNS
	 * resolution), non-http(s) schemes and non-standard ports — the same
	 * policy wp_safe_remote_get() enforces. Every transport funnels
	 * through this facade, and redirect hops are re-checked against this
	 * method as well.
	 *
	 * Self-hosted/internal endpoints can be opted back in with the core
	 * filters wp_http_validate_url() applies — `http_request_host_is_external`
	 * (private hosts) and `http_allowed_safe_ports` (ports) — the same knobs
	 * wp_safe_remote_* respects.
	 *
	 * Fail-closed: anything that cannot be validated is not fetchable.
	 *
	 * @param string $url
	 *
	 * @return bool
	 */
	public static function is_url_allowed( $url ) {

		if ( ! is_string( $url ) || '' === $url ) {
			return false;
		}

		if ( function_exists( 'wp_http_validate_url' ) ) {
			return false !== wp_http_validate_url( $url );
		}

		return false;
	}

	/**
	 * Transport-shaped response for a destination the site must not contact.
	 * Carries a `blocked` flag so callers can tell a policy rejection from
	 * a transport failure. The error string deliberately repeats no URL:
	 * it may flow into error_log, and the URL is attacker-influenced input.
	 *
	 * @return array
	 */
	private static function blocked_url_response() {

		return array(
			'data'    => null,
			'status'  => 0,
			'error'   => 'Destination rejected by the outbound URL policy (loopback, private networks and non-standard ports are not fetchable).',
			'blocked' => true,
		);
	}
}
