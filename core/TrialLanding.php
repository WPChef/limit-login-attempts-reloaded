<?php

namespace LLAR\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Optional trial registration landing redirects (Wordfence-style outbound hop).
 *
 * Before leaving for info.php the plugin stores a one-time nonce in a transient
 * (30 min) and embeds it into the url= parameter of the outbound landing link, so
 * only a return from a user-initiated outbound click can auto-activate a
 * setup_code. The active token is reused (not regenerated) while it is still
 * fresh, so page views no longer grow wp_options.
 */
class TrialLanding {

	const INFO_ID_ONBOARDING = 37;
	const INFO_ID_DASHBOARD  = 38;
	// Per-CTA marketing info ids: each entry point gets its own landing tag.
	const INFO_ID_LOGS            = 42;
	const INFO_ID_PREMIUM         = 43;
	const INFO_ID_SETTINGS_LOCAL  = 44;
	const INFO_ID_SETTINGS_CLOUD  = 45;
	const INFO_ID_UPGRADE_LOCAL   = 46;
	const INFO_ID_UPGRADE_CLOUD   = 47;
	const TOKEN_QUERY_ARG    = 'llar_trial_token';
	const TRANSIENT_PREFIX   = 'llar_trial_land_';
	const TOKEN_TTL          = 1800;
	const LANDING_BASE       = 'https://www.limitloginattempts.com/info.php';
	// Reversed "api.limitloginattempts.com/" marker every cloud setup code ends with.
	const SETUP_CODE_SUFFIX  = '/moc.stpmettanigoltimil.ipa';

	/**
	 * Create (or reuse) the one-time return token for the current admin.
	 *
	 * While a previously issued token is still unconsumed and fresh, it is
	 * reused instead of writing a new transient on every page view.
	 *
	 * @param string $context onboarding|dashboard
	 * @return string Empty string on failure.
	 */
	public static function create_token( $context ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return '';
		}

		$context    = self::normalize_context( $context );
		$active_key = self::active_token_key( $user_id, $context );

		$existing = get_transient( $active_key );
		if ( ! empty( $existing )
			&& false !== get_transient( self::TRANSIENT_PREFIX . $existing )
		) {
			return $existing;
		}

		$token = wp_generate_password( 32, false, false );
		$stored  = array(
			'user_id' => (int) $user_id,
			'context' => $context,
			'created' => time(),
		);

		set_transient( self::TRANSIENT_PREFIX . $token, $stored, self::TOKEN_TTL );
		set_transient( $active_key, $token, self::TOKEN_TTL );

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

		$active_key = self::active_token_key( (int) $data['user_id'], self::normalize_context( $data['context'] ) );
		if ( get_transient( $active_key ) === $token ) {
			delete_transient( $active_key );
		}

		return $data;
	}

	/**
	 * Transient key holding the reusable active token for a user + context.
	 *
	 * @param int    $user_id User ID.
	 * @param string $context onboarding|dashboard
	 * @return string
	 */
	private static function active_token_key( $user_id, $context ) {
		return self::TRANSIENT_PREFIX . 'active_' . (int) $user_id . '_' . $context;
	}

	/**
	 * Build the outbound info.php URL with separate url and email params.
	 *
	 * The one-time nonce is embedded into the url= parameter (not passed
	 * separately), so the landing only has to send the user back to that
	 * url verbatim for the nonce to survive the round trip.
	 *
	 * @param int    $info_id    Landing id (37 onboarding, 38 dashboard).
	 * @param string $email      Notification / admin email. Empty when the user
	 *                            has not consented to sharing it — the landing
	 *                            asks for the email on its own form instead.
	 * @param string $return_url Absolute URL the landing should send the user back to.
	 * @param string $context    onboarding|dashboard
	 * @param string $token      Optional pre-created token; created when empty.
	 * @return string
	 */
	public static function build_url( $info_id, $email, $return_url, $context, $token = '' ) {
		if ( '' === $token ) {
			$token = self::create_token( $context );
		}

		$return_url = self::with_token( $return_url, $token );

		$url = self::LANDING_BASE
			. '?id=' . (int) $info_id
			. '&url=' . rawurlencode( $return_url );

		if ( '' !== $email ) {
			$url .= '&email=' . rawurlencode( $email );
		}

		return $url;
	}

	/**
	 * Embed the one-time nonce into a return URL.
	 *
	 * add_query_arg() replaces an existing value, so calling this on a URL
	 * that already carries the nonce never duplicates it.
	 *
	 * @param string $return_url Absolute return URL.
	 * @param string $token      Nonce to embed (skipped when empty).
	 * @return string
	 */
	private static function with_token( $return_url, $token ) {
		if ( '' === $token ) {
			return $return_url;
		}

		return add_query_arg( self::TOKEN_QUERY_ARG, $token, $return_url );
	}

	/**
	 * Return URL for onboarding outbound hop (includes onboarding=true).
	 *
	 * @param string $token Optional one-time nonce to embed in the URL.
	 * @return string
	 */
	public static function onboarding_return_url( $token = '' ) {
		$base = admin_url( 'admin.php?page=limit-login-attempts&tab=dashboard' );
		$base = add_query_arg( 'onboarding', 'true', $base );

		return self::with_token( $base, $token );
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
	 * Ready-made outbound dashboard landing URL.
	 *
	 * Keeps every "start the free trial" link on the same contract
	 * (info id, return tab, context). The email is only pre-filled for
	 * consent-gated CTAs; everywhere else the landing asks for it.
	 *
	 * @param string $tab        Plugin tab slug to return to.
	 * @param string $token      Optional pre-created token.
	 * @param bool   $with_email Whether the admin email may be shared (consent given).
	 * @return string
	 */
	public static function dashboard_landing_url( $tab = 'dashboard', $token = '', $with_email = true ) {
		return self::build_url(
			self::INFO_ID_DASHBOARD,
			$with_email ? self::resolve_email() : '',
			self::dashboard_return_url( $tab ),
			'dashboard',
			$token
		);
	}

	/**
	 * Ready-made outbound landing URL for a specific CTA info id.
	 *
	 * Non-dashboard CTAs (logs, premium, settings) open the trial start
	 * popup, so the admin email is never pre-filled here — the landing
	 * asks for it after the consent click.
	 *
	 * @param int    $info_id    Marketing info id of the CTA.
	 * @param string $tab        Plugin tab slug to return to.
	 * @param bool   $with_email Whether the admin email may be shared (consent given).
	 * @return string
	 */
	public static function cta_landing_url( $info_id, $tab = 'dashboard', $with_email = false ) {
		return self::build_url(
			(int) $info_id,
			$with_email ? self::resolve_email() : '',
			self::dashboard_return_url( $tab ),
			'dashboard',
			''
		);
	}

	/**
	 * Whether a setup code was issued by the LLAR cloud.
	 *
	 * Every real cloud setup code ends with the reversed-domain marker, so an
	 * arbitrary string in the return URL can never auto-activate a trial.
	 *
	 * @param string $setup_code Raw setup code from the return URL.
	 * @return bool
	 */
	public static function is_trusted_setup_code( $setup_code ) {
		$setup_code = (string) $setup_code;
		if ( strlen( $setup_code ) <= strlen( self::SETUP_CODE_SUFFIX ) ) {
			return false;
		}

		return substr( $setup_code, -strlen( self::SETUP_CODE_SUFFIX ) ) === self::SETUP_CODE_SUFFIX;
	}

	/**
	 * Message shown when a trial return link cannot be used.
	 *
	 * @return string
	 */
	public static function invalid_return_message() {
		return __( 'The link you\'re using to activate your trial period is invalid or has expired.', 'limit-login-attempts-reloaded' );
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
	 *     @type bool   $can_activate    Token valid, setup code trusted and site has no setup code yet.
	 *     @type bool   $return_error    Setup code was returned but the token or the setup code is invalid/expired.
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
			'return_error'    => false,
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public return URL from external landing.
		$onboarding = isset( $_GET['onboarding'] ) ? sanitize_text_field( wp_unslash( $_GET['onboarding'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$setup_code = isset( $_GET['setup_code'] ) ? sanitize_text_field( wp_unslash( $_GET['setup_code'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce rides inside the url= the landing returns to.
		$token = isset( $_GET[ self::TOKEN_QUERY_ARG ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::TOKEN_QUERY_ARG ] ) ) : '';

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

		// Only setup codes issued by the LLAR cloud may auto-activate.
		if ( ! self::is_trusted_setup_code( $setup_code ) ) {
			$result['return_error'] = true;
			self::$parsed_return  = $result;
			return self::$parsed_return;
		}

		$token_data = self::consume_token( $token );
		if ( empty( $token_data ) ) {
			$result['return_error'] = true;
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
