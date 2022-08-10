<?php
/*
 * Plugin Name: N-Genius Payment Gateway
 * Plugin URI: https://docs.ngenius-payments.com/docs/plugins
 * Description: N-Genius Online Gateway from Network International
 * Author: Network International
 * Author URI: https://www.network.ae
 * Version: 1.0.1
 */

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */


function register_ngenius_order_status()
{
    $statuses = include 'gateway/order-status-ngenius.php';
    foreach ($statuses as $status) {
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

add_action('init', 'register_ngenius_order_status');

function ngenius_order_status($order_statuses)
{
    $statuses = include 'gateway/order-status-ngenius.php';
    $id       = get_the_ID();
    if ('shop_order' === get_post_type() && $id && isset($_GET['action']) && 'edit' === $_GET['action']) {
        $order = wc_get_order($id);
        if ($order) {
            $current_status = $order->get_status();
            foreach ($statuses as $status) {
                if ('wc-' . $current_status === $status['status']) {
                    $order_statuses[$status['status']] = $status['label'];
                }
            }
        }
    } else {
        foreach ($statuses as $status) {
            $order_statuses[$status['status']] = $status['label'];
        }
    }

    return $order_statuses;
}

add_filter('wc_order_statuses', 'ngenius_order_status');

global $wpdb;
define('NGENIUS_TABLE', $wpdb->prefix . 'ngenius_networkinternational');

function ngenius_table_install()
{
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
    dbDelta($sql);
}

register_activation_hook(__FILE__, 'ngenius_table_install');

function plugin_action_links($links)
{
    $plugin_links = array(
        '<a href="admin.php?page=wc-settings&tab=checkout&section=ngenius">' . esc_html__(
            'Settings',
            'woocommerce'
        ) . '</a>',
    );

    return array_merge($plugin_links, $links);
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'plugin_action_links');

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action('plugins_loaded', 'ngenius_init_gateway_class');

/* end menu page */

function print_errors()
{
    settings_errors('ngenius_error');
}

function ngenius_init_gateway_class()
{
    if ( ! class_exists('WC_Payment_Gateway')) {
        return;
    }
    include_once 'gateway/class-ngenius-gateway.php';
    NgeniusGateway::get_instance()->init_hooks();
}

function ngenius_add_gateway_class($gateways)
{
    $gateways[] = 'ngeniusgateway';

    return $gateways;
}

add_filter('woocommerce_payment_gateways', 'ngenius_add_gateway_class');

