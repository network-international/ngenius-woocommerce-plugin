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
 * Ngenius_Gateway_Payment class.
 */
class NgeniusGatewayPayment
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
        $config       = new NgeniusGatewayConfig(new NgeniusGateway());

        if ($config->get_debug_mode() === 'yes') {
            wc_add_notice(__('This is a cron debugging test, the order is still pending.'), 'notice');
            wp_redirect($redirect_url);
            exit();
        }

        if ($order_ref) {
            $result        = $this->get_response_api($order_ref);
            $responseArray = $this->objectToArray($result);
            $embeded       = self::NGENIUS_EMBEDED;
            if ($result && isset($result->$embeded->payment) && is_array($result->$embeded->payment)) {
                $apiProcessor = new ApiProcessor($responseArray);
                $action       = isset($result->action) ? $result->action : '';
                $array        = $this->fetch_order("reference='" . $order_ref . "'");
                $order_item   = reset($array);
                $order        = $this->process_order($apiProcessor, $order_item, $action);
                $redirect_url = $order->get_checkout_order_received_url();
            }
            wp_redirect($redirect_url);
            exit();
        } else {
            wp_redirect($redirect_url);
            exit();
        }
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

            $config = new NgeniusGatewayConfig(new NgeniusGateway());

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
        $order      = "";
        if ($order_item->order_id) {
            $payment_id = $apiProcessor->getPaymentId();

            $order = wc_get_order($order_item->order_id);
            if ($order) {
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
            $embeded        = self::NGENIUS_EMBEDED;
            $capture        = "cnp:capture";
            $links          = self::NGENIUS_LINKS;
            $refund         = "cnp:refund";
            if (isset($paymentResult[$embeded][$capture][0])) {
                $transaction_id = $apiProcessor->getTransactionId();
            }
            $message = 'Captured Amount: ' . $order->get_formatted_order_total(
                ) . ' | Transaction ID: ' . $transaction_id;
            $order->payment_complete($transaction_id);
            $order->update_status($this->orderStatus[3]['status']);
            $order->add_order_note($message);
            $order->save();

            $config = new NgeniusGatewayConfig(new NgeniusGateway());

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
        include_once dirname(__FILE__) . '/http/class-ngenius-gateway-http-abstract.php';
        include_once dirname(__FILE__) . '/config/class-ngenius-gateway-config.php';
        include_once dirname(__FILE__) . '/request/class-ngenius-gateway-request-token.php';
        include_once dirname(__FILE__) . '/http/class-ngenius-gateway-http-transfer.php';
        include_once dirname(__FILE__) . '/http/class-ngenius-gateway-http-fetch.php';

        $gateway     = new NgeniusGateway();
        $order       = $this->fetch_order("reference='" . $order_ref . "'");
        $config      = new NgeniusGatewayConfig($gateway, $order);
        $token_class = new NgeniusGatewayRequestToken($config);
        $token       = $token_class->get_access_token();

        if ($token && !is_wp_error($token)) {
            $fetch_class = new NgeniusGatewayHttpFetch();

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

    /**
     * Fetch Order details.
     *
     * @param string $where
     *
     * @return array
     */
    public function fetch_order(string $where): array
    {
        global $wpdb;

        // Create a unique cache key based on the query parameters
        $cache_key   = 'fetch_order_' . md5($where);
        $cache_group = 'ngenius_orders';

        // Try to get the cached result
        $cached_result = wp_cache_get($cache_key, $cache_group);

        if (false !== $cached_result) {
            return $cached_result;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
        $results = $wpdb->get_results(sprintf('SELECT * FROM %s WHERE %s ORDER BY `nid` DESC', NGENIUS_TABLE, $where));

        // Cache the result
        wp_cache_set($cache_key, $results, $cache_group, 3600); // Cache for 1 hour (3600 seconds)

        return $results;
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
        $updated = $wpdb->update(NGENIUS_TABLE, $data, array('nid' => $nid));

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

        $order_items = $this->fetch_order(
            "state = '" . self::NGENIUS_STARTED .
            "' AND payment_id='' AND DATE_ADD(created_at, INTERVAL 60 MINUTE) < NOW()"
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
