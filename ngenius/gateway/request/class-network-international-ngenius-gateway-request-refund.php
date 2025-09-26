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
 * Class NetworkInternationalNgeniusGatewayRequestRefund
 */
class NetworkInternationalNgeniusGatewayRequestRefund
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
     * Builds ENV refund request
     *
     * @param object $order_item
     * @param float $amount
     *
     * @return array|null
     */
    public function build($order_item, $amount, $url)
    {
        $currencyCode = $order_item->currency;

        $amount = ValueFormatter::floatToIntRepresentation($currencyCode, $amount);

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
                'uri'    => $url,
            ],
        ];
    }
}
