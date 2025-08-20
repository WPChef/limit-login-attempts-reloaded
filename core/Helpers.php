<?php

namespace LLAR\Core;

use LLAR\Lib\CidrCheck;

if ( !defined( 'ABSPATH' ) ) exit;

class Helpers {

	/**
	 * @param string $msg
	 * @param bool $is_error
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

					if ( is_array( $info ) && ! empty( $info['date'] ) && ! empty( $info['counter'] ) ) { // For new plugin version
						$new_log[ $info['date'] ] = array(
							'ip'       => $ip,
							'username' => $user_name,
							'counter'  => $info['counter'],
							'gateway'  => isset( $info['gateway'] ) ? $info['gateway'] : '-',
							'unlocked' => ! empty( $info['unlocked'] ),
						);
						continue;
					}

					if ( ! is_array( $info ) ) { // For old plugin version
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

	public static function get_continent_list() {

		if ( ! ( $continent = require LLA_PLUGIN_DIR . '/resources/continent.php' ) ) {

			return array();
		}

		asort( $continent );

		return $continent;
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

	// Solution prevents double quotes problem in json string
	public static function sanitize_stripslashes_deep( $value )
	{
		if ( is_array( $value ) ) {
			return array_map( [self::class, 'sanitize_stripslashes_deep'], $value );
		}

		if ( is_bool( $value ) || is_null( $value ) ) {
			return $value;
		}

		return sanitize_textarea_field( stripslashes( (string)$value ) );
	}


	public static function is_auto_update_enabled() {
		$auto_update_plugins = get_site_option( 'auto_update_plugins' );
		return is_array( $auto_update_plugins ) && in_array( LLA_PLUGIN_BASENAME, $auto_update_plugins );
	}

	public static function is_block_automatic_update_disabled() {

        if ( ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS )
            || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
            return true;
        }

        return apply_filters( 'automatic_updater_disabled', false ) || ! apply_filters( 'auto_update_plugin', true, 10, 2 );
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

	public static function ip_in_range( $ip, $list ) {

		foreach ( $list as $range ) {

			$range = array_map('trim', explode('-', $range) );
			if ( count( $range ) == 1 ) {

				// CIDR
				if( strpos( $range[0], '/' ) !== false && self::check_ip_cidr( $ip, $range[0] ) ) {

					return true;
				}
				// Single IP
				else if ( (string)$ip === (string)$range[0] ) {

					return true;
				}

			} else {

				$low = ip2long( $range[0] );
				$high = ip2long( $range[1] );
				$needle = ip2long( $ip );

				if ( $low === false || $high === false || $needle === false )
					continue;

				$low = (float)sprintf("%u",$low);
				$high = (float)sprintf("%u",$high);
				$needle = (float)sprintf("%u",$needle);

				if ( $needle >= $low && $needle <= $high )
					return true;
			}
		}

		return false;
	}

	public static function detect_ip_address( $trusted_ip_origins ) {
		if( empty( $trusted_ip_origins ) || !is_array( $trusted_ip_origins ) ) {

			$trusted_ip_origins = array();
		}

		if( !in_array( 'REMOTE_ADDR', $trusted_ip_origins ) ) {

			$trusted_ip_origins[] = 'REMOTE_ADDR';
		}

		$ip = '';
		foreach ( $trusted_ip_origins as $origin ) {

			if( isset( $_SERVER[$origin] ) && !empty( $_SERVER[$origin] ) ) {

				if( strpos( $_SERVER[$origin], ',' ) !== false ) {

					$origin_ips = explode( ',', $_SERVER[$origin] );
					$origin_ips = array_map( 'trim', $origin_ips );

					if( $origin_ips ) {

						foreach ($origin_ips as $check_ip) {

							if( self::is_ip_valid( $check_ip ) ) {

								$ip = $check_ip;
								break 2;
							}
						}
					}
				}

				if( self::is_ip_valid( $_SERVER[$origin] ) ) {

					$ip = $_SERVER[$origin];
					break;
				}
			}
		}

		$ip = preg_replace('/^(\d+\.\d+\.\d+\.\d+):\d+$/', '\1', $ip);

		return $ip;
	}

	public static function get_all_ips() {

		$ips = array();

		foreach ( $_SERVER as $key => $value ) {

			if( in_array( $key, array( 'SERVER_ADDR' ) ) ) continue;

			if( $valid_ip = self::is_ip_valid( $value ) ) {

				$ips[$key] = $valid_ip;
			}
		}

		if( !empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) && !array_key_exists( 'HTTP_X_FORWARDED_FOR', $ips ) ) {

			$ips['HTTP_X_FORWARDED_FOR'] = $_SERVER['HTTP_X_FORWARDED_FOR'];
		}

		return $ips;
	}

	public static function is_ip_valid( $ip ) {
		if( empty( $ip ) ) return false;

		return filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ?:
			filter_var( preg_replace('/^(\d+\.\d+\.\d+\.\d+):\d+$/', '\1', $ip ), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 );
	}

	public static function detect_gateway() {

		$gateway = 'wp_login';

		if ( strpos( $_SERVER['REQUEST_URI'], 'wp-login.php' ) !== false && ( !isset( $_GET['action'] ) || $_GET['action'] === 'login' ) ) {
			$gateway = 'wp_login';
		} elseif ( isset( $_GET['action'] ) && $_GET['action'] === 'lostpassword' && strpos( $_SERVER['REQUEST_URI'], 'wp-login.php' ) !== false ) {
			$gateway = 'wp_lostpassword';
		} elseif ( isset($_GET['action']) && $_GET['action'] === 'register' && strpos($_SERVER['REQUEST_URI'], 'wp-login.php') !== false ) {
			$gateway = 'wp_register';
		} elseif ( isset( $GLOBALS['wp_xmlrpc_server'] ) && is_object( $GLOBALS['wp_xmlrpc_server'] ) ) {
			$gateway = 'wp_xmlrpc';
		} elseif ( strpos( $_SERVER['REQUEST_URI'], 'wp-login.php' ) === false ) {
			$gateway = trim( $_SERVER['REQUEST_URI'], '/' );
		}

		return $gateway;
	}

	public static function short_number($num) {
	    $units = array( '', 'K', 'M', 'B', 'T' );
	    for ($i = 0; $num >= 1000; $i++) {
	        $num /= 1000;
	    }
		return round($num, 1) . $units[$i];
	}

	public static function send_mail_with_logo( $to, $subject, $body ) {

		add_action( 'phpmailer_init', array( 'LLAR\Core\Helpers', 'add_attachments_to_php_mailer' ) );

		@wp_mail( $to, $subject, $body, array( 'content-type: text/html' ) );

		remove_action( 'phpmailer_init', array( 'LLAR\Core\Helpers', 'add_attachments_to_php_mailer' ) );
	}

	public static function add_attachments_to_php_mailer( &$phpmailer ) {
		$logo_path = LLA_PLUGIN_DIR . 'assets/img/logo.png';

		if( file_exists( $logo_path ) ) {
			$phpmailer->AddEmbeddedImage( $logo_path, 'logo' );
		}
	}

	public static function wp_locale() {
		return str_replace( '_', '-', get_locale() );
	}
	
	/**
	 * Retrieves debug information for the Debug tab.
	 *
	 * @return string Debug info as a multi-line string.
	 */
	public static function get_debug_info() {
		$debug_info = '';
		$ips        = array();
		$server     = array();

		foreach ( $_SERVER as $key => $value ) {
			if ( in_array( $key, array( 'SERVER_ADDR' ), true ) || is_array( $value ) ) {
				continue;
			}
			$ips_for_check = array_map( 'trim', explode( ',', $value ) );
			foreach ( $ips_for_check as $ip ) {
				if ( self::is_ip_valid( $ip ) ) {
					if ( ! in_array( $ip, $ips, true ) ) {
						$ips[] = $ip;
					}
					if ( ! isset( $server[ $key ] ) ) {
						$server[ $key ] = '';
					}
					if ( in_array( $ip, array( '127.0.0.1', '0.0.0.0' ), true ) ) {
						$server[ $key ] = $ip;
					} else {
						$server[ $key ] .= 'IP' . array_search( $ip, $ips, true ) . ',';
					}
				}
			}
		}
		$debug_info .= 'IPs:' . "\n";
		foreach ( $server as $server_key => $ips_val ) {
			$debug_info .= $server_key . ' = ' . trim( $ips_val, ',' ) . "\n";
		}

		$plugin_data = get_plugin_data( LLA_PLUGIN_FILE );
		if ( is_array( $plugin_data ) ) {
			$version = isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : '';
			$debug_info .= "\nPlugin Version: " . $version . "\n";
		} else {
			$debug_info .= "\nPlugin Version: \n";
		}
		$debug_info .= 'WordPress Version: ' . get_bloginfo( 'version' ) . "\n";
		$debug_info .= 'Is Multisite: ' . ( is_multisite() ? 'yes' : 'no' ) . "\n";
		$debug_info .= "\nActive Plugins:\n";
		$all_plugins = get_plugins();
		$active_plugins = get_option( 'active_plugins' );
		if ( is_array( $active_plugins ) ) {
			foreach ( $active_plugins as $plugin_file ) {
				if ( isset( $all_plugins[ $plugin_file ] ) ) {
					$plugin_data_item = $all_plugins[ $plugin_file ];

					$name    = isset( $plugin_data_item['Name'] ) ? $plugin_data_item['Name'] : '';
					$version = isset( $plugin_data_item['Version'] ) ? $plugin_data_item['Version'] : '';
					$uri     = isset( $plugin_data_item['PluginURI'] ) ? $plugin_data_item['PluginURI'] : '';

					// Base slug from path.
					$slug = dirname( $plugin_file );

					// Single-file plugin at plugins root: dirname() returns '.'.
					if ( '.' === $slug || '' === $slug ) {
						$slug = basename( $plugin_file, '.php' );
					}
					

					// If PluginURI points to WordPress.org, prefer slug from the URI.
					if ( ! empty( $uri ) && preg_match( '#WordPress\.org/plugins/([^/]+)/?#i', $uri, $m ) ) {
						$slug = $m[1];
					}

					// Normalize slug similar to WP's sanitize_title().
					$slug = sanitize_title( $slug );

					$mu_indicator = '';
					if ( 0 === strpos( $plugin_file, 'limit-login-attempts-reloaded' ) ) {
						$mu_indicator = self::is_mu() ? ' MU' : '';
					}

					// Prefer official WordPress.org PluginURI when it is clearly such.
					if ( ! empty( $uri ) && 0 === strpos( $uri, 'https://wordpress.org/plugins/' ) ) {
						$debug_info .= $name . ' ' . $version . ' (' . $uri . ')' . $mu_indicator . "\n";
					} else {
						$debug_info .= $name . ' ' . $version . ' (https://wordpress.org/plugins/' . $slug . '/)' . $mu_indicator . "\n";
					}
				}
			}
		}

		$current_theme = wp_get_theme();
		$theme_name    = is_object( $current_theme ) ? $current_theme->get( 'Name' ) : '';
		$theme_uri     = is_object( $current_theme ) ? $current_theme->get( 'ThemeURI' ) : '';
		$theme_slug    = is_object( $current_theme ) ? $current_theme->get_stylesheet() : '';

		$debug_info .= "\nActive Theme:\n";

		if ( ! empty( $theme_uri ) && 0 === strpos( $theme_uri, 'https://wordpress.org/themes/' ) ) {
			$debug_info .= $theme_name . ' (' . $theme_uri . ')' . "\n";
		} else {
			$debug_info .= $theme_name . ' (https://wordpress.org/themes/' . $theme_slug . '/)' . "\n";
		}

		return $debug_info;
	}
		
	
}