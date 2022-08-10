<?php

/**
 * NgeniusGatewayHttpCapture class.
 */
class NgeniusGatewayHttpCapture extends NgeniusGatewayHttpAbstract
{


    public function get_total_amount($response)
    {
        $amount = 0;
        $embedded = self::NGENIUS_EMBEDED;
        $cnpcapture = self::NGENIUS_CAPTURE;
        foreach ($response->$embedded->$cnpcapture as $capture) {
            if (isset($capture->state) && ('SUCCESS' === $capture->state) && isset($capture->amount->value)) {
                $amount += $capture->amount->value;
            }
        }

        return $amount;
    }

    public function get_captured_amount($last_transaction)
    {
        if (isset($last_transaction->state) && ('SUCCESS' === $last_transaction->state) && isset($last_transaction->amount->value)) {
            return $last_transaction->amount->value / 100;
        }
    }

    public function get_transaction_id($last_transaction)
    {
        if (isset($last_transaction->_links->self->href)) {
            $transaction_arr = explode('/', $last_transaction->_links->self->href);

            return end($transaction_arr);
        }
    }

    public function get_order_status($state)
    {
        if ('PARTIALLY_CAPTURED' === $state) {
            $order_status = substr($this->order_status[6]['status'], 3);
        } else {
            $order_status = substr($this->order_status[5]['status'], 3);
        }

        return $order_status;
    }

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
            $amount = 0;
            $last_transaction = array();
            $embeded = self::NGENIUS_EMBEDED;
            $capture = self::NGENIUS_CAPTURE;
            if (isset($response->$embeded->$capture)) {
                $last_transaction = end($response->$embeded->$capture);
                $amount           = $this->get_total_amount($response);
            }
            $captured_amt = $this->get_captured_amount($last_transaction);

            $transaction_id = $this->get_transaction_id($last_transaction);

            $amount = ($amount > 0) ? $amount / 100 : 0;
            $state  = isset($response->state) ? $response->state : '';

            $order_status = $this->get_order_status($state);

            return [
                'result' => [
                    'total_captured' => $amount,
                    'captured_amt'   => $captured_amt,
                    'state'          => $state,
                    'order_status'   => $order_status,
                    'transaction_id' => $transaction_id,
                ],
            ];
        }
    }

}
