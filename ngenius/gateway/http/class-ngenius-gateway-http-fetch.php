<?php

/**
 * NgeniusGatewayHttpFetch class.
 */
class NgeniusGatewayHttpFetch extends NgeniusGatewayHttpAbstract
{


    /**
     * Processing of API request body
     *
     * @param array $data
     *
     * @return string
     */
    protected function pre_process(array $data)
    {
        return json_encode($data);
    }

    /**
     * Processing of API response
     *
     * @param array $response_enc
     *
     * @return array
     */
    protected function post_process($response_enc)
    {
        return $response_enc;
    }

}
