<?php

if ( ! defined('ABSPATH')) {
    exit;
}

/**
 * Class NgeniusGatewayValidatorVoid
 */
class NgeniusGatewayValidatorVoid
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
                'status' => "failed",
                'order_status' => "cancelled",
                'error'  =>  $response->get_error_message(),
                'message'  =>  $response->get_error_message(),
            );
        } else {
            if ( ! isset($response['result']['order_status']) && empty($response['result']['order_status'])) {
                return false;
            } else {
                return $response['result'];
            }
        }
    }

}
