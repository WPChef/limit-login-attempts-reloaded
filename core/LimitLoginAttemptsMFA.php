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
        if ($user === false) {
            wp_send_json_error(array('message' => __('User not found.', 'limit-login-attempts-reloaded')));
        }

        $user_id = $user->ID;

        // Check if the provided password is correct
        if (wp_check_password($password, $user->user_pass, $user_id) === false) {
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

        // Check API whitelist
        if (isset(LimitLoginAttempts::$cloud_app) === true) {
            $cloud_app = LimitLoginAttempts::$cloud_app;
            if (method_exists($cloud_app, 'request') === true) {
                $api_whitelist_usernames = $cloud_app->request('acl', 'get', array('type' => 'whitelist'));
                if (is_array($api_whitelist_usernames['items']) === true && empty($api_whitelist_usernames['items']) === false) {
                    foreach ($api_whitelist_usernames['items'] as $rule) {
                        if ($username === $rule['pattern'] && $rule['rule'] === 'allow') {
                            wp_set_auth_cookie($user_id, true);
                            wp_set_current_user($user_id);
                            do_action('wp_login', $username, $user);
                            wp_send_json_success(array('message' => __('User is whitelisted via API and logged in successfully.', 'limit-login-attempts-reloaded')));
                        }
                    }
                }
            }
        }


		$user_ip = '';
		if (function_exists('\LLAR\Core\Helpers::detect_ip_address')) {
			$user_ip = \LLAR\Core\Helpers::detect_ip_address(array());
		} elseif (isset($_SERVER['REMOTE_ADDR'])) {
			$user_ip = filter_var(wp_unslash($_SERVER['REMOTE_ADDR']), FILTER_VALIDATE_IP);
		}
		
		
        $whitelisted_ips = get_option('limit_login_whitelist');

        if (is_array($whitelisted_ips) && in_array($user_ip, $whitelisted_ips, true)) {
            wp_set_auth_cookie($user_id, true);
            wp_set_current_user($user_id);
            do_action('wp_login', $username, $user);
            wp_send_json_success(['message' => __('User IP is whitelisted and logged in successfully.', 'limit-login-attempts-reloaded')]);
        }

        if (isset(LimitLoginAttempts::$cloud_app) === true) {
            $cloud_app = LimitLoginAttempts::$cloud_app;
            if (method_exists($cloud_app, 'request')) {
				$api_whitelist_ips = $cloud_app->request('acl', 'get', array('type' => 'ip'));
				if (is_array($api_whitelist_ips['items']) && !empty($api_whitelist_ips['items'])) {
					foreach ($api_whitelist_ips['items'] as $rule) {
						if ($user_ip === $rule['pattern'] && $rule['rule'] === 'allow') {
							wp_set_auth_cookie($user_id, true);
							wp_set_current_user($user_id);
							do_action('wp_login', $username, $user);
							wp_send_json_success(['message' => __('User IP is whitelisted via API and logged in successfully.', 'limit-login-attempts-reloaded')]);
						}
					}
				}
            }
        }
		
        $user_roles = array_flip($user->roles);
        if (array_intersect_key($this->_allowed_roles, $user_roles) === array()) {
            wp_send_json_success(array('requires_mfa' => false));
        } else {
            if (session_id() === '') {
                session_start();
            }
            $_SESSION['mfa_user_id'] = $user_id;
            wp_send_json_success(array('requires_mfa' => true));
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

		// Verify if user ID exists in session and password is provided in the request.
		if (array_key_exists('mfa_user_id', $_SESSION) === false || array_key_exists('password', $_POST) === false) {
			wp_send_json_error(array('message' => __('Session expired or missing data, please log in again.', 'limit-login-attempts-reloaded')));
		}

		// Retrieve user ID from session.
		$user_id  = (int) $_SESSION['mfa_user_id'];
		$password = sanitize_text_field(wp_unslash($_POST['password']));
        // Sanitize the provided password input.

		// Retrieve the user object by ID.
		$user = get_user_by('ID', $user_id);
		if ($user === false) {
			wp_send_json_error(array('message' => __('User not found.', 'limit-login-attempts-reloaded')));
		}

		// Verify the provided password against the user's stored password hash.
		if (wp_check_password($password, $user->user_pass, $user_id) === false) {
			wp_send_json_error(array('message' => __('Incorrect password.', 'limit-login-attempts-reloaded')));
		}

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
		if ($user === false) {
			wp_send_json_error(array('message' => __('User not found.', 'limit-login-attempts-reloaded')));
		}

		global $wpdb;

		$ip = '';
		if (function_exists('\LLAR\Core\Helpers::detect_ip_address')) {
			$ip = \LLAR\Core\Helpers::detect_ip_address(array());
		} elseif (isset($_SERVER['REMOTE_ADDR'])) {
			$ip = filter_var(wp_unslash($_SERVER['REMOTE_ADDR']), FILTER_VALIDATE_IP);
		}

		$username = isset($_POST['username']) ? sanitize_text_field(wp_unslash($_POST['username'])) : '';
		if (empty($username) && is_user_logged_in() === true) {
			$current_user = wp_get_current_user();
			$username     = $current_user->user_login;
		}

		$blacklisted_ip        = get_option('limit_login_blacklisted_ips', '');
		$blacklisted_usernames = get_option('limit_login_blacklist_usernames', '');

		if (is_array($blacklisted_ip) && in_array($ip, $blacklisted_ip, true)) {
			wp_send_json_error(array( 'message' => __('Your IP is blacklisted.', 'limit-login-attempts-reloaded') ));
		}

		if (is_array($blacklisted_usernames) && in_array($username, $blacklisted_usernames, true)) {
			wp_send_json_error(array( 'message' => __('This username is blacklisted.', 'limit-login-attempts-reloaded') ));
		}

		if (class_exists('\LLAR\Core\CloudApp') === true && isset(\LLAR\Core\LimitLoginAttempts::$cloud_app) === true) {
			$cloud_app = \LLAR\Core\LimitLoginAttempts::$cloud_app;

			if (method_exists($cloud_app, 'request') === true) {
				// Check IP blacklist via API
				$api_blacklist_ip = $cloud_app->request('acl', 'get', array( 'type' => 'ip' ));
				if (!empty($api_blacklist_ip['items']) === true) {
					foreach ( $api_blacklist_ip['items'] as $rule ) {
						if ($rule['pattern'] === $ip && $rule['rule'] === 'deny') {
							wp_send_json_error(array( 'message' => __('Your IP is blacklisted.', 'limit-login-attempts-reloaded') ));
						}
					}
				}

				// Check Username blacklist via API
				$api_blacklist_usernames = $cloud_app->request('acl', 'get', array( 'type' => 'login' ));
				if (! empty($api_blacklist_usernames['items']) ) {
					foreach ( $api_blacklist_usernames['items'] as $rule ) {
						if ($rule['pattern'] === $username && $rule['rule'] === 'deny' ) {
							wp_send_json_error(array( 'message' => __('This username is blacklisted.', 'limit-login-attempts-reloaded') ));
						}
					}
				}
			}
		}

		if (!wp_check_password($password, $user->user_pass, $user->ID)) {
			if (function_exists('limit_login_failed_attempt')) {
				// Record a failed login attempt for security tracking.
				limit_login_failed_attempt($username, $ip);
			}
			
			wp_send_json_error(array('message' => __('Incorrect password.', 'limit-login-attempts-reloaded')));
		}

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
			wp_send_json_error(array('message' => sprintf(__('Incorrect or expired mfa code. Attempts left: %d', 'limit-login-attempts-reloaded'), $remaining_attempts)));
		}

		delete_user_meta($user_id, 'limit_login_failed_attempts'); 
		delete_transient('mfa_otp_' . $user_id); 
		delete_transient($lockout_key); 

		// Unset MFA-related session data.
		unset($_SESSION['mfa_user_id'], $_SESSION['mfa_user_password']);

		// Remove transient data related to the user session and password storage.
		delete_transient('mfa_last_user_id');
		delete_transient('mfa_last_user_password_' . $user_id);

		wp_set_auth_cookie($user_id, true); 
		wp_set_current_user($user_id); 
		do_action('wp_login', $username, $user); 

		wp_send_json_success(
            array(
                'message'  => __('Verification successful! Redirecting...', 'limit-login-attempts-reloaded'),
                'redirect' => esc_url(admin_url()),
            )
        );
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

Limit_Login_Attempts_MFA::init()->register();
