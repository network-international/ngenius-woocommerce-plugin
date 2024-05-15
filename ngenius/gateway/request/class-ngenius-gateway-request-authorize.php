<?php

require_once 'class-ngenius-gateway-request-abstract.php';

use Ngenius\NgeniusCommon\Formatter\ValueFormatter;

/**
 * NgeniusGatewayRequestAuthorize class.
 */
class NgeniusGatewayRequestAuthorize extends NgeniusGatewayRequestAbstract
{
    /**
     * Builds athorization request array
     *
     * @param array $order
     *
     * @return array
     */
    public function get_build_array($order)
    {
        $currency    = $order->get_currency();
        $amount      = strval($order->get_total() * 100);
        $countryCode = WC()->countries->get_country_calling_code($order->get_billing_country());

        ValueFormatter::formatCurrencyAmount($currency, $amount);

        return [
            'data'   => [
                'action'                 => 'AUTH',
                'amount'                 => [
                    'currencyCode' => $currency,
                    'value'        => $amount,
                ],
                'merchantAttributes'     => [
                    'redirectUrl'          => add_query_arg('wc-api', 'ngeniusonline', home_url('/')),
                    'skipConfirmationPage' => true,
                    'cancelUrl'            => str_replace('&amp;', '&', $order->get_cancel_order_url()),
                    'cancelText'           => 'Continue Shopping'
                ],
                'merchantOrderReference' => $order->get_id(),
                'emailAddress'           => $order->get_billing_email(),
                'billingAddress'         => [
                    'firstName'   => $order->get_billing_first_name(),
                    'lastName'    => $order->get_billing_last_name(),
                    'address1'    => $order->get_billing_address_1(),
                    'address2'    => $order->get_billing_address_2(),
                    'city'        => $order->get_billing_city(),
                    'stateCode'   => $order->get_billing_state(),
                    'postalCode'  => $order->get_billing_postcode(),
                    'countryCode' => $order->get_billing_country(),
                ],
                'phoneNumber'            => [
                    'countryCode' => substr($countryCode, 1),
                    'subscriber'  => $order->get_billing_phone(),
                ],
                'merchantDefinedData'    => [
                    'pluginName'                => 'woocommerce',
                    'pluginVersion'             => WC_GATEWAY_NGENIUS_VERSION,
                    'merchantCustomOrderFields' => $this->getCustomOrderFields($order)
                ]
            ],
            'method' => 'POST',
            'uri'    => $this->config->get_order_request_url(),
        ];
    }
}
