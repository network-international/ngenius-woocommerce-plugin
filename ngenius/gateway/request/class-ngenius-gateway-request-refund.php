<?php

if (! defined('ABSPATH')) {
    exit;
}


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
        return [
            'token'   => $this->config->get_token(),
            'request' => [
                'data'   => [
                    'amount' => [
                        'currencyCode' => $order_item->currency,
                        'value'        => $amount * 100,
                    ],
                ],
                'method' => 'POST',
                'uri'    => $url,
            ],
        ];
    }
}
