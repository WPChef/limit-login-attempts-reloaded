<?php
/**
 * MFA Verification Form Template
 *
 * @package Limit_Login_Attempts_Reloaded
 */

if ( defined( 'ABSPATH' ) === false ) {
	exit;
}

add_action( 'login_enqueue_scripts', 'llar_enqueue_core_login_styles' );
function llar_enqueue_core_login_styles() {
	$styles = array( 'dashicons', 'buttons', 'forms', 'l10n', 'login' );

	foreach ( $styles as $style ) {
		wp_enqueue_style( $style );
	}
}

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title><?php esc_html_e( 'MFA Verification', 'limit-login-attempts-reloaded' ); ?></title>
	<?php
	wp_admin_css( 'login', true );
	do_action( 'login_enqueue_scripts' );
	do_action( 'login_head' );
	?>
</head>
<body class="login js login-action-login wp-core-ui  locale-en-us">
<script>document.body.className = document.body.className.replace('no-js','js');</script>

<div id="login">
	<h1><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a></h1>

	<?php
	if ( isset( $_SESSION['mfa_error'] ) ) :
	?>
		<div id="login_error">
			<strong><?php esc_html_e( 'ERROR', 'limit-login-attempts-reloaded' ); ?>:</strong>
			<?php echo esc_html( wp_kses_post( $_SESSION['mfa_error'] ) ); ?>
		</div>
	<?php
		unset( $_SESSION['mfa_error'] );
	endif;
	?>

	<form name="mfaform" id="loginform" action="" method="post">
		<p>
			<label for="mfa_code"><?php esc_html_e( 'Enter MFA Code', 'limit-login-attempts-reloaded' ); ?><br />
				<input type="text" name="mfa_code" id="mfa_code" class="input" value="" size="20" required />
			</label>
		</p>

		<?php wp_nonce_field( 'mfa_form_nonce', 'mfa_nonce' ); ?>

		<p class="submit">
			<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="<?php esc_attr_e( 'Submit', 'limit-login-attempts-reloaded' ); ?>" />
		</p>
	</form>

	<p id="backtoblog">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>">&larr; <?php esc_html_e( 'Back to site', 'limit-login-attempts-reloaded' ); ?></a>
	</p>
</div>

<?php do_action( 'login_footer' ); ?> 
</body>
</html>