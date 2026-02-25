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

?>
<div id="llar-setting-page" class="llar-admin">
	<form action="<?php echo esc_url( $this->get_options_page_uri( 'mfa' ) ); ?>" method="post">
		<div class="llar-settings-wrap">
			<h3 class="title_page">
				<img src="<?php echo esc_url( LLA_PLUGIN_URL . 'assets/css/images/icon-gears.png' ); ?>">
				<?php esc_html_e( '2FA Settings', 'limit-login-attempts-reloaded' ); ?>
			</h3>
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
									<?php checked( $mfa_enabled, true ); ?>
									<?php
									if ( $mfa_temporarily_disabled || $is_mfa_disabled ) :
										?>
										disabled<?php endif; ?>/>
							<label for="mfa_enabled">
								<?php esc_html_e( 'Enable multi-factor authentication for selected user roles', 'limit-login-attempts-reloaded' ); ?>
							</label>
							<div class="description-secondary" style="margin-top: 10px;">
								<?php echo esc_html__( 'By turning this feature ON, you consent that for the selected user groups and for all visitors without an assigned group (e.g., guests), the following data will be sent to a secure endpoint at limitloginattempts.com to facilitate multi-factor authentication: username, IP address, user group (if known), and user agent. We will use this data only for 2FA/MFA and will delete it from our servers as soon as the 2FA session ends, unless you (the admin) specify otherwise. The passwords will NOT be sent to us.', 'limit-login-attempts-reloaded' ); ?>
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
						</td>
					</tr>

				</table>
			</div>

			<p class="submit">
				<?php wp_nonce_field( 'limit-login-attempts-options' ); ?>
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
	let htmlContentForPDF = null;

	<?php if ( isset( $mfa_settings['show_rescue_popup'] ) && $mfa_settings['show_rescue_popup'] ) : ?>
	// Show popup if flag is set (MFA enabled without codes)
	showRescuePopup();
	<?php endif; ?>

	// Handle form submission - check if MFA is being enabled
	$('form').on('submit', function(e) {
		const $form = $(this);
		const $mfaCheckbox = $('#mfa_enabled');
		
		// Only intercept if MFA checkbox is checked
		if ($mfaCheckbox.is(':checked')) {
			// Check if we need to show popup (no codes exist)
			// This will be handled server-side, but we can prevent submission if popup should be shown
			// The server will set the flag and reload the page
		}
	});

	function showRescuePopup() {
		if (rescuePopupShown) {
			return;
		}
		rescuePopupShown = true;

		const popupContent = $('#llar-mfa-rescue-popup-content').html();

		rescueModal = $.dialog({
			title: false, // Hide default title, we have our own in content
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
			closeIcon: false,
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
				rescueModal.$content.closest('.jconfirm').find('.jconfirm-closeIcon').remove();
				runGenerateRescueCodes();

				// Handle PDF download button click
				rescueModal.$content.find('.llar-download-pdf').off('click').on('click', function() {
					if (!htmlContentForPDF) {
						$.alert({
							title: '<?php echo esc_js( __( 'Error', 'limit-login-attempts-reloaded' ) ); ?>',
							content: '<?php echo esc_js( __( 'No content available for download. Please generate links first.', 'limit-login-attempts-reloaded' ) ); ?>',
							type: 'red'
						});
						return;
					}
					downloadAsPDF(htmlContentForPDF);
				});
			}
		});
	}

	function runGenerateRescueCodes() {
		const $displayContainer = rescueModal.$content.find('#llar-rescue-links-display');
		const $loading = $displayContainer.find('#llar-rescue-links-loading');
		const $list = $displayContainer.find('#llar-rescue-links-list');
		$loading.show().text('<?php echo esc_js( __( 'Generating rescue links...', 'limit-login-attempts-reloaded' ) ); ?>');
		$displayContainer.find('.llar-rescue-copy-row, .llar-rescue-pdf-row').hide();
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
					htmlContentForPDF = response.data.html_content;
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
		const $list = $('<ol class="llar-rescue-links-ol"></ol>');
		urls.forEach(function(url) {
			const $listItem = $('<li class="llar-rescue-link-item"></li>');
			$listItem.append($('<span class="llar-rescue-link-text"></span>').text(url));
			$list.append($listItem);
		});
		$linksList.append($list);
		const $feedback = $displayContainer.find('#llar-copy-feedback');
		$displayContainer.find('.llar-copy-rescue-links').off('click').on('click', function() {
			const text = urls.join(' \n\n');
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
			let content = '<div class="llar-rescue-print-body" style="font-family: sans-serif; padding: 20px;">';
			content += '<h1 style="margin-bottom: 16px;">' + printTitle + '</h1><ol style="padding-left: 24px;">';
			urls.forEach(function(url) {
				content += '<li style="margin: 8px 0; word-break: break-all;">' + url + '</li>';
			});
			content += '</ol></div>';
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
		$displayContainer.find('.llar-rescue-copy-row, .llar-rescue-pdf-row').show();

		const $confirmRow = rescueModal.$content.find('.llar-rescue-confirm-row');
		const $savedCheckbox = rescueModal.$content.find('#llar-rescue-saved-confirm');
		const $closeBtn = rescueModal.$content.find('.llar-rescue-close-btn');
		$savedCheckbox.prop('checked', false);
		$closeBtn.prop('disabled', true);
		$savedCheckbox.off('change').on('change', function() {
			$closeBtn.prop('disabled', !$savedCheckbox.is(':checked'));
		});
		$closeBtn.off('click').on('click', function() {
			if ($savedCheckbox.is(':checked')) {
				rescueModal.close();
			}
		});
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

	function downloadAsPDF(htmlContent) {
		// Check if required libraries are available
		if (typeof html2canvas === 'undefined' || typeof window.jspdf === 'undefined') {
			$.alert({
				title: '<?php echo esc_js( __( 'Error', 'limit-login-attempts-reloaded' ) ); ?>',
				content: '<?php echo esc_js( __( 'PDF libraries not loaded. Please refresh the page and try again.', 'limit-login-attempts-reloaded' ) ); ?>',
				type: 'red'
			});
			return;
		}

		// Debug: check if htmlContent is valid
		if (!htmlContent || htmlContent.trim() === '') {
			console.error('HTML content is empty');
			$.alert({
				title: '<?php echo esc_js( __( 'Error', 'limit-login-attempts-reloaded' ) ); ?>',
				content: '<?php echo esc_js( __( 'No content available for PDF generation.', 'limit-login-attempts-reloaded' ) ); ?>',
				type: 'red'
			});
			return;
		}

		// Remove any existing temp div
		const existingDiv = document.getElementById('llar-pdf-temp-container');
		if (existingDiv) {
			document.body.removeChild(existingDiv);
		}

		// Extract content if it's wrapped in body/html tags
		let contentToUse = htmlContent;
		const tempParser = document.createElement('div');
		tempParser.innerHTML = htmlContent;
		const bodyContent = tempParser.querySelector('body');
		if (bodyContent) {
			contentToUse = bodyContent.innerHTML;
		} else {
			const divContent = tempParser.querySelector('div');
			if (divContent) {
				contentToUse = divContent.outerHTML;
			}
		}
		
		// Create a container for rendering - positioned off-screen
		const tempDiv = document.createElement('div');
		tempDiv.id = 'llar-pdf-temp-container';
		tempDiv.style.position = 'absolute';
		tempDiv.style.top = '-9999px'; // Move far above viewport
		tempDiv.style.left = '-9999px'; // Move far left of viewport
		tempDiv.style.width = '794px'; // A4 width at 96 DPI
		tempDiv.style.padding = '20px';
		tempDiv.style.backgroundColor = '#ffffff';
		tempDiv.style.zIndex = '-1'; // Behind everything
		tempDiv.style.opacity = '1'; // Fully opaque for html2canvas
		tempDiv.style.pointerEvents = 'none';
		tempDiv.style.boxSizing = 'border-box';
		tempDiv.style.overflow = 'hidden';
		tempDiv.innerHTML = contentToUse;
		
		document.body.appendChild(tempDiv);

		// Wait for content to render
		setTimeout(function() {
			// Check if element has content
			if (!tempDiv.innerHTML || tempDiv.innerHTML.trim() === '' || tempDiv.children.length === 0) {
				console.error('Temp div has no content');
				console.error('HTML content:', htmlContent.substring(0, 500));
				if (document.body.contains(tempDiv)) {
					document.body.removeChild(tempDiv);
				}
				$.alert({
					title: '<?php echo esc_js( __( 'Error', 'limit-login-attempts-reloaded' ) ); ?>',
					content: '<?php echo esc_js( __( 'Failed to prepare content for PDF. Please try again.', 'limit-login-attempts-reloaded' ) ); ?>',
					type: 'red'
				});
				return;
			}

			// Log for debugging
			console.log('Generating PDF from element');
			console.log('Element height:', tempDiv.scrollHeight);
			console.log('Element width:', tempDiv.offsetWidth);
			console.log('Element has children:', tempDiv.children.length);

			// Use html2canvas and jsPDF directly
			const { jsPDF } = window.jspdf;
			
			html2canvas(tempDiv, {
				scale: 2,
				useCORS: true,
				logging: false,
				backgroundColor: '#ffffff',
				width: tempDiv.scrollWidth,
				height: tempDiv.scrollHeight
			}).then(function(canvas) {
				const imgData = canvas.toDataURL('image/png');
				const pdf = new jsPDF('p', 'mm', 'a4');
				
				const imgWidth = 210; // A4 width in mm
				const pageHeight = 297; // A4 height in mm
				const imgHeight = (canvas.height * imgWidth) / canvas.width;
				let heightLeft = imgHeight;
				let position = 0;
				
				// Add first page
				pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
				heightLeft -= pageHeight;
				
				// Add additional pages if needed
				while (heightLeft > 0) {
					position = heightLeft - imgHeight;
					pdf.addPage();
					pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
					heightLeft -= pageHeight;
				}
				
				// Save PDF
				pdf.save('llar-2fa-rescue-links.pdf');
				
				console.log('PDF generated successfully');
				
				// Cleanup
				if (document.body.contains(tempDiv)) {
					document.body.removeChild(tempDiv);
				}
			}).catch(function(error) {
				console.error('PDF generation error:', error);
				console.error('Error details:', error);
				
				$.alert({
					title: '<?php echo esc_js( __( 'Error', 'limit-login-attempts-reloaded' ) ); ?>',
					content: '<?php echo esc_js( __( 'Failed to generate PDF. Please check browser console (F12) for details.', 'limit-login-attempts-reloaded' ) ); ?>',
					type: 'red'
				});
				
				// Cleanup
				if (document.body.contains(tempDiv)) {
					document.body.removeChild(tempDiv);
				}
			});
		}, 1000); // Wait 1 second for full rendering
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

