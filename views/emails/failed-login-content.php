<?php
/**
 * Lockout email body — abstract template.
 *
 * This file defines markup structure only. Every string, URL and inline
 * style is provided by LockoutEmailPresenter (built from business facts
 * resolved by LockoutNotificationService) as the $view array.
 *
 * @var array $view {
 *     Render values.
 *
 *     @type array  $styles                       Inline style strings keyed by usage.
 *     @var   array $kses_strong                  Allowed kses tags for body HTML strings.
 *     @type string $greeting
 *     @type string $intro_html
 *     @type string $login_activity_heading
 *     @type string $failed_attempts_line_html
 *     @type string $ip_address_line_html
 *     @type string $username_attempted_line_html
 *     @type string $action_taken_line_html
 *     @type string $login_page_line_html
 *     @type string $no_action_required
 *     @type string $dashboard_url
 *     @type string $dashboard_button_label
 *     @type string $dashboard_helper_text
 *     @type string $additional_protection_heading
 *     @type string $additional_protection_text
 *     @type string $additional_protection_url
 *     @type string $additional_protection_label
 *     @type string $about_notification_heading
 *     @type string $about_notification_html
 *     @type bool   $show_mu_notice
 *     @type string $mu_notice
 *     @type string $unsubscribe_footer_text
 * }
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

$styles                 = $view['styles'];
$unsubscribe_footer_text = $view['unsubscribe_footer_text'];
?>
<p style="<?php echo esc_attr( $styles['paragraph_14'] ); ?>">
	<?php echo esc_html( $view['greeting'] ); ?>
</p>
<p style="<?php echo esc_attr( $styles['paragraph_14'] ); ?>">
	<?php echo wp_kses( $view['intro_html'], $view['kses_strong'] ); ?>
</p>
<p style="<?php echo esc_attr( $styles['paragraph_subhead'] ); ?>">
	<strong><?php echo esc_html( $view['login_activity_heading'] ); ?></strong>
</p>
<ul style="<?php echo esc_attr( $styles['activity_list'] ); ?>">
	<li style="<?php echo esc_attr( $styles['activity_list_item'] ); ?>"><?php echo wp_kses( $view['failed_attempts_line_html'], $view['kses_strong'] ); ?></li>
	<li style="<?php echo esc_attr( $styles['activity_list_item'] ); ?>"><?php echo wp_kses( $view['ip_address_line_html'], $view['kses_strong'] ); ?></li>
	<li style="<?php echo esc_attr( $styles['activity_list_item'] ); ?>"><?php echo wp_kses( $view['username_attempted_line_html'], $view['kses_strong'] ); ?></li>
	<li style="<?php echo esc_attr( $styles['activity_list_item'] ); ?>"><?php echo wp_kses( $view['action_taken_line_html'], $view['kses_strong'] ); ?></li>
	<li style="<?php echo esc_attr( $styles['activity_list_item'] ); ?>"><?php echo wp_kses( $view['login_page_line_html'], $view['kses_strong'] ); ?></li>
</ul>
<p style="<?php echo esc_attr( $styles['paragraph_16'] ); ?>">
	<?php echo esc_html( $view['no_action_required'] ); ?>
</p>
<p style="<?php echo esc_attr( $styles['cta_paragraph'] ); ?>">
	<a href="<?php echo esc_url( $view['dashboard_url'] ); ?>" target="_blank" rel="noopener" style="<?php echo esc_attr( $styles['button_primary'] ); ?>">
		<?php echo esc_html( $view['dashboard_button_label'] ); ?>
	</a>
</p>
<p style="<?php echo esc_attr( $styles['paragraph_16'] ); ?>">
	<?php echo esc_html( $view['dashboard_helper_text'] ); ?>
</p>
<h3 style="<?php echo esc_attr( $styles['heading'] ); ?>">
	<strong><?php echo esc_html( $view['additional_protection_heading'] ); ?></strong>
</h3>
<p style="<?php echo esc_attr( $styles['paragraph_12'] ); ?>">
	<?php echo esc_html( $view['additional_protection_text'] ); ?>
</p>
<p style="<?php echo esc_attr( $styles['cta_paragraph'] ); ?>">
	<a href="<?php echo esc_url( $view['additional_protection_url'] ); ?>" target="_blank" rel="noopener" style="<?php echo esc_attr( $styles['button_premium'] ); ?>">
		<?php echo esc_html( $view['additional_protection_label'] ); ?>
	</a>
</p>
<h3 style="<?php echo esc_attr( $styles['heading'] ); ?>">
	<strong><?php echo esc_html( $view['about_notification_heading'] ); ?></strong>
</h3>
<p style="<?php echo esc_attr( $styles['paragraph_12'] ); ?>">
	<?php echo wp_kses( $view['about_notification_html'], $view['kses_strong'] ); ?>
</p>
<?php if ( ! empty( $view['show_mu_notice'] ) ) : ?>
<p style="<?php echo esc_attr( $styles['mu_notice'] ); ?>">
	<em><?php echo esc_html( $view['mu_notice'] ); ?></em>
</p>
<?php endif; ?>
<?php require LLA_PLUGIN_DIR . 'views/emails/footer-unsubscribe-text.php'; ?>
