<?php

namespace Ngenius\NgeniusCommon\Processor;

use stdClass;

class RefundProcessor
{
    public static function extractUrl(stdClass $payment)
    {
        $cnpCapture = "cnp:capture";
        $cnpRefund  = 'cnp:refund';
        $cnpCancel  = 'cnp:cancel';

        $refund_url = "";
        if ($payment->state === "PURCHASED"
            || $payment->state === "PARTIALLY_REFUNDED") {
            if (isset($payment->_links->$cnpCancel->href)) {
                $refund_url = $payment->_links->$cnpCancel->href;
            } elseif (isset($payment->_links->$cnpRefund->href)) {
                $refund_url = $payment->_links->$cnpRefund->href;
            } elseif (isset($payment->_embedded->{$cnpCapture}[0]->_links->$cnpRefund->href)) {
                $refund_url = $payment->_embedded->{$cnpCapture}[0]->_links->$cnpRefund->href;
            }
        } elseif ($payment->state === "CAPTURED") {
            if (isset($payment->_embedded->{$cnpCapture}[0]->_links->$cnpRefund->href)) {
                $refund_url = $payment->_embedded->{$cnpCapture}[0]->_links->$cnpRefund->href;
            } elseif (isset($payment->_embedded->{$cnpCapture}[0]->_links->self->href)) {
                $refund_url = $payment->_embedded->{$cnpCapture}[0]->_links->self->href . '/refund';
            }
        } else {
            if (isset($payment->_embedded->{$cnpCapture}[0]->_links->$cnpRefund->href)) {
                $refund_url = $payment->_embedded->{$cnpCapture}[0]->_links->$cnpRefund->href;
            }
        }

        return $refund_url;
    }
}
