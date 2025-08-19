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
        
        $this->title = $this->get_option('title', 'OnePay Debug Version');
        $this->description = $this->get_option('description', 'This is a debug version to test gateway display');
        $this->enabled = $this->get_option('enabled', 'no');  // 从设置中读取，默认为禁用
        
        // 添加保存设置的钩子
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        
        error_log('OnePay Debug Gateway constructed with enabled status: ' . $this->enabled);
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
        // 首先检查是否启用
        if ('yes' !== $this->enabled) {
            error_log('OnePay Debug is_available: disabled in settings - enabled=' . $this->enabled);
            return false;
        }
        
        error_log('OnePay Debug is_available: enabled and available');
        return true;
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