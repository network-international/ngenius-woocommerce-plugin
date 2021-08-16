<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once 'class-ngenius-gateway-request-abstract.php';
/**
 * Ngenius_Gateway_Request_Authorize class.
 */
class Ngenius_Gateway_Request_Authorize extends Ngenius_Gateway_Request_Abstract {


	/**
	 * Builds athorization request array
	 *
	 * @param  array $order
	 * @return array
	 */
	public function get_build_array( $order ) {

		return[
			'data'   => [
				'action'                 => 'AUTH',
				'amount'                 => [
					'currencyCode' => $order->get_currency(),
					'value'        => strval( $order->get_total() * 100 ),
				],
				'merchantAttributes'     => [
					'redirectUrl'          => site_url() . '/wc-api/ngeniusonline',
					'skipConfirmationPage' => true,
				],
				'merchantOrderReference' => $order->get_id(),
				'emailAddress'           => $order->get_billing_email(),
                                'billingAddress' => [
                                    'firstName' => $order->get_billing_first_name(),
                                    'lastName' => $order->get_billing_last_name(),
                                ]
			],
			'method' => 'POST',
			'uri'    => $this->config->get_order_request_url(),
		];
	}

}
