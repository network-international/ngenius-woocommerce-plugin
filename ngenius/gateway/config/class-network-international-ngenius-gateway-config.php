<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * NetworkInternationalNgeniusGatewayConfig class.
 */
class NetworkInternationalNgeniusGatewayConfig
{
    /**
     * Config tags
     */
    public const TOKEN_ENDPOINT   = '/identity/auth/access-token';
    public const ORDER_ENDPOINT   = '/transactions/outlets/%s/orders';
    public const FETCH_ENDPOINT   = '/transactions/outlets/%s/orders/%s';
    public const CAPTURE_ENDPOINT = '/transactions/outlets/%s/orders/%s/payments/%s/captures';
    public const REFUND_ENDPOINT  = '/transactions/outlets/%s/orders/%s/payments/%s/captures/%s/refund';
    public const VOID_ENDPOINT    = '/transactions/outlets/%s/orders/%s/payments/%s/cancel';

    /**
     * Pointer to gateway making the request.
     *
     * @var NetworkInternationalNgeniusGateway
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
     * @param NetworkInternationalNgeniusGateway $gateway N-Genius gateway object.
     */
    public function __construct(NetworkInternationalNgeniusGateway $gateway, $order = "")
    {
        $this->order   = $order;
        $this->gateway = $gateway;
    }

    /**
     * Set token
     *
     * @param string $token
     */
    public function set_token(string $token): void
    {
        $this->token = $token;
    }

    /**
     * Get token
     *
     * @return string Token
     */
    public function get_token(): string
    {
        return $this->token;
    }

    /**
     * Retrieve apikey and outletReferenceId empty or not
     *
     * @return bool
     */
    public function is_complete(): bool
    {
        return (!empty($this->get_api_key()) && !empty($this->get_outlet_reference_id()));
    }

    /**
     * Gets Payment Action.
     *
     * @return string
     */
    public function get_payment_action(): string
    {
        return $this->gateway->get_option('paymentAction');
    }

    /**
     * Gets Environment.
     *
     * @return string
     */
    public function get_environment(): string
    {
        return $this->gateway->get_option('environment');
    }

    /**
     * Gets Debug Mode.
     *
     * @return string
     */
    public function get_debug_mode(): string
    {
        return $this->gateway->get_option('debugMode');
    }

    /**
     * Gets Api Url.
     *
     * @return string
     */
    public function get_api_url(): string
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
    public function get_outlet_reference_id(): string
    {
        return $this->should_use_outlet_override_ref() ? $this->gateway->get_option(
            'outlet_override_ref'
        ) : $this->gateway->get_option('outletRef');
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
            if (is_array($this->order) && !empty($this->order)) {
                $orderCurrency = $this->order[0]->currency ?? '';
            } else {
                $orderCurrency = method_exists($this->order, 'get_currency') ?
                    $this->order->get_currency() : '';
            }
            foreach ($overriddenCurrencies as $overriddenCurrency) {
                if ($orderCurrency === NetworkInternationalNgeniusSettings::$currencies[intval($overriddenCurrency)]) {
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
    public function get_api_key(): string
    {
        return $this->gateway->get_option('apiKey');
    }

    /**
     * Gets TokenRequest URL.
     *
     * @return string
     */
    public function get_token_request_url(): string
    {
        return $this->get_api_url() . sprintf(self::TOKEN_ENDPOINT);
    }

    /**
     * Gets Order Request URL.
     *
     * @return string
     */
    public function get_order_request_url(): string
    {
        $endpoint = sprintf(self::ORDER_ENDPOINT, $this->get_outlet_reference_id());

        return $this->get_api_url() . $endpoint;
    }

    /**
     * Gets Fetch Request URL.
     *
     * @param string $orderRef
     *
     * @return string
     */
    public function get_fetch_request_url(string $orderRef): string
    {
        $endpoint = sprintf(self::FETCH_ENDPOINT, $this->get_outlet_reference_id(), $orderRef);

        return $this->get_api_url() . $endpoint;
    }

    /**
     * Gets Order Capture URL.
     *
     * @param string $orderRef
     * @param string $paymentRef
     *
     * @return string
     */
    public function get_order_capture_url(string $orderRef, string $paymentRef): string
    {
        $endpoint = sprintf(self::CAPTURE_ENDPOINT, $this->get_outlet_reference_id(), $orderRef, $paymentRef);

        return $this->get_api_url() . $endpoint;
    }

    /**
     * Gets Order Refund URL.
     *
     * @param string $orderRef
     * @param string $paymentRef
     * @param string $transactionId
     *
     * @return string
     */
    public function get_order_refund_url(string $orderRef, string $paymentRef, string $transactionId): string
    {
        $endpoint = sprintf(
            self::REFUND_ENDPOINT,
            $this->get_outlet_reference_id(),
            $orderRef,
            $paymentRef,
            $transactionId
        );

        return $this->get_api_url() . $endpoint;
    }

    /**
     * Gets Order Void URL.
     *
     * @param string $orderRef
     * @param string $paymentRef
     *
     * @return string
     */
    public function get_order_void_url(string $orderRef, string $paymentRef): string
    {
        $endpoint = sprintf(self::VOID_ENDPOINT, $this->get_outlet_reference_id(), $orderRef, $paymentRef);

        return $this->get_api_url() . $endpoint;
    }

    public function get_default_complete_order_status(): string
    {
        return $this->gateway->get_option('default_complete_order_status') ?? "no";
    }

    public function get_http_version(): string
    {
        return $this->gateway->get_option('curl_http_version') ?? "CURL_HTTP_VERSION_NONE";
    }

    public function get_custom_order_fields(): string
    {
        return $this->gateway->get_option('customOrderFields') ?? "";
    }
}
