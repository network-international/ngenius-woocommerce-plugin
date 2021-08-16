<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Ngenius_Gateway_Http_Void class.
 */
class Ngenius_Gateway_Http_Void extends Ngenius_Gateway_Http_Abstract {


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
	 * @return array|null
	 */
	protected function post_process( $response_enc ) {

		$response = json_decode( $response_enc, true );
		if ( isset( $response['errors'] ) && is_array( $response['errors'] ) ) {
			return null;
		} else {

			$state        = isset( $response['state'] ) ? $response['state'] : '';
			$order_status = ( 'REVERSED' === $state ) ? substr( $this->order_status[7]['status'], 3 ) : '';
			return [
				'result' => [
					'state'        => $state,
					'order_status' => $order_status,
				],
			];
		}
	}

}
