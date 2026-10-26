<?php
/**
 * Active Lockouts Presenter
 *
 * Decides empty vs rows for the Login Firewall Active Lockouts widget.
 * Templates stay markup-only.
 *
 * @package LimitLoginAttempts
 */

namespace LLAR\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * View-model builder for app_load_lockouts HTML.
 */
class ActiveLockoutsPresenter {

	/**
	 * Build view variables for the lockouts table body fragment.
	 *
	 * Empty mode only when the API returned no items and no continuation
	 * offset — a partial empty page with a cursor must not emit empty-row HTML
	 * (fill-up would otherwise leave "No lockouts yet." next to data rows).
	 *
	 * @param array  $lockouts {
	 *     Cloud API lockout list payload.
	 *
	 *     @type array       $items  Lockout items.
	 *     @type string|null $offset Continuation cursor (empty when exhausted).
	 * }
	 * @param string $request_offset POST offset of the current request (unused in v1; reserved).
	 * @return array{mode: string, rows: array<int, array{ip: string, login: string, count: string, expires_minutes: string}>}
	 */
	public static function get_view_vars( array $lockouts, $request_offset = '' ) {
		unset( $request_offset );

		$items           = isset( $lockouts['items'] ) && is_array( $lockouts['items'] ) ? $lockouts['items'] : array();
		$response_offset = isset( $lockouts['offset'] ) ? (string) $lockouts['offset'] : '';

		if ( empty( $items ) && '' === $response_offset ) {
			return array(
				'mode' => 'empty',
				'rows' => array(),
			);
		}

		$rows = array();
		foreach ( $items as $item ) {
			$login = '-';
			if ( isset( $item['login'] ) && ! is_null( $item['login'] ) ) {
				$login = is_array( $item['login'] )
					? implode( ',', $item['login'] )
					: (string) $item['login'];
			}

			$count = '-';
			if ( isset( $item['count'] ) && ! is_null( $item['count'] ) ) {
				$count = (string) $item['count'];
			}

			$expires = '-';
			if ( isset( $item['ttl'] ) && ! is_null( $item['ttl'] ) ) {
				$expires = (string) round( ( (int) $item['ttl'] - time() ) / 60 );
			}

			$rows[] = array(
				'ip'              => isset( $item['ip'] ) ? (string) $item['ip'] : '',
				'login'           => $login,
				'count'           => $count,
				'expires_minutes' => $expires,
			);
		}

		return array(
			'mode' => 'rows',
			'rows' => $rows,
		);
	}
}
