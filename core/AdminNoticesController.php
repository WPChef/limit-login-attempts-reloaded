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
	private static $allowed = array( 'auto-update', 'https-recommended' );

	/**
	 * Get type, CSS class and HTML content for a notice key.
	 *
	 * @param string $notice_key Notice identifier.
	 * @return array|null Array with 'type', 'class', 'content' or null if unknown.
	 */
	private function get_notice_config( $notice_key ) {
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
		$config = $this->get_notice_config( $notice_key );
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
