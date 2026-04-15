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
	 * Runtime marker for LLAR send in progress.
	 *
	 * @var bool
	 */
	private static $runtime_send_active = false;

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
		$headers = self::ensure_llar_email_header( $headers );

		self::$runtime_send_active = true;
		try {
			return self::get_transport()->send( $to, $subject, $message, $headers, $attachments, $suppress_errors, $layout );
		} finally {
			self::$runtime_send_active = false;
		}
	}

	/**
	 * Check whether LLAR mail send is active in current runtime.
	 *
	 * @return bool
	 */
	public static function is_runtime_send_active() {
		return self::$runtime_send_active;
	}

	/**
	 * Add LLAR marker header to outgoing messages.
	 *
	 * @param array|string $headers Original headers.
	 * @return array
	 */
	private static function ensure_llar_email_header( $headers ) {
		$headers_list = is_array( $headers ) ? $headers : array( (string) $headers );
		$has_marker   = false;

		foreach ( $headers_list as $header_line ) {
			$line = strtolower( trim( (string) $header_line ) );
			if ( 0 === strpos( $line, 'x-llar-email:' ) ) {
				$has_marker = true;
				break;
			}
		}

		if ( ! $has_marker ) {
			$headers_list[] = 'X-LLAR-Email: true';
		}

		return $headers_list;
	}
}
