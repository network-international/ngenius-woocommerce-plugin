<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * NgeniusGatewayRequestAbstract class.
 */
abstract class NgeniusGatewayRequestAbstract
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
     * Builds request array
     *
     * @param array $order
     *
     * @return array
     */
    public function build($order)
    {
        return [
            'token'   => $this->config->get_token(),
            'request' => $this->get_build_array($order),
        ];
    }

    /**
     * Gets custom order meta field string
     *
     * @param $order
     *
     * @return string
     */
    public function getCustomOrderFields($order): string
    {
        $metaKey = $this->config->get_custom_order_fields();

        return $order->get_meta($metaKey, true);
    }

    /**
     * Builds abstract request array
     *
     * @param array $order
     *
     * @return array
     */
    abstract public function get_build_array($order);
}
