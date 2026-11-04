<?php

namespace LLAR\Core\MfaFlow;

use LLAR\Core\CloudAclService;
use LLAR\Core\Config;
use LLAR\Core\IpAddressResolver;
use LLAR\Core\LimitLoginAttempts;
use LLAR\Core\LocalLockoutManager;
use LLAR\Core\MfaConstants;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MFA login handshake redirect after password verification (wp-login flow).
 *
 * Contract: when MFA is required for the authenticated user, the login may
 * proceed ONLY after the verification step positively succeeds (redirect to
 * the MFA app), except for one deliberate trade-off: while the MFA service
 * itself is unavailable (transport error, 429/5xx, unusable 2xx body) 2FA is
 * disabled for a short window instead of locking every protected account out
 * of the site. Refusals: non-browser auth route (XML-RPC), rate limit,
 * provider missing, a payload that cannot be serialised (attacker-influenced)
 * and a request rejected by the service (4xx) return a WP_Error.
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
	 * @var LocalLockoutManager
	 */
	private $local_lockout;

	/**
	 * @var CloudAclService
	 */
	private $cloud_acl;

	/**
	 * @param IpAddressResolver   $ip_resolver   Client IP resolver.
	 * @param LocalLockoutManager $local_lockout Local allowlist/denylist and lockout state.
	 * @param CloudAclService     $cloud_acl     Cloud ACL service.
	 */
	public function __construct( IpAddressResolver $ip_resolver, LocalLockoutManager $local_lockout, CloudAclService $cloud_acl ) {
		$this->ip_resolver   = $ip_resolver;
		$this->local_lockout = $local_lockout;
		$this->cloud_acl     = $cloud_acl;
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
	 * Error for a login that must be refused because the second factor could
	 * not be positively completed.
	 *
	 * @param string $code WP_Error code.
	 * @return WP_Error
	 */
	/**
	 * Neutral user-facing copy for a refused second factor. It only ever
	 * reaches a user whose password was already verified, so it must not be
	 * rewritten into a password error by the login error presenter.
	 *
	 * @return string
	 */
	public static function refusal_message() {
		return __( '<strong>ERROR</strong>: Two-factor verification is required for this account and could not be completed. Please try again.', 'limit-login-attempts-reloaded' );
	}

	private function mfa_refusal_error( $code ) {
		return new WP_Error( $code, self::refusal_message() );
	}

	/**
	 * @param string       $username             Login username.
	 * @param bool         $is_pre_authenticated Password verified.
	 * @param \WP_User|null $authenticated_user  Authenticated user when known.
	 * @return WP_Error|null WP_Error when the login must be refused (MFA required
	 *                       but the verification step did not positively succeed,
	 *                       or a protected account authenticating over a
	 *                       non-interactive route such as XML-RPC — also during
	 *                       a provider outage); null when MFA is not required,
	 *                       or when it was temporarily disabled (rescue, or the
	 *                       service being down on interactive routes).
	 */
	public function try_mfa_flow_redirect( $username, $is_pre_authenticated = false, $authenticated_user = null ) {
		// CRITICAL: never fetch or disclose any user info unless password was verified.
		if ( ! $is_pre_authenticated ) {
			return null;
		}
		$ip = $this->ip_resolver->get_address();

		if ( $this->ip_resolver->is_ip_whitelisted( $ip ) || $this->local_lockout->is_local_allowlisted_username( $username, $authenticated_user ) ) {
			return null;
		}

		if ( LimitLoginAttempts::$cloud_app ) {
			$acl_response = $this->cloud_acl->get_auth_acl_response( $username );
			if ( is_array( $acl_response ) && ! empty( $acl_response['result'] ) && 'pass' === $acl_response['result'] ) {
				return null;
			}
		}

		$disable_transient        = get_transient( MfaConstants::TRANSIENT_MFA_DISABLED );
		$mfa_outage_window        = is_string( $disable_transient ) && MfaConstants::TRANSIENT_VALUE_API_UNREACHABLE === $disable_transient;
		$mfa_temporarily_disabled = false !== $disable_transient;
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
		$mfa_roles              = Config::get( 'mfa_roles', array() );
		$mfa_roles              = is_array( $mfa_roles ) ? $mfa_roles : array();
		$has_mfa_groups         = ! empty( $mfa_roles );
		$user_excluded          = $user && $has_mfa_groups && ! array_intersect( (array) $user->roles, $mfa_roles );
		$mfa_required_by_config = ( (bool) Config::get( 'mfa_enabled' ) ) && $has_mfa_groups && ! $user_excluded;
		$should_trigger_mfa     = $mfa_enabled && $has_mfa_groups && ! $user_excluded;

		// The verification flow is interactive (redirect to the MFA app): it can
		// never complete on a non-browser authentication route such as XML-RPC.
		// A protected account must be refused there, not granted a session. The
		// refusal must also hold during a provider outage: the 60s availability
		// window applies to interactive routes only and must not reopen
		// password-only XML-RPC sessions. A deliberate rescue disable stores a
		// different transient value, so it still lifts the refusal.
		if ( $mfa_required_by_config && ( ! $mfa_temporarily_disabled || $mfa_outage_window )
			&& defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return new WP_Error(
				'llar_mfa_xmlrpc_required',
				__( '<strong>ERROR</strong>: Two-factor verification is required for this account and cannot be completed over XML-RPC.', 'limit-login-attempts-reloaded' )
			);
		}

		if ( ! $should_trigger_mfa ) {
			return null;
		}

		if ( self::$mfa_flow_handshake_attempted ) {
			return $this->mfa_refusal_error( 'llar_mfa_unavailable' );
		}

		$provider_id = defined( 'LLA_MFA_PROVIDER' ) ? LLA_MFA_PROVIDER : 'llar';
		$provider    = MfaProviderRegistry::get( $provider_id );
		if ( ! $provider ) {
			return $this->mfa_refusal_error( 'llar_mfa_unavailable' );
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
			return $this->mfa_refusal_error( 'llar_mfa_rate_limited' );
		}

		self::$mfa_flow_handshake_attempted = true;
		$user_group = '';
		if ( $user && ! empty( $user->roles ) && is_array( $user->roles ) ) {
			$user_group = reset( $user->roles );
		}
		$redirect_to         = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : '';
		$cancel_url          = add_query_arg( 'llar_mfa_cancelled', '1', wp_login_url() );
		$current_request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
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

		$rate['c'] = (int) $rate['c'] + 1;
		set_transient( $rate_key, $rate, $period );

		// A payload that cannot be serialised is reachable through request-derived
		// fields, so this path must never fall back to a password-only session:
		// it is the #342 bypass. A missing or non-absolute API base URL is a
		// misconfiguration: refusing (and showing the settings notice) is safer
		// than silently disabling 2FA through repeated availability windows.
		$error_code = isset( $result['error_code'] ) ? (string) $result['error_code'] : '';
		if ( \LLAR\Core\Http\Http::ERROR_PAYLOAD_ENCODING === $error_code
			|| MfaApiClient::ERROR_NOT_CONFIGURED === $error_code ) {
			return $this->mfa_refusal_error( 'llar_mfa_unavailable' );
		}

		$status = isset( $result['status'] ) ? (int) $result['status'] : 0;

		// Service unavailable: transport error (0), rate limited by the provider
		// (429) or provider error (5xx). Disabling 2FA for a short window is the
		// pre-existing availability trade-off — an MFA provider outage must not
		// lock protected accounts out of the site; XML-RPC stays refused inside
		// the window (see the guard above), and sites that need strict 2FA can
		// opt into fail-closed below. A 200 that reaches this point carries no
		// usable handshake (invalid body or incomplete payload): it is refused
		// below instead of opening an availability window.
		if ( 0 === $status || 429 === $status || $status >= 500 ) {
			// Sites that must never trade 2FA for availability can opt in via
			// define( 'LLA_MFA_OUTAGE_FAIL_CLOSED', true ) or the filter below.
			// FILTER_VALIDATE_BOOLEAN on both: a literal 'false'/'0'/'off'
			// string must not enable the strict mode.
			$fail_closed = defined( 'LLA_MFA_OUTAGE_FAIL_CLOSED' )
				? filter_var( LLA_MFA_OUTAGE_FAIL_CLOSED, FILTER_VALIDATE_BOOLEAN )
				: false;
			$fail_closed = filter_var( apply_filters( 'llar_mfa_outage_fail_closed', $fail_closed ), FILTER_VALIDATE_BOOLEAN );
			if ( $fail_closed ) {
				return $this->mfa_refusal_error( 'llar_mfa_unavailable' );
			}

			if ( false === $disable_transient && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// Audit trail for the availability trade-off: logged when the
				// window opens, not on TTL refreshes (concurrent first requests
				// may each log it once), and only when the site enabled debug
				// logging.
				error_log( 'LLAR: MFA provider handshake failed (status ' . $status . ') - 2FA temporarily disabled for 60 seconds' );
			}
			set_transient( MfaConstants::TRANSIENT_MFA_DISABLED, MfaConstants::TRANSIENT_VALUE_API_UNREACHABLE, 60 );
			return null;
		}

		// The service answered but did not complete the handshake (200 with an
		// unusable body or an incomplete payload, any other 4xx): treat as a
		// hard failure rather than a reason to let the login through.
		return $this->mfa_refusal_error( 'llar_mfa_unavailable' );
	}
}
