<?php

if( !defined( 'ABSPATH' ) ) exit();

/**
 * @var $this Limit_Login_Attempts
 */
?>

<div class="llar-premium-page-wrapper">
    <div class="llar-premium-page-promo">
        <div class="section-1">
            <div class="text">
                <div class="title"><?php _e( 'Limit Login Attempts Reloaded <strong>Premium</strong>', 'limit-login-attempts-reloaded' ); ?></div>
                <ul class="links">
                    <li><a href="" target="_blank"><?php _e( 'Full feature list', 'limit-login-attempts-reloaded' ); ?></a></li>
                    <li><a href="" target="_blank"><?php _e( 'Pre-sales FAQs', 'limit-login-attempts-reloaded' ); ?></a></li>
                    <li><a href="" target="_blank"><?php _e( 'Ask a pre-sales question', 'limit-login-attempts-reloaded' ); ?></a></li>
                    <li><a href="" target="_blank"><?php _e( 'Support', 'limit-login-attempts-reloaded' ); ?></a></li>
                </ul>
            </div>
            <div class="action">
                <a class="button button-primary" href="" target="_blank"><?php _e( 'Get It Here', 'limit-login-attempts-reloaded' ); ?></a>
                <div class="label"><span class="dashicons dashicons-external"></span><?php _e( 'Goes to LLAR checkout page', 'limit-login-attempts-reloaded' ); ?></div>
            </div>
        </div>
        <div class="section-2">
			<?php _e( 'You are currently using the free version of <strong>Limit Login Attempts Reloaded</strong>.', 'limit-login-attempts-reloaded' ); ?>
            <a href="#" target="_blank"><?php _e( 'If you purchased a premium plan, check your email for setup instructions (key included).', 'limit-login-attempts-reloaded' ); ?></a>
        </div>
    </div>

    <div class="text-block-1">
        <h1><?php _e( 'Why should I Consider Premium?', 'limit-login-attempts-reloaded' ); ?></h1>
        <p><?php _e( 'Although the free version offers great protection, it lacks higher IP intelligence. With higher IP ' .
				'intelligence, your website will be able to identify malicious IPs before they attempt a login and absorb them' .
				' into the cloud to save system resources. Your site will not only be more secure, but will operate at its ' .
				'optimal performance.', 'limit-login-attempts-reloaded' ); ?></p>
    </div>

    <h3><?php _e( 'Features comparison', 'limit-login-attempts-reloaded' ); ?></h3>

    <div class="llar-premium-plans-table">
        <table>
            <tr>
                <th class="feature"></th>
                <th><img src="<?php echo esc_attr( LLA_PLUGIN_URL . '/assets/img/icon-256x256.png' ); ?>" alt=""><div class="plan-name"><?php _e( 'Free', 'limit-login-attempts-reloaded' ); ?></div></th>
                <th><img src="<?php echo esc_attr( LLA_PLUGIN_URL . '/assets/img/icon-256x256.png' ); ?>" alt=""><div class="plan-name"><?php _e( 'Premium', 'limit-login-attempts-reloaded' ); ?></div></th>
                <th><img src="<?php echo esc_attr( LLA_PLUGIN_URL . '/assets/img/icon-256x256.png' ); ?>" alt=""><div class="plan-name"><?php _e( 'Premium Plus', 'limit-login-attempts-reloaded' ); ?></div></th>
                <th><img src="<?php echo esc_attr( LLA_PLUGIN_URL . '/assets/img/icon-256x256.png' ); ?>" alt=""><div class="plan-name"><?php _e( 'Professional', 'limit-login-attempts-reloaded' ); ?></div></th>
            </tr>
            <tr class="table-actions">
                <td class="feature"></td>
                <td><span class="installed-label"><span class="dashicons dashicons-yes"></span> <?php _e( 'Installed', 'limit-login-attempts-reloaded' ); ?></span></td>
                <td><a class="button button-primary" href="#" target="_blank"><?php _e( 'Upgrade now', 'limit-login-attempts-reloaded' ); ?></a></td>
                <td><a class="button button-primary" href="#" target="_blank"><?php _e( 'Upgrade now', 'limit-login-attempts-reloaded' ); ?></a></td>
                <td><a class="button button-primary" href="#" target="_blank"><?php _e( 'Upgrade now', 'limit-login-attempts-reloaded' ); ?></a></td>
            </tr>
            <tr>
                <td class="feature">
                    <div class="name"><?php _e( 'Limit number of Retry Attempts', 'limit-login-attempts-reloaded' ); ?></div>
                </td>
                <td><span class="dashicons dashicons-yes"></span></td>
                <td><span class="dashicons dashicons-yes"></span></td>
                <td><span class="dashicons dashicons-yes"></span></td>
                <td><span class="dashicons dashicons-yes"></span></td>
            </tr>
            <tr>
                <td class="feature">
                    <div class="name"><?php _e( 'Configurable Lockout Timing', 'limit-login-attempts-reloaded' ); ?></div>
                </td>
                <td><span class="dashicons dashicons-yes"></span></td>
                <td><span class="dashicons dashicons-yes"></span></td>
                <td><span class="dashicons dashicons-yes"></span></td>
                <td><span class="dashicons dashicons-yes"></span></td>
            </tr>
            <tr>
                <td class="feature">
                    <div class="name"><?php _e( 'Performance Optimizer', 'limit-login-attempts-reloaded' ); ?></div>
                    <div class="desc"><?php _e( 'Absorb failed login attempts from brute force bots in the cloud to ' .
                            'keep your website at its optimal performance.', 'limit-login-attempts-reloaded' ); ?></div>
                </td>
                <td><span class="dashicons dashicons-no-alt"></span></td>
                <td>
                    <span class="dashicons dashicons-yes"></span>
                    <div class="feature-value"><?php _e( '100k requests per month', 'limit-login-attempts-reloaded' ); ?></div>
                </td>
                <td>
                    <span class="dashicons dashicons-yes"></span>
                    <div class="feature-value"><?php _e( '200k requests per month', 'limit-login-attempts-reloaded' ); ?></div>
                </td>
                <td>
                    <span class="dashicons dashicons-yes"></span>
                    <div class="feature-value"><?php _e( '300k requests per month', 'limit-login-attempts-reloaded' ); ?></div>
                </td>
            </tr>
            <tr>
                <td class="feature">
                    <div class="name"><?php _e( 'Intelligent IP Blocking', 'limit-login-attempts-reloaded' ); ?></div>
                    <div class="desc"><?php _e( 'Use active IP database vie the cloud to automatically block users ' .
                            'before they are able to make a failed login.', 'limit-login-attempts-reloaded' ); ?></div>
                </td>
                <td><span class="dashicons dashicons-no-alt"></span></td>
                <td><span class="dashicons dashicons-yes"></span></td>
                <td><span class="dashicons dashicons-yes"></span></td>
                <td><span class="dashicons dashicons-yes"></span></td>
            </tr>
            <tr class="table-actions">
                <td class="feature"></td>
                <td><span class="installed-label"><span class="dashicons dashicons-yes"></span> <?php _e( 'Installed', 'limit-login-attempts-reloaded' ); ?></span></td>
                <td><a class="button button-primary" href="#" target="_blank"><?php _e( 'Upgrade now', 'limit-login-attempts-reloaded' ); ?></a></td>
                <td><a class="button button-primary" href="#" target="_blank"><?php _e( 'Upgrade now', 'limit-login-attempts-reloaded' ); ?></a></td>
                <td><a class="button button-primary" href="#" target="_blank"><?php _e( 'Upgrade now', 'limit-login-attempts-reloaded' ); ?></a></td>
            </tr>
        </table>
    </div>
</div>


