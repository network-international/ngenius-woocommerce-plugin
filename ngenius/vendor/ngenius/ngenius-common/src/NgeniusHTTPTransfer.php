<?php

namespace Ngenius\NgeniusCommon;

class NgeniusHTTPTransfer
{
    private string $url;
    private string $httpVersion;
    private array $headers;
    private string $method;
    private array $data;

    /**
     * @param string $url
     * @param string $httpVersion
     * @param string $method
     * @param array $data
     * @param array $headers
     */
    public function __construct(
        string $url,
        string $httpVersion = "",
        string $method = "",
        array $data = [],
        array $headers = []
    ) {
        $this->url         = $url;
        $this->httpVersion = $httpVersion;
        $this->headers     = $headers;
        $this->method      = $method;
        $this->data        = $data;
    }


    /**
     * @param $key
     *
     * @return void
     */
    public function setTokenHeaders($key): void
    {
        $this->setHeaders([
                              "Authorization: Basic $key",
                              "Content-Type:  application/vnd.ni-identity.v1+json",
                              "Content-Length: 0"
                          ]);
    }

    /**
     * @param $token
     *
     * @return void
     */
    public function setPaymentHeaders($token): void
    {
        $this->setHeaders([
                              "Authorization: Bearer $token",
                              "Content-type: application/vnd.ni-payment.v2+json",
                              "Accept: application/vnd.ni-payment.v2+json"
                          ]);
    }

    /**
     * @param $token
     *
     * @return void
     */
    public function setInvoiceHeaders($token): void
    {
        $this->setHeaders([
                              "Authorization: Bearer $token",
                              "Content-type: application/vnd.ni-invoice.v1+json",
                          ]);
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @param string $url
     */
    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    /**
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @param array $headers
     */
    public function setHeaders(array $headers): void
    {
        $this->headers = $headers;
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @param string $method
     */
    public function setMethod(string $method): void
    {
        $this->method = $method;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data ?? [];
    }

    /**
     * @param array $data
     */
    public function setData(array $data): void
    {
        $this->data = $data;
    }

    /**
     * @return string
     */
    public function getHttpVersion(): string
    {
        return $this->httpVersion ?? "";
    }

    /**
     * @param string $httpVersion
     */
    public function setHttpVersion(string $httpVersion): void
    {
        $this->httpVersion = $httpVersion;
    }

    public function build(array $requestData): void
    {
        $this->url    = $requestData["uri"];
        $this->method = $requestData["method"];
        $this->data   = $requestData["data"] ?? [];
    }
}
