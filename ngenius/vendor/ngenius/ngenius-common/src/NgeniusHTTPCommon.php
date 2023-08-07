<?php

namespace Ngenius\NgeniusCommon;

class NgeniusHTTPCommon
{

    /**
     * @param NgeniusHTTPTransfer $ngeniusHTTPTransfer
     * @return string|bool
     */
    public static function placeRequest(NgeniusHTTPTransfer $ngeniusHTTPTransfer): string|bool
    {
        $httpVersion = match ($ngeniusHTTPTransfer->getHttpVersion()) {
            "CURL_HTTP_VERSION_NONE" => CURL_HTTP_VERSION_NONE,
            "CURL_HTTP_VERSION_1_0" => CURL_HTTP_VERSION_1_0,
            "CURL_HTTP_VERSION_1_1" => CURL_HTTP_VERSION_1_1,
            "CURL_HTTP_VERSION_2_0" => CURL_HTTP_VERSION_2_0,
            "CURL_HTTP_VERSION_2TLS" => CURL_HTTP_VERSION_2TLS,
            "CURL_HTTP_VERSION_2_PRIOR_KNOWLEDGE" => CURL_HTTP_VERSION_2_PRIOR_KNOWLEDGE,
            default => CURL_HTTP_VERSION_NONE,
        };

        $ch = curl_init();
        $curlConfig = array(
            CURLOPT_URL => $ngeniusHTTPTransfer->getUrl(),
            CURLOPT_HTTPHEADER => $ngeniusHTTPTransfer->getHeaders(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTP_VERSION => $httpVersion,
            CURLOPT_CONNECTTIMEOUT => 0,
            CURLOPT_TIMEOUT => 400,
        );

        $data = $ngeniusHTTPTransfer->getData();
        $method = $ngeniusHTTPTransfer->getMethod();

        if (!empty($data)) {
            $curlConfig[CURLOPT_POSTFIELDS] = json_encode($data);
        }

        if($method === "POST") {
            $curlConfig[CURLOPT_POST] = true;
        }
        elseif ($method === "PUT") {
            $curlConfig[CURLOPT_PUT] = true;
        }

        curl_setopt_array($ch, $curlConfig);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            return curl_error($ch);
        }

        return $response;
    }
}
