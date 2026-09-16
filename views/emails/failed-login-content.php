<?php
/**
 * Lockout email body. All copy and URLs come from the controller.
 *
 * Expected variables (set by LockoutNotificationService):
 * @var string $greeting
 * @var string $auto_notice
 * @var string $installed_on_html
 * @var string $details_heading
 * @var string $attempts_line_html
 * @var string $username_line_html
 * @var string $blocked_duration_line
 * @var string $login_address_line_html
 * @var string $dashboard_prompt
 * @var string $dashboard_url
 * @var string $dashboard_button_label
 * @var string $premium_cta_html
 * @var string $site_domain
 * @var string $llar_url
 * @var bool   $show_mu_notice
 * @var string $mu_notice
 * @var string $unsubscribe_footer_text
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

$kses_strong = array( 'strong' => array() );
$kses_link   = array(
	'a' => array(
		'href'   => array(),
		'target' => array(),
		'rel'    => array(),
		'style'  => array(),
	),
);
$kses_strong_link = array_merge( $kses_strong, $kses_link );
?>
<p style="margin:0 0 14px;font-size:14px;line-height:1.5;color:#333333;">
	<?php echo esc_html( $greeting ); ?>
</p>
<p style="margin:0 0 10px;font-size:14px;line-height:1.5;color:#333333;">
	<?php echo esc_html( $auto_notice ); ?>
</p>
<p style="margin:0 0 14px;font-size:14px;line-height:1.5;color:#333333;">
	<?php echo wp_kses( $installed_on_html, $kses_strong ); ?>
</p>
<p style="margin:0 0 8px;font-size:14px;line-height:1.5;color:#333333;">
	<?php echo esc_html( $details_heading ); ?>
</p>
<ul style="margin:0 0 16px;padding-left:18px;font-size:14px;line-height:1.5;color:#333333;">
	<li style="margin-bottom:8px;"><?php echo wp_kses( $attempts_line_html, $kses_strong_link ); ?></li>
	<li style="margin-bottom:8px;"><?php echo wp_kses( $username_line_html, $kses_strong ); ?></li>
	<li style="margin-bottom:8px;"><?php echo esc_html( $blocked_duration_line ); ?></li>
	<li style="margin-bottom:8px;"><?php echo wp_kses( $login_address_line_html, $kses_strong_link ); ?></li>
</ul>
<p style="margin:0 0 16px;font-size:14px;line-height:1.5;color:#333333;">
	<?php echo esc_html( $dashboard_prompt ); ?>
</p>
<p style="margin:0 0 16px;font-size:14px;line-height:1.5;color:#333333;text-align:center;">
	<a href="<?php echo esc_url( $dashboard_url ); ?>" target="_blank" rel="noopener" style="display:inline-block;background:#50c1cd;color:#ffffff;border-radius:30px;padding:10px 20px;text-decoration:none;">
		<?php echo esc_html( $dashboard_button_label ); ?>
	</a>
</p>
<p style="margin:0 0 12px;font-size:14px;line-height:1.5;color:#333333;">
	<?php echo wp_kses( $premium_cta_html, $kses_link ); ?>
</p>
<?php include LLA_PLUGIN_DIR . 'views/emails/failed-login-faq.php'; ?>
<?php if ( ! empty( $show_mu_notice ) ) : ?>
<p style="margin:0 0 12px;font-size:13px;line-height:1.5;color:#4b5563;">
	<em><?php echo esc_html( $mu_notice ); ?></em>
</p>
<?php endif; ?>
<?php include LLA_PLUGIN_DIR . 'views/emails/footer-unsubscribe-text.php'; ?>
