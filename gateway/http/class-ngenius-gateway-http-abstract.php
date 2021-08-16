<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ngenius_Gateway_Http_Abstract class.
 */
abstract class Ngenius_Gateway_Http_Abstract {


	/**
	 * Ngenius Order status.
	 */
	protected $order_status;

	/**
	 * Places request to gateway.
	 *
	 * @param  TransferInterface $transfer_object
	 * @return array|null
	 */
	public function place_request( Ngenius_Gateway_Http_Transfer $transfer_object ) {
		$this->order_status = include dirname( __FILE__ ) . '/../order-status-ngenius.php';
		$data               = $this->pre_process( $transfer_object->get_body() );
		$log                = array(
			'request'     => $data,
			'request_uri' => $transfer_object->get_uri(),
		);
		try {
			$response = wp_remote_post(
				$transfer_object->get_uri(),
				array(
					'method'      => $transfer_object->get_method(),
					'httpversion' => '1.0',
					'timeout'     => 30,
					'headers'     => $transfer_object->get_headers(),
					'body'        => $data,
				)
			);
			if ( is_wp_error( $response ) ) {
				Ngenius_Gateway::get_instance()->log( $response->get_error_message() );
				return new WP_Error( 'ngenius_error', 'Failed! ' . $response->get_error_message() );
			} elseif ( in_array( $response['response']['code'], array( 200, 201 ), true ) ) {
				$log['response'] = $response['body'];
				Ngenius_Gateway::get_instance()->log( json_encode( $log ) );
				return $this->post_process( $response['body'] );
			} else {
				$message = 'Failed! #' . $response['response']['code'] . ', ' . $response['response']['message'];
				if ( 409 === $response['response']['code'] ) {
					$message .= '- Already captured/ Not processed settlement, etc.';
				}
				if ( is_admin() ) {
					if ( wp_doing_ajax() ) {
						throw new Exception( $message );
					} else {
						WC_Admin_Notices::add_custom_notice( 'ngenius', $message );
					}
					return false;
				} else {
					wc_add_notice( $message, 'error' );
				}
				return false;
			}
		} catch ( Exception $e ) {
			return new WP_Error( 'error', $e->getMessage() );
		} finally {
			//log
		}
	}

	/**
	 * Processing of API request body
	 *
	 * @param  array $data
	 * @return string|array
	 */
	abstract protected function pre_process( array $data);

	/**
	 * Processing of API response
	 *
	 * @param  array $response
	 * @return array|null
	 */
	abstract protected function post_process( $response);
}
