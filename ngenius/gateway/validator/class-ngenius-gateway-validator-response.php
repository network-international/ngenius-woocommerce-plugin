<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class NgeniusGatewayValidatorResponse
 */
class NgeniusGatewayValidatorResponse
{
    /**
     * Performs response validation for transaction
     *
     * @param array $response
     *
     * @return bool
     */
    public function validate($response)
    {
        if (is_wp_error($response)) {
            throw new InvalidArgumentException(wp_kses_post($response->get_error_message()));
        } else {
            if (isset($response['payment_url']) && filter_var($response['payment_url'], FILTER_VALIDATE_URL)) {
                return $response['payment_url'];
            } else {
                wc_add_notice('Error! Invalid payment gateway URL.', 'error');

                return false;
            }
        }
    }
}
