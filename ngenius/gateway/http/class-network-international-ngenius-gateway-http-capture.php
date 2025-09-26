<?php

use Ngenius\NgeniusCommon\Formatter\ValueFormatter;

add_action('plugins_loaded', function() {
    if (!class_exists('WooCommerce')) {
        return;
    }

    if (!defined('ABSPATH')) {
        exit;
    }
});

/**
 * NetworkInternationalNgeniusGatewayHttpCapture class.
 */

class NetworkInternationalNgeniusGatewayHttpCapture extends NetworkInternationalNgeniusGatewayHttpAbstract
{
    public function get_total_amount($response): int
    {
        $amount     = 0;
        $embedded   = self::NGENIUS_EMBEDED;
        $cnpCapture = self::NGENIUS_CAPTURE;
        foreach ($response->$embedded->$cnpCapture as $capture) {
            if (isset($capture->state) && ('SUCCESS' === $capture->state) && isset($capture->amount->value)) {
                $amount += $capture->amount->value;
            }
        }

        return $amount;
    }

    public function get_captured_amount($lastTransaction): float|null
    {
        if (isset($lastTransaction->state)
            && ('SUCCESS' === $lastTransaction->state)
            && isset($lastTransaction->amount->value)) {
            return $lastTransaction->amount->value;
        }

        return null;
    }

    public function get_transaction_id($lastTransaction): bool|null|string
    {
        if (isset($lastTransaction->_links->self->href)) {
            $transactionArr = explode('/', $lastTransaction->_links->self->href);

            return end($transactionArr);
        }

        return null;
    }

    public function get_order_status($state): string
    {
        if ('PARTIALLY_CAPTURED' === $state) {
            $orderStatus = substr($this->orderStatus[1]['status'], 3);
        } else {
            $orderStatus = substr($this->orderStatus[5]['status'], 3);
        }

        return $orderStatus;
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
        return wp_json_encode($data);
    }

    /**
     * Processing of API response
     *
     * @param array $response_enc
     *
     * @return array|null
     */
    protected function post_process(stdClass $response): ?array
    {
        if (isset($response->errors)) {
            return null;
        } else {
            $amount          = 0;
            $lastTransaction = array();
            $embedded        = self::NGENIUS_EMBEDED;
            $capture         = self::NGENIUS_CAPTURE;
            if (isset($response->$embedded->$capture)) {
                $lastTransaction = end($response->$embedded->$capture);
                $amount          = $this->get_total_amount($response);
            }
            $capturedAmt = $this->get_captured_amount($lastTransaction);
            $capturedAmt = ValueFormatter::intToFloatRepresentation($response->amount->currencyCode, $capturedAmt);

            $transactionId = $this->get_transaction_id($lastTransaction);

            $amount = ($amount > 0) ? ValueFormatter::intToFloatRepresentation(
                $response->amount->currencyCode,
                $amount
            ) : 0;
            $state  = $response->state ?? '';

            $orderStatus = $this->get_order_status($state);

            return [
                'result' => [
                    'total_captured' => $amount,
                    'captured_amt'   => $capturedAmt,
                    'state'          => $state,
                    'orderStatus'    => $orderStatus,
                    'transaction_id' => $transactionId,
                ],
            ];
        }
    }
}
