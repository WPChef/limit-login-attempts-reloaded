<?php
/**
 * Site-wide PHP2Shell upstream-version warning (dismissible via cookie).
 *
 * @var string $notice_content Already-built HTML for inside <p> (escaped/kses'd upstream).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cookie_name = 'llar_php_vuln_dismissed_' . (int) get_current_user_id();
?>
<div class="notice notice-error is-dismissible llar-options-notice llar-php-vulnerable-notice" data-llar-php-vuln-cookie="<?php echo esc_attr( $cookie_name ); ?>">
	<p>
		<?php echo wp_kses_post( $notice_content ); ?>
	</p>
</div>
<script type="text/javascript">
( function( $ ) {
	$( document ).ready( function() {
		$( '.llar-php-vulnerable-notice' ).on( 'click', '.notice-dismiss', function() {
			var $notice = $( this ).closest( '.llar-php-vulnerable-notice' );
			var name = $notice.data( 'llar-php-vuln-cookie' );
			if ( ! name ) {
				return;
			}
			var expires = new Date();
			expires.setFullYear( expires.getFullYear() + 10 );
			document.cookie = encodeURIComponent( name ) + '=1; expires=' + expires.toUTCString() + '; path=/; SameSite=Lax';
		} );
	} );
} )( jQuery );
</script>
