<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Ngenius_Gateway_Http_Sale class.
 */
class Ngenius_Gateway_Http_Sale extends Ngenius_Gateway_Http_Abstract {


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
		$response = json_decode( $response_enc );
		if ( isset( $response->_links->payment->href ) ) {
			global $wp_session;
			$data                  = [];
			$data['reference']     = isset( $response->reference ) ? $response->reference : '';
			$data['action']        = isset( $response->action ) ? $response->action : '';
			$data['state']         = isset( $response->_embedded->payment[0]->state ) ? $response->_embedded->payment[0]->state : '';
			$data['status']        = substr( $this->order_status[0]['status'], 3 );
			$wp_session['ngenius'] = $data;
			return [ 'payment_url' => $response->_links->payment->href ];
		} else {
			return null;
		}
	}

}
