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
 * Class NetworkInternationalNgeniusGatewayValidatorCapture
 */
class NetworkInternationalNgeniusGatewayValidatorCapture
{
    /**
     * Performs validation for capture transaction
     *
     * @param array $response
     *
     * @return bool|null
     */
    public function validate($response)
    {
        if (is_wp_error($response)) {
            return array(
                'status'      => "failed",
                'orderStatus' => "cancelled",
                'error'       => $response->get_error_message(),
                'message'     => $response->get_error_message(),
            );
        } else {
            if (!isset($response['result']) && !is_array($response['result'])) {
                return false;
            } else {
                return $response['result'];
            }
        }
    }
}
