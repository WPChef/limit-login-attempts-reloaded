<?php

namespace LLAR\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * "Start Your 7-Day Free Trial" popup for every non-onboarding trial CTA.
 *
 * Onboarding keeps its own multi-step flow. Every other entry point (dashboard
 * trial block, settings tab CTAs, Login Firewall recommendation, the WP
 * dashboard widget and the Plugins screen "Free Upgrade" link) opens this
 * popup instead of navigating straight to the marketing landing: the email is
 * typed and consented to right before leaving, so it never sits in a
 * server-rendered href.
 *
 * The CTA anchors keep a no-email landing URL as the no-JS fallback and carry
 * the .llar-trial-start-cta class; a plain left click opens the popup.
 */
class TrialStartPopup {

	/**
	 * Whether the popup is needed on this screen.
	 *
	 * @return bool
	 */
	public static function should_show() {
		return empty( Config::get( 'app_setup_code' ) );
	}

	/**
	 * Register the admin footer hook once.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_footer', array( __CLASS__, 'maybe_render' ) );
	}

	/**
	 * Render the popup once per page on the screens that carry trial CTAs:
	 * the plugin settings pages, the Plugins list and the WP dashboard.
	 *
	 * @return void
	 */
	public static function maybe_render() {
		if ( ! self::should_show() ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$id     = $screen ? $screen->id : '';

		// Covers both menu modes (top-level page and the Settings submenu
		// when "show_top_level_menu_item" is off), the Plugins list in site
		// and network admin, and the WP dashboard widget screen.
		$screens = array(
			'toplevel_page_limit-login-attempts',
			'settings_page_limit-login-attempts',
			'settings_page_limit-login-attempts-network',
			'plugins',
			'plugins-network',
			'dashboard',
		);
		if ( ! in_array( $id, $screens, true ) ) {
			return;
		}

		self::render();
	}

	/**
	 * Print the popup markup, styles and script.
	 *
	 * @return void
	 */
	public static function render() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' === $tab ) {
			$tab = 'dashboard';
		}

		$token = TrialLanding::create_token( 'dashboard' );

		$config = array(
			'landing'     => array(
				'base_url'   => TrialLanding::LANDING_BASE,
				'info_id'    => TrialLanding::INFO_ID_DASHBOARD,
				'return_url' => TrialLanding::dashboard_return_url( $tab ) . '&llar_trial_token=' . rawurlencode( $token ),
			),
			'admin_email' => TrialLanding::resolve_email(),
			'i18n'        => array(
				'title'         => sprintf(
					/* translators: %d: trial length in days. */
					__( 'Start Your %d-Day Free Trial', 'limit-login-attempts-reloaded' ),
					LimitLoginAttempts::TRIAL_DAYS
				),
				'email_label'   => __( 'Your email', 'limit-login-attempts-reloaded' ),
				'consent_label' => __( 'I consent to my domain name and email address being sent to the Limit Login Attempts Reloaded server for trial account registration.', 'limit-login-attempts-reloaded' ),
				'yes_label'     => __( 'Yes, continue to the trial website', 'limit-login-attempts-reloaded' ),
				'close_label'   => __( 'Close', 'limit-login-attempts-reloaded' ),
			),
		);

		$email = $config['admin_email'];
		?>
<style>
.llar-trial-start{position:fixed;inset:0;z-index:100000;display:none;}
.llar-trial-start.is-open{display:block;}
.llar-trial-start__backdrop{position:absolute;inset:0;background:rgba(0,0,0,.55);}
.llar-trial-start__dialog{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:480px;max-width:calc(100vw - 32px);background:#fff;border-radius:8px;box-shadow:0 12px 40px rgba(0,0,0,.25);padding:28px 28px 24px;box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif;}
.llar-trial-start__title{font-size:20px;font-weight:600;color:#1d2327;margin:0 0 20px;padding-right:28px;}
.llar-trial-start__close{position:absolute;top:14px;right:12px;background:none;border:0;cursor:pointer;color:#646970;width:26px;height:26px;font-size:18px;}
.llar-trial-start__close:hover{color:#1d2327;}
.llar-trial-start__row{display:flex;align-items:center;gap:10px;margin-bottom:16px;}
.llar-trial-start__label{flex:0 0 auto;font-size:14px;font-weight:600;color:#1d2327;}
.llar-trial-start__input{flex:1 1 auto;min-width:0;height:36px;padding:0 10px;border:1px solid #8c8f94;border-radius:4px;font-size:14px;box-shadow:none;}
.llar-trial-start__input:focus{border-color:#FF7C06;outline:2px solid rgba(255,124,6,.25);}
.llar-trial-start__input.is-invalid{border-color:#d63638;outline:2px solid rgba(214,54,56,.25);}
.llar-trial-start__consent{display:flex;align-items:flex-start;gap:8px;margin:0 0 20px;font-size:13px;line-height:1.5;color:#3c434a;cursor:pointer;}
.llar-trial-start__consent input{margin-top:2px;}
.llar-trial-start__submit{display:inline-flex;align-items:center;justify-content:center;gap:6px;width:100%;height:42px;padding:0 18px;border:1px solid #FF7C06;border-radius:4px;background:#FF7C06;color:#fff;font-size:14px;font-weight:600;cursor:pointer;box-shadow:0 6px 12px rgba(255,124,6,.5);}
.llar-trial-start__submit:hover{background:#F07200;}
.llar-trial-start__submit .dashicons{font-size:16px;width:16px;height:16px;}
.llar-trial-start__submit.llar-disabled{opacity:.55;cursor:not-allowed;box-shadow:none;}
</style>
<div class="llar-trial-start" id="llar-trial-start" aria-hidden="true">
	<div class="llar-trial-start__backdrop" data-llar-trial-start-close="1"></div>
	<div class="llar-trial-start__dialog" role="dialog" aria-modal="true" aria-labelledby="llar-trial-start-title">
		<button type="button" class="llar-trial-start__close dashicons dashicons-no-alt" data-llar-trial-start-close="1" aria-label="<?php echo esc_attr( $config['i18n']['close_label'] ); ?>"></button>
		<div class="llar-trial-start__title" id="llar-trial-start-title"><?php echo esc_html( $config['i18n']['title'] ); ?></div>
		<div class="llar-trial-start__row">
			<label class="llar-trial-start__label" for="llar-trial-start-email"><?php echo esc_html( $config['i18n']['email_label'] ); ?></label>
			<input type="email" class="llar-trial-start__input" id="llar-trial-start-email" placeholder="<?php echo esc_attr( $config['i18n']['email_label'] ); ?>" value="<?php echo esc_attr( $email ); ?>" autocomplete="off">
		</div>
		<label class="llar-trial-start__consent">
			<input type="checkbox" id="llar-trial-start-consent">
			<span><?php echo esc_html( $config['i18n']['consent_label'] ); ?></span>
		</label>
		<button type="button" class="llar-trial-start__submit llar-disabled" id="llar-trial-start-yes">
			<?php echo esc_html( $config['i18n']['yes_label'] ); ?>
			<span class="dashicons dashicons-external" aria-hidden="true"></span>
		</button>
	</div>
</div>
<script>
( function () {
	var config = <?php echo wp_json_encode( $config ); ?>;
	var popup = document.getElementById( 'llar-trial-start' );
	if ( ! popup || popup.dataset.bound ) {
		return;
	}
	popup.dataset.bound = '1';

	var emailInput = document.getElementById( 'llar-trial-start-email' );
	var consent = document.getElementById( 'llar-trial-start-consent' );
	var yesBtn = document.getElementById( 'llar-trial-start-yes' );

	var infoId = config.landing.info_id;

	var open = function ( ctaInfoId ) {
		// Each CTA carries its own marketing info id.
		infoId = ctaInfoId || config.landing.info_id;
		consent.checked = false;
		yesBtn.classList.add( 'llar-disabled' );
		emailInput.classList.remove( 'is-invalid' );
		emailInput.value = config.admin_email || '';
		popup.classList.add( 'is-open' );
		popup.setAttribute( 'aria-hidden', 'false' );
		emailInput.focus();
	};

	var close = function () {
		popup.classList.remove( 'is-open' );
		popup.setAttribute( 'aria-hidden', 'true' );
	};

	var isValidEmail = function ( value ) {
		return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( value );
	};

	var buildLandingUrl = function ( email ) {
		return config.landing.base_url
			+ '?id=' + encodeURIComponent( infoId )
			+ '&url=' + encodeURIComponent( config.landing.return_url )
			+ '&email=' + encodeURIComponent( email );
	};

	consent.addEventListener( 'change', function () {
		yesBtn.classList.toggle( 'llar-disabled', ! consent.checked );
	} );

	emailInput.addEventListener( 'input', function () {
		emailInput.classList.remove( 'is-invalid' );
	} );

	yesBtn.addEventListener( 'click', function () {
		if ( yesBtn.classList.contains( 'llar-disabled' ) ) {
			return;
		}
		var email = ( emailInput.value || '' ).trim();
		if ( ! isValidEmail( email ) ) {
			emailInput.classList.add( 'is-invalid' );
			emailInput.focus();
			return;
		}
		window.location.href = buildLandingUrl( email );
	} );

	popup.addEventListener( 'click', function ( e ) {
		if ( e.target && e.target.getAttribute( 'data-llar-trial-start-close' ) ) {
			close();
		}
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key && popup.classList.contains( 'is-open' ) ) {
			close();
		}
	} );

	document.addEventListener( 'click', function ( e ) {
		var link = e.target && e.target.closest ? e.target.closest( 'a.llar-trial-start-cta' ) : null;
		if ( ! link ) {
			return;
		}
		if ( e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey ) {
			return;
		}
		e.preventDefault();
		open( link.getAttribute( 'data-llar-info-id' ) );
	} );
} )();
</script>
		<?php
	}
}
