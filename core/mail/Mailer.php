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
	 * @param string       $message Content-only HTML (without header/footer wrapper).
	 * @param array|string $headers
	 * @param array        $attachments
	 * @param bool         $suppress_errors
	 * @param array        $layout Optional layout settings: title, logo_cid, use_layout.
	 *
	 * @return bool
	 */
	public static function send( $to, $subject, $message, $headers = array(), $attachments = array(), $suppress_errors = false, $layout = array() ) {
		$layout = is_array( $layout ) ? $layout : array();
		return self::get_transport()->send( $to, $subject, $message, $headers, $attachments, $suppress_errors, $layout );
	}
}
