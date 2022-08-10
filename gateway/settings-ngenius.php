<?php

/**
 * Settings for N-Genius Gateway.
 */
defined('ABSPATH') || exit;

class SettingsNgenius extends WC_Settings_API{

    public static $currencies = ['AED', 'ALL', 'AOA', 'AUD', 'BHD', 'BWP', 'CAD', 'DKK', 'EGP', 'EUR', 'GBP', 'GHS', 'GNF', 'HKD', 'INR', 'JOD', 'JPY', 'KES', 'KWD', 'LKR', 'MAD', 'MWK', 'MYR', 'NAD', 'NGN', 'OMR', 'PHP', 'PKR', 'QAR', 'SAR', 'SEK', 'SGD', 'THB', 'TRY', 'TZS', 'UGX', 'USD', 'XAF', 'XOF', 'ZAR', 'ZMW', 'ZWL'];

    public function overrideFormFieldsVariable(){
        return array(
            'enabled'        => array(
                'title'   => __('Enable/Disable', 'woocommerce'),
                'label'   => __('Enable N-Genius Payment Gateway', 'woocommerce'),
                'type'    => 'checkbox',
                'default' => 'no',
            ),
            'title'          => array(
                'title'       => __('Title', 'woocommerce'),
                'type'        => 'text',
                'description' => __('The title which the user sees during checkout.', 'woocommerce'),
                'default'     => __('N-Genius Online', 'woocommerce'),
            ),
            'description'    => array(
                'title'       => __('Description', 'woocommerce'),
                'type'        => 'textarea',
                'css'         => 'width: 400px;height:60px;',
                'description' => __('The description which the user sees during checkout.', 'woocommerce'),
                'default'     => __('You will be redirected to payment gateway.', 'woocommerce'),
            ),
            'environment'    => array(
                'title'   => __('Environment', 'woocommerce'),
                'type'    => 'select',
                'class'   => 'wc-enhanced-select',
                'options' => array(
                    'uat'  => __('Sandbox', 'woocommerce'),
                    'live' => __('Live', 'woocommerce'),
                ),
                'default' => 'uat',
            ),
            'uat_api_url'    => array(
                'title'   => __('Sandbox API URL', 'woocommerce'),
                'type'    => 'text',
                'default' => 'https://api-gateway.sandbox.ngenius-payments.com',
            ),
            'live_api_url'    => array(
                'title'   => __('Live API URL', 'woocommerce'),
                'type'    => 'text',
                'default' => 'https://api-gateway.ngenius-payments.com',
            ),
            'payment_action' => array(
                'title'   => __('Payment Action', 'woocommerce'),
                'type'    => 'select',
                'class'   => 'wc-enhanced-select',
                'options' => array(
                    'authorize' => __('Authorize', 'woocommerce'),
                    'sale'      => __('Sale', 'woocommerce'),
                    'purchase' => __('Purchase', 'woocommerce'),
                ),
                'default' => 'sale',
            ),
            'order_status'   => array(
                'title'   => __('Status of new order', 'woocommerce'),
                'type'    => 'select',
                'class'   => 'wc-enhanced-select',
                'options' => array(
                    'ngenius_pending' => __('N-Genius Pending', 'woocommerce'),
                ),
                'default' => 'ngenius_pending',
            ),
            'outlet_ref'     => array(
                'title' => __('Outlet Reference ID', 'woocommerce'),
                'type'  => 'text',
            ),
            'outlet_override_currency'           => [
                'title'       => __('Outlet 2 Currencies (Optional)'),
                'type'        => 'multiselect',
                'class'       => 'wc-enhanced-select',
                'options'     => self::$currencies,
                'label'       => __('Outlet 2 Currencies (Optional)'),
                'description' => __('If these currencies are selected, Outlet 2 Reference ID will be used.', 'woocommerce'),
            ],
            'outlet_override_ref'            => array(
                'title' => __('Outlet 2 Reference ID (Optional)', 'woocommerce'),
                'type'  => 'text',
            ),
            'api_key'        => array(
                'title' => __('API Key', 'woocommerce'),
                'type'  => 'textarea',
                'css'   => 'width: 400px;height:50px;',
            ),
            'debug'          => array(
                'title'       => __('Debug Log', 'woocommerce'),
                'type'        => 'checkbox',
                'label'       => __('Enable logging', 'woocommerce'),
                'description' => sprintf(
                    __('Log file will be %s', 'woocommerce'),
                    '<code>' . WC_Log_Handler_File::get_log_file_path('ngenius') . '</code>'
                ),
                'default'     => 'yes',
            ),
        );
    }
}
