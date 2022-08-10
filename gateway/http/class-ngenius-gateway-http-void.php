<?php

/**
 * NgeniusGatewayHttpVoid class.
 */
class NgeniusGatewayHttpVoid extends NgeniusGatewayHttpAbstract
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
     * @return array|null
     */
    protected function post_process($response)
    {

        if (isset($response->errors)) {
            return null;
        } else {
            $state        = isset($response->state) ? $response->state : '';
            $order_status = ('REVERSED' === $state) ? substr($this->order_status[7]['status'], 3) : '';

            return [
                'result' => [
                    'state'        => $state,
                    'order_status' => $order_status,
                ],
            ];
        }
    }

}
