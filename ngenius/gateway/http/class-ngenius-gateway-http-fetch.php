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
    protected function pre_process(array $data): string
    {
        return json_encode($data);
    }

    /**
     * Processing of API response
     *
     * @param stdClass $response
     *
     * @return stdClass
     */
    protected function post_process(stdClass $response): stdClass
    {
        return $response;
    }
}
