<?php

namespace Ngenius\NgeniusCommon\Processor;

class TransactionProcessor
{
    private array $response;
    private const EMBEDDED_LITERAL    = '_embedded';
    private const CAPTURE_LITERAL     = 'cnp:capture';
    private const REFUND_LITERAL      = 'cnp:refund';
    private const NGENIUS_CUP_RESULTS = 'cnp:china_union_pay_results';

    public function __construct(array $response)
    {
        $this->response = $response;
    }

    /**
     * @return array
     */
    public function getResponse(): array
    {
        return $this->response;
    }

    /**
     * @param array $response
     *
     * @return void
     */
    public function setResponse(array $response): void
    {
        $this->response = $response;
    }

    /**
     * Gets the total refunded value
     *
     * @return float
     */
    public function getTotalRefunded(): float
    {
        $refunded_amt = 0.00;

        if (isset($this->response[self::EMBEDDED_LITERAL][self::REFUND_LITERAL]) && is_array(
                $this->response[self::EMBEDDED_LITERAL][self::REFUND_LITERAL]
            )) {
            foreach ($this->response[self::EMBEDDED_LITERAL][self::REFUND_LITERAL] as $refund) {
                $refunded_amt += $this->getTransactionAmount($refund);
            }
        } elseif (isset($this->response['state']) && $this->response['state'] === 'REVERSED') {
            $refunded_amt = $this->response['amount']['value'];
        }

        return $refunded_amt;
    }

    /**
     * Gets the total captured value
     *
     * @return float
     */
    public function getTotalCaptured(): float
    {
        $captured_amt = 0.00;
        if (isset($this->response[self::EMBEDDED_LITERAL][self::CAPTURE_LITERAL]) && is_array(
                $this->response[self::EMBEDDED_LITERAL][self::CAPTURE_LITERAL]
            )) {
            foreach ($this->response[self::EMBEDDED_LITERAL][self::CAPTURE_LITERAL] as $capture) {
                $captured_amt += $this->getTransactionAmount($capture);
            }
        }

        return $captured_amt;
    }

    /**
     * Gets the last refund transaction
     *
     * @return array
     */
    public function getLastRefundTransaction(): array
    {
        return end($this->response[self::EMBEDDED_LITERAL][self::REFUND_LITERAL]);
    }

    /**
     * Gets the last capture transaction
     *
     * @return array
     */
    public function getLastCaptureTransaction(): array
    {
        return end($this->response[self::EMBEDDED_LITERAL][self::CAPTURE_LITERAL]);
    }

    /**
     * Extracts amount value
     *
     * @param array $transaction
     *
     * @return float
     */
    public function getTransactionAmount(array $transaction): float
    {
        $amount = 0.00;
        if (isset($transaction['state'])
            && ($transaction['state'] == 'SUCCESS'
                || (isset($transaction['_links'][self::NGENIUS_CUP_RESULTS])
                    && $transaction['state'] === 'REQUESTED')) && isset($transaction['amount']['value'])
        ) {
            $amount = (float)$transaction['amount']['value'];
        }

        return $amount;
    }

    /**
     * Extracts transaction ID from transaction
     *
     * @param $transaction
     *
     * @return string
     */
    public function getTransactionID($transaction): string
    {
        $transactionId = '';
        if (isset($transaction['_links']['self']['href'])) {
            $transactionArr = explode('/', $transaction['_links']['self']['href']);
            $transactionId  = end($transactionArr);
        } elseif (isset($transaction['_links'][self::NGENIUS_CUP_RESULTS])) {
            $transactionArr = explode('/', $transaction['_links'][self::NGENIUS_CUP_RESULTS]['href']);
            $transactionId  = end($transactionArr);
        }

        return $transactionId;
    }
}
