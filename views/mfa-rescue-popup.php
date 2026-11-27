<?php
/**
 * MFA Rescue Links Popup
 * Pure HTML/JS template - no PHP code generation here
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

$site_url    = home_url();
$site_domain = wp_parse_url( $site_url, PHP_URL_HOST );
$current_user_email = isset( $current_user_email ) ? $current_user_email : '';
$profile_url = admin_url( 'profile.php' );

?>
<div id="llar-mfa-rescue-popup-content" style="display: none;">
	<div class="micro_cloud_modal__content">
		<div class="micro_cloud_modal__body">
			<div class="card mx-auto">
				<div class="card-body">
					<div class="field-wrap">
						<h3 class="title_page">
							<?php echo esc_html__( 'Download Your Rescue Links', 'limit-login-attempts-reloaded' ); ?>
						</h3>
						<div class="description-page">
							<p class="description llar-rescue-warning" style="color: #FF7C06; font-weight: bold;">
								&#9888;&#65039; <?php echo esc_html__( 'Before enabling 2FA, you must download your rescue links. These links allow you to regain access to your site if you lose access to your 2FA device or encounter another technical issue (e.g., email is not working).', 'limit-login-attempts-reloaded' ); ?>
								<?php echo esc_html__( 'By clicking a rescue link, 2FA will be fully disabled on', 'limit-login-attempts-reloaded' ); ?>
								<strong><?php echo esc_html( $site_domain ); ?></strong>
								<?php echo esc_html__( 'for 1 hour.', 'limit-login-attempts-reloaded' ); ?>
							</p>
						</div>
						<form id="llar-rescue-confirm-form" class="llar-rescue-confirm-form">
						<!-- Rescue links are generated automatically when popup opens -->
						<div id="llar-rescue-links-display">
							<div class="llar-rescue-links-list" id="llar-rescue-links-list"></div>
							<div class="llar-rescue-copy-row" style="display: none;">
								<button type="button" class="button menu__item button__transparent_orange llar-copy-rescue-links" title="<?php echo esc_attr__( 'Copy to clipboard', 'limit-login-attempts-reloaded' ); ?>" aria-label="<?php echo esc_attr__( 'Copy to clipboard', 'limit-login-attempts-reloaded' ); ?>">📋 <?php echo esc_html__( 'Copy to clipboard', 'limit-login-attempts-reloaded' ); ?></button>
								<button type="button" class="button menu__item button__transparent_orange llar-print-rescue-links" title="<?php echo esc_attr__( 'Print', 'limit-login-attempts-reloaded' ); ?>" aria-label="<?php echo esc_attr__( 'Print', 'limit-login-attempts-reloaded' ); ?>">🖨️ <?php echo esc_html__( 'Print', 'limit-login-attempts-reloaded' ); ?></button>
								<button type="button" class="button menu__item button__transparent_orange llar-download-pdf" title="<?php echo esc_attr__( 'Download as PDF', 'limit-login-attempts-reloaded' ); ?>" aria-label="<?php echo esc_attr__( 'Download as PDF', 'limit-login-attempts-reloaded' ); ?>">📄 <?php echo esc_html__( 'Download as PDF', 'limit-login-attempts-reloaded' ); ?></button>
								<span class="llar-copy-feedback" id="llar-copy-feedback" aria-live="polite"></span>
							</div>
							<div class="description-page llar-rescue-save-hint" style="display: none;">
								<p class="description">
									<?php echo esc_html__( 'Save these links in a secure location. Each link can only be used once.', 'limit-login-attempts-reloaded' ); ?>
								</p>
							</div>
						</div>
						<div class="llar-rescue-email-test" style="display: none; visibility: hidden;">
							<h3 class="title_page">
								<?php echo esc_html__( 'Check email delivery', 'limit-login-attempts-reloaded' ); ?>
							</h3>
							<p class="description">
								<?php echo esc_html__( '2FA emails are sent differently. Test delivery before enabling 2FA, even if regular site emails work.', 'limit-login-attempts-reloaded' ); ?>
							</p>
							<?php
							$has_email = ! empty( $current_user_email );
							?>
							<div class="llar-rescue-email-test-row">
								<?php if ( $has_email ) : ?>
									<p class="description llar-rescue-email-test-copy">
										<?php
										echo wp_kses(
											sprintf(
												/* translators: 1: email address, 2: opening link tag to user profile, 3: closing link tag. */
												__( 'Send a test email to %1$s. You can %2$schange this address in your profile%3$s.', 'limit-login-attempts-reloaded' ),
												'<strong>' . esc_html( $current_user_email ) . '</strong>',
												'<a href="' . esc_url( $profile_url ) . '" target="_blank" rel="noopener noreferrer">',
												'</a>'
											),
											array(
												'strong' => array(),
												'a'      => array(
													'href'   => array(),
													'target' => array(),
													'rel'    => array(),
												),
											)
										);
										?>
									</p>
								<?php else : ?>
									<p class="description" style="color: #dc3232;">
										<?php esc_html_e( 'Your account has no email address. Please set an email in your profile; 2FA requires it to send codes.', 'limit-login-attempts-reloaded' ); ?>
									</p>
								<?php endif; ?>
								<button type="button" class="button menu__item button__transparent_orange llar-rescue-send-test-email" id="llar-rescue-send-test-email"<?php disabled( ! $has_email ); ?>>
									<?php echo esc_html__( 'Send', 'limit-login-attempts-reloaded' ); ?>
								</button>
							</div>
							<p class="llar-rescue-email-test-status" id="llar-rescue-email-test-status" aria-live="polite"></p>
							<label style="display: block; margin-top: 12px;">
								<input type="checkbox" id="llar-rescue-email-received-confirm" name="llar_rescue_email_received_confirm" value="1" required aria-required="true"<?php disabled( ! $has_email ); ?>/>
								<?php echo esc_html__( 'I received the test email with the code "T E S T O K".', 'limit-login-attempts-reloaded' ); ?>
							</label>
						</div>
						<div class="llar-rescue-confirm-row" style="display: none; visibility: hidden;">
							<label style="display: block; margin-bottom: 10px;">
								<input type="checkbox" id="llar-rescue-saved-confirm" name="llar_rescue_saved_confirm" value="1" required aria-required="true"/>
								<?php echo esc_html__( 'I have saved the rescue links in a secure location. I have tested email delivery. I am ready to activate 2FA.', 'limit-login-attempts-reloaded' ); ?>
							</label>
							<button type="submit" class="button menu__item button__orange llar-rescue-close-btn llar-rescue-close-btn--inactive">
								<?php echo esc_html__( 'Activate 2FA and Save Settings', 'limit-login-attempts-reloaded' ); ?>
							</button>
						</div>
						</form>
					</div>
				</div>
			</div>
			<div id="llar-rescue-links-loading" class="llar-rescue-links-loading" style="display: none;">
				<span class="llar-rescue-loading-icon-wrap" aria-hidden="true">
					<img class="llar-rescue-loading-icon" src="<?php echo esc_url( LLA_PLUGIN_URL . 'assets/img/llar-spinner.svg' ); ?>" alt="">
				</span>
				<span class="llar-rescue-loading-text"><strong><?php echo esc_html__( 'Generating Rescue Links', 'limit-login-attempts-reloaded' ); ?></strong><span class="llar-rescue-loading-dots">...</span></span>
			</div>
		</div>
	</div>
</div>
