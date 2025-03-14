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

    public function register() {
        $this->load_config();
        add_action('login_form', [$this, 'add_2fa_scripts'], 99);
        add_action('wp_ajax_check_mfa_role', [$this, 'check_mfa_role']);
        add_action('wp_ajax_nopriv_check_mfa_role', [$this, 'check_mfa_role']);
        add_action('wp_ajax_send_2fa_code', [$this, 'send_2fa_code']);
        add_action('wp_ajax_nopriv_send_2fa_code', [$this, 'send_2fa_code']);
        add_action('wp_ajax_verify_2fa_code', [$this, 'verify_2fa_code']);
        add_action('wp_ajax_nopriv_verify_2fa_code', [$this, 'verify_2fa_code']);
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
        if (!isset($_SESSION['mfa_user_id'])) {
            wp_send_json_error(['message' => __('Session expired, please log in again.', 'limit-login-attempts-reloaded')]);
        }

        $user_id = $_SESSION['mfa_user_id'];
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            wp_send_json_error(['message' => __('User not found.', 'limit-login-attempts-reloaded')]);
        }

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

        if (!isset($_SESSION['mfa_user_id'])) {
            wp_send_json_error(['message' => __('Session expired, please log in again.', 'limit-login-attempts-reloaded')]);
        }

        $user_id = $_SESSION['mfa_user_id'];
        $saved_code = get_transient("mfa_otp_$user_id");

        if (!$saved_code || empty($_POST['mfa_code']) || $_POST['mfa_code'] !== $saved_code) {
            wp_send_json_error(['message' => __('Incorrect or expired 2FA code.', 'limit-login-attempts-reloaded')]);
        }

        delete_transient("mfa_otp_$user_id");
        unset($_SESSION['mfa_user_id']);

        wp_set_auth_cookie($user_id, true);
        wp_set_current_user($user_id);
        do_action('wp_login', get_userdata($user_id)->user_login, get_userdata($user_id));

        wp_send_json_success(['message' => __('Verification successful! Redirecting...', 'limit-login-attempts-reloaded'), 'redirect' => esc_url(admin_url())]);
    }

    public function add_2fa_scripts() {
        ?>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script>
        jQuery(document).ready(function($) {
            let form = $("#loginform");

            form.on("submit", function(e) {
                let username = $("#user_login").val();
                if (!username) return;

                e.preventDefault();

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

                    $("#mfa-actions").show();
                    $("#send-2fa-code").click();
                });
            });

            $("#send-2fa-code").click(function() {
                $.post('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', { action: "send_2fa_code" }, function(response) {
                    $("#mfa-message").text(response.data.message);
                    if (response.success) {
                        $("#send-2fa-code").hide();
                        $("#mfa-code, #verify-2fa-code").show();
                    }
                });
            });

            $('#verify-2fa-code').click(function() {
                let mfa_code = $('#mfa-code').val();
                if (!mfa_code) {
                    $('#mfa-message').text('<?php echo __('Enter the 2FA code.', 'limit-login-attempts-reloaded'); ?>');
                    return;
                }

                $.post('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                    action: 'verify_2fa_code',
                    mfa_code: mfa_code
                }, function(response) {
                    $('#mfa-message').text(response.data.message);
                    if (response.success) {
                        window.location.href = response.data.redirect;
                    }
                });
            });
        });
        </script>

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
