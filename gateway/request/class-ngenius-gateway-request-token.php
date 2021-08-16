<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Ngenius_Gateway_Request_Token class.
 */
class Ngenius_Gateway_Request_Token {


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
	 * Builds access token request
	 *
	 * @param  array $order
	 * @param  float $amount
	 * @return string|null
	 */
	public function get_access_token() {

		$response = wp_remote_post(
			$this->config->get_token_request_url(),
			array(
				'method'      => 'POST',
				'httpversion' => '1.0',
				'timeout'     => 30,
				'headers'     => array(
					'Authorization' => 'Basic ' . $this->config->get_api_key(),
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'        => http_build_query( array( 'grant_type' => 'client_credentials' ) ),
			)
		);
		if ( is_wp_error( $response ) ) {
			echo $response->get_error_message();
			die();
		} else {
			$result = json_decode( $response['body'] );
			if ( isset( $result->access_token ) ) {
				return $result->access_token;
			}
		}
	}

}
