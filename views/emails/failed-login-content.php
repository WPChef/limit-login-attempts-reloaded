<?php
/**
 * Lockout email body. All copy and URLs come from the controller.
 *
 * Expected variables (set by LockoutNotificationService):
 * @var string $greeting
 * @var string $intro_html
 * @var string $login_activity_heading
 * @var string $failed_attempts_line_html
 * @var string $ip_address_line_html
 * @var string $username_attempted_line_html
 * @var string $action_taken_line_html
 * @var string $login_page_line_html
 * @var string $no_action_required
 * @var string $review_activity_url
 * @var string $review_activity_label
 * @var string $dashboard_helper_text
 * @var string $dashboard_button_label
 * @var string $additional_protection_heading
 * @var string $additional_protection_text
 * @var string $additional_protection_url
 * @var string $additional_protection_label
 * @var string $about_notification_heading
 * @var string $about_notification_html
 * @var string $manage_settings_url
 * @var string $manage_settings_label
 * @var bool   $show_mu_notice
 * @var string $mu_notice
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

$kses_strong = array( 'strong' => array() );
?>
<p style="margin:0 0 14px;font-size:14px;line-height:1.5;color:#333333;">
	<?php echo esc_html( $greeting ); ?>
</p>
<p style="margin:0 0 14px;font-size:14px;line-height:1.5;color:#333333;">
	<?php echo wp_kses( $intro_html, $kses_strong ); ?>
</p>
<p style="margin:0 0 8px;font-size:14px;line-height:1.5;color:#333333;">
	<strong><?php echo esc_html( $login_activity_heading ); ?></strong>
</p>
<ul style="margin:0 0 16px;padding-left:18px;font-size:14px;line-height:1.5;color:#333333;">
	<li style="margin-bottom:8px;"><?php echo wp_kses( $failed_attempts_line_html, $kses_strong ); ?></li>
	<li style="margin-bottom:8px;"><?php echo wp_kses( $ip_address_line_html, $kses_strong ); ?></li>
	<li style="margin-bottom:8px;"><?php echo wp_kses( $username_attempted_line_html, $kses_strong ); ?></li>
	<li style="margin-bottom:8px;"><?php echo wp_kses( $action_taken_line_html, $kses_strong ); ?></li>
	<li style="margin-bottom:8px;"><?php echo wp_kses( $login_page_line_html, $kses_strong ); ?></li>
</ul>
<p style="margin:0 0 16px;font-size:14px;line-height:1.5;color:#333333;">
	<?php echo esc_html( $no_action_required ); ?>
</p>
<p style="margin:0 0 16px;font-size:14px;line-height:1.5;color:#333333;">
	<a href="<?php echo esc_url( $review_activity_url ); ?>" target="_blank" rel="noopener" style="color:#fda33b;text-decoration:underline;">
		<strong><?php echo esc_html( $review_activity_label ); ?></strong>
	</a>
</p>
<p style="margin:0 0 16px;font-size:14px;line-height:1.5;color:#333333;text-align:center;">
	<a href="<?php echo esc_url( $review_activity_url ); ?>" target="_blank" rel="noopener" style="display:inline-block;background:#50c1cd;color:#ffffff;border-radius:30px;padding:10px 20px;text-decoration:none;">
		<?php echo esc_html( $dashboard_button_label ); ?>
	</a>
</p>
<p style="margin:0 0 16px;font-size:14px;line-height:1.5;color:#333333;">
	<?php echo esc_html( $dashboard_helper_text ); ?>
</p>
<h3 style="margin:0 0 8px;font-size:16px;line-height:1.5;color:#333333;">
	<strong><?php echo esc_html( $additional_protection_heading ); ?></strong>
</h3>
<p style="margin:0 0 12px;font-size:14px;line-height:1.5;color:#333333;">
	<?php echo esc_html( $additional_protection_text ); ?>
</p>
<p style="margin:0 0 16px;font-size:14px;line-height:1.5;color:#333333;">
	<a href="<?php echo esc_url( $additional_protection_url ); ?>" target="_blank" rel="noopener" style="color:#fda33b;text-decoration:underline;">
		<strong><?php echo esc_html( $additional_protection_label ); ?></strong>
	</a>
</p>
<h3 style="margin:0 0 8px;font-size:16px;line-height:1.5;color:#333333;">
	<strong><?php echo esc_html( $about_notification_heading ); ?></strong>
</h3>
<p style="margin:0 0 12px;font-size:14px;line-height:1.5;color:#333333;">
	<?php echo wp_kses( $about_notification_html, $kses_strong ); ?>
</p>
<p style="margin:0 0 12px;font-size:14px;line-height:1.5;color:#333333;">
	<a href="<?php echo esc_url( $manage_settings_url ); ?>" target="_blank" rel="noopener" style="color:#fda33b;text-decoration:underline;">
		<strong><?php echo esc_html( $manage_settings_label ); ?></strong>
	</a>
</p>
<?php if ( ! empty( $show_mu_notice ) ) : ?>
<p style="margin:0 0 12px;font-size:13px;line-height:1.5;color:#4b5563;">
	<em><?php echo esc_html( $mu_notice ); ?></em>
</p>
<?php endif; ?>
