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
    protected function pre_process(array $data): string
    {
        return wp_json_encode($data);
    }

    /**
     * Processing of API response
     *
     * @param stdClass $response
     *
     * @return array|null
     */
    protected function post_process(stdClass $response): ?array
    {
        if (isset($response->errors)) {
            return null;
        } else {
            $state        = $response->state ?? '';
            $order_status = ('REVERSED' === $state) ? substr($this->orderStatus[7]['status'], 3) : '';

            return [
                'result' => [
                    'state'       => $state,
                    'orderStatus' => $order_status,
                ],
            ];
        }
    }
}
