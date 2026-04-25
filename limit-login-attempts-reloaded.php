<?php
/*
Plugin Name: Limit Login Attempts Reloaded
Description: Block excessive login attempts and protect your site against brute force attacks. Simple, yet powerful tools to improve site performance.
Author: Limit Login Attempts Reloaded
Author URI: https://www.limitloginattempts.com/
Text Domain: limit-login-attempts-reloaded
Version: 3.2.0

Copyright 2008-2012 Johan Eenfeldt, 2016–present Limit Login Attempts Reloaded
*/

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/***************************************************************************************
 * Constants
 **************************************************************************************/
define( 'LLA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LLA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LLA_PLUGIN_FILE', __FILE__ );
define( 'LLA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Default risk widget config (bounds, colors, level rules).
 *
 * @return array
 */
function llar_get_risk_config_defaults() {
	return array(
		'bounds' => array(
			'low_upper'    => 100,
			'medium_upper' => 300,
		),
		'colors' => array(
			'green'  => '#97F6C8',
			'yellow' => '#FFE066',
			'orange' => '#FFA34C',
			'red'    => '#FF6633',
		),
		'levels' => array(
			'local'                 => array(
				array(
					'exact' => 0,
					'title' => 'zero_title',
					'color' => 'green',
				),
				array(
					'max_exclusive' => 100,
					'count_title'   => true,
					'desc'          => 'desc_low',
					'color'         => 'yellow',
				),
				array(
					'max_exclusive'  => 300,
					'count_title'    => true,
					'desc'           => 'desc_medium',
					/* Same recommendation block as high (red); threshold moved to 300+. */
					'recommendation' => true,
					'color'          => 'orange',
				),
				array(
					'min_inclusive'  => 300,
					'default'        => true,
					'warning_title'  => true,
					'recommendation' => true,
					'color'          => 'red',
				),
			),
		),
	);
}

/**
 * Merge filtered config with defaults so colors/levels/bounds always exist.
 *
 * @param array $defaults Default config.
 * @param mixed $cfg      Filtered value.
 *
 * @return array
 */
function llar_normalize_risk_config( $defaults, $cfg ) {
	if ( ! is_array( $cfg ) ) {
		return $defaults;
	}

	$out = $cfg;
	foreach ( array( 'bounds', 'colors', 'levels' ) as $key ) {
		if ( ! isset( $out[ $key ] ) || ! is_array( $out[ $key ] ) ) {
			$out[ $key ] = $defaults[ $key ];
		}
	}

	if ( ! isset( $out['levels']['local'] ) || ! is_array( $out['levels']['local'] ) ) {
		$out['levels']['local'] = $defaults['levels']['local'];
	}

	return $out;
}

/**
 * Risk widget config (colors, level rules). Cached per request; overridable via llar_risk_config filter.
 *
 * @return array
 */
function llar_get_risk_config() {
	static $cached = null;

	if ( null !== $cached ) {
		return $cached;
	}

	$defaults = llar_get_risk_config_defaults();
	$merged   = apply_filters( 'llar_risk_config', $defaults );
	$cached   = llar_normalize_risk_config( $defaults, $merged );

	return $cached;
}

/**
 * Warm risk config on init (after translations load).
 *
 * @return void
 */
function llar_define_risk_config() {
	llar_get_risk_config();
}

add_action( 'init', 'llar_define_risk_config', 1 );

/***************************************************************************************
 * Different ways to get remote address: direct & behind proxy
 **************************************************************************************/
define( 'LLA_DIRECT_ADDR', 'REMOTE_ADDR' );
define( 'LLA_PROXY_ADDR', 'HTTP_X_FORWARDED_FOR' );

/* Notify value checked against these in limit_login_sanitize_variables() */
define( 'LLA_LOCKOUT_NOTIFY_ALLOWED', 'log,email' );

/** Regex: valid email for obfuscation (1=first, 2=middle, 3=last, 4=domain). */
define( 'LLA_EMAIL_OBFUSCATE_REGEX', '/^(.)([^@]*)(.?)@(.*)$/' );
/** Regex: one char in local part to mask (not first, not last). (?<=.) = at least one char before; [^@*] avoids re-matching asterisks. */
define( 'LLA_EMAIL_OBFUSCATE_LOCAL', '/(?<=.)[^@*](?=[^@]+@)/' );
/** Regex: one char in domain to mask (non-dot). */
define( 'LLA_EMAIL_OBFUSCATE_DOMAIN', '/(?<=^[^@]*@.*)[^.]/' );

/***************************************************************************************
 * MFA constants (rescue codes, rate limiting, transients).
 * Overridable: define in wp-config.php before plugin load to override defaults.
 **************************************************************************************/
defined( 'LLA_MFA_CODE_LENGTH' ) || define( 'LLA_MFA_CODE_LENGTH', 64 );
defined( 'LLA_MFA_RESCUE_TOKEN_LENGTH' ) || define( 'LLA_MFA_RESCUE_TOKEN_LENGTH', 32 );
defined( 'LLA_MFA_CODE_COUNT' ) || define( 'LLA_MFA_CODE_COUNT', 10 );
/* Rescue link payload storage TTL (WordPress transients). Default 10 years; links are one-time (payload deleted on use). RESCUE_NOTICE_THRESHOLD is for admin warning; with a long TTL, "near expiry" is rare and missing/invalid payloads is the main trigger. */
defined( 'LLA_MFA_RESCUE_LINK_TTL' ) || define( 'LLA_MFA_RESCUE_LINK_TTL', 10 * YEAR_IN_SECONDS );
defined( 'LLA_MFA_RESCUE_NOTICE_THRESHOLD' ) || define( 'LLA_MFA_RESCUE_NOTICE_THRESHOLD', 5 * DAY_IN_SECONDS );
defined( 'LLA_MFA_DISABLE_DURATION' ) || define( 'LLA_MFA_DISABLE_DURATION', 3600 );
defined( 'LLA_MFA_RATE_LIMIT_PERIOD' ) || define( 'LLA_MFA_RATE_LIMIT_PERIOD', 3600 );
defined( 'LLA_MFA_RESCUE_USE_COOLDOWN' ) || define( 'LLA_MFA_RESCUE_USE_COOLDOWN', 60 );
defined( 'LLA_MFA_TRANSIENT_RESCUE_PREFIX' ) || define( 'LLA_MFA_TRANSIENT_RESCUE_PREFIX', 'llar_mfa_rescue_' );
defined( 'LLA_MFA_TRANSIENT_RESCUE_LAST_USE' ) || define( 'LLA_MFA_TRANSIENT_RESCUE_LAST_USE', 'llar_rescue_last_use' );
defined( 'LLA_MFA_TRANSIENT_MFA_DISABLED' ) || define( 'LLA_MFA_TRANSIENT_MFA_DISABLED', 'llar_mfa_temporarily_disabled' );
defined( 'LLA_MFA_TRANSIENT_CHECKBOX_STATE' ) || define( 'LLA_MFA_TRANSIENT_CHECKBOX_STATE', 'llar_mfa_checkbox_state' );
defined( 'LLA_MFA_CHECKBOX_STATE_TTL' ) || define( 'LLA_MFA_CHECKBOX_STATE_TTL', 300 );
defined( 'LLA_MFA_PDF_RATE_LIMIT_MAX' ) || define( 'LLA_MFA_PDF_RATE_LIMIT_MAX', 5 );
defined( 'LLA_MFA_PDF_RATE_LIMIT_PERIOD' ) || define( 'LLA_MFA_PDF_RATE_LIMIT_PERIOD', 60 );
defined( 'LLA_MFA_WP_SALT_SCHEME_FALLBACK' ) || define( 'LLA_MFA_WP_SALT_SCHEME_FALLBACK', 'auth' );
defined( 'LLA_MFA_BLOCK_REASON_SSL' ) || define( 'LLA_MFA_BLOCK_REASON_SSL', 'ssl' );
defined( 'LLA_MFA_BLOCK_REASON_SALT' ) || define( 'LLA_MFA_BLOCK_REASON_SALT', 'salt' );
defined( 'LLA_MFA_BLOCK_REASON_OPENSSL' ) || define( 'LLA_MFA_BLOCK_REASON_OPENSSL', 'openssl' );

/** MFA Flow: session and OTP transients (after failed login handshake). */
defined( 'LLA_MFA_FLOW_TRANSIENT_SESSION_PREFIX' ) || define( 'LLA_MFA_FLOW_TRANSIENT_SESSION_PREFIX', 'llar_mfa_session_' );
defined( 'LLA_MFA_FLOW_TRANSIENT_OTP_PREFIX' ) || define( 'LLA_MFA_FLOW_TRANSIENT_OTP_PREFIX', 'llar_mfa_otp_' );
defined( 'LLA_MFA_FLOW_TRANSIENT_SEND_SECRET_PREFIX' ) || define( 'LLA_MFA_FLOW_TRANSIENT_SEND_SECRET_PREFIX', 'llar_mfa_send_secret_' );
defined( 'LLA_MFA_FLOW_TRANSIENT_STATE_PREFIX' ) || define( 'LLA_MFA_FLOW_TRANSIENT_STATE_PREFIX', 'llar_mfa_state_' );
defined( 'LLA_MFA_FLOW_OTP_TTL' ) || define( 'LLA_MFA_FLOW_OTP_TTL', 180 );
defined( 'LLA_MFA_FLOW_HANDSHAKE_RATE_LIMIT_PERIOD' ) || define( 'LLA_MFA_FLOW_HANDSHAKE_RATE_LIMIT_PERIOD', 60 );
defined( 'LLA_MFA_FLOW_HANDSHAKE_RATE_LIMIT_MAX' ) || define( 'LLA_MFA_FLOW_HANDSHAKE_RATE_LIMIT_MAX', 5 );
defined( 'LLA_MFA_FLOW_LOG_PREFIX' ) || define( 'LLA_MFA_FLOW_LOG_PREFIX', 'LLAR MFA Flow: ' );
defined( 'LLA_MFA_RESCUE_PREFETCH_BYPASS_ARG' ) || define( 'LLA_MFA_RESCUE_PREFETCH_BYPASS_ARG', 'llar_rescue_confirm' );

/** MFA Flow: API and session (values from constants, no UI settings). */
defined( 'LLA_MFA_API_BASE_URL' ) || define( 'LLA_MFA_API_BASE_URL', 'https://api.limitloginattempts.com' );
defined( 'LLA_MFA_API_PATH' ) || define( 'LLA_MFA_API_PATH', '/mfa' );
defined( 'LLA_MFA_SESSION_TTL' ) || define( 'LLA_MFA_SESSION_TTL', 600 ); /* seconds, 10 minutes */
defined( 'LLA_MFA_PROVIDER' ) || define( 'LLA_MFA_PROVIDER', 'llar' );

$um_limit_login_failed            = false;
$limit_login_my_error_shown       = false; /* have we shown our stuff? */
$limit_login_just_lockedout       = false; /* started this pageload??? */
$limit_login_nonempty_credentials = false; /* user and pwd nonempty */

if ( file_exists( LLA_PLUGIN_DIR . 'autoload.php' ) ) {

	require_once LLA_PLUGIN_DIR . 'autoload.php';

	add_action(
		'plugins_loaded',
		function () {
			( new LLAR\Core\LimitLoginAttempts() );
		},
		9999
	);

	/**
	 * Activation hook: Cleanup old cron events and transients
	 */
	register_activation_hook( __FILE__, 'llar_mfa_activation_cleanup' );

	function llar_mfa_activation_cleanup() {
		// Clear old rescue transients
		llar_mfa_cleanup_rescue_transients();

		// Schedule daily cleanup if not already scheduled
		if ( ! wp_next_scheduled( 'llar_mfa_daily_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'llar_mfa_daily_cleanup' );
		}

		if ( class_exists( 'LLAR\\Core\\Helpers' ) ) {
			\LLAR\Core\Helpers::persist_stored_plugin_version();
		}
	}

	/**
	 * Deactivation hook: Cleanup cron events and transients (CRITICAL)
	 */
	register_deactivation_hook( __FILE__, 'llar_mfa_deactivation_cleanup' );

	function llar_mfa_deactivation_cleanup() {
		// Clear all scheduled events
		wp_clear_scheduled_hook( 'llar_mfa_daily_cleanup' );

		// Clear all rescue transients
		llar_mfa_cleanup_rescue_transients();
	}

	/**
	 * Daily cleanup: Remove old transients (prevents DB accumulation)
	 */
	add_action( 'llar_mfa_daily_cleanup', 'llar_mfa_daily_cleanup' );

	function llar_mfa_daily_cleanup() {
		$keys = llar_mfa_get_expired_rescue_transient_keys();
		foreach ( $keys as $key ) {
			delete_transient( $key );
		}
	}

	/**
	 * Get transient keys for rescue transients that are older than 1 day.
	 * Uses _transient_timeout_* where option_value is the expiration timestamp.
	 *
	 * @return array List of transient keys (e.g. llar_mfa_rescue_xxx).
	 */
	function llar_mfa_get_expired_rescue_transient_keys() {
		global $wpdb;
		$prefix = LLA_MFA_TRANSIENT_RESCUE_PREFIX;
		$cutoff = time() - DAY_IN_SECONDS;
		$like   = $wpdb->esc_like( '_transient_timeout_' . $prefix ) . '%';
		$names  = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT option_name FROM ' . $wpdb->options . ' WHERE option_name LIKE %s AND option_value < %d',
				$like,
				$cutoff
			)
		);
		if ( ! is_array( $names ) ) {
			return array();
		}
		$prefix_len = strlen( '_transient_timeout_' );
		$keys       = array();
		foreach ( $names as $name ) {
			$keys[] = substr( $name, $prefix_len );
		}
		return $keys;
	}

	/**
	 * Helper: delete all rescue transients (e.g. on deactivation).
	 * Uses delete_transient() so object cache stays in sync.
	 */
	function llar_mfa_cleanup_rescue_transients() {
		$keys = llar_mfa_get_all_rescue_transient_keys();
		foreach ( $keys as $key ) {
			delete_transient( $key );
		}
	}

	/**
	 * Get all rescue transient keys (for full cleanup).
	 *
	 * @return array List of transient keys.
	 */
	function llar_mfa_get_all_rescue_transient_keys() {
		global $wpdb;
		$prefix = LLA_MFA_TRANSIENT_RESCUE_PREFIX;
		$like   = $wpdb->esc_like( '_transient_timeout_' . $prefix ) . '%';
		$names  = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT option_name FROM ' . $wpdb->options . ' WHERE option_name LIKE %s',
				$like
			)
		);
		if ( ! is_array( $names ) ) {
			return array();
		}
		$prefix_len = strlen( '_transient_timeout_' );
		$keys       = array();
		foreach ( $names as $name ) {
			$keys[] = substr( $name, $prefix_len );
		}
		return $keys;
	}
}
