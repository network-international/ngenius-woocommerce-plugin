<?php

use Ngenius\NgeniusCommon\Formatter\ValueFormatter;

add_action('plugins_loaded', function() {
    if (!defined('ABSPATH')) {
        exit;
    }

    if (!class_exists('WooCommerce')) {
        return;
    }
});

/**
 * NetworkInternationalNgeniusGatewayRequestCapture class.
 */
class NetworkInternationalNgeniusGatewayRequestCapture
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param NetworkInternationalNgeniusGatewayConfig $config
     */
    public function __construct(NetworkInternationalNgeniusGatewayConfig $config)
    {
        $this->config = $config;
    }

    /**
     * Builds Capture request
     *
     * @param array $order_item
     *
     * @return array
     */
    public function build($order_item)
    {
        $currencyCode = $order_item->currency;

        $amount = ValueFormatter::floatToIntRepresentation($currencyCode, $order_item->amount);

        return [
            'token'   => $this->config->get_token(),
            'request' => [
                'data'   => [
                    'amount'              => [
                        'currencyCode' => $currencyCode,
                        'value'        => $amount,
                    ],
                    'merchantDefinedData' => [
                        'pluginName'    => 'woocommerce',
                        'pluginVersion' => NETWORK_INTERNATIONAL_NGENIUS_VERSION
                    ]
                ],
                'method' => 'POST',
                'uri'    => $this->config->get_order_capture_url($order_item->reference, $order_item->payment_id),
            ],
        ];
    }
}
