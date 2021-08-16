<?php

/**
 * Settings for n-genius Gateway.
 */
defined( 'ABSPATH' ) || exit;

return array(
	'enabled'        => array(
		'title'   => __( 'Enable/Disable', 'woocommerce' ),
		'label'   => __( 'Enable n-genius Payment Gateway', 'woocommerce' ),
		'type'    => 'checkbox',
		'default' => 'no',
	),
	'title'          => array(
		'title'       => __( 'Title', 'woocommerce' ),
		'type'        => 'text',
		'description' => __( 'The title which the user sees during checkout.', 'woocommerce' ),
		'default'     => __( 'n-genius Payment Gateway', 'woocommerce' ),
	),
	'description'    => array(
		'title'       => __( 'Description', 'woocommerce' ),
		'type'        => 'textarea',
		'css'         => 'width: 400px;height:60px;',
		'description' => __( 'The description which the user sees during checkout.', 'woocommerce' ),
		'default'     => __( 'You will be redirected to payment gateway.', 'woocommerce' ),
	),
	'environment'    => array(
		'title'   => __( 'Environment', 'woocommerce' ),
		'type'    => 'select',
		'class'   => 'wc-enhanced-select',
		'options' => array(
			'uat'  => __( 'UAT', 'woocommerce' ),
			'live' => __( 'Live', 'woocommerce' ),
		),
		'default' => 'uat',
	),
	'tenant'         => array(
		'title'   => __( 'Tenant', 'woocommerce' ),
		'type'    => 'select',
		'class'   => 'wc-enhanced-select',
		'options' => array(
			'networkinternational' => __( 'Network International', 'woocommerce' ),
		),
		'default' => 'ni',
	),
	'payment_action' => array(
		'title'   => __( 'Payment Action', 'woocommerce' ),
		'type'    => 'select',
		'class'   => 'wc-enhanced-select',
		'options' => array(
			'sale'      => __( 'Sale', 'woocommerce' ),
			'authorize' => __( 'Authorize', 'woocommerce' ),			
		),
		'default' => 'sale',
	),
	'order_status'   => array(
		'title'   => __( 'Status of new order', 'woocommerce' ),
		'type'    => 'select',
		'class'   => 'wc-enhanced-select',
		'options' => array(
			'ngenius_pending' => __( 'n-genius Pending', 'woocommerce' ),
		),
		'default' => 'ngenius_pending',
	),
	'outlet_ref'     => array(
		'title' => __( 'Outlet Reference ID', 'woocommerce' ),
		'type'  => 'text',
	),
	'api_key'        => array(
		'title' => __( 'API Key', 'woocommerce' ),
		'type'  => 'textarea',
		'css'   => 'width: 400px;height:50px;',
	),
	'debug'          => array(
		'title'       => __( 'Debug Log', 'woocommerce' ),
		'type'        => 'checkbox',
		'label'       => __( 'Enable logging', 'woocommerce' ),
		'description' => sprintf( __( 'Log file will be %s', 'woocommerce' ), '<code>' . WC_Log_Handler_File::get_log_file_path( 'ngenius' ) . '</code>' ),
		'default'     => 'no',
	),
);
