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

    if (session_id() === '') {
        session_start();
    }

    if (isset($_SESSION['mfa_verified_user']) && $_SESSION['mfa_verified_user'] === $username) {
        return $user;
    }

    if (is_wp_error($user) && empty($_SESSION['mfa_user_id'])) {
        return $user;
    }
    if (isset($_SESSION['mfa_error'])) {
        $error = new \WP_Error('mfa_error', sanitize_text_field($_SESSION['mfa_error']));
        unset($_SESSION['mfa_error']);
        return $error;
    }


    if ($user instanceof \WP_User) {

        $user_id = $user->ID;
        $user_email = $user->user_email;
		
        $user_ip = '';
        if (function_exists('\LLAR\Core\Helpers::detect_ip_address')) {
            $user_ip = \LLAR\Core\Helpers::detect_ip_address(array());
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $user_ip = filter_var(wp_unslash($_SERVER['REMOTE_ADDR']), FILTER_VALIDATE_IP);
        }
		
        $whitelisted_usernames = get_option('limit_login_whitelist_usernames', '');
        if (is_array($whitelisted_usernames) && in_array($username, $whitelisted_usernames, true)) {
            return $user;
        }
		
        $whitelisted_ips = get_option('limit_login_whitelist');

        if (is_array($whitelisted_ips) && in_array($user_ip, $whitelisted_ips, true)) {
			return $user;
        }
		
        global $wpdb;

        $blacklisted_ip = get_option('limit_login_blacklist', '');
        $blacklisted_usernames = get_option('limit_login_blacklist_usernames', '');

        if (is_array($blacklisted_ip) && in_array($user_ip, $blacklisted_ip, true)) {
            return new \WP_Error('mfa_error', __('Your IP is blacklisted.', 'limit-login-attempts-reloaded'));
        }

        if (is_array($blacklisted_usernames) && in_array($username, $blacklisted_usernames, true)) {
            return new \WP_Error('mfa_error', __('This username is blacklisted.', 'limit-login-attempts-reloaded'));
        }

        $lockouts = Config::get('lockouts');

        if (!is_array($lockouts) || isset($lockouts[$user_ip])) {
            return new \WP_Error('mfa_error', __('You are temporarily locked out due to too many failed attempts.', 'limit-login-attempts-reloaded'));
        }
		
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
            $_SESSION['mfa_user_password'] = $password;
            $_SESSION['mfa_user'] = $user;
            if (!get_transient('mfa_otp_' . $user_id)) {
                $otp_code = wp_rand(100000, 999999);
                set_transient('mfa_otp_' . $user_id, $otp_code, 10 * MINUTE_IN_SECONDS);

                $subject = __('Your MFA Code', 'limit-login-attempts-reloaded');
				// translators: %s is the MFA verification code.
                $message = sprintf( __( 'Your MFA verification code is: %s. This code is valid for 10 minutes.', 'limit-login-attempts-reloaded' ), $otp_code );

				wp_mail( $user_email, $subject, $message );

            }
            if ($mfa_mode === 'soft') {
                if ($has_failed_attempts || $has_lockout) {

                    $_SESSION['mfa_user_id'] = $user_id;
                    wp_redirect(site_url('/wp-login.php?action=mfa_required'));
					exit;
                } else {
					return $user;
                }
            } elseif ($mfa_mode === 'hard') {
                $_SESSION['mfa_user_id'] = $user_id;
                wp_redirect(site_url('/wp-login.php?action=mfa_required'));
				exit;
            } else {
                return new \WP_Error('mfa_error', __('MFA mode not configured.', 'limit-login-attempts-reloaded'));
            }
            wp_redirect(site_url('/wp-login.php?action=mfa_required'));
            exit;
        }

    }

    return $user;

}, 100, 3);

add_action('login_form_mfa_required', function () {
    if (session_id() === '') {
        session_start();
    }

    if (!isset($_SESSION['mfa_user_id'])) {
        wp_redirect(site_url('/wp-login.php'));
        exit;
    }

	if ( isset( $_POST['mfa_nonce'], $_POST['mfa_code'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mfa_nonce'] ) ), 'mfa_form_nonce' ) ) {
		$submitted_code = sanitize_text_field( wp_unslash( $_POST['mfa_code'] ) );
        $saved_code = get_transient( 'mfa_otp_' . intval( $_SESSION['mfa_user_id'] ) );

        if ($submitted_code === $saved_code) {
			delete_transient( 'mfa_otp_' . intval( $_SESSION['mfa_user_id'] ) );
			wp_set_auth_cookie( intval( $_SESSION['mfa_user_id'] ), true );
			wp_set_current_user( intval( $_SESSION['mfa_user_id'] ) );
			if ( isset( $_SESSION['mfa_user_login'] ) ) {
				$user_login = sanitize_user( $_SESSION['mfa_user_login'] );
				$user       = get_user_by( 'login', $user_login );
				do_action( 'wp_login', $user_login, $user );
			}
            wp_redirect(site_url('wp-admin'));
            unset($_SESSION['mfa_user_id']);
            unset($_SESSION['mfa_user_login']);
            unset($_SESSION['mfa_user_password']);
            unset($_SESSION['mfa_user']);
            exit;

        } else {

			delete_transient( 'mfa_otp_' . intval( $_SESSION['mfa_user_id'] ) );

			if ( isset( $_SESSION['mfa_user_login'] ) ) {
				do_action( 'wp_login_failed', sanitize_user( $_SESSION['mfa_user_login'] ) );
			}

            $_SESSION['mfa_error'] = __('Invalid MFA code. Please try again.', 'limit-login-attempts-reloaded');
            wp_redirect(site_url('wp-login.php'));
            exit;
        }
    }
	include plugin_dir_path( __DIR__ ) . 'views/mfa-form.php';
    exit;
});

add_filter('login_message', function ($message) {
    if (isset($_COOKIE['mfa_error'])) {
		$message .= '<div id="login_error" class="notice notice-error"><span><strong>ERROR</strong>: ' . sanitize_text_field( wp_unslash( $_COOKIE['mfa_error'] ) ) . '</span></div>';
        setcookie('mfa_error', '', time() - 3600, '/');
    }
    return $message;
});