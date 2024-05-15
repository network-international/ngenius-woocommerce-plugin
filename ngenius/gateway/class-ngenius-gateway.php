<?php

if (!defined('ABSPATH')) {
    exit;
}

$f = dirname(__DIR__, 1);
require_once "$f/vendor/autoload.php";

require_once dirname(__FILE__) . '/class-ngenius-abstract.php';

use Ngenius\NgeniusCommon\Formatter\ValueFormatter;
use Ngenius\NgeniusCommon\NgeniusHTTPTransfer;
use Ngenius\NgeniusCommon\NgeniusOrderStatuses;

/**
 * NgeniusGateway class.
 */
class NgeniusGateway extends NgeniusAbstract
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
     * @var NgeniusGateway
     */
    private static NgeniusGateway $instance;

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
     * @return NgeniusGateway
     */
    public static function get_instance(): NgeniusGateway
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
        if (!wp_next_scheduled('ngenius_cron_order_update')) {
            wp_schedule_event(time(), 'hourly', 'ngenius_cron_order_update');
        }
        add_action('ngenius_cron_order_update', array($this, 'cron_order_update'));
    }

    // Add the Gateway to WooCommerce

    /**
     * Initialize module hooks
     */
    public function init_hooks()
    {
        add_action('init', array($this, 'ngenius_cron_task'));
        add_action('woocommerce_api_ngeniusonline', array($this, 'update_ngenius_response'));
        if (is_admin()) {
            add_filter('woocommerce_payment_gateways', array($this, 'ngenius_woocommerce_add_gateway_ngenius'));
            add_action(
                'woocommerce_update_options_payment_gateways_' . $this->id,
                array($this, 'processAdminOptions')
            );
            add_action('add_meta_boxes', array($this, 'ngenius_online_meta_boxes'), 10, 2);
            add_action('save_post', array($this, 'ngenius_online_actions'));
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
        do_action('dpocard_solution_addfields', $html);
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
        include_once dirname(__FILE__) . '/request/class-ngenius-gateway-request-authorize.php';
        include_once dirname(__FILE__) . '/request/class-ngenius-gateway-request-sale.php';
        include_once dirname(__FILE__) . '/request/class-ngenius-gateway-request-purchase.php';
        include_once dirname(__FILE__) . '/http/class-ngenius-gateway-http-authorize.php';
        include_once dirname(__FILE__) . '/http/class-ngenius-gateway-http-purchase.php';
        include_once dirname(__FILE__) . '/http/class-ngenius-gateway-http-sale.php';
        include_once dirname(__FILE__) . '/validator/class-ngenius-gateway-validator-response.php';

        global $woocommerce;
        $order       = wc_get_order($order_id);
        $config      = new NgeniusGatewayConfig($this, $order);
        $token_class = new NgeniusGatewayRequestToken($config);
        $data        = [];


        if ($config->is_complete()) {
            $token = $token_class->get_access_token();
            if ($token && !is_wp_error($token)) {
                $config->set_token($token);
                if ($config->get_payment_action() == "authorize") {
                    $request_class = new NgeniusGatewayRequestAuthorize($config);
                    $request_http  = new NgeniusGatewayHttpAuthorize();
                } elseif ($config->get_payment_action() == "sale") {
                    $request_class = new NgeniusGatewayRequestSale($config);
                    $request_http  = new NgeniusGatewayHttpSale();
                } elseif ($config->get_payment_action() == "purchase") {
                    $request_class = new NgeniusGatewayRequestPurchase($config);
                    $request_http  = new NgeniusGatewayHttpPurchase();
                }


                $validator = new NgeniusGatewayValidatorResponse();

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
                        'result'   => 'success',
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
        throw new Exception($message);
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
        $wpdb->replace(
            NGENIUS_TABLE,
            array_merge(
                $wp_session['ngenius'],
                array(
                    'order_id' => $order->get_id(),
                    'currency' => $order->get_currency(),
                    'amount'   => $order->get_total(),
                )
            )
        );
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
        $wpdb->update(NGENIUS_TABLE, $data, $where);
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
                    __('Invalid Reference ID'),
                    'error'
                );
            }
            if (empty($this->get_option('apiKey'))) {
                $this->add_settings_error(
                    'ngenius_error',
                    esc_attr('settings_updated'),
                    __('Invalid API Key'),
                    'error'
                );
            }
            add_action('admin_notices', 'print_errors');
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
        include_once plugin_dir_path(__FILE__) . '/class-ngenius-gateway-payment.php';
        $payment = new NgeniusGatewayPayment();
        $payment->execute($order_ref);
        die;
    }

    /**
     * Cron Job Action
     */
    public function cron_order_update()
    {
        include_once plugin_dir_path(__FILE__) . '/class-ngenius-gateway-payment.php';
        $payment = new NgeniusGatewayPayment();
        if ($payment->order_update()) {
            $this->log('Cron updated the orders: ' . $payment->order_update());
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
    public function fetch_order(int $order_id): object|null
    {
        global $wpdb;

        return $wpdb->get_row(sprintf('SELECT * FROM %s WHERE order_id=%d', NGENIUS_TABLE, $order_id));
    }

    /**
     * N-Genius Meta Boxes
     */
    public function ngenius_online_meta_boxes($post_type, $post)
    {
        $order_id = $post->ID;
        $order    = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $payment_method = $order->get_meta('_payment_method', true);
        if ($this->id === $payment_method) {
            add_meta_box(
                'ngenius-payment-actions',
                __('N-Genius Payment Gateway', 'woocommerce'),
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
            $order_item    = $this->fetch_order($order_id);
            $currency_code = $order_item->currency . ' ';
            ValueFormatter::formatCurrencyDecimals(trim($currency_code), $order_item->amount);
            $html = '';
            try {
                $ngAuthorised            = "";
                $ngAuthorisedAmount      = $currency_code . $order_item->amount;
                $ngAuthorisedAmountLabel = __('Authorized:', 'woocommerce');
                if ('ng-authorised' === $order_item->status) {
                    $ngAuthorised = <<<HTML
                        <tr>
                            <td> $ngAuthorisedAmountLabel </td>
                            <td> $ngAuthorisedAmount </td>
                        </tr>
HTML;
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
                if ('ng-authorised' === $order_item->status) {
                    $ng_void       = __('Void', 'woocommerce');
                    $ng_capture    = __('Capture', 'woocommerce');
                    $ngAuthorised2 = <<<HTML
                        <tr>
                            <td>
                                <input id="ngenius_void_submit" class="button void"
                                 name="ngenius_void" type="submit" value="$ng_void" />
                            </td>
                            <td>
                                <input id="ngenius_capture_submit" class="button button-primary"
                                 name="ngenius_capture" type="submit" value="$ng_capture" />
                            </td>
                        </tr>

HTML;
                }

                $orderStatuses  = NgeniusOrderStatuses::orderStatuses();
                $ng_state       = __('Status:', 'woocommerce');
                $ng_state_value = $order->get_status();

                $itemState = $order_item->state;
                foreach ($orderStatuses as $status) {
                    if ("wc-" . $ng_state_value === $status["status"]) {
                        $ng_state_value = $status["label"];
                    }
                }

                $ng_payment_id_label = __('Payment_ID:', 'woocommerce');
                $ng_payment_id       = $order_item->payment_id;

                $ng_captured_label  = __('Captured:', 'woocommerce');
                $ng_captured_amount = $currency_code . $order_item->amount;

                $ng_refunded_label  = __('Refunded:', 'woocommerce');
                $ng_refunded_amount = $currency_code . $refunded;

                $html = <<<HTML
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

HTML;
                // Don't display captured line on 'STARTED' and 'AUTHORISED' states
                if ($itemState != 'STARTED'
                    && $itemState != 'AUTHORISED'
                    && $itemState != 'REVERSED'
                ) {
                    $html .= <<<HTML
                    <tr>
                        <td> $ng_captured_label </td>
				        <td> $ng_captured_amount </td>
                    </tr>
HTML;
                }
                $html .= <<<HTML
 </table>
 HTML;
                echo ent2ncr($html);
            } catch (Exception $e) {
                throw new InvalidArgumentException($e->getMessage());
            }
        }
    }

    /**
     * Handle actions on order page
     *
     * @param int $post_id
     *
     * @return void
     */
    public function ngenius_online_actions($post_id)
    {
        $this->message = '';
        if (isset($_POST['ngenius_capture']) || isset($_POST['ngenius_void'])) {
            WC_Admin_Notices::remove_all_notices();
            $order_item = $this->fetch_order($post_id);
            $order      = wc_get_order($post_id);

            if ($order && $order_item) {
                $config      = new NgeniusGatewayConfig($this, $order);
                $token_class = new NgeniusGatewayRequestToken($config);

                $this->validate_complete($config, $token_class, $order, $order_item);
            } else {
                $this->message = 'Order #' . $post_id . ' not found.';
                WC_Admin_Notices::add_custom_notice('ngenius', $this->message);
            }
            add_filter('redirect_post_location', array($this, 'add_notice_query_var'), 99);
        }
    }

    public function validate_complete($config, $token_class, $order, $order_item)
    {
        if ($config->is_complete()) {
            $token = $token_class->get_access_token();
            if ($token) {
                $config->set_token($token);
                if (isset($_POST['ngenius_capture'])) {
                    $this->ngenius_capture($order, $config, $order_item);
                } elseif (isset($_POST['ngenius_void'])) {
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
     * @param NgeniusGatewayConfig $config
     * @param object $orderItem
     */
    public function ngenius_capture(WC_Order $order, NgeniusGatewayConfig $config, object $orderItem)
    {
        include_once dirname(__FILE__) . '/request/class-ngenius-gateway-request-capture.php';
        include_once dirname(__FILE__) . '/http/class-ngenius-gateway-http-capture.php';
        include_once dirname(__FILE__) . '/validator/class-ngenius-gateway-validator-capture.php';

        $requestClass = new NgeniusGatewayRequestCapture($config);
        $requestHttp  = new NgeniusGatewayHttpCapture();
        $validator    = new NgeniusGatewayValidatorCapture();

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

        $capturedAmount = $result['captured_amt'] ? ValueFormatter::formatOrderStatusAmount($currencyCode, $result['captured_amt']) : $result['captured_amt'];
        $totalCaptured  = $result['total_captured'] ? ValueFormatter::formatOrderStatusAmount($currencyCode, $result['total_captured']) : $result['total_captured'];

        ValueFormatter::formatCurrencyDecimals($currencyCode, $capturedAmount);

        if ($result['status'] != "failed") {
            $data                 = [];
            $data['status']       = $result['orderStatus'];
            $data['state']        = $result['state'];
            $data['captured_amt'] = $totalCaptured;
            $data['capture_id']   = $result['transaction_id'];
            $this->updateData($data, array('nid' => $orderItem->nid));
            $order_message = 'Captured an amount ' . $currencyCode . $capturedAmount;
            $this->message = 'Success! ' . $order_message . ' of an order #' . $orderItem->order_id;
            $order_message .= '. Transaction ID: ' . $result['transaction_id'];
            $order->payment_complete($result['transaction_id']);
            $order->update_status($result['orderStatus']);
            $order->add_order_note($order_message);
            $eMailer = new WC_Emails();
            $eMailer->customer_invoice($order);
        } else {
            $order_message = $result['message'];
            $order->add_order_note($order_message);
        }
    }
}
