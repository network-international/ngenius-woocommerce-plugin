<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Ngenius_Gateway_Http_Fetch class.
 */
class Ngenius_Gateway_Http_Fetch extends Ngenius_Gateway_Http_Abstract {


	/**
	 * Processing of API request body
	 *
	 * @param  array $data
	 * @return string
	 */
	protected function pre_process( array $data ) {
		return json_encode( $data );
	}

	/**
	 * Processing of API response
	 *
	 * @param  array $response_enc
	 * @return array
	 */
	protected function post_process( $response_enc ) {
		return json_decode( $response_enc, true );
	}

}
