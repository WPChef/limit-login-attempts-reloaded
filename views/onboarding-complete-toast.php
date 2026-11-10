<?php
/**
 * One-shot dashboard toast after onboarding completion.
 *
 * @var string $toast_message Escaped plain-text message.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

if ( empty( $toast_message ) ) {
	return;
}
?>
<div id="llar-onboarding-toast" class="llar-onboarding-toast" role="status" aria-live="polite">
	<span class="llar-onboarding-toast__icon" aria-hidden="true">
		<span class="dashicons dashicons-yes-alt"></span>
	</span>
	<span class="llar-onboarding-toast__text"><?php echo esc_html( $toast_message ); ?></span>
	<button type="button" class="llar-onboarding-toast__close" aria-label="<?php esc_attr_e( 'Dismiss', 'limit-login-attempts-reloaded' ); ?>">
		<span class="dashicons dashicons-no-alt"></span>
	</button>
</div>
<script>
( function () {
	var toast = document.getElementById( 'llar-onboarding-toast' );
	if ( ! toast ) {
		return;
	}
	var hide = function () {
		toast.classList.add( 'llar-onboarding-toast--hide' );
		window.setTimeout( function () {
			if ( toast && toast.parentNode ) {
				toast.parentNode.removeChild( toast );
			}
		}, 350 );
	};
	var closeBtn = toast.querySelector( '.llar-onboarding-toast__close' );
	if ( closeBtn ) {
		closeBtn.addEventListener( 'click', hide );
	}
	window.setTimeout( hide, 10000 );
} )();
</script>
