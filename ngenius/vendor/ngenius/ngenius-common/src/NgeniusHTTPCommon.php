<?php

namespace Ngenius\NgeniusCommon;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class NgeniusHTTPCommon
{
    /**
     * @param NgeniusHTTPTransfer $ngeniusHTTPTransfer
     *
     * @return string|bool
     */
    public static function placeRequest(NgeniusHTTPTransfer $ngeniusHTTPTransfer): string|bool
    {
        $client       = new Client();
        $method       = $ngeniusHTTPTransfer->getMethod();
        $url          = $ngeniusHTTPTransfer->getUrl();
        $headersArray = $ngeniusHTTPTransfer->getHeaders();
        $data         = $ngeniusHTTPTransfer->getData();

        $httpVersion = match ($ngeniusHTTPTransfer->getHttpVersion()) {
            "CURL_HTTP_VERSION_1_0" => '1.0',
            "CURL_HTTP_VERSION_2_0", "CURL_HTTP_VERSION_2TLS", "CURL_HTTP_VERSION_2_PRIOR_KNOWLEDGE" => '2.0',
            default => '1.1',
        };

        // Convert the headers array to an associative array for Guzzle
        $headers = [];
        foreach ($headersArray as $header) {
            [$key, $value] = explode(':', $header, 2);
            $headers[trim($key)] = trim($value);
        }

        // Prepare options for the Guzzle request
        $options = [
            'headers'     => $headers,
            'http_errors' => false, // To handle non-2xx responses gracefully
            'version'     => $httpVersion,
        ];

        // Add the payload if the request includes data
        if (!empty($data)) {
            $options['json'] = $data;
        }

        try {
            $response = $client->request($method, $url, $options);

            // Return the response body as a string
            return $response->getBody()->getContents();
        } catch (RequestException $e) {
            // Handle exceptions and return error message
            return $e->getMessage();
        }
    }
}
