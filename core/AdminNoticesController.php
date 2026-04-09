<?php

namespace LLAR\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders admin notices for the LLAR options page.
 * All notices use a single view; content is built from config per notice key.
 */
class AdminNoticesController {

	/**
	 * Allowed notice keys.
	 *
	 * @var array
	 */
	private static $allowed = array( 'auto-update', 'https-recommended', 'https-recommended-mfa', 'debug-foreign-auth-hooks', 'flash' );

	/**
	 * Get type, CSS class and HTML content for a notice key.
	 *
	 * @param string $notice_key Notice identifier.
	 * @param array  $args       Optional. For 'flash' key: 'msg', 'is_error'.
	 * @return array|null Array with 'type', 'class', 'content' or null if unknown.
	 */
	private function get_notice_config( $notice_key, array $args = array() ) {
		$text_domain = 'limit-login-attempts-reloaded';
		switch ( $notice_key ) {
			case 'auto-update':
				$content = \__( 'Do you want Limit Login Attempts Reloaded to provide the latest version automatically?', $text_domain );
				$content .= ' <a href="#" class="auto-enable-update-option" data-val="yes">';
				$content .= \__( 'Yes, enable auto-update', $text_domain ) . '</a> | ';
				$content .= '<a href="#" class="auto-enable-update-option" data-val="no">';
				$content .= \__( 'No thanks', $text_domain ) . '</a>';
				return array(
					'type'    => 'notice-error',
					'class'   => 'llar-options-notice llar-auto-update-notice',
					'content' => $content,
				);
			case 'https-recommended':
				return array(
					'type'    => 'notice-warning',
					'class'   => 'llar-options-notice',
					'content' => \__( 'Your site is not using HTTPS. Enabling HTTPS is recommended for better security.', $text_domain ),
				);
			case 'https-recommended-mfa':
				return array(
					'type'    => 'notice-warning',
					'class'   => 'llar-options-notice',
					'content' => \__( 'Before enabling 2FA/MFA, we strongly recommend ensuring your website is accessible only via HTTPS.', $text_domain ),
				);
			case 'debug-foreign-auth-hooks':
				$hooks = isset( $args['hooks'] ) && is_array( $args['hooks'] ) ? $args['hooks'] : array();
				if ( empty( $hooks ) ) {
					return null;
				}

				$content = \__( 'These plugins register additional callbacks on the authenticate filter and may affect LLAR login protection.', $text_domain );
				$grouped_hooks = array();
				foreach ( $hooks as $hook ) {
					$plugin_slug = 'unknown';
					$plugin_label = 'Unknown plugin';
					$plugin_details_url = '';

					if ( ! empty( $hook['plugin'] ) && is_array( $hook['plugin'] ) ) {
						$plugin_name = isset( $hook['plugin']['name'] ) ? (string) $hook['plugin']['name'] : '';
						$plugin_slug_raw = isset( $hook['plugin']['slug'] ) ? (string) $hook['plugin']['slug'] : '';
						$plugin_slug = '' !== $plugin_slug_raw ? \sanitize_key( $plugin_slug_raw ) : 'unknown';
						$plugin_version = isset( $hook['plugin']['version'] ) ? (string) $hook['plugin']['version'] : '';

						if ( '' !== $plugin_name ) {
							$plugin_label = $plugin_name;
							if ( '' !== $plugin_version ) {
								$plugin_label .= ' v' . $plugin_version;
							}
						} elseif ( 'unknown' !== $plugin_slug ) {
							$plugin_label = $plugin_slug;
						}

						if ( 'unknown' !== $plugin_slug ) {
							$plugin_details_url = \admin_url(
								'plugin-install.php?tab=plugin-information&plugin=' . rawurlencode( $plugin_slug ) . '&TB_iframe=true&width=600&height=550'
							);
						}
					}

					if ( ! isset( $grouped_hooks[ $plugin_slug ] ) ) {
						$grouped_hooks[ $plugin_slug ] = array(
							'label' => $plugin_label,
							'url'   => $plugin_details_url,
						);
					}
				}

				$content .= '<br /><ul style="margin-left: 18px; list-style: disc;">';
				foreach ( $grouped_hooks as $plugin_group ) {
					$content .= '<li><strong>' . \esc_html( $plugin_group['label'] ) . '</strong>';
					if ( '' !== $plugin_group['url'] ) {
						$content .= ' - <a href="' . \esc_url( $plugin_group['url'] ) . '" class="thickbox open-plugin-details-modal" target="_blank" rel="noopener noreferrer">'
							. \esc_html__( 'View details', $text_domain ) . '</a>';
					}
					$content .= '</li>';
				}
				$content .= '</ul>';

				return array(
					'type'    => 'notice-warning',
					'class'   => 'llar-options-notice',
					'content' => $content,
				);
			case 'flash':
				$msg      = isset( $args['msg'] ) ? $args['msg'] : '';
				$is_error = ! empty( $args['is_error'] );
				if ( $msg === '' ) {
					return null;
				}
				return array(
					'type'    => $is_error ? 'notice-error' : 'notice-success',
					'class'   => 'llar-options-notice llar-flash-notice',
					'content' => $msg,
				);
			default:
				return null;
		}
	}

	/**
	 * Render a notice by key.
	 *
	 * @param string $notice_key Notice identifier (e.g. 'auto-update', 'https-recommended').
	 * @param array  $args       Optional. Unused; reserved for future use.
	 * @return void
	 */
	public function render( $notice_key, array $args = array() ) {
		$notice_key = \sanitize_key( $notice_key );
		if ( ! in_array( $notice_key, self::$allowed, true ) ) {
			return;
		}
		$config = $this->get_notice_config( $notice_key, $args );
		if ( null === $config ) {
			return;
		}
		$path = LLA_PLUGIN_DIR . 'views/notices.php';
		if ( ! is_readable( $path ) ) {
			return;
		}
		$notice_type    = $config['type'];
		$notice_class   = $config['class'];
		$notice_content = $config['content'];
		include $path;
	}
}
