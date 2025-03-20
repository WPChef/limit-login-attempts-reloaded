<?php
declare(strict_types=1);

namespace LLAR\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Limit_Login_Attempts_MFA {
    private static $instance;
    private $allowed_roles = array();

    public static function init() {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
	public function logout_clear_mfa_data() {
		if (!session_id()) {
			session_start();
		}

		if (isset($_SESSION['mfa_user_id'])) {
			$user_id = $_SESSION['mfa_user_id'];
			delete_transient("mfa_email_sent_$user_id"); 
			delete_transient("mfa_otp_$user_id"); 
			unset($_SESSION['mfa_user_id']);
		}
	}
    public function register() {
        $this->load_config();
        add_action('login_form', [$this, 'add_2fa_scripts'], 99);
        add_action('wp_ajax_check_mfa_role', [$this, 'check_mfa_role']);
        add_action('wp_ajax_nopriv_check_mfa_role', [$this, 'check_mfa_role']);
        add_action('wp_ajax_send_2fa_code', [$this, 'send_2fa_code']);
        add_action('wp_ajax_nopriv_send_2fa_code', [$this, 'send_2fa_code']);
        add_action('wp_ajax_verify_2fa_code', [$this, 'verify_2fa_code']);
        add_action('wp_ajax_nopriv_verify_2fa_code', [$this, 'verify_2fa_code']);
		add_action('wp_logout', [$this, 'logout_clear_mfa_data']);
    }

    private function load_config() {
        $app_config_json = get_option('limit_login_app_config', '{}');

        if (!is_string($app_config_json)) {
            $app_config_json = wp_json_encode($app_config_json);
        }

        $app_config = json_decode($app_config_json, true);
        if (!is_array($app_config)) {
            $app_config = [];
        }

        $this->allowed_roles = [];
        if (!empty($app_config['mfa_roles']) && is_array($app_config['mfa_roles'])) {
            foreach ($app_config['mfa_roles'] as $role => $status) {
                if ($status !== 'off') {
                    $this->allowed_roles[$role] = true;
                }
            }
        }
    }

    public function check_mfa_role() {
        $username = isset($_POST['username']) ? sanitize_text_field(wp_unslash($_POST['username'])) : '';

        if ($username === '') {
            wp_send_json_error(['message' => __('Username is required.', 'limit-login-attempts-reloaded')]);
        }

        $user = get_user_by('login', $username);
        if (!$user) {
            wp_send_json_error(['message' => __('User not found.', 'limit-login-attempts-reloaded')]);
        }

        $user_roles = array_flip($user->roles);
        if (!array_intersect_key($this->allowed_roles, $user_roles)) {
            wp_send_json_success(['requires_mfa' => false]);
        } else {
            if (!session_id()) {
                session_start();
            }
            $_SESSION['mfa_user_id'] = $user->ID;
            wp_send_json_success(['requires_mfa' => true]);
        }
    }

public function send_2fa_code() {
    if (!session_id()) {
        session_start();
    }

    if (!isset($_SESSION['mfa_user_id']) || !isset($_POST['password'])) {
        wp_send_json_error(['message' => __('Session expired or missing data, please log in again.', 'limit-login-attempts-reloaded')]);
    }

    $user_id = $_SESSION['mfa_user_id'];
    $password = sanitize_text_field($_POST['password']); // Secure the password input
    $user = get_user_by('ID', $user_id);

    if (!$user) {
        wp_send_json_error(['message' => __('User not found.', 'limit-login-attempts-reloaded')]);
    }

    // ✅ Verify the password before storing it
    if (!wp_check_password($password, $user->user_pass, $user_id)) {
        wp_send_json_error(['message' => __('Incorrect password.', 'limit-login-attempts-reloaded')]);
    }

    // ✅ Store the password securely in session and transient
    $_SESSION['mfa_user_password'] = $password;
    set_transient("mfa_last_user_password_$user_id", $password, 10 * MINUTE_IN_SECONDS);

    if (get_transient("mfa_email_sent_$user_id")) {
        wp_send_json_error(['message' => __('2FA code was already sent. Please check your email.', 'limit-login-attempts-reloaded')]);
    }

    $otp_code = wp_rand(100000, 999999);
    set_transient("mfa_otp_$user_id", $otp_code, 5 * MINUTE_IN_SECONDS);
    set_transient("mfa_email_sent_$user_id", true, 5 * MINUTE_IN_SECONDS);

    wp_mail($user->user_email, __('Your 2FA Code', 'limit-login-attempts-reloaded'), sprintf(__('Your verification code: %s', 'limit-login-attempts-reloaded'), $otp_code));

    wp_send_json_success(['message' => __('2FA code sent to your email.', 'limit-login-attempts-reloaded')]);
}

public function verify_2fa_code() {
    if (!session_id()) {
        session_start();
    }
		
    // ✅ Ensure session variables exist or retrieve from transient
    if (!isset($_SESSION['mfa_user_id'])) {
        $user_id = get_transient("mfa_last_user_id");
        $password = get_transient("mfa_last_user_password_" . $user_id);

        if (!$user_id || !$password) {
            wp_send_json_error(array('message' => __('Session expired, please log in again.', 'limit-login-attempts-reloaded')));
        }
    } else {
        $user_id = $_SESSION['mfa_user_id'];
        $password = isset($_SESSION['mfa_user_password']) ? $_SESSION['mfa_user_password'] : get_transient("mfa_last_user_password_" . $user_id);
    }

    // ✅ Restore session values
    $_SESSION['mfa_user_id'] = $user_id;
    $_SESSION['mfa_user_password'] = $password;

    $user = get_user_by('ID', $user_id);
    if (!$user) {
        wp_send_json_error(array('message' => __('User not found.', 'limit-login-attempts-reloaded')));
    }


	global $wpdb;

	/**
	 * Get the user IP address.
	 */
	$ip = function_exists( '\LLAR\Core\Helpers::detect_ip_address' )
		? \LLAR\Core\Helpers::detect_ip_address( array() )
		: $_SERVER['REMOTE_ADDR'];

	/**
	 * Get the username from POST request or current logged-in user.
	 */
	$username = isset( $_POST['username'] ) ? sanitize_text_field( $_POST['username'] ) : '';
	if ( empty( $username ) && is_user_logged_in() ) {
		$current_user = wp_get_current_user();
		$username = $current_user->user_login;
	}

	/**
	 * Retrieve blacklists from database options.
	 */
	$blacklisted_ip = get_option( 'limit_login_blacklisted_ips', '' );
	$blacklisted_usernames = get_option( 'limit_login_blacklisted_usernames', '' );

	/**
	 * Check if IP is blacklisted in the database.
	 */
	if ( ! empty( $blacklisted_ip ) && strpos( $blacklisted_ip, $ip ) !== false ) {
		wp_send_json_error( array( 'message' => __( 'Your IP is blacklisted.', 'limit-login-attempts-reloaded' ) ) );
	}

	/**
	 * Check if Username is blacklisted in the database.
	 */
	if ( ! empty( $blacklisted_usernames ) && strpos( $blacklisted_usernames, $username ) !== false ) {
		wp_send_json_error( array( 'message' => __( 'This username is blacklisted.', 'limit-login-attempts-reloaded' ) ) );
	}

	/**
	 * Check API if CloudApp class is available and initialized.
	 */
	if ( class_exists( '\LLAR\Core\CloudApp' ) && isset( \LLAR\Core\LimitLoginAttempts::$cloud_app ) ) {
		$cloudApp = \LLAR\Core\LimitLoginAttempts::$cloud_app;

		if ( method_exists( $cloudApp, 'request' ) ) {
			// Check IP blacklist via API
			$api_blacklist_ip = $cloudApp->request( 'acl', 'get', array( 'type' => 'ip' ) );
			if ( ! empty( $api_blacklist_ip['items'] ) ) {
				foreach ( $api_blacklist_ip['items'] as $rule ) {
					if ( $rule['pattern'] === $ip && $rule['rule'] === 'deny' ) {
						wp_send_json_error( array( 'message' => __( 'Your IP is blacklisted.', 'limit-login-attempts-reloaded' ) ) );
					}
				}
			}

			// Check Username blacklist via API
			$api_blacklist_usernames = $cloudApp->request( 'acl', 'get', array( 'type' => 'login' ) );
			if ( ! empty( $api_blacklist_usernames['items'] ) ) {
				foreach ( $api_blacklist_usernames['items'] as $rule ) {
					if ( $rule['pattern'] === $username && $rule['rule'] === 'deny' ) {
						wp_send_json_error( array( 'message' => __( 'This username is blacklisted.', 'limit-login-attempts-reloaded' ) ) );
					}
				}
			}
		}
	}





    // ✅ Verify the stored password before checking 2FA
    if (!wp_check_password($password, $user->user_pass, $user->ID)) {
        if (function_exists('limit_login_failed_attempt')) {
            limit_login_failed_attempt($username, $ip); // ✅ Register failed login attempt
        }
        wp_send_json_error(array('message' => __('Incorrect password.', 'limit-login-attempts-reloaded')));
    }

    // ✅ Get stored 2FA code
    $saved_code = get_transient("mfa_otp_" . $user_id);
    $provided_code = isset($_POST['mfa_code']) ? sanitize_text_field($_POST['mfa_code']) : '';

    // ✅ Check login retry limits
    $failed_attempts = get_user_meta($user_id, 'limit_login_failed_attempts', true);
    $failed_attempts = !empty($failed_attempts) ? (int) $failed_attempts : 0;
    $max_attempts = (int) get_option('limit_login_retries', 3);
    $lockout_time = (int) get_option('limit_login_lockout_duration', 900);
    $lockout_key = "limit_login_lockout_" . $user_id;
    $is_locked = get_transient($lockout_key);

    // 🚨 Lock user if they exceeded allowed attempts
    if ($failed_attempts >= $max_attempts || $is_locked) {
        wp_send_json_error(array('message' => __('You have been temporarily locked out due to too many failed attempts.', 'limit-login-attempts-reloaded')));
    }

    // 🚨 If incorrect 2FA code, register a failed login attempt
    if (!$saved_code || empty($provided_code) || $provided_code !== $saved_code) {
        update_user_meta($user_id, 'limit_login_failed_attempts', $failed_attempts + 1);

        if (function_exists('limit_login_failed_attempt')) {
            limit_login_failed_attempt($username, $ip); // ✅ Register failed 2FA attempt
        }

        // 🚨 Lock user if max attempts reached
        if (($failed_attempts + 1) >= $max_attempts) {
            set_transient($lockout_key, true, $lockout_time);
            wp_send_json_error(array('message' => __('You have been temporarily locked out due to too many failed attempts.', 'limit-login-attempts-reloaded')));
        }

        $remaining_attempts = max(0, $max_attempts - ($failed_attempts + 1));
        wp_send_json_error(array('message' => sprintf(__('Incorrect or expired 2FA code. Attempts left: %d', 'limit-login-attempts-reloaded'), $remaining_attempts)));
    }

    // ✅ Clear failed attempts and lockouts on success
    delete_user_meta($user_id, 'limit_login_failed_attempts');
    delete_transient("mfa_otp_" . $user_id);
    delete_transient($lockout_key);
    unset($_SESSION['mfa_user_id'], $_SESSION['mfa_user_password']);
    delete_transient("mfa_last_user_id");
    delete_transient("mfa_last_user_password_" . $user_id);

    // ✅ Authenticate the user only after 2FA success
    wp_set_auth_cookie($user_id, true);
    wp_set_current_user($user_id);
    do_action('wp_login', $username, $user);

    wp_send_json_success(array(
        'message' => __('Verification successful! Redirecting...', 'limit-login-attempts-reloaded'),
        'redirect' => esc_url(admin_url())
    ));
}



public function add_2fa_scripts() {
    ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
jQuery(document).ready(function($) {
    let form = $("#loginform");
    let sendCodeButton = $("#send-2fa-code");
    let verifyButton = $("#verify-2fa-code");
    let mfaCodeInput = $("#mfa-code");
    let mfaMessage = $("#mfa-message");
    let mfaActions = $("#mfa-actions");
    let isMfaVerified = localStorage.getItem("isMfaVerified") === "true";

    // ✅ Clear storage if user logs out
    if (window.location.href.includes("loggedout=true")) {
        localStorage.clear();
    }

    // ✅ Restore saved form inputs
    $("#user_login").val(localStorage.getItem("mfa_username") || '');
    $("#user_pass").val(localStorage.getItem("mfa_password") || '');
    mfaCodeInput.val(localStorage.getItem("mfa_code") || '');
    mfaMessage.text(localStorage.getItem("mfa_message") || '');

    // ✅ Restore UI state
    if (localStorage.getItem("mfa_actions_visible") === "true") {
        mfaActions.show();
    }
    if (localStorage.getItem("mfa_code_visible") === "true") {
        mfaCodeInput.show();
        verifyButton.show();
    }

    let resendCooldown = parseInt(localStorage.getItem("mfa_resend_cooldown")) || 0;
    let lastRequestTime = parseInt(localStorage.getItem("mfa_last_request_time")) || 0;
    let currentTime = Math.floor(Date.now() / 1000);
    let remainingTime = lastRequestTime + resendCooldown - currentTime;

    function startResendTimer(timeLeft) {
        if (timeLeft > 0) {
            sendCodeButton.text(`Resend Code (${timeLeft}s)`).prop("disabled", true);
            let resendTimer = setInterval(() => {
                timeLeft--;
                sendCodeButton.text(`Resend Code (${timeLeft}s)`);
                if (timeLeft <= 0) {
                    clearInterval(resendTimer);
                    sendCodeButton.text("Resend Code").prop("disabled", false);
                    localStorage.removeItem("mfa_resend_cooldown");
                    localStorage.removeItem("mfa_last_request_time");
                }
            }, 1000);
        }
    }

    if (remainingTime > 0) {
        startResendTimer(remainingTime);
    }

    form.on("submit", function(e) {
		
        let username = $("#user_login").val();
        let password = $("#user_pass").val();

        if (!username || !password) return;

        // ✅ Prevent login form from submitting if 2FA is required
        if (!isMfaVerified) {
            e.preventDefault();

            localStorage.setItem("mfa_username", username);
            localStorage.setItem("mfa_password", password);

            $.post('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                action: "check_mfa_role",
                username: username
            }, function(response) {
                if (!response.success) {
                    alert(response.data.message);
                    return;
                }

                if (!response.data.requires_mfa) {
                    form.off("submit").submit();
                    return;
                }

                localStorage.setItem("mfa_actions_visible", "true");
                mfaActions.show();
                sendCodeButton.click();
            });
        }
    });

    sendCodeButton.click(function() {
        let password = $("#user_pass").val();
        if (!password) {
            mfaMessage.text('<?php echo __('Enter your password first.', 'limit-login-attempts-reloaded'); ?>');
            return;
        }

        $.post('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
            action: "send_2fa_code",
            password: password
        }, function(response) {
            mfaMessage.text(response.data.message);
            localStorage.setItem("mfa_message", response.data.message);

            sendCodeButton.text("Resend Code (60s)").prop("disabled", true);
            localStorage.setItem("mfa_resend_cooldown", "60");
            localStorage.setItem("mfa_last_request_time", Math.floor(Date.now() / 1000));

			mfaCodeInput.show().css('display', 'block !important'); // ❌ .style() ➔ ✅ .css()
			verifyButton.show().css('display', 'block !important'); // ❌ .style() ➔ ✅ .css()

            localStorage.setItem("mfa_code_visible", "true");
            startResendTimer(60);
        });
    });

    verifyButton.click(function() {
        let mfa_code = mfaCodeInput.val();
        if (!mfa_code) {
            mfaMessage.text('<?php echo __('Enter the 2FA code.', 'limit-login-attempts-reloaded'); ?>');
            localStorage.setItem("mfa_message", '<?php echo __('Enter the 2FA code.', 'limit-login-attempts-reloaded'); ?>');
            return;
        }

        $.post('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
            action: 'verify_2fa_code',
			username: $("#user_login").val(),
            mfa_code: mfa_code
        }, function(response) {
            mfaMessage.text(response.data.message);
            localStorage.setItem("mfa_message", response.data.message);

            if (!response.success) {
                if (response.data.message.includes("locked out") || response.data.message.includes("Too many failed")) {
                    sendCodeButton.prop("disabled", true);
                    verifyButton.prop("disabled", true);
                    mfaCodeInput.prop("disabled", true);
					isMfaVerified = true; 
					localStorage.setItem("isMfaVerified", "true"); 
                    let lockoutTime = 900;
                    let lockoutTimer = setInterval(() => {
                        lockoutTime--;
                        mfaMessage.text('<?php echo __('Locked out. Try again in', 'limit-login-attempts-reloaded'); ?> ' + lockoutTime + 's');
                        localStorage.setItem("mfa_message", '<?php echo __('Locked out. Try again in', 'limit-login-attempts-reloaded'); ?> ' + lockoutTime + 's');

                        if (lockoutTime <= 0) {
                            clearInterval(lockoutTimer);
                            sendCodeButton.prop("disabled", false);
                            verifyButton.prop("disabled", false);
                            mfaCodeInput.prop("disabled", false);
                            mfaMessage.text('<?php echo __('You can try again now.', 'limit-login-attempts-reloaded'); ?>');
                            localStorage.setItem("mfa_message", '<?php echo __('You can try again now.', 'limit-login-attempts-reloaded'); ?>');
                        }
                    }, 1000);
                } else {
                    mfaCodeInput.val('');
                }
                return;
            }

            if (response.success) {
                isMfaVerified = true; 
                localStorage.clear();
                form.off("submit").submit(); 
            }
        });
    });
});


    </script>
<style>
    #mfa-actions {
        display: none;
        background: #f8f9fa;
        border: 1px solid #ccd0d4;
        padding: 20px;
        border-radius: 5px;
        margin-top: 15px;
		margin-bottom: 15px !important;
        text-align: center;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    #mfa-code {
        width: 100%;
        padding: 10px;
        font-size: 16px;
        border: 1px solid #ccd0d4;
        border-radius: 3px;
        text-align: center;
        display: none;
        margin: 10px 0;
    }

    #send-2fa-code,
    #verify-2fa-code {
        width: 100%;
        padding: 10px;
        font-size: 16px;
        cursor: pointer;
        border: none;
        border-radius: 3px;
        font-weight: bold;
        transition: background 0.3s;
    }

    #send-2fa-code {
        background: #007cba;
        color: #fff;
        margin-bottom: 5px;
    }

    #send-2fa-code:hover {
        background: #005a9e;
    }

    #verify-2fa-code {
        background: #28a745;
        color: #fff;
        display: none;
    }

    #verify-2fa-code:hover {
        background: #218838;
    }

    #mfa-message {
        display: block;
        font-size: 14px;
        margin-top: 10px;
        color: #dc3545;
		padding-top: 50px;
    }
</style>
    <p id="mfa-actions" style="display:none;">
        <button type="button" id="send-2fa-code" class="button button-primary"><?php echo __('Send Code', 'limit-login-attempts-reloaded'); ?></button>
        <input type="text" id="mfa-code" class="input" placeholder="<?php echo __('Enter 2FA code', 'limit-login-attempts-reloaded'); ?>" style="display:none;">
        <button type="button" id="verify-2fa-code" class="button button-primary" style="display:none;"><?php echo __('Verify', 'limit-login-attempts-reloaded'); ?></button>
        <span id="mfa-message"></span>
    </p>
    <?php
}


}

Limit_Login_Attempts_MFA::init()->register();
