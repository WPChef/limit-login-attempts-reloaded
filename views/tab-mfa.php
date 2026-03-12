<?php
/**
 * MFA Settings Page
 *
 * @var string $active_app
 * @var bool $is_active_app_custom
 * @var string $block_sub_group
 *
 */

use LLAR\Core\Config;
use LLAR\Core\LimitLoginAttempts;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * @var $this LLAR\Core\LimitLoginAttempts
 */

// Get MFA settings from controller
$mfa_settings = array();
if ( isset( $this->mfa_controller ) && method_exists( $this->mfa_controller, 'get_settings_for_view' ) ) {
	$mfa_settings = $this->mfa_controller->get_settings_for_view();
}

// Extract settings with defaults
$mfa_enabled              = isset( $mfa_settings['mfa_enabled'] ) ? $mfa_settings['mfa_enabled'] : false;
$mfa_temporarily_disabled = isset( $mfa_settings['mfa_temporarily_disabled'] ) ? $mfa_settings['mfa_temporarily_disabled'] : false;
$mfa_roles                = isset( $mfa_settings['mfa_roles'] ) ? $mfa_settings['mfa_roles'] : array();
$all_roles                = isset( $mfa_settings['prepared_roles'] ) ? $mfa_settings['prepared_roles'] : array();
$editable_roles           = isset( $mfa_settings['editable_roles'] ) ? $mfa_settings['editable_roles'] : array();

// Single source: mfa_block_reason (ssl/salt/openssl) drives all "cannot enable" logic and messages
$mfa_block_reason  = isset( $mfa_settings['mfa_block_reason'] ) ? $mfa_settings['mfa_block_reason'] : null;
$mfa_block_message = isset( $mfa_settings['mfa_block_message'] ) ? $mfa_settings['mfa_block_message'] : '';
$is_mfa_disabled   = ( null !== $mfa_block_reason );

// When MFA is temporarily disabled via rescue, show checkbox as unchecked (effective state) and active.
$mfa_enabled_effective = $mfa_enabled && ! $mfa_temporarily_disabled;

// Current user email (2FA codes are sent to this address for the user logging in).
$current_user       = wp_get_current_user();
$current_user_email = ! empty( $current_user->user_email ) ? $current_user->user_email : '';

$mfa_email_confirm_required = (bool) get_transient( 'llar_mfa_email_confirm_required' );
if ( $mfa_email_confirm_required ) {
	delete_transient( 'llar_mfa_email_confirm_required' );
}

?>
<div id="llar-setting-page" class="llar-admin">
	<form action="<?php echo esc_url( $this->get_options_page_uri( 'mfa' ) ); ?>" method="post">
		<div class="llar-settings-wrap">
			<h3 class="title_page">
				<img src="<?php echo esc_url( LLA_PLUGIN_URL . 'assets/css/images/icon-gears.png' ); ?>">
				<?php esc_html_e( '2FA Settings', 'limit-login-attempts-reloaded' ); ?>
			</h3>
			<?php if ( $mfa_email_confirm_required ) : ?>
				<div class="notice notice-error inline" style="margin: 15px 0; padding: 15px; border-left: 4px solid #dc3232; background: #fff; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
					<p style="margin: 0; font-size: 14px;">
						<?php esc_html_e( 'Please confirm your email address by checking the box below before enabling 2FA. One-time codes will be sent to that address.', 'limit-login-attempts-reloaded' ); ?>
					</p>
				</div>
			<?php endif; ?>
			<?php if ( $is_mfa_disabled ) : ?>
				<div class="notice notice-error inline" style="margin: 15px 0; padding: 15px; border-left: 4px solid #dc3232; background: #fff; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
					<p style="margin: 0 0 8px 0; font-weight: bold; font-size: 16px; color: #dc3232;">
						<?php esc_html_e( '⚠️ 2FA Unavailable', 'limit-login-attempts-reloaded' ); ?>
					</p>
					<p style="margin: 0 0 8px 0; font-size: 14px;">
						<?php echo esc_html( $mfa_block_message ); ?>
					</p>
					<p style="margin: 0; font-size: 14px; font-weight: bold; color: #dc3232;">
						<?php esc_html_e( 'All 2FA settings are disabled until the requirement above is met.', 'limit-login-attempts-reloaded' ); ?>
					</p>
				</div>
			<?php endif; ?>
			<div class="llar-settings-wrap<?php echo $is_mfa_disabled ? ' llar-mfa-disabled-no-ssl' : ''; ?>">
				<table class="llar-form-table">
					<!-- Global MFA Control -->
					<tr>
						<th scope="row" valign="top">
							<?php esc_html_e( 'Enable 2FA', 'limit-login-attempts-reloaded' ); ?>
						</th>
						<td>
							<input type="checkbox" 
									name="mfa_enabled" 
									value="1" 
									id="mfa_enabled"
									<?php checked( $mfa_enabled_effective, true ); ?>
									<?php
									if ( $is_mfa_disabled ) :
										?>
										disabled<?php endif; ?>/>
							<label for="mfa_enabled">
								<?php esc_html_e( 'Enable multi-factor authentication for selected user roles', 'limit-login-attempts-reloaded' ); ?>
							</label>
							<div class="description-secondary" style="margin-top: 10px;">
								<ul style="margin: 0.5em 0 0 1.2em; padding-left: 0;">
									<li><?php echo esc_html__( 'Enabling this feature sends limited data to a secure endpoint at limitloginattempts.com to process 2FA authentication.', 'limit-login-attempts-reloaded' ); ?></li>
									<li><?php echo esc_html__( 'Data may include the site domain, user ID (if known), username, a partially hidden email address (e.g., a***b@*****.***), IP addresses, user role (if known), and browser/device information.', 'limit-login-attempts-reloaded' ); ?></li>
									<li><?php echo esc_html__( 'This data is used only to verify login attempts with 2FA.', 'limit-login-attempts-reloaded' ); ?></li>
									<li><?php echo esc_html__( 'Passwords are never transmitted.', 'limit-login-attempts-reloaded' ); ?></li>
									<li><?php echo esc_html__( 'Any data sent is deleted once the 2FA session ends, unless the site administrator specifies otherwise.', 'limit-login-attempts-reloaded' ); ?></li>
									<li><?php echo esc_html__( 'If a full email address is sent (this will be clearly stated in advance), it is only used to deliver a one-time 2FA code and is never shared with third parties.', 'limit-login-attempts-reloaded' ); ?></li>
								</ul>
							</div>
							<?php if ( $is_mfa_disabled ) : ?>
								<p class="description" style="color: #dc3232; font-weight: bold; margin-top: 8px;">
									<?php echo esc_html( $mfa_block_message ); ?>
								</p>
							<?php elseif ( $mfa_temporarily_disabled ) : ?>
								<p class="description">
									<?php esc_html_e( '2FA is temporarily disabled via rescue link. It will be automatically re-enabled in 1 hour.', 'limit-login-attempts-reloaded' ); ?>
								</p>
							<?php endif; ?>
							<p class="description" style="margin-top: 10px; font-weight: bold;">
								<?php esc_html_e( 'Please note: 2FA is available with email only. SMS and authenticator app support is in development.', 'limit-login-attempts-reloaded' ); ?>
							</p>
						</td>
					</tr>

					<!-- Role-based MFA -->
					<tr>
						<th scope="row" valign="top">
							<?php esc_html_e( 'User Roles', 'limit-login-attempts-reloaded' ); ?>
						</th>
						<td>
							<div class="llar-mfa-roles-list">
								<?php
								foreach ( $all_roles as $role_key => $role_display_name ) :
									// Check if role is admin (role_display_name already sanitized, but we check role_key primarily)
									$is_admin_role = LimitLoginAttempts::is_admin_role( $role_key );
									$is_checked    = in_array( $role_key, $mfa_roles, true );
									?>
									<div class="llar-mfa-role-item">
										<label>
											<input type="checkbox" 
													name="mfa_roles[]" 
													value="<?php echo esc_attr( $role_key ); ?>"
													<?php checked( $is_checked, true ); ?>
													<?php echo $is_mfa_disabled ? 'disabled' : ''; ?>/>
											<span class="llar-role-name">
												<?php echo esc_html( $role_display_name ); ?>
												<?php if ( $is_admin_role ) : ?>
													<span class="llar-role-recommended"><?php echo esc_html__( '(recommended)', 'limit-login-attempts-reloaded' ); ?></span>
												<?php endif; ?>
											</span>
										</label>
									</div>
								<?php endforeach; ?>
							</div>
							<p class="description">
								<?php esc_html_e( 'You can also create custom user groups using a plugin that adds user grouping functionality to WordPress, to better control which users are required to use 2FA.', 'limit-login-attempts-reloaded' ); ?>
							</p>
						</td>
					</tr>

				</table>
			</div>

			<p class="submit">
				<?php wp_nonce_field( 'limit-login-attempts-options' ); ?>
				<input type="hidden" name="mfa_confirm_email" id="llar_mfa_confirm_email_input" value="0"/>
				<input class="button menu__item col button__orange" 
						name="llar_update_mfa_settings"
						value="<?php esc_attr_e( 'Save Settings', 'limit-login-attempts-reloaded' ); ?>"
						type="submit"
						<?php echo $is_mfa_disabled ? 'disabled' : ''; ?>/>
			</p>
		</div>
	</form>
</div>

<?php
// Include rescue popup template
require_once LLA_PLUGIN_DIR . 'views/mfa-rescue-popup.php';
?>

<script type="text/javascript">
jQuery(document).ready(function($) {
	let rescuePopupShown = false;
	let rescueCodesDownloaded = false;
	let rescueModal = null;
	let rescueUrlsForPDF = null;
	let domainForPDF = null;

	// Open popup when user checks "Enable multi-factor authentication"
	$('#mfa_enabled').on('change', function() {
		if ($(this).is(':checked') && !$(this).prop('disabled')) {
			rescuePopupShown = false;
			showRescuePopup();
		}
	});

	<?php if ( isset( $mfa_settings['show_rescue_popup'] ) && $mfa_settings['show_rescue_popup'] ) : ?>
	// Show popup if flag is set (e.g. page load with MFA enabled but no codes yet)
	showRescuePopup();
	<?php endif; ?>

	function showRescuePopup() {
		if (rescuePopupShown) {
			return;
		}
		rescuePopupShown = true;

		const popupContent = $('#llar-mfa-rescue-popup-content').html();

		rescueModal = $.dialog({
			title: false,
			content: popupContent,
			type: 'default',
			typeAnimated: true,
			draggable: false,
			animation: 'top',
			animationBounce: 1,
			offsetTop: 50,
			offsetBottom: 0,
			boxWidth: 1280,
			useBootstrap: false,
			bgOpacity: 0.9,
			closeIcon: function() {
				window.location.reload();
				return false;
			},
			backgroundDismiss: false,
			escapeKey: function() {
				if (!rescueCodesDownloaded) {
					return false;
				}
				const $cb = rescueModal.$content.find('#llar-rescue-saved-confirm');
				if (!$cb.length || !$cb.is(':checked')) {
					return false;
				}
				return true;
			},
			onContentReady: function() {
				// Checkboxes stay hidden (visibility in HTML) until displayRescueLinks runs
				// Bind click on close icon to reload (same pattern as onboarding / micro-cloud popups)
				rescueModal.$content.closest('.jconfirm').find('.jconfirm-closeIcon').off('click.llarMfaRescue').on('click.llarMfaRescue', function(e) {
					e.preventDefault();
					e.stopImmediatePropagation();
					window.location.reload();
				});
				// Form submit: after HTML5 validation, prevent actual submit and trigger main form
				rescueModal.$content.find('#llar-rescue-confirm-form').off('submit.llarRescue').on('submit.llarRescue', function(e) {
					e.preventDefault();
					var hiddenInput = document.getElementById('llar_mfa_confirm_email_input');
					if (hiddenInput) {
						hiddenInput.value = '1';
					}
					rescueModal.close();
					var saveBtn = document.querySelector('#llar-setting-page input[name="llar_update_mfa_settings"]');
					if (saveBtn) {
						saveBtn.click();
					} else {
						document.querySelector('#llar-setting-page form').submit();
					}
					return false;
				});
				runGenerateRescueCodes();

				// Handle PDF download button click
				rescueModal.$content.find('.llar-download-pdf').off('click').on('click', function() {
					if (!rescueUrlsForPDF || !rescueUrlsForPDF.length || !domainForPDF) {
						$.alert({
							title: '<?php echo esc_js( __( 'Error', 'limit-login-attempts-reloaded' ) ); ?>',
							content: '<?php echo esc_js( __( 'No content available for download. Please generate links first.', 'limit-login-attempts-reloaded' ) ); ?>',
							type: 'red'
						});
						return;
					}
					downloadAsPDF(rescueUrlsForPDF, domainForPDF);
				});
			}
		});
	}

	function runGenerateRescueCodes() {
		const $displayContainer = rescueModal.$content.find('#llar-rescue-links-display');
		const $loading = $displayContainer.find('#llar-rescue-links-loading');
		const $list = $displayContainer.find('#llar-rescue-links-list');
		$list.empty();
		$loading.show();
		$displayContainer.find('.llar-rescue-copy-row').hide();
		if (!llar_vars || !llar_vars.nonce_mfa_generate_codes) {
			showRescueError($displayContainer, '<?php echo esc_js( __( 'Security token is missing. Please refresh the page and try again.', 'limit-login-attempts-reloaded' ) ); ?>');
			return;
		}
		$.ajax({
			url: llar_vars.ajax_url || '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
			type: 'POST',
			data: {
				action: 'llar_mfa_generate_rescue_codes',
				nonce: llar_vars.nonce_mfa_generate_codes
			},
			success: function(response) {
				if (response && response.success && response.data && response.data.rescue_urls) {
					rescueUrlsForPDF = response.data.rescue_urls;
					domainForPDF = response.data.domain || '';
					$loading.hide();
					displayRescueLinks(response.data.rescue_urls, response.data.domain);
					rescueCodesDownloaded = true;
				} else {
					const msg = (response && response.data && response.data.message) ? response.data.message : '<?php echo esc_js( __( 'Failed to generate rescue codes.', 'limit-login-attempts-reloaded' ) ); ?>';
					showRescueError($displayContainer, msg);
				}
			},
			error: function(xhr) {
				let msg = '<?php echo esc_js( __( 'Failed to generate rescue codes. Please try again.', 'limit-login-attempts-reloaded' ) ); ?>';
				if (xhr.responseText) {
					try {
						const err = JSON.parse(xhr.responseText);
						if (err.data && err.data.message) { msg = err.data.message; }
					} catch (e) {}
				}
				showRescueError($displayContainer, msg);
			}
		});
	}

	function showRescueError($displayContainer, message) {
		const $loading = $displayContainer.find('#llar-rescue-links-loading');
		const $list = $displayContainer.find('#llar-rescue-links-list');
		$loading.hide();
		$list.empty().show();
		const retryText = '<?php echo esc_js( __( 'Retry', 'limit-login-attempts-reloaded' ) ); ?>';
		$list.html('<p class="llar-rescue-error">' + message + '</p><button type="button" class="button llar-rescue-retry">' + retryText + '</button>');
		$displayContainer.find('.llar-rescue-retry').off('click').on('click', runGenerateRescueCodes);
	}

	function displayRescueLinks(urls, domain) {
		const $displayContainer = rescueModal.$content.find('#llar-rescue-links-display');
		const $linksList = $displayContainer.find('#llar-rescue-links-list');
		$linksList.empty().show();
		const linksText = urls.join('\n');
		const $scrollDiv = $('<div class="llar-rescue-links-scroll" aria-label="Rescue links" role="region"></div>');
		urls.forEach(function(url) {
			$scrollDiv.append($('<div class="llar-rescue-link-line"></div>').text(url));
		});
		$linksList.append($scrollDiv);
		// Force full width: jconfirm can constrain width, so set from content box (innerWidth = width minus padding)
		var $box = rescueModal.$content.closest('.jconfirm-box');
		var contentWidth = $box.length ? $box.innerWidth() : 0;
		if (contentWidth && contentWidth > 0) {
			$displayContainer.css('width', contentWidth + 'px');
			$linksList.css('width', contentWidth + 'px');
			$scrollDiv.css({ 'width': contentWidth + 'px', 'max-width': '100%', 'box-sizing': 'border-box' });
		} else {
			$displayContainer.css('width', '100%');
			$linksList.css('width', '100%');
			$scrollDiv.css({ 'width': '100%', 'max-width': '100%', 'box-sizing': 'border-box' });
		}
		const $feedback = $displayContainer.find('#llar-copy-feedback');
		$displayContainer.find('.llar-copy-rescue-links').off('click').on('click', function() {
			const text = linksText;
			$feedback.removeClass('llar-copy-feedback-visible').text('');
			function showCopied() {
				$feedback.text('<?php echo esc_js( __( 'Copied to clipboard.', 'limit-login-attempts-reloaded' ) ); ?>').addClass('llar-copy-feedback-visible');
				setTimeout(function() { $feedback.removeClass('llar-copy-feedback-visible').text(''); }, 3000);
			}
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(showCopied).catch(function() { fallbackCopy(text, showCopied); });
			} else {
				fallbackCopy(text, showCopied);
			}
		});
		$displayContainer.find('.llar-print-rescue-links').off('click').on('click', function() {
			const printTitle = '<?php echo esc_js( __( 'Rescue Links', 'limit-login-attempts-reloaded' ) ); ?>';
			const printId = 'llar-rescue-print-area';
			const printClass = 'llar-rescue-print-area-offscreen';
			const escapedLines = urls.map(function(url) { return url.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); });
			let content = '<div class="llar-rescue-print-body" style="font-family: sans-serif; padding: 20px;">';
			content += '<h1 style="margin-bottom: 16px;">' + printTitle + '</h1><pre style="white-space: pre-wrap; word-break: break-all;">' + escapedLines.join('\n') + '</pre></div>';
			let area = document.getElementById(printId);
			if (!area) {
				area = document.createElement('div');
				area.id = printId;
				area.className = printClass;
				document.body.appendChild(area);
			} else {
				area.className = printClass;
			}
			area.innerHTML = content;
			const styleId = 'llar-rescue-print-style';
			let styleEl = document.getElementById(styleId);
			if (!styleEl) {
				styleEl = document.createElement('style');
				styleEl.id = styleId;
				styleEl.textContent = '.' + printClass + ' { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; } ';
				styleEl.textContent += '@media print { body > *:not(#' + printId + ') { display: none !important; } #' + printId + ' { display: block !important; position: fixed !important; left: 0 !important; top: 0 !important; right: 0 !important; width: 100% !important; height: auto !important; min-height: auto !important; overflow: visible !important; visibility: visible !important; } }';
				document.head.appendChild(styleEl);
			}
			const cleanup = function() {
				area.innerHTML = '';
				if (styleEl.parentNode) {
					styleEl.parentNode.removeChild(styleEl);
				}
			};
			window.addEventListener('afterprint', cleanup, { once: true });
			setTimeout(cleanup, 10000);
			window.print();
		});
		$displayContainer.find('.llar-rescue-copy-row').show();

		rescueModal.$content.find('.llar-rescue-email-confirm').css('visibility', '');
		rescueModal.$content.find('.llar-rescue-confirm-row').css('visibility', '').show();
		const $confirmRow = rescueModal.$content.find('.llar-rescue-confirm-row');
		const $savedCheckbox = rescueModal.$content.find('#llar-rescue-saved-confirm');
		const $emailConfirmCheckbox = rescueModal.$content.find('#llar-rescue-confirm-email');
		const $closeBtn = rescueModal.$content.find('.llar-rescue-close-btn');
		$savedCheckbox.prop('checked', false);
		if ($emailConfirmCheckbox.length) {
			$emailConfirmCheckbox.prop('checked', false);
		}
		function updateActivateButtonVisual() {
			var savedOk = $savedCheckbox.is(':checked');
			var emailOk = $emailConfirmCheckbox.length > 0 && $emailConfirmCheckbox.is(':checked');
			$closeBtn.toggleClass('llar-rescue-close-btn--inactive', !(savedOk && emailOk));
			$closeBtn.toggleClass('llar-rescue-close-btn--active', savedOk && emailOk);
		}
		updateActivateButtonVisual();
		$savedCheckbox.off('change').on('change', updateActivateButtonVisual);
		if ($emailConfirmCheckbox.length) {
			$emailConfirmCheckbox.off('change').on('change', updateActivateButtonVisual);
		}
		$confirmRow.show();
	}

	function fallbackCopy(text, onSuccess) {
		const $ta = $('<textarea>').val(text).css({ position: 'fixed', left: '-9999px' }).appendTo(document.body);
		$ta[0].select();
		try {
			document.execCommand('copy');
			if (onSuccess) { onSuccess(); }
		} catch (e) {}
		$ta.remove();
	}

	function downloadAsPDF(rescueUrls, domain) {
		if (typeof window.jspdf === 'undefined') {
			$.alert({
				title: '<?php echo esc_js( __( 'Error', 'limit-login-attempts-reloaded' ) ); ?>',
				content: '<?php echo esc_js( __( 'PDF library not loaded. Please refresh the page and try again.', 'limit-login-attempts-reloaded' ) ); ?>',
				type: 'red'
			});
			return;
		}
		const pdfTitlePrefix = '<?php echo esc_js( __( 'LLAR 2FA Rescue Links for', 'limit-login-attempts-reloaded' ) ); ?>';
		const pdfImportantPart1 = '<?php echo esc_js( __( 'Important: By clicking a link above, 2FA will be fully disabled on', 'limit-login-attempts-reloaded' ) ); ?>';
		const pdfImportantPart2 = '<?php echo esc_js( __( 'for 1 hour. Each link can only be used once.', 'limit-login-attempts-reloaded' ) ); ?>';
		const margin = 20;
		const pageW = 210;
		const pageH = 297;
		const textW = pageW - margin * 2;
		const lineHeight = 5;
		const titleFontSize = 16;
		const bodyFontSize = 10;
		const noteFontSize = 9;
		try {
			const jsPDF = window.jspdf.jsPDF;
			const pdf = new jsPDF('p', 'mm', 'a4');
			pdf.setFontSize(titleFontSize);
			pdf.text(pdfTitlePrefix + ' ' + domain, margin, margin + 5);
			let y = margin + 18;
			pdf.setFontSize(bodyFontSize);
			for (let i = 0; i < rescueUrls.length; i++) {
				if (y > pageH - margin - 20) {
					pdf.addPage();
					y = margin;
				}
				const num = (i + 1) + '. ';
				const url = rescueUrls[i];
				const lines = pdf.splitTextToSize(url, textW - pdf.getTextWidth(num));
				const linkY = y - 3;
				const linkH = lines.length * lineHeight + 2;
				pdf.link(margin, linkY, textW, linkH, { url: url });
				pdf.setTextColor(0, 102, 204);
				pdf.text(num, margin, y);
				const numW = pdf.getTextWidth(num);
				pdf.text(lines, margin + numW, y);
				pdf.setTextColor(0, 0, 0);
				y += lines.length * lineHeight + 6;
			}
			y += 8;
			if (y > pageH - margin - 25) {
				pdf.addPage();
				y = margin;
			}
			pdf.setFontSize(noteFontSize);
			const noteLines = pdf.splitTextToSize(pdfImportantPart1 + ' ' + domain + ' ' + pdfImportantPart2, textW);
			pdf.text(noteLines, margin, y);
			pdf.save('llar-2fa-rescue-links.pdf');
		} catch (err) {
			console.error('PDF generation error:', err);
			$.alert({
				title: '<?php echo esc_js( __( 'Error', 'limit-login-attempts-reloaded' ) ); ?>',
				content: '<?php echo esc_js( __( 'Failed to generate PDF. Please check browser console (F12) for details.', 'limit-login-attempts-reloaded' ) ); ?>',
				type: 'red'
			});
		}
	}
});
</script>
<style type="text/css">
/* Styles for disabled MFA settings when SSL is not enabled */
.llar-mfa-disabled-no-ssl {
	opacity: 0.6;
	pointer-events: none;
	position: relative;
}

.llar-mfa-disabled-no-ssl::before {
	content: '';
	position: absolute;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	z-index: 1;
	cursor: not-allowed;
}

.llar-mfa-disabled-no-ssl input[type="checkbox"],
.llar-mfa-disabled-no-ssl input[type="submit"],
.llar-mfa-disabled-no-ssl button {
	cursor: not-allowed !important;
	opacity: 0.5;
}

.llar-mfa-disabled-no-ssl label {
	cursor: not-allowed !important;
}
</style>

