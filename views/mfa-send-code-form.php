<?php
/**
 * MFA Send Code Form Template
 *
 * @package Limit_Login_Attempts_Reloaded
 */

if (defined('ABSPATH') === false) {
    exit;
}

add_action('login_enqueue_scripts', 'llar_enqueue_core_login_styles');
function llar_enqueue_core_login_styles()
{
    $styles = array('dashicons', 'buttons', 'forms', 'l10n', 'login');
    foreach ($styles as $style) {
        wp_enqueue_style($style);
    }
}

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title><?php esc_html_e('Send MFA Code', 'limit-login-attempts-reloaded'); ?></title>

    <?php
    wp_admin_css('login', true);
    do_action('login_enqueue_scripts');
    do_action('login_head');
    ?>
</head>
<body class="login js login-action-login wp-core-ui  locale-en-us">
<script>document.body.className = document.body.className.replace('no-js', 'js');</script>

<div id="login">
    <h1><a href="<?php echo esc_url(home_url('/')); ?>"><?php bloginfo('name'); ?></a></h1>

    <?php
    if (isset($_SESSION['mfa_error'])) {
        ?>
        <div id="login_error" class="notice notice-error">
            <strong><?php esc_html_e('ERROR', 'limit-login-attempts-reloaded'); ?>:</strong>
            <?php echo esc_html(wp_kses_post($_SESSION['mfa_error'])); ?>
        </div>
        <?php
        unset($_SESSION['mfa_error']);
    }
    ?>

    <form name="sendcodeform" id="loginform"
          action="<?php echo esc_url(site_url('wp-login.php?action=mfa_send_code')); ?>" method="post">
        <p style="text-align: center;">
            <?php esc_html_e('Click the button below to receive your OTP code via email.', 'limit-login-attempts-reloaded'); ?>
        </p>

        <?php wp_nonce_field('send_mfa_code_nonce', 'mfa_send_code_nonce'); ?>

        <p class="submit" style="text-align:center;">
            <input type="submit" class="button button-primary button-large" style="float:none;margin-top:20px;"
                   value="<?php esc_attr_e('Send Code', 'limit-login-attempts-reloaded'); ?>"/>
        </p>
    </form>

    <p id="backtoblog">
        <a href="<?php echo esc_url(home_url('/')); ?>">&larr; <?php esc_html_e('Back to site', 'limit-login-attempts-reloaded'); ?></a>
    </p>
</div>

<?php do_action('login_footer'); ?>
</body>
</html>
