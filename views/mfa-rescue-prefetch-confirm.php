<?php
/**
 * Prefetch confirmation screen for MFA rescue one-time links.
 *
 * Expected variables (set by MfaEndpoint before include):
 *
 * @var string $rescue_prefetch_intro          Intro text (translated).
 * @var string $rescue_prefetch_form_action    Absolute URL for form action.
 * @var string $rescue_prefetch_field_name     POST field name for confirmation (e.g. llar_rescue_confirm).
 * @var string $rescue_prefetch_field_value    Hidden field value.
 * @var string $rescue_prefetch_button_label   Submit button label (translated).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<p><?php echo esc_html( $rescue_prefetch_intro ); ?></p>
<form method="post" action="<?php echo esc_url( $rescue_prefetch_form_action ); ?>">
	<input type="hidden" name="<?php echo esc_attr( $rescue_prefetch_field_name ); ?>" value="<?php echo esc_attr( $rescue_prefetch_field_value ); ?>" />
	<p><button type="submit" class="button button-primary"><?php echo esc_html( $rescue_prefetch_button_label ); ?></button></p>
</form>
