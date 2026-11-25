<?php
/**
 * Extensions Page
 *
 * @var LLAR\Core\LimitLoginAttempts $this
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

if ( ! function_exists( 'llar_get_extension_status' ) ) {
	/**
	 * Get the install status of an extension
	 *
	 * @param string $plugin_file Plugin basename (dir/file.php).
	 *
	 * @return string 'active' | 'inactive' | 'not-installed'
	 */
	function llar_get_extension_status( $plugin_file ) {

		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( is_plugin_active( $plugin_file ) ) {
			return 'active';
		}

		if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin_file ) ) {
			return 'inactive';
		}

		return 'not-installed';
	}
}

$extensions = require LLA_PLUGIN_DIR . '/resources/extensions-list.php';
?>

<div id="llar-setting-page-extensions" class="llar-extensions-page">

	<h3 class="title_page">
		<img src="<?php echo esc_url( LLA_PLUGIN_URL . 'assets/css/images/icon-gears.png' ); ?>" alt="">
		<?php esc_html_e( 'Extensions', 'limit-login-attempts-reloaded' ); ?>
	</h3>

	<div class="llar-extensions-intro">
		<p>
			<?php
			esc_html_e(
				'Discover more plugins developed or handpicked by our team. From security enhancements to other useful tools, these are solutions we trust and recommend to make your WordPress experience even better.',
				'limit-login-attempts-reloaded'
			);
			?>
		</p>
	</div>

	<div class="llar-extensions-grid">
		<?php foreach ( $extensions as $extension ) : ?>
			<?php
			$extension_status = llar_get_extension_status( $extension['file'] );
			$is_active        = ( 'active' === $extension_status );
			$info_url         = add_query_arg(
				array(
					'tab'       => 'plugin-information',
					'plugin'    => $extension['slug'],
					'TB_iframe' => 'true',
					'width'     => '600',
					'height'    => '550',
				),
				admin_url( 'plugin-install.php' )
			);
			?>
			<div class="llar-extension-card" data-slug="<?php echo esc_attr( $extension['slug'] ); ?>">

				<div class="llar-extension-card__header">
					<img class="llar-extension-card__icon"
						src="<?php echo esc_url( $extension['icon'] ); ?>"
						alt="<?php echo esc_attr( $extension['name'] ); ?>">
					<a class="llar-extension-card__name"
						href="<?php echo esc_url( $extension['url'] ); ?>"
						target="_blank" rel="noopener noreferrer">
						<?php echo esc_html( $extension['name'] ); ?>
					</a>
				</div>

				<div class="llar-extension-card__description">
					<?php echo esc_html( $extension['description'] ); ?>
				</div>

				<div class="llar-extension-card__footer">
					<a class="llar-extension-info thickbox open-plugin-details-modal"
						href="<?php echo esc_url( $info_url ); ?>">
						<?php esc_html_e( 'Info', 'limit-login-attempts-reloaded' ); ?>
					</a>
					<button type="button"
						class="button button__orange llar-extension-install<?php echo $is_active ? ' llar-extension-install--active' : ''; ?>"
						data-slug="<?php echo esc_attr( $extension['slug'] ); ?>"
						<?php disabled( $is_active ); ?>>
						<?php
						echo $is_active
							? esc_html__( 'Active', 'limit-login-attempts-reloaded' )
							: esc_html__( 'Install', 'limit-login-attempts-reloaded' );
						?>
					</button>
				</div>

			</div>
		<?php endforeach; ?>
	</div>

</div>
