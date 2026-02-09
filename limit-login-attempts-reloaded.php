<?php
/*
Plugin Name: Limit Login Attempts Reloaded
Description: Block excessive login attempts and protect your site against brute force attacks. Simple, yet powerful tools to improve site performance.
Author: Limit Login Attempts Reloaded
Author URI: https://www.limitloginattempts.com/
Text Domain: limit-login-attempts-reloaded
Version: 2.26.28

Copyright 2008-2012 Johan Eenfeldt, 2016–present Limit Login Attempts Reloaded
*/

if( !defined( 'ABSPATH' ) ) exit;

/***************************************************************************************
 * Constants
 **************************************************************************************/
define( 'LLA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LLA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LLA_PLUGIN_FILE', __FILE__ );
define( 'LLA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/***************************************************************************************
 * Different ways to get remote address: direct & behind proxy
 **************************************************************************************/
define( 'LLA_DIRECT_ADDR', 'REMOTE_ADDR' );
define( 'LLA_PROXY_ADDR', 'HTTP_X_FORWARDED_FOR' );

/* Notify value checked against these in limit_login_sanitize_variables() */
define( 'LLA_LOCKOUT_NOTIFY_ALLOWED', 'log,email' );

/***************************************************************************************
 * MFA constants (rescue codes, rate limiting, transients).
 * Overridable: define in wp-config.php before plugin load to override defaults.
 **************************************************************************************/
defined( 'LLA_MFA_CODE_LENGTH' ) || define( 'LLA_MFA_CODE_LENGTH', 64 );
defined( 'LLA_MFA_CODE_COUNT' ) || define( 'LLA_MFA_CODE_COUNT', 10 );
defined( 'LLA_MFA_MAX_ATTEMPTS' ) || define( 'LLA_MFA_MAX_ATTEMPTS', 5 );
defined( 'LLA_MFA_RESCUE_LINK_TTL' ) || define( 'LLA_MFA_RESCUE_LINK_TTL', 300 );
defined( 'LLA_MFA_DISABLE_DURATION' ) || define( 'LLA_MFA_DISABLE_DURATION', 3600 );
defined( 'LLA_MFA_RATE_LIMIT_PERIOD' ) || define( 'LLA_MFA_RATE_LIMIT_PERIOD', 3600 );
defined( 'LLA_MFA_RESCUE_USE_COOLDOWN' ) || define( 'LLA_MFA_RESCUE_USE_COOLDOWN', 60 );
defined( 'LLA_MFA_TRANSIENT_RESCUE_PREFIX' ) || define( 'LLA_MFA_TRANSIENT_RESCUE_PREFIX', 'llar_mfa_rescue_' );
defined( 'LLA_MFA_TRANSIENT_ATTEMPTS_PREFIX' ) || define( 'LLA_MFA_TRANSIENT_ATTEMPTS_PREFIX', 'llar_rescue_attempts_' );
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
defined( 'LLA_MFA_FLOW_OTP_TTL' ) || define( 'LLA_MFA_FLOW_OTP_TTL', 180 );
defined( 'LLA_MFA_FLOW_HANDSHAKE_RATE_LIMIT_PERIOD' ) || define( 'LLA_MFA_FLOW_HANDSHAKE_RATE_LIMIT_PERIOD', 60 );
defined( 'LLA_MFA_FLOW_HANDSHAKE_RATE_LIMIT_MAX' ) || define( 'LLA_MFA_FLOW_HANDSHAKE_RATE_LIMIT_MAX', 5 );

$um_limit_login_failed = false;
$limit_login_my_error_shown = false; /* have we shown our stuff? */
$limit_login_just_lockedout = false; /* started this pageload??? */
$limit_login_nonempty_credentials = false; /* user and pwd nonempty */

if( file_exists( LLA_PLUGIN_DIR . 'autoload.php' ) ) {

	require_once( LLA_PLUGIN_DIR . 'autoload.php' );

	add_action( 'plugins_loaded', function() {
		(new LLAR\Core\LimitLoginAttempts());
	}, 9999 );

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
		global $wpdb;
		// $wpdb->options is set by WordPress and safe to use (table name, not user input).
		$table_name = $wpdb->options;
		$prefix     = LLA_MFA_TRANSIENT_RESCUE_PREFIX;

		// Delete rescue transients older than 1 day (use constant so overrides in wp-config are respected)
		$like_pattern = $wpdb->esc_like( '_transient_' . $prefix ) . '%';
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} 
				 WHERE option_name LIKE %s 
				 AND option_value < %d",
				$like_pattern,
				time() - DAY_IN_SECONDS
			)
		);

		$like_pattern_timeout = $wpdb->esc_like( '_transient_timeout_' . $prefix ) . '%';
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} 
				 WHERE option_name LIKE %s 
				 AND option_value < %d",
				$like_pattern_timeout,
				time() - DAY_IN_SECONDS
			)
		);
	}

	/**
	 * Helper function: Cleanup rescue transients
	 */
	function llar_mfa_cleanup_rescue_transients() {
		global $wpdb;
		// $wpdb->options is set by WordPress and safe to use (table name, not user input).
		$table_name = $wpdb->options;
		$prefix     = LLA_MFA_TRANSIENT_RESCUE_PREFIX;

		// Delete all rescue transients (use constant so overrides in wp-config are respected)
		$like_pattern = $wpdb->esc_like( '_transient_' . $prefix ) . '%';
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} 
				 WHERE option_name LIKE %s",
				$like_pattern
			)
		);

		$like_pattern_timeout = $wpdb->esc_like( '_transient_timeout_' . $prefix ) . '%';
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} 
				 WHERE option_name LIKE %s",
				$like_pattern_timeout
			)
		);
	}
}