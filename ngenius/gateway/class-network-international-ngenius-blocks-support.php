<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

add_action('plugins_loaded', function() {
    if (!class_exists('WooCommerce')) {
        return;
    }

    if (!defined('ABSPATH')) {
        exit;
    }
});

/**
 * Ngenius payment method integration
 *
 * @since 1.5.0
 */
final class NetworkInternationalNgeniusBlocksSupport extends AbstractPaymentMethodType
{
    /**
     * Name of the payment method.
     *
     * @var string
     */
    protected $name = 'ngenius';
    protected $settings;

    /**
     * Initializes the payment method type.
     */
    public function initialize()
    {
        $this->settings = get_option('woocommerce_ngenius_settings', []);
        add_action('enqueue_block_assets', [$this, 'enqueue_ngenius_blocks_styles']);
    }
    /**
     * Enqueue CSS for WooCommerce Blocks checkout.
     */
    public function enqueue_ngenius_blocks_styles()
    {
        if (is_checkout()) {
            wp_enqueue_style(
                'ngenius-blocks-style',
                NETWORK_INTERNATIONAL_NGENIUS_URL . '/resources/css/style.css',
                [],
                NETWORK_INTERNATIONAL_NGENIUS_VERSION
            );
        }
    }

    /**
     * Returns if this payment method should be active. If false, the scripts will not be enqueued.
     *
     * @return boolean
     */
    public function is_active()
    {
        $payment_gateways_class = WC()->payment_gateways();
        $payment_gateways       = $payment_gateways_class->payment_gateways();

        if (!isset($payment_gateways['ngenius']) || !$payment_gateways['ngenius']) {
            return false;
        }

        return $payment_gateways['ngenius']->is_available();
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles()
    {
        $asset_path   = NETWORK_INTERNATIONAL_NGENIUS_PATH . '/resources/js/index.asset.php';
        $version      = NETWORK_INTERNATIONAL_NGENIUS_VERSION;
        $dependencies = [];
        if (file_exists($asset_path)) {
            $asset        = require $asset_path;
            $version      = is_array($asset) && isset($asset['version'])
                ? $asset['version']
                : $version;
            $dependencies = is_array($asset) && isset($asset['dependencies'])
                ? $asset['dependencies']
                : $dependencies;
        }
        wp_register_script(
            'wc-ngenius-blocks-integration',
            NETWORK_INTERNATIONAL_NGENIUS_URL . '/resources/js/index.js',
            $dependencies,
            $version,
            true
        );
        wp_set_script_translations(
            'wc-ngenius-blocks-integration',
            'woocommerce'
        );

        return ['wc-ngenius-blocks-integration'];
    }

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script.
     *
     * @return array
     */
    public function get_payment_method_data()
    {
        return [
            'title'       => $this->get_setting('title'),
            'description' => $this->get_setting('description'),
            'supports'    => $this->get_supported_features(),
            'logo_url'    => NETWORK_INTERNATIONAL_NGENIUS_URL . '/resources/network_logo.png',
        ];
    }

    /**
     * Returns an array of supported features.
     *
     * @return string[]
     */
    public function get_supported_features()
    {
        $payment_gateways = WC()->payment_gateways->payment_gateways();

        if (!isset($payment_gateways['ngenius']) || !$payment_gateways['ngenius']) {
            return [];
        }

        return $payment_gateways['ngenius']->supports;
    }
}
