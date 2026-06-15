<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="footer">
	<div class="text" style="font-size:13px;color:#6b7280;text-align:center;">
		<?php echo wp_kses_post( $unsubscribe_footer_text ); ?>
	</div>
</div>
