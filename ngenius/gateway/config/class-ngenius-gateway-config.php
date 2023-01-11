<?php

if ( ! defined('ABSPATH')) {
    exit;
}

/**
 * NgeniusGatewayConfig class.
 */
class NgeniusGatewayConfig
{


    /**
     * Config tags
     */
    const TOKEN_ENDPOINT    = '/identity/auth/access-token';
    const ORDER_ENDPOINT    = '/transactions/outlets/%s/orders';
    const FETCH_ENDPOINT    = '/transactions/outlets/%s/orders/%s';
    const CAPTURE_ENDPOINT  = '/transactions/outlets/%s/orders/%s/payments/%s/captures';
    const REFUND_ENDPOINT   = '/transactions/outlets/%s/orders/%s/payments/%s/captures/%s/refund';
    const VOID_ENDPOINT     = '/transactions/outlets/%s/orders/%s/payments/%s/cancel';

    /**
     * Pointer to gateway making the request.
     *
     * @var NgeniusGateway
     */
    public $gateway;

    /**
     * Token for gateway request
     *
     * @var string token
     */
    private $token;

    public $order;

    /**
     * Constructor.
     *
     * @param NgeniusGateway $gateway N-Genius gateway object.
     */
    public function __construct(NgeniusGateway $gateway, $order="")
    {
        $this->order = $order;
        $this->gateway = $gateway;
    }

    /**
     * Set token
     *
     * @param string $token
     */
    public function set_token($token)
    {
        $this->token = $token;
    }

    /**
     * Get token
     *
     * @return string Token
     */
    public function get_token()
    {
        return $this->token;
    }

    /**
     * Retrieve apikey and outletReferenceId empty or not
     *
     * @return bool
     */
    public function is_complete()
    {
        return ( ! empty($this->get_api_key()) && ! empty($this->get_outlet_reference_id()));
    }

    /**
     * Gets Payment Action.
     *
     * @return string
     */
    public function get_payment_action()
    {
        return $this->gateway->get_option('payment_action');
    }

    /**
     * Gets Environment.
     *
     * @return string
     */
    public function get_environment()
    {
        return $this->gateway->get_option('environment');
    }

    /**
     * Gets Api Url.
     *
     * @return string
     */
    public function get_api_url()
    {
        $value = $this->gateway->get_option('live_api_url');
        if ($this->get_environment() == "uat") {
            $value = $this->gateway->get_option('uat_api_url');
        }

        return $value;
    }

    /**
     * Gets Outlet Reference Id.
     *
     * @return string
     */
    public function get_outlet_reference_id()
    {
        return $this->should_use_outlet_override_ref() ? $this->gateway->get_option('outlet_override_ref') : $this->gateway->get_option('outlet_ref');
    }
    /**
     * Returns true or false if order currency is selected for Outlet Ref 2.
     *
     * @return bool
     */
    public function should_use_outlet_override_ref(): bool
    {
        $overriddenCurrencies = $this->gateway->get_option('outlet_override_currency');
        if (is_array($overriddenCurrencies)) {
            $order_currency = method_exists($this->order, 'get_currency') ? $this->order->get_currency() : $this->order[0]->currency;
            foreach ($overriddenCurrencies as $overriddenCurrency){
                if ($order_currency === SettingsNgenius::$currencies[intval($overriddenCurrency)]) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Gets Api Key.
     *
     * @return string
     */
    public function get_api_key()
    {
        return $this->gateway->get_option('api_key');
    }

    /**
     * Gets TokenRequest URL.
     *
     * @return string
     */
    public function get_token_request_url()
    {
        return $this->get_api_url() . sprintf(self::TOKEN_ENDPOINT);
    }

    /**
     * Gets Order Request URL.
     *
     * @return string
     */
    public function get_order_request_url()
    {
        $endpoint = sprintf(self::ORDER_ENDPOINT, $this->get_outlet_reference_id());

        return $this->get_api_url() . $endpoint;
    }

    /**
     * Gets Fetch Request URL.
     *
     * @param string $order_ref
     *
     * @return string
     */
    public function get_fetch_request_url($order_ref)
    {
        $endpoint = sprintf(self::FETCH_ENDPOINT, $this->get_outlet_reference_id(), $order_ref);

        return $this->get_api_url() . $endpoint;
    }

    /**
     * Gets Order Capture URL.
     *
     * @param string $order_ref
     * @param string $payment_ref
     *
     * @return string
     */
    public function get_order_capture_url($order_ref, $payment_ref)
    {
        $endpoint = sprintf(self::CAPTURE_ENDPOINT, $this->get_outlet_reference_id(), $order_ref, $payment_ref);

        return $this->get_api_url() . $endpoint;
    }

    /**
     * Gets Order Refund URL.
     *
     * @param string $order_ref
     * @param string $payment_ref
     * @param string $transaction_id
     *
     * @return string
     */
    public function get_order_refund_url($order_ref, $payment_ref, $transaction_id)
    {
        $endpoint = sprintf(
            self::REFUND_ENDPOINT,
            $this->get_outlet_reference_id(),
            $order_ref,
            $payment_ref,
            $transaction_id
        );

        return $this->get_api_url() . $endpoint;
    }

    /**
     * Gets Order Void URL.
     *
     * @param string $order_ref
     * @param string $payment_ref
     *
     * @return string
     */
    public function get_order_void_url($order_ref, $payment_ref)
    {
        $endpoint = sprintf(self::VOID_ENDPOINT, $this->get_outlet_reference_id(), $order_ref, $payment_ref);

        return $this->get_api_url() . $endpoint;
    }

}
