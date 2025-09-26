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
 * Class NetworkInternationalNgeniusGatewayValidatorVoid
 */
class NetworkInternationalNgeniusGatewayValidatorVoid
{
    /**
     * Performs reversed the authorization
     *
     * @param array $response
     *
     * @return bool
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
            if (!isset($response['result']['orderStatus']) && empty($response['result']['orderStatus'])) {
                return false;
            } else {
                return $response['result'];
            }
        }
    }
}
