<?php

if (! defined('ABSPATH')) {
    exit;
}

$f = dirname(__DIR__, 2);
require_once "$f/vendor/autoload.php";

use \Ngenius\NgeniusCommon\NgeniusHTTPCommon;
use \Ngenius\NgeniusCommon\NgeniusHTTPTransfer;


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
     * @return WP_Error|string|null
     */
    public function get_access_token(): WP_Error|string|null
    {
        require_once(dirname(__DIR__) . '/http/class-ngenius-gateway-http-fetch.php');

        $url = $this->config->get_token_request_url();
        $key = $this->config->get_api_key();

        $httpTokenTransfer = new NgeniusHTTPTransfer($url, $this->config->get_http_version(), "POST");
        $httpTokenTransfer->setTokenHeaders($key);

        $result = json_decode(NgeniusHTTPCommon::placeRequest($httpTokenTransfer));

        if (isset($result->access_token)) {
            return $result->access_token;
        } else {
            $error_message = $result->errors[0]->message;
            return new WP_Error('error', $error_message);
        }
    }
}
