<?php

if ( ! defined('ABSPATH')) {
    exit;
}

/**
 * NgeniusGatewayHttpTransfer class.
 */
class NgeniusGatewayHttpTransfer
{

    /**
     * @var array
     */
    private $headers = array();

    /**
     * @var array
     */
    private $body = array();

    /**
     * @var api curl uri
     */
    private $uri = '';

    /**
     * @var method
     */
    private $method;

    /**
     * Builds gateway transfer object
     *
     * @param array $request
     *
     * @return TransferInterface
     */
    public function create(array $request)
    {
        if ($request['token'] && is_array($request['request'])) {
            return $this->set_body($request['request']['data'])
                        ->set_method($request['request']['method'])
                        ->set_headers(
                            array(
                                'Authorization' => 'Bearer ' . $request['token'],
                                'Content-Type'  => 'application/vnd.ni-payment.v2+json',
                                'Accept'        => 'application/vnd.ni-payment.v2+json',
                            )
                        )
                        ->set_uri($request['request']['uri']);
        }
    }

    /**
     * Set header for transfer object
     *
     * @param array $headers
     *
     * @return Transferfactory
     */
    public function set_headers(array $headers)
    {
        $this->headers = $headers;

        return $this;
    }

    /**
     * Set body for transfer object
     *
     * @param array $body
     *
     * @return Transferfactory
     */
    public function set_body($body)
    {
        $this->body = $body;

        return $this;
    }

    /**
     * Set method for transfer object
     *
     * @param array $method
     *
     * @return Transferfactory
     */
    public function set_method($method)
    {
        $this->method = $method;

        return $this;
    }

    /**
     * Set uri for transfer object
     *
     * @param array $uri
     *
     * @return Transferfactory
     */
    public function set_uri($uri)
    {
        $this->uri = $uri;

        return $this;
    }

    /**
     * Retrieve method from transfer object
     *
     * @return string
     */
    public function get_method()
    {
        return (string)$this->method;
    }

    /**
     * Retrieve header from transfer object
     *
     * @return Transferfactory
     */
    public function get_headers()
    {
        return $this->headers;
    }

    /**
     * Retrieve body from transfer object
     *
     * @return Transferfactory
     */
    public function get_body()
    {
        return $this->body;
    }

    /**
     * Retrieve uri from transfer object
     *
     * @return string
     */
    public function get_uri()
    {
        return (string)$this->uri;
    }
}
