<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Ngenius_Gateway_Http_Capture class.
 */
class Ngenius_Gateway_Http_Capture extends Ngenius_Gateway_Http_Abstract {


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
			$amount = 0;
			if ( isset( $response['_embedded']['cnp:capture'] ) && is_array( $response['_embedded']['cnp:capture'] ) ) {
				$last_transaction = end( $response['_embedded']['cnp:capture'] );
				foreach ( $response['_embedded']['cnp:capture'] as $capture ) {
					if ( isset( $capture['state'] ) && ( 'SUCCESS' === $capture['state'] ) && isset( $capture['amount']['value'] ) ) {
						$amount += $capture['amount']['value'];
					}
				}
			}
			$captured_amt = 0;
			if ( isset( $last_transaction['state'] ) && ( 'SUCCESS' === $last_transaction['state'] ) && isset( $last_transaction['amount']['value'] ) ) {
				$captured_amt = $last_transaction['amount']['value'] / 100;
			}

			$transaction_id = '';
			if ( isset( $last_transaction['_links']['self']['href'] ) ) {
				$transaction_arr = explode( '/', $last_transaction['_links']['self']['href'] );
				$transaction_id  = end( $transaction_arr );
			}
			$amount = ( $amount > 0 ) ? $amount / 100 : 0;
			$state  = isset( $response['state'] ) ? $response['state'] : '';

			if ( 'PARTIALLY_CAPTURED' === $state ) {
				$order_status = substr( $this->order_status[6]['status'], 3 );
			} else {
				$order_status = substr( $this->order_status[5]['status'], 3 );
			}
			return [
				'result' => [
					'total_captured' => $amount,
					'captured_amt'   => $captured_amt,
					'state'          => $state,
					'order_status'   => $order_status,
					'transaction_id' => $transaction_id,
				],
			];
		}
	}

}
