<?php

if (!defined('ABSPATH')) {
    exit;
}

use Ngenius\NgeniusCommon\Formatter\ValueFormatter;

/**
 * Class NgeniusGatewayRequestRefund
 */
class NgeniusGatewayRequestRefund
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param NgeniusGatewayConfig $config
     */
    public function __construct(NgeniusGatewayConfig $config)
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

        ValueFormatter::formatCurrencyAmount($currencyCode, $amount);

        return [
            'token'   => $this->config->get_token(),
            'request' => [
                'data'   => [
                    'amount'              => [
                        'currencyCode' => $currencyCode,
                        'value'        => $amount * 100,
                    ],
                    'merchantDefinedData' => [
                        'pluginName'    => 'woocommerce',
                        'pluginVersion' => WC_GATEWAY_NGENIUS_VERSION
                    ]
                ],
                'method' => 'POST',
                'uri'    => $url,
            ],
        ];
    }
}
