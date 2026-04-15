<?php

namespace LLAR\Core\Mail;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MailTransportWp implements MailTransportInterface {
	/**
	 * Cached CSS text for email layout `<style>` block.
	 *
	 * @var string|null
	 */
	private static $email_css_text = null;

	/**
	 * @param string       $to
	 * @param string       $subject
	 * @param string       $message
	 * @param array|string $headers
	 * @param array        $attachments
	 * @param bool         $suppress_errors
	 * @param array        $layout Optional layout settings: title, logo_cid, use_layout.
	 *
	 * @return bool
	 */
	public function send( $to, $subject, $message, $headers = array(), $attachments = array(), $suppress_errors = false, $layout = array() ) {
		$layout = is_array( $layout ) ? $layout : array();

		if ( ! array_key_exists( 'use_layout', $layout ) || $layout['use_layout'] ) {
			$message = $this->render_layout( $subject, $message, $layout );
		}

		if ( $suppress_errors ) {
			return (bool) @\wp_mail( $to, $subject, $message, $headers, $attachments );
		}

		return (bool) \wp_mail( $to, $subject, $message, $headers, $attachments );
	}

	/**
	 * Build final HTML email body from content and shared layout templates.
	 *
	 * @param string $subject
	 * @param string $content_html Content-only HTML.
	 * @param array  $layout
	 *
	 * @return string
	 */
	private function render_layout( $subject, $content_html, $layout = array() ) {
		$email_title = isset( $layout['title'] ) && is_string( $layout['title'] ) && $layout['title'] !== ''
			? $layout['title']
			: (string) $subject;

		$email_logo_cid = isset( $layout['logo_cid'] ) && is_string( $layout['logo_cid'] )
			? $layout['logo_cid']
			: '';
		$email_css_text = $this->get_email_css_text();

		ob_start();
		include LLA_PLUGIN_DIR . 'views/emails/header.php';
		echo (string) $content_html;
		include LLA_PLUGIN_DIR . 'views/emails/footer.php';

		return (string) ob_get_clean();
	}

	/**
	 * Load and cache CSS for email layout injected into the `<style>` block.
	 *
	 * @return string
	 */
	private function get_email_css_text() {
		if ( null !== self::$email_css_text ) {
			return self::$email_css_text;
		}

		$css_path = LLA_PLUGIN_DIR . 'views/emails/email-layout.css';
		if ( file_exists( $css_path ) && is_readable( $css_path ) ) {
			self::$email_css_text = (string) file_get_contents( $css_path );
		} else {
			self::$email_css_text = '';
		}

		return self::$email_css_text;
	}
}
