<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Ngenius_Gateway_Http_Refund class.
 */
class Ngenius_Gateway_Http_Refund extends Ngenius_Gateway_Http_Abstract {


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
			$captured_amt = 0;
			if ( isset( $response['_embedded']['cnp:capture'] ) && is_array( $response['_embedded']['cnp:capture'] ) ) {
				foreach ( $response['_embedded']['cnp:capture'] as $capture ) {
					if ( isset( $capture['state'] ) && ( 'SUCCESS' === $capture['state'] ) && isset( $capture['amount']['value'] ) ) {
						$captured_amt += $capture['amount']['value'];
					}
				}
			}

			$refunded_amt = 0;
			if ( isset( $response['_embedded']['cnp:refund'] ) && is_array( $response['_embedded']['cnp:refund'] ) ) {
				$transaction_arr = end( $response['_embedded']['cnp:refund'] );
				foreach ( $response['_embedded']['cnp:refund'] as $refund ) {
					if ( isset( $refund['state'] ) && ( 'SUCCESS' === $refund['state'] ) && isset( $refund['amount']['value'] ) ) {
						$refunded_amt += $refund['amount']['value'];
					}
				}
			}

			$last_refunded_amt = 0;
			if ( isset( $transaction_arr['state'] ) && ( 'SUCCESS' === $transaction_arr['state'] ) && isset( $transaction_arr['amount']['value'] ) ) {
				$last_refunded_amt = $transaction_arr['amount']['value'] / 100;
			}

			$transaction_id = '';
			if ( isset( $transaction_arr['_links']['self']['href'] ) ) {
				$transaction_arr = explode( '/', $transaction_arr['_links']['self']['href'] );
				$transaction_id  = end( $transaction_arr );
			}
			$state = isset( $response['state'] ) ? $response['state'] : '';

			if ( $captured_amt === $refunded_amt ) {
				$order_status = 'refunded';
			} else {
				$order_status = substr( $this->order_status[6]['status'], 3 );
			}
			return [
				'result' => [
					'captured_amt'   => ( $captured_amt - $refunded_amt ) / 100,
					'refunded_amt'   => $last_refunded_amt,
					'state'          => $state,
					'order_status'   => $order_status,
					'transaction_id' => $transaction_id,
				],
			];
		}
	}

}
