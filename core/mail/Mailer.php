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
	 *
	 * @return bool
	 */
	public static function send( $to, $subject, $message, $headers = array(), $attachments = array(), $suppress_errors = false ) {
		return self::get_transport()->send( $to, $subject, $message, $headers, $attachments, $suppress_errors );
	}
}
