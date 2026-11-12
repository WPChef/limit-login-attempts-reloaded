/**
 * Onboarding popup (Welcome → Completion).
 *
 * Expects window.llar_onboarding config and depends on lla-main helpers:
 * llar_ajax_callback_post_wp, llar_activate_license_key, llar_is_valid_email,
 * llar_micro_cloud_error_message.
 */
( function ( $ ) {
	'use strict';

	const disabled = 'llar-disabled';
	const hidden = 'llar-hidden';
	const visibility = 'llar-visibility';

	$( document ).ready( function () {
		const cfg = window.llar_onboarding;
		if ( ! cfg ) {
			return;
		}

		const $body = $( 'body' );
		const errors = cfg.errors || {};
		const trialReturn = cfg.trialReturn || {};
		const trialLanding = cfg.trialLanding || {};
		const adminEmail = cfg.adminEmail || '';
		const spinnerHtml = cfg.spinnerHtml || '';
		const welcomeHtml = cfg.welcomeHtml || '';
		const step2Html = cfg.step2Html || '';

		let onboardingCompleted = false;
		let isCompleting = false;

		const buildTrialLandingUrl = function ( email ) {
			return trialLanding.base_url
				+ '?id=' + encodeURIComponent( trialLanding.info_id )
				+ '&url=' + encodeURIComponent( trialLanding.return_url )
				+ '&email=' + encodeURIComponent( email || '' );
		};

		const scrubOnboardingReturnParams = function () {
			try {
				const url = new URL( window.location.href );
				// Drop return-flow flags so Basic / activation reload lands on a
				// clean dashboard URL and does not reopen Trial over the toast.
				url.searchParams.delete( 'onboarding' );
				url.searchParams.delete( 'setup_code' );
				url.searchParams.delete( 'llar_trial_token' );
				window.history.replaceState( {}, document.title, url.toString() );
			} catch ( e ) {}
		};

		const reloadAfterOnboardingComplete = function () {
			scrubOnboardingReturnParams();
			window.location.reload();
		};

		const jumpToStep2 = function () {
			const step_line = $( '.llar-onboarding__line .point__block' );
			step_line.removeClass( 'active' );
			step_line.filter( '[data-step="1"], [data-step="2"]' ).addClass( 'visited' );
			step_line.filter( '[data-step="2"]' ).addClass( 'active' );
		};

		const showAjaxError = function ( message ) {
			$.alert( {
				title: false,
				content: $( '<div/>' ).text( message ).html(),
				type: 'red',
			} );
		};

		const unlockBodyAndCloseIcon = function ( $closeIcon ) {
			$body.removeClass( disabled );
			if ( $closeIcon && $closeIcon.length ) {
				$closeIcon.removeClass( hidden );
			}
		};

		const completeOnboarding = function ( outcome ) {
			if ( isCompleting ) {
				return Promise.reject( new Error( 'completing' ) );
			}

			isCompleting = true;
			const $closeIcon = $( '.jconfirm-closeIcon' );
			$closeIcon.addClass( hidden );
			$body.addClass( disabled );

			return llar_ajax_callback_post_wp( ajaxurl, {
				action: 'dismiss_onboarding_popup',
				sec: llar_vars.nonce_dismiss_onboarding_popup,
				outcome: outcome || 'basic'
			} ).then( function () {
				onboardingCompleted = true;
				$closeIcon.remove();
				reloadAfterOnboardingComplete();
			} ).catch( function ( err ) {
				isCompleting = false;
				unlockBodyAndCloseIcon( $closeIcon );
				showAjaxError( errors.complete_failed );
				return Promise.reject( err );
			} );
		};

		// Server already persisted toast + onboarding_popup_shown in app_setup.
		const finishAfterSuccessfulActivation = function () {
			onboardingCompleted = true;
			$( '.jconfirm-closeIcon' ).remove();
			reloadAfterOnboardingComplete();
		};

		const bindStep2Handlers = function ( email ) {
			const $limited_upgrade_subscribe = $( '#llar-limited-upgrade-subscribe' );
			const $limited_upgrade_no_subscribe = $( '#llar-limited-upgrade-no_subscribe' );
			const $block_upgrade_subscribe = $( '.llar-upgrade-subscribe' );
			const $description = $( '#llar-description-step-2' );
			const $trial_email = $( '#llar-trial-email' );
			const $trial_consent = $( '#llar-trial-consent' );
			const spinner = '.preloader-wrapper .spinner';

			if ( email === '' || email === null ) {
				email = adminEmail;
			}

			if ( $trial_email.length && email ) {
				$trial_email.val( email );
			}

			const syncTrialCtaFromConsent = function () {
				if ( $trial_consent.prop( 'checked' ) ) {
					$limited_upgrade_subscribe.removeClass( disabled );
				} else {
					$limited_upgrade_subscribe.addClass( disabled );
				}
			};

			$limited_upgrade_no_subscribe.off( 'click.llarTrial' ).on( 'click.llarTrial', function ( e ) {
				e.preventDefault();
				e.stopImmediatePropagation();
				if ( $limited_upgrade_no_subscribe.hasClass( disabled ) || isCompleting ) {
					return;
				}
				$limited_upgrade_no_subscribe.addClass( disabled );
				completeOnboarding( 'basic' ).catch( function () {
					$limited_upgrade_no_subscribe.removeClass( disabled );
				} );
			} );

			// Consent gate: CTA stays disabled until the consent checkbox is ticked.
			$trial_consent.off( 'change.llarTrial' ).on( 'change.llarTrial', syncTrialCtaFromConsent );
			syncTrialCtaFromConsent();

			$limited_upgrade_subscribe.off( 'click.llarTrial' ).on( 'click.llarTrial', function ( e ) {
				e.preventDefault();
				e.stopImmediatePropagation();

				if ( $limited_upgrade_subscribe.hasClass( disabled ) || ! $trial_consent.prop( 'checked' ) ) {
					syncTrialCtaFromConsent();
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
					.append( spinnerHtml );
				$( '.llar-onboarding__body .card' ).append( $loadinfo );

				const restoreTrialStepUi = function () {
					unlockBodyAndCloseIcon( $( '.jconfirm-closeIcon' ) );
					$loadinfo.remove();
					$description.removeClass( 'llar-display-none' );
					$block_upgrade_subscribe.removeClass( 'llar-display-none' );
					syncTrialCtaFromConsent();
				};

				llar_activate_license_key( trialReturn.setup_code )
					.then( function () {
						scrubOnboardingReturnParams();
						finishAfterSuccessfulActivation();
					} )
					.catch( function ( response ) {
						restoreTrialStepUi();
						// Keep setup_code/llar_trial_token in the URL: the
						// server only burns the token on success, so a page
						// reload must be able to retry the activation.
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
			$( '.llar-onboarding__body' ).replaceWith( step2Html );
			bindStep2Handlers( email );
		};

		const bindWelcomeHandlers = function () {
			const $subscribe_email = $( '#llar-subscribe-email' );
			const $subscribe_email_button = $( '#llar-subscribe-email-button' );
			const $subscribe_spinner = $subscribe_email_button.find( '.preloader-wrapper .spinner' );

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

				llar_ajax_callback_post_wp( ajaxurl, {
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
						showAjaxError( errors.subscribe_failed );
					} );
			} );
		};

		$.dialog( {
			title: false,
			content: welcomeHtml,
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
					reloadAfterOnboardingComplete();
					return false;
				}
				if ( isCompleting ) {
					return false;
				}
				return true;
			},
			backgroundDismiss: false,
			// Mouse-driven UI only; Escape would also dismiss nested $.alert dialogs.
			escapeKey: false,
			onClose: function () {
				if ( ! onboardingCompleted && ! isCompleting ) {
					llar_ajax_callback_post_wp( ajaxurl, {
						action: 'dismiss_onboarding_popup',
						sec: llar_vars.nonce_dismiss_onboarding_popup
					} ).catch( function () {
						// Dismiss without toast is best-effort.
					} );
				}

				$body.removeClass( disabled );
				$body.css( 'overflow', '' );
			},
			buttons: {},
			onContentReady: function () {
				this.$contentPane.attr( 'tabindex', '-1' ).focus();
			},
			onOpenBefore: function () {
				$body.css( 'overflow', 'hidden' );

				if ( trialReturn.open_step_2 ) {
					showTrialStep( adminEmail );
				} else {
					bindWelcomeHandlers();
				}
			}
		} );
	} );
} )( jQuery );
