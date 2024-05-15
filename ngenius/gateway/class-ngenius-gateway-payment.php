<?php

if (!defined('ABSPATH')) {
    exit;
}

$f = dirname(__DIR__, 1);
require_once "$f/vendor/autoload.php";

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
        $this->orderStatus = NgeniusOrderStatuses::orderStatuses();
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
        if ($order_ref) {
            $result  = $this->get_response_api($order_ref);
            $embeded = self::NGENIUS_EMBEDED;
            if ($result && isset($result->$embeded->payment) && is_array($result->$embeded->payment)) {
                $action         = isset($result->action) ? $result->action : '';
                $payment_result = $result->$embeded->payment[0];
                $array          = $this->fetch_order("reference='" . $order_ref . "'");
                $order_item     = reset($array);
                $order          = $this->process_order($payment_result, $order_item, $action);
                $redirect_url   = $order->get_checkout_order_received_url();
            }
            wp_redirect($redirect_url);
            exit();
        } else {
            wp_redirect($redirect_url);
            exit();
        }
    }

    public function get_payment_id($payment_result)
    {
        $payment_id = '';
        if (isset($payment_result->_id)) {
            $payment_id_arr = explode(':', $payment_result->_id);
            $payment_id     = end($payment_id_arr);
        }

        return $payment_id;
    }

    public function update_order_status($action, $order, $payment_result)
    {
        $capture_id      = '';
        $captured_amt    = 0;
        $data_table_data = array();
        $order_ref       = filter_input(INPUT_GET, 'ref', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $data            = $this->get_response_api($order_ref);
        $result          = $this->objectToArray($data);

        $apiProcessor = new ApiProcessor($result);
        $apiProcessor->processPaymentAction($action, $this->ngeniusState);
        if (self::NGENIUS_FAILED !== $this->ngeniusState) {
            if (self::NGENIUS_STARTED !== $this->ngeniusState) {
                if ($action == "AUTH") {
                    $this->order_authorize($order);
                } elseif ($action == "SALE" || $action == "PURCHASE") {
                    echo "update_status";
                    list($captured_amt, $capture_id, $order, $sendInvoice) = $this->order_sale($order, $payment_result);
                }
                $data_table['status'] = $order->get_status();

                $config = new NgeniusGatewayConfig(new NgeniusGateway());

                if ($config->get_default_complete_order_status() === "yes") {
                    $order->update_status('processing');
                }
            } else {
                $data_table['status'] = substr($this->orderStatus[0]['status'], 3);
            }
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
     * @param array $payment_result
     * @param object $order_item
     * @param string $action
     *
     * @return $this|null
     */
    public function process_order($payment_result, $order_item, $action, $abandoned_order = false)
    {
        $data_table = [];
        $order      = "";
        if ($order_item->order_id) {
            $payment_id = $this->get_payment_id($payment_result);

            $order = wc_get_order($order_item->order_id);
            if ($order) {
                list($data_table_data) = $this->update_order_status($action, $order, $payment_result);
                $data_table                 = $data_table_data['data_table'];
                $data_table['payment_id']   = $payment_id;
                $data_table['captured_amt'] = $data_table_data['captured_amt'];
                $data_table['capture_id']   = $data_table_data['capture_id'];
                $this->update_table($data_table, $order_item->nid, $abandoned_order);

                return $order;
            } else {
                $order = new WP_Error('ngenius_error', 'Order Not Found');
            }
        }

        return $order;
    }

    /**
     * Order Authorize.
     *
     * @param object $order
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
     * @param array $payment_result
     *
     * @return null|array
     */
    public function order_sale($order, $payment_result)
    {
        if (self::NGENIUS_CAPTURED === $this->ngeniusState) {
            $transaction_id = '';
            $embeded        = self::NGENIUS_EMBEDED;
            $capture        = "cnp:capture";
            $links          = self::NGENIUS_LINKS;
            $refund         = "cnp:refund";
            if (isset($payment_result->$embeded->$capture[0])) {
                $last_transaction = $payment_result->$embeded->$capture[0];
                if (isset($last_transaction->$links->self->href)) {
                    $transaction_arr = explode('/', $last_transaction->$links->self->href);
                    $transaction_id  = end($transaction_arr);
                } elseif ($last_transaction->$links->$refund->href) {
                    $transaction_arr = explode('/', $last_transaction->$links->$refund->href);
                    $transaction_id  = $transaction_arr[count($transaction_arr) - 2];
                }
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
            $emailer = new WC_Emails();
            $emailer->customer_invoice($order);

            return array($order->get_total(), $transaction_id, $order, false);
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
            throw new InvalidArgumentException($result->get_error_message());
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

        return $wpdb->get_results(sprintf('SELECT * FROM %s WHERE %s ORDER BY `nid` DESC', NGENIUS_TABLE, $where));
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

        if (!isset($data['state'])) {
            $data['state'] = $abandoned_order ? self::NGENIUS_CANCELED : $this->ngeniusState;
        }

        return $wpdb->update(NGENIUS_TABLE, $data, array('nid' => $nid));
    }

    /**
     * Cron Job function
     */
    public function order_update(): bool|string
    {
        $order_items = $this->fetch_order(
            "state = '" . self::NGENIUS_STARTED .
            "' AND payment_id='' AND DATE_ADD(created_at, INTERVAL 60 MINUTE) < NOW()"
        );
        $log         = [];
        $embedded    = self::NGENIUS_EMBEDED;
        if (is_array($order_items)) {
            foreach ($order_items as $order_item) {
                $dataTable['state'] = 'CRON';
                $this->update_table($dataTable, $order_item->nid, true);

                $order_ref = $order_item->reference;
                $result    = $this->get_response_api($order_ref);
                if ($result && isset($result->$embedded->payment)) {
                    $action         = $result->action ?? '';
                    $payment_result = $result->$embedded->payment[0];
                    if ($payment_result->state == self::NGENIUS_STARTED
                        || $payment_result->state == self::NGENIUS_AWAIT_3DS
                    ) {
                        $order = $this->process_order($payment_result, $order_item, $action, true);
                    } else {
                        $order = $this->process_order($payment_result, $order_item, $action);
                    }
                    $log[] = $order->get_id();
                }
            }

            return json_encode($log);
        } else {
            return false;
        }
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
