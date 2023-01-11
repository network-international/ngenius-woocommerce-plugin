<?php

if ( ! defined('ABSPATH')) {
    exit;
}

require_once dirname(__FILE__) . '/config/class-ngenius-gateway-config.php';
require_once dirname(__FILE__) . '/request/class-ngenius-gateway-request-token.php';
require_once dirname(__FILE__) . '/http/class-ngenius-gateway-http-transfer.php';
require_once dirname(__FILE__) . '/http/class-ngenius-gateway-http-abstract.php';

/**
 * Class NgeniusAbstract
 */
class NgeniusAbstract extends WC_Payment_Gateway
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
     * Constructor for the gateway.
     */
    public function __construct()
    {
        $this->id                 = 'ngenius';
        $this->icon               = ''; // URL of the icon that will be displayed on checkout page near your gateway name
        $this->has_fields         = false; // in case you need a custom credit card form
        $this->method_title       = 'N-Genius Payment Gateway';
        $this->method_description = 'Payment Gateway from Network International Payment Solutions'; // will be displayed on the options page
        // gateways can support subscriptions, refunds, saved payment methods
        $this->supports = array(
            'products',
            'refunds',
        );
        include_once dirname(__FILE__) . '/settings-ngenius.php';

        // Method with all the options fields
        $settingsNgenius = new SettingsNgenius();
        $this->form_fields = $settingsNgenius->overrideFormFieldsVariable();

        // Load the settings.
        $this->init_settings();

        $this->method_title       = __('N-Genius', $this->id);
        $this->method_description = __(
            'N-Genius works by sending the customer to N-Genius to complete their payment.',
            $this->id
        );

        $this->title          = $this->get_option('title');
        $this->description    = $this->get_option('description');
        $this->enabled        = $this->get_option('enabled');
        $this->environment    = $this->get_option('environment');
        $this->payment_action = $this->get_option('payment_action');
        $this->order_status   = $this->get_option('order_status');
        $this->outlet_ref     = $this->get_option('outlet_ref');
        $this->api_key        = $this->get_option('api_key');
        $this->debug          = 'yes' === $this->get_option('debug', 'no');
        self::$log_enabled    = $this->debug;
    }

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
     * Process Authorization Reversal
     *
     * @param WC_Order $order
     * @param NgeniusGatewayConfig $config
     * @param object $order_item
     */
    public function ngenius_void($order, $config, $order_item)
    {
        include_once dirname(__FILE__) . '/request/class-ngenius-gateway-request-void.php';
        include_once dirname(__FILE__) . '/http/class-ngenius-gateway-http-void.php';
        include_once dirname(__FILE__) . '/validator/class-ngenius-gateway-validator-void.php';

        $request_class  = new NgeniusGatewayRequestVoid($config);
        $request_http   = new NgeniusGatewayHttpVoid();
        $transfer_class = new NgeniusGatewayHttpTransfer();
        $validator      = new NgeniusGatewayValidatorVoid();

        $response = $request_http->place_request($transfer_class->create($request_class->build($order_item)));
        $result   = $validator->validate($response);
        if ($result) {
            $data           = [];
            $data['status'] = $result['order_status'];
            $data['state']  = $result['state'];
            $this->update_data($data, array('nid' => $order_item->nid));
            $this->message = 'The void transaction was successful.';
            $order->update_status($result['order_status']);
            $order->add_order_note($this->message);
        }
    }

    /**
     * Process Refund
     *
     * @param int $order_id
     * @param float|null $amount
     * @param string $reason
     *
     * @return bool
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $this->message = '';
        if (isset($amount) && $amount > 0) {
            include_once dirname(__FILE__) . '/request/class-ngenius-gateway-request-refund.php';
            include_once dirname(__FILE__) . '/http/class-ngenius-gateway-http-refund.php';
            include_once dirname(__FILE__) . '/validator/class-ngenius-gateway-validator-refund.php';
            include_once dirname(__FILE__) . '/class-ngenius-gateway-payment.php';

            $amount     = number_format((float)$amount, 2);
            $order_item = $this->fetch_order($order_id);
            $order      = wc_get_order($order_id);

            $config      = new NgeniusGatewayConfig($this, $order);
            $token_class = new NgeniusGatewayRequestToken($config);

            if ($config->is_complete()) {
                $token = $token_class->get_access_token();

                return $this->validate_token($token, $config, $order, $order_item,$amount);
            }
        }

        return false;
    }

    public function validate_token($token, $config, $order, $order_item,$amount)
    {
        if ($token) {
            $config->set_token($token);
            $request_class   = new NgeniusGatewayRequestRefund($config);
            $request_http    = new NgeniusGatewayHttpRefund();
            $transfer_class  = new NgeniusGatewayHttpTransfer();
            $validator       = new NgeniusGatewayValidatorRefund();
            $refund_url      = $this->get_refund_url($order_item->reference);
            $response        = $request_http->place_request(
                $transfer_class->create($request_class->build($order_item, $amount,$refund_url))
            );

            $result          = $validator->validate($response);
            $response_result = $this->validate_result($result, $order, $order_item);
            if ($response_result) {
                return true;
            }
        }
    }

    public function validate_result($result, $order, $order_item)
    {
        if ($result) {
            $data                 = [];
            $data['status']       = $result['order_status'];
            $data['state']        = $result['state'];
            $data['captured_amt'] = $result['captured_amt'];
            $this->update_data($data, array('nid' => $order_item->nid));
            $order_message = 'Refunded an amount ' . $order_item->currency . $result['refunded_amt'];
            $this->message = 'Success! ' . $order_message . ' of an order #' . $order_item->order_id;
            $order_message .= '. Transaction ID: ' . $result['transaction_id'];
            if ('refunded' !== $result['order_status']) {
                $order->update_status($result['order_status']);
            }
            $order->add_order_note($order_message);
            WC_Admin_Notices::add_custom_notice('ngenius', $this->message);

            return true;
        }
    }

    /**
     * @return refund_url
     * Get response from api for order ref code end
     */
    public function get_refund_url($order_ref){
        $payment = new NgeniusGatewayPayment();
        $response = $payment->get_response_api($order_ref);

        if(isset($response->errors)){
            return $response->errors[0]->message;
        }

        $cnpcapture = "cnp:capture";
        $cnprefund = 'cnp:refund';

        $payment = $response->_embedded->payment[0];

        $refund_url = "";
        if($payment->state == "PURCHASED" && isset($payment->_links->$cnprefund->href)){
            $refund_url = $payment->_links->$cnprefund->href;
        }elseif($payment->state == "CAPTURED" && isset($payment->_embedded->$cnpcapture[0]->_links->$cnprefund->href)){
            $refund_url = $payment->_embedded->$cnpcapture[0]->_links->$cnprefund->href;
        }else {
            if (isset($payment->_links->$cnprefund->href)) {
                $refund_url = $payment->_embedded->$cnpcapture[0]->_links->$cnprefund->href;
            }
        }

        return $refund_url;
    }

}
