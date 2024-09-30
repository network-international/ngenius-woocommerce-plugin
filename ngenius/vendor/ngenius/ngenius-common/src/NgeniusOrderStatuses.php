<?php

namespace Ngenius\NgeniusCommon;

class NgeniusOrderStatuses
{
    static public function orderStatuses(): array
    {
        return [
            [
                'status' => 'wc-ng-pending',
                'label'  => 'N-Genius Pending',
            ],
            [
                'status' => 'wc-ng-processing',
                'label'  => 'N-Genius Processing',
            ],
            [
                'status' => 'wc-ng-failed',
                'label'  => 'N-Genius Failed',
            ],
            [
                'status' => 'wc-ng-complete',
                'label'  => 'N-Genius Complete',
            ],
            [
                'status' => 'wc-ng-authorised',
                'label'  => 'N-Genius Authorised',
            ],
            [
                'status' => 'wc-ng-captured',
                'label'  => 'N-Genius Captured',
            ],
            [
                'status' => 'wc-ng-part-refunded',
                'label'  => 'N-Genius Partially Refunded',
            ],
            [
                'status' => 'wc-ng-auth-reversed',
                'label'  => 'N-Genius Auth Reversed',
            ],
            [
                'status' => 'wc-ng-refunded',
                'label'  => 'N-Genius Fully Refunded',
            ],
        ];
    }

    static public function magentoOrderStatuses(): array
    {
        return [
            [
                'status' => 'ngenius_pending',
                'label'  => 'N-Genius Pending',
            ],
            [
                'status' => 'ngenius_processing',
                'label'  => 'N-Genius Processing',
            ],
            [
                'status' => 'ngenius_failed',
                'label'  => 'N-Genius Failed',
            ],
            [
                'status' => 'ngenius_complete',
                'label'  => 'N-Genius Complete',
            ],
            [
                'status' => 'ngenius_authorised',
                'label'  => 'N-Genius Authorised',
            ],
            [
                'status' => 'ngenius_fully_captured',
                'label'  => 'N-Genius Fully Captured',
            ],
            [
                'status' => 'ngenius_partially_captured',
                'label'  => 'N-Genius Partially Captured',
            ],
            [
                'status' => 'ngenius_fully_refunded',
                'label'  => 'N-Genius Fully Refunded',
            ],
            [
                'status' => 'ngenius_partially_refunded',
                'label'  => 'N-Genius Partially Refunded',
            ],
            [
                'status' => 'ngenius_auth_reversed',
                'label'  => 'N-Genius Auth Reversed',
            ],
            [
                'status' => 'ngenius_declined',
                'label'  => 'N-Genius Declined',
            ],
        ];
    }
}
