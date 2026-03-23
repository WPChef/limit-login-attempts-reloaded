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

?>
<div id="llar-mfa-rescue-popup-content" style="display: none;">
	<div class="micro_cloud_modal__content">
		<div class="micro_cloud_modal__body">
			<div class="card mx-auto">
				<div class="card-body">
					<div class="field-wrap">
						<h3 class="title_page">
							<?php echo esc_html__( 'Important: Download Your Rescue Links', 'limit-login-attempts-reloaded' ); ?>
						</h3>
						<div class="description-page">
							<p class="description" style="color: #dc3232; font-weight: bold;">
								&#9888;&#65039; <?php echo esc_html__( 'Before enabling 2FA, you must download your rescue links. These links allow you to regain access to your site if you lose access to your 2FA device or encounter another technical issue (e.g., email is not working).', 'limit-login-attempts-reloaded' ); ?>
							</p>
							<p class="description">
								<strong><?php echo esc_html__( 'Each rescue link can only be used once.', 'limit-login-attempts-reloaded' ); ?></strong>
							</p>
							<p class="description">
								<?php echo esc_html__( 'By clicking a rescue link, 2FA will be fully disabled on', 'limit-login-attempts-reloaded' ); ?>
								<strong><?php echo esc_html( $site_domain ); ?></strong>
								<?php echo esc_html__( 'for 1 hour.', 'limit-login-attempts-reloaded' ); ?>
							</p>
						</div>
						<form id="llar-rescue-confirm-form" class="llar-rescue-confirm-form">
						<div class="llar-rescue-email-confirm" style="margin: 18px 0; padding: 12px 0; border-top: 1px solid #ddd; visibility: hidden;">
							<h4 style="margin: 0 0 8px 0; font-size: 14px;"><?php esc_html_e( 'Your email for 2FA', 'limit-login-attempts-reloaded' ); ?></h4>
							<?php if ( ! empty( $current_user_email ) ) : ?>
								<p class="description" style="margin-bottom: 8px;">
									<strong><?php echo esc_html( $current_user_email ); ?></strong>
								</p>
								<p class="description" style="margin-bottom: 10px;">
									<?php esc_html_e( 'One-time 2FA codes will be sent to this address. Please confirm it is correct before activating.', 'limit-login-attempts-reloaded' ); ?>
								</p>
								<label style="display: block;">
									<input type="checkbox" id="llar-rescue-confirm-email" name="llar_rescue_confirm_email" value="1" required aria-required="true"/>
									<?php esc_html_e( 'I confirm this email address is correct and I will receive 2FA codes here.', 'limit-login-attempts-reloaded' ); ?>
								</label>
							<?php else : ?>
								<p class="description" style="color: #dc3232;">
									<?php esc_html_e( 'Your account has no email address. Please set an email in your profile; 2FA requires it to send codes.', 'limit-login-attempts-reloaded' ); ?>
								</p>
							<?php endif; ?>
						</div>
						<!-- Rescue links are generated automatically when popup opens -->
						<div id="llar-rescue-links-display">
							<h3 class="title_page">
								<?php echo esc_html__( 'Your Rescue Links', 'limit-login-attempts-reloaded' ); ?>
							</h3>
							<div class="description-page">
								<p class="description">
									<?php echo esc_html__( 'Save these links in a secure location. Each link can only be used once.', 'limit-login-attempts-reloaded' ); ?>
								</p>
							</div>
							<div id="llar-rescue-links-loading" class="llar-rescue-links-loading" style="display: none;">
								<span class="llar-rescue-loading-text"><strong><?php echo esc_html__( 'Generating Rescue Links', 'limit-login-attempts-reloaded' ); ?></strong><span class="llar-rescue-loading-dots">...</span></span>
							</div>
							<div class="llar-rescue-links-list" id="llar-rescue-links-list"></div>
							<div class="llar-rescue-copy-row" style="display: none;">
								<button type="button" class="button llar-copy-rescue-links" title="<?php echo esc_attr__( 'Copy to clipboard', 'limit-login-attempts-reloaded' ); ?>" aria-label="<?php echo esc_attr__( 'Copy to clipboard', 'limit-login-attempts-reloaded' ); ?>">📋 <?php echo esc_html__( 'Copy to clipboard', 'limit-login-attempts-reloaded' ); ?></button>
								<button type="button" class="button llar-print-rescue-links" title="<?php echo esc_attr__( 'Print', 'limit-login-attempts-reloaded' ); ?>" aria-label="<?php echo esc_attr__( 'Print', 'limit-login-attempts-reloaded' ); ?>">🖨️ <?php echo esc_html__( 'Print', 'limit-login-attempts-reloaded' ); ?></button>
								<button type="button" class="button llar-download-pdf" title="<?php echo esc_attr__( 'Download as PDF', 'limit-login-attempts-reloaded' ); ?>" aria-label="<?php echo esc_attr__( 'Download as PDF', 'limit-login-attempts-reloaded' ); ?>">📄 <?php echo esc_html__( 'Download as PDF', 'limit-login-attempts-reloaded' ); ?></button>
								<span class="llar-copy-feedback" id="llar-copy-feedback" aria-live="polite"></span>
							</div>
						</div>
						<div class="llar-rescue-confirm-row" style="display: none; margin-top: 20px; padding-top: 15px; border-top: 1px solid #ccc; visibility: hidden;">
							<label style="display: block; margin-bottom: 10px;">
								<input type="checkbox" id="llar-rescue-saved-confirm" name="llar_rescue_saved_confirm" value="1" required aria-required="true"/>
								<?php echo esc_html__( 'I have saved my rescue links in a secure location. I am ready to activate 2FA.', 'limit-login-attempts-reloaded' ); ?>
							</label>
							<button type="submit" class="button menu__item button__orange llar-rescue-close-btn llar-rescue-close-btn--inactive">
								<?php echo esc_html__( 'Activate 2FA and Save Settings', 'limit-login-attempts-reloaded' ); ?>
							</button>
						</div>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
