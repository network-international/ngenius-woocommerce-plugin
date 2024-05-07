<?php
/*
 * Plugin Name: N-Genius Payment Gateway
 * Plugin URI: https://github.com/network-international/ngenius-woocommerce-plugin/
 * Description: Receive payments using the Network International Payment Solutions payments provider.
 * Author: Network International
 * Author URI: https://www.network.ae/
 * Version: 1.0.5
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Tested up to: 6.5.2
 * WC tested up to: 8.8.3
 * WC requires at least: 6.0
 *
 * Developer: App Inlet (Pty) Ltd
 * Developer URI: https://www.appinlet.com/
 *
 * Copyright: © 2023 Network International
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: ngenius
 */

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */

if (version_compare(phpversion(), '8.0', '<')) {
    die("N-Genius Payment Gateway requires PHP 8.0 or higher.");
}

$f = dirname(__FILE__);
require_once "$f/vendor/autoload.php";

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use Ngenius\NgeniusCommon\NgeniusOrderStatuses;

define('WC_GATEWAY_NGENIUS_VERSION', '1.0.5'); // WRCS: DEFINED_VERSION.
define(
    'WC_GATEWAY_NGENIUS_URL',
    untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__)))
);
define('WC_GATEWAY_NGENIUS_PATH', untrailingslashit(plugin_dir_path(__FILE__)));

function register_ngenius_order_status()
{
    $statuses = NgeniusOrderStatuses::orderStatuses();
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
add_action('init', 'custom_cancel_order_handler');

/*
 * Restore Cart if order is canceled
 */
function custom_cancel_order_handler(): void
{
    // Check if the cancel_order parameter is present
    if (isset($_GET['cancel_order']) && $_GET['cancel_order'] === 'true') {
        // Get the order ID
        $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;

        // Get the order object
        $order = wc_get_order($order_id);

        // Check if the order exists and has items
        $order_items = $order ? $order->get_items() : array();

        restoreCart($order_items);
    }
}

/**
 * @param array $order_items
 *
 * @return void
 */
function restoreCart(array $order_items): void
{
    if (!empty($order_items)) {
        WC()->cart->empty_cart();
        foreach ($order_items as $product) {
            $product_id   = isset($product['product_id']) ? (int)$product['product_id'] : 0;
            $quantity     = isset($product['quantity']) ? (int)$product['quantity'] : 1;
            $variation_id = isset($product['variation_id']) ? (int)$product['variation_id'] : 0;
            $variation    = isset($product['variation']) ? (array)$product['variation'] : array();
            WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation);
        }
        WC()->cart->calculate_totals();
    }
}

function ngenius_order_status($order_statuses)
{
    $statuses = NgeniusOrderStatuses::orderStatuses();
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
    if (!class_exists('WC_Payment_Gateway')) {
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

add_action('woocommerce_blocks_loaded', 'woocommerce_ngenius_woocommerce_blocks_support');

function woocommerce_ngenius_woocommerce_blocks_support()
{
    if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        require_once dirname(__FILE__) . '/gateway/class-wc-gateway-ngenius-blocks-support.php';
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                $payment_method_registry->register(new WC_Ngenius_Blocks_Support);
            }
        );
    }
}

add_action('woocommerce_order_refunded', 'after_refund', 10, 1);

function after_refund($order_id): void
{
    $order = wc_get_order($order_id);
    if ($order->get_payment_method() === "ngenius") {
        $statuses = NgeniusOrderStatuses::orderStatuses();

        if ((float)$order->get_remaining_refund_amount() > 0) {
            $order->update_status($statuses[6]["status"]);
        } else {
            $order->update_status($statuses[8]["status"]);
        }
    }
}

/**
 * Declares support for HPOS.
 *
 * @return void
 */
function woocommerce_ngenius_declare_hpos_compatibility()
{
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
}

add_action('before_woocommerce_init', 'woocommerce_ngenius_declare_hpos_compatibility');
