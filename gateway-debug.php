<?php
/**
 * OnePay 网关调试工具
 * 创建一个最简单的网关来测试
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Gateway_OnePay_Debug extends WC_Payment_Gateway {
    
    public function __construct() {
        $this->id = 'onepay_debug';
        $this->icon = '';
        $this->has_fields = false;
        $this->method_title = 'OnePay Debug';
        $this->method_description = 'Debug version of OnePay gateway';
        
        $this->supports = array(
            'products'
        );
        
        $this->init_form_fields();
        $this->init_settings();
        
        $this->title = 'OnePay Debug Version';
        $this->description = 'This is a debug version to test gateway display';
        $this->enabled = 'yes';  // 直接设为yes
        
        error_log('OnePay Debug Gateway constructed');
    }
    
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => 'Enable/Disable',
                'type'    => 'checkbox',
                'label'   => 'Enable OnePay Debug',
                'default' => 'yes'
            ),
            'title' => array(
                'title'       => 'Title',
                'type'        => 'text',
                'description' => 'This controls the title which the user sees during checkout.',
                'default'     => 'OnePay Debug',
                'desc_tip'    => true,
            )
        );
    }
    
    public function is_available() {
        error_log('OnePay Debug is_available called - returning true');
        return true; // 总是返回true用于测试
    }
    
    public function process_payment($order_id) {
        error_log('OnePay Debug process_payment called for order: ' . $order_id);
        
        return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url(wc_get_order($order_id))
        );
    }
}

// 注册调试网关
add_filter('woocommerce_payment_gateways', function($gateways) {
    $gateways[] = 'WC_Gateway_OnePay_Debug';
    error_log('OnePay Debug Gateway added to payment gateways');
    return $gateways;
});

// 输出调试信息到控制台
add_action('wp_footer', function() {
    if (is_checkout() && current_user_can('administrator')) {
        ?>
        <script>
        console.log('OnePay Debug: Checkout page loaded');
        
        // 检查所有支付方法
        jQuery(document).ready(function($) {
            console.log('Available payment methods:', $('.payment_methods input[name="payment_method"]').map(function() {
                return this.value;
            }).get());
        });
        </script>
        <?php
    }
}, 999);