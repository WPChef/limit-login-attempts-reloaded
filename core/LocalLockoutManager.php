<?php

namespace LLAR\Core;

use Exception;
use LLAR\Core\Utils\RiskLevelMath;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Local lockout recording, ACL/local allow checks, notifications, and list filters.
 */
class LocalLockoutManager {

	/**
	 * Guard: failed login already recorded in this request.
	 *
	 * @var bool
	 */
	private static $failed_login_recorded_in_request = false;

	/**
	 * @var IpAddressResolver
	 */
	private $ip_resolver;

	/**
	 * @var CloudAclService
	 */
	private $cloud_acl;

	/**
	 * @var LimitLoginAttempts
	 */
	private $plugin;

	/**
	 * @param IpAddressResolver  $ip_resolver IP resolution and whitelist/blacklist filters.
	 * @param CloudAclService    $cloud_acl   Cloud ACL cache.
	 * @param LimitLoginAttempts $plugin      Plugin facade.
	 */
	public function __construct( IpAddressResolver $ip_resolver, CloudAclService $cloud_acl, LimitLoginAttempts $plugin ) {
		$this->ip_resolver = $ip_resolver;
		$this->cloud_acl   = $cloud_acl;
		$this->plugin      = $plugin;
	}

	/**
	 * Reset per-request failed-login guard (persistent runtimes).
	 *
	 * @return void
	 */
	public static function reset_failed_login_recorded_in_request() {
		self::$failed_login_recorded_in_request = false;
	}

	/**
	 * Whether a failed login was already recorded in this request.
	 *
	 * @return bool
	 */
	public static function is_failed_login_recorded_in_request() {
		return self::$failed_login_recorded_in_request;
	}

	/**
	 * Get failed login attempts count for the last 24 hours in local mode.
	 *
	 * @return int
	 */
	public function get_local_retries_count_for_last_day() {
		$retries_count = 0;
		$retries_stats = Config::get( 'retries_stats' );

		if ( $retries_stats ) {
			$cutoff_ts = time() - DAY_IN_SECONDS;
			foreach ( $retries_stats as $key => $count ) {
				if ( is_numeric( $key ) && (int) $key > $cutoff_ts ) {
					$retries_count += $count;
				} elseif ( ! is_numeric( $key ) && date_i18n( 'Y-m-d' ) === $key ) {
					$retries_count += $count;
				}
			}
		}

		return (int) $retries_count;
	}

	/**
	 * @param bool   $allow Ignored.
	 * @param string $ip    IP address.
	 * @return bool
	 */
	public function check_whitelist_ips( $allow, $ip ) {
		return Helpers::ip_in_range( $ip, (array) Config::get( 'whitelist' ) );
	}

	/**
	 * @param bool   $allow    Ignored.
	 * @param string $username Username.
	 * @return bool
	 */
	public function check_whitelist_usernames( $allow, $username ) {
		return in_array( $username, (array) Config::get( 'whitelist_usernames' ) );
	}

	/**
	 * @param bool   $allow Ignored.
	 * @param string $ip    IP address.
	 * @return bool
	 */
	public function check_blacklist_ips( $allow, $ip ) {
		return Helpers::ip_in_range( $ip, (array) Config::get( 'blacklist' ) );
	}

	/**
	 * @param bool   $allow    Ignored.
	 * @param string $username Username.
	 * @return bool
	 */
	public function check_blacklist_usernames( $allow, $username ) {
		return in_array( $username, (array) Config::get( 'blacklist_usernames' ) );
	}

	/**
	 * Check if it is ok to login.
	 *
	 * @param string $username Optional username from the auth hook.
	 * @return bool
	 * @throws Exception
	 */
	public function is_limit_login_ok( $username = '' ) {
		$ip = $this->ip_resolver->get_address();

		if ( $this->ip_resolver->is_ip_whitelisted( $ip ) ) {
			return true;
		}

		$cloud_allowed = $this->is_cloud_login_allowed( $username );
		if ( null !== $cloud_allowed ) {
			return $cloud_allowed;
		}

		$lockouts = Config::get( Config::OPTION_LOCKOUTS );

		return ( ! is_array( $lockouts ) || ! isset( $lockouts[ $ip ] ) || time() >= $lockouts[ $ip ] );
	}

	/**
	 * Action when login attempt failed.
	 *
	 * @param string $username Login username.
	 * @return void
	 */
	public function limit_login_failed( $username ) {
		// @temporary WP 7.0 compat — prevent double-recording when late authenticate fallback already fired.
		// TODO: Remove after WP 7.1 release or when auth flow is stable.
		if ( self::$failed_login_recorded_in_request ) {
			return;
		}

		$this->record_failed_login_attempt( $username );
	}

	/**
	 * Handle notification in event of lockout.
	 *
	 * @param mixed $user Username string or user object.
	 * @return bool|void
	 */
	public function notify( $user ) {
		if ( is_object( $user ) ) {
			return false;
		}

		$this->notify_log( $user );

		$args = explode( ',', Config::get( 'lockout_notify' ) );

		if ( empty( $args ) ) {
			return;
		}

		if ( in_array( 'email', $args ) ) {
			$this->notify_email( $user );
		}
	}

	/**
	 * Email notification of lockout to admin (if configured).
	 *
	 * @param string $user Login username.
	 * @return void
	 */
	public function notify_email( $user ) {
		$ip      = $this->ip_resolver->get_address();
		$retries = Config::get( 'retries' );

		if ( ! is_array( $retries ) ) {
			$retries = array();
		}

		if (
			isset( $retries[ $ip ] )
			&& ( ( (int) $retries[ $ip ] / Config::get( 'allowed_retries' ) ) % Config::get( 'notify_email_after' ) ) != 0
		) {
			return;
		}

		if ( ! isset( $retries[ $ip ] ) ) {
			$count    = Config::get( 'allowed_retries' ) * Config::get( 'allowed_lockouts' );
			$lockouts = Config::get( 'allowed_lockouts' );
			$time     = round( Config::get( 'long_duration' ) / 3600 );
			$when     = sprintf( _n( '%d hour', '%d hours', $time, 'limit-login-attempts-reloaded' ), $time );
		} else {
			$count    = $retries[ $ip ];
			$lockouts = floor( ( $count ) / Config::get( 'allowed_retries' ) );
			$time     = round( Config::get( 'lockout_duration' ) / 60 );
			$when     = sprintf( _n( '%d minute', '%d minutes', $time, 'limit-login-attempts-reloaded' ), $time );
		}

		if ( $custom_admin_email = Config::get( 'admin_notify_email' ) ) {
			$admin_email = $custom_admin_email;
		} else {
			$admin_email = get_site_option( 'admin_email' );
		}

		$admin_name = '';

		global $wpdb;

		$res = $wpdb->get_col(
			$wpdb->prepare(
				"
                SELECT u.display_name
                FROM $wpdb->users AS u
                LEFT JOIN $wpdb->usermeta AS m ON u.ID = m.user_id
                WHERE u.user_email = %s
                AND m.meta_key LIKE 'wp_capabilities'
                AND m.meta_value LIKE '%administrator%'",
				$admin_email
			)
		);

		if ( $res ) {
			$admin_name = $res[0];
		}

		$site_domain = str_replace( array( 'http://', 'https://' ), '', home_url() );
		$blogname    = Helpers::use_local_options() ? get_option( 'blogname' ) : get_site_option( 'site_name' );
		$blogname    = htmlspecialchars_decode( $blogname, ENT_QUOTES );

		$plugin_data = get_plugin_data( LLA_PLUGIN_DIR . 'limit-login-attempts-reloaded.php' );

		$subject = sprintf(
			__( 'Failed login by IP %1$s %2$s', 'limit-login-attempts-reloaded' ),
			esc_html( $ip ),
			esc_html( $site_domain )
		);

		ob_start();
		include LLA_PLUGIN_DIR . 'views/emails/failed-login.php';
		$email_body = ob_get_clean();

		$current_url_label = preg_replace( '/^\/|\/$/', '', $_SERVER['REQUEST_URI'] );
		$current_url       = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : get_site_url() . $_SERVER['REQUEST_URI'];

		$placeholders = array(
			'{name}'              => $admin_name,
			'{domain}'            => $site_domain,
			'{attempts_count}'    => $count,
			'{lockouts_count}'    => $lockouts,
			'{ip_address}'        => esc_html( $ip ),
			'{ip_address_link}'   => esc_url( 'https://www.limitloginattempts.com/location/?ip=' . $ip ),
			'{username}'          => $user,
			'{blocked_duration}'  => $when,
			'{dashboard_url}'     => $this->plugin->get_options_page_uri(),
			'{premium_url}'       => 'https://www.limitloginattempts.com/info.php?from=plugin-lockout-email&v=' . $plugin_data['Version'],
			'{llar_url}'          => 'https://www.limitloginattempts.com/?from=plugin-lockout-email&v=' . $plugin_data['Version'],
			'{unsubscribe_url}'   => $this->plugin->get_options_page_uri( 'settings' ),
			'{current_url}'       => $current_url,
			'{current_url_label}' => $current_url_label,
		);

		$email_body = str_replace(
			array_keys( $placeholders ),
			array_values( $placeholders ),
			$email_body
		);

		Helpers::send_mail_with_logo( $admin_email, $subject, $email_body );
	}

	/**
	 * Logging of lockout (if configured).
	 *
	 * @param string $user_login Login username.
	 * @return void
	 */
	public function notify_log( $user_login ) {
		if ( ! $user_login ) {
			return;
		}

		$log    = $option = Config::get( Config::OPTION_LOGGED );
		$log    = is_array( $log ) ? $log : array();
		$ip     = $this->ip_resolver->get_address();

		if ( ! isset( $log[ $ip ] ) ) {
			$log[ $ip ] = array();
		}

		if ( ! isset( $log[ $ip ][ $user_login ] ) ) {
			$log[ $ip ][ $user_login ] = array( 'counter' => 0 );
		} elseif ( ! is_array( $log[ $ip ][ $user_login ] ) ) {
			$log[ $ip ][ $user_login ] = array( 'counter' => $log[ $ip ][ $user_login ] );
		}

		$log[ $ip ][ $user_login ]['counter']++;
		$log[ $ip ][ $user_login ]['date']    = time();
		$log[ $ip ][ $user_login ]['gateway'] = Helpers::detect_gateway();

		if ( $option === false ) {
			Config::add( 'logged', $log );
		} else {
			Config::update( Config::OPTION_LOGGED, $log );
		}
	}

	/**
	 * Check if username is whitelisted.
	 *
	 * @param string $username Username.
	 * @return bool
	 */
	public function is_username_whitelisted( $username ) {
		if ( empty( $username ) ) {
			return false;
		}

		$whitelisted = apply_filters( 'limit_login_whitelist_usernames', false, $username );

		return ( $whitelisted === true );
	}

	/**
	 * Check if username is blacklisted.
	 *
	 * @param string $username Username.
	 * @return bool
	 */
	public function is_username_blacklisted( $username ) {
		if ( empty( $username ) ) {
			return false;
		}

		$blacklisted = apply_filters( 'limit_login_blacklist_usernames', false, $username );

		return ( $blacklisted === true );
	}

	/**
	 * Clean up old lockouts and retries, and save supplied arrays.
	 *
	 * @param array|null $retries  Retries array or null to load from config.
	 * @param array|null $lockouts Lockouts array or null to load from config.
	 * @param array|null $valid    Valid-until array or null to load from config.
	 * @return void
	 */
	public function cleanup( $retries = null, $lockouts = null, $valid = null ) {
		$now      = time();
		$lockouts = ! is_null( $lockouts ) ? $lockouts : Config::get( Config::OPTION_LOCKOUTS );
		$log      = Config::get( Config::OPTION_LOGGED );

		if ( is_array( $lockouts ) ) {
			foreach ( $lockouts as $ip => $lockout ) {
				if ( $lockout < $now ) {
					unset( $lockouts[ $ip ] );

					if ( is_array( $log ) && isset( $log[ $ip ] ) ) {
						foreach ( $log[ $ip ] as $user_login => &$data ) {
							if ( ! is_array( $data ) ) {
								$data = array();
							}
							$data['unlocked'] = true;
						}
					}
				}
			}
			Config::update( Config::OPTION_LOCKOUTS, $lockouts );
		}

		Config::update( Config::OPTION_LOGGED, $log );

		$valid   = ! is_null( $valid ) ? $valid : Config::get( 'retries_valid' );
		$retries = ! is_null( $retries ) ? $retries : Config::get( 'retries' );

		if ( ! is_array( $valid ) || ! is_array( $retries ) ) {
			return;
		}

		foreach ( $valid as $ip => $lockout ) {
			if ( $lockout < $now ) {
				unset( $valid[ $ip ] );
				unset( $retries[ $ip ] );
			}
		}

		foreach ( $retries as $ip => $retry ) {
			if ( ! isset( $valid[ $ip ] ) ) {
				unset( $retries[ $ip ] );
			}
		}

		$retries_stats = Config::get( 'retries_stats' );

		if ( $retries_stats ) {
			$stats_cutoff = strtotime( '-8 day' );
			foreach ( $retries_stats as $key => $count ) {
				if ( RiskLevelMath::is_retries_stats_bucket_expired( $key, $stats_cutoff ) ) {
					unset( $retries_stats[ $key ] );
				}
			}

			Config::update( 'retries_stats', $retries_stats );
		}

		Config::update( 'retries', $retries );
		Config::update( 'retries_valid', $valid );
	}

	/**
	 * Record one failed login attempt: Cloud lockout_check and/or local retries, lockout, notify.
	 *
	 * @param string $username Login username.
	 * @return void
	 */
	public function record_failed_login_attempt( $username ) {
		self::$failed_login_recorded_in_request = true;

		LoginFlowTransientStore::ensure_token();
		LoginFlowTransientStore::merge( array( 'login_attempts_left' => 0 ) );

		if ( LimitLoginAttempts::$cloud_app && $response = LimitLoginAttempts::$cloud_app->lockout_check(
			array(
				'ip'      => Helpers::get_all_ips(),
				'login'   => $username,
				'gateway' => Helpers::detect_gateway(),
			)
		) ) {

			if ( $response['result'] === 'allow' ) {
				LoginFlowTransientStore::merge(
					array(
						'login_attempts_left' => (int) $response['attempts_left'],
					)
				);
			} elseif ( $response['result'] === 'deny' ) {
				global $limit_login_just_lockedout;
				$limit_login_just_lockedout = true;

				$err = __( '<strong>ERROR</strong>: Too many failed login attempts.', 'limit-login-attempts-reloaded' );

				$time_left = ( ! empty( $response['time_left'] ) ) ? $response['time_left'] : 0;

				if ( $time_left > 60 ) {
					$time_left = ceil( $time_left / 60 );
					$err      .= ' ' . sprintf( _n( 'Please try again in %d hour.', 'Please try again in %d hours.', $time_left, 'limit-login-attempts-reloaded' ), $time_left );
				} else {
					$err .= ' ' . sprintf( _n( 'Please try again in %d minute.', 'Please try again in %d minutes.', $time_left, 'limit-login-attempts-reloaded' ), $time_left );
				}

				LimitLoginAttempts::$cloud_app->add_error( $err );
				LoginFlowTransientStore::merge( array( 'errors_in_early_hook' => false ) );
			}
		} else {
			$ip       = $this->ip_resolver->get_address();
			$lockouts = Config::get( Config::OPTION_LOCKOUTS );

			if ( ! is_array( $lockouts ) ) {
				$lockouts = array();
			}

			if ( isset( $lockouts[ $ip ] ) && time() < $lockouts[ $ip ] ) {
				return;
			}

			$retries       = Config::get( 'retries' );
			$valid         = Config::get( 'retries_valid' );
			$retries_stats = Config::get( 'retries_stats' );

			if ( ! is_array( $retries ) ) {
				$retries = array();
				Config::add( 'retries', $retries );
			}

			if ( ! is_array( $valid ) ) {
				$valid = array();
				Config::add( 'retries_valid', $valid );
			}

			if ( ! is_array( $retries_stats ) ) {
				$retries_stats = array();
				Config::add( 'retries_stats', $retries_stats );
			}

			$date_key = strtotime( date( 'Y-m-d H:00:00' ) );
			if ( ! empty( $retries_stats[ $date_key ] ) ) {
				$retries_stats[ $date_key ]++;
			} else {
				$retries_stats[ $date_key ] = 1;
			}
			$retries_stats = RiskLevelMath::prune_retries_stats_old_buckets( $retries_stats );
			Config::update( 'retries_stats', $retries_stats );

			if ( isset( $retries[ $ip ] ) && isset( $valid[ $ip ] ) && time() < $valid[ $ip ] ) {
				$retries[ $ip ]++;
			} else {
				$retries[ $ip ] = 1;
			}
			$valid[ $ip ] = time() + Config::get( 'valid_duration' );

			if ( $retries[ $ip ] % Config::get( 'allowed_retries' ) != 0 ) {
				$this->cleanup( $retries, null, $valid );

				LoginFlowTransientStore::merge(
					array(
						'login_attempts_left' => $this->calculate_retries_remaining(),
					)
				);

				return;
			}

			$whitelisted  = $this->ip_resolver->is_ip_whitelisted( $ip );
			$retries_long = Config::get( 'allowed_retries' ) * Config::get( 'allowed_lockouts' );

			if ( $whitelisted ) {
				if ( $retries[ $ip ] >= $retries_long ) {
					unset( $retries[ $ip ] );
					unset( $valid[ $ip ] );
				}
			} else {
				global $limit_login_just_lockedout;
				$limit_login_just_lockedout = true;

				if ( ( isset( $retries[ $ip ] ) ? $retries[ $ip ] : 0 ) >= $retries_long ) {
					$lockouts[ $ip ] = time() + Config::get( 'long_duration' );
					unset( $retries[ $ip ] );
					unset( $valid[ $ip ] );
				} else {
					$lockouts[ $ip ] = time() + Config::get( 'lockout_duration' );
				}
			}

			$this->cleanup( $retries, $lockouts, $valid );
			$this->notify( $username );

			$total = Config::get( 'lockouts_total' );
			if ( $total === false || ! is_numeric( $total ) ) {
				Config::add( 'lockouts_total', 1 );
			} else {
				Config::update( 'lockouts_total', $total + 1 );
			}
		}
	}

	/**
	 * Resolve login identifier for cloud ACL checks.
	 *
	 * @param string $username Optional username from the auth hook.
	 * @return string
	 */
	private function resolve_login_username( $username = '' ) {
		if ( '' !== $username ) {
			return $username;
		}

		if ( isset( $_REQUEST['log'] ) ) {
			return sanitize_text_field( wp_unslash( $_REQUEST['log'] ) );
		}

		if ( method_exists( $this->plugin, 'get_integration_login_identifier' ) ) {
			return $this->plugin->get_integration_login_identifier();
		}

		return '';
	}

	/**
	 * Cloud ACL lockout state for the current request.
	 *
	 * @param string $username Optional username from the auth hook.
	 * @return bool|null True when login is allowed, false when denied, null when local check applies.
	 * @throws Exception
	 */
	private function is_cloud_login_allowed( $username = '' ) {
		if ( ! LimitLoginAttempts::$cloud_app ) {
			return null;
		}

		$username = $this->resolve_login_username( $username );
		if ( '' === $username ) {
			return null;
		}

		$response = $this->cloud_acl->get_auth_acl_response( $username );
		if ( ! $response ) {
			return null;
		}

		return ( 'deny' !== $response['result'] );
	}

	/**
	 * @return int
	 */
	private function calculate_retries_remaining() {
		$remaining = 0;

		$ip      = $this->ip_resolver->get_address();
		$retries = Config::get( 'retries' );
		$valid   = Config::get( 'retries_valid' );
		$a       = $this->check_key( $retries, $ip );
		$b       = $this->check_key( $retries, $this->get_hash( $ip ) );
		$c       = $this->check_key( $valid, $ip );
		$d       = $this->check_key( $valid, $this->get_hash( $ip ) );

		if ( ! is_array( $retries ) || ! is_array( $valid ) ) {
			return $remaining;
		}
		if (
			( ! isset( $retries[ $ip ] ) && ! isset( $retries[ $this->get_hash( $ip ) ] ) )
			|| ( ! isset( $valid[ $ip ] ) && ! isset( $valid[ $this->get_hash( $ip ) ] ) )
			|| ( time() > $c && time() > $d )
		) {
			return $remaining;
		}
		if (
			( $a % Config::get( 'allowed_retries' ) ) == 0
			&& ( $b % Config::get( 'allowed_retries' ) ) == 0
		) {
			return $remaining;
		}

		$remaining = max( ( Config::get( 'allowed_retries' ) - ( ( $a + $b ) % Config::get( 'allowed_retries' ) ) ), 0 );

		return (int) $remaining;
	}

	/**
	 * @param string $str IP or other string.
	 * @return string
	 */
	public function get_hash( $str ) {
		return md5( $str );
	}

	/**
	 * @param array  $arr Array.
	 * @param string $k   Key.
	 * @return int
	 */
	public function check_key( $arr, $k ) {
		return isset( $arr[ $k ] ) ? $arr[ $k ] : 0;
	}
}
