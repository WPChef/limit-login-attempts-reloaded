<?php

namespace LLAR\Core\Mfa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MFA Rescue PDF HTML service.
 * Single responsibility: build HTML for rescue PDF with path validation.
 */
class MfaRescuePdfService {

	/**
	 * Rescue URL generator
	 *
	 * @var MfaRescueUrlGenerator
	 */
	private $url_generator;

	/**
	 * @param MfaRescueUrlGenerator $url_generator Rescue URL generator
	 */
	public function __construct( MfaRescueUrlGenerator $url_generator ) {
		$this->url_generator = $url_generator;
	}

	/**
	 * Generate HTML for rescue PDF. Validates template path is under LLA_PLUGIN_DIR/views.
	 *
	 * @param array $plain_codes Plain rescue codes
	 * @return string HTML content
	 * @throws \Exception When template path is invalid
	 */
	public function generate_html( $plain_codes ) {
		$domain = wp_parse_url( home_url(), PHP_URL_HOST );

		$rescue_urls = array();
		foreach ( (array) $plain_codes as $code ) {
			$rescue_urls[] = $this->url_generator->get_rescue_url( $code );
		}

		$allowed_dir   = realpath( LLA_PLUGIN_DIR . 'views' );
		$template_path = realpath( LLA_PLUGIN_DIR . 'views/mfa-rescue-pdf.php' );
		if ( false === $allowed_dir || false === $template_path || 0 !== strpos( $template_path, $allowed_dir ) ) {
			throw new \Exception( __( 'Invalid file path for PDF template.', 'limit-login-attempts-reloaded' ) );
		}

		ob_start();
		include $template_path;
		return ob_get_clean();
	}
}
