<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Onboarding popup.
 *
 * All copy and state comes from the controller via get_onboarding_popup_view_vars().
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
		<div class="title_description">
			<?php echo nl2br( esc_html( $popup['step1']['subtitle'] ) ); ?>
		</div>
		<div class="card mx-auto">
			<div class="field-wrap">
				<div class="field-title">
					<?php echo esc_html( $popup['step1']['setup_title'] ); ?>
				</div>
				<div class="field-key">
					<input type="text" class="input_border" id="llar-setup-code-field" placeholder="<?php echo esc_attr( $popup['step1']['setup_placeholder'] ); ?>" value="">
					<button class="button menu__item button__orange llar-disabled" id="llar-app-install-btn">
						<?php echo esc_html( $popup['step1']['setup_button'] ); ?>
						<span class="dashicons dashicons-arrow-right-alt"></span>
						<?php echo $spinner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted spinner markup. ?>
					</button>
				</div>
				<div class="field-error"></div>
				<div class="field-desc">
					<?php echo esc_html( $popup['step1']['setup_desc'] ); ?>
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

add_filter(
	'wp_kses_allowed_html',
	function ( $tags, $context ) {
		if ( 'post' === $context ) {
			$tags['form'] = array(
				'action' => true,
				'method' => true,
				'id'     => true,
				'class'  => true,
			);

			$tags['input'] = array(
				'type'        => true,
				'name'        => true,
				'value'       => true,
				'id'          => true,
				'class'       => true,
				'placeholder' => true,
				'checked'     => true,
				'disabled'    => true,
				'readonly'    => true,
			);
		}

		return $tags;
	},
	10,
	2
);

?>

<script>
	;( function ( $ ) {

		const disabled = 'llar-disabled';
		const hidden = 'llar-hidden';
		const visibility = 'llar-visibility';

		$( document ).ready( function () {
			const $body = $( 'body' );

			let onboardingCompleted = false;
			const trialReturn = <?php echo wp_json_encode( $popup['trial_return'] ); ?>;
			const trialLanding = <?php echo wp_json_encode( $popup['trial_landing'] ); ?>;

			const buildTrialLandingUrl = function ( email ) {
				return trialLanding.base_url
					+ '?id=' + encodeURIComponent( trialLanding.info_id )
					+ '&url=' + encodeURIComponent( trialLanding.return_url )
					+ '&email=' + encodeURIComponent( email || '' );
			};

			const scrubOnboardingReturnParams = function () {
				try {
					const url = new URL( window.location.href );
					url.searchParams.delete( 'setup_code' );
					url.searchParams.delete( 'llar_trial_token' );
					window.history.replaceState( {}, document.title, url.toString() );
				} catch ( e ) {}
			};

			const jumpToStep2 = function () {
				const step_line = $( '.llar-onboarding__line .point__block' );
				step_line.removeClass( 'active' );
				step_line.filter( '[data-step="1"], [data-step="2"]' ).addClass( 'visited' );
				step_line.filter( '[data-step="2"]' ).addClass( 'active' );
			};

			const completeOnboarding = function ( outcome ) {
				onboardingCompleted = true;
				$( '.jconfirm-closeIcon' ).remove();
				$body.addClass( disabled );

				return llar_ajax_callback_post( ajaxurl, {
					action: 'dismiss_onboarding_popup',
					sec: llar_vars.nonce_dismiss_onboarding_popup,
					outcome: outcome || 'basic'
				} ).finally( function () {
					window.location.reload();
				} );
			};

			const bindStep2Handlers = function ( email ) {
				const $limited_upgrade_subscribe = $( '#llar-limited-upgrade-subscribe' );
				const $limited_upgrade_no_subscribe = $( '#llar-limited-upgrade-no_subscribe' );
				const $block_upgrade_subscribe = $( '.llar-upgrade-subscribe' );
				const $description = $( '#llar-description-step-2' );
				const $trial_email = $( '#llar-trial-email' );
				const spinner = '.preloader-wrapper .spinner';

				if ( email === '' || email === null ) {
					email = '<?php echo esc_js( $popup['admin_email'] ); ?>';
				}

				if ( $trial_email.length && email ) {
					$trial_email.val( email );
				}

				$limited_upgrade_no_subscribe.off( 'click.llarTrial' ).on( 'click.llarTrial', function ( e ) {
					e.preventDefault();
					e.stopImmediatePropagation();
					if ( $limited_upgrade_no_subscribe.hasClass( disabled ) ) {
						return;
					}
					$limited_upgrade_no_subscribe.addClass( disabled );
					completeOnboarding( 'basic' );
				} );

				// Consent gate: CTA stays disabled until the consent checkbox is ticked.
				const $trial_consent = $( '#llar-trial-consent' );
				$trial_consent.off( 'change.llarTrial' ).on( 'change.llarTrial', function () {
					if ( $( this ).prop( 'checked' ) ) {
						$limited_upgrade_subscribe.removeClass( disabled );
					} else {
						$limited_upgrade_subscribe.addClass( disabled );
					}
				} );

				$limited_upgrade_subscribe.off( 'click.llarTrial' ).on( 'click.llarTrial', function ( e ) {
					e.preventDefault();
					e.stopImmediatePropagation();

					if ( $limited_upgrade_subscribe.hasClass( disabled ) ) {
						return;
					}

					const trialEmail = $trial_email.length
						? $trial_email.val().trim()
						: email;

					if ( ! llar_is_valid_email( trialEmail ) ) {
						$trial_email.trigger( 'focus' );
						return;
					}

					$limited_upgrade_subscribe.addClass( disabled );
					$( this ).find( spinner ).addClass( visibility );
					window.location.href = buildTrialLandingUrl( trialEmail );
				} );

				if ( trialReturn.can_activate && trialReturn.setup_code ) {
					$description.addClass( 'llar-display-none' );
					$block_upgrade_subscribe.addClass( 'llar-display-none' );
					$body.addClass( disabled );
					$( '.jconfirm-closeIcon' ).addClass( hidden );

					const $loadinfo = $( '<div class="llar-trial-loadinfo" style="text-align:center;padding:24px 0;"/>' )
						.append( <?php echo wp_json_encode( $spinner ); ?> );
					$( '.llar-onboarding__body .card' ).append( $loadinfo );

					llar_activate_license_key( trialReturn.setup_code )
						.then( function () {
							scrubOnboardingReturnParams();
							return completeOnboarding( 'trial' );
						} )
						.catch( function ( response ) {
							$body.removeClass( disabled );
							$( '.jconfirm-closeIcon' ).removeClass( hidden );
							$loadinfo.remove();
							$description.removeClass( 'llar-display-none' );
							$block_upgrade_subscribe.removeClass( 'llar-display-none' );
							$limited_upgrade_subscribe.removeClass( disabled );
							scrubOnboardingReturnParams();
							$.alert( {
								title: false,
								content: $( '<div/>' ).text( llar_micro_cloud_error_message( response ) ).html(),
								type: 'red',
							} );
						} );
				}
			};

			const showTrialStep = function ( email ) {
				jumpToStep2();
				$( '.llar-onboarding__body' ).replaceWith( <?php echo wp_json_encode( trim( $content_step_2 ), JSON_HEX_QUOT | JSON_HEX_TAG ); ?> );
				bindStep2Handlers( email );
			};

			const bindWelcomeHandlers = function () {
				const $setup_code_key = $( '#llar-setup-code-field' );
				const $activate_button = $( '#llar-app-install-btn' );
				const $spinner = $activate_button.find( '.preloader-wrapper .spinner' );
				const $subscribe_email = $( '#llar-subscribe-email' );
				const $subscribe_email_button = $( '#llar-subscribe-email-button' );
				const $subscribe_spinner = $subscribe_email_button.find( '.preloader-wrapper .spinner' );

				$setup_code_key.on( 'input', function () {
					if ( $( this ).val().trim() !== '' ) {
						$activate_button.removeClass( disabled );
					} else {
						$activate_button.addClass( disabled );
					}
				} );

				$activate_button.on( 'click', function ( e ) {
					e.preventDefault();

					if ( $activate_button.hasClass( disabled ) ) {
						return;
					}

					const $error = $( '.field-error' );
					const $setup_code = $setup_code_key.val();
					const $closeIcon = $( '.jconfirm-closeIcon' );
					$error.text( '' ).hide();
					$activate_button.addClass( disabled );
					$spinner.addClass( visibility );
					$body.addClass( disabled );
					$closeIcon.addClass( hidden );
					llar_activate_license_key( $setup_code )
						.then( function () {
							return completeOnboarding( 'basic' );
						} )
						.catch( function ( response ) {
							if ( ! response.success && response.data.msg ) {
								$error.text( response.data.msg ).show();
								$body.removeClass( disabled );
								$closeIcon.removeClass( hidden );
								$spinner.removeClass( visibility );
							}
						} )
						.finally( function () {
							$body.removeClass( disabled );
						} );
				} );

				$subscribe_email.on( 'blur', function () {
					const email = $( this ).val().trim();
					if ( ! llar_is_valid_email( email ) ) {
						$subscribe_email_button.addClass( disabled );
					} else {
						$subscribe_email_button.removeClass( disabled );
					}
				} );

				$subscribe_email_button.on( 'click', function () {
					const email = $subscribe_email.val().trim();
					const $is_subscribe = !! $( '.field-checkbox input[name="lockout_notify_email"]' ).prop( 'checked' );

					if ( ! llar_is_valid_email( email ) ) {
						$subscribe_email.trigger( 'focus' );
						return;
					}

					$subscribe_email_button.addClass( disabled );
					$subscribe_spinner.addClass( visibility );
					$body.addClass( disabled );

					llar_ajax_callback_post( ajaxurl, {
						action: 'subscribe_email',
						email: email,
						is_subscribe_yes: $is_subscribe,
						sec: llar_vars.nonce_subscribe_email
					} )
						.then( function () {
							$body.removeClass( disabled );
							$subscribe_email_button.removeClass( disabled );
							$subscribe_spinner.removeClass( visibility );
							showTrialStep( email );
						} )
						.catch( function () {
							$body.removeClass( disabled );
							$subscribe_email_button.removeClass( disabled );
							$subscribe_spinner.removeClass( visibility );
						} );
				} );
			};

			const ondoarding_modal = $.dialog( {
				title: false,
				content: `<?php echo wp_kses_post( trim( $popup_complete_install_content ) ); ?>`,
				type: 'default',
				typeAnimated: true,
				draggable: false,
				animation: 'top',
				animationBounce: 1,
				offsetTop: 50,
				offsetBottom: 0,
				boxWidth: '95%',
				containerFluid: true,
				bgOpacity: 0.9,
				useBootstrap: false,
				closeIcon: function () {
					if ( onboardingCompleted ) {
						window.location.reload();
						return false;
					}
					return true;
				},
				backgroundDismiss: false,
				escapeKey: function () {
					if ( onboardingCompleted ) {
						window.location.reload();
						return false;
					}
					return true;
				},
				onClose: function () {
					$body.removeClass( disabled );
					if ( ! onboardingCompleted ) {
						llar_ajax_callback_post( ajaxurl, {
							action: 'dismiss_onboarding_popup',
							sec: llar_vars.nonce_dismiss_onboarding_popup
						} ).catch( function () {
							$body.removeClass( disabled );
						} ).finally( function () {
							$body.removeClass( disabled );
						} );
					}

					$body.css( 'overflow', '' );
				},
				buttons: {},
				onContentReady: function () {
					this.$contentPane.attr( 'tabindex', '-1' ).focus();
				},
				onOpenBefore: function () {
					$body.css( 'overflow', 'hidden' );

					if ( trialReturn.open_step_2 ) {
						showTrialStep( '<?php echo esc_js( $popup['admin_email'] ); ?>' );
					} else {
						bindWelcomeHandlers();
					}
				}
			} );
		} );

	} )( jQuery )
</script>
