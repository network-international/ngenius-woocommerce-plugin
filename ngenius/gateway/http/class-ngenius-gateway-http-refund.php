<?php

/**
 * NgeniusGatewayHttpRefund class.
 */
class NgeniusGatewayHttpRefund extends NgeniusGatewayHttpAbstract
{
    public function get_refunded_amount($response): array
    {
        $refunded_amt    = 0;
        $data            = array();
        $transaction_arr = array();
        $embedded         = self::NGENIUS_EMBEDED;
        $refund_stmt     = self::NGENIUS_REFUND;
        $cmp_refund      = 'cnp:refund';
        if (isset($response->$embedded->$refund_stmt)) {
            $transaction_arr = end($response->$embedded->$cmp_refund);
            foreach ($response->$embedded->$refund_stmt as $refund) {
                if (isset($refund->state) && ('SUCCESS' === $refund->state) && isset($refund->amount->value)) {
                    $refunded_amt += $refund->amount->value;
                }
            }
        }

        $last_refunded_amt = 0;
        if (isset($transaction_arr->state)
            && ('SUCCESS' === $transaction_arr->state || 'REQUESTED' === $transaction_arr->state)
            && isset($transaction_arr->amount->value)) {
            $last_refunded_amt = $transaction_arr->amount->value / 100;
        }

        $data['refunded_amount']   = $refunded_amt;
        $data['transaction_arr']   = $transaction_arr;
        $data['last_refunded_amt'] = $last_refunded_amt;

        return $data;
    }

    public function get_transaction_id($transaction_arr)
    {
        $cupResults = self::NGENIUS_CUP_RESULTS;

        if (isset($transaction_arr->_links->self->href)) {
            $transaction_arr = explode('/', $transaction_arr->_links->self->href);

            return end($transaction_arr);
        } elseif (isset($transaction_arr->_links->$cupResults)) {
            $transaction_arr = explode('/', $transaction_arr->_links->$cupResults->href);

            return array_slice($transaction_arr, -2, 1)[0];
        }
        return "";
    }

    public function get_order_status($captured_amt, $refunded_amt)
    {
        if ($captured_amt === $refunded_amt) {
            $order_status = 'refunded';
        } else {
            $order_status = substr($this->orderStatus[6]['status'], 3);
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
    protected function pre_process(array $data): string
    {
        return json_encode($data);
    }

    /**
     * Processing of API response
     *
     * @param stdClass $response
     * @return array
     */
    protected function post_process(stdClass $response): array
    {
        if (isset($response->errors)) {
            return [
                'result' => []
                ];
        } else {
            $refunded_data     = $this->get_refunded_amount($response);
            $transaction_arr   = $refunded_data['transaction_arr'];
            $refunded_amt      = $refunded_data['refunded_amount'] / 100;
            $last_refunded_amt = $refunded_data['last_refunded_amt'];
            $captured_amt = $response->amount->value / 100;

            $transaction_id = $this->get_transaction_id($transaction_arr);

            $state = $response->state ?? '';

            $order_status = $this->get_order_status($captured_amt, $refunded_amt);

            return [
                'result' => [
                    'captured_amt'   => $refunded_amt,
                    'refunded_amt'   => $last_refunded_amt,
                    'state'          => $state,
                    'orderStatus'   => $order_status,
                    'transaction_id' => $transaction_id,
                    'total_refunded_amount' => $refunded_amt,
                ],
            ];
        }
    }
}
