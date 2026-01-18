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
	var rescuePopupShown = false;
	var rescueCodesDownloaded = false;
	var rescueModal = null;
	var htmlContentForPDF = null;

	<?php if ( isset( $this->mfa_show_rescue_popup ) && $this->mfa_show_rescue_popup ) : ?>
	// Show popup if flag is set (MFA enabled without codes)
	showRescuePopup();
	<?php endif; ?>

	// Handle form submission - check if MFA is being enabled
	$('form').on('submit', function(e) {
		var $form = $(this);
		var $mfaCheckbox = $('#mfa_enabled');
		
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

		var popupContent = $('#llar-mfa-rescue-popup-content').html();

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
					var $button = $(this);
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
		var displayHtml = $('#llar-rescue-links-display').html();
		
		// Create temporary container to build the list
		var $tempContainer = $('<div>').html(displayHtml);
		var $linksList = $tempContainer.find('#llar-rescue-links-list');
		
		// Clear previous content
		$linksList.empty();
		
		// Create ordered list of links
		var $list = $('<ol class="llar-rescue-links-ol"></ol>');
		urls.forEach(function(url, index) {
			var $listItem = $('<li class="llar-rescue-link-item"></li>');
			var $link = $('<a href="' + url + '" target="_blank" class="llar-rescue-link" rel="noopener noreferrer">' + url + '</a>');
			$listItem.append($link);
			$list.append($listItem);
		});
		
		$linksList.append($list);
		
		// Update modal content with the new HTML
		rescueModal.setContent($tempContainer.html());
		
		// Force left alignment after content is set
		setTimeout(function() {
			var $modalCard = rescueModal.$content.find('.card');
			var $modalFieldWrap = rescueModal.$content.find('.field-wrap');
			var $modalLinksList = rescueModal.$content.find('.llar-rescue-links-list');
			
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
		// Check if html2pdf is available
		if (typeof html2pdf === 'undefined') {
			$.alert({
				title: '<?php echo esc_js( __( 'Error', 'limit-login-attempts-reloaded' ) ); ?>',
				content: '<?php echo esc_js( __( 'PDF library not loaded. Please refresh the page and try again.', 'limit-login-attempts-reloaded' ) ); ?>',
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
		var existingDiv = document.getElementById('llar-pdf-temp-container');
		if (existingDiv) {
			document.body.removeChild(existingDiv);
		}

		// Create a wrapper div that will contain our content
		var wrapperDiv = document.createElement('div');
		wrapperDiv.id = 'llar-pdf-temp-container';
		wrapperDiv.style.position = 'fixed';
		wrapperDiv.style.top = '0';
		wrapperDiv.style.left = '0';
		wrapperDiv.style.width = '750px'; // A4 content width
		wrapperDiv.style.backgroundColor = '#ffffff';
		wrapperDiv.style.zIndex = '9999';
		wrapperDiv.style.opacity = '0';
		wrapperDiv.style.pointerEvents = 'none';
		wrapperDiv.style.overflow = 'visible';
		
		// Create inner div with the actual content
		var contentDiv = document.createElement('div');
		contentDiv.style.width = '750px';
		contentDiv.style.margin = '0 auto';
		contentDiv.style.backgroundColor = '#ffffff';
		contentDiv.innerHTML = htmlContent;
		wrapperDiv.appendChild(contentDiv);
		
		document.body.appendChild(wrapperDiv);

		// Wait for content to render
		setTimeout(function() {
			// Check if element has content
			if (!contentDiv.innerHTML || contentDiv.innerHTML.trim() === '' || contentDiv.children.length === 0) {
				console.error('Content div has no content');
				console.error('HTML content:', htmlContent.substring(0, 200));
				if (document.body.contains(wrapperDiv)) {
					document.body.removeChild(wrapperDiv);
				}
				$.alert({
					title: '<?php echo esc_js( __( 'Error', 'limit-login-attempts-reloaded' ) ); ?>',
					content: '<?php echo esc_js( __( 'Failed to prepare content for PDF. Please try again.', 'limit-login-attempts-reloaded' ) ); ?>',
					type: 'red'
				});
				return;
			}

			// Log for debugging
			console.log('Generating PDF from element with height:', contentDiv.scrollHeight);

			// Generate PDF with A4 size (210mm x 297mm = 8.27in x 11.69in)
			var opt = {
				margin: [0.4, 0.4, 0.4, 0.4], // Smaller margins for A4
				filename: 'llar-2fa-rescue-links.pdf',
				image: { 
					type: 'jpeg', 
					quality: 0.98 
				},
				html2canvas: { 
					scale: 2,
					useCORS: true,
					letterRendering: true,
					logging: false,
					width: 750, // Fixed width for A4 content
					height: contentDiv.scrollHeight,
					windowWidth: 750,
					backgroundColor: '#ffffff'
				},
				jsPDF: { 
					unit: 'mm', 
					format: 'a4', // A4 format
					orientation: 'portrait'
				},
				pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
			};

			// Generate PDF from the content div, not wrapper
			html2pdf()
				.set(opt)
				.from(contentDiv)
				.save()
				.then(function() {
					console.log('PDF generated successfully');
					// Cleanup
					if (document.body.contains(wrapperDiv)) {
						document.body.removeChild(wrapperDiv);
					}
				})
				.catch(function(error) {
					console.error('PDF generation error:', error);
					console.error('HTML content length:', htmlContent ? htmlContent.length : 0);
					console.error('Content div innerHTML length:', contentDiv.innerHTML ? contentDiv.innerHTML.length : 0);
					console.error('Content div children count:', contentDiv.children.length);
					console.error('Content div scrollHeight:', contentDiv.scrollHeight);
					
					$.alert({
						title: '<?php echo esc_js( __( 'Error', 'limit-login-attempts-reloaded' ) ); ?>',
						content: '<?php echo esc_js( __( 'Failed to generate PDF. Please check browser console (F12) for details.', 'limit-login-attempts-reloaded' ) ); ?>',
						type: 'red'
					});
					
					// Cleanup
					if (document.body.contains(wrapperDiv)) {
						document.body.removeChild(wrapperDiv);
					}
				});
		}, 500);
	}
});
</script>

