<?php
/*
 * Plugin Name: N-Genius Online by Network
 * Plugin URI: https://github.com/network-international/ngenius-woocommerce-plugin/
 * Description: Receive payments using the Network International Payment Solutions payments provider.
 * Author: Network International
 * Author URI: https://www.network.ae/en
 * Version: 1.3.2
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Tested up to: 6.8.2
 * WC tested up to: 10.1.2
 * WC requires at least: 6.0
 *
 * Developer: App Inlet (Pty) Ltd
 * Developer URI: https://www.appinlet.com/
 *
 * Copyright: © 2025 Network International
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: ngenius
 */

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

if (version_compare(phpversion(), '8.0', '<')) {
    die("N-Genius Online by Network requires PHP 8.0 or higher.");
}

$f = dirname(__FILE__);
require_once "$f/vendor/autoload.php";

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use Ngenius\NgeniusCommon\NgeniusOrderStatuses;

define('NETWORK_INTERNATIONAL_NGENIUS_VERSION', '1.3.2'); // WRCS: DEFINED_VERSION.
define(
    'NETWORK_INTERNATIONAL_NGENIUS_URL',
    untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__)))
);
define('NETWORK_INTERNATIONAL_NGENIUS_PATH', untrailingslashit(plugin_dir_path(__FILE__)));

function network_international_ngenius_register_order_status()
{
    $statuses = NgeniusOrderStatuses::orderStatuses('N-Genius', 'ng');
    foreach ($statuses as $status) {
        register_post_status(
            $status['status'],
            array(
                'label'                     => $status['label'],
                'public'                    => true,
                'exclude_from_search'       => false,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                // Translators: %s represents the number of orders with this status.
                'label_count'               => _n_noop(
                /* translators: %s: Number of orders with this status */
                    '%s order',
                    '%s orders',
                    'ngenius'
                ),
            )
        );
    }

    // Filter the status views to include custom labels and counts
    add_filter('views_edit-shop_order', function ($views) use ($statuses) {
        // Check user permissions
        if (!current_user_can('edit_shop_orders')) {
            return $views;
        }
        $ngenius_status_nonce = filter_input(INPUT_GET, 'ngenius_status_nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (
            empty($ngenius_status_nonce) ||
            !wp_verify_nonce(wp_unslash($ngenius_status_nonce), 'ngenius_shop_order_status_filter')
        ) {
            return $views;
        }
        $post_status = isset($_GET['post_status']) ? sanitize_text_field(wp_unslash($_GET['post_status'])) : '';
        foreach ($statuses as $status) {
            $status_key = $status['status'];
            if (isset($views[$status_key])) {
                $count = wp_count_posts('shop_order')->{$status_key};
                $label = $status['label'];

                $url = add_query_arg([
                                         'post_status' => $status_key,
                                         '_wpnonce'    => wp_create_nonce('ngenius_admin_action')
                                     ], admin_url('edit.php?post_type=shop_order'));

                $views[$status_key] = sprintf(
                    '<a href="%s"%s>%s</a>',
                    esc_url($url),
                    $post_status === $status_key ? ' class="current" aria-current="page"' : '',
                    wp_kses_post(sprintf(
                                 /* translators: 1: Order status label. 2: Order count. */
                                     __('%1$s <span class="count">(%2$s)</span>', 'ngenius'),
                                     esc_html($label),
                                     esc_html($count)
                                 ))
                );
            }
        }
        return $views;
    });
}

add_action('init', 'network_international_ngenius_register_order_status');
add_action('template_redirect', 'ngenius_cancel_order_handler', 10);

/**
 * Restore Cart if order is canceled
 */
function ngenius_cancel_order_handler(): void
{
    // Check user permissions
    if (!is_user_logged_in() || !current_user_can('edit_shop_orders')) {
        return;
    }
    // Safely check if nonce is present using filter_input and sanitize
    $nonce = sanitize_text_field(filter_input(INPUT_GET, '_wpnonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS));

    // Verify nonce
    if (!$nonce || !wp_verify_nonce($nonce, 'woocommerce-cancel_order')) {
        return;
    }

    // Check if required parameters are present after nonce verification
    $cancel_order = sanitize_text_field(filter_input(INPUT_GET, 'cancel_order', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $order_id = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);

    if (!$cancel_order || !$order_id) {
        return;
    }

    // Verify cancel_order value
    if ($cancel_order !== 'true') {
        return;
    }

    // Get the order object
    $order = wc_get_order($order_id);

    // Verify that the order exists and belongs to the current user
    if ($order && current_user_can('view_order', $order_id)) {
        // Restore cart from order items
        $order_items = $order->get_items();
        networkInternationalNgeniusRestoreCart($order_items);
    }
}

/**
 * @param array $order_items
 *
 * @return void
 */
function networkInternationalNgeniusRestoreCart(array $order_items): void
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

function network_international_ngenius_order_status($order_statuses)
{
    $statuses = NgeniusOrderStatuses::orderStatuses('N-Genius', 'ng');

    foreach ($statuses as $status) {
        $order_statuses[$status['status']] = $status['label'];
    }

    return $order_statuses;
}

add_filter('wc_order_statuses', 'network_international_ngenius_order_status');

global $wpdb;
define('NETWORK_INTERNATIONAL_NGENIUS_TABLE', $wpdb->prefix . 'ngenius_networkinternational');

function network_international_ngenius_table_install()
{
    $sql = 'CREATE TABLE IF NOT EXISTS `' . NETWORK_INTERNATIONAL_NGENIUS_TABLE . "` (
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

register_activation_hook(__FILE__, 'network_international_ngenius_table_install');

function network_international_ngenius_plugin_action_links($links)
{
    $plugin_links = array(
        '<a href="admin.php?page=wc-settings&tab=checkout&section=ngenius">' . esc_html__(
            'Settings',
            'ngenius'
        ) . '</a>',
    );

    return array_merge($plugin_links, $links);
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'network_international_ngenius_plugin_action_links');

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action('plugins_loaded', 'network_international_ngenius_init_gateway_class');

/* end menu page */

function network_international_ngenius_print_errors()
{
    settings_errors('ngenius_error');
}

function network_international_ngenius_init_gateway_class()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }
    include_once plugin_dir_path(__FILE__) . 'gateway/class-network-international-ngenius-gateway.php';
    if (class_exists('NetworkInternationalNgeniusGateway')) {
        $gateway = NetworkInternationalNgeniusGateway::get_instance();
        $gateway->init_hooks();
    }
}

function network_international_ngenius_add_gateway_class($gateways)
{
    $gateways[] = 'NetworkInternationalNgeniusGateway';
    return $gateways;
}

add_filter('woocommerce_payment_gateways', 'network_international_ngenius_add_gateway_class');

add_action('woocommerce_blocks_loaded', 'network_international_ngenius_woocommerce_blocks_support');

function network_international_ngenius_woocommerce_blocks_support()
{
    if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        require_once dirname(__FILE__) . '/gateway/class-network-international-ngenius-blocks-support.php';
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                $payment_method_registry->register(new NetworkInternationalNgeniusBlocksSupport);
            }
        );
    }
}

add_action('woocommerce_order_refunded', 'network_international_ngenius_after_refund', 10, 1);

function network_international_ngenius_after_refund($order_id): void
{
    $order = wc_get_order($order_id);
    if ($order->get_payment_method() === "ngenius") {
        $statuses = NgeniusOrderStatuses::orderStatuses('N-Genius', 'ng');

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
function network_international_ngenius_declare_hpos_compatibility()
{
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
}

add_action('before_woocommerce_init', 'network_international_ngenius_declare_hpos_compatibility');

/**
 * Check if the intl extension is loaded and display an admin notice if not.
 */
function check_intl_extension() {
    if (!extension_loaded('intl')) {
        add_action('admin_notices', 'intl_extension_not_installed_notice');
    }
}
add_action('admin_init', 'check_intl_extension');

/**
 * Display the admin notice.
 */
function intl_extension_not_installed_notice() {
    ?>
    <div class="notice notice-error is-dismissible">
        <p>
            <?php esc_html_e('The intl PHP extension is required for N-Genius Online by Network plugin to function correctly. Please install and enable the intl extension.', 'ngenius'); ?>
        </p>
        <p><strong><?php esc_html_e('Installation Instructions:', 'ngenius'); ?></strong></p>
        <ul>
            <li><strong><?php esc_html_e('For Ubuntu/Debian:', 'ngenius'); ?></strong> <code>sudo apt-get install php-intl && sudo systemctl restart apache2</code></li>
            <li><strong><?php esc_html_e('For CentOS/RHEL:', 'ngenius'); ?></strong> <code>sudo yum install php-intl && sudo systemctl restart httpd</code></li>
            <li><strong><?php esc_html_e('For Windows:', 'ngenius'); ?></strong> <?php esc_html_e('Enable', 'ngenius'); ?> <code>extension=intl</code> <?php esc_html_e('in your', 'ngenius'); ?> <code>php.ini</code> <?php esc_html_e('file and restart your server.', 'ngenius'); ?></li>
            <li><strong><?php esc_html_e('For cPanel:', 'ngenius'); ?></strong> <?php esc_html_e('Go to', 'ngenius'); ?> <em><?php esc_html_e('Select PHP Version', 'ngenius'); ?></em> <?php esc_html_e('and enable', 'ngenius'); ?> <code>intl</code>.</li>
        </ul>
    </div>
    <?php
}
