<?php

namespace LLAR\Core\Http;

class HttpTransportWp implements HttpTransportInterface {

	/**
	 * @param $url
	 * @param array $options
	 *
	 * @return array
	 */
	public function get( $url, $options = array() ) {
		$request_options = $this->build_request_options( $options );
		$response = wp_remote_get( $url, array(
			'headers' 	=> !empty( $options['headers'] ) ? $this->format_headers( $options['headers'] ) : array(),
			'body' 		=> !empty( $options['data'] ) ? $options['data'] : array(),
			'timeout'   => $request_options['timeout'],
			'sslverify' => $request_options['sslverify'],
		) );

		return $this->prepare_response( $response );
	}

	/**
	 * @param $url
	 * @param array $options
	 *
	 * @return array
	 */
	public function post( $url, $options = array() ) {
		$request_options = $this->build_request_options( $options );
		$response = wp_remote_post( $url, array(
			'headers' 	=> !empty( $options['headers'] ) ? $this->format_headers( $options['headers'] ) : array(),
			'body' 		=> !empty( $options['data'] ) ? json_encode( $options['data'], JSON_FORCE_OBJECT ) : null,
			'timeout'   => $request_options['timeout'],
			'sslverify' => $request_options['sslverify'],
		) );

		return $this->prepare_response( $response );
	}

	/**
	 * @param $response
	 *
	 * @return array
	 */
	private function prepare_response( $response ) {

		$return = array(
			'data'      => null,
			'status'    => 0,
			'error'     => null
		);

		if( is_wp_error( $response ) ) {
			$return['error'] = $response->get_error_message();
		} else {
			$return['data'] = wp_remote_retrieve_body( $response );
			$return['status'] = intval( wp_remote_retrieve_response_code( $response ) );
		}

		return $return;
	}

	/**
	 * @param array $headers
	 *
	 * @return array
	 */
	private function format_headers( $headers = array() ) {

		$formatted_headers = array();

		if( !empty( $headers ) ) {
			foreach ( $headers as $header ) {
				list( $name, $value ) = explode( ':', $header );

				$formatted_headers[ trim( $name ) ] = trim( $value );
			}
		}

		return $formatted_headers;
	}

	/**
	 * @param array $options
	 * @return array
	 */
	private function build_request_options( $options ) {
		$timeout = isset( $options['timeout'] ) ? (int) $options['timeout'] : 5;
		if ( $timeout <= 0 ) {
			$timeout = 5;
		}

		return array(
			'timeout'   => $timeout,
			'sslverify' => ! ( isset( $options['sslverify'] ) && false === $options['sslverify'] ),
		);
	}
}