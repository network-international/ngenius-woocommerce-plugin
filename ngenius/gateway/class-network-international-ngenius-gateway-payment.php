<?php

if (!defined('ABSPATH')) {
    exit;
}

$f = dirname(__DIR__, 1);
require_once "$f/vendor/autoload.php";

use Automattic\WooCommerce\Admin\Overrides\Order;
use \Ngenius\NgeniusCommon\NgeniusHTTPTransfer;
use Ngenius\NgeniusCommon\NgeniusOrderStatuses;
use Ngenius\NgeniusCommon\Processor\ApiProcessor;

/**
 * NetworkInternationalNgeniusGatewayPayment class.
 */
class NetworkInternationalNgeniusGatewayPayment
{
    /**
     * N-Genius states
     */
    public const NGENIUS_STARTED    = 'STARTED';
    public const NGENIUS_AUTHORISED = 'AUTHORISED';
    public const NGENIUS_CAPTURED   = 'CAPTURED';
    public const NGENIUS_PURCHASED  = 'PURCHASED';
    public const NGENIUS_FAILED     = 'FAILED';
    public const NGENIUS_CANCELED   = 'CANCELED';
    public const NGENIUS_EMBEDED    = '_embedded';
    public const NGENIUS_LINKS      = '_links';
    public const NGENIUS_AWAIT_3DS  = 'AWAIT_3DS';

    /**
     *
     * @var array Order Status
     */
    protected array $orderStatus;

    /**
     *
     * @var string N-Genius state
     */
    protected string $ngeniusState;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->orderStatus = NgeniusOrderStatuses::orderStatuses('N-Genius', 'ng');
    }

    /**
     * Execute action.
     *
     * @param string $order_ref Order reference
     */
    public function execute($order_ref): void
    {
        global $woocommerce;
        $redirect_url = wc_get_checkout_url();
        if (empty($order_ref)) {
            wp_safe_redirect($redirect_url);
            exit;
        }
        $config = new NetworkInternationalNgeniusGatewayConfig(new NetworkInternationalNgeniusGateway());

        if ($config->get_debug_mode() === 'yes') {
            wc_add_notice(__('This is a cron debugging test, the order is still pending.', 'ngenius'), 'notice');
            wp_safe_redirect($redirect_url);
            exit;
        }
        $result = $this->get_response_api($order_ref);
        if (!$result) {
            wp_safe_redirect($redirect_url);
            exit;
        }

        $responseArray = $this->objectToArray($result);
        if (isset($result->{self::NGENIUS_EMBEDED}->payment) && is_array($result->{self::NGENIUS_EMBEDED}->payment)) {
            $apiProcessor = new ApiProcessor($responseArray);
            $order_item = $this->fetch_order_by_reference($order_ref);

            if (!$order_item || !isset($order_item->order_id)) {
                wp_safe_redirect($redirect_url);
                exit;
            }
            $order = $this->process_order($apiProcessor, $order_item, $result->action ?? '');

            if ($order instanceof WC_Order) {
                $redirect_url = $order->get_checkout_order_received_url();
            }
        }
        wp_safe_redirect($redirect_url);
        exit;
    }

    public function update_order_status($action, $order, ApiProcessor $apiProcessor)
    {
        $capture_id      = '';
        $captured_amt    = 0;
        $data_table_data = array();

        $apiProcessor->processPaymentAction($action, $this->ngeniusState);

        if ($apiProcessor->isPaymentConfirmed()) {
            if ($action == "AUTH") {
                $this->order_authorize($order);
            } elseif ($action == "SALE" || $action == "PURCHASE") {
                list($captured_amt, $capture_id, $order, $sendInvoice) = $this->order_sale($order, $apiProcessor);
            }
            $data_table['status'] = $order->get_status();

            $config = new NetworkInternationalNgeniusGatewayConfig(new NetworkInternationalNgeniusGateway());

            if ($config->get_default_complete_order_status() === "yes") {
                $order->update_status('processing');
            }
        } elseif (self::NGENIUS_STARTED == $this->ngeniusState) {
            $data_table['status'] = substr($this->orderStatus[0]['status'], 3);
            $order->update_status($this->orderStatus[2]['status'], 'The transaction has been canceled.');
            $order->update_status('failed');
        } else {
            $order->update_status($this->orderStatus[2]['status'], 'The transaction has been failed.');
            $order->update_status('failed');
            $data_table['status'] = substr($this->orderStatus[2]['status'], 3);
        }

        $data_table_data['capture_id']   = $capture_id;
        $data_table_data['captured_amt'] = $captured_amt;
        $data_table_data['data_table']   = $data_table;

        return [$data_table_data, $sendInvoice ?? ''];
    }

    /**
     * Process Order.
     *
     * @param array $paymentResult
     * @param object $order_item
     * @param string $action
     *
     * @return $this|null
     */
    public function process_order(ApiProcessor $apiProcessor, $order_item, $action, $abandoned_order = false)
    {
        $data_table = [];
        if ($order_item && isset($order_item->order_id)) {
            $payment_id = $apiProcessor->getPaymentId();
            $order      = wc_get_order($order_item->order_id);
            if ($order instanceof WC_Order) {
                list($data_table_data) = $this->update_order_status($action, $order, $apiProcessor);
                $data_table                 = $data_table_data['data_table'];
                $data_table['payment_id']   = $payment_id;
                $data_table['captured_amt'] = $data_table_data['captured_amt'];
                $data_table['capture_id']   = $data_table_data['capture_id'];
                $this->update_table($data_table, $order_item->nid, $abandoned_order);

                return $order;
            } else {
                $order = new WP_Error('ngenius_error', 'Order Not Found');
                wc_get_logger()->debug("N-GENIUS: Platform order not found");
            }
        }

        return $order;
    }

    /**
     * Order Authorize.
     *
     * @param Order $order
     *
     * @return null
     */
    public function order_authorize($order)
    {
        if (self::NGENIUS_AUTHORISED === $this->ngeniusState) {
            $message = 'Authorised Amount: ' . $order->get_formatted_order_total();
            $order->payment_complete();
            $order->update_status($this->orderStatus[4]['status']);
            $order->add_order_note($message);
        }
    }

    /**
     * Order Sale.
     *
     * @param object $order
     * @param array $paymentResult
     *
     * @return null|array
     */
    public function order_sale($order, ApiProcessor $apiProcessor)
    {
        $paymentResult = $apiProcessor->getPaymentResult();

        if (self::NGENIUS_CAPTURED === $this->ngeniusState) {
            $transaction_id = '';
            $embedded       = self::NGENIUS_EMBEDED;
            $capture        = "cnp:capture";
            if (isset($paymentResult[$embedded][$capture][0])) {
                $transaction_id = $apiProcessor->getTransactionId();
            }
            $message = 'Captured Amount: ' . $order->get_formatted_order_total(
                ) . ' | Transaction ID: ' . $transaction_id;
            $order->payment_complete($transaction_id);
            $order->update_status($this->orderStatus[3]['status']);
            $order->add_order_note($message);
            $order->save();

            $config = new NetworkInternationalNgeniusGatewayConfig(new NetworkInternationalNgeniusGateway());

            if ($config->get_default_complete_order_status() !== "yes") {
                $order->update_status('completed');
                $order->save();
            }
            $order->update_status($this->orderStatus[3]['status']);
            $order->save();

            return array($order->get_total(), $transaction_id, $order, true);
        } elseif (self::NGENIUS_PURCHASED === $this->ngeniusState) {
            $transaction_id = '';
            $message        = "Purchased Amount with action PURCHASED";
            $order->payment_complete($transaction_id);
            $order->update_status($this->orderStatus[3]['status']);
            $order->add_order_note($message);

            $this->sendCustomerInvoice($order);

            return array($order->get_total(), $transaction_id, $order, false);
        }
    }

    /**
     * @param $order
     *
     * @return void
     */
    public function sendCustomerInvoice($order): void
    {
        if (!class_exists('WC_Emails')) {
            return;
        }

        if (!$order) {
            return;
        }

        $mailer = WC()->mailer(); // Get the WooCommerce mailer
        $mails  = $mailer->get_emails(); // Get all the email classes

        if (isset($mails['WC_Email_Customer_Invoice'])) {
            $mails['WC_Email_Customer_Invoice']->trigger($order->get_id());
        }
    }

    /**
     * Gets Response API.
     *
     * @param string $order_ref
     *
     * @return array|boolean
     */
    public function get_response_api($order_ref)
    {
        include_once dirname(__FILE__) . '/http/class-network-international-ngenius-gateway-http-abstract.php';
        include_once dirname(__FILE__) . '/config/class-network-international-ngenius-gateway-config.php';
        include_once dirname(__FILE__) . '/request/class-network-international-ngenius-gateway-request-token.php';
        include_once dirname(__FILE__) . '/http/class-network-international-ngenius-gateway-http-transfer.php';
        include_once dirname(__FILE__) . '/http/class-network-international-ngenius-gateway-http-fetch.php';

        $gateway     = new NetworkInternationalNgeniusGateway();
        $order = $this->fetch_order_by_reference($order_ref);
        $config      = new NetworkInternationalNgeniusGatewayConfig($gateway, $order);
        $token_class = new NetworkInternationalNgeniusGatewayRequestToken($config);
        $token       = $token_class->get_access_token();

        if ($token && !is_wp_error($token)) {
            $fetch_class = new NetworkInternationalNgeniusGatewayHttpFetch();

            $transfer_class = new NgeniusHttpTransfer(
                $config->get_fetch_request_url($order_ref),
                $config->get_http_version(),
                'GET'
            );

            $transfer_class->setPaymentHeaders($token);

            $response = $fetch_class->place_request($transfer_class);

            return $this->result_validator($response);
        }
    }

    /**
     * Result Validator.
     *
     * @param array $result
     *
     * @return array|boolean
     */
    public function result_validator($result)
    {
        if (is_wp_error($result)) {
            throw new InvalidArgumentException(wp_kses_post($result->get_error_message()));
        } else {
            if (isset($result->errors)) {
                return false;
            } else {
                $embedded           = self::NGENIUS_EMBEDED;
                $this->ngeniusState = $result->$embedded->payment[0]->state ?? '';

                return $result;
            }
        }
    }
    public function fetch_orders(string $whereClause, array $params = []): array
    {
        global $wpdb;

        $table = NETWORK_INTERNATIONAL_NGENIUS_TABLE;

        $cache_key = 'ngenius_orders_' . md5($whereClause . serialize($params));
        $cache_group = 'ngenius_orders';

        $cached_results = wp_cache_get($cache_key, $cache_group);
        if ($cached_results !== false) {
            return $cached_results;
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is a safe constant.
        $sql = "SELECT * FROM {$table} WHERE {$whereClause}";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL query passed as variable, safely prepared.
        $prepared_sql = $wpdb->prepare($sql, ...$params);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- Query is properly prepared and result is cached.
        $results = $wpdb->get_results($prepared_sql);

        $results = is_array($results) ? $results : [];

        wp_cache_set($cache_key, $results, $cache_group, 3600);

        return $results;
    }

    /**
     * Fetch Order details.
     *
     * @param string $where
     *
     * @return object|null
     */
    public function fetch_order_by_reference(string $order_ref): ?object
    {
        global $wpdb;

        $cache_key   = 'ngenius_order_ref_' . md5($order_ref);
        $cache_group = 'ngenius_orders';

        $cached = wp_cache_get($cache_key, $cache_group);
        if ($cached !== false) {
            return $cached;
        }

        $table = NETWORK_INTERNATIONAL_NGENIUS_TABLE;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- Query is properly prepared and result is cached.
        $sql = "SELECT * FROM {$table} WHERE reference = %s ORDER BY nid DESC LIMIT 1";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL query passed as variable, safely prepared.
        $prepared_sql = $wpdb->prepare($sql, $order_ref);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- Query is properly prepared and result is cached.
        $result = $wpdb->get_row($prepared_sql);

        wp_cache_set($cache_key, $result, $cache_group, 3600);

        return $result ?: null;
    }

    /**
     * Update Table.
     *
     * @param array $data
     * @param int $nid
     *
     * @return bool true
     */
    public function update_table(array $data, int $nid, bool $abandoned_order): bool
    {
        global $wpdb;

        // Generate a unique cache key for the given nid
        $cache_key = 'ngenius_order_' . $nid;

        if (!isset($data['state'])) {
            $data['state'] = $abandoned_order ? self::NGENIUS_CANCELED : $this->ngeniusState;
        }

        // Perform the update operation
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $updated = $wpdb->update(NETWORK_INTERNATIONAL_NGENIUS_TABLE, $data, array('nid' => $nid));

        // If the update is successful, delete the cache for this key
        if (false !== $updated) {
            wp_cache_delete($cache_key, 'ngenius_orders');
        }

        return false !== $updated;
    }

    /**
     * Cron Job function
     */
    public function order_update(): bool|string
    {
        wc_get_logger()->debug("N-GENIUS: Cron started");

        $order_items = $this->fetch_orders(
            "state = %s AND payment_id = '' AND DATE_ADD(created_at, INTERVAL 60 MINUTE) < NOW()",
            [self::NGENIUS_STARTED]
        );
        $log         = [];
        $embedded    = self::NGENIUS_EMBEDED;
        $cronSuccess = false;
        if (is_array($order_items)) {
            wc_get_logger()->debug("N-GENIUS: Found " . count($order_items) . " unprocessed order(s)");
            $counter = 0;

            foreach ($order_items as $order_item) {
                if ($counter >= 5) {
                    wc_get_logger()->debug("N-GENIUS: Breaking loop at 5 orders to avoid timeout");
                    break;
                }

                try {
                    wc_get_logger()->debug("N-GENIUS: Processing order #" . $order_item->order_id);

                    $dataTable['state'] = 'CRON';
                    $this->update_table($dataTable, $order_item->nid, true);

                    $order_ref     = $order_item->reference;
                    $result        = $this->get_response_api($order_ref);
                    $responseArray = $this->objectToArray($result);

                    if ($result && isset($result->$embedded->payment) && $responseArray) {
                        $apiProcessor = new ApiProcessor($responseArray);
                        wc_get_logger()->debug("N-GENIUS: State is " . $order_item->state);
                        $action = $result->action ?? '';

                        if ($apiProcessor->isPaymentAbandoned()) {
                            $order = $this->process_order($apiProcessor, $order_item, $action, true);
                        } else {
                            $order = $this->process_order($apiProcessor, $order_item, $action);
                        }

                        $log[] = $order->get_id();
                    } else {
                        wc_get_logger()->debug("N-GENIUS: Payment result not found");
                    }
                } catch (Exception $e) {
                    wc_get_logger()->debug("N-GENIUS: Exception " . $e->getMessage());
                }
                $counter++;
            }
            $cronSuccess = wp_json_encode($log);
        }
        wc_get_logger()->debug("N-GENIUS: Cron ended");

        return $cronSuccess;
    }

    function objectToArray($obj)
    {
        if (is_object($obj)) {
            // Convert object to array
            $obj = (array)$obj;
        }

        if (is_array($obj)) {
            $arr = [];
            foreach ($obj as $key => $value) {
                // Recursively convert nested objects
                $arr[$key] = $this->objectToArray($value);
            }

            return $arr;
        }

        // Base case: return value if it's not an object or array
        return $obj;
    }
}
