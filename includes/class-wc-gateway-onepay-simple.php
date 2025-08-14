<?php
/**
 * OnePay Simple Gateway - 最小化版本用于测试
 * 这是一个完全符合WooCommerce标准的最简单实现
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * OnePay Payment Gateway Simple Version
 * 
 * @class       WC_Gateway_OnePay_Simple
 * @extends     WC_Payment_Gateway
 * @version     1.0.0
 */
class WC_Gateway_OnePay_Simple extends WC_Payment_Gateway {

    /**
     * Constructor for the gateway.
     */
    public function __construct() {
        $this->id                 = 'onepay_simple';
        $this->icon               = '';
        $this->has_fields         = false;
        $this->method_title       = __('OnePay Simple', 'woocommerce');
        $this->method_description = __('Accept payments via OnePay', 'woocommerce');

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title        = $this->get_option('title', $this->method_title);
        $this->description  = $this->get_option('description', $this->method_description);
        $this->enabled      = $this->get_option('enabled');

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    /**
     * Initialize Gateway Settings Form Fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Enable/Disable', 'woocommerce'),
                'type'    => 'checkbox',
                'label'   => __('Enable OnePay Simple', 'woocommerce'),
                'default' => 'yes'
            ),
            'title' => array(
                'title'       => __('Title', 'woocommerce'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                'default'     => __('OnePay Simple', 'woocommerce'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'woocommerce'),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => __('This controls the description which the user sees during checkout.', 'woocommerce'),
                'default'     => __('Pay via OnePay', 'woocommerce')
            ),
        );
    }

    /**
     * Process the payment and return the result
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        
        // Mark as processing (payment won't be taken until delivery)
        $order->update_status('processing', __('Payment to be made upon delivery.', 'woocommerce'));
        
        // Reduce stock levels
        wc_reduce_stock_levels($order_id);
        
        // Remove cart
        WC()->cart->empty_cart();
        
        // Return thankyou redirect
        return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url($order)
        );
    }
}