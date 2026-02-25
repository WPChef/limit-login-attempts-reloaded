<?php
/**
 * MFA Rescue Links PDF Template
 * HTML template for PDF generation
 *
 * @var array  $rescue_urls Array of rescue URLs
 * @var string $domain      Site domain name
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}
?>
<div style="font-family: Arial, Helvetica, sans-serif; padding: 20px; background-color: #ffffff; color: #000000; font-size: 14px; width: 100%; box-sizing: border-box;">
	<h1 style="color: #000000; font-size: 20px; font-weight: bold; margin: 0 0 20px 0; padding-bottom: 10px; border-bottom: 2px solid #4ACAD8; text-align: left;">
		<?php esc_html_e( 'LLAR 2FA Rescue Links for', 'limit-login-attempts-reloaded' ); ?> <?php echo esc_html( $domain ); ?>
	</h1>
	<ol style="margin: 0; padding-left: 25px; line-height: 1.6; list-style-type: decimal; color: #000000;">
		<?php foreach ( $rescue_urls as $rescue_url ) : ?>
			<li style="margin-bottom: 12px; padding: 10px; background-color: #f6fbff; border-radius: 4px; word-break: break-all; border: 1px solid #e0f0f5; color: #000000;">
				<span style="color: #0066cc; text-decoration: underline; font-size: 13px; display: block; font-weight: normal;">
					<?php echo esc_html( $rescue_url ); ?>
				</span>
			</li>
		<?php endforeach; ?>
	</ol>
	<div style="margin-top: 25px; padding: 15px; background-color: #fff9e6; border-left: 4px solid #ff7c06; border-radius: 4px;">
		<p style="margin: 0; color: #000000; font-size: 12px; line-height: 1.5;">
			<strong style="color: #000000; font-weight: bold;">Important:</strong> By clicking a link above, 2FA will be fully disabled on <strong style="color: #000000; font-weight: bold;"><?php echo esc_html( $domain ); ?></strong> for 1 hour. Each link can only be used once.
		</p>
	</div>
</div>
