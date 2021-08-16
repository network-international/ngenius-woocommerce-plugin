<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ngenius_Gateway_Config class.
 */
class Ngenius_Gateway_Config {


	/**
	 * Config tags
	 */
	const UAT_IDENTITY_URL  = 'https://identity-uat.ngenius-payments.com';
	const LIVE_IDENTITY_URL = 'https://identity.ngenius-payments.com';
	const UAT_API_URL       = 'https://api-gateway-uat.ngenius-payments.com';
	const LIVE_API_URL      = 'https://api-gateway.ngenius-payments.com';
	const TOKEN_ENDPOINT    = '/auth/realms/%s/protocol/openid-connect/token';
	const ORDER_ENDPOINT    = '/transactions/outlets/%s/orders';
	const FETCH_ENDPOINT    = '/transactions/outlets/%s/orders/%s';
	const CAPTURE_ENDPOINT  = '/transactions/outlets/%s/orders/%s/payments/%s/captures';
	const REFUND_ENDPOINT   = '/transactions/outlets/%s/orders/%s/payments/%s/captures/%s/refund';
	const VOID_ENDPOINT     = '/transactions/outlets/%s/orders/%s/payments/%s/cancel';

	/**
	 * Pointer to gateway making the request.
	 *
	 * @var Ngenius_Gateway
	 */
	public $gateway;

	/**
	 * Token for gateway request
	 *
	 * @var string token
	 */
	private $token;

	/**
	 * Constructor.
	 *
	 * @param Ngenius_Gateway $gateway n-genius gateway object.
	 */
	public function __construct( Ngenius_Gateway $gateway ) {
		$this->gateway = $gateway;
	}

	/**
	 * Set token
	 *
	 * @param string $token
	 */
	public function set_token( $token ) {
		$this->token = $token;
	}

	/**
	 * Get token
	 *
	 * @return string Token
	 */
	public function get_token() {
		return $this->token;
	}

	/**
	 * Retrieve apikey and outletReferenceId empty or not
	 *
	 * @return bool
	 */
	public function is_complete() {
		if ( ! empty( $this->get_api_key() ) && ! empty( $this->get_outlet_reference_id() ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Gets Identity Url.
	 *
	 * @return string
	 */
	public function get_identity_url() {
		switch ( $this->get_environment() ) {
			case 'uat':
				$value = self::UAT_IDENTITY_URL;
				break;
			case 'live':
				$value = self::LIVE_IDENTITY_URL;
				break;
		}
		return $value;
	}

	/**
	 * Gets Payment Action.
	 *
	 * @return string
	 */
	public function get_payment_action() {
		return $this->gateway->get_option( 'payment_action' );
	}

	/**
	 * Gets Environment.
	 *
	 * @return string
	 */
	public function get_environment() {
		return $this->gateway->get_option( 'environment' );
	}

	/**
	 * Gets Api Url.
	 *
	 * @return string
	 */
	public function get_api_url() {
		switch ( $this->get_environment() ) {
			case 'uat':
				$value = self::UAT_API_URL;
				break;
			case 'live':
				$value = self::LIVE_API_URL;
				break;
		}
		return $value;
	}

	/**
	 * Gets Outlet Reference Id.
	 *
	 * @return string
	 */
	public function get_outlet_reference_id() {
		return $this->gateway->get_option( 'outlet_ref' );
	}

	/**
	 * Gets Api Key.
	 *
	 * @return string
	 */
	public function get_api_key() {
		return $this->gateway->get_option( 'api_key' );
	}

	/**
	 * Gets TokenRequest URL.
	 *
	 * @return string
	 */
	public function get_token_request_url() {
		$tenant     = '';
		$tenant_arr = [
			'networkinternational' => [
				'uat'  => 'ni',
				'live' => 'networkinternational',
			],
		];
		if ( isset( $tenant_arr[ $this->gateway->get_option( 'tenant' ) ][ $this->get_environment() ] ) ) {
			$tenant = $tenant_arr[ $this->gateway->get_option( 'tenant' ) ][ $this->get_environment() ];
		}
		return $this->get_identity_url() . sprintf( self::TOKEN_ENDPOINT, $tenant );
	}

	/**
	 * Gets Order Request URL.
	 *
	 * @return string
	 */
	public function get_order_request_url() {
		$endpoint = sprintf( self::ORDER_ENDPOINT, $this->get_outlet_reference_id() );
		return $this->get_api_url() . $endpoint;
	}

	/**
	 * Gets Fetch Request URL.
	 *
	 * @param  string $order_ref
	 * @return string
	 */
	public function get_fetch_request_url( $order_ref ) {
		$endpoint = sprintf( self::FETCH_ENDPOINT, $this->get_outlet_reference_id(), $order_ref );
		return $this->get_api_url() . $endpoint;
	}

	/**
	 * Gets Order Capture URL.
	 *
	 * @param  string $order_ref
	 * @param  string $payment_ref
	 * @return string
	 */
	public function get_order_capture_url( $order_ref, $payment_ref ) {
		$endpoint = sprintf( self::CAPTURE_ENDPOINT, $this->get_outlet_reference_id(), $order_ref, $payment_ref );
		return $this->get_api_url() . $endpoint;
	}

	/**
	 * Gets Order Refund URL.
	 *
	 * @param  string $order_ref
	 * @param  string $payment_ref
	 * @param  string $transaction_id
	 * @return string
	 */
	public function get_order_refund_url( $order_ref, $payment_ref, $transaction_id ) {
		$endpoint = sprintf( self::REFUND_ENDPOINT, $this->get_outlet_reference_id(), $order_ref, $payment_ref, $transaction_id );
		return $this->get_api_url() . $endpoint;
	}

	/**
	 * Gets Order Void URL.
	 *
	 * @param  string $order_ref
	 * @param  string $payment_ref
	 * @return string
	 */
	public function get_order_void_url( $order_ref, $payment_ref ) {
		$endpoint = sprintf( self::VOID_ENDPOINT, $this->get_outlet_reference_id(), $order_ref, $payment_ref );
		return $this->get_api_url() . $endpoint;
	}

}
