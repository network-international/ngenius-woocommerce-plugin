<?php

if (! defined('ABSPATH')) {
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
     * Builds abstract request array
     *
     * @param array $order
     *
     * @return array
     */
    abstract public function get_build_array($order);
}
