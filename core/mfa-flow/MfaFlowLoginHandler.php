<?php

namespace LLAR\Core\MfaFlow;

use LLAR\Core\Config;
use LLAR\Core\IpAddressResolver;
use LLAR\Core\LimitLoginAttempts;
use LLAR\Core\MfaConstants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MFA login handshake redirect after password verification (wp-login flow).
 */
class MfaFlowLoginHandler {

	/**
	 * @var bool
	 */
	private static $mfa_flow_handshake_attempted = false;

	/**
	 * @var IpAddressResolver
	 */
	private $ip_resolver;

	/**
	 * @param IpAddressResolver $ip_resolver Client IP resolver.
	 */
	public function __construct( IpAddressResolver $ip_resolver ) {
		$this->ip_resolver = $ip_resolver;
	}

	/**
	 * Reset per-request MFA handshake guard.
	 *
	 * @return void
	 */
	public static function reset_handshake_guard() {
		self::$mfa_flow_handshake_attempted = false;
	}

	/**
	 * Redirect browser to MFA app URL.
	 *
	 * @param string $url Redirect URL.
	 * @return void
	 */
	public static function redirect_to_url( $url ) {
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		if ( ! headers_sent() ) {
			header( 'Location: ' . $url, true, 302 );
			exit;
		}
		$url_attr = esc_attr( $url );
		$url_js   = esc_js( $url );
		echo '<!DOCTYPE html><html><head><meta charset="utf-8"><meta http-equiv="refresh" content="0;url=' . $url_attr . '"><title>Redirect</title></head><body><p><a href="' . $url_attr . '">Continue to verification</a></p><script>window.location.replace("' . $url_js . '");</script></body></html>';
		exit;
	}

	/**
	 * @param string       $username             Login username.
	 * @param bool         $is_pre_authenticated Password verified.
	 * @param \WP_User|null $authenticated_user  Authenticated user when known.
	 * @return void
	 */
	public function try_mfa_flow_redirect( $username, $is_pre_authenticated = false, $authenticated_user = null ) {
		if ( ! $is_pre_authenticated ) {
			return;
		}
		$ip = $this->ip_resolver->get_address();

		$mfa_temporarily_disabled = false !== get_transient( MfaConstants::TRANSIENT_MFA_DISABLED );
		$mfa_enabled              = (bool) Config::get( 'mfa_enabled' ) && ! $mfa_temporarily_disabled;
		$user                     = null;
		if ( is_a( $authenticated_user, 'WP_User' ) ) {
			$user = $authenticated_user;
		} elseif ( is_string( $username ) && '' !== $username ) {
			$user = get_user_by( 'login', $username );
			if ( ! $user && function_exists( 'is_email' ) && is_email( $username ) ) {
				$user = get_user_by( 'email', $username );
			}
		}
		$mfa_roles          = Config::get( 'mfa_roles', array() );
		$mfa_roles          = is_array( $mfa_roles ) ? $mfa_roles : array();
		$has_mfa_groups     = ! empty( $mfa_roles );
		$user_excluded      = $user && $has_mfa_groups && ! array_intersect( (array) $user->roles, $mfa_roles );
		$should_trigger_mfa = $mfa_enabled && $has_mfa_groups && ! $user_excluded;

		if ( ! $should_trigger_mfa ) {
			return;
		}

		if ( self::$mfa_flow_handshake_attempted ) {
			return;
		}

		$provider_id = defined( 'LLA_MFA_PROVIDER' ) ? LLA_MFA_PROVIDER : 'llar';
		$provider    = MfaProviderRegistry::get( $provider_id );
		if ( ! $provider ) {
			return;
		}

		$rate_key = 'llar_mfa_flow_handshake_' . md5( $ip . ( defined( 'AUTH_SALT' ) ? AUTH_SALT : 'llar' ) );
		$rate     = get_transient( $rate_key );
		$period   = defined( 'LLA_MFA_FLOW_HANDSHAKE_RATE_LIMIT_PERIOD' ) ? (int) LLA_MFA_FLOW_HANDSHAKE_RATE_LIMIT_PERIOD : 60;
		$max      = defined( 'LLA_MFA_FLOW_HANDSHAKE_RATE_LIMIT_MAX' ) ? (int) LLA_MFA_FLOW_HANDSHAKE_RATE_LIMIT_MAX : 5;

		if ( is_array( $rate ) && isset( $rate['t'], $rate['c'] ) ) {
			if ( time() - (int) $rate['t'] >= $period ) {
				$rate = array( 'c' => 0, 't' => time() );
			}
		} else {
			$rate = array( 'c' => 0, 't' => time() );
		}

		$rate_ok = ( (int) $rate['c'] < $max );
		if ( ! $rate_ok ) {
			$rate['c'] = (int) $rate['c'] + 1;
			set_transient( $rate_key, $rate, $period );
			return;
		}

		self::$mfa_flow_handshake_attempted = true;
		$user_group = '';
		if ( $user && ! empty( $user->roles ) && is_array( $user->roles ) ) {
			$user_group = reset( $user->roles );
		}
		$redirect_to         = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : '';
		$cancel_url          = add_query_arg( 'llar_mfa_cancelled', '1', wp_login_url() );
		$current_request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$current_login_url   = '';
		if ( is_string( $current_request_uri ) && '' !== $current_request_uri ) {
			$current_login_url = home_url( $current_request_uri );
		}
		$login_url = ( '' !== $current_login_url ) ? $current_login_url : wp_login_url();
		$login_url = add_query_arg( 'llar_mfa', '1', $login_url );
		if ( '' !== $redirect_to ) {
			$login_url = add_query_arg( 'redirect_to', $redirect_to, $login_url );
		}
		$payload = array(
			'user_ip'              => \LLAR\Core\Helpers::get_all_ips(),
			'login_url'            => $login_url,
			'user_group'           => $user_group,
			'is_pre_authenticated' => (bool) $is_pre_authenticated,
		);
		if ( $user ) {
			$payload['user_id'] = (int) $user->ID;
			if ( ! empty( $user->user_email ) && is_string( $user->user_email ) ) {
				$payload['user_email'] = \LLAR\Core\Helpers::obfuscate_email( $user->user_email );
			}
		}

		$result             = $provider->handshake( $payload );
		$has_token          = ! empty( $result['data']['token'] );
		$has_secret         = ! empty( $result['data']['secret'] );
		$redirect_url_value = isset( $result['data']['redirect_url'] ) ? $result['data']['redirect_url'] : ( isset( $result['data']['redirectUrl'] ) ? $result['data']['redirectUrl'] : '' );
		$has_redirect       = ! empty( $redirect_url_value );

		if ( $result['success'] && $has_token && $has_secret && $has_redirect ) {
			$store = new SessionStore();
			$store->save_send_email_secret( $result['data']['token'], $result['data']['secret'] );
			$state            = wp_generate_password( 32, false, false );
			$remember_me      = ! empty( $_REQUEST['rememberme'] );
			$session_username = ( $user && ! empty( $user->user_login ) ) ? $user->user_login : $username;
			$store->save_session(
				$result['data']['token'],
				$result['data']['secret'],
				$session_username,
				$user ? (int) $user->ID : 0,
				$redirect_to,
				$cancel_url,
				$provider_id,
				$is_pre_authenticated,
				$remember_me
			);
			$store->save_callback_state( $state, $result['data']['token'] );
			SessionStore::set_state_cookie( $state );
			$mfa_redirect_url = esc_url_raw( $redirect_url_value );
			if ( $mfa_redirect_url ) {
				self::redirect_to_url( $mfa_redirect_url );
			}
		}

		if ( ! $result['success'] && ! empty( $result['server_unreachable'] ) ) {
			set_transient( MfaConstants::TRANSIENT_MFA_DISABLED, 'api_unreachable', 60 );
			return;
		}

		$rate['c'] = (int) $rate['c'] + 1;
		set_transient( $rate_key, $rate, $period );
	}
}
