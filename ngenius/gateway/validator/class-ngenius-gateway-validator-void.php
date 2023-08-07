<?php

if (! defined('ABSPATH')) {
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
                'orderStatus' => "cancelled",
                'error'  =>  $response->get_error_message(),
                'message'  =>  $response->get_error_message(),
            );
        } else {
            if (! isset($response['result']['orderStatus']) && empty($response['result']['orderStatus'])) {
                return false;
            } else {
                return $response['result'];
            }
        }
    }
}
