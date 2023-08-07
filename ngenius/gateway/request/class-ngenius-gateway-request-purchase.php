<?php

require_once 'class-ngenius-gateway-request-abstract.php';

/**
 * NgeniusGatewayRequestPurchase class.
 */

class NgeniusGatewayRequestPurchase extends NgeniusGatewayRequestAbstract
{
    /**
     * Builds sale request array
     *
     * @param array $order
     *
     * @return array
     */
    public function get_build_array($order)
    {
        $currency = $order->get_currency();
        $amount = strval($order->get_total() * 100);
        if ($currency === "UGX" || $currency === "XOF") {
            $amount = $amount/100;
        }
        return [
            'data'   => [
                'action'                 => 'PURCHASE',
                'amount'                 => [
                    'currencyCode' => $currency,
                    'value'        => $amount,
                ],
                'merchantAttributes'     => [
                    'redirectUrl'          => add_query_arg('wc-api',
                    'ngeniusonline', home_url('/')),
                    'skipConfirmationPage' => true,
                ],
                'merchantOrderReference' => $order->get_id(),
                'emailAddress'           => $order->get_billing_email(),
                'billingAddress'         => [
                    'firstName' => $order->get_billing_first_name(),
                    'lastName'  => $order->get_billing_last_name(),
                ]
            ],
            'method' => 'POST',
            'uri'    => $this->config->get_order_request_url(),
        ];
    }
}
