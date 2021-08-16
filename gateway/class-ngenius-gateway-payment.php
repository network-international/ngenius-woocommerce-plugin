<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Ngenius_Gateway_Payment class.
 */
class Ngenius_Gateway_Payment {


	/**
	 * n-genius states
	 */
	const NGENIUS_STARTED    = 'STARTED';
	const NGENIUS_AUTHORISED = 'AUTHORISED';
	const NGENIUS_CAPTURED   = 'CAPTURED';
	const NGENIUS_FAILED     = 'FAILED';

	/**
	 *
	 * @var string Order Status
	 */
	protected $order_status;

		/**
		 *
		 * @var string n-genius state
		 */
	protected $ngenius_state;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->order_status = include dirname( __FILE__ ) . '/order-status-ngenius.php';
	}

	/**
	 * Execute action.
	 *
	 * @param string $order_ref Order reference
	 */
	public function execute( $order_ref ) {
		global $woocommerce;
		$redirect_url = $woocommerce->cart->get_checkout_url();
		if ( $order_ref ) {
			$result = $this->get_response_api( $order_ref );
			if ( $result && isset( $result['_embedded']['payment'] ) && is_array( $result['_embedded']['payment'] ) ) {
				$action         = isset( $result['action'] ) ? $result['action'] : '';
				$payment_result = $result['_embedded']['payment'][0];
				$order_item     = reset( $this->fetch_order( 'reference="' . $order_ref . '"' ) );
				$order          = $this->process_order( $payment_result, $order_item, $action );
				$redirect_url   = $order->get_checkout_order_received_url();
			}
			wp_redirect( $redirect_url );
			exit();
		} else {
			wp_redirect( $redirect_url );
			exit();
		}
	}

	/**
	 * Process Order.
	 *
	 * @param  array  $payment_result
	 * @param  object $order_item
	 * @param  string $action
	 * @return $this|null
	 */
	public function process_order( $payment_result, $order_item, $action ) {

		$data_table = [];
		if ( $order_item->order_id ) {
			$payment_id   = '';
			$capture_id   = '';
			$captured_amt = 0;
			if ( isset( $payment_result['_id'] ) ) {
				$payment_id_arr = explode( ':', $payment_result['_id'] );
				$payment_id     = end( $payment_id_arr );
			}

			$order = wc_get_order( $order_item->order_id );
			if ( $order->get_id() ) {
				if ( self::NGENIUS_FAILED !== $this->ngenius_state ) {
					if ( self::NGENIUS_STARTED !== $this->ngenius_state ) {
						switch ( $action ) {
							case 'AUTH':
								$this->order_authorize( $order );
								break;
							case 'SALE':
								list($captured_amt, $capture_id) = $this->order_sale( $order, $payment_result );
								break;
						}
						$data_table['status'] = $order->get_status();
					} else {
						$data_table['status'] = substr( $this->order_status[0]['status'], 3 );
					}
				} else {
					$order->update_status( $this->order_status[2]['status'], 'The transaction has been failed.' );
					$order->update_status( 'failed' );
					$data_table['status'] = substr( $this->order_status[2]['status'], 3 );
				}
				$data_table['payment_id']   = $payment_id;
				$data_table['captured_amt'] = $captured_amt;
				$data_table['capture_id']   = $capture_id;
				$this->update_table( $data_table, $order_item->nid );
				return $order;
			} else {
				return new WP_Error( 'ngenius_error', 'Order Not Found' );
				return;
			}
		}
	}

	/**
	 * Order Authorize.
	 *
	 * @param  object $order
	 * @return null
	 */
	public function order_authorize( $order ) {

		if ( self::NGENIUS_AUTHORISED === $this->ngenius_state ) {
			$message = 'Authorised Amount: ' . $order->get_formatted_order_total();
			$order->payment_complete();
			$order->update_status( $this->order_status[4]['status'] );
			$order->add_order_note( $message );
		}
	}

	/**
	 * Order Sale.
	 *
	 * @param  object $order
	 * @param  array  $payment_result
	 * @return null|array
	 */
	public function order_sale( $order, $payment_result ) {

		if ( self::NGENIUS_CAPTURED === $this->ngenius_state ) {
			$transaction_id = '';
			if ( isset( $payment_result['_embedded']['cnp:capture'][0] ) ) {
				$last_transaction = $payment_result['_embedded']['cnp:capture'][0];
				if ( isset( $last_transaction['_links']['self']['href'] ) ) {
					$transaction_arr = explode( '/', $last_transaction['_links']['self']['href'] );
					$transaction_id  = end( $transaction_arr );
				}elseif ($last_transaction['_links']['cnp:refund']['href']) {
                                        $transaction_arr = explode('/', $last_transaction['_links']['cnp:refund']['href']);
                                        $transaction_id = $transaction_arr[count($transaction_arr)-2];
                                }
			}
			$message = 'Captured Amount: ' . $order->get_formatted_order_total() . ' | Transaction ID: ' . $transaction_id;
			$order->payment_complete( $transaction_id );
			$order->update_status( $this->order_status[3]['status'] );
			$order->add_order_note( $message );
//			$emailer = new WC_Email_Customer_Invoice();
//			$emailer->trigger( $order->get_id() );
                        $emailer = new WC_Emails();
			$emailer->customer_invoice( $order );
			return array( $order->get_total(), $transaction_id );
		}
	}

	/**
	 * Gets Response API.
	 *
	 * @param  string $order_ref
	 * @return array|boolean
	 */
	public function get_response_api( $order_ref ) {
			include_once dirname( __FILE__ ) . '/http/class-ngenius-gateway-http-abstract.php';
		include_once dirname( __FILE__ ) . '/config/class-ngenius-gateway-config.php';
		include_once dirname( __FILE__ ) . '/request/class-ngenius-gateway-request-token.php';
		include_once dirname( __FILE__ ) . '/http/class-ngenius-gateway-http-transfer.php';
		include_once dirname( __FILE__ ) . '/http/class-ngenius-gateway-http-fetch.php';

		$gateway     = new Ngenius_Gateway();
		$config      = new Ngenius_Gateway_Config( $gateway );
		$token_class = new Ngenius_Gateway_Request_Token( $config );
		$token       = $token_class->get_access_token();

		if ( $token ) {
			$transfer_class = new Ngenius_Gateway_Http_Transfer();
			$fetch_class     = new Ngenius_Gateway_Http_Fetch();
			$request_data   = [
				'token'   => $token,
				'request' => [
					'data'   => [],
					'method' => 'GET',
					'uri'    => $config->get_fetch_request_url( $order_ref ),
				],
			];
			$response       = $fetch_class->place_request( $transfer_class->create( $request_data ) );
			return $this->result_validator( $response );
		}
	}

	/**
	 * Result Validator.
	 *
	 * @param  array $result
	 * @return array|boolean
	 */
	public function result_validator( $result ) {
		if ( is_wp_error( $result ) ) {
			throw new Exception( $result->get_error_message() );
		} else {
			if ( isset( $result['errors'] ) && is_array( $result['errors'] ) ) {
				return false;
			} else {
				$this->ngenius_state = isset( $result['_embedded']['payment'][0]['state'] ) ? $result['_embedded']['payment'][0]['state'] : '';
				return $result;
			}
		}
	}

	/**
	 * Fetch Order details.
	 *
	 * @param  string $where
	 * @return object
	 */
	public function fetch_order( string $where ) {
		global $wpdb;
		return $wpdb->get_results( sprintf( 'SELECT * FROM %s WHERE %s ORDER BY `nid` DESC', NGENIUS_TABLE, $where ) );
	}

	/**
	 * Update Table.
	 *
	 * @param  array $data
	 * @param  int   $nid
	 * @return bool true
	 */
	public function update_table( array $data, int $nid ) {
		global $wpdb;
		$data['state'] = $this->ngenius_state;
		return $wpdb->update( NGENIUS_TABLE, $data, array( 'nid' => $nid ) );
	}

	/**
	 * Cron Job function
	 */
	public function order_update() {
		$order_items = $this->fetch_order( 'state = "' . self::NGENIUS_STARTED . '" AND payment_id="" AND DATE_ADD(created_at, INTERVAL 60 MINUTE) < NOW()' );
		$log         = [];
		if ( is_array( $order_items ) ) {
			foreach ( $order_items as $order_item ) {
				$order_ref = $order_item->reference;
				$result    = $this->get_response_api( $order_ref );
				if ( $result && isset( $result['_embedded']['payment'] ) && is_array( $result['_embedded']['payment'] ) ) {
					$action         = isset( $result['action'] ) ? $result['action'] : '';
					$payment_result = $result['_embedded']['payment'][0];
					$order          = $this->process_order( $payment_result, $order_item, $action );
					$log[]          = $order->get_id();
				}
			}
			return json_encode( $log );
		} else {
			return false;
		}
	}

}
