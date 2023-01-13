<?php

require_once 'class-ngenius-gateway-request-abstract.php';

/**
 * Class NgeniusGatewayRequestSale
 */
class NgeniusGatewayRequestSale extends NgeniusGatewayRequestAbstract
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
        $amount = strval($order->get_total() * 100);
        if ($order->get_currency() == "UGX") {
            $amount = $amount/100;
        }
        return [
            'data'   => [
                'action'                 => 'SALE',
                'amount'                 => [
                    'currencyCode' => $order->get_currency(),
                    'value'        => $amount,
                ],
                'merchantAttributes'     => [
                    'redirectUrl' => add_query_arg('wc-api', 'ngeniusonline', home_url('/')),
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
