<?php

/**
 * NgeniusGatewayHttpSale class.
 */
class NgeniusGatewayHttpSale extends NgeniusGatewayHttpAbstract
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
}
