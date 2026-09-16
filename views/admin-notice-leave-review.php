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
		<p><?php
			echo wp_kses_post(
				sprintf(
					__( 'We would really like to hear your feedback about the plugin! Please take a couple minutes to write a few words <a href="%s" target="_blank">here</a>. Thank you!', 'limit-login-attempts-reloaded' ),
					'https://wordpress.org/support/plugin/limit-login-attempts-reloaded/reviews/#new-post'
				)
			);
		?></p>
		<ul class="llar-buttons">
			<li><a href="#" class="llar-review-dismiss" data-type="dismiss"><?php esc_html_e( 'Don\'t show again', 'limit-login-attempts-reloaded' ); ?></a></li>
			<li><a href="#" class="llar-review-dismiss llar_button menu__item button__transparent_orange" data-type="later"><?php esc_html_e( 'Maybe later', 'limit-login-attempts-reloaded' ); ?></a></li>
			<li><a class="llar_button menu__item button__transparent_orange" target="_blank" rel="noopener noreferrer" href="https://wordpress.org/support/plugin/limit-login-attempts-reloaded/reviews/#new-post"><?php esc_html_e( 'Leave a review', 'limit-login-attempts-reloaded' ); ?></a></li>
		</ul>
	</div>
</div>
<script type="text/javascript">
( function( $ ) {
	$( document ).ready( function() {
		$( '.llar-review-dismiss' ).on( 'click', function( e ) {
			e.preventDefault();
			var type = $( this ).data( 'type' );
			$.post( ajaxurl, {
				action: 'dismiss_review_notice',
				type: type,
				sec: '<?php echo esc_js( wp_create_nonce( 'llar-dismiss-review' ) ); ?>'
			} );
			$( this ).closest( '.llar-notice-review' ).remove();
		} );
		$( '.llar-notice-review' ).on( 'click', '.notice-dismiss', function () {
			var expires = '';
			var date = new Date();
			date.setTime( date.getTime() + ( 30 * 24 * 60 * 60 * 1000 ) );
			expires = '; expires=' + date.toUTCString();
			document.cookie = encodeURIComponent( 'llar_review_notice_shown' ) + '=1' + expires + '; path=/';
		} );
	} );
} )( jQuery );
</script>
