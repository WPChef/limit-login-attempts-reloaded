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
							<p class="description">
								<?php echo esc_html__( 'Before enabling 2FA, you must download your rescue links. These links allow you to regain access to your site if you lose access to your 2FA device.', 'limit-login-attempts-reloaded' ); ?>
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
							<div id="llar-rescue-links-loading" class="llar-rescue-links-loading"><?php echo esc_html__( 'Generating rescue links...', 'limit-login-attempts-reloaded' ); ?></div>
							<div class="llar-rescue-links-list" id="llar-rescue-links-list" style="display: none;"></div>
							<div class="llar-rescue-copy-row" style="display: none;">
								<button type="button" class="button llar-copy-rescue-links" title="<?php echo esc_attr__( 'Copy to clipboard', 'limit-login-attempts-reloaded' ); ?>" aria-label="<?php echo esc_attr__( 'Copy to clipboard', 'limit-login-attempts-reloaded' ); ?>">📋 <?php echo esc_html__( 'Copy to clipboard', 'limit-login-attempts-reloaded' ); ?></button>
								<span class="llar-copy-feedback" id="llar-copy-feedback" aria-live="polite"></span>
							</div>
							<div class="button_block-single llar-rescue-pdf-row" style="display: none;">
								<button type="button" class="button menu__item button__orange llar-download-pdf">
									<?php echo esc_html__( 'Download as PDF', 'limit-login-attempts-reloaded' ); ?>
								</button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
