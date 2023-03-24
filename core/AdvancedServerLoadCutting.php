<?php

namespace LLAR\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdvancedServerLoadCutting {

	const PROXY_FILE_NAME = 'load-proxy.php';

	/**
	 * @return bool[]
	 * @throws \Exception
	 */
	public static function compatibility_checks() {
		$checklist = array(
			'root_folder_writable' => false,
			'wp_config_writable' => false,
			'proxy_file_writable' => false,
			'fopen_available' => false,
			'curl_available' => false,
		);

		if( Helpers::is_writable( ABSPATH ) ) {
			$checklist['root_folder_writable'] = true;
		}

		if( Helpers::is_writable( self::get_wp_config_path() ) ) {
			$checklist['wp_config_writable'] = true;
		}

		if( Helpers::is_writable( self::get_proxy_file_path() ) || Helpers::is_writable( WP_CONTENT_DIR ) ) {
			$checklist['proxy_file_writable'] = true;
		}

		if( function_exists( 'fopen' ) && ini_get( 'allow_url_fopen' ) === '1' ) {
			$checklist['fopen_available'] = true;
		} elseif( function_exists( 'curl_version' ) ) {
			$checklist['curl_available'] = true;
		}

		return $checklist;
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public static function is_checks_passed() {
		$checks = self::compatibility_checks();
		$is_http_transport_available = $checks['fopen_available'] || $checks['curl_available'];

		unset($checks['fopen_available']);
		unset($checks['curl_available']);

		return !in_array( false, $checks, true ) && $is_http_transport_available;
	}

	/**
	 * @return string
	 */
	public static function get_proxy_file_path() {
		return WP_CONTENT_DIR . DIRECTORY_SEPARATOR . self::PROXY_FILE_NAME;
	}

	/**
	 * @return string
	 */
	public static function get_include_file_line() {
		return 'if( file_exists( "' . self::get_proxy_file_path() . '" ) ) include_once( "' . self::get_proxy_file_path() . '" );';
	}

	/**
	 * @return string
	 * @throws \Exception
	 */
	public static function get_wp_config_path() {
		if( file_exists( ABSPATH . 'wp-config.php' ) ) {
			return ABSPATH . 'wp-config.php';
		} elseif( file_exists( dirname( ABSPATH ) . '/wp-config.php' ) ) {
			return dirname( ABSPATH ) . '/wp-config.php';
		}

		throw new \Exception( __( 'Unable to locate wp-config.php file', 'limit-login-attempts-reloaded' ) );
	}

	public static function form_handler() {

		if( !self::is_checks_passed() ) {
			throw new \Exception( __( 'Compatibility checks failed.', 'limit-login-attempts-reloaded' ) );
		}

		$is_enabled = !empty( $_POST['load_proxy_enabled'] );

		if( $is_enabled ) {
			self::create_proxy_file();
		} else {
			self::remove_proxy_file();
		}

		self::patch_wp_config( $is_enabled );

		Config::update( 'load_proxy_enabled', $is_enabled );
	}

	public static function create_proxy_file() {
		@file_put_contents( self::get_proxy_file_path(), self::generate_proxy_file_content() );
	}

	private static function remove_proxy_file() {
		if( file_exists( self::get_proxy_file_path() ) )
			@unlink( self::get_proxy_file_path() );
	}

	/**
	 * @return string
	 */
	public static function generate_proxy_file_content() {
		$proxy_content = "<?php\n";

		if( Config::get( 'active_app' ) === 'custom' && $config = Config::get( 'app_config' ) ) {
			$proxy_config = array(
				'key'       => $config['key'],
				'header'    => $config['header'],
				'api'       => $config['api'],
				'settings'  => $config['settings'],
			);
		} else {
			$proxy_config =  array(
				'trusted_ip_origins' => Config::get( 'trusted_ip_origins' ),
				'acl' => array(
					'whitelist_ips'         => Config::get( 'whitelist' ),
					'whitelist_usernames'   => Config::get( 'whitelist_usernames' ),
					'blacklist_ips'         => Config::get( 'blacklist' ),
					'blacklist_usernames'   => Config::get( 'blacklist_usernames' ),
				)
			);
		}

		$proxy_content .= "\$proxy_config = '" . json_encode( $proxy_config, JSON_FORCE_OBJECT ) . "';";
		$proxy_content .= "\ninclude_once(\"" . str_replace( '\\', '/', LLA_PLUGIN_DIR ) .  "load-proxy-handler.php\");";

		return $proxy_content;
	}

	/**
	 * @param false $include
	 *
	 * @throws \Exception
	 */
	private static function patch_wp_config( $include = false ) {
		$wp_config_path = self::get_wp_config_path();

		if( !file_exists( $wp_config_path ) ) {
			throw new \Exception( __( 'Unable to locate wp-config.php file', 'limit-login-attempts-reloaded' ) );
		}

		if( !Helpers::is_writable( $wp_config_path ) ) {
			throw new \Exception( __( 'Unable to update wp-config.php. Fix the file and folder permissions.', 'limit-login-attempts-reloaded' ) );
		}

		$wp_config_content = file_get_contents( $wp_config_path );

		$pattern = '~[\/\/\#]*if\(\s*file_exists\((.*?load\-proxy\.php.*?)\)\s*\)\s*include_once\(\1\);\R~is';

		$new_wp_config_content = preg_replace( $pattern, '', $wp_config_content, 1 );

		if( $include ) {
			$new_wp_config_content = preg_replace( '~<\?php~', "\\0\r\n" . str_replace( '\\', '/', self::get_include_file_line() ), $new_wp_config_content, 1 );
		}

		if( $wp_config_content !== $new_wp_config_content ) {

			if( !self::backup_wp_config( $wp_config_path ) ) {
				throw new \Exception( 'Unable to update wp-config.php. Fix the file and folder permissions.' );
			}

			@file_put_contents( $wp_config_path, $new_wp_config_content );
		}
	}

	/**
	 * @param $wp_config_path
	 *
	 * @return bool
	 */
	private static function backup_wp_config( $wp_config_path ) {
		$backup_wp_config_path = str_replace( 'wp-config', 'wp-config-llar-backup-' . time(), $wp_config_path );

		if( !copy( $wp_config_path, $backup_wp_config_path ) ) {
			return false;
		}

		$wp_content_origin_content = file_get_contents( $wp_config_path );
		$wp_content_backup_content = file_get_contents( $backup_wp_config_path );

		return strlen( $wp_content_origin_content ) === strlen( $wp_content_backup_content );
	}
}
