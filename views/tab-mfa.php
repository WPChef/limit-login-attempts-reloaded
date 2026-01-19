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

// Get MFA settings
$mfa_enabled_raw = Config::get( 'mfa_enabled', false );
// Check if MFA is temporarily disabled via rescue code
$mfa_temporarily_disabled = false;
if ( isset( $this->mfa_controller ) && method_exists( $this->mfa_controller, 'is_mfa_temporarily_disabled' ) ) {
	$mfa_temporarily_disabled = $this->mfa_controller->is_mfa_temporarily_disabled();
}
// MFA is considered enabled if it's enabled in config AND not temporarily disabled
$mfa_enabled = $mfa_enabled_raw && ! $mfa_temporarily_disabled;

$mfa_roles = Config::get( 'mfa_roles', array() );
// Ensure $mfa_roles is always an array
if ( ! is_array( $mfa_roles ) ) {
	$mfa_roles = array();
}

// Get prepared roles from controller (with translated and sanitized names)
$all_roles = isset( $this->mfa_prepared_roles ) && ! empty( $this->mfa_prepared_roles ) ? $this->mfa_prepared_roles : array();
$editable_roles = isset( $this->mfa_editable_roles ) && ! empty( $this->mfa_editable_roles ) ? $this->mfa_editable_roles : array();

?>
<div id="llar-setting-page" class="llar-admin">
    <form action="<?php echo $this->get_options_page_uri( 'mfa' ); ?>" method="post">
        <div class="llar-settings-wrap">
            <h3 class="title_page">
                <img src="<?php echo LLA_PLUGIN_URL ?>assets/css/images/icon-gears.png">
				<?php _e( '2FA Settings', 'limit-login-attempts-reloaded' ); ?>
            </h3>
            <div class="description-page">
				<?php _e( 'Configure multi-factor authentication settings for user roles.', 'limit-login-attempts-reloaded' ); ?>
            </div>
            <div class="llar-settings-wrap">
                <table class="llar-form-table">
                    <!-- Global MFA Control -->
                    <tr>
                        <th scope="row" valign="top">
                            <?php _e( 'Enable 2FA', 'limit-login-attempts-reloaded' ); ?>
                        </th>
                        <td>
                            <input type="checkbox" 
                                   name="mfa_enabled" 
                                   value="1" 
                                   id="mfa_enabled"
                                   <?php checked( $mfa_enabled, true ); ?>
                                   <?php if ( $mfa_temporarily_disabled ) : ?>disabled<?php endif; ?>/>
                            <label for="mfa_enabled">
                                <?php _e( 'Enable multi-factor authentication for selected user roles', 'limit-login-attempts-reloaded' ); ?>
                            </label>
                            <?php if ( $mfa_temporarily_disabled ) : ?>
                                <p class="description">
                                    <?php _e( '2FA is temporarily disabled via rescue link. It will be automatically re-enabled in 1 hour.', 'limit-login-attempts-reloaded' ); ?>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <!-- Role-based MFA -->
                    <tr>
                        <th scope="row" valign="top">
                            <?php _e( 'User Roles', 'limit-login-attempts-reloaded' ); ?>
                        </th>
                        <td>
                            <div class="llar-mfa-roles-list">
                                <?php foreach ( $all_roles as $role_key => $role_display_name ) : 
                                    // Check if role is admin (role_display_name already sanitized, but we check role_key primarily)
                                    $is_admin_role = LimitLoginAttempts::is_admin_role( $role_key );
                                    $is_checked = in_array( $role_key, $mfa_roles );
                                ?>
                                    <div class="llar-mfa-role-item">
                                        <label>
                                            <input type="checkbox" 
                                                   name="mfa_roles[]" 
                                                   value="<?php echo esc_attr( $role_key ); ?>"
                                                   <?php checked( $is_checked, true ); ?>/>
                                            <span class="llar-role-name">
                                                <?php echo $role_display_name; // Already sanitized in controller ?>
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

                    <!-- Privacy Notice -->
                    <tr>
                        <td colspan="2">
                            <div class="description-secondary">
                                <?php echo esc_html__( 'By turning this feature ON, you consent that for the selected user groups and for all visitors without an assigned group (e.g., guests), the following data will be sent to a secure endpoint at limitloginattempts.com to facilitate multi-factor authentication: username, IP address, user group (if known), and user agent. We will use this data only for 2FA/MFA and will delete it from our servers as soon as the 2FA session ends, unless you (the admin) specify otherwise. The passwords will NOT be sent to us.', 'limit-login-attempts-reloaded' ); ?>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            <p class="submit">
                <?php wp_nonce_field( 'limit-login-attempts-options' ); ?>
                <input class="button menu__item col button__orange" 
                       name="llar_update_mfa_settings"
                       value="<?php _e( 'Save Settings', 'limit-login-attempts-reloaded' ); ?>"
                       type="submit"/>
            </p>
        </div>
    </form>
</div>

<?php
// Include rescue popup template
include_once LLA_PLUGIN_DIR . 'views/mfa-rescue-popup.php';
?>

<script type="text/javascript">
jQuery(document).ready(function($) {
	let rescuePopupShown = false;
	let rescueCodesDownloaded = false;
	let rescueModal = null;
	let htmlContentForPDF = null;

	<?php if ( isset( $this->mfa_show_rescue_popup ) && $this->mfa_show_rescue_popup ) : ?>
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
			typeAnimated: false,
			draggable: false,
			animation: 'scale',
			animationBounce: 1,
			offsetTop: 50,
			offsetBottom: 0,
			boxWidth: '700px',
			useBootstrap: false,
			bgOpacity: 0.9,
			closeIcon: function() {
				// Prevent closing until codes are generated
				if (!rescueCodesDownloaded) {
					$.alert({
						title: '<?php echo esc_js( __( 'Action Required', 'limit-login-attempts-reloaded' ) ); ?>',
						content: '<?php echo esc_js( __( 'You must generate and download the rescue links before closing this window. 2FA will not be enabled until you download the file.', 'limit-login-attempts-reloaded' ) ); ?>',
						type: 'orange'
					});
					return false; // Prevent closing
				}
				return true; // Allow closing
			},
			backgroundDismiss: false,
			escapeKey: function() {
				// Prevent closing by ESC key until codes are generated
				if (!rescueCodesDownloaded) {
					return false; // Prevent closing
				}
				return true; // Allow closing
			},
			onContentReady: function() {
				// Handle generate button click
				$(document).off('click', '.llar-generate-rescue-links').on('click', '.llar-generate-rescue-links', function() {
					const $button = $(this);
					$button.prop('disabled', true).text('<?php echo esc_js( __( 'Generating...', 'limit-login-attempts-reloaded' ) ); ?>');

					// AJAX request to generate codes
					$.ajax({
						url: llar_vars.ajax_url,
						type: 'POST',
						data: {
							action: 'llar_mfa_generate_rescue_codes',
							nonce: llar_vars.nonce_mfa_generate_codes || ''
						},
						success: function(response) {
							if (response.success && response.data && response.data.rescue_urls) {
								htmlContentForPDF = response.data.html_content;
								
								// Display links in popup
								displayRescueLinks(response.data.rescue_urls, response.data.domain);
								
								// Mark as generated so popup can be closed
								rescueCodesDownloaded = true;
							} else {
								$.alert({
									title: '<?php echo esc_js( __( 'Error', 'limit-login-attempts-reloaded' ) ); ?>',
									content: response.data && response.data.message ? response.data.message : '<?php echo esc_js( __( 'Failed to generate rescue codes.', 'limit-login-attempts-reloaded' ) ); ?>',
									type: 'red'
								});
								$button.prop('disabled', false).text('<?php echo esc_js( __( 'Generate Rescue Links', 'limit-login-attempts-reloaded' ) ); ?>');
							}
						},
						error: function() {
							$.alert({
								title: '<?php echo esc_js( __( 'Error', 'limit-login-attempts-reloaded' ) ); ?>',
								content: '<?php echo esc_js( __( 'Failed to generate rescue codes. Please try again.', 'limit-login-attempts-reloaded' ) ); ?>',
								type: 'red'
							});
							$button.prop('disabled', false).text('<?php echo esc_js( __( 'Generate Rescue Links', 'limit-login-attempts-reloaded' ) ); ?>');
						}
					});
				});

				// Handle PDF download button click
				$(document).off('click', '.llar-download-pdf').on('click', '.llar-download-pdf', function() {
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

	function displayRescueLinks(urls, domain) {
		// Get the display container HTML
		const displayHtml = $('#llar-rescue-links-display').html();
		
		// Create temporary container to build the list
		const $tempContainer = $('<div>').html(displayHtml);
		const $linksList = $tempContainer.find('#llar-rescue-links-list');
		
		// Clear previous content
		$linksList.empty();
		
		// Create ordered list of links
		const $list = $('<ol class="llar-rescue-links-ol"></ol>');
		urls.forEach(function(url, index) {
			const $listItem = $('<li class="llar-rescue-link-item"></li>');
			const $link = $('<a href="' + url + '" target="_blank" class="llar-rescue-link" rel="noopener noreferrer">' + url + '</a>');
			$listItem.append($link);
			$list.append($listItem);
		});
		
		$linksList.append($list);
		
		// Update modal content with the new HTML
		rescueModal.setContent($tempContainer.html());
		
		// Force left alignment after content is set
		setTimeout(function() {
			const $modalCard = rescueModal.$content.find('.card');
			const $modalFieldWrap = rescueModal.$content.find('.field-wrap');
			const $modalLinksList = rescueModal.$content.find('.llar-rescue-links-list');
			
			// Apply inline styles to force left alignment
			$modalCard.css({
				'text-align': 'left',
				'padding-left': '40px',
				'padding-right': '40px'
			});
			
			$modalFieldWrap.css({
				'text-align': 'left',
				'margin-left': '0',
				'margin-right': '0'
			});
			
			$modalLinksList.css({
				'text-align': 'left',
				'margin-left': '0',
				'margin-right': '0',
				'padding-left': '15px',
				'padding-right': '15px'
			});
			
			rescueModal.$content.find('.field-title, .field-desc').css({
				'text-align': 'left'
			});
		}, 100);
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

