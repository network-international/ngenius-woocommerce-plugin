<?php

add_action('plugins_loaded', function() {
    if (!class_exists('WooCommerce')) {
        return;
    }

    if (!defined('ABSPATH')) {
        exit;
    }
});

use \Ngenius\NgeniusCommon\NgeniusHTTPCommon;
use \Ngenius\NgeniusCommon\NgeniusHTTPTransfer;
use Ngenius\NgeniusCommon\NgeniusOrderStatuses;

$f = dirname(__DIR__, 2);
require_once "$f/vendor/autoload.php";

/**
 * NetworkInternationalNgeniusGatewayHttpAbstract class.
 */
abstract class NetworkInternationalNgeniusGatewayHttpAbstract
{
    public const NGENIUS_EMBEDED     = '_embedded';
    public const NGENIUS_CAPTURE     = 'cnp:capture';
    public const NGENIUS_REFUND      = 'cnp:refund';
    public const NGENIUS_CUP_RESULTS = 'cnp:china_union_pay_results';

    /**
     * Ngenius Order status.
     */
    protected array $orderStatus;

    /**
     * Places request to gateway.
     *
     * @param NgeniusHTTPTransfer $transferObject
     *
     * @return WP_Error|array|stdClass|null
     */
    public function place_request(NgeniusHttpTransfer $transferObject): WP_Error|array|null|stdClass
    {
        $this->orderStatus = NgeniusOrderStatuses::orderStatuses('N-Genius', 'ng');

        try {
            $response = json_decode(NgeniusHTTPCommon::placeRequest($transferObject));

            if (isset($response->_id)) {
                $returnData = $this->post_process($response);
            } else {
                $returnData = new WP_Error('ngenius_error', 'Failed! ' . $response->errors[0]->message);
            }
        } catch (Exception $e) {
            $returnData = new WP_Error('error', $e->getMessage());
        }

        return $returnData;
    }

    abstract protected function pre_process(array $data);


    protected function post_process(stdClass $response): array|stdClass|null
    {
        if (isset($response->_links->payment->href)) {
            global $wp_session;
            $data                  = [];
            $data['reference']     = $response->reference ?? '';
            $data['action']        = $response->action ?? '';
            $data['state']         = $response->_embedded->payment[0]->state ?? '';
            $data['status']        = substr($this->orderStatus[0]['status'], 3);
            $wp_session['ngenius'] = $data;

            return ['payment_url' => $response->_links->payment->href];
        } else {
            return null;
        }
    }
}
