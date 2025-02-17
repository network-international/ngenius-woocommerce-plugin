<?php

namespace Ngenius\NgeniusCommon;

class NgeniusOrderStatuses
{
    static public function orderStatuses($label = 'N-Genius', $key = 'ng'): array
    {
        return [
            [
                'status' => "wc-$key-pending",
                'label'  => "$label Pending",
            ],
            [
                'status' => "wc-$key-processing",
                'label'  => "$label Processing",
            ],
            [
                'status' => "wc-$key-failed",
                'label'  => "$label Failed",
            ],
            [
                'status' => "wc-$key-complete",
                'label'  => "$label Complete",
            ],
            [
                'status' => "wc-$key-authorised",
                'label'  => "$label Authorised",
            ],
            [
                'status' => "wc-$key-captured",
                'label'  => "$label Captured",
            ],
            [
                'status' => "wc-$key-part-refunded",
                'label'  => "$label Partially Refunded",
            ],
            [
                'status' => "wc-$key-auth-reversed",
                'label'  => "$label Auth Reversed",
            ],
            [
                'status' => "wc-$key-refunded",
                'label'  => "$label Fully Refunded",
            ],
        ];
    }

    static public function magentoOrderStatuses($label = 'N-Genius', $key = 'ngenius'): array
    {
        return [
            [
                'status' => "{$key}_pending",
                'label'  => "$label Pending",
            ],
            [
                'status' => "{$key}_processing",
                'label'  => "$label Processing",
            ],
            [
                'status' => "{$key}_failed",
                'label'  => "$label Failed",
            ],
            [
                'status' => "{$key}_complete",
                'label'  => "$label Complete",
            ],
            [
                'status' => "{$key}_authorised",
                'label'  => "$label Authorised",
            ],
            [
                'status' => "{$key}_fully_captured",
                'label'  => "$label Fully Captured",
            ],
            [
                'status' => "{$key}_partially_captured",
                'label'  => "$label Partially Captured",
            ],
            [
                'status' => "{$key}_fully_refunded",
                'label'  => "$label Fully Refunded",
            ],
            [
                'status' => "{$key}_partially_refunded",
                'label'  => "$label Partially Refunded",
            ],
            [
                'status' => "{$key}_auth_reversed",
                'label'  => "$label Auth Reversed",
            ],
            [
                'status' => "{$key}_declined",
                'label'  => "$label Declined",
            ],
        ];
    }
}
