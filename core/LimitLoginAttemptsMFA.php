<?php
/**
 * Class Limit_Login_Attempts_MFA
 *
 * This class handles the implementation of Multi-Factor Authentication (MFA)
 * for the Limit Login Attempts plugin. It manages configuration loading,
 * role-based restrictions, session handling, and interaction with WordPress hooks.
 *
 * @category Authentication
 * @package LLAR\Core
 * @author  Limit Login Attempts Reloaded <support@limitloginattempts.com>
 * @license https://opensource.org/licenses/MIT MIT License
 * @link    https://github.com/WPChef/limit-login-attempts-reloaded
 * PHP Version: 5.6
 */
 
namespace LLAR\Core;

if (defined('ABSPATH') === false) {
    exit;
}

add_filter('authenticate', function ($user, $username, $password) {
	unset($_SESSION['mfa_is_bot']);
	$lockouts = Config::get('lockouts');
    $user_ip = '';
    if (function_exists('\LLAR\Core\Helpers::detect_ip_address')) {
        $user_ip = \LLAR\Core\Helpers::detect_ip_address(array());
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $user_ip = filter_var(wp_unslash($_SERVER['REMOTE_ADDR']), FILTER_VALIDATE_IP);
    }
    $blacklisted_ip = get_option('limit_login_blacklist', '');
    $blacklisted_usernames = get_option('limit_login_blacklist_usernames', '');
	$retries = get_option( 'limit_login_retries', array() );
	
    if (is_array($blacklisted_ip) && in_array($user_ip, $blacklisted_ip, true)) {
        return new \WP_Error('mfa_error', __('Your IP is blacklisted.', 'limit-login-attempts-reloaded'));
    }

    if (is_array($blacklisted_usernames) && in_array($username, $blacklisted_usernames, true)) {
        return new \WP_Error('mfa_error', __('<strong>ERROR</strong>: Too many failed login attempts.', 'limit-login-attempts-reloaded'));
    }
    $whitelisted_usernames = get_option('limit_login_whitelist_usernames', '');
	$whitelisted_ips = get_option('limit_login_whitelist');

    if (session_id() === '') {
        session_start();
    }

    if (isset($_SESSION['mfa_verified_user']) && $_SESSION['mfa_verified_user'] === $username) {
        return $user;
    }
	
    if (isset($_SESSION['mfa_error']) && isset($_SESSION['mfa_refferform']) && $_SESSION['mfa_refferform'] === true) {
        $error = new \WP_Error('mfa_error', sanitize_text_field($_SESSION['mfa_error']));
        unset($_SESSION['mfa_error']);
        return $error;
    }
	
	if ( is_wp_error( $user ) ) {
		if ( $user->get_error_code() === 'incorrect_password' ) {
			$allow_mfa_on_invalid_password = false;

			if ( isset( $username ) ) {
				$user_data = get_user_by( 'login', $username );
				
				if ( $user_data instanceof \WP_User ) {
					$required_mfa_roles = get_option( 'limit_login_app_config', array() );

					if ( isset( $required_mfa_roles['mfa_roles'] ) && is_array( $required_mfa_roles['mfa_roles'] ) ) {
						$limit_login_retries = get_option('limit_login_retries', array());
						$limit_login_retries_valid = get_option('limit_login_retries_valid', array());

						$has_failed_attempts = isset($limit_login_retries[$user_ip]);
						$has_lockout = isset($limit_login_retries_valid[$user_ip]);

						foreach ( $user_data->roles as $role ) {
							if (
								isset( $required_mfa_roles['mfa_roles'][ $role ] )
								&& 'soft' === $required_mfa_roles['mfa_roles'][ $role ]
							) {
								if ($has_failed_attempts || $has_lockout) {
									$allow_mfa_on_invalid_password = true;
									break;
								} else {
									$allow_mfa_on_invalid_password = false;
									break;
								}
								
							}
							if (
								isset( $required_mfa_roles['mfa_roles'][ $role ] )
								&& 'off' !== $required_mfa_roles['mfa_roles'][ $role ]
							) {
								$allow_mfa_on_invalid_password = true;
								break;
							}
							
						}
					}
				}
			}
			if (is_array($whitelisted_usernames) && in_array($username, $whitelisted_usernames, true) || is_array($whitelisted_ips) && in_array($user_ip, $whitelisted_ips, true)) {
				return new \WP_Error('mfa_error', __('<strong>Error:</strong> The password you entered for the username <strong>member-deny-name</strong> is incorrect. <a href="?action=lostpassword">Lost your password?</a>', 'limit-login-attempts-reloaded'));
				if ( class_exists( '\LLAR\Core\LimitLoginAttempts' ) && \LLAR\Core\LimitLoginAttempts::$instance ) {
					if ( isset( $_SESSION['mfa_user_login'] ) ) {
						\LLAR\Core\LimitLoginAttempts::$instance->limit_login_failed( sanitize_user( $_SESSION['mfa_user_login'] ) );
						$_SESSION['mfa_suppress_failed_hook'] = true;
					}
				}
			} else {
				if ( true === $allow_mfa_on_invalid_password ) {
					$_SESSION['mfa_user_login'] = $username;
					$_SESSION['mfa_wrong_pwd']  = true;
					$_SESSION['mfa_user_id'] = $user_data->ID;
					wp_redirect( site_url( '/wp-login.php?action=mfa_send_code' ) );
					exit;
				}
			}
		}
	}

	if ( is_wp_error( $user ) ) {
        if (!is_array($lockouts) || isset($lockouts[$user_ip])) {
            return new \WP_Error('mfa_error', __('You are temporarily locked out due to too many failed attempts.', 'limit-login-attempts-reloaded'));
        }
		$required_mfa_roles = get_option( 'limit_login_app_config', array() );
		$has_mfa_roles = false;

		if (
			isset( $required_mfa_roles['mfa_roles'] ) 
			&& is_array( $required_mfa_roles['mfa_roles'] )
		) {
			foreach ( $required_mfa_roles['mfa_roles'] as $role => $mode ) {
				if ( $mode !== 'off' ) {
					$has_mfa_roles = true;
					break;
				}
			}
		}
		if ( $user->get_error_code() === 'invalid_username' && $has_mfa_roles === true ) {
			$_SESSION['mfa_user_login'] = $username;
			$_SESSION['mfa_is_bot'] = true;
			wp_redirect( site_url( '/wp-login.php?action=mfa_send_code' ) );
			exit;
		}

		return $user;
	}

    if ($user instanceof \WP_User) {

        $user_id = $user->ID;
        $user_email = $user->user_email;

        if (is_array($whitelisted_usernames) && in_array($username, $whitelisted_usernames, true)) {
            return $user;
        }

        if (is_array($whitelisted_ips) && in_array($user_ip, $whitelisted_ips, true)) {
			return $user;
        }
		
        global $wpdb;
		
        $limit_login_retries = get_option('limit_login_retries', array());
        $limit_login_retries_valid = get_option('limit_login_retries_valid', array());

        $has_failed_attempts = isset($limit_login_retries[$user_ip]);
        $has_lockout = isset($limit_login_retries_valid[$user_ip]);

        $user_roles = $user->roles;
        $required_mfa_roles = get_option('limit_login_app_config', array());
        $requires_mfa = false;

        if (isset($required_mfa_roles['mfa_roles']) && is_array($required_mfa_roles['mfa_roles'])) {
            foreach ($user_roles as $role) {
                if (isset($required_mfa_roles['mfa_roles'][$role]) && $required_mfa_roles['mfa_roles'][$role] !== 'off') {
                    $requires_mfa = true;
                    break;
                }
            }
        }

        if (isset($required_mfa_roles['mfa_roles']) && is_array($required_mfa_roles['mfa_roles'])) {
            foreach ($user_roles as $role) {
                if (isset($required_mfa_roles['mfa_roles'][$role])) {
                    $mfa_mode = $required_mfa_roles['mfa_roles'][$role];
                    break;
                }
            }
        }

        if ($requires_mfa) {
            $_SESSION['mfa_user_id'] = $user_id;
            $_SESSION['mfa_user_login'] = $username;

			if ( is_wp_error( $user ) && $user->get_error_code() === 'incorrect_password' ) {
				$_SESSION['mfa_password_invalid'] = true;
				wp_redirect(site_url('/wp-login.php?action=mfa_send_code'));
				exit;
			}
            if ($mfa_mode === 'soft') {
                if ($has_failed_attempts || $has_lockout) {

                    $_SESSION['mfa_user_id'] = $user_id;
                    wp_redirect(site_url('/wp-login.php?action=mfa_send_code'));
					exit;
                } else {
					return $user;
                }
            } elseif ($mfa_mode === 'hard') {
                $_SESSION['mfa_user_id'] = $user_id;
                wp_redirect(site_url('/wp-login.php?action=mfa_send_code'));
				exit;
            } else {
                return new \WP_Error('mfa_error', __('MFA mode not configured.', 'limit-login-attempts-reloaded'));
            }
            wp_redirect(site_url('/wp-login.php?action=mfa_send_code'));
            exit;
        }

    }

    return $user;

}, 100, 3);

add_action( 'login_form_mfa_send_code', function () {

	if ( session_id() === '' ) {
		session_start();
	}

	if ( isset( $_SESSION['mfa_is_bot'] ) && true === $_SESSION['mfa_is_bot'] ) {
		
		if (
			isset( $_POST['mfa_send_code_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mfa_send_code_nonce'] ) ), 'send_mfa_code_nonce' )
		) {
			wp_redirect( site_url( '/wp-login.php?action=mfa_required' ) );
			exit;
		}
	}
	if ( empty( $_SESSION['mfa_user_login'] ) ) {
		wp_redirect( site_url( '/wp-login.php' ) );
		exit;
	}

	$username = sanitize_user( $_SESSION['mfa_user_login'] );
	$user     = get_user_by( 'login', $username );

	if ( ! $user instanceof \WP_User ) {
		include plugin_dir_path( __DIR__ ) . 'views/mfa-send-code-form.php';
		exit;
	}

	$required_mfa_roles = get_option( 'limit_login_app_config', array() );
	$has_mfa            = false;

	if ( isset( $required_mfa_roles['mfa_roles'] ) && is_array( $required_mfa_roles['mfa_roles'] ) ) {
		foreach ( $user->roles as $role ) {
			if ( isset( $required_mfa_roles['mfa_roles'][ $role ] ) && $required_mfa_roles['mfa_roles'][ $role ] !== 'off' ) {
				$has_mfa = true;
				break;
			}
		}
	}

	if ( ! $has_mfa ) {
		wp_redirect( site_url( '/wp-login.php' ) );
		exit;
	}

	if (
		isset( $_POST['mfa_send_code_nonce'] )
		&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mfa_send_code_nonce'] ) ), 'send_mfa_code_nonce' )
	) {
		llar_send_mfa_code( $user );
		wp_redirect( site_url( '/wp-login.php?action=mfa_required' ) );
		exit;
	}

	include plugin_dir_path( __DIR__ ) . 'views/mfa-send-code-form.php';
	exit;
} );


function llar_send_mfa_code( $user ) {

	if ( ! $user instanceof \WP_User ) {
		$_SESSION['mfa_error'] = __( 'Invalid user.', 'limit-login-attempts-reloaded' );
		return false;
	}

	$user_id    = $user->ID;
	$user_email = $user->user_email;
	$expires_at = get_option( '_transient_timeout_llar_mfa_otp_' . $user_id );
	$existing_code = get_transient( 'llar_mfa_otp_' . $user_id );


	if ( $existing_code ) {

		if ( $expires_at ) {
			$seconds_left = intval( $expires_at ) - time();
			if ( $seconds_left > 0 ) {
				$minutes_left = ceil( $seconds_left / 60 );
				// Translators: %s is the 6-digit MFA verification code sent to the user.
				$_SESSION['mfa_error'] = sprintf( __( 'You can request a new code in %d minute(s).', 'limit-login-attempts-reloaded' ), $minutes_left );
				return false;
			}
		}
	}

	$otp_code = wp_rand( 100000, 999999 );
	set_transient( 'llar_mfa_otp_' . $user_id, $otp_code, 10 * MINUTE_IN_SECONDS );
	$subject = __( 'Your MFA Code', 'limit-login-attempts-reloaded' );
	// Translators: %d is the number of minutes remaining before a new code can be requested.
	$message = sprintf( __( 'Your MFA verification code is: %s. This code is valid for 10 minutes.', 'limit-login-attempts-reloaded' ), $otp_code );

	$mail_result = wp_mail( $user_email, $subject, $message );

	if ( ! $mail_result ) {
		$_SESSION['mfa_error'] = __( 'Failed to send the MFA code. Please try again later.', 'limit-login-attempts-reloaded' );
		return false;
	}

	return true;
}


add_action('login_form_mfa_required', function () {
    if (session_id() === '') {
        session_start();
    }

	if ( isset( $_SESSION['mfa_is_bot'] ) && true === $_SESSION['mfa_is_bot'] ) {
		include plugin_dir_path( __DIR__ ) . 'views/mfa-form.php';
		exit;
	}

    if (!isset($_SESSION['mfa_user_id'])) {
        wp_redirect(site_url('/wp-login.php'));
        exit;
    }

	if ( isset( $_POST['mfa_nonce'], $_POST['mfa_code'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mfa_nonce'] ) ), 'mfa_form_nonce' ) ) {
			$submitted_code = sanitize_text_field( wp_unslash( $_POST['mfa_code'] ) );
			$user_id        = intval( $_SESSION['mfa_user_id'] );
			$transient_key  = 'llar_mfa_otp_' . $user_id;
			$expires_at     = get_option( '_transient_timeout_' . $transient_key );
			$saved_code     = get_transient( $transient_key );
			$current_time   = time();
        if ($submitted_code === $saved_code) {
			delete_transient( $transient_key );
			if( isset( $_SESSION['mfa_wrong_pwd'] ) && $_SESSION['mfa_wrong_pwd'] === true){
				$_SESSION['mfa_wrong_pwd'] = false;
				$_SESSION['mfa_error'] = __( 'Invalid password.', 'limit-login-attempts-reloaded' );
				if ( class_exists( '\LLAR\Core\LimitLoginAttempts' ) && \LLAR\Core\LimitLoginAttempts::$instance ) {
					if ( isset( $_SESSION['mfa_user_login'] ) ) {
						\LLAR\Core\LimitLoginAttempts::$instance->limit_login_failed( sanitize_user( $_SESSION['mfa_user_login'] ) );
						$_SESSION['mfa_suppress_failed_hook'] = true;
					}
				}
				$_SESSION['mfa_refferform'] = true;
				wp_redirect(site_url('/wp-login.php'));
				exit;
			} else {
				wp_set_auth_cookie( $user_id, true );
				wp_set_current_user( $user_id );
				if ( isset( $_SESSION['mfa_user_login'] ) ) {
					$user_login = sanitize_user( $_SESSION['mfa_user_login'] );
					$user       = get_user_by( 'login', $user_login );
					do_action( 'wp_login', $user_login, $user );
				}
			}
            unset($_SESSION['mfa_user_id']);
            unset($_SESSION['mfa_user_login']);
            wp_redirect(site_url('wp-admin'));
            exit;

        } else {
			if ( $saved_code === false ) {
				if ( $expires_at && $expires_at < $current_time ) {
					$_SESSION['mfa_error'] = __( 'The code is expired, please request a new one.', 'limit-login-attempts-reloaded' );
					$_SESSION['mfa_suppress_failed_hook'] = true;
					$_SESSION['nonempty_credentials'] = false;
				} else {
					$_SESSION['mfa_error'] = __( 'No MFA code was found. Please request a new one.', 'limit-login-attempts-reloaded' );
					if ( class_exists( '\LLAR\Core\LimitLoginAttempts' ) && \LLAR\Core\LimitLoginAttempts::$instance ) {
						if ( isset( $_SESSION['mfa_user_login'] ) ) {
							\LLAR\Core\LimitLoginAttempts::$instance->limit_login_failed( sanitize_user( $_SESSION['mfa_user_login'] ) );
							$_SESSION['mfa_suppress_failed_hook'] = true;
						}
					}
				}
			} elseif ( $submitted_code !== $saved_code ) {

				$_SESSION['mfa_error'] = __( 'Invalid MFA code. Please try again.', 'limit-login-attempts-reloaded' );
				delete_transient( 'llar_mfa_otp_' . $user_id );
				
				if ( class_exists( '\LLAR\Core\LimitLoginAttempts' ) && \LLAR\Core\LimitLoginAttempts::$instance ) {
					if ( isset( $_SESSION['mfa_user_login'] ) ) {
						\LLAR\Core\LimitLoginAttempts::$instance->limit_login_failed( sanitize_user( $_SESSION['mfa_user_login'] ) );
						$_SESSION['mfa_suppress_failed_hook'] = true;
					}
				}
				
			}
			$_SESSION['mfa_refferform'] = true;
			wp_redirect( site_url( 'wp-login.php' ) );
			exit;
        }
    }
	
	include plugin_dir_path( __DIR__ ) . 'views/mfa-form.php';
    exit;
});
add_action( 'wp_logout', function () {
    if ( session_id() === '' ) {
        session_start();
    }
    unset( $_SESSION['errors_in_early_hook'] );
    unset( $_SESSION['mfa_user_login'] );
    unset( $_SESSION['mfa_wrong_pwd'] );
    unset( $_SESSION['mfa_refferform'] );
    unset( $_SESSION['mfa_error'] );
    unset( $_SESSION['mfa_suppress_failed_hook'] );
});