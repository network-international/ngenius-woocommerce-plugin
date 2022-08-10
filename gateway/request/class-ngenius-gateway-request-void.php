<?php

if ( ! defined('ABSPATH')) {
    exit;
}

/**
 * Class NgeniusGatewayRequestVoid
 */
class NgeniusGatewayRequestVoid
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
     * Builds ENV void request
     *
     * @param object $order_item
     *
     * @return array|null
     */
    public function build($order_item)
    {
        return [
            'token'   => $this->config->get_token(),
            'request' => [
                'data'   => [],
                'method' => 'PUT',
                'uri'    => $this->config->get_order_void_url($order_item->reference, $order_item->payment_id),
            ],
        ];
    }

}
