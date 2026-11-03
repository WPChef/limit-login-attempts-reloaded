<?php

namespace LLAR\Core;

use LLAR\Lib\CidrCheck;
use LLAR\Core\Mail\Mailer;

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

		$previous_app_config = Config::get( 'app_config' );

		if( $active_app_config = $previous_app_config ) {

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

		// The /info payload may change with the app config — drop the cached
		// exhausted flag for both the previous and the new app id.
		LimitLoginAttempts::clear_mc_exhausted_cache( $previous_app_config );
		LimitLoginAttempts::clear_mc_exhausted_cache( $new_app_config );
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

			// If REMOTE_ADDR contains multiple values (comma-separated), keep only the first before validation
			if ( $key === 'REMOTE_ADDR' && strpos( $value, ',' ) !== false ) {

				$parts = explode( ',', $value );
				$value = trim( $parts[0] );
			}

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
		// Use raw path for matching; avoid sanitize_text_field() which can alter the URI and break gateway detection.
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? rawurldecode( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';

		// Some plugins hide wp-login.php and mask REQUEST_URI.
		// Prefer core routing marker when available.
		if ( isset( $GLOBALS['pagenow'] ) && 'wp-login.php' === $GLOBALS['pagenow'] ) {
			switch ( $action ) {
				case 'lostpassword':
					return 'wp_lostpassword';
				case 'register':
					return 'wp_register';
				default:
					return 'wp_login';
			}
		}

		switch ( true ) {
			case false !== strpos( $request_uri, 'wp-login.php' ) && ( ! $action || 'login' === $action ):
				$gateway = 'wp_login';
				break;
			case 'lostpassword' === $action && false !== strpos( $request_uri, 'wp-login.php' ):
				$gateway = 'wp_lostpassword';
				break;
			case 'register' === $action && false !== strpos( $request_uri, 'wp-login.php' ):
				$gateway = 'wp_register';
				break;
			case isset( $GLOBALS['wp_xmlrpc_server'] ) && is_object( $GLOBALS['wp_xmlrpc_server'] ):
				$gateway = 'wp_xmlrpc';
				break;
			case false === strpos( $request_uri, 'wp-login.php' ):
				$gateway = trim( $request_uri, '/' );
				$gateway = str_replace( '/', '_', $gateway );
				$gateway = substr( sanitize_key( $gateway ), 0, 100 );
				if ( empty( $gateway ) ) {
					$gateway = 'custom_login';
				}
				break;
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

	/**
	 * Get current request URI from server globals.
	 *
	 * @return string
	 */
	public static function get_request_uri() {
		return isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	}

	/**
	 * Get current URL label from request URI.
	 *
	 * @return string
	 */
	public static function get_current_url_label() {
		return preg_replace( '/^\/|\/$/', '', self::get_request_uri() );
	}

	/**
	 * Get current URL with referer fallback.
	 *
	 * @return string
	 */
	public static function get_current_url() {
		if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
			return wp_unslash( $_SERVER['HTTP_REFERER'] );
		}

		return get_site_url() . self::get_request_uri();
	}

	/**
	 * Send HTML email with embedded LLAR logo using shared Mailer layout.
	 *
	 * @param string $to
	 * @param string $subject
	 * @param string $body Content-only HTML.
	 *
	 * @return bool
	 */
	public static function send_mail_with_logo( $to, $subject, $body ) {

		add_action( 'phpmailer_init', array( 'LLAR\Core\Helpers', 'add_attachments_to_php_mailer' ) );

		$sent = Mailer::send(
			$to,
			$subject,
			$body,
			array( 'content-type: text/html' ),
			array(),
			true,
			array(
				'title'    => $subject,
				'logo_cid' => 'logo',
			)
		);

		remove_action( 'phpmailer_init', array( 'LLAR\Core\Helpers', 'add_attachments_to_php_mailer' ) );

		return (bool) $sent;
	}

	public static function add_attachments_to_php_mailer( &$phpmailer ) {
		if ( ! self::is_llar_phpmailer_message( $phpmailer ) ) {
			return;
		}

		$logo_path = LLA_PLUGIN_DIR . 'assets/img/llar-logo-email.png';

		if( file_exists( $logo_path ) ) {
			$phpmailer->AddEmbeddedImage( $logo_path, 'logo' );
		}
	}

	/**
	 * Determine if PHPMailer instance belongs to LLAR mail.
	 *
	 * @param \PHPMailer\PHPMailer\PHPMailer|object $phpmailer PHPMailer instance.
	 * @return bool
	 */
	private static function is_llar_phpmailer_message( $phpmailer ) {
		if ( is_object( $phpmailer ) && method_exists( $phpmailer, 'getCustomHeaders' ) ) {
			$headers = $phpmailer->getCustomHeaders();
			if ( is_array( $headers ) ) {
				foreach ( $headers as $header ) {
					if ( ! is_array( $header ) || count( $header ) < 2 ) {
						continue;
					}
					$name  = strtolower( (string) $header[0] );
					$value = strtolower( trim( (string) $header[1] ) );
					if ( 'x-llar-email' === $name && 'true' === $value ) {
						return true;
					}
				}
			}
		}

		// Fallback for SMTP plugins that drop custom headers.
		return Mailer::is_runtime_send_active();
	}

	public static function wp_locale() {
		return str_replace( '_', '-', get_locale() );
	}

	/**
	 * Load and cache CSS for email layout injected into the <style> block.
	 *
	 * @return string
	 */
	public static function get_email_css_text() {
		static $css_text = null;

		if ( null !== $css_text ) {
			return $css_text;
		}

		$css_path = LLA_PLUGIN_DIR . 'views/emails/email-layout.css';
		if ( file_exists( $css_path ) && is_readable( $css_path ) ) {
			$css_text = (string) file_get_contents( $css_path );
		} else {
			$css_text = '';
		}

		return $css_text;
	}

	/**
	 * Obfuscate email for handshake API: first+asterisks+last per part, preserving length (e.g. t**t@*******.***).
	 *
	 * @param string $email Raw email address.
	 * @return string Obfuscated email or empty string if invalid.
	 */
	public static function obfuscate_email( $email ) {
		$email = trim( (string) $email );
		if ( $email === '' ) {
			return '';
		}
		if ( ! preg_match( LLA_EMAIL_OBFUSCATE_REGEX, $email ) ) {
			return '***@***.***';
		}
		$after_local  = preg_replace( LLA_EMAIL_OBFUSCATE_LOCAL, '*', $email );
		$after_domain = preg_replace_callback( '/@(.*)$/', function ( $m ) {
			return '@' . preg_replace_callback( '/[^.]+/', function ( $m2 ) {
				return str_repeat( '*', strlen( $m2[0] ) );
			}, $m[1] );
		}, $after_local );
		return $after_domain;
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

	/**
	 * Write the plugin's header Version to options (for migrations / diagnostics after activate or in-dashboard update).
	 *
	 * @return void
	 */
	public static function persist_stored_plugin_version() {
		Config::init();
		$plugin_data = get_plugin_data( LLA_PLUGIN_FILE, false, false );
		$version     = is_array( $plugin_data ) && ! empty( $plugin_data['Version'] )
			? sanitize_text_field( $plugin_data['Version'] )
			: '';
		Config::update( 'plugin_version', $version );
	}


	/**
	 * Returns compatibility data for the Debug tab.
	 *
	 * Shows which LLAR features work with which third-party plugins.
	 * Values reflect integration code and the readme "Compatible With" list.
	 *
	 * @return array
	 */
	public static function get_compatibility_data() {
		return require LLA_PLUGIN_DIR . 'resources/compatibility-data.php';
	}

	/**
	 * Whether the runtime PHP build is below a patched PHP2Shell release.
	 *
	 * Upstream minima: 8.5.12 / 8.4.27 / 8.3.36 / 8.2.35 / 8.6.0RC4.
	 * Distro packages (Ubuntu/Debian) keep an older PHP_VERSION while shipping
	 * the fix in the package revision — when a matching php*.deb is installed,
	 * that revision is compared instead. Source-built PHP (e.g. official Docker
	 * images with no php*-deb) still uses PHP_VERSION.
	 *
	 * @param string|null $php_version     Optional. Defaults to PHP_VERSION.
	 * @param string|null $package_version Optional. Injected Debian package version for tests.
	 * @param array|null  $os_release      Optional. Injected os-release fields (ID, VERSION_ID).
	 * @return bool
	 */
	public static function is_php_critically_vulnerable( $php_version = null, $package_version = null, $os_release = null ) {
		$version = null === $php_version ? PHP_VERSION : (string) $php_version;
		if ( '' === $version ) {
			return false;
		}

		$branch = self::php_major_minor( $version );
		if ( null === $branch ) {
			return false;
		}

		// Vendor package path: Ubuntu/Debian backports keep PHP_VERSION frozen.
		$package = null === $package_version
			? self::detect_debian_php_package_version( $branch )
			: (string) $package_version;

		if ( '' !== (string) $package ) {
			$os      = null === $os_release ? self::read_os_release() : $os_release;
			$min_pkg = self::php2shell_min_distro_package( $os, $branch );
			if ( null !== $min_pkg ) {
				return ! self::debian_version_ge( (string) $package, $min_pkg );
			}
			// Packaged PHP on an unknown distro/release: do not flag by PHP_VERSION alone
			// (would false-positive on already-patched LTS hosts).
			return false;
		}

		return self::is_upstream_php_critically_vulnerable( $version, $branch );
	}

	/**
	 * Upstream (php.net) PHP_VERSION comparison for PHP2Shell.
	 *
	 * @param string $version Full PHP_VERSION string.
	 * @param string $branch  Major.minor, e.g. "8.3".
	 * @return bool
	 */
	private static function is_upstream_php_critically_vulnerable( $version, $branch ) {
		// 8.6 prereleases: fix landed in 8.6.0RC4 (not the final 8.6.0 gate).
		if ( '8.6' === $branch ) {
			return version_compare( $version, '8.6.0RC4', '<' );
		}

		// Newer minor branches: no known threshold yet.
		if ( version_compare( $branch . '.0', '8.7.0', '>=' ) ) {
			return false;
		}

		// EOL branches have no upstream security release.
		if ( version_compare( $branch . '.0', '8.2.0', '<' ) ) {
			return true;
		}

		$minima = array(
			'8.5' => '8.5.12',
			'8.4' => '8.4.27',
			'8.3' => '8.3.36',
			'8.2' => '8.2.35',
		);

		if ( ! isset( $minima[ $branch ] ) ) {
			return true;
		}

		return version_compare( $version, $minima[ $branch ], '<' );
	}

	/**
	 * Extract major.minor from a PHP version string (e.g. 8.6.0RC3 → 8.6).
	 *
	 * @param string $version PHP version.
	 * @return string|null
	 */
	private static function php_major_minor( $version ) {
		if ( ! preg_match( '/^(\d+)\.(\d+)/', (string) $version, $m ) ) {
			return null;
		}
		return $m[1] . '.' . $m[2];
	}

	/**
	 * Minimum fixed Debian/Ubuntu package versions for PHP2Shell backports.
	 *
	 * @param array|null $os     os-release fields with lowercase 'id' and 'version_id'.
	 * @param string     $branch PHP major.minor.
	 * @return string|null Package version minimum, or null if unknown.
	 */
	private static function php2shell_min_distro_package( $os, $branch ) {
		if ( ! is_array( $os ) || empty( $os['id'] ) || empty( $os['version_id'] ) ) {
			return null;
		}

		$id         = strtolower( (string) $os['id'] );
		$version_id = (string) $os['version_id'];

		// id may be "ubuntu"; some derivatives set ID_LIKE — keep the table on ID only for now.
		$table = array(
			'ubuntu' => array(
				'26.10' => array( '8.5' => '8.5.9-0ubuntu2.1' ),
				'26.04' => array( '8.5' => '8.5.4-0ubuntu1.5' ),
				'24.04' => array( '8.3' => '8.3.6-0ubuntu0.24.04.13' ),
				'22.04' => array( '8.1' => '8.1.2-1ubuntu2.28' ),
			),
			'debian' => array(
				'13' => array( '8.4' => '8.4.27-1~deb13u1' ),
				'12' => array( '8.2' => '8.2.35-1~deb12u1' ),
			),
		);

		if ( ! isset( $table[ $id ][ $version_id ][ $branch ] ) ) {
			return null;
		}

		return $table[ $id ][ $version_id ][ $branch ];
	}

	/**
	 * Read /etc/os-release into a lowercase key map (id, version_id, …).
	 *
	 * @return array<string,string>|null
	 */
	private static function read_os_release() {
		$path = '/etc/os-release';
		if ( ! is_readable( $path ) ) {
			return null;
		}
		$raw = @file_get_contents( $path );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return null;
		}

		$out = array();
		foreach ( explode( "\n", $raw ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || '#' === $line[0] || false === strpos( $line, '=' ) ) {
				continue;
			}
			list( $key, $value ) = explode( '=', $line, 2 );
			$value               = trim( $value, " \t\"'" );
			$out[ strtolower( $key ) ] = $value;
		}

		return empty( $out ) ? null : $out;
	}

	/**
	 * Installed Debian package version for the running PHP major.minor, if any.
	 *
	 * Source-built runtimes (official PHP/WordPress images) typically have no
	 * matching phpX.Y-* package — returns empty string so callers fall back to PHP_VERSION.
	 *
	 * @param string $branch PHP major.minor.
	 * @return string Package version or "".
	 */
	private static function detect_debian_php_package_version( $branch ) {
		$packages = array(
			'php' . $branch . '-fpm',
			'php' . $branch . '-cli',
			'libapache2-mod-php' . $branch,
			'php' . $branch . '-cgi',
			'php' . $branch . '-common',
		);

		foreach ( $packages as $package ) {
			$version = self::read_dpkg_status_version( $package );
			if ( '' !== $version ) {
				return $version;
			}
		}

		return '';
	}

	/**
	 * Parse Version for a Package block from /var/lib/dpkg/status (no shell).
	 *
	 * @param string $package Package name.
	 * @return string Version or "".
	 */
	private static function read_dpkg_status_version( $package ) {
		$status = '/var/lib/dpkg/status';
		if ( ! is_readable( $status ) ) {
			return '';
		}

		$fh = @fopen( $status, 'rb' );
		if ( false === $fh ) {
			return '';
		}

		$in_package = false;
		$version    = '';
		$installed  = false;

		while ( false !== ( $line = fgets( $fh ) ) ) {
			if ( "\n" === $line || "\r\n" === $line ) {
				if ( $in_package && $installed && '' !== $version ) {
					fclose( $fh );
					return $version;
				}
				$in_package = false;
				$version    = '';
				$installed  = false;
				continue;
			}

			if ( 0 === strncmp( $line, 'Package: ', 9 ) ) {
				$in_package = ( trim( substr( $line, 9 ) ) === $package );
				$version    = '';
				$installed  = false;
				continue;
			}

			if ( ! $in_package ) {
				continue;
			}

			if ( 0 === strncmp( $line, 'Status: ', 8 ) ) {
				$installed = ( false !== strpos( $line, ' installed' ) );
			} elseif ( 0 === strncmp( $line, 'Version: ', 9 ) ) {
				$version = trim( substr( $line, 9 ) );
			}
		}

		fclose( $fh );

		if ( $in_package && $installed && '' !== $version ) {
			return $version;
		}

		return '';
	}

	/**
	 * Whether Debian package version $installed is greater than or equal to $minimum.
	 *
	 * Prefers `dpkg --compare-versions` when exec is available; otherwise a
	 * pragmatic fallback for the ubuntu0.* / debNu* forms we ship in the table.
	 *
	 * @param string $installed Installed package version.
	 * @param string $minimum   Minimum fixed package version.
	 * @return bool
	 */
	private static function debian_version_ge( $installed, $minimum ) {
		if ( $installed === $minimum ) {
			return true;
		}

		$disabled = array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) );
		if ( function_exists( 'exec' ) && ! in_array( 'exec', $disabled, true ) ) {
			$command = 'dpkg --compare-versions '
				. escapeshellarg( $installed ) . ' ge ' . escapeshellarg( $minimum ) . ' 2>/dev/null';
			$out  = array();
			$code = 1;
			@exec( $command, $out, $code );
			if ( 0 === $code || 1 === $code ) {
				return 0 === $code;
			}
		}

		return self::debian_version_ge_fallback( $installed, $minimum );
	}

	/**
	 * Best-effort Debian version compare without dpkg (epoch + upstream + revision).
	 *
	 * @param string $installed Installed version.
	 * @param string $minimum   Minimum version.
	 * @return bool
	 */
	private static function debian_version_ge_fallback( $installed, $minimum ) {
		$parse = static function ( $v ) {
			$epoch = 0;
			if ( preg_match( '/^(\d+):(.*)$/', $v, $m ) ) {
				$epoch = (int) $m[1];
				$v     = $m[2];
			}
			$upstream = $v;
			$revision = '0';
			if ( preg_match( '/^(.*)-([^-]+)$/', $v, $m ) ) {
				$upstream = $m[1];
				$revision = $m[2];
			}
			return array( $epoch, $upstream, $revision );
		};

		list( $e1, $u1, $r1 ) = $parse( $installed );
		list( $e2, $u2, $r2 ) = $parse( $minimum );

		if ( $e1 !== $e2 ) {
			return $e1 > $e2;
		}

		$upstream_cmp = version_compare( $u1, $u2 );
		if ( 0 !== $upstream_cmp ) {
			return $upstream_cmp > 0;
		}

		// Normalize ~ (Debian: sorts before everything) for version_compare.
		$norm = static function ( $r ) {
			return str_replace( '~', '-', $r );
		};

		return version_compare( $norm( $r1 ), $norm( $r2 ), '>=' );
	}

}
