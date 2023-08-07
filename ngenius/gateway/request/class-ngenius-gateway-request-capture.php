<?php

if (! defined('ABSPATH')) {
    exit;
}

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
        return [
            'token'   => $this->config->get_token(),
            'request' => [
                'data'   => [
                    'amount' => [
                        'currencyCode' => $order_item->currency,
                        'value'        => $order_item->amount * 100,
                    ],
                ],
                'method' => 'POST',
                'uri'    => $this->config->get_order_capture_url($order_item->reference, $order_item->payment_id),
            ],
        ];
    }
}
