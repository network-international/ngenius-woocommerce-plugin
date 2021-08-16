<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __FILE__ ) . '/config/class-ngenius-gateway-config.php';
require_once dirname( __FILE__ ) . '/request/class-ngenius-gateway-request-token.php';
require_once dirname( __FILE__ ) . '/http/class-ngenius-gateway-http-transfer.php';
require_once dirname( __FILE__ ) . '/http/class-ngenius-gateway-http-abstract.php';
/**
 * Ngenius_Gateway class.
 */
class Ngenius_Gateway extends WC_Payment_Gateway {


	/**
	 * Whether or not logging is enabled
	 *
	 * @var bool
	 */
	public static $log_enabled = false;

	/**
	 * Logger instance
	 *
	 * @var WC_Logger
	 */
	public static $log = false;

	/**
	 * Singleton instance
	 *
	 * @var Ngenius_Gateway
	 */
	private static $instance;

	/**
	 * Notice variable
	 *
	 * @var string
	 */
	private $message;

	/**
	 * get_instance
	 *
	 * Returns a new instance of self, if it does not already exist.
	 *
	 * @access public
	 * @static
	 * @return Ngenius_Gateway
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {

		$this->id                 = 'ngenius';
		$this->icon               = ''; // URL of the icon that will be displayed on checkout page near your gateway name
		$this->has_fields         = false; // in case you need a custom credit card form
		$this->method_title       = 'n-genius Payment Gateway';
		$this->method_description = 'Payment Gateway from Network International Payment Solutions'; // will be displayed on the options page
		// gateways can support subscriptions, refunds, saved payment methods
		$this->supports = array(
			'products',
			'refunds',
		);

		// Method with all the options fields
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		$this->title          = $this->get_option( 'title' );
		$this->description    = $this->get_option( 'description' );
		$this->enabled        = $this->get_option( 'enabled' );
                $this->tenant         = $this->get_option( 'tenant' );
		$this->environment    = $this->get_option( 'environment' );
		$this->payment_action = $this->get_option( 'payment_action' );
		$this->order_status   = $this->get_option( 'order_status' );
		$this->outlet_ref     = $this->get_option( 'outlet_ref' );
		$this->api_key        = $this->get_option( 'api_key' );
		$this->debug          = 'yes' === $this->get_option( 'debug', 'no' );
		self::$log_enabled    = $this->debug;

	}

	/**
	 * Plug-in options
	 */
	public function init_form_fields() {
		$this->form_fields = include 'settings-ngenius.php';
	}

	/**
	 * Cron Job Hook
	 */
	public function ngenius_cron_task() {

		if ( ! wp_next_scheduled( 'ngenius_cron_order_update' ) ) {
			wp_schedule_event( time(), 'hourly', 'ngenius_cron_order_update' );
		}
		add_action( 'ngenius_cron_order_update', array( $this, 'cron_order_update' ) );
	}

	/**
	 * Initilize module hooks
	 */
	public function init_hooks() {
		add_action( 'init', array( $this, 'ngenius_cron_task' ) );
		add_action( 'woocommerce_api_ngeniusonline', array( $this, 'update_ngenius_response' ) );
		if ( is_admin() ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'add_meta_boxes', array( $this, 'ngenius_online_meta_boxes' ) );
			add_action( 'save_post', array( $this, 'ngenius_online_actions' ) );
		}
	}

	/**
	 * Add notice query variable
	 *
	 * @param string $location
	 * @return string
	 */
	public function add_notice_query_var( $location ) {
		remove_filter( 'redirect_post_location', array( $this, 'add_notice_query_var' ), 99 );
		return add_query_arg( array( 'message' => false ), $location );
	}

	/**
		* Processing order
		*
		* @global object $woocommerce
		* @param int $order_id
		* @return array|null
		*/
	public function process_payment( $order_id ) {

		include_once dirname( __FILE__ ) . '/request/class-ngenius-gateway-request-authorize.php';
		include_once dirname( __FILE__ ) . '/request/class-ngenius-gateway-request-sale.php';
		include_once dirname( __FILE__ ) . '/http/class-ngenius-gateway-http-authorize.php';
		include_once dirname( __FILE__ ) . '/http/class-ngenius-gateway-http-sale.php';
		include_once dirname( __FILE__ ) . '/validator/class-ngenius-gateway-validator-response.php';

		global $woocommerce;
		$order       = wc_get_order( $order_id );
		$config      = new Ngenius_Gateway_Config( $this );
		$token_class = new Ngenius_Gateway_Request_Token( $config );

		if ( $config->is_complete() ) {
			$token = $token_class->get_access_token();
			if ( $token ) {
				$config->set_token( $token );

				switch ( $config->get_payment_action() ) {
					case 'authorize':
						$request_class = new Ngenius_Gateway_Request_Authorize( $config );
						$request_http  = new Ngenius_Gateway_Http_Authorize();
						break;
					case 'sale':
						$request_class = new Ngenius_Gateway_Request_Sale( $config );
						$request_http  = new Ngenius_Gateway_Http_Sale();
						break;
				}

				$transfer_class = new Ngenius_Gateway_Http_Transfer();
				$validator      = new Ngenius_Gateway_Validator_Response();

				$response = $request_http->place_request( $transfer_class->create( $request_class->build( $order ) ) );
				$result   = $validator->validate( $response );
				if ( $result ) {
					$this->save_data( $order );
					$woocommerce->cart->empty_cart();
					return array(
						'result'   => 'success',
						'redirect' => $result,
					);
				}
			}
		} else {
			wc_add_notice( 'Error! Invalid configuration.', 'error' );
			return;
		}
	}

	/**
	 * Save data
	 *
	 * @global object $wpdb
	 * @global object $wp_session
	 * @param object $order
	 */
	public function save_data( $order ) {
		global $wpdb;
		global $wp_session;
		$wpdb->replace(
			NGENIUS_TABLE,
			array_merge(
				$wp_session['ngenius'],
				array(
					'order_id' => $order->get_id(),
					'currency' => $order->get_currency(),
					'amount'   => $order->get_total(),
				)
			)
		);
	}

	/**
	 * Update data
	 *
	 * @global object $wpdb
	 * @param array $data
	 * @param array $where
	 */
	public function update_data( array $data, array $where ) {
		global $wpdb;
		$wpdb->update( NGENIUS_TABLE, $data, $where );
	}

	/**
	 * Logging method.
	 *
	 * @param string $message Log message.
	 * @param string $level   Optional. Default 'info'. Possible values:
	 *                        emergency|alert|critical|error|warning|notice|info|debug.
	 */
	public static function log( $message, $level = 'debug' ) {
		if ( self::$log_enabled ) {
			if ( empty( self::$log ) ) {
				self::$log = wc_get_logger();
			}
			self::$log->log( $level, $message, array( 'source' => 'ngenius' ) );
		}
	}

	/**
	 * Processes and saves options.
	 * If there is an error thrown, will continue to save and validate fields, but will leave the erroring field out.
	 *
	 * @return bool was anything saved?
	 */
	public function process_admin_options() {
		$saved = parent::process_admin_options();
		if ( 'yes' === $this->get_option( 'enabled', 'no' ) ) {
			if ( empty( $this->get_option( 'outlet_ref' ) ) ) {
				add_settings_error( 'ngenius_error', esc_attr( 'settings_updated' ), __( 'Invalid Reference ID' ), 'error' );
			}
			if ( empty( $this->get_option( 'api_key' ) ) ) {
				add_settings_error( 'ngenius_error', esc_attr( 'settings_updated' ), __( 'Invalid API Key' ), 'error' );
			}
			add_action( 'admin_notices', 'print_errors' );
		}
		if ( 'yes' !== $this->get_option( 'debug', 'no' ) ) {
			if ( empty( self::$log ) ) {
				self::$log = wc_get_logger();
			}
			self::$log->clear( 'ngenius' );
		}
		return $saved;
	}

	/**
	 * Catch response from n-genius
	 */
	public function update_ngenius_response() {
		$order_ref = filter_input( INPUT_GET, 'ref', FILTER_SANITIZE_STRING );
		include plugin_dir_path( __FILE__ ) . '/class-ngenius-gateway-payment.php';
		$payment = new Ngenius_Gateway_Payment();
		$payment->execute( $order_ref );
		die;
	}

	/**
	 * Cron Job Action
	 */
	public function cron_order_update() {
		include plugin_dir_path( __FILE__ ) . '/class-ngenius-gateway-payment.php';
		$payment = new Ngenius_Gateway_Payment();
		$log     = $payment->order_update();
		if ( $log ) {
			$this->log( 'Cron updated the orders: ' . $log );
		}
	}

	/**
	 * Can the order be refunded?
	 *
	 * @param  WC_Order $order Order object.
	 * @return bool
	 */
	public function can_refund_order( $order ) {
		$order_item = $this->fetch_order( $order->get_id() );
		if ( in_array( $order_item->status, array( 'ng-complete', 'ng-captured', 'ng-part-refunded' ), true ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Fetch Order details.
	 *
	 * @param  int $order_id
	 * @return object
	 */
	public function fetch_order( $order_id ) {
		global $wpdb;
		return $wpdb->get_row( sprintf( 'SELECT * FROM %s WHERE order_id=%d', NGENIUS_TABLE, $order_id ) );
	}

	/**
	 * n-genius Meta Boxes
	 */
	public function ngenius_online_meta_boxes() {

		global $post;
		$order_id       = $post->ID;
		$payment_method = get_post_meta( $order_id, '_payment_method', true );
		if ( $this->id === $payment_method ) {
			add_meta_box(
				'ngenius-payment-actions',
				__( 'n-genius Payment Gateway', 'woocommerce' ),
				array( $this, 'ngenius_online_meta_box_payment' ),
				'shop_order',
				'side',
				'high'
			);
		}
	}

	/**
	 * Generate the n-genius payment meta box and echos the HTML
	 */
	public function ngenius_online_meta_box_payment() {
		global $post;
		$order_id = $post->ID;
		$order    = wc_get_order( $order_id );
		if ( ! empty( $order ) ) {
			$order_item = $this->fetch_order( $order_id );
			$html       = '';
			try {
				$available_for_capture = $order_item->amount - $order_item->captured_amt;
				$curency_code          = $order_item->currency . ' ';

				$html  = '<table border="0" cellspacing="10">';
				$html .= '<tr>';
				$html .= '<td>' . __( 'State:', 'woocommerce' ) . '</td>';
				$html .= '<td>' . $order_item->state . '</td>';
				$html .= '</tr>';
				$html .= '<tr>';
				$html .= '<td>' . __( 'Payment_ID:', 'woocommerce' ) . '</td>';
				$html .= '<td>' . $order_item->payment_id . '</td>';
				$html .= '</tr>';
				if ( 'ng-authorised' === $order_item->status ) {
					$html .= '<tr>';
					$html .= '<td>' . __( 'Authorized:', 'woocommerce' ) . '</td>';
					$html .= '<td>' . $curency_code . number_format( $order_item->amount, 2 ) . '</td>';
					$html .= '</tr>';
				}
				$html    .= '<tr>';
				$html    .= '<td>' . __( 'Captured:', 'woocommerce' ) . '</td>';
				$html    .= '<td>' . $curency_code . number_format( $order_item->captured_amt, 2 ) . '</td>';
				$html    .= '</tr>';
				$refunded = 0;
				if ( 'ng-full-refunded' === $order_item->status ) {
					$refunded = $order_item->amount;
				} elseif ( 'ng-part-refunded' === $order_item->status ) {
					$refunded = $order_item->amount - $order_item->captured_amt;
				}
				$html .= '<tr>';
				$html .= '<td>' . __( 'Refunded:', 'woocommerce' ) . '</td>';
				$html .= '<td>' . $curency_code . number_format( $refunded, 2 ) . '</td>';
				$html .= '</tr>';

				if ( 'ng-authorised' === $order_item->status ) {
					$html .= '<tr>';
					$html .= '<td><input id="ngenius_void_submit" class="button void" name="ngenius_void" type="submit" value="' . __( 'Void', 'woocommerce' ) . '" /></td>';
					$html .= '<td><input id="ngenius_capture_submit" class="button button-primary" name="ngenius_capture" type="submit" value="' . __( 'Capture', 'woocommerce' ) . '" /></td>';
					$html .= '</tr>';
				}

				$html .= '</table>';

				echo ent2ncr( $html );
			} catch ( Exception $e ) {

			}
		}
	}

	/**
	 * Handle actions on order page
	 *
	 * @param int $post_id
	 * @return null
	 */
	public function ngenius_online_actions( $post_id ) {
		$this->message = '';
		if ( isset( $_POST['ngenius_capture'] ) || isset( $_POST['ngenius_void'] ) ) {

			WC_Admin_Notices::remove_all_notices();
			$order_item = $this->fetch_order( $post_id );
			$order      = wc_get_order( $post_id );

			if ( $order && $order_item ) {
				$config      = new Ngenius_Gateway_Config( $this );
				$token_class = new Ngenius_Gateway_Request_Token( $config );

				if ( $config->is_complete() ) {
					$token = $token_class->get_access_token();
					if ( $token ) {
						$config->set_token( $token );
						if ( isset( $_POST['ngenius_capture'] ) ) {
							$this->ngenius_capture( $order, $config, $order_item );
						} elseif ( isset( $_POST['ngenius_void'] ) ) {
							$this->ngenius_void( $order, $config, $order_item );
						}
						WC_Admin_Notices::add_custom_notice( 'ngenius', $this->message );
					}
				} else {
					$this->message = 'Payment gateway credential are not configured properly.';
					WC_Admin_Notices::add_custom_notice( 'ngenius', $this->message );
				}
			} else {
				$this->message = 'Order #' . $post_id . ' not found.';
				WC_Admin_Notices::add_custom_notice( 'ngenius', $this->message );
			}
			add_filter( 'redirect_post_location', array( $this, 'add_notice_query_var' ), 99 );
			return;
		}
	}

	/**
	 * Process Capture
	 *
	 * @param WC_Order               $order
	 * @param Ngenius_Gateway_Config $config
	 * @param object                 $order_item
	 */
	public function ngenius_capture( $order, $config, $order_item ) {

		include_once dirname( __FILE__ ) . '/request/class-ngenius-gateway-request-capture.php';
		include_once dirname( __FILE__ ) . '/http/class-ngenius-gateway-http-capture.php';
		include_once dirname( __FILE__ ) . '/validator/class-ngenius-gateway-validator-capture.php';

		$request_class  = new Ngenius_Gateway_Request_Capture( $config );
		$request_http   = new Ngenius_Gateway_Http_Capture();
		$transfer_class = new Ngenius_Gateway_Http_Transfer();
		$validator      = new Ngenius_Gateway_Validator_Capture();

		$response = $request_http->place_request( $transfer_class->create( $request_class->build( $order_item ) ) );
		$result   = $validator->validate( $response );
		if ( $result ) {
			$data                 = [];
			$data['status']       = $result['order_status'];
			$data['state']        = $result['state'];
			$data['captured_amt'] = $result['total_captured'];
			$data['capture_id']   = $result['transaction_id'];
			$this->update_data( $data, array( 'nid' => $order_item->nid ) );
			$message       = 'Captured an amount ' . $order_item->currency . $result['captured_amt'];
			$this->message = 'Success! ' . $message . ' of an order #' . $order_item->order_id;
			$message      .= '. Transaction ID: ' . $result['transaction_id'];
			$order->payment_complete( $result['transaction_id'] );
			$order->update_status( $result['order_status'] );
			$order->add_order_note( $message );
			$emailer = new WC_Emails();
			$emailer->customer_invoice( $order );
		}
	}

	/**
	 * Process Authorization Reversal
	 *
	 * @param WC_Order               $order
	 * @param Ngenius_Gateway_Config $config
	 * @param object                 $order_item
	 */
	public function ngenius_void( $order, $config, $order_item ) {

		include_once dirname( __FILE__ ) . '/request/class-ngenius-gateway-request-void.php';
		include_once dirname( __FILE__ ) . '/http/class-ngenius-gateway-http-void.php';
		include_once dirname( __FILE__ ) . '/validator/class-ngenius-gateway-validator-void.php';

		$request_class  = new Ngenius_Gateway_Request_Void( $config );
		$request_http   = new Ngenius_Gateway_Http_Void();
		$transfer_class = new Ngenius_Gateway_Http_Transfer();
		$validator      = new Ngenius_Gateway_Validator_Void();

		$response = $request_http->place_request( $transfer_class->create( $request_class->build( $order_item ) ) );
		$result   = $validator->validate( $response );
		if ( $result ) {
			$data           = [];
			$data['status'] = $result['order_status'];
			$data['state']  = $result['state'];
			$this->update_data( $data, array( 'nid' => $order_item->nid ) );
			$this->message = 'The void transaction was successful.';
			$order->update_status( $result['order_status'] );
			$order->add_order_note( $this->message );
		}
	}

	/**
	 * Process Refund
	 *
	 * @param  int        $order_id
	 * @param  float|null $amount
	 * @param  string     $reason
	 * @return bool
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$this->message = '';
		if ( isset( $amount ) && $amount > 0 ) {
			include_once dirname( __FILE__ ) . '/request/class-ngenius-gateway-request-refund.php';
			include_once dirname( __FILE__ ) . '/http/class-ngenius-gateway-http-refund.php';
			include_once dirname( __FILE__ ) . '/validator/class-ngenius-gateway-validator-refund.php';

			$amount     = number_format( (float) $amount, 2 );
			$order_item = $this->fetch_order( $order_id );
			$order      = wc_get_order( $order_id );

			if ( $amount <= $order_item->captured_amt ) {
				$config      = new Ngenius_Gateway_Config( $this );
				$token_class = new Ngenius_Gateway_Request_Token( $config );

				if ( $config->is_complete() ) {
					$token = $token_class->get_access_token();
					if ( $token ) {
						$config->set_token( $token );
						$request_class  = new Ngenius_Gateway_Request_Refund( $config );
						$request_http   = new Ngenius_Gateway_Http_Refund();
						$transfer_class = new Ngenius_Gateway_Http_Transfer();
						$validator      = new Ngenius_Gateway_Validator_Refund();

						$response = $request_http->place_request( $transfer_class->create( $request_class->build( $order_item, $amount ) ) );
						$result   = $validator->validate( $response );
						if ( $result ) {
							$data                 = [];
							$data['status']       = $result['order_status'];
							$data['state']        = $result['state'];
							$data['captured_amt'] = $result['captured_amt'];
							$this->update_data( $data, array( 'nid' => $order_item->nid ) );
							$message       = 'Refunded an amount ' . $order_item->currency . $result['refunded_amt'];
							$this->message = 'Success! ' . $message . ' of an order #' . $order_item->order_id;
							$message      .= '. Transaction ID: ' . $result['transaction_id'];
							if ( 'refunded' !== $result['order_status'] ) {
								$order->update_status( $result['order_status'] );
							}
							$order->add_order_note( $message );
							WC_Admin_Notices::add_custom_notice( 'ngenius', $this->message );
							return true;
						}
					}
				}
			}
		}
		return false;
	}

}
