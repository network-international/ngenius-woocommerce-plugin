<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Ngenius_Gateway_Validator_Void class.
 */
class Ngenius_Gateway_Validator_Void {


	/**
	 * Performs reversed the authorization
	 *
	 * @param  array $response
	 * @return bool
	 */
	public function validate( $response ) {
		if ( is_wp_error( $response ) ) {
			throw new Exception( $response->get_error_message() );
		} else {
			if ( ! isset( $response['result']['order_status'] ) && empty( $response['result']['order_status'] ) ) {
				return false;
			} else {
				return $response['result'];
			}
		}
	}

}
