<?php

namespace LLAR\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Config {

	const OPTION_LOCKOUTS   = 'lockouts';
	const OPTION_LOGGED     = 'logged';
	const OPTION_ACTIVE_APP = 'active_app';

	private static $default_options = array(
		'gdpr'                          => 0,
		'gdpr_message'                  => '',

		/* Are we behind a proxy? */
		'client_type'                   => LLA_DIRECT_ADDR,

		/* Lock out after this many tries */
		'allowed_retries'               => 4,

		/* Lock out for this many seconds */
		'lockout_duration'              => 1200, // 20 minutes

		/* Long lock out after this many lockouts */
		'allowed_lockouts'              => 4,

		/* Long lock out for this many seconds */
		'long_duration'                 => 86400, // 24 hours,

		/* Reset failed attempts after this many seconds */
		'valid_duration'                => 86400, // 12 hours

		/* Also limit malformed/forged cookies? */
		'cookies'                       => true,

		/* Notify on lockout. Values: '', 'log', 'email', 'log,email' */
		'lockout_notify'                => 'email',

        /* strong account policies */
        'checklist'                     => false,

		/* If notify by email, do so after this number of lockouts */
		'notify_email_after' => 3,

		'review_notice_shown'           => false,
		'enable_notify_notice_shown'    => false,

		'whitelist'                     => array(),
		'whitelist_usernames'           => array(),
		'blacklist'                     => array(),
		'blacklist_usernames'           => array(),

		'active_app'                    => 'local',
		'app_config'                    => '',
		'show_top_level_menu_item'      => true,
		'show_top_bar_menu_item'        => true,
		'hide_dashboard_widget'         => false,
		'show_warning_badge'            => true,
		'onboarding_popup_shown'        => false,
		/* Last known plugin header Version (from file), persisted on activate/update. */
		'plugin_version'                => '',
		'custom_error_message'          => '',
		'digest_daily'                  => 0,
		'digest_weekly'                 => 0,
		'digest_monthly'                => 0,

		'logged'                        => array(),
		'retries_valid'                 => array(),
		'retries'                       => array(),
		'lockouts'                      => array(),
		'auto_update_choice'            => null,

		/* MFA Rescue Codes */
		'mfa_rescue_codes'              => array(),
		'mfa_rescue_download_token'     => '',

		/* MFA Flow (after failed login: handshake, verify, email code) */
		'mfa_enabled'                   => 0,
		'mfa_provider'                  => 'llar',
		'mfa_provider_config'           => array(),
		'mfa_roles'                     => array( 'administrator' ),
	);

	private static $disable_autoload_options = array(
		'lockouts',
		'logged',
		'retries',
		'retries_valid',
		'retries_stats'
	);

	private static $prefix = 'limit_login_';

	private static $use_local_options = true;

	public static function get_default_options()
	{
		return self::$default_options || array();
	}

	public static function use_local_options( $value )
	{
		self::$use_local_options = $value;
	}

	public static function init() {
		self::$use_local_options = Helpers::use_local_options();
		self::apply_digest_defaults_from_definitions();
		self::ensure_digest_install_defaults();
	}

	public static function init_defaults() {
		self::$default_options['gdpr_message'] = __( 'By proceeding you understand and give your consent that your IP address and browser information might be processed by the security plugins installed on this site.', 'limit-login-attempts-reloaded' );
		self::apply_digest_defaults_from_definitions();
	}

	/**
	 * @param $name
	 *
	 * @return false|string
	 */
	private static function format_option_name( $name ) {
		if ( ! $name ) {
			return false;
		}

		return self::$prefix . $name;
	}

	/**
	 * Get option by name
	 *
	 * @param $option_name
	 *
	 * @return null
	 */
	public static function get( $option_name ) {
		$func  = self::$use_local_options ? 'get_option' : 'get_site_option';
		$value = $func( self::format_option_name( $option_name ), null );

		if ( is_null( $value ) && isset( self::$default_options[ $option_name ] ) ) {
			$value = self::$default_options[ $option_name ];
		}

		return $value;
	}

	/**
	 * Check if option is explicitly stored in DB.
	 *
	 * @param string $option_name Option name without prefix.
	 * @return bool
	 */
	public static function exists( $option_name ) {
		$func  = self::$use_local_options ? 'get_option' : 'get_site_option';
		$value = $func( self::format_option_name( $option_name ), null );

		return ! is_null( $value );
	}

	/**
	 * @param $option_name
	 * @param $value
	 *
	 * @return mixed
	 */
	public static function update( $option_name, $value ) {
		$func = self::$use_local_options ? 'update_option' : 'update_site_option';

		return $func( self::format_option_name( $option_name ), $value, self::is_autoload( $option_name ) );
	}

	/**
	 * @param $option_name
	 * @param $value
	 *
	 * @return mixed
	 */
	public static function add( $option_name, $value ) {
		$func = self::$use_local_options ? 'add_option' : 'add_site_option';

		return $func( self::format_option_name( $option_name ), $value, '', self::is_autoload( $option_name ) );
	}

	/**
	 * @param $option_name
	 *
	 * @return mixed
	 */
	public static function delete( $option_name ) {
		$func = self::$use_local_options ? 'delete_option' : 'delete_site_option';

		return $func( self::format_option_name( $option_name ) );
	}

	/**
	 * Setup main options
	 */
	public static function sanitize_options() {
		$simple_int_options = array(
			'allowed_retries',
			'lockout_duration',
			'valid_duration',
			'allowed_lockouts',
			'long_duration',
			'notify_email_after'
		);

		foreach ( $simple_int_options as $option ) {
			$val = self::get( $option );
			if ( (int) $val != $val || (int) $val <= 0 ) {
				self::update( $option, 1 );
			}
		}

		if ( self::get( 'notify_email_after' ) > self::get( 'allowed_lockouts' ) ) {
			self::update( 'notify_email_after', self::get( 'allowed_lockouts' ) );
		}

		$args         = explode( ',', self::get( 'lockout_notify' ) );
		$args_allowed = explode( ',', LLA_LOCKOUT_NOTIFY_ALLOWED );
		$new_args     = array_intersect( $args, $args_allowed );

		self::update( 'lockout_notify', implode( ',', $new_args ) );

		$client_type = self::get( 'client_type' );

		if ( $client_type != LLA_DIRECT_ADDR && $client_type != LLA_PROXY_ADDR ) {
			self::update( 'client_type', LLA_DIRECT_ADDR );
		}
	}

	/**
	 * @param $option_name
	 *
	 * @return string
	 */
	private static function is_autoload( $option_name ) {
		return in_array( trim( $option_name ), self::$disable_autoload_options ) ? 'no' : 'yes';
	}

	/**
	 * Digest toggles for fresh installs (register_activation_hook / no established data).
	 *
	 * @return array Map of digest_key => 0|1.
	 */
	private static function get_digest_defaults_for_new_install() {
		return array(
			'daily'   => 1,
			'weekly'  => 1,
			'monthly' => 1,
		);
	}

	/**
	 * Digest toggles for established sites that never saved digest_* options.
	 *
	 * @return array Map of digest_key => 0|1.
	 */
	private static function get_digest_defaults_for_existing_site() {
		return array(
			'daily'   => 0,
			'weekly'  => 1,
			'monthly' => 1,
		);
	}

	/**
	 * @param string $digest_key daily|weekly|monthly.
	 * @param array  $defaults   Map of digest_key => 0|1.
	 * @return int 0|1
	 */
	private static function get_digest_toggle_from_map( $digest_key, $defaults ) {
		$digest_key = sanitize_key( (string) $digest_key );

		return ! empty( $defaults[ $digest_key ] ) ? 1 : 0;
	}

	/**
	 * Persist all digest toggles on for a fresh plugin activation (register_activation_hook).
	 *
	 * @return void
	 */
	public static function apply_digest_defaults_on_fresh_activation() {
		// Do not call init() here: it runs ensure_digest_install_defaults() for established sites.
		self::$use_local_options = Helpers::use_local_options();

		if ( self::any_digest_option_stored() ) {
			return;
		}

		if ( self::exists( 'activation_timestamp' ) ) {
			return;
		}

		self::persist_digest_defaults( self::get_digest_defaults_for_new_install() );
	}

	/**
	 * Persist digest defaults for existing installs when digest options were never saved.
	 *
	 * @return void
	 */
	public static function ensure_digest_defaults_for_existing_site() {
		if ( self::any_digest_option_stored() ) {
			return;
		}

		self::persist_digest_defaults( self::get_digest_defaults_for_existing_site() );
	}

	/**
	 * Persist digest defaults for established sites that never saved digest options.
	 *
	 * @return void
	 */
	private static function ensure_digest_install_defaults() {
		if ( self::any_digest_option_stored() ) {
			return;
		}

		if ( ! self::has_established_plugin_data() ) {
			return;
		}

		self::persist_digest_defaults( self::get_digest_defaults_for_existing_site() );
	}

	/**
	 * @param array $defaults Map of digest key => 0|1.
	 * @return void
	 */
	private static function persist_digest_defaults( $defaults ) {
		foreach ( $defaults as $digest_key => $value ) {
			self::update( 'digest_' . sanitize_key( (string) $digest_key ), (int) $value ? 1 : 0 );
		}
	}

	/**
	 * @return bool
	 */
	private static function any_digest_option_stored() {
		foreach ( self::get_digest_definitions() as $digest_key => $digest_definition ) {
			if ( self::exists( 'digest_' . $digest_key ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return bool
	 */
	private static function has_established_plugin_data() {
		if ( self::exists( 'allowed_retries' ) || self::exists( 'activation_timestamp' ) ) {
			return true;
		}

		return self::exists( 'plugin_version' ) && '' !== (string) self::get( 'plugin_version' );
	}

	/**
	 * Fallback when digest options are not stored in the database yet.
	 *
	 * @param string $digest_key daily|weekly|monthly.
	 * @return int 0|1
	 */
	private static function get_digest_fallback_value( $digest_key ) {
		if ( ! self::any_digest_option_stored() && self::has_established_plugin_data() ) {
			return self::get_digest_toggle_from_map( $digest_key, self::get_digest_defaults_for_existing_site() );
		}

		return self::get_digest_toggle_from_map( $digest_key, self::get_digest_defaults_for_new_install() );
	}

	/**
	 * Apply digest defaults from shared digest definitions constant.
	 *
	 * @return void
	 */
	private static function apply_digest_defaults_from_definitions() {
		$digest_definitions = self::get_digest_definitions();

		foreach ( $digest_definitions as $digest_key => $digest_definition ) {
			$option_key = 'digest_' . $digest_key;
			self::$default_options[ $option_key ] = self::get_digest_fallback_value( $digest_key );
		}
	}

	/**
	 * Read digest definitions from shared constant with filter support.
	 *
	 * @return array
	 */
	private static function get_digest_definitions() {
		if ( ! is_array( LLA_DIGEST_DEFINITIONS ) ) {
			return array();
		}

		$definitions = apply_filters( 'llar_digest_definitions', LLA_DIGEST_DEFINITIONS );

		return is_array( $definitions ) ? $definitions : array();
	}
}