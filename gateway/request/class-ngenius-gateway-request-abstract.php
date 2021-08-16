<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Ngenius_Gateway_Request_Abstract class.
 */
abstract class Ngenius_Gateway_Request_Abstract {


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
	 * Builds request array
	 *
	 * @param  array $order
	 * @return array
	 */
	public function build( $order ) {
		return[
			'token'   => $this->config->get_token(),
			'request' => $this->get_build_array( $order ),
		];
	}

	/**
	 * Builds abstract request array
	 *
	 * @param  array $order
	 * @return array
	 */
	abstract public function get_build_array( $order);
}
