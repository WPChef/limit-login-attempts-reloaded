<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

?>
            <div class="info-box-3">
                <div class="section-title__new">
                    <div class="title"><?php
                    if ( $free_requests_exhausted ) {
                        _e( 'Micro Cloud Protection Paused', 'limit-login-attempts-reloaded' );
                    } else {
                        _e( 'Premium Protection Disabled', 'limit-login-attempts-reloaded' );
                    }
                     ?></div>
                </div>
                <div class="section-content">
                    <div class="desc">
                        <?php _e( 'As a free user, your local server is absorbing the traffic brought on by brute force attacks, potentially slowing down your website. Upgrade to Premium today to outsource these attacks through our cloud app, and slow down future attacks with advanced throttling.', 'limit-login-attempts-reloaded' ); ?>
                    </div>
                </div>
                <div class="actions">
                    <div class="actions__buttons">
                        <a href="https://www.limitloginattempts.com/upgrade/?from=plugin-dashboard-cta"
                           title="Upgrade To Premium"
                           target="_blank"
                           class="link__style_unlink">
                            <button class="button menu__item col button__orange">
                                <?php _e( 'Upgrade to Premium', 'limit-login-attempts-reloaded' ); ?>
                            </button>
                        </a>
                    </div>
                </div>
            </div>