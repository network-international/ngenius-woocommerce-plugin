<?php

/**
 * Order statuses for n-genius Gateway.
 */
defined( 'ABSPATH' ) || exit;

return [
	[
		'status' => 'wc-ng-pending',
		'label'  => 'n-genius Pending',
	],
	[
		'status' => 'wc-ng-processing',
		'label'  => 'n-genius Processing',
	],
	[
		'status' => 'wc-ng-failed',
		'label'  => 'n-genius Failed',
	],
	[
		'status' => 'wc-ng-complete',
		'label'  => 'n-genius Complete',
	],
	[
		'status' => 'wc-ng-authorised',
		'label'  => 'n-genius Authorised',
	],
	[
		'status' => 'wc-ng-captured',
		'label'  => 'n-genius Captured',
	],
	[
		'status' => 'wc-ng-part-refunded',
		'label'  => 'n-genius Partially Refunded',
	],
	[
		'status' => 'wc-ng-auth-reversed',
		'label'  => 'n-genius Auth Reversed',
	],
];
