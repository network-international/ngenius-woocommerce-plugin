<?php

if (!defined('ABSPATH')) {
    exit;
}

use Ngenius\NgeniusCommon\Formatter\ValueFormatter;

/**
 * NgeniusGatewayRequestCapture class.
 */
class NgeniusGatewayRequestCapture
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
                        'pluginVersion' => WC_GATEWAY_NGENIUS_VERSION
                    ]
                ],
                'method' => 'POST',
                'uri'    => $this->config->get_order_capture_url($order_item->reference, $order_item->payment_id),
            ],
        ];
    }
}
