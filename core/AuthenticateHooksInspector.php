<?php

namespace LLAR\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Inspects foreign callbacks on the authenticate filter (debug tab).
 */
class AuthenticateHooksInspector {

	/**
	 * Request-scoped cache: hook callback -> reflection file path.
	 *
	 * @var array
	 */
	private static $hook_callback_source_file_cache = array();

	/**
	 * Request-scoped cache: normalized source file path -> plugin metadata.
	 *
	 * @var array
	 */
	private static $hook_source_file_plugin_cache = array();

	/**
	 * @return array
	 */
	public static function get_foreign_authenticate_hooks() {
		global $wp_filter;

		if ( empty( $wp_filter['authenticate'] ) || ! is_object( $wp_filter['authenticate'] ) || ! isset( $wp_filter['authenticate']->callbacks ) ) {
			return array();
		}

		$allowed_callbacks = array(
			'wp_authenticate_username_password',
			'wp_authenticate_email_password',
			'wp_authenticate_spam_check',
		);

		$foreign = array();
		foreach ( $wp_filter['authenticate']->callbacks as $priority => $callbacks ) {
			if ( ! is_array( $callbacks ) ) {
				continue;
			}

			foreach ( $callbacks as $callback_data ) {
				if ( empty( $callback_data['function'] ) ) {
					continue;
				}

				$callback_name = self::normalize_hook_callback_name( $callback_data['function'] );
				if ( '' === $callback_name ) {
					continue;
				}

				$is_llar = ( 0 === strpos( $callback_name, 'LLAR\\Core\\' ) );
				if ( $is_llar || in_array( $callback_name, $allowed_callbacks, true ) ) {
					continue;
				}

				$plugin_meta = self::detect_plugin_for_hook_callback( $callback_data['function'] );
				$hook_priority = (int) $priority;
				if ( empty( $plugin_meta ) && ! self::is_anomalous_authenticate_priority( $hook_priority ) ) {
					continue;
				}

				$foreign[] = array(
					'priority'      => $hook_priority,
					'callback'      => $callback_name,
					'accepted_args' => isset( $callback_data['accepted_args'] ) ? (int) $callback_data['accepted_args'] : 0,
					'plugin'        => $plugin_meta,
				);
			}
		}

		return $foreign;
	}

	/**
	 * @param int $priority Hook priority.
	 * @return bool
	 */
	private static function is_anomalous_authenticate_priority( $priority ) {
		$priority = (int) $priority;
		$min      = (int) apply_filters( 'llar_foreign_auth_hook_normal_priority_min', -10000 );
		$max      = (int) apply_filters( 'llar_foreign_auth_hook_normal_priority_max', 999 );
		if ( $priority < $min || $priority > $max ) {
			return true;
		}

		return (bool) apply_filters( 'llar_foreign_auth_hook_force_anomalous_priority', false, $priority );
	}

	/**
	 * @param mixed $callback Callback.
	 * @return string
	 */
	private static function normalize_hook_callback_name( $callback ) {
		if ( is_string( $callback ) ) {
			return $callback;
		}

		if ( is_array( $callback ) && 2 === count( $callback ) ) {
			if ( is_object( $callback[0] ) ) {
				return get_class( $callback[0] ) . '::' . $callback[1];
			}
			if ( is_string( $callback[0] ) ) {
				return $callback[0] . '::' . $callback[1];
			}
		}

		if ( $callback instanceof \Closure ) {
			return 'Closure';
		}

		return '';
	}

	/**
	 * @param mixed $callback Callback.
	 * @return array
	 */
	private static function detect_plugin_for_hook_callback( $callback ) {
		$source_file = self::get_hook_callback_source_file_cached( $callback );
		if ( '' === $source_file ) {
			return array();
		}

		$source_file = wp_normalize_path( $source_file );
		if ( isset( self::$hook_source_file_plugin_cache[ $source_file ] ) ) {
			return self::$hook_source_file_plugin_cache[ $source_file ];
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins     = get_plugins();
		$plugins_dir = trailingslashit( wp_normalize_path( WP_PLUGIN_DIR ) );

		if ( 0 !== strpos( $source_file, $plugins_dir ) ) {
			self::$hook_source_file_plugin_cache[ $source_file ] = array();
			return array();
		}

		$relative_file = ltrim( substr( $source_file, strlen( $plugins_dir ) ), '/' );
		$result        = array();

		foreach ( $plugins as $plugin_file => $plugin_data ) {
			$plugin_file = wp_normalize_path( $plugin_file );
			$plugin_dir  = dirname( $plugin_file );
			$is_main_file = ( $relative_file === $plugin_file );
			$is_inside_plugin_dir = ( '.' !== $plugin_dir && 0 === strpos( $relative_file, trailingslashit( $plugin_dir ) ) );

			if ( ! $is_main_file && ! $is_inside_plugin_dir ) {
				continue;
			}

			$slug = explode( '/', $plugin_file );
			$slug = sanitize_key( $slug[0] );

			$result = array(
				'slug'    => $slug,
				'name'    => isset( $plugin_data['Name'] ) ? $plugin_data['Name'] : '',
				'version' => isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : '',
				'file'    => $plugin_file,
			);
			break;
		}

		self::$hook_source_file_plugin_cache[ $source_file ] = $result;

		return $result;
	}

	/**
	 * @param mixed $callback Callback.
	 * @return string
	 */
	private static function get_hook_callback_cache_key( $callback ) {
		if ( is_string( $callback ) ) {
			return 's:' . $callback;
		}

		if ( is_array( $callback ) && 2 === count( $callback ) ) {
			if ( is_object( $callback[0] ) ) {
				return 'o:' . spl_object_hash( $callback[0] ) . ':' . (string) $callback[1];
			}
			if ( is_string( $callback[0] ) ) {
				return 'c:' . $callback[0] . '::' . (string) $callback[1];
			}
		}

		if ( $callback instanceof \Closure ) {
			return 'f:' . spl_object_hash( $callback );
		}

		return 'u:' . md5( serialize( $callback ) );
	}

	/**
	 * @param mixed $callback Callback.
	 * @return string
	 */
	private static function get_hook_callback_source_file_cached( $callback ) {
		$key = self::get_hook_callback_cache_key( $callback );
		if ( isset( self::$hook_callback_source_file_cache[ $key ] ) ) {
			return self::$hook_callback_source_file_cache[ $key ];
		}

		$file = self::get_hook_callback_source_file( $callback );
		self::$hook_callback_source_file_cache[ $key ] = $file;

		return $file;
	}

	/**
	 * @param mixed $callback Callback.
	 * @return string
	 */
	private static function get_hook_callback_source_file( $callback ) {
		try {
			if ( is_string( $callback ) && function_exists( $callback ) ) {
				$reflection = new \ReflectionFunction( $callback );
				return (string) $reflection->getFileName();
			}

			if ( is_array( $callback ) && 2 === count( $callback ) ) {
				$object_or_class = $callback[0];
				$method          = $callback[1];

				if ( is_object( $object_or_class ) && method_exists( $object_or_class, $method ) ) {
					$reflection = new \ReflectionMethod( $object_or_class, $method );
					return (string) $reflection->getFileName();
				}

				if ( is_string( $object_or_class ) && method_exists( $object_or_class, $method ) ) {
					$reflection = new \ReflectionMethod( $object_or_class, $method );
					return (string) $reflection->getFileName();
				}
			}

			if ( $callback instanceof \Closure ) {
				$reflection = new \ReflectionFunction( $callback );
				return (string) $reflection->getFileName();
			}
		} catch ( \Exception $e ) {
			return '';
		}

		return '';
	}
}
