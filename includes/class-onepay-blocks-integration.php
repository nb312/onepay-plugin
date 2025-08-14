<?php

if (!defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * OnePay Blocks Integration Class
 * 
 * Integrates OnePay payment gateway with WooCommerce Blocks checkout
 */
final class OnePay_Blocks_Integration extends AbstractPaymentMethodType {

    /**
     * Payment method name/id/slug
     * 
     * @var string
     */
    protected $name = 'onepay';

    /**
     * Instance of the main gateway class
     * 
     * @var WC_Gateway_OnePay
     */
    private $gateway;

    /**
     * Constructor
     */
    public function __construct() {
        // Load the gateway class if not already loaded
        if (!class_exists('WC_Gateway_OnePay')) {
            require_once ONEPAY_PLUGIN_PATH . 'includes/class-wc-gateway-onepay.php';
        }
        
        // Get the gateway instance
        $gateways = WC()->payment_gateways->payment_gateways();
        $this->gateway = isset($gateways['onepay']) ? $gateways['onepay'] : new WC_Gateway_OnePay();
    }

    /**
     * Initialize the payment method type
     */
    public function initialize() {
        $this->settings = get_option('woocommerce_onepay_settings', []);
    }

    /**
     * Check if the payment method is active
     * 
     * @return bool
     */
    public function is_active() {
        return $this->gateway->is_available();
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method
     * 
     * @return array
     */
    public function get_payment_method_script_handles() {
        $script_path = 'assets/js/onepay-blocks.js';
        $script_asset_path = ONEPAY_PLUGIN_PATH . 'assets/js/onepay-blocks.asset.php';
        
        $script_asset = file_exists($script_asset_path)
            ? require($script_asset_path)
            : array(
                'dependencies' => array(
                    'wc-blocks-registry',
                    'wc-settings',
                    'wp-element',
                    'wp-html-entities',
                    'wp-i18n',
                ),
                'version' => ONEPAY_VERSION
            );

        wp_register_script(
            'onepay-blocks-integration',
            ONEPAY_PLUGIN_URL . $script_path,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('onepay-blocks-integration', 'onepay');
        }
        
        // Register blocks CSS
        wp_register_style(
            'onepay-blocks-style',
            ONEPAY_PLUGIN_URL . 'assets/css/onepay-blocks.css',
            array(),
            ONEPAY_VERSION
        );
        
        wp_enqueue_style('onepay-blocks-style');

        return ['onepay-blocks-integration'];
    }

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script
     * 
     * @return array
     */
    public function get_payment_method_data() {
        return [
            'title' => $this->gateway->title,
            'description' => $this->gateway->description,
            'supports' => array_filter($this->gateway->supports, [$this->gateway, 'supports']),
            'showSaveOption' => false,
            'showSavedCards' => false,
            'isAdmin' => is_admin(),
            'paymentMethods' => [
                'FPS' => [
                    'name' => __('FPS (Fast Payment System)', 'onepay'),
                    'description' => __('Russian Fast Payment System - Min: 1 RUB, Max: Account limit', 'onepay')
                ],
                'CARDPAYMENT' => [
                    'name' => __('Card Payment', 'onepay'),
                    'description' => __('Pay with your card - Min: 1 RUB, Max: Card limit', 'onepay')
                ]
            ],
            'defaultMethod' => 'FPS',
            'icons' => [
                'fps' => ONEPAY_PLUGIN_URL . 'assets/images/fps-icon.png',
                'card' => ONEPAY_PLUGIN_URL . 'assets/images/card-icon.png'
            ]
        ];
    }
}