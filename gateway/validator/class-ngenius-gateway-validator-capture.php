<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Ngenius_Gateway_Validator_Capture class.
 */
class Ngenius_Gateway_Validator_Capture {


	/**
	 * Performs validation for capture transaction
	 *
	 * @param  array $response
	 * @return bool|null
	 */
	public function validate( $response ) {
		if ( is_wp_error( $response ) ) {
			throw new Exception( $response->get_error_message() );
		} else {
			if ( ! isset( $response['result'] ) && ! is_array( $response['result'] ) ) {
				return false;
			} else {
				return $response['result'];
			}
		}
	}

}
