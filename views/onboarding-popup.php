<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Onboarding popup.
 *
 * All copy and state comes from the controller via get_onboarding_popup_view_vars().
 * Behaviour lives in assets/js/onboarding-popup.js.
 *
 * @var $this LLAR\Core\AdminUiController
 */

$popup = $this->get_onboarding_popup_view_vars();

if ( ! $popup['should_show'] ) {
	return;
}

$spinner = '<span class="preloader-wrapper"><span class="spinner llar-app-ajax-spinner"></span></span>';

ob_start();
?>
<div class="llar-onboarding-popup__content">
	<div class="logo">
		<img src="<?php echo esc_url( LLA_PLUGIN_URL ); ?>assets/img/icon-logo-menu-dark.png">
	</div>
	<div class="llar-onboarding__line">
		<?php foreach ( $popup['steps'] as $step_index => $step_label ) : ?>
		<div class="point__block<?php echo 0 === $step_index ? ' visited active' : ''; ?>" data-step="<?php echo (int) $step_index + 1; ?>">
			<div class="point"></div>
			<div class="description">
				<?php echo esc_html( $step_label ); ?>
			</div>
		</div>
		<?php endforeach ?>
	</div>
	<div class="llar-onboarding__body">
		<div class="title">
			<img src="<?php echo esc_url( LLA_PLUGIN_URL ); ?>assets/css/images/welcome.png">
			<?php echo esc_html( $popup['step1']['title'] ); ?>
		</div>
		<div class="card mx-auto">
			<div class="field-wrap">
				<div class="field-title">
					<?php echo esc_html( $popup['step1']['intro_title'] ); ?>
				</div>
				<div class="field-desc-add">
					<?php echo nl2br( esc_html( $popup['step1']['intro_body'] ) ); ?>
				</div>
			</div>
		</div>
		<div class="card mx-auto">
			<div class="field-wrap">
				<div class="field-title">
					<?php echo esc_html( $popup['step1']['notify_title'] ); ?>
				</div>
				<div class="field-email field-email--inline">
					<label class="field-email__label" for="llar-subscribe-email">
						<?php echo esc_html( $popup['step1']['email_label'] ); ?>
					</label>
					<input type="text" class="input_border" id="llar-subscribe-email" placeholder="<?php echo esc_attr( $popup['step1']['email_placeholder'] ); ?>"
							value="<?php echo esc_attr( $popup['admin_email'] ); ?>">
				</div>
				<div class="field-desc-additional">
					<?php echo esc_html( $popup['step1']['notify_desc'] ); ?>
				</div>
				<label class="field-checkbox">
					<input type="checkbox" name="lockout_notify_email" value="email"/>
					<span>
						<?php echo esc_html( $popup['step1']['checkbox_label'] ); ?>
					</span>
				</label>
			</div>
		</div>
		<div class="button_block-horizon">
			<button class="button menu__item button__orange" id="llar-subscribe-email-button">
				<?php
				echo esc_html( $popup['step1']['continue_label'] );
				echo $spinner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted spinner markup.
				?>
			</button>
		</div>
	</div>
</div>
<?php
$popup_complete_install_content = ob_get_clean();
?>

<?php
ob_start();
?>
<div class="llar-onboarding__body">
	<div class="title">
		<img src="<?php echo esc_url( LLA_PLUGIN_URL ); ?>assets/css/images/rocket-min.png">
		<?php echo esc_html( $popup['step2']['title'] ); ?>
	</div>
	<div class="title_description">
		<?php echo esc_html( $popup['step2']['subtitle'] ); ?>
	</div>
	<div class="card mx-auto">
		<div class="field-wrap" id="llar-description-step-2">
			<div class="field-desc-add">
				<?php echo esc_html( $popup['step2']['pitch'] ); ?>
			</div>
			<div class="field-email field-email--inline llar-trial-email">
				<label class="field-email__label" for="llar-trial-email">
					<?php echo esc_html( $popup['step2']['email_label'] ); ?>
				</label>
				<input type="email" class="input_border" id="llar-trial-email" placeholder="<?php echo esc_attr( $popup['step2']['email_placeholder'] ); ?>"
						value="<?php echo esc_attr( $popup['admin_email'] ); ?>">
			</div>
			<label class="field-checkbox" for="llar-trial-consent">
				<input type="checkbox" id="llar-trial-consent" value="consent"/>
				<span>
					<?php echo esc_html( $popup['step2']['consent_label'] ); ?>
				</span>
			</label>
		</div>
		<div class="llar-upgrade-subscribe">
			<div class="button_block-horizon button_block-horizon--trial">
				<button class="button menu__item button__orange button--fit llar-disabled" id="llar-limited-upgrade-subscribe">
					<?php echo esc_html( $popup['step2']['yes_label'] ); ?>
					<?php echo $spinner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted spinner markup. ?>
				</button>
				<div class="llar-trial-after-note">
					<?php echo esc_html( $popup['step2']['after_trial_note'] ); ?>
				</div>
				<button type="button" class="button llar-onboarding-skip" id="llar-limited-upgrade-no_subscribe">
					<?php echo esc_html( $popup['step2']['skip_label'] ); ?>
				</button>
			</div>
			<div class="explanations">
				<?php echo wp_kses_post( $popup['step2']['terms'] ); ?>
			</div>
		</div>
	</div>
</div>

<?php
$content_step_2 = ob_get_clean();

$plugin_data = get_plugin_data( LLA_PLUGIN_DIR . 'limit-login-attempts-reloaded.php' );
$script_ver  = ! empty( $plugin_data['Version'] ) ? $plugin_data['Version'] : '1.0.0';

wp_enqueue_script(
	'llar-onboarding-popup',
	LLA_PLUGIN_URL . 'assets/js/onboarding-popup.js',
	array( 'jquery', 'lla-main', 'lla-jquery-confirm' ),
	$script_ver,
	true
);

$onboarding_config = array(
	'errors'       => $popup['errors'],
	'trialReturn'  => $popup['trial_return'],
	'trialLanding' => $popup['trial_landing'],
	'adminEmail'   => $popup['admin_email'],
	'spinnerHtml'  => $spinner,
	'welcomeHtml'  => trim( $popup_complete_install_content ),
	'step2Html'    => trim( $content_step_2 ),
);

wp_add_inline_script(
	'llar-onboarding-popup',
	'window.llar_onboarding = ' . wp_json_encode( $onboarding_config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';',
	'before'
);
