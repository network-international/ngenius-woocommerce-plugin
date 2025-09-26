<?php

add_action('plugins_loaded', function() {
    if (!class_exists('WooCommerce')) {
        return;
    }

    if (!defined('ABSPATH')) {
        exit;
    }
});

use Ngenius\NgeniusCommon\Formatter\ValueFormatter;
use Ngenius\NgeniusCommon\NgeniusHTTPTransfer;
use Ngenius\NgeniusCommon\NgeniusOrderStatuses;


$f = dirname(__DIR__, 1);
require_once "$f/vendor/autoload.php";

require_once dirname(__FILE__) . '/class-network-international-ngenius-abstract.php';

/**
 * NetworkInternationalNgeniusGateway class.
 */
class NetworkInternationalNgeniusGateway extends NetworkInternationalNgeniusAbstract
{
    /**
     * Whether logging is enabled
     *
     * @var bool
     */
    public static bool $logEnabled = false;

    /**
     * Logger instance
     *
     * @var bool|WC_Logger
     */
    public static bool|WC_Logger $log = false;

    /**
     * Singleton instance
     *
     * @var NetworkInternationalNgeniusGateway
     */
    private static NetworkInternationalNgeniusGateway $instance;

    /**
     * Notice variable
     *
     * @var string
     */
    private string $message;

    /**
     * get_instance
     *
     * Returns a new instance of self, if it does not already exist.
     *
     * @access public
     * @static
     * @return NetworkInternationalNgeniusGateway
     */
    public function __construct()
    {
        parent::__construct();

        // Initialize form fields and settings
        $this->init_form_fields();
        $this->init_settings();

        // Load settings
        $this->title = $this->get_option('title', __('N-Genius by Network', 'ngenius'));
        $this->description = $this->get_option('description', __('Pay securely via N-Genius by Network.', 'ngenius'));
        $this->enabled = $this->get_option('enabled', 'no');
    }

    public static function get_instance(): NetworkInternationalNgeniusGateway
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Logging method.
     *
     * @param string $message Log message.
     * @param string $level Optional. Default 'info'. Possible values:
     *                        emergency|alert|critical|error|warning|notice|info|debug.
     */
    public static function log(string $message, string $level = 'debug')
    {
        if (self::$logEnabled) {
            if (empty(self::$log)) {
                self::$log = wc_get_logger();
            }
            self::$log->log($level, $message, array('source' => 'ngenius'));
        }
    }

    /**
     * Cron Job Hook
     */
    public function ngenius_cron_task()
    {
        if (!wp_next_scheduled('network_international_ngenius_cron_order_update')) {
            wp_schedule_event(time(), 'hourly', 'network_international_ngenius_cron_order_update');
        }
        add_action('network_international_ngenius_cron_order_update', array($this, 'cron_order_update'));
    }

    /**
     * Initialize module hooks
     */
    public function init_hooks()
    {
        add_action('init', array($this, 'ngenius_cron_task'));
        add_action('woocommerce_api_ngeniusonline', array($this, 'update_ngenius_response'));
        add_action('upgrader_process_complete', array($this, 'clear_cron_on_update'), 10, 2); // New: Clear cron on update
        if (is_admin()) {
            add_action(
                'woocommerce_update_options_payment_gateways_' . $this->id,
                array($this, 'processAdminOptions')
            );
            add_action('add_meta_boxes', array($this, 'ngenius_online_meta_boxes'), 10, 2);
            add_action('woocommerce_order_action_ngenius_capture', array($this, 'ngenius_capture_order_action'), 10, 1);
            add_action('woocommerce_order_action_ngenius_void', array($this, 'ngenius_void_order_action'), 10, 1);
            add_action('woocommerce_order_actions', array($this, 'wc_add_order_meta_box_action'), 1, 2);
        }
        register_deactivation_hook(__FILE__, array($this, 'clear_cron_on_deactivation'));
    }

    /**
     * Clear cron event when plugin is updated
     *
     * @param object $upgrader_object Unused parameter from upgrader_process_complete hook
     * @param array $options
     */
    public function clear_cron_on_update($upgrader_object, array $options)
    {
        if ($options['action'] === 'update' && $options['type'] === 'plugin') {
            $updated_plugins = $options['plugins'] ?? array();
            $plugin_file = plugin_basename(__FILE__);

            if (in_array($plugin_file, $updated_plugins)) {
                $cron_hook = 'ngenius_cron_order_update';

                if (wp_next_scheduled($cron_hook)) {
                    wp_clear_scheduled_hook($cron_hook);
                }

                if (function_exists('as_unschedule_all_actions')) {
                    as_unschedule_all_actions($cron_hook);
                }

                self::log('Cleared cron event on plugin update: ' . $cron_hook, 'info');
            }
        }
    }

    /**
     * Clear cron event when plugin is deactivated
     */
    public function clear_cron_on_deactivation()
    {
        $cron_hook = 'network_international_ngenius_cron_order_update';

        if (wp_next_scheduled($cron_hook)) {
            wp_clear_scheduled_hook($cron_hook);
        }

        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions($cron_hook);
        }

        self::log('Cleared cron event on plugin deactivation: ' . $cron_hook, 'info');
    }

    /**
     * Add order meta box actions
     *
     * @param array $actions
     * @param WC_Order $order
     * @return array
     */
    public function wc_add_order_meta_box_action($actions, $order)
    {
        $order_item = $this->fetch_order($order->get_id());

        if ($order_item && 'ng-authorised' === $order_item->status) {
            $actions['ngenius_capture'] = __('Capture N-Genius Online', 'ngenius');
            $actions['ngenius_void']    = __('Void N-Genius Online', 'ngenius');
        }

        return $actions;
    }

    public function ngenius_capture_order_action($order)
    {
        $ngenius_state = ['ngenius_capture' => true];
        $this->ngeniusAction($order, $ngenius_state);
    }

    public function ngenius_void_order_action($order)
    {
        $ngenius_state = ['ngenius_void' => true];
        $this->ngeniusAction($order, $ngenius_state);
    }

    /**
     * Handle actions on order page
     *
     * @param $order
     *
     * @return void
     */
    public function ngeniusAction($order, $ngenius_state): void
    {
        $this->message = '';
        WC_Admin_Notices::remove_all_notices();
        $orderID    = $order->get_id();
        $order_item = $this->fetch_order($orderID);

        if ($order_item) {
            $config      = new NetworkInternationalNgeniusGatewayConfig($this, $order);
            $token_class = new NetworkInternationalNgeniusGatewayRequestToken($config);

            $this->validate_complete($config, $token_class, $order, $order_item, $ngenius_state);
        } else {
            $this->message = 'Order #' . $orderID . ' not found.';
            WC_Admin_Notices::add_custom_notice('ngenius', $this->message);
        }
        add_filter('redirect_post_location', array($this, 'add_notice_query_var'), 99);
    }

    /**
     * N-Genius Meta Boxes
     */
    public function ngenius_online_meta_boxes($post_type, $post)
    {
        if ('shop_order' !== $post_type || !$post instanceof WP_Post) {
            return;
        }
        $order = wc_get_order($post->ID);

        // Check if the order object is valid
        if (!$order instanceof WC_Order) {
            self::log("Invalid order object for post ID: {$post->ID}", 'error');

            return;
        }

        // Get the payment method used for the order
        $payment_method = $order->get_meta('_payment_method', true);

        // Check if the payment method matches the current gateway ID
        if ($this->id === $payment_method) {
            add_meta_box(
                'ngenius-payment-actions',
                __('N-Genius Online by Network', 'ngenius'),
                array($this, 'ngenius_online_meta_box_payment'),
                $post_type,
                'side',
                'high'
            );
        }
    }

    /**
     * Generate the N-Genius payment meta box and echos the HTML
     */
    public function ngenius_online_meta_box_payment($post)
    {
        $order_id = $post->ID;
        $order    = wc_get_order($order_id);
        if (!empty($order)) {
            $order_item = $this->fetch_order($order_id);
            if (is_null($order_item)) {
                $this->log("No order item found for order ID: " . $order_id, 'warning');

                return;
            }
            $currency_code = $order_item->currency . ' ';
            ValueFormatter::formatCurrencyDecimals(trim($currency_code), $order_item->amount);
            $html = '';
            try {
                $ngAuthorised            = "";
                $ngAuthorisedAmount      = $currency_code . $order_item->amount;
                $ngAuthorisedAmountLabel = __('Authorized:', 'ngenius');
                if ('ng-authorised' === $order_item->status) {
                    $ngAuthorised = "
                        <tr>
                            <td> $ngAuthorisedAmountLabel </td>
                            <td> $ngAuthorisedAmount </td>
                        </tr>
";
                }
                $refunded = 0;
                if ('ng-full-refunded' === $order_item->status || 'ng-part-refunded' === $order_item->status ||
                    'refunded' === $order_item->status) {
                    $refunds = $order->get_refunds();
                    foreach ($refunds as $refund) {
                        $refunded += (double)($refund->get_data()["amount"]);
                    }
                }

                $ngAuthorised2 = "";

                $orderStatuses  = NgeniusOrderStatuses::orderStatuses('N-Genius', 'ng');
                $ng_state       = __('Status:', 'ngenius');
                $ng_state_value = $order->get_status();

                $itemState = $order_item->state;
                foreach ($orderStatuses as $status) {
                    if ("wc-" . $ng_state_value === $status["status"]) {
                        $ng_state_value = $status["label"];
                    }
                }

                $ng_payment_id_label = __('Payment_ID:', 'ngenius');
                $ng_payment_id       = $order_item->payment_id;

                $ng_captured_label  = __('Captured:', 'ngenius');
                $ng_captured_amount = $currency_code . $order_item->amount;

                $ng_refunded_label  = __('Refunded:', 'ngenius');
                $ng_refunded_amount = $currency_code . $refunded;

                $html = "
                    <table>
                    <tr>
                        <td> $ng_state </td>
                        <td> $ng_state_value </td>
                    </tr>
                    <tr>
                        <td> $ng_payment_id_label </td>
                        <td> $ng_payment_id </td>
                    </tr>
                    $ngAuthorised
                 
                    <tr>
				        <td> $ng_refunded_label </td>
				        <td> $ng_refunded_amount </td>
				    </tr>
				    $ngAuthorised2

";
                // Don't display captured line on 'STARTED' and 'AUTHORISED' states
                if ($itemState != 'STARTED'
                    && $itemState != 'AUTHORISED'
                    && $itemState != 'REVERSED'
                ) {
                    $html .= "
                    <tr>
                        <td> $ng_captured_label </td>
				        <td> $ng_captured_amount </td>
                    </tr>
";
                }
                $html .= '
 </table>
';

                if ('ng-authorised' === $order_item->status) {
                    $html .= '
 <hr>
 <p style="color: gray;">Void and capture moved to order actions.</p>
 ';
                }

                echo wp_kses_post($html);
            } catch (Exception $e) {
                throw new InvalidArgumentException(wp_kses_post($e->getMessage()));
            }
        }
    }

    // WooCommerce DPO Group settings html

    public function ngenius_woocommerce_add_gateway_ngenius($methods)
    {
        $methods[] = $this->id;

        return $methods;
    }

    public function payment_fields()
    {
        $html = new stdClass();
        parent::payment_fields();
        if (isset($html->html)) {
            echo esc_html($html->html);
        }
    }

    /**
     * Add notice query variable
     *
     * @param string $location
     *
     * @return string
     */
    public function add_notice_query_var(string $location): string
    {
        remove_filter('redirect_post_location', array($this, 'add_notice_query_var'), 99);

        return add_query_arg(array('message' => false), $location);
    }

    /**
     * Processing order
     *
     * @param int $order_id
     *
     * @return array
     * @throws Exception
     * @global object $woocommerce
     */
    public function process_payment($order_id): array
    {
        include_once dirname(__FILE__) . '/request/class-network-international-ngenius-gateway-request-authorize.php';
        include_once dirname(__FILE__) . '/request/class-network-international-ngenius-gateway-request-sale.php';
        include_once dirname(__FILE__) . '/request/class-network-international-ngenius-gateway-request-purchase.php';
        include_once dirname(__FILE__) . '/http/class-network-international-ngenius-gateway-http-authorize.php';
        include_once dirname(__FILE__) . '/http/class-network-international-ngenius-gateway-http-purchase.php';
        include_once dirname(__FILE__) . '/http/class-network-international-ngenius-gateway-http-sale.php';
        include_once dirname(__FILE__) . '/validator/class-network-international-ngenius-gateway-validator-response.php';

        global $woocommerce;
        $order = wc_get_order($order_id);
        $config = new NetworkInternationalNgeniusGatewayConfig($this, $order);
        $token_class = new NetworkInternationalNgeniusGatewayRequestToken($config);
        $data = [];


        if ($config->is_complete()) {
            $token = $token_class->get_access_token();
            if ($token && !is_wp_error($token)) {
                $config->set_token($token);
                if ($config->get_payment_action() == "authorize") {
                    $request_class = new NetworkInternationalNgeniusGatewayRequestAuthorize($config);
                    $request_http = new NetworkInternationalNgeniusGatewayHttpAuthorize();
                } elseif ($config->get_payment_action() == "sale") {
                    $request_class = new NetworkInternationalNgeniusGatewayRequestSale($config);
                    $request_http = new NetworkInternationalNgeniusGatewayHttpSale();
                } elseif ($config->get_payment_action() == "purchase") {
                    $request_class = new NetworkInternationalNgeniusGatewayRequestPurchase($config);
                    $request_http = new NetworkInternationalNgeniusGatewayHttpPurchase();
                }


                $validator = new NetworkInternationalNgeniusGatewayValidatorResponse();

                $tokenRequest = $request_class->build($order);

                $transferClass = new NgeniusHttpTransfer(
                    $tokenRequest['request']['uri'],
                    $config->get_http_version(),
                    $tokenRequest['request']['method'],
                    $tokenRequest['request']['data']
                );

                $transferClass->setPaymentHeaders($token);

                if (is_wp_error($tokenRequest['token'])) {
                    $this->checkoutErrorThrow('Invalid Server Config');
                }

                $response = $request_http->place_request($transferClass);

                $result = $validator->validate($response);

                if ($result) {
                    $this->save_data($order);
                    $woocommerce->cart->empty_cart();
                    $data = array(
                        'result' => 'success',
                        'redirect' => $result,
                    );
                }
            } else {
                $errorMsg = $token->errors['error'][0];

                if ($errorMsg == '') {
                    $errorMsg = 'Invalid configuration';
                }
                $this->checkoutErrorThrow("Error! " . $errorMsg . ".");
            }
        } else {
            $this->checkoutErrorThrow("Error! Invalid configuration.");
        }

        return $data;
    }

    /**
     * @throws Exception
     */
    private function checkoutErrorThrow($message)
    {
        throw new Exception(wp_kses_post($message));
    }

    /**
     * Save data
     *
     * @param object $order
     *
     * @global object $wp_session
     * @global object $wpdb
     */
    public function save_data(object $order)
    {
        global $wpdb;
        global $wp_session;

        $order_id = $order->get_id();
        $cache_key = 'ngenius_order_' . $order_id;

        // Prepare the data to be saved
        $data = array_merge(
            $wp_session['ngenius'],
            array(
                'order_id' => $order_id,
                'currency' => $order->get_currency(),
                'amount' => $order->get_total(),
            )
        );

        // Check if the data is already cached
        $cached_data = wp_cache_get($cache_key, 'ngenius');

        if ($cached_data === false) {
            // Data not cached, perform the database operation and cache the data
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->replace(NETWORK_INTERNATIONAL_NGENIUS_TABLE, $data);

            // Cache the data
            wp_cache_set($cache_key, $data, 'ngenius');
        } else {
            // Data is already cached, no need to perform the database operation
        }
    }

    /**
     * Update data
     *
     * @param array $data
     * @param array $where
     *
     * @global object $wpdb
     */
    public function updateData(array $data, array $where): void
    {
        global $wpdb;

        // Define a unique cache key based on the update data and conditions
        $cache_key = 'ngenius_' . md5(serialize($where));

        // Perform the database update
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $updated = $wpdb->update(NETWORK_INTERNATIONAL_NGENIUS_TABLE, $data, $where);

        // If the update is successful, update the cache
        if ($updated !== false) {
            wp_cache_set($cache_key, $data, 'ngenius_cache');
        }
    }

    /**
     * Processes and saves options.
     * If there is an error thrown, will continue to save and validate fields, but will leave the error field out.
     *
     * @return bool was anything saved?
     */
    public function processAdminOptions(): bool
    {
        $saved = parent::process_admin_options();
        if ('yes' === $this->get_option('enabled', 'no')) {
            if (empty($this->get_option('outletRef'))) {
                $this->add_settings_error(
                    'ngenius_error',
                    esc_attr('settings_updated'),
                    __('Invalid Reference ID', 'ngenius'),
                    'error'
                );
            }
            if (empty($this->get_option('apiKey'))) {
                $this->add_settings_error(
                    'ngenius_error',
                    esc_attr('settings_updated'),
                    __('Invalid API Key', 'ngenius'),
                    'error'
                );
            }
            add_action('admin_notices', 'network_international_ngenius_print_errors');
        }
        if ('yes' !== $this->get_option('debug', 'no')) {
            if (empty(self::$log)) {
                self::$log = wc_get_logger();
            }
            self::$log->clear('ngenius');
        }

        return $saved;
    }

    public function add_settings_error($setting, $code, $message, $type = 'error')
    {
        global $wp_settings_errors;

        $wp_settings_errors[] = array(
            'setting' => $setting,
            'code'    => $code,
            'message' => $message,
            'type'    => $type,
        );
    }

    /**
     * Catch response from N-Genius
     */
    public function update_ngenius_response()
    {
        $order_ref = filter_input(INPUT_GET, 'ref', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        include_once plugin_dir_path(__FILE__) . '/class-network-international-ngenius-gateway-payment.php';
        $payment = new NetworkInternationalNgeniusGatewayPayment();
        $payment->execute($order_ref);
        die;
    }

    /**
     * Cron Job Action
     */
    public function cron_order_update()
    {
        include_once plugin_dir_path(__FILE__) . '/class-network-international-ngenius-gateway-payment.php';
        $payment = new NetworkInternationalNgeniusGatewayPayment();

        $cronSuccess = $payment->order_update();
        if ($cronSuccess) {
            $this->log('Cron updated the orders: ' . $cronSuccess);
        }
    }

    /**
     * Can the order be refunded?
     *
     * @param WC_Order $order Order object.
     *
     * @return bool
     */
    public function can_refund_order($order): bool
    {
        $order_item = $this->fetch_order($order->get_id());
        if (in_array($order_item->status, array('ng-complete', 'ng-captured', 'ng-part-refunded'), true)) {
            return true;
        }

        return false;
    }

    /**
     * Fetch Order details.
     *
     * @param int $order_id
     *
     * @return object
     */
    public function fetch_order(int $order_id): ?object
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ngenius_networkinternational';

        // Define a unique cache key
        $cache_key = 'ngenius_order_' . $order_id;

        // Try to get the order data from cache
        $order = wp_cache_get($cache_key, 'ngenius_orders');

        if ($order === false) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            // Custom table query and constant table name are required for N-Genius data.
            $order = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$table_name} WHERE order_id = %d",
                    $order_id
                )
            );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            wp_cache_set($cache_key, $order, 'ngenius_orders', 3600);
        }

        return $order;
    }

    public function validate_complete($config, $token_class, $order, $order_item, $ngenius_state)
    {
        if ($config->is_complete()) {
            $token = $token_class->get_access_token();
            if ($token) {
                $config->set_token($token);
                if ($ngenius_state['ngenius_capture']) {
                    $this->ngenius_capture($order, $config, $order_item);
                } elseif ($ngenius_state['ngenius_void']) {
                    $this->ngenius_void($order, $config, $order_item);
                }
                WC_Admin_Notices::add_custom_notice('ngenius', $this->message);
            }
        } else {
            $this->message = 'Payment gateway credential are not configured properly.';
            WC_Admin_Notices::add_custom_notice('ngenius', $this->message);
        }
    }

    /**
     * Process Capture
     *
     * @param WC_Order $order
     * @param NetworkInternationalNgeniusGatewayConfig $config
     * @param object $orderItem
     */
    public function ngenius_capture(WC_Order $order, NetworkInternationalNgeniusGatewayConfig $config, object $orderItem)
    {
        include_once dirname(__FILE__) . '/request/class-network-international-ngenius-gateway-request-capture.php';
        include_once dirname(__FILE__) . '/http/class-network-international-ngenius-gateway-http-capture.php';
        include_once dirname(__FILE__) . '/validator/class-network-international-ngenius-gateway-validator-capture.php';

        $requestClass = new NetworkInternationalNgeniusGatewayRequestCapture($config);
        $requestHttp  = new NetworkInternationalNgeniusGatewayHttpCapture();
        $validator    = new NetworkInternationalNgeniusGatewayValidatorCapture();

        $requestBuild = $requestClass->build($orderItem);

        $transferClass = new NgeniusHttpTransfer(
            $requestBuild['request']['uri'],
            $config->get_http_version(),
            $requestBuild['request']['method'],
            $requestBuild['request']['data']
        );

        $transferClass->setPaymentHeaders($requestBuild['token']);

        $response = $requestHttp->place_request($transferClass);
        $result   = $validator->validate($response);

        $currencyCode = $orderItem->currency;

        $capturedAmount = $result['captured_amt'];
        $totalCaptured  = $result['total_captured'];

        ValueFormatter::formatCurrencyDecimals($currencyCode, $capturedAmount);

        if (isset($result['status']) && $result['status'] === "failed") {
            $order_message = $result['message'];
            $order->add_order_note($order_message);
        } else {
            $data                 = [];
            $data['status']       = $result['orderStatus'];
            $data['state']        = $result['state'];
            $data['captured_amt'] = $totalCaptured;
            $data['capture_id']   = $result['transaction_id'];
            $this->updateData($data, array('nid' => $orderItem->nid));
            $order_message = 'Captured an amount ' . $currencyCode . $capturedAmount;
            $this->message = 'Success! ' . $order_message . ' of an order #' . $orderItem->order_id;
            $order_message .= ' | Transaction ID: ' . $result['transaction_id'];
            $order->payment_complete($result['transaction_id']);
            $order->update_status($result['orderStatus']);
            $order->add_order_note($order_message);
            $eMailer = new WC_Emails();
            $eMailer->customer_invoice($order);
        }
    }
    /**
     * Display custom payment method icon.
     *
     * @return string
     */
    public function get_icon()
    {
        $plugin_url = plugin_dir_url(dirname(__FILE__));
        $logo_url   = $plugin_url . 'resources/network_logo.png';

        $icon = '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr($this->get_title()) . '" style="height: 18px;vertical-align: middle;" />';

        return apply_filters('woocommerce_gateway_icon', $icon, $this->id);
    }
}
