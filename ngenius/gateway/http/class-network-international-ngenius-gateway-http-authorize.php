<?php

add_action('plugins_loaded', function() {
    if (!class_exists('WooCommerce')) {
        return;
    }

    if (!defined('ABSPATH')) {
        exit;
    }
});

/**
 * NetworkInternationalNgeniusGatewayHttpAuthorize class.
 */
class NetworkInternationalNgeniusGatewayHttpAuthorize extends NetworkInternationalNgeniusGatewayHttpAbstract
{
    /**
     * Processing of API request body
     *
     * @param array $data
     *
     * @return string
     */
    protected function pre_process(array $data): string
    {
        return wp_json_encode($data);
    }
}
