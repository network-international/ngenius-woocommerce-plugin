<?php
/*
 * Plugin Name: n-genius Payment Gateway
 * Plugin URI: https://www.network.ae
 * Description: Payment Gateway from Network International Payment Solutions
 * Author: Abzer
 * Author URI: https://www.abzer.com
 * Version: 1.0.1
 */
/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */

function register_ngenius_order_status() {
	$statuses = include 'gateway/order-status-ngenius.php';
	foreach ( $statuses as $status ) {
			$label = $status['label'];
		register_post_status(
			$status['status'],
			array(
				'label'                     => $status['label'],
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop(
					$status['label'] . ' <span class="count">(%s)</span>',
					$status['label'] . ' <span class="count">(%s)</span>'
				),
			)
		);
	}
}

add_action( 'init', 'register_ngenius_order_status' );

function ngenius_order_status( $order_statuses ) {
	$statuses = include 'gateway/order-status-ngenius.php';
	$id       = get_the_ID();
	if ( 'shop_order' === get_post_type() && $id && isset( $_GET['action'] ) && 'edit' === $_GET['action'] ) {
		$order = wc_get_order( $id );
		if ( $order ) {
			$current_status = $order->get_status();
			foreach ( $statuses as $status ) {
				if ( 'wc-' . $current_status === $status['status'] ) {
					$order_statuses[ $status['status'] ] = $status['label'];
				}
			}
		}
	} else {
		foreach ( $statuses as $status ) {
			$order_statuses[ $status['status'] ] = $status['label'];
		}
	}
	return $order_statuses;
}

add_filter( 'wc_order_statuses', 'ngenius_order_status' );

global $wpdb;
define( 'NGENIUS_TABLE', $wpdb->prefix . 'ngenius_networkinternational' );

function ngenius_table_install() {
	$sql = 'CREATE TABLE IF NOT EXISTS `' . NGENIUS_TABLE . "` (
             `nid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'n-genius Id',
             `order_id` varchar(55) NOT NULL COMMENT 'Order Id',
             `amount` decimal(12,4) UNSIGNED NOT NULL COMMENT 'Amount',
             `currency` varchar(3) NOT NULL COMMENT 'Currency',
             `reference` text NOT NULL COMMENT 'Reference',
             `action` varchar(20) NOT NULL COMMENT 'Action',
             `state` varchar(20) NOT NULL COMMENT 'State',
             `status` varchar(50) NOT NULL COMMENT 'Status',
             `payment_id` text NOT NULL COMMENT 'Payment Id',
             `captured_amt` decimal(12,4) UNSIGNED NOT NULL COMMENT 'Captured Amount',
             `capture_id` text NOT NULL COMMENT 'Capture Id',
             `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Created At',
             PRIMARY KEY (`nid`),
             UNIQUE KEY `NGENIUS_ONLINE_ORDER_ID` (`order_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='n-genius order table';";

	include_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}

register_activation_hook( __FILE__, 'ngenius_table_install' );

function plugin_action_links( $links ) {
	$plugin_links = array(
		'<a href="admin.php?page=wc-settings&tab=checkout&section=ngenius">' . esc_html__( 'Settings', 'woocommerce' ) . '</a>',
		'<a href="admin.php?page=ngenius-report">' . esc_html__( 'Report', 'woocommerce' ) . '</a>',
	);
	return array_merge( $plugin_links, $links );
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'plugin_action_links' );

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'ngenius_init_gateway_class' );

/* start menu page */

function register_ngenius_report_page() {
	$hook = add_submenu_page( 'woocommerce', 'n-genius Report', 'n-genius Report', 'manage_options', 'ngenius-report', 'ngenius_page_callback' );
	add_action( "load-$hook", 'add_options' );
}

function add_options() {
	global $ngenius_table;
	$option = 'per_page';
	$args   = array(
		'label'   => 'No. of records',
		'default' => 10,
		'option'  => 'records_per_page',
	);
	add_screen_option( $option, $args );
	include_once 'gateway/class-ngenius-gateway-report.php';
	$ngenius_table = new Ngenius_Gateway_Report();
}

add_action( 'admin_menu', 'register_ngenius_report_page' );

function ngenius_page_callback() {
	global $ngenius_table;
	echo '</pre><div class="wrap"><h2>n-genius Report</h2>';
	$ngenius_table->prepare_items();
	?>
	<form method="post">
		<input type="hidden" name="page" value="ngenius_list_table">
		<?php
		$ngenius_table->search_box( 'search', 'ngenius_search_id' );
		$ngenius_table->display();
		echo '</form></div>';
}

function ngenius_table_set_option( $status, $option, $value ) {
	return $value;
}

	add_filter( 'set-screen-option', 'ngenius_table_set_option', 10, 3 );

	/* end menu page */

function print_errors() {
	settings_errors( 'ngenius_error' );
}

function ngenius_init_gateway_class() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}
	include_once 'gateway/class-ngenius-gateway.php';
	Ngenius_Gateway::get_instance()->init_hooks();
}

function ngenius_add_gateway_class( $gateways ) {
	$gateways[] = 'ngenius_gateway';
	return $gateways;
}

	add_filter( 'woocommerce_payment_gateways', 'ngenius_add_gateway_class' );

