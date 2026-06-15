<?php

namespace LLAR\Core\Mail;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface MailTransportInterface {

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
	public function send( $to, $subject, $message, $headers = array(), $attachments = array(), $suppress_errors = false, $layout = array() );
}
