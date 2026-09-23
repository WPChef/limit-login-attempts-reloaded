<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="message" class="updated fade notice is-dismissible llar-notice-review">
	<div class="llar-review-image">
		<img width="80px" src="<?php echo esc_url( LLA_PLUGIN_URL . 'assets/img/icon-256x256.png' ); ?>" alt="review-logo">
	</div>
	<div class="llar-review-info">
		<p><?php esc_html_e( 'Hey Limit Login Attempts Reloaded user!', 'limit-login-attempts-reloaded' ); ?></p>
		<p>
		<?php
			echo wp_kses_post(
				sprintf(
					// translators: %s: URL of the plugin review form on wordpress.org.
					__( 'We would really like to hear your feedback about the plugin! Please take a couple minutes to write a few words <a href="%s" target="_blank">here</a>. Thank you!', 'limit-login-attempts-reloaded' ),
					'https://wordpress.org/support/plugin/limit-login-attempts-reloaded/reviews/#new-post'
				)
			);
			?>
		</p>
		<ul class="llar-buttons">
			<li><a href="#" class="llar-review-dismiss" data-type="dismiss"><?php esc_html_e( 'Don\'t show again', 'limit-login-attempts-reloaded' ); ?></a></li>
			<li><a href="#" class="llar-review-dismiss llar_button menu__item button__transparent_orange" data-type="later"><?php esc_html_e( 'Maybe later', 'limit-login-attempts-reloaded' ); ?></a></li>
			<li><a class="llar_button menu__item button__transparent_orange" target="_blank" rel="noopener noreferrer" href="https://wordpress.org/support/plugin/limit-login-attempts-reloaded/reviews/#new-post"><?php esc_html_e( 'Leave a review', 'limit-login-attempts-reloaded' ); ?></a></li>
		</ul>
	</div>
</div>
<?php
// Dismiss handlers live in assets/js/llar-admin-review-notice.js (enqueued and
// localized by LimitLoginAttempts::enqueue_leave_review_notice_script()).
// No inline <script> here: kses-based output filters on admin_notices strip
// script tags but keep their body, printing the JS as plain text.
