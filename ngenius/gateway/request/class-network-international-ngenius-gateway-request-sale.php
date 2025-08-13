<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once 'class-network-international-ngenius-gateway-request-abstract.php';

use Ngenius\NgeniusCommon\Formatter\ValueFormatter;

/**
 * Class NetworkInternationalNgeniusGatewayRequestSale
 */
class NetworkInternationalNgeniusGatewayRequestSale extends NetworkInternationalNgeniusGatewayRequestAbstract
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
        $currencyCode = $order->get_currency();
        $amount       = ValueFormatter::floatToIntRepresentation($currencyCode, $order->get_total());
        $countryCode  = WC()->countries->get_country_calling_code($order->get_billing_country());
        $debugMode    = $this->config->get_debug_mode();

        if ($debugMode === 'yes') {
            $cancelUrl = wc_get_checkout_url();
        } else {
            $cancelUrl = str_replace('&amp;', '&', $order->get_cancel_order_url());
        }

        return [
            'data'   => [
                'action'                 => 'SALE',
                'amount'                 => [
                    'currencyCode' => $currencyCode,
                    'value'        => $amount,
                ],
                'merchantAttributes'     => [
                    'redirectUrl'          => add_query_arg('wc-api', 'ngeniusonline', home_url('/')),
                    'skipConfirmationPage' => true,
                    'cancelUrl'            => $cancelUrl,
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
                    'pluginVersion'             => NETWORK_INTERNATIONAL_NGENIUS_VERSION,
                    'merchantCustomOrderFields' => $this->getCustomOrderFields($order)
                ]
            ],
            'method' => 'POST',
            'uri'    => $this->config->get_order_request_url(),
        ];
    }
}
