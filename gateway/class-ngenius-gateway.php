<?php

if ( ! defined('ABSPATH')) {
    exit;
}

require_once dirname(__FILE__) . '/class-ngenius-abstract.php';

/**
 * NgeniusGateway class.
 */
class NgeniusGateway extends NgeniusAbstract
{


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
     * @var NgeniusGateway
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
     * @return NgeniusGateway
     */
    public static function get_instance()
    {
        if ( ! isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Logging method.
     *
     * @param string $message Log message.
     * @param string $level Optional. Default 'info'. Possible values:
     *                        emergency|alert|critical|error|warning|notice|info|debug.
     */
    public static function log($message, $level = 'debug')
    {
        if (self::$log_enabled) {
            if (empty(self::$log)) {
                self::$log = wc_get_logger();
            }
            self::$log->log($level, $message, array('source' => 'ngenius'));
        }
    }

    /**
     * Cron Job Hook
     */
    public function ngenius_cron_task()
    {
        if ( ! wp_next_scheduled('ngenius_cron_order_update')) {
            wp_schedule_event(time(), 'hourly', 'ngenius_cron_order_update');
        }
        add_action('ngenius_cron_order_update', array($this, 'cron_order_update'));
    }

    // Add the Gateway to WooCommerce

    /**
     * Initilize module hooks
     */
    public function init_hooks()
    {
        add_action('init', array($this, 'ngenius_cron_task'));
        add_action('woocommerce_api_ngeniusonline', array($this, 'update_ngenius_response'));
        if (is_admin()) {
            add_filter('woocommerce_payment_gateways', array($this, 'ngenius_woocommerce_add_gateway_ngenius'));
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options')
            );
            add_action('add_meta_boxes', array($this, 'ngenius_online_meta_boxes'));
            add_action('save_post', array($this, 'ngenius_online_actions'));
        }
    }

    // WooCommerce DPO Group settings html

    public function ngenius_woocommerce_add_gateway_ngenius($methods)
    {
        $methods[] = $this->id;

        return $methods;
    }

    public function payment_fields()
    {
        $html = new stdClass();
        parent::payment_fields();
        do_action('dpocard_solution_addfields', $html);
        if (isset($html->html)) {
            echo esc_html($html->html);
        }
    }

    /**
     * Add notice query variable
     *
     * @param string $location
     *
     * @return string
     */
    public function add_notice_query_var($location)
    {
        remove_filter('redirect_post_location', array($this, 'add_notice_query_var'), 99);

        return add_query_arg(array('message' => false), $location);
    }

    /**
     * Processing order
     *
     * @param int $order_id
     *
     * @return array|null
     * @global object $woocommerce
     */
    public function process_payment($order_id)
    {
        include_once dirname(__FILE__) . '/request/class-ngenius-gateway-request-authorize.php';
        include_once dirname(__FILE__) . '/request/class-ngenius-gateway-request-sale.php';
        include_once dirname(__FILE__) . '/request/class-ngenius-gateway-request-purchase.php';
        include_once dirname(__FILE__) . '/http/class-ngenius-gateway-http-authorize.php';
        include_once dirname(__FILE__) . '/http/class-ngenius-gateway-http-purchase.php';
        include_once dirname(__FILE__) . '/http/class-ngenius-gateway-http-sale.php';
        include_once dirname(__FILE__) . '/validator/class-ngenius-gateway-validator-response.php';

        global $woocommerce;
        $order       = wc_get_order($order_id);
        $config      = new NgeniusGatewayConfig($this, $order);
        $token_class = new NgeniusGatewayRequestToken($config);
        $data        = "";

        if ($config->is_complete()) {
            $token = $token_class->get_access_token();

            if ($token && !is_wp_error($token)) {
                $config->set_token($token);
                if ($config->get_payment_action() == "authorize") {
                    $request_class = new NgeniusGatewayRequestAuthorize($config);
                    $request_http  = new NgeniusGatewayHttpAuthorize();
                } elseif ($config->get_payment_action() == "sale") {
                    $request_class = new NgeniusGatewayRequestSale($config);
                    $request_http  = new NgeniusGatewayHttpSale();
                }elseif ($config->get_payment_action() == "purchase") {
                    $request_class = new NgeniusGatewayRequestPurchase($config);
                    $request_http  = new NgeniusGatewayHttpPurchase();
                }

                $transfer_class = new NgeniusGatewayHttpTransfer();
                $validator      = new NgeniusGatewayValidatorResponse();

                $response = $request_http->place_request($transfer_class->create($request_class->build($order)));

                $result = $validator->validate($response);

                if ($result) {
                    $this->save_data($order);
                    $woocommerce->cart->empty_cart();
                    $data = array(
                        'result'   => 'success',
                        'redirect' => $result,
                    );
                }
            } else {
                $errorMsg = $token->errors['error'][0];

                if ($errorMsg == '') {
                    $errorMsg = 'Invalid configuration';
                }
                wc_add_notice("Error! " . $errorMsg . ".", 'error');
                $data = "";
            }
        } else {
            wc_add_notice('Error! Invalid configuration.', 'error');
            $data = "";
        }

        return $data;
    }

    /**
     * Save data
     *
     * @param object $order
     *
     * @global object $wp_session
     * @global object $wpdb
     */
    public function save_data($order)
    {
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
     * @param array $data
     * @param array $where
     *
     * @global object $wpdb
     */
    public function update_data(array $data, array $where)
    {
        global $wpdb;
        $wpdb->update(NGENIUS_TABLE, $data, $where);
    }

    /**
     * Processes and saves options.
     * If there is an error thrown, will continue to save and validate fields, but will leave the erroring field out.
     *
     * @return bool was anything saved?
     */
    public function process_admin_options()
    {
        $saved = parent::process_admin_options();
        if ('yes' === $this->get_option('enabled', 'no')) {
            if (empty($this->get_option('outlet_ref'))) {
                $this->add_settings_error(
                    'ngenius_error',
                    esc_attr('settings_updated'),
                    __('Invalid Reference ID'),
                    'error'
                );
            }
            if (empty($this->get_option('api_key'))) {
                $this->add_settings_error(
                    'ngenius_error',
                    esc_attr('settings_updated'),
                    __('Invalid API Key'),
                    'error'
                );
            }
            add_action('admin_notices', 'print_errors');
        }
        if ('yes' !== $this->get_option('debug', 'no')) {
            if (empty(self::$log)) {
                self::$log = wc_get_logger();
            }
            self::$log->clear('ngenius');
        }

        return $saved;
    }

    public function add_settings_error($setting, $code, $message, $type = 'error')
    {
        global $wp_settings_errors;

        $wp_settings_errors[] = array(
            'setting' => $setting,
            'code'    => $code,
            'message' => $message,
            'type'    => $type,
        );
    }

    /**
     * Catch response from N-Genius
     */
    public function update_ngenius_response()
    {
        $order_ref = filter_input(INPUT_GET, 'ref', FILTER_SANITIZE_STRING);
        include plugin_dir_path(__FILE__) . '/class-ngenius-gateway-payment.php';
        $payment = new NgeniusGatewayPayment();
        $payment->execute($order_ref);
        die;
    }

    /**
     * Cron Job Action
     */
    public function cron_order_update()
    {
        include plugin_dir_path(__FILE__) . '/class-ngenius-gateway-payment.php';
        $payment = new NgeniusGatewayPayment();
        if ($payment->order_update()) {
            $this->log('Cron updated the orders: ' . $payment->order_update());
        }
    }

    /**
     * Can the order be refunded?
     *
     * @param WC_Order $order Order object.
     *
     * @return bool
     */
    public function can_refund_order($order)
    {
        $order_item = $this->fetch_order($order->get_id());
        if (in_array($order_item->status, array('ng-complete', 'ng-captured', 'ng-part-refunded'), true)) {
            return true;
        }

        return false;
    }

    /**
     * Fetch Order details.
     *
     * @param int $order_id
     *
     * @return object
     */
    public function fetch_order($order_id)
    {
        global $wpdb;

        return $wpdb->get_row(sprintf('SELECT * FROM %s WHERE order_id=%d', NGENIUS_TABLE, $order_id));
    }

    /**
     * N-Genius Meta Boxes
     */
    public function ngenius_online_meta_boxes()
    {
        global $post;
        $order_id       = $post->ID;
        $payment_method = get_post_meta($order_id, '_payment_method', true);
        if ($this->id === $payment_method) {
            add_meta_box(
                'ngenius-payment-actions',
                __('N-Genius Payment Gateway', 'woocommerce'),
                array($this, 'ngenius_online_meta_box_payment'),
                'shop_order',
                'side',
                'high'
            );
        }
    }

    /**
     * Generate the N-Genius payment meta box and echos the HTML
     */
    public function ngenius_online_meta_box_payment()
    {
        global $post;
        $order_id = $post->ID;
        $order    = wc_get_order($order_id);
        if ( ! empty($order)) {
            $order_item = $this->fetch_order($order_id);
            $html       = '';
            try {
                $curency_code              = $order_item->currency . ' ';
                $ngauthorised              = "";
                $ngauthorised_amount       = $curency_code . number_format($order_item->amount, 2);
                $ngauthorised_amount_label = __('Authorized:', 'woocommerce');
                if ('ng-authorised' === $order_item->status) {
                    $ngauthorised = <<<HTML
                        <tr>
                            <td> $ngauthorised_amount_label </td>
                            <td> $ngauthorised_amount </td>
                        </tr>
HTML;
                }
                $refunded = 0;
                if ('ng-full-refunded' === $order_item->status || 'ng-part-refunded' === $order_item->status || 'refunded' === $order_item->status) {
                    $refunded = $order_item->captured_amt;
                }

                $ngauthorised2 = "";
                if ('ng-authorised' === $order_item->status) {
                    $ng_void       = __('Void', 'woocommerce');
                    $ng_capture    = __('Capture', 'woocommerce');
                    $ngauthorised2 = <<<HTML
                        <tr>
                            <td>
                                <input id="ngenius_void_submit" class="button void" name="ngenius_void" type="submit" value="$ng_void" />
                            </td>
                            <td>
                                <input id="ngenius_capture_submit" class="button button-primary" name="ngenius_capture" type="submit" value="$ng_capture" />
                            </td>
                        </tr>

HTML;
                }

                $ng_state       = __('State:', 'woocommerce');
                $ng_state_value = $order_item->state;

                $ng_payment_id_label = __('Payment_ID:', 'woocommerce');
                $ng_payment_id       = $order_item->payment_id;

                $ng_captured_label  = __('Captured:', 'woocommerce');
                $ng_captured_amount = $curency_code . number_format($order_item->amount, 2);

                $ng_refunded_label  = __('Refunded:', 'woocommerce');
                $ng_refunded_amount = $curency_code . number_format($refunded, 2);
                $html               = <<<HTML
                    <table>
                    <tr>
                        <td> $ng_state </td>
                        <td> $ng_state_value </td>
                    </tr>
                    <tr>
                        <td> $ng_payment_id_label </td>
                        <td> $ng_payment_id </td>
                    </tr>
                    $ngauthorised
                    <tr>
                        <td> $ng_captured_label </td>
				        <td> $ng_captured_amount </td>
                    </tr>
                    <tr>
				        <td> $ng_refunded_label </td>
				        <td> $ng_refunded_amount </td>
				    </tr>
				    $ngauthorised2
</table>
HTML;

                echo ent2ncr($html);
            } catch (Exception $e) {
                throw new InvalidArgumentException($e->getMessage());
            }
        }
    }

    /**
     * Handle actions on order page
     *
     * @param int $post_id
     *
     * @return null
     */
    public function ngenius_online_actions($post_id)
    {
        $this->message = '';
        if (isset($_POST['ngenius_capture']) || isset($_POST['ngenius_void'])) {
            WC_Admin_Notices::remove_all_notices();
            $order_item = $this->fetch_order($post_id);
            $order      = wc_get_order($post_id);

            if ($order && $order_item) {
                $config      = new NgeniusGatewayConfig($this, $order);
                $token_class = new NgeniusGatewayRequestToken($config);

                $this->validate_complete($config, $token_class, $order, $order_item);
            } else {
                $this->message = 'Order #' . $post_id . ' not found.';
                WC_Admin_Notices::add_custom_notice('ngenius', $this->message);
            }
            add_filter('redirect_post_location', array($this, 'add_notice_query_var'), 99);
        }
    }

    public function validate_complete($config, $token_class, $order, $order_item)
    {
        if ($config->is_complete()) {
            $token = $token_class->get_access_token();
            if ($token) {
                $config->set_token($token);
                if (isset($_POST['ngenius_capture'])) {
                    $this->ngenius_capture($order, $config, $order_item);
                } elseif (isset($_POST['ngenius_void'])) {
                    $this->ngenius_void($order, $config, $order_item);
                }
                WC_Admin_Notices::add_custom_notice('ngenius', $this->message);
            }
        } else {
            $this->message = 'Payment gateway credential are not configured properly.';
            WC_Admin_Notices::add_custom_notice('ngenius', $this->message);
        }
    }

    /**
     * Process Capture
     *
     * @param WC_Order $order
     * @param NgeniusGatewayConfig $config
     * @param object $order_item
     */
    public function ngenius_capture($order, $config, $order_item)
    {
        include_once dirname(__FILE__) . '/request/class-ngenius-gateway-request-capture.php';
        include_once dirname(__FILE__) . '/http/class-ngenius-gateway-http-capture.php';
        include_once dirname(__FILE__) . '/validator/class-ngenius-gateway-validator-capture.php';

        $request_class  = new NgeniusGatewayRequestCapture($config);
        $request_http   = new NgeniusGatewayHttpCapture();
        $transfer_class = new NgeniusGatewayHttpTransfer();
        $validator      = new NgeniusGatewayValidatorCapture();

        $response = $request_http->place_request($transfer_class->create($request_class->build($order_item)));
        $result   = $validator->validate($response);
        if ($result['status'] != "failed") {
            $data                 = [];
            $data['status']       = $result['order_status'];
            $data['state']        = $result['state'];
            $data['captured_amt'] = $result['total_captured'];
            $data['capture_id']   = $result['transaction_id'];
            $this->update_data($data, array('nid' => $order_item->nid));
            $order_message = 'Captured an amount ' . $order_item->currency . $result['captured_amt'];
            $this->message = 'Success! ' . $order_message . ' of an order #' . $order_item->order_id;
            $order_message .= '. Transaction ID: ' . $result['transaction_id'];
            $order->payment_complete($result['transaction_id']);
            $order->update_status($result['order_status']);
            $order->add_order_note($order_message);
            $emailer = new WC_Emails();
            $emailer->customer_invoice($order);
        }else{
			$order_message = $result['message'];
			$order->add_order_note($order_message);
		}
    }

}
