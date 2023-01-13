<?php

/**
 * Order statuses for N-Genius Gateway.
 */
defined('ABSPATH') || exit;

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
];
