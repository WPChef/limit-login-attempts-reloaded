<?php
/**
 * Lockout Recorder Service
 *
 * @package LimitLoginAttempts
 * @since 3.3.0
 */

namespace LLAR\Core;

use Exception;
use LLAR\Core\Utils\RiskLevelMath;
use LLAR\Core\Interfaces\CloudAppInterface;
use LLAR\Core\Interfaces\IntegrationLoginProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records failed login attempts and manages lockout state.
 */
class LockoutRecorder {

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
	 * @var WhitelistBlacklistChecker
	 */
	private $whitelist_checker;

	/**
	 * @var LockoutNotificationService
	 */
	private $notification_service;

	/**
	 * @var LockoutCleanupService
	 */
	private $cleanup_service;

	/**
	 * @var IntegrationLoginProvider
	 */
	private $integration_login_provider;

	/**
	 * @var CloudAppInterface|null
	 */
	private $cloud_app;

	/**
	 * @param IpAddressResolver            $ip_resolver                IP resolver.
	 * @param CloudAclService              $cloud_acl                  Cloud ACL service.
	 * @param WhitelistBlacklistChecker    $whitelist_checker          Whitelist/blacklist checker.
	 * @param LockoutNotificationService   $notification_service       Notification service.
	 * @param LockoutCleanupService        $cleanup_service            Cleanup service.
	 * @param IntegrationLoginProvider     $integration_login_provider Integration login provider.
	 * @param CloudAppInterface|null       $cloud_app                  Cloud app instance or null.
	 */
	public function __construct(
		IpAddressResolver $ip_resolver,
		CloudAclService $cloud_acl,
		WhitelistBlacklistChecker $whitelist_checker,
		LockoutNotificationService $notification_service,
		LockoutCleanupService $cleanup_service,
		IntegrationLoginProvider $integration_login_provider,
		$cloud_app = null
	) {
		$this->ip_resolver = $ip_resolver;
		$this->cloud_acl = $cloud_acl;
		$this->whitelist_checker = $whitelist_checker;
		$this->notification_service = $notification_service;
		$this->cleanup_service = $cleanup_service;
		$this->integration_login_provider = $integration_login_provider;
		$this->cloud_app = $cloud_app;
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
	 * Record one failed login attempt: Cloud lockout_check and/or local retries, lockout, notify.
	 *
	 * @param string $username Login username.
	 * @return void
	 */
	public function record_failed_login_attempt( $username ) {
		self::$failed_login_recorded_in_request = true;

		LoginFlowTransientStore::ensure_token();
		LoginFlowTransientStore::merge( array( 'login_attempts_left' => 0 ) );

		if ( $this->cloud_app && $response = $this->cloud_app->lockout_check(
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
				LoginFlowState::instance()->set_just_lockedout( true );

				$err = __( '<strong>ERROR</strong>: Too many failed login attempts.', 'limit-login-attempts-reloaded' );

				$time_left = ( ! empty( $response['time_left'] ) ) ? $response['time_left'] : 0;

				if ( $time_left > 60 ) {
					$time_left = ceil( $time_left / 60 );
					$err      .= ' ' . sprintf( _n( 'Please try again in %d hour.', 'Please try again in %d hours.', $time_left, 'limit-login-attempts-reloaded' ), $time_left );
				} else {
					$err .= ' ' . sprintf( _n( 'Please try again in %d minute.', 'Please try again in %d minutes.', $time_left, 'limit-login-attempts-reloaded' ), $time_left );
				}

				$this->cloud_app->add_error( $err );
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
				$this->cleanup_service->cleanup( $retries, null, $valid );

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
				LoginFlowState::instance()->set_just_lockedout( true );

				if ( ( isset( $retries[ $ip ] ) ? $retries[ $ip ] : 0 ) >= $retries_long ) {
					$lockouts[ $ip ] = time() + Config::get( 'long_duration' );
					unset( $retries[ $ip ] );
					unset( $valid[ $ip ] );
				} else {
					$lockouts[ $ip ] = time() + Config::get( 'lockout_duration' );
				}
			}

			$this->cleanup_service->cleanup( $retries, $lockouts, $valid );
			$this->notification_service->notify( $username );

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

		return $this->integration_login_provider->get_integration_login_identifier();
	}

	/**
	 * Cloud ACL lockout state for the current request.
	 *
	 * @param string $username Optional username from the auth hook.
	 * @return bool|null True when login is allowed, false when denied, null when local check applies.
	 * @throws Exception
	 */
	private function is_cloud_login_allowed( $username = '' ) {
		if ( ! $this->cloud_app ) {
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
	 * Calculate remaining retries.
	 *
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
	 * Get hash of string.
	 *
	 * @param string $str IP or other string.
	 * @return string
	 */
	private function get_hash( $str ) {
		return md5( $str );
	}

	/**
	 * Check array key value.
	 *
	 * @param array  $arr Array.
	 * @param string $k   Key.
	 * @return int
	 */
	private function check_key( $arr, $k ) {
		return isset( $arr[ $k ] ) ? $arr[ $k ] : 0;
	}
}
