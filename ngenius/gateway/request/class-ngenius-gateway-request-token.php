<?php

if ( ! defined('ABSPATH')) {
    exit;
}

/**
 * Class NgeniusGatewayRequestToken
 */
class NgeniusGatewayRequestToken
{


    /**
     * @var Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param NgeniusGatewayConfig $config
     */
    public function __construct(NgeniusGatewayConfig $config)
    {
        $this->config = $config;
    }

    /**
     * Builds access token request
     *
     * @param array $order
     * @param float $amount
     *
     * @return string|null
     */
    public function get_access_token()
    {
        $url = $this->config->get_token_request_url();
        $key = $this->config->get_api_key();

        $headers = array(
            "Authorization: Basic $key",
            "Content-Type:  application/vnd.ni-identity.v1+json",
            "Content-Length: 0"
        );

        $ch = curl_init();

        $curlConfig = array(
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        );
        curl_setopt_array($ch, $curlConfig);
        $response = curl_exec($ch);
        $result   = json_decode($response);

        if (isset($result->access_token)) {
            return $result->access_token;
        } else {
            $error_message = $result->errors[0]->message;
            return new WP_Error('error', $error_message);
        }
    }

}
