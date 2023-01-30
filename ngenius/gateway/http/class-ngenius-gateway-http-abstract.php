<?php

if ( ! defined('ABSPATH')) {
    exit;
}

/**
 * NgeniusGatewayHttpAbstract class.
 */
abstract class NgeniusGatewayHttpAbstract
{

    const NGENIUS_EMBEDED = '_embedded';
    const NGENIUS_CAPTURE = 'cnp:capture';
    const NGENIUS_REFUND  = 'cnp:refund';

    /**
     * Ngenius Order status.
     */
    protected $order_status;

    /**
     * Places request to gateway.
     *
     * @param TransferInterface $transfer_object
     *
     * @return array|null
     */
    public function place_request(NgeniusGatewayHttpTransfer $transfer_object)
    {
        $this->order_status = include dirname(__FILE__) . '/../order-status-ngenius.php';
        $data               = $this->pre_process($transfer_object->get_body());

        try {
            $method = $transfer_object->get_method();

            $response = $this->process_curl($transfer_object->get_uri(), $transfer_object->get_headers(), $data,$method);

            if (isset($response->_id)) {
                $return_data = $this->post_process($response);
            } else {
                $return_data = new WP_Error('ngenius_error', 'Failed! ' . $response->errors[0]->message);
            }
        } catch (Exception $e) {
            $return_data = new WP_Error('error', $e->getMessage());
        }

        return $return_data;
    }

    public function process_curl($url, $args, $data,$method)
    {
        $authorization = "Authorization:" . $args['Authorization'];

        $headers = array(
            'Content-Type: application/vnd.ni-payment.v2+json',
            $authorization,
            'Accept: application/vnd.ni-payment.v2+json'
        );

        $ch         = curl_init();
        $curlConfig = array(
            CURLOPT_URL            => $url,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        );
        if($data) {
            $json_data = json_decode($data);
        }

        if (is_object($json_data)) {
            $curlConfig[CURLOPT_POST]       = true;
            $curlConfig[CURLOPT_POSTFIELDS] = $data;
        }

        if($method == "PUT"){
            $curlConfig[CURLOPT_PUT]       = true;
        }

        curl_setopt_array($ch, $curlConfig);
        $response = curl_exec($ch);

        return json_decode($response);
    }

    /**
     * Processing of API request body
     *
     * @param array $data
     *
     * @return string|array
     */
    abstract protected function pre_process(array $data);

    /**
     * Processing of API response
     *
     * @param array $response
     *
     * @return array|null
     */
    abstract protected function post_process($response);
}
