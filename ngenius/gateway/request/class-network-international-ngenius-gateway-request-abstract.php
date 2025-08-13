<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * NetworkInternationalNgeniusGatewayRequestAbstract class.
 */
abstract class NetworkInternationalNgeniusGatewayRequestAbstract
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
