<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Ngenius_Gateway_Request_Void class.
 */
class Ngenius_Gateway_Request_Void {


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
	 * Builds ENV void request
	 *
	 * @param  object $order_item
	 * @return array|null
	 */
	public function build( $order_item ) {
		return[
			'token'   => $this->config->get_token(),
			'request' => [
				'data'   => [],
				'method' => 'PUT',
				'uri'    => $this->config->get_order_void_url( $order_item->reference, $order_item->payment_id ),
			],
		];
	}

}
