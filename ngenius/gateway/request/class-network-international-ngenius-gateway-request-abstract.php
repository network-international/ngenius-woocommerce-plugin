<?php

add_action('plugins_loaded', function() {
    if (!defined('ABSPATH')) {
        exit;
    }

    if (!class_exists('WooCommerce')) {
        return;
    }
});

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
     * Builds a signed callback URL so only genuine payment redirects can update orders.
     *
     * @param WC_Order $order
     *
     * @return string
     */
    protected function get_redirect_url($order): string
    {
        $order_id = (int) $order->get_id();
        $order_key = (string) $order->get_order_key();
        $signature = hash_hmac('sha256', $order_id . '|' . $order_key, wp_salt('auth'));

        return add_query_arg(
            array(
                'wc-api'      => 'ngeniusonline',
                'oid'         => $order_id,
                'ngenius_sig' => $signature,
            ),
            home_url('/')
        );
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
