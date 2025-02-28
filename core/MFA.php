<?php

class Limit_Login_MFA {
    private $allowed_roles;

    public function __construct() {
        $this->load_config();
        add_action('wp_ajax_lar_send_otp', array($this, 'send_otp'));
        add_action('wp_ajax_nopriv_lar_send_otp', array($this, 'send_otp'));
        add_action('wp_ajax_lar_verify_otp', array($this, 'verify_otp'));
        add_action('wp_ajax_nopriv_lar_verify_otp', array($this, 'verify_otp'));
    }

    private function load_config() {
        $app_config_json = get_option('limit_login_app_config', '{}');
        $app_config = json_decode($app_config_json, true);
        $this->allowed_roles = isset($app_config['mfa_roles']) ? $app_config['mfa_roles'] : array();
    }

    public function handle_login($username, $password) {
        $user = get_user_by('login', sanitize_user($username));
        if ($user && wp_check_password($password, $user->user_pass, $user->ID)) {
            $requires_mfa = array_intersect($this->allowed_roles, $user->roles);
            if (!empty($requires_mfa)) {
                set_transient('pending_user_' . $user->ID, $user->ID, 300);
                wp_send_json_success(array('status' => 'send_otp'));
            } else {
                wp_set_auth_cookie($user->ID);
                wp_send_json_success(array('status' => 'success'));
            }
        } else {
            wp_send_json_error(array('status' => 'error', 'message' => 'Invalid credentials'));
        }
        exit;
    }

    public function send_otp() {
        if (!isset($_POST['user_id'])) {
            wp_send_json_error(array('status' => 'error', 'message' => 'User ID missing'));
        }
        $user_id = intval($_POST['user_id']);
        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(array('status' => 'error', 'message' => 'User not found'));
        }
        $otp = wp_rand(100000, 999999);
        set_transient('otp_' . $user_id, $otp, 300);
        wp_mail($user->user_email, 'Your OTP Code', 'Your OTP: ' . $otp);
        wp_send_json_success(array('status' => 'otp_sent'));
        exit;
    }

    public function verify_otp() {
        if (!isset($_POST['user_id']) || !isset($_POST['otp'])) {
            wp_send_json_error(array('status' => 'error', 'message' => 'Missing data'));
        }
        $user_id = intval($_POST['user_id']);
        $otp = sanitize_text_field($_POST['otp']);
        $stored_otp = get_transient('otp_' . $user_id);
        
        if (!$stored_otp) {
            wp_send_json_error(array('status' => 'error', 'message' => 'OTP expired, please request a new one'));
        }
        
        if ($stored_otp == $otp) {
            wp_set_auth_cookie($user_id);
            delete_transient('otp_' . $user_id);
            wp_send_json_success(array('status' => 'success'));
        } else {
            wp_send_json_error(array('status' => 'error', 'message' => 'Invalid OTP'));
        }
        exit;
    }
}

new Limit_Login_MFA();