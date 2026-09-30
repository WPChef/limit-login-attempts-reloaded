<?php

namespace LLAR\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Optional trial registration landing redirects (Wordfence-style outbound hop).
 *
 * Before leaving for info.php the plugin stores a one-time token in a transient
 * (24h). The landing must echo that token back as llar_trial_token so only a
 * return from a user-initiated outbound click can auto-activate a setup_code.
 */
class TrialLanding {

	const INFO_ID_ONBOARDING = 37;
	const INFO_ID_DASHBOARD  = 38;
	const TOKEN_QUERY_ARG    = 'llar_trial_token';
	const TRANSIENT_PREFIX   = 'llar_trial_land_';
	const TOKEN_TTL          = 86400;
	const LANDING_BASE       = 'https://www.limitloginattempts.com/info.php';

	/**
	 * Create a one-time return token for the current admin.
	 *
	 * @param string $context onboarding|dashboard
	 * @return string Empty string on failure.
	 */
	public static function create_token( $context ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return '';
		}

		$context = self::normalize_context( $context );
		$token   = wp_generate_password( 32, false, false );
		$stored  = array(
			'user_id' => (int) $user_id,
			'context' => $context,
			'created' => time(),
		);

		set_transient( self::TRANSIENT_PREFIX . $token, $stored, self::TOKEN_TTL );

		return $token;
	}

	/**
	 * Verify and consume a return token.
	 *
	 * @param string $token Token from the return URL.
	 * @return array|false Stored payload or false.
	 */
	public static function consume_token( $token ) {
		$token = sanitize_text_field( $token );
		if ( '' === $token ) {
			return false;
		}

		$key  = self::TRANSIENT_PREFIX . $token;
		$data = get_transient( $key );
		delete_transient( $key );

		if ( empty( $data ) || ! is_array( $data ) ) {
			return false;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id || (int) $data['user_id'] !== (int) $user_id ) {
			return false;
		}

		return $data;
	}

	/**
	 * Peek at a token without consuming it (for soft checks).
	 *
	 * @param string $token Token from the return URL.
	 * @return array|false
	 */
	public static function peek_token( $token ) {
		$token = sanitize_text_field( $token );
		if ( '' === $token ) {
			return false;
		}

		$data = get_transient( self::TRANSIENT_PREFIX . $token );
		if ( empty( $data ) || ! is_array( $data ) ) {
			return false;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id || (int) $data['user_id'] !== (int) $user_id ) {
			return false;
		}

		return $data;
	}

	/**
	 * Build the outbound info.php URL with an encoded client payload.
	 *
	 * @param int    $info_id    Landing id (37 onboarding, 38 dashboard).
	 * @param string $email      Notification / admin email.
	 * @param string $return_url Absolute URL the landing should send the user back to.
	 * @param string $context    onboarding|dashboard
	 * @param string $token      Optional pre-created token; created when empty.
	 * @return string
	 */
	public static function build_url( $info_id, $email, $return_url, $context, $token = '' ) {
		if ( '' === $token ) {
			$token = self::create_token( $context );
		}

		$client = array(
			'url'   => $return_url,
			'email' => $email,
			'token' => $token,
		);

		$client_json = wp_json_encode( $client );
		if ( false === $client_json ) {
			$client_json = '{}';
		}

		return self::LANDING_BASE
			. '?id=' . (int) $info_id
			. '&client=' . rawurlencode( $client_json );
	}

	/**
	 * Return URL for onboarding outbound hop (includes onboarding=true).
	 *
	 * @return string
	 */
	public static function onboarding_return_url() {
		$base = admin_url( 'admin.php?page=limit-login-attempts&tab=dashboard' );
		return add_query_arg( 'onboarding', 'true', $base );
	}

	/**
	 * Return URL for dashboard / settings outbound hop (no onboarding=trial).
	 *
	 * @param string $tab Plugin tab slug.
	 * @return string
	 */
	public static function dashboard_return_url( $tab = 'dashboard' ) {
		$tab = sanitize_key( $tab );
		if ( '' === $tab ) {
			$tab = 'dashboard';
		}

		return admin_url( 'admin.php?page=limit-login-attempts&tab=' . $tab );
	}

	/**
	 * Best-effort admin notification email for the client payload.
	 *
	 * @return string
	 */
	public static function resolve_email() {
		$admin_notify_email = Config::get( 'admin_notify_email' );
		if ( ! empty( $admin_notify_email ) && is_email( $admin_notify_email ) ) {
			return $admin_notify_email;
		}

		$email = is_multisite() ? get_site_option( 'admin_email' ) : get_option( 'admin_email' );
		return is_email( $email ) ? $email : '';
	}

	/**
	 * Cached parse of the current request (token consumed at most once).
	 *
	 * @var array|null
	 */
	private static $parsed_return = null;

	/**
	 * Parse inbound return query args for the LLAR admin screen.
	 *
	 * Token is required only when setup_code is present (activation path).
	 * onboarding=trial alone re-opens step 3 without consuming a token.
	 *
	 * @return array {
	 *     @type bool   $open_trial_step Jump onboarding to step 3.
	 *     @type string $setup_code      Setup code to activate (empty if none).
	 *     @type string $context         onboarding|dashboard|''.
	 *     @type bool   $can_activate    Token valid and site has no setup code yet.
	 * }
	 */
	public static function parse_return_request() {
		if ( null !== self::$parsed_return ) {
			return self::$parsed_return;
		}

		$result = array(
			'open_trial_step' => false,
			'setup_code'      => '',
			'context'         => '',
			'can_activate'    => false,
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public return URL from external landing.
		$onboarding = isset( $_GET['onboarding'] ) ? sanitize_text_field( wp_unslash( $_GET['onboarding'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$setup_code = isset( $_GET['setup_code'] ) ? sanitize_text_field( wp_unslash( $_GET['setup_code'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token = isset( $_GET[ self::TOKEN_QUERY_ARG ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::TOKEN_QUERY_ARG ] ) ) : '';
		if ( '' === $token && isset( $_GET['token'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- alias if landing echoes client.token as token=
			$token = sanitize_text_field( wp_unslash( $_GET['token'] ) );
		}

		if ( 'trial' === $onboarding ) {
			$result['open_trial_step'] = true;
			$result['context']         = 'onboarding';
		}

		if ( '' === $setup_code ) {
			self::$parsed_return = $result;
			return self::$parsed_return;
		}

		// Already activated — ignore inbound setup_code entirely.
		if ( ! empty( Config::get( 'app_setup_code' ) ) ) {
			self::$parsed_return = $result;
			return self::$parsed_return;
		}

		$token_data = self::consume_token( $token );
		if ( empty( $token_data ) ) {
			self::$parsed_return = $result;
			return self::$parsed_return;
		}

		$result['setup_code']   = $setup_code;
		$result['context']      = self::normalize_context( $token_data['context'] );
		$result['can_activate'] = true;

		if ( 'onboarding' === $result['context'] ) {
			$result['open_trial_step'] = true;
		}

		self::$parsed_return = $result;
		return self::$parsed_return;
	}

	/**
	 * @param string $context Raw context.
	 * @return string onboarding|dashboard
	 */
	private static function normalize_context( $context ) {
		$context = sanitize_key( $context );
		if ( 'onboarding' === $context ) {
			return 'onboarding';
		}

		return 'dashboard';
	}
}
