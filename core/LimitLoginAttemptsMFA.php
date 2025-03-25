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
declare(strict_types=1);

namespace LLAR\Core;

// Exit if accessed directly outside of WordPress environment.
if (defined('ABSPATH') === false) {
    exit;
}

class Limit_Login_Attempts_MFA {
	/**
	 * Holds the single instance of the class and allowed roles for MFA.
	 *
	 * @var Limit_Login_Attempts_MFA|null $instance The single instance of the class.
	 * @var array $_allowed_roles Stores roles that are allowed to use MFA.
	 */
	private static $_instance = null;
	private $_allowed_roles   = array();
	
	/**
	 * Initializes the singleton instance of the class.
	 *
	 * Ensures that only one instance of this class is loaded or can be loaded.
	 *
	 * @return Limit_Login_Attempts_MFA The single instance of the class.
	 */
	public static function init() {
		if (self::$_instance === null) { 
			self::$_instance = new self();
		}
		return self::$_instance;
	}
	
	/**
	 * Clears MFA session data upon user logout.
	 *
	 * This function checks if an MFA session exists and removes associated transients 
	 * (mfa code and email status) when a user logs out of the WordPress site.
	 *
	 * @return void
	 */
	public function logout_clear_mfa_data() {
		// Start a session if it hasn't been started already.
		if (session_id() === '') {
			session_start();
		}

		// Check if the MFA session exists for the user.
        if (array_key_exists('mfa_user_id', $_SESSION) === true) {
            $user_id = (int) $_SESSION['mfa_user_id'];

            // Remove transients related to the user's MFA process.
            delete_transient('mfa_email_sent_' . $user_id);
            delete_transient('mfa_otp_' . $user_id);

            // Clear the user ID from the session.
            unset($_SESSION['mfa_user_id']);
        }
	}
	
	/**
	 * Registers plugin actions and loads configuration settings.
	 *
	 * This function sets up various WordPress hooks to handle:
	 * - Adding mfa scripts to the login form.
	 * - Handling AJAX requests for MFA verification.
	 * - Clearing MFA data upon logout.
	 * 
	 * @return void
	 */
	public function register() {
		// Load configuration settings for allowed MFA roles.
		$this->_load_config();
		
		// Add mfa scripts to the WordPress login form.
		add_action('login_form', array($this, 'print_mfa_html'), 99);

		// Register AJAX handlers for checking user role and triggering MFA.
		add_action('wp_ajax_check_mfa_role', array($this, 'check_mfa_role'));
		add_action('wp_ajax_nopriv_check_mfa_role', array($this, 'check_mfa_role'));
		add_action('wp_ajax_send_mfa_code', array($this, 'send_mfa_code'));
		add_action('wp_ajax_nopriv_send_mfa_code', array($this, 'send_mfa_code'));
		add_action('wp_ajax_verify_mfa_code', array($this, 'verify_mfa_code'));
		add_action('wp_ajax_nopriv_verify_mfa_code', array($this, 'verify_mfa_code'));

		// Clear MFA session data when a user logs out.
		add_action('wp_logout', array($this, 'logout_clear_mfa_data'));
		add_action('login_enqueue_scripts', array($this, 'add_mfa_scripts'));
	}

	/**
	 * Loads and parses the application configuration from the database.
	 *
	 * This method retrieves the configuration JSON string from the WordPress options table,
	 * decodes it, and checks for allowed roles to enable MFA (Multi-Factor Authentication).
	 * The resulting roles are stored in the $_allowed_roles property.
	 *
	 * @return void
	 */
	private function _load_config() {
		// Retrieve the app configuration JSON string from the database.
		$app_config_json = get_option('limit_login_app_config', '{}');

		// Ensure the configuration is a valid JSON string.
        if (is_string($app_config_json) === false) {
            if (is_array($app_config_json) === true || is_object($app_config_json) === true) {
                $app_config_json = wp_json_encode($app_config_json);
            } else {
                $app_config_json = '{}';
            }
        }

		// Decode the JSON string into an associative array.
		$app_config = json_decode($app_config_json, true);
        if (is_array($app_config) === false) { 
            $app_config = array(); 
        }

		// Initialize allowed roles.
		$this->_allowed_roles = array();

		// Check if MFA roles are specified and properly structured.
        if (empty($app_config['mfa_roles']) === false && is_array($app_config['mfa_roles']) === true) {
            foreach ($app_config['mfa_roles'] as $role => $status) {
                if ('off' !== $status) {
                    $this->_allowed_roles[$role] = true;
                }
            }
        }
	}

    /**
     * Checks if the user requires MFA or is whitelisted for bypass.
     *
     * @return void Sends JSON response indicating if MFA is required or user is logged in.
     */
    public function check_mfa_role() {
		$nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
		if (empty($nonce) && wp_verify_nonce($nonce, 'llar_mfa_nonce') === false) {
			wp_send_json_error(array('message' => __('Nonce verification failed.', 'limit-login-attempts-reloaded')));
		}

        $username = array_key_exists('username', $_POST) === TRUE ? sanitize_text_field(wp_unslash($_POST['username'])) : '';
        $password = array_key_exists('password', $_POST) === TRUE ? sanitize_text_field(wp_unslash($_POST['password'])) : '';

        if ($username === '' || $password === '') {
            wp_send_json_error(array('message' => __('Username and password are required.', 'limit-login-attempts-reloaded')));
        }

        $user = get_user_by('login', $username);

        if (wp_check_password($password, $user->user_pass, $user->ID) === false) {
            wp_send_json_error(array('message' => __('Invalid password.', 'limit-login-attempts-reloaded')));
        }

        // Check local whitelist
        $whitelisted_usernames = get_option('limit_login_whitelist_usernames', '');

		if (is_array($whitelisted_usernames) && in_array($username, $whitelisted_usernames, true)) {
            wp_set_auth_cookie($user_id, true);
            wp_set_current_user($user_id);
            do_action('wp_login', $username, $user);
            wp_send_json_success(array('message' => __('User is whitelisted and logged in successfully.', 'limit-login-attempts-reloaded')));
        }


		global $wpdb;

		$user_ip = '';
		if (function_exists('\LLAR\Core\Helpers::detect_ip_address')) {
			$user_ip = \LLAR\Core\Helpers::detect_ip_address(array());
		} elseif (isset($_SERVER['REMOTE_ADDR'])) {
			$user_ip = filter_var(wp_unslash($_SERVER['REMOTE_ADDR']), FILTER_VALIDATE_IP);
		}
		
		$lockouts = Config::get('lockouts');

		if (! is_array( $lockouts ) || isset( $lockouts[ $user_ip ] ) ) {
			wp_send_json_error(array('message' => __('You are temporarily locked out due to too many failed attempts.', 'limit-login-attempts-reloaded')));

		}		
        $whitelisted_ips = get_option('limit_login_whitelist');

        if (is_array($whitelisted_ips) && in_array($user_ip, $whitelisted_ips, true)) {
            wp_set_auth_cookie($user_id, true);
            wp_set_current_user($user_id);
            do_action('wp_login', $username, $user);
            wp_send_json_success(['message' => __('User IP is whitelisted and logged in successfully.', 'limit-login-attempts-reloaded')]);
        }

		$blacklisted_ip        = get_option('limit_login_blacklist', '');
		$blacklisted_usernames = get_option('limit_login_blacklist_usernames', '');

		if (is_array($blacklisted_ip) && in_array($user_ip, $blacklisted_ip, true)) {
			wp_send_json_error(array( 'message' => __('Your IP is blacklisted.', 'limit-login-attempts-reloaded') ));
		}

		if (is_array($blacklisted_usernames) && in_array($username, $blacklisted_usernames, true)) {
			wp_send_json_error(array( 'message' => __('This username is blacklisted.', 'limit-login-attempts-reloaded') ));
		}
		
        $user_roles = array_flip($user->roles);
		$app_config = get_option('limit_login_app_config', array());
		$mfa_mode = isset($app_config['mfa_mode']) ? $app_config['mfa_mode'] : ''; 

		if (array_intersect_key($this->_allowed_roles, $user_roles) === array()) {

			wp_send_json_success(array('requires_mfa' => false));
		} else {
			$user_id = $user->ID;

			if (session_id() === '') {
				session_start();
			}

			$app_config = get_option('limit_login_app_config', array());

			$user_roles = $user->roles;

			foreach ($user_roles as $role) {
				if (isset($app_config['mfa_roles'][$role])) {
					$mfa_mode = $app_config['mfa_roles'][$role];
					break;
				}
			}


			$limit_login_retries = get_option('limit_login_retries', array());
			$limit_login_retries_valid = get_option('limit_login_retries_valid', array());

			$has_failed_attempts = isset($limit_login_retries[$user_ip]);
			$has_lockout = isset($limit_login_retries_valid[$user_ip]);

			if ($mfa_mode === 'soft') {
				if ($has_failed_attempts || $has_lockout) {

					$_SESSION['mfa_user_id'] = $user_id;
					wp_send_json_success(array('requires_mfa' => true));
				} else {
					wp_set_auth_cookie($user_id, true);
					wp_set_current_user($user_id);
					do_action('wp_login', $username, $user);
					setcookie('isMfaVerified', 'true', time() + 600, "/", "", is_ssl(), true);
					setcookie('MfaVerified', 'true', time() + 600, "/", "", is_ssl(), true);
					wp_send_json_success(array('requires_mfa' => false));
				}
			} elseif ($mfa_mode === 'hard') {

				$_SESSION['mfa_user_id'] = $user_id;
				wp_send_json_success(array('requires_mfa' => true));
			} else {

				wp_send_json_error(array( 'message' => __('MFA mode not configured.', 'limit-login-attempts-reloaded') ));
			}
		}
    }


	/**
	 * Sends a One-Time Password (OTP) code to the user via email.
	 *
	 * This function checks if the user session is active, verifies the password,
	 * and generates an OTP code. The code is sent to the user's registered email.
	 * 
	 * @return void
	 */
	public function send_mfa_code() {

		if (array_key_exists('nonce', $_POST) === false && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) ) === false) {
			wp_send_json_error(array('message' => __('Nonce verification failed.', 'limit-login-attempts-reloaded')));
		}
		// Start a session if one hasn't already been started.
		if (session_id() === '') {
			session_start();
		}

		// Retrieve user ID from session.
		if (isset($_SESSION['mfa_user_id']) && !empty($_SESSION['mfa_user_id'])) {
			$user_id = (int) $_SESSION['mfa_user_id'];
		}
		if (isset($_POST['password']) && !empty($_POST['password'])) {
			$password = sanitize_text_field(wp_unslash($_POST['password']));
		}
        // Sanitize the provided password input.

		// Retrieve the user object by ID.
		$user = get_user_by('ID', $user_id);

		// Store the password securely in session and transient for subsequent verification.
		$_SESSION['mfa_user_password'] = $password;
		set_transient('mfa_last_user_password_' . $user_id, $password, (10 * MINUTE_IN_SECONDS));

		// Check if an OTP has already been sent recently.
		if (get_transient('mfa_email_sent_' . $user_id) !== false) {
			wp_send_json_error(array('message' => __('mfa code was already sent. Please check your email.', 'limit-login-attempts-reloaded')));
		}

		// Generate a new OTP code and store it in a transient for verification.
		$otp_code = wp_rand(100000, 999999);
		set_transient('mfa_otp_' . $user_id, $otp_code, (10 * MINUTE_IN_SECONDS));
		set_transient('mfa_email_sent_' . $user_id, true, (10 * MINUTE_IN_SECONDS));

		// Send the OTP code to the user's registered email address.
		$subject = __('Your mfa Code', 'limit-login-attempts-reloaded');
		// Translators: %s is the OTP code sent to the user.
		$message = sprintf(__('Your verification code: %s', 'limit-login-attempts-reloaded'), $otp_code);
		wp_mail($user->user_email, $subject, $message);

		// Respond with a success message.
		wp_send_json_success(array('message' => __('mfa code sent to your email.', 'limit-login-attempts-reloaded')));
	}

	/**
	 * Verifies if the user's MFA session is valid and retrieves user data.
	 *
	 * This function checks if a session or transient containing MFA data exists.
	 * It then attempts to restore the user session and prepares it for OTP validation.
	 *
	 * @return void
	 */
	public function verify_mfa_code() {
		if (array_key_exists('nonce', $_POST) === false && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'llar_mfa_nonce') === false) {
			wp_send_json_error(array('message' => __('Nonce verification failed.', 'limit-login-attempts-reloaded')));
		}
		// Start the session if it's not already started.
		if (session_id() === '') {
			session_start();
		}
		
		$user_id  = null;
		$password = null;

		// Check if the user session is set or retrieve from transients if missing.
		if (array_key_exists('mfa_user_id', $_SESSION) === false) {
			// Attempt to retrieve user data from transients.
			$user_id  = get_transient('mfa_last_user_id');
			$password = get_transient('mfa_last_user_password_' . $user_id);

			// If no valid user ID or password exists, return an error.
			if ($user_id === false || $password === false) {
				wp_send_json_error(array('message' => __('Session expired, please log in again.', 'limit-login-attempts-reloaded')));
			}
		} else {
			// Retrieve user ID and password from the active session.
			$user_id  = (int) $_SESSION['mfa_user_id'];
			$password = isset($_SESSION['mfa_user_password']) ? sanitize_text_field(wp_unslash($_SESSION['mfa_user_password'])) : get_transient('mfa_last_user_password_' . $user_id);
		}

		// Restore session variables for user authentication if necessary.
		$_SESSION['mfa_user_id']       = $user_id;
		$_SESSION['mfa_user_password'] = $password;

		// Attempt to retrieve the user by ID.
		$user = get_user_by('ID', $user_id);

		$saved_code    = get_transient('mfa_otp_' . $user_id);
		$provided_code = isset($_POST['mfa_code']) ? sanitize_text_field(wp_unslash($_POST['mfa_code'])) : ''; 
		$failed_attempts = get_user_meta($user_id, 'limit_login_failed_attempts', true);
		$failed_attempts = !empty($failed_attempts) ? (int) $failed_attempts : 0;
		$max_attempts    = (int) get_option('limit_login_allowed_retries');
		$lockout_time    = (int) get_option('limit_login_lockout_duration');
		$lockout_key     = 'limit_login_lockout_' . $user_id;
		$is_locked       = get_transient($lockout_key);

		if ($failed_attempts >= $max_attempts || $is_locked) {
			wp_send_json_error(array('message' => __('You have been temporarily locked out due to too many failed attempts.', 'limit-login-attempts-reloaded')));
		}

		if (false === $saved_code || '' === $provided_code || $saved_code !== $provided_code) {
			// Increment the failed attempt count.
			update_user_meta($user_id, 'limit_login_failed_attempts', ($failed_attempts + 1));

			if (function_exists('limit_login_failed_attempt')) {
				// Record the failed attempt for mfa verification.
				limit_login_failed_attempt($username, $ip);
			}
			if (($failed_attempts + 1) >= $max_attempts) {
				set_transient($lockout_key, true, $lockout_time);
				wp_send_json_error(array('message' => __('You have been temporarily locked out due to too many failed attempts.', 'limit-login-attempts-reloaded')));
			}

			// Calculate the remaining attempts allowed and return an error message.
			$remaining_attempts = max(0, ($max_attempts - ($failed_attempts + 1)));
			// Translators: %s 
			$_SESSION['login_error_message'] = sprintf(__('Incorrect or expired mfa code.','limit-login-attempts-reloaded'));
			wp_send_json_error(array('message' => sprintf(__('Incorrect or expired mfa code!', 'limit-login-attempts-reloaded'))));
		} else {
			wp_send_json_success(
				array(
					'message' => __('Verification successful! Redirecting...', 'limit-login-attempts-reloaded'),
				)
			);			
		}

		delete_user_meta($user_id, 'limit_login_failed_attempts'); 
		delete_transient('mfa_otp_' . $user_id); 
		delete_transient($lockout_key); 

		// Unset MFA-related session data.
		unset($_SESSION['mfa_user_id'], $_SESSION['mfa_user_password']);

		// Remove transient data related to the user session and password storage.
		delete_transient('mfa_last_user_id');
		delete_transient('mfa_last_user_password_' . $user_id);
	}


	/**
	 * Enqueue mfa-related scripts and localize data for use in JavaScript.
	 *
	 * This function properly enqueues the script file for handling mfa logic 
	 * and uses `wp_localize_script()` to pass necessary PHP data to JavaScript.
	 * @return void
	 */
	public function add_mfa_scripts() {
		if ('wp-login.php' !== $GLOBALS['pagenow']) {
			return; 
		}

		// Register the JavaScript file.
		wp_register_script(
			'limit-login-attempts-mfa', 
			plugin_dir_url(__FILE__) . '../assets/js/llar-mfa.js', 
			array('jquery'), 
			'1.0', 
			true 
		);

		// Localize script to pass PHP data to the JS file.
		wp_localize_script(
            'limit-login-attempts-mfa',
            'llar_mfa_data',
            array(
                'ajax_url'            => admin_url('admin-ajax.php'),
                'nonce'               => wp_create_nonce('llar_mfa_nonce'),
                'logged_out'          => isset($_GET['loggedout']),
                'lockout_time'        => get_option('limit_login_lockout_duration'),
                'mfa_resend_cooldown' => (int) get_option('mfa_resend_cooldown', 60),
			)
        );

		// Enqueue the JavaScript file.
		wp_enqueue_script('limit-login-attempts-mfa');

		// Register and enqueue the CSS file.
		wp_register_style(
			'llar-mfa-styles', 
			plugin_dir_url(__FILE__) . '../assets/css/llar-mfa-styles.css', 
			array(), 
			'1.0' 
		);
		
		wp_enqueue_style('llar-mfa-styles'); 
	}


	/**
	 * Adds MFA HTML block to the login form.
	 *
	 * @return void
	 */
	public function print_mfa_html() {
		$html = '
			<p id="mfa-actions" style="display:none;">
				<button type="button" id="send-mfa-code" class="button button-primary">' . esc_html__('Send Code', 'limit-login-attempts-reloaded') . '</button>
				<input type="text" id="mfa-code" class="input" placeholder="' . esc_attr__('Enter mfa code', 'limit-login-attempts-reloaded') . '" style="display:none;">
				<button type="button" id="verify-mfa-code" class="button button-primary" style="display:none;">' . esc_html__('Verify', 'limit-login-attempts-reloaded') . '</button>
				<span id="mfa-message"></span>
			</p>
		';
		echo wp_kses(
            $html,
            array(
                'p'      => array(
                    'id'    => array(),
                    'style' => array(),
                ),
                'button' => array(
                    'type'  => array(),
                    'id'    => array(),
                    'class' => array(),
                ),
                'input'  => array(
                    'type'        => array(),
                    'id'          => array(),
                    'class'       => array(),
                    'placeholder' => array(),
                    'style'       => array(),
                ),
                'span'   => array(
                    'id' => array(),
                ),
            )
        );
	}
}

add_filter('authenticate', function($user, $username, $password) {
		
		$lockouts = Config::get('lockouts');
		$ip = '';
		if (function_exists('\LLAR\Core\Helpers::detect_ip_address')) {
			$ip = \LLAR\Core\Helpers::detect_ip_address(array());
		} elseif (isset($_SERVER['REMOTE_ADDR'])) {
			$ip = filter_var(wp_unslash($_SERVER['REMOTE_ADDR']), FILTER_VALIDATE_IP);
		}
		
		if (! is_array( $lockouts ) || isset( $lockouts[ $ip ] ) ) {
			return new \WP_Error('blacklist_error', __('You are temporarily locked out due to too many failed attempts.', 'limit-login-attempts-reloaded'));
		}		
		$blacklisted_ip = get_option('limit_login_blacklist', array());
		if (is_array($blacklisted_ip) && in_array($ip, $blacklisted_ip, true)) {
			return new \WP_Error('blacklist_error', __('Your IP is blacklisted.', 'limit-login-attempts-reloaded'));
		}
		$blacklisted_usernames = get_option('limit_login_blacklist_usernames', array());
		if (is_array($blacklisted_usernames) && in_array($username, $blacklisted_usernames, true)) {
			return new \WP_Error('blacklist_error', __('This username is blacklisted.', 'limit-login-attempts-reloaded'));
		}

		$whitelisted_ips = get_option('limit_login_whitelist', array());
		if (is_array($whitelisted_ips) && in_array($ip, $whitelisted_ips, true)) {
			return $user;
		}

		$whitelisted_usernames = get_option('limit_login_whitelist_usernames', array());
		if (is_array($whitelisted_usernames) && in_array($username, $whitelisted_usernames, true)) {
			return $user; 
		}
		
    if (isset($_COOKIE['mfa_error'])) {
        setcookie('mfa_error', '', time() - 3600, '/');
        
        wp_logout();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
            $_SESSION = array();
        }
        
        return new \WP_Error('mfa_error', __('Invalid MFA code!', 'limit-login-attempts-reloaded'));
    }

    if (is_wp_error($user) || !$user instanceof \WP_User) {
        return $user;
    }

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
		$mfa_mode = isset($app_config['mfa_mode']) ? $app_config['mfa_mode'] : ''; 
        if ($mfa_mode === 'soft') {
            $limit_login_retries = get_option('limit_login_retries', array());
            $limit_login_retries_valid = get_option('limit_login_retries_valid', array());

            $has_failed_attempts = isset($limit_login_retries[$ip]);
            $has_lockout = isset($limit_login_retries_valid[$ip]);

            if (!$has_failed_attempts && !$has_lockout) {
				setcookie('MfaVerified', '', time() - 3600, '/'); 
				wp_set_auth_cookie($user->ID, true);
				wp_set_current_user($user->ID);
				do_action('wp_login', $username, $user);
			
                return $user;
            }
        }
        if (isset($_COOKIE['MfaVerified']) && $_COOKIE['MfaVerified'] === 'true') {
            setcookie('MfaVerified', '', time() - 3600, '/'); 
            wp_set_auth_cookie($user->ID, true);
            wp_set_current_user($user->ID);
            do_action('wp_login', $username, $user);
            
            return $user;
        } else {
            return new \WP_Error('mfa_error', __('MFA verification required. Please try again.', 'limit-login-attempts-reloaded'));
        }
    }

    return $user;

}, 100, 3);



add_filter('login_errors', function($error) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (isset($_SESSION['login_error_message'])) {
		$error .= '<br>' . sanitize_text_field($_SESSION['login_error_message']);
		unset($_SESSION['login_error_message']);
    }

    return $error;
});


Limit_Login_Attempts_MFA::init()->register();
