<?php

namespace LLAR\Core;

use LLAR\Lib\CidrCheck;

if ( !defined( 'ABSPATH' ) ) exit;

class Helpers {

	/**
	 * @param string $msg
	 */
	public static function show_message( $msg = '', $is_error = false ) {
		if ( empty( $msg ) ) {
			return;
		}

		$class = $is_error ? 'error' : 'updated';
		echo '<div id="message" class="' . $class . ' fade"><p>' . $msg . '</p></div>';
	}

	/**
	 * @param $log
	 *
	 * @return array
	 */
	public static function sorted_log_by_date( $log ) {
		$new_log = array();

		if ( ! is_array( $log ) || empty( $log ) ) {
			return $new_log;
		}

		foreach ( $log as $ip => $users ) {

			if ( ! empty( $users ) ) {
				foreach ( $users as $user_name => $info ) {

					if ( is_array( $info ) ) { // For new plugin version
						$new_log[ $info['date'] ] = array(
							'ip'       => $ip,
							'username' => $user_name,
							'counter'  => $info['counter'],
							'gateway'  => ( isset( $info['gateway'] ) ) ? $info['gateway'] : '-',
							'unlocked' => ! empty( $info['unlocked'] ),
						);
					} else { // For old plugin version
						$new_log[0] = array(
							'ip'       => $ip,
							'username' => $user_name,
							'counter'  => $info,
							'gateway'  => '-',
							'unlocked' => false,
						);
					}

				}
			}

		}

		krsort( $new_log );

		return $new_log;
	}

	public static function get_countries_list() {

		if ( ! ( $countries = require LLA_PLUGIN_DIR . '/resources/countries.php' ) ) {

			return array();
		}

		asort( $countries );

		return $countries;
	}

	/**
	 * @param $ip
	 * @param $cidr
	 *
	 * @return bool
	 */
	public static function check_ip_cidr( $ip, $cidr ) {

		if ( ! $ip || ! $cidr ) {
			return false;
		}

		$cidr_checker = new CidrCheck();

		return $cidr_checker->match( $ip, $cidr );
	}

	/**
	 * Checks if the plugin is installed as Must Use plugin
	 *
	 * @return bool
	 */
	public static function is_mu() {

		return ( strpos( LLA_PLUGIN_DIR, 'mu-plugins' ) !== false );
	}

	/**
	 * @param $content
	 *
	 * @return string|string[]|null
	 */
	public static function deslash( $content ) {

		$content = preg_replace( "/\\\+'/", "'", $content );
		$content = preg_replace( '/\\\+"/', '"', $content );
		$content = preg_replace( '/\\\+/', '\\', $content );

		return $content;
	}

	public static function is_auto_update_enabled() {
		$auto_update_plugins = get_site_option( 'auto_update_plugins' );
		return is_array( $auto_update_plugins ) && in_array( LLA_PLUGIN_BASENAME, $auto_update_plugins );
	}

	public static function get_wordpress_version() {
		global $wp_version;
		return $wp_version;
	}

	/**
	 * @return bool
	 */
	public static function is_network_mode() {
		if ( !is_multisite() ) return false;

		require_once ABSPATH.'wp-admin/includes/plugin.php';

		return is_plugin_active_for_network( 'limit-login-attempts-reloaded/limit-login-attempts-reloaded.php' );
	}

	/**
	 * @return bool
	 */
	public static function allow_local_options() {

		if( !self::is_network_mode() ) return true;

		return get_site_option( 'limit_login_allow_local_options', false );
	}

	/**
	 * @return bool
	 */
	public static function use_local_options() {

		if( !self::is_network_mode() ) return true;

		return get_site_option( 'limit_login_allow_local_options', false ) &&
		       get_option( 'limit_login_use_local_options', false );
	}

	/**
	 * @param $new_app_config
	 * @param false $update_created_at
	 *
	 * @return false
	 */
	public static function cloud_app_update_config( $new_app_config, $update_created_at = false ) {
		if( !$new_app_config ) return false;

		if( $active_app_config = Config::get( 'app_config' ) ) {

			foreach ( $active_app_config['settings'] as $key => $info ) {

				if( array_key_exists( $key, $new_app_config['settings'] ) ) {

					if( !empty( $new_app_config['settings'][$key]['options'] ) &&
					    !in_array( $info['value'], $new_app_config['settings'][$key]['options'] ) ) {

						continue;
					}

					$new_app_config['settings'][$key]['value'] = $info['value'];
				}
			}

		}

		if( $update_created_at )
			$new_app_config['created_at'] = time();

		Config::update( 'app_config', $new_app_config );
	}

	/**
	 * @param $filepath
	 *
	 * @return bool
	 */
	public static function is_writable( $filepath ) {
		return file_exists( $filepath ) && wp_is_writable( $filepath );
	}
}