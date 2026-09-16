<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="footer">
	<div class="text">
		<?php
		echo wp_kses(
			__( 'This verification email was sent by<wbr> <wbr><strong><a href="https://www.limitloginattempts.com" target="_blank" rel="noopener">Limit&nbsp;Login&nbsp;Attempts&nbsp;Reloaded</a></strong>.', 'limit-login-attempts-reloaded' ),
			array(
				'strong' => array(),
				'a'      => array(
					'href'   => array(),
					'target' => array(),
					'rel'    => array(),
				),
				'wbr'    => array(),
			)
		);
		?>
	</div>
</div>
