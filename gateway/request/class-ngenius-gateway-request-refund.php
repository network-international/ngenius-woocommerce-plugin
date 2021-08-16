<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Ngenius_Gateway_Request_Refund class.
 */
class Ngenius_Gateway_Request_Refund {


	/**
	 * @var Config
	 */
	protected $config;

	/**
	 * Constructor
	 *
	 * @param Ngenius_Gateway_Config $config
	 */
	public function __construct( Ngenius_Gateway_Config $config ) {
		$this->config = $config;
	}

	/**
	 * Builds ENV refund request
	 *
	 * @param  object $order_item
	 * @param  float  $amount
	 * @return array|null
	 */
	public function build( $order_item, $amount ) {
		return[
			'token'   => $this->config->get_token(),
			'request' => [
				'data'   => [
					'amount' => [
						'currencyCode' => $order_item->currency,
						'value'        => $amount * 100,
					],
				],
				'method' => 'POST',
				'uri'    => $this->config->get_order_refund_url( $order_item->reference, $order_item->payment_id, $order_item->capture_id ),
			],
		];
	}

}
