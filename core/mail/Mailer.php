<?php

namespace LLAR\Core\Mail;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mailer {

	/**
	 * @var MailTransportInterface|null
	 */
	private static $transport = null;

	/**
	 * @param MailTransportInterface $transport
	 *
	 * @return void
	 */
	public static function set_transport( MailTransportInterface $transport ) {
		self::$transport = $transport;
	}

	/**
	 * @return MailTransportInterface
	 */
	public static function get_transport() {
		if ( null === self::$transport ) {
			self::$transport = new MailTransportWp();
		}

		return self::$transport;
	}

	/**
	 * @param string       $to
	 * @param string       $subject
	 * @param string       $message
	 * @param array|string $headers
	 * @param array        $attachments
	 * @param bool         $suppress_errors
	 * @param array        $layout
	 *
	 * @return bool
	 */
	public static function send( $to, $subject, $message, $headers = array(), $attachments = array(), $suppress_errors = false, $layout = array() ) {
		$layout = is_array( $layout ) ? $layout : array();

		if ( ! array_key_exists( 'use_layout', $layout ) || $layout['use_layout'] ) {
			$message = self::render_layout( $subject, $message, $layout );
		}

		return self::get_transport()->send( $to, $subject, $message, $headers, $attachments, $suppress_errors );
	}

	/**
	 * @param string $subject
	 * @param string $content_html
	 * @param array  $layout
	 *
	 * @return string
	 */
	private static function render_layout( $subject, $content_html, $layout = array() ) {
		$email_title = isset( $layout['title'] ) && is_string( $layout['title'] ) && $layout['title'] !== ''
			? $layout['title']
			: (string) $subject;

		$email_logo_cid = isset( $layout['logo_cid'] ) && is_string( $layout['logo_cid'] )
			? $layout['logo_cid']
			: '';

		ob_start();
		include LLA_PLUGIN_DIR . 'views/emails/header.php';
		echo (string) $content_html;
		include LLA_PLUGIN_DIR . 'views/emails/footer.php';

		return (string) ob_get_clean();
	}
}
