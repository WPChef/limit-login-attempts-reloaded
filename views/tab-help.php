<?php
/**
 * Help Page
 *
 * @var bool $is_active_app_custom
 * @var string $block_sub_group
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}
?>

<div id="llar-setting-page-help" class="llar-help-page">

    <div class="section-mission">
        <h3 class="title_page">
            <img src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/icon-blocklist.png">
            <?php _e( 'Our Mission', 'limit-login-attempts-reloaded' ); ?>
        </h3>
        <div class="section-mission-block">
            <div class="text">
                <?php _e( 'Our mission is to empower website owners and administrators with an effective security solution that mitigates the risk of unauthorized access, enhances user authentication, and safeguards WordPress websites from brute-force attacks. We are committed to providing a robust and user-friendly plugin that helps protect our clients\' digital assets, promotes a secure online environment, and fosters trust in the WordPress community.', 'limit-login-attempts-reloaded' ); ?>
            </div>
            <div class="add_block__under_table">
                <div class="add_block__list">
                    <div class="item">
                        <img class="icon" src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/icon-pre-install-bg.png">
                        <div class="name">
					        <?php _e( '2.5 Million Active<br>Installs', 'limit-login-attempts-reloaded' ); ?>
                        </div>
                    </div>
                    <div class="item">
                        <img class="icon" src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/icon-star-bg.png">
                        <div class="name">
					        <?php _e( '4.9 Rating On WordPress.org<br>(1,400 reviews)', 'limit-login-attempts-reloaded' ); ?>
                        </div>
                    </div>
                    <div class="item">
                        <img class="icon"
                             src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/icon-check-bg.png">
                        <div class="name">
	                        <?php _e( 'Top 25 Plugin<br>(by active installs)', 'limit-login-attempts-reloaded' ); ?>
                        </div>
                    </div>
                    <div class="item">
                        <img class="icon"
                             src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/icon-exploitation-bg.png">
                        <div class="name">
	                        <?php _e( '10,000 avg Daily<br>Activations', 'limit-login-attempts-reloaded' ); ?>
                        </div>
                    </div>
                    <div class="item">
                        <img class="icon"
                             src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/icon-grow-clients-bg.png">
                        <div class="name">
	                        <?php _e( 'Users from 150+<br>countries', 'limit-login-attempts-reloaded' ); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

	<?php if ( ! $is_active_app_custom || $block_sub_group === 'Micro Cloud' ) : ?>
        <div class="section-1">
            <div class="block">
                <div class="title">
					<?php _e( 'Upgrade Now to Access Premium Support', 'limit-login-attempts-reloaded' ); ?>
                </div>
                <div class="text">
					<?php _e( 'Our technical support team is available by email to help<br>with any questions.', 'limit-login-attempts-reloaded' ); ?>
                </div>
                <div class="actions mt-1_5">
                    <a class="button menu__item button__orange"
                       href="<?php echo esc_url( ( $block_sub_group === 'Micro Cloud' )
                        ? add_query_arg('id', '7', $this->info_upgrade_url())
                        : 'https://www.limitloginattempts.com/info.php?id=22' ); ?>" target="_blank">
						<?php _e( 'Upgrade To Premium', 'limit-login-attempts-reloaded' ); ?>
                    </a>
                </div>
            </div>
            <div class="block">
                <div class="title">
					<?php _e( 'Free Support', 'limit-login-attempts-reloaded' ); ?>
                </div>
                <div class="text">
					<?php _e( 'Support for free customers is available via our forums page on WordPress.org.<br>The majority of requests <b>receive an answer within a few days</b>.', 'limit-login-attempts-reloaded' ); ?>
                </div>
                <div class="actions mt-1_5">
                    <a class="button menu__item col button__transparent_orange"
                       href="https://wordpress.org/support/plugin/limit-login-attempts-reloaded/" target="_blank">
						<?php _e( 'Go To Support Forums', 'limit-login-attempts-reloaded' ); ?>
                    </a>
                </div>
            </div>
        </div>
	<?php endif; ?>

    <a href="https://www.limitloginattempts.com/gdpr-qa/?from=plugin-help-tab" target="_blank"
       class="description-page gdpr-information-link">
		<?php _e( 'GDPR Information', 'limit-login-attempts-reloaded' ); ?>
    </a>

    <a href="https://docs.limitloginattempts.com/" class="description-page gdpr-information-link all-doc-title mt-1_5" target="_blank">
		<?php _e( 'Software Documentation', 'limit-login-attempts-reloaded' ); ?>
    </a>

    <div class="documentation-section mt-1_5">
        <div class="questions">
            <h3 class="title_page">
                <img src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/icon-blocklist.png">
				<?php _e( 'All Documentation', 'limit-login-attempts-reloaded' ); ?>
            </h3>
            <div class="questions__block">
                <a class="question"
                   href="https://www.limitloginattempts.com/info.php?id=12"
                   target="_blank">
                    <div class="title"><?php _e( 'Cloud Service & Security', 'limit-login-attempts-reloaded' ); ?></div>
                    <div class="desc">
						<?php _e( 'Questions regarding the cloud service including how to activate, logs and storage, and compliance.', 'limit-login-attempts-reloaded' ); ?>
                    </div>
                </a>
                <a class="question"
                   href="https://www.limitloginattempts.com/info.php?id=13"
                   target="_blank">
                    <div class="title"><?php _e( 'Technical Questions', 'limit-login-attempts-reloaded' ); ?></div>
                    <div class="desc">
						<?php _e( 'Popular technical questions about the service including admin blocking, definitions, and email notifications.', 'limit-login-attempts-reloaded' ); ?>
                    </div>
                </a>
                <a class="question" href="https://www.limitloginattempts.com/info.php?id=14"
                   target="_blank">
                    <div class="title"><?php _e( 'Accounts & Billing', 'limit-login-attempts-reloaded' ); ?></div>
                    <div class="desc">
						<?php _e( 'Questions regarding updating billing info, cancellation, and expiration.', 'limit-login-attempts-reloaded' ); ?>
                    </div>
                </a>
                <a class="question"
                   href="https://www.limitloginattempts.com/info.php?id=15"
                   target="_blank">
                    <div class="title"><?php _e( 'Pre-sales Questions', 'limit-login-attempts-reloaded' ); ?></div>
                    <div class="desc">
						<?php _e( 'Questions regarding premium software sales.', 'limit-login-attempts-reloaded' ); ?>
                    </div>
                </a>
            </div>
        </div>
        <div class="top-list">
            <h3 class="title_page">
                <img src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/icon-blocklist.png">
				<?php _e( 'Top Topics and Questions', 'limit-login-attempts-reloaded' ); ?>
            </h3>
            <div class="list__block">
                <ol>
                    <li>
                        <a href="https://www.limitloginattempts.com/under-attack/?from=plugin-help-tab" target="_blank">
							<?php _e( 'How do I know if I\'m under attack?', 'limit-login-attempts-reloaded' ); ?>
                        </a>
                    </li>
                    <li>
                        <a href="https://www.limitloginattempts.com/how-can-i-tell-that-the-premium-plugin-is-working/?from=plugin-help-tab"
                           target="_blank">
							<?php _e( 'How can I tell that the premium plugin is working?', 'limit-login-attempts-reloaded' ); ?>
                        </a>
                    </li>
                    <li>
                        <a href="https://www.limitloginattempts.com/help-center/technical-questions/#what-do-i-do-if-the-admin-gets-blocked"
                           target="_blank">
							<?php _e( 'What do I do if the admin gets blocked?', 'limit-login-attempts-reloaded' ); ?>
                        </a>
                    </li>
                    <li>
                        <a href="https://www.limitloginattempts.com/why-am-i-still-seeing-login-attempts-even-after-the-ip-got-blocked/?from=plugin-help-tab"
                           target="_blank">
							<?php _e( 'Why am I still seeing login attempts even after the IP got blocked?', 'limit-login-attempts-reloaded' ); ?>
                        </a>
                    </li>
                    <li>
                        <a href="https://www.limitloginattempts.com/could-these-failed-login-attempts-be-fake/?from=plugin-help-tab"
                           target="_blank">
							<?php _e( 'Could these failed login attempts be fake?', 'limit-login-attempts-reloaded' ); ?>
                        </a>
                    </li>
                    <li>
                        <a href="https://www.limitloginattempts.com/the-logs-tab-how-system-works/?from=plugin-help-tab"
                           target="_blank">
							<?php _e( 'How does the login firewall work?', 'limit-login-attempts-reloaded' ); ?>
                        </a>
                    </li>
                    <li>
                        <a href="https://www.limitloginattempts.com/help-center/accounts-billing/#what-happens-if-my-site-exceeds-the-request-limits-in-the-plan"
                           target="_blank">
							<?php _e( 'What happens if my site exceeds the request limits in the plan?', 'limit-login-attempts-reloaded' ); ?>
                        </a>
                    </li>
                    <li>
                        <a href="https://www.limitloginattempts.com/help-center/technical-questions/#what-do-i-do-if-all-users-get-blocked"
                           target="_blank">
							<?php _e( 'What do I do if all users get blocked?', 'limit-login-attempts-reloaded' ); ?>
                        </a>
                    </li>
                    <li>
                        <a href="https://www.limitloginattempts.com/help-center/technical-questions/#i-just-installed-llar-and-im-already-getting-several-failed-login-attempts"
                           target="_blank">
							<?php _e( 'I just installed LLAR and I\'m already getting several failed login attempts', 'limit-login-attempts-reloaded' ); ?>
                        </a>
                    </li>
                    <li>
                        <a href="https://www.limitloginattempts.com/help-center/technical-questions/#what-urls-are-being-attacked-and-protected"
                           target="_blank">
							<?php _e( 'What URLs are being attacked and protected?', 'limit-login-attempts-reloaded' ); ?>
                        </a>
                    </li>
                </ol>
            </div>
        </div>
    </div>

	<?php if ( $is_active_app_custom && $block_sub_group !== 'Micro Cloud') : ?>
        <div class="section-1 mt-1_5">
            <div class="block">
                <div class="title">
					<?php _e( 'Premium Support', 'limit-login-attempts-reloaded' ); ?>
                </div>
                <div class="text">
					<?php _e( 'Our technical support team is available by email to help with any questions.', 'limit-login-attempts-reloaded' ); ?>
                </div>
                <div class="actions mt-1_5">
                    <a class="button menu__item button__orange"
                       href="https://www.limitloginattempts.com/contact-us/?from=plugin-help-tab" target="_blank">
						<?php _e( 'Contact Support', 'limit-login-attempts-reloaded' ); ?>
                    </a>
                </div>
            </div>
        </div>
	<?php endif ?>

	<?php if ( $is_active_app_custom && $block_sub_group !== 'Micro Cloud') : ?>
        <div class="section-team mt-1_5">
            <h3 class="title_page">
                <img src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/icon-grow-clients.png">
				<?php _e( 'The Team', 'limit-login-attempts-reloaded' ); ?>
            </h3>
            <div class="section-team-block">
                <div class="team-member">
                    <img class="team-member-image" src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/alex-benko.png">
                    <div class="team-member-description">
                        <div class="team-member-description-name">
                            Alex Benko - CCO
                        </div>
                        <a class="team-member-description-button button menu__item button__blue" href="https://www.linkedin.com/in/alexbenkotripshock/" target="_blank">in</a>
                    </div>
                </div>
                <div class="team-member">
                    <img class="team-member-image" src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/guriy.webp">
                    <div class="team-member-description">
                        <div class="team-member-description-name">
                            Guriy Habarov - CTO
                        </div>
                        <a class="team-member-description-button button menu__item button__blue" href="https://www.linkedin.com/in/gurii/" target="_blank">in</a>
                    </div>
                </div>
                <div class="team-member">
                    <img class="team-member-image" src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/greg-fisher.png">
                    <div class="team-member-description">
                        <div class="team-member-description-name">
                            Greg Fisher - CMO
                        </div>
                        <a class="team-member-description-button button menu__item button__blue" href="https://www.linkedin.com/in/greg-fisher-1b3a6514/" target="_blank">in</a>
                    </div>
                </div>
            </div>
        </div>
	<?php endif ?>
</div>