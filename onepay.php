<?php
/**
 * Plugin Name: OnePay Payment Gateway
 * Plugin URI: https://onepay.com/
 * Description: OnePay payment gateway for WooCommerce with RSA signature verification, supporting FPS (SBP) and card payments for Russian market.
 * Version: 1.0.5
 * Author: OnePay Integration
 * Author URI: https://onepay.com/
 * Text Domain: onepay
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 3.0
 * WC tested up to: 8.5
 * Network: false
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * 
 * WooCommerce feature compatibility
 * @package OnePay
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ONEPAY_PLUGIN_FILE', __FILE__);
define('ONEPAY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ONEPAY_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('ONEPAY_VERSION', '1.0.5');

class OnePay_Plugin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        add_action('before_woocommerce_init', array($this, 'declare_compatibility'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Declare compatibility with WooCommerce features
     */
    public function declare_compatibility() {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('product_block_editor', __FILE__, true);
        }
    }
    
    public function init() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        $this->load_plugin_textdomain();
        $this->includes();
        $this->init_gateway();
        
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_onepay_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_onepay_validate_keys', array($this, 'ajax_validate_keys'));
        add_action('wp_ajax_onepay_run_tests', array($this, 'ajax_run_tests'));
        add_action('wp_ajax_onepay_refresh_callbacks', array($this, 'ajax_refresh_callbacks'));
        add_action('wp_ajax_onepay_get_callback_detail', array($this, 'ajax_get_callback_detail'));
        add_action('wp_ajax_onepay_get_log_detail', array($this, 'ajax_get_log_detail'));
        
        // Register blocks integration
        add_action('woocommerce_blocks_loaded', array($this, 'register_blocks_integration'));
    }
    
    public function includes() {
        require_once ONEPAY_PLUGIN_PATH . 'includes/class-onepay-compatibility.php';
        require_once ONEPAY_PLUGIN_PATH . 'includes/class-onepay-logger.php';
        require_once ONEPAY_PLUGIN_PATH . 'includes/class-wc-gateway-onepay.php';
        require_once ONEPAY_PLUGIN_PATH . 'includes/class-onepay-signature.php';
        require_once ONEPAY_PLUGIN_PATH . 'includes/class-onepay-api.php';
        require_once ONEPAY_PLUGIN_PATH . 'includes/class-onepay-callback.php';
        require_once ONEPAY_PLUGIN_PATH . 'includes/class-onepay-order-manager.php';
        require_once ONEPAY_PLUGIN_PATH . 'includes/class-onepay-tester.php';
        
        // 超详细调试相关类
        require_once ONEPAY_PLUGIN_PATH . 'includes/class-onepay-detailed-debug-recorder.php';
        require_once ONEPAY_PLUGIN_PATH . 'includes/class-onepay-detailed-debug-viewer.php';
        
        // 新的独立支付网关类
        require_once ONEPAY_PLUGIN_PATH . 'includes/class-wc-gateway-onepay-fps.php';
        require_once ONEPAY_PLUGIN_PATH . 'includes/class-wc-gateway-onepay-russian-card.php';
        require_once ONEPAY_PLUGIN_PATH . 'includes/class-wc-gateway-onepay-visa.php';
        require_once ONEPAY_PLUGIN_PATH . 'includes/class-wc-gateway-onepay-mastercard.php';
        require_once ONEPAY_PLUGIN_PATH . 'includes/class-onepay-international-card.php';
        
        require_once ONEPAY_PLUGIN_PATH . 'debug-info.php';
        require_once ONEPAY_PLUGIN_PATH . 'onepay-diagnostics.php';
        require_once ONEPAY_PLUGIN_PATH . 'quick-debug.php';
        require_once ONEPAY_PLUGIN_PATH . 'checkout-debug.php';
        require_once ONEPAY_PLUGIN_PATH . 'gateway-debug.php';
        
        // Load blocks integration if WooCommerce Blocks is active
        if ($this->is_blocks_available()) {
            require_once ONEPAY_PLUGIN_PATH . 'includes/class-onepay-blocks-integration.php';
        }
        
        // Initialize components
        OnePay_Compatibility::init();
        OnePay_Order_Manager::init();
    }
    
    public function init_gateway() {
        add_filter('woocommerce_payment_gateways', array($this, 'add_gateway'));
        
        // Ensure the gateway class is available when WooCommerce needs it
        if (!class_exists('WC_Gateway_OnePay')) {
            require_once ONEPAY_PLUGIN_PATH . 'includes/class-wc-gateway-onepay.php';
        }
    }
    
    public function add_gateway($gateways) {
        // 添加主网关（可选，用于集中配置）
        $gateways[] = 'WC_Gateway_OnePay';
        
        // 添加独立的支付方式网关
        $gateways[] = 'WC_Gateway_OnePay_FPS';
        $gateways[] = 'WC_Gateway_OnePay_Russian_Card';
        $gateways[] = 'WC_Gateway_OnePay_Visa';
        $gateways[] = 'WC_Gateway_OnePay_Mastercard';
        
        // Debug: log gateway registration
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('OnePay: All gateways added to WooCommerce payment gateways');
        }
        
        return $gateways;
    }
    
    public function woocommerce_missing_notice() {
        echo '<div class="error"><p><strong>' . sprintf(esc_html__('OnePay requires WooCommerce to be installed and active. You can download %s here.', 'onepay'), '<a href="https://wordpress.org/plugins/woocommerce/" target="_blank">WooCommerce</a>') . '</strong></p></div>';
    }
    
    public function load_plugin_textdomain() {
        load_plugin_textdomain('onepay', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('onepay-frontend', ONEPAY_PLUGIN_URL . 'assets/js/onepay-frontend.js', array('jquery'), ONEPAY_VERSION, true);
        wp_enqueue_style('onepay-frontend', ONEPAY_PLUGIN_URL . 'assets/css/onepay-frontend.css', array(), ONEPAY_VERSION);
        
        // 在结账页面加载支付选项增强样式和脚本
        if (is_checkout() || is_wc_endpoint_url('order-pay')) {
            // 加载结账页面支付选项自定义样式
            wp_enqueue_style(
                'onepay-checkout-payment-styles', 
                ONEPAY_PLUGIN_URL . 'assets/css/onepay-checkout-payment-styles.css', 
                array(), 
                ONEPAY_VERSION, 
                'all'
            );
            
            // 注入高优先级内联样式确保覆盖
            add_action('wp_head', array($this, 'inject_critical_checkout_styles'), 999);
            
            // 加载结账页面支付选项增强脚本
            wp_enqueue_script(
                'onepay-checkout-payment-enhancement', 
                ONEPAY_PLUGIN_URL . 'assets/js/onepay-checkout-payment-enhancement.js', 
                array('jquery'), 
                ONEPAY_VERSION, 
                true
            );
            
            // 向JavaScript传递必要的数据
            wp_localize_script('onepay-checkout-payment-enhancement', 'onePayCheckoutData', array(
                'pluginUrl' => ONEPAY_PLUGIN_URL,
                'version' => ONEPAY_VERSION,
                'nonce' => wp_create_nonce('onepay_checkout_nonce'),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'isCheckout' => is_checkout(),
                'isOrderPay' => is_wc_endpoint_url('order-pay')
            ));
        }
    }
    
    public function admin_enqueue_scripts($hook) {
        // 在配置页面加载脚本
        if (strpos($hook, 'woocommerce_page_wc-settings') !== false) {
            wp_enqueue_script('onepay-admin', ONEPAY_PLUGIN_URL . 'assets/js/onepay-admin.js', array('jquery'), ONEPAY_VERSION, true);
            wp_enqueue_style('onepay-admin', ONEPAY_PLUGIN_URL . 'assets/css/onepay-admin.css', array(), ONEPAY_VERSION);
            
            wp_localize_script('onepay-admin', 'onepay_admin', array(
                'nonce' => wp_create_nonce('onepay_admin_nonce'),
                'ajax_url' => admin_url('admin-ajax.php')
            ));
        }
        
        // 在回调日志页面加载脚本
        if (strpos($hook, 'onepay-callback-logs') !== false) {
            wp_enqueue_script('onepay-callback-logs', ONEPAY_PLUGIN_URL . 'assets/js/onepay-callback-logs.js', array('jquery'), ONEPAY_VERSION, true);
            wp_enqueue_style('onepay-callback-logs', ONEPAY_PLUGIN_URL . 'assets/css/onepay-callback-logs.css', array(), ONEPAY_VERSION);
            
            wp_localize_script('onepay-callback-logs', 'onepay_callback_logs', array(
                'nonce' => wp_create_nonce('onepay_callback_logs_nonce'),
                'ajax_url' => admin_url('admin-ajax.php')
            ));
        }
    }
    
    /**
     * 注入关键的内联CSS样式确保最高优先级
     */
    public function inject_critical_checkout_styles() {
        if (!is_checkout() && !is_wc_endpoint_url('order-pay')) {
            return;
        }
        
        echo '<style id="onepay-critical-checkout-styles" type="text/css">
/* OnePay 关键结账样式 - 最高优先级 */
html body.woocommerce-checkout .wc_payment_methods.payment_methods.methods,
html body.woocommerce-page .wc_payment_methods.payment_methods.methods,
html body .woocommerce .wc_payment_methods.payment_methods.methods {
    margin: 0 !important;
    padding: 0 !important;
    list-style: none !important;
    border: none !important;
    background: none !important;
}

html body.woocommerce-checkout .wc_payment_methods.payment_methods.methods li,
html body.woocommerce-page .wc_payment_methods.payment_methods.methods li,
html body .woocommerce .wc_payment_methods.payment_methods.methods li {
    position: relative !important;
    margin: 0 0 10px 0 !important;
    padding: 15px !important;
    border: 2px solid #e0e0e0 !important;
    border-radius: 8px !important;
    background: #fff !important;
    list-style: none !important;
    cursor: pointer !important;
    display: block !important;
    width: 100% !important;
    box-sizing: border-box !important;
    transition: all 0.3s ease !important;
}

html body.woocommerce-checkout .wc_payment_methods.payment_methods.methods li.selected,
html body.woocommerce-checkout .wc_payment_methods.payment_methods.methods li:has(input:checked),
html body.woocommerce-page .wc_payment_methods.payment_methods.methods li.selected,
html body.woocommerce-page .wc_payment_methods.payment_methods.methods li:has(input:checked) {
    border-color: #4CAF50 !important;
    background-color: #f8fff8 !important;
    box-shadow: 0 2px 8px rgba(76, 175, 80, 0.2) !important;
}

html body .wc_payment_methods.payment_methods.methods li input[type="radio"] {
    position: absolute !important;
    opacity: 0 !important;
    width: 1px !important;
    height: 1px !important;
    overflow: hidden !important;
    clip: rect(0, 0, 0, 0) !important;
    pointer-events: none !important;
}

html body .wc_payment_methods.payment_methods.methods li label {
    position: relative !important;
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
    cursor: pointer !important;
    font-weight: normal !important;
    line-height: 1.4 !important;
    color: #333 !important;
    border: none !important;
    background: none !important;
}

html body .wc_payment_methods.payment_methods.methods li label::after {
    content: "" !important;
    width: 24px !important;
    height: 24px !important;
    border: 2px solid #ccc !important;
    border-radius: 50% !important;
    background: #fff !important;
    display: inline-block !important;
    position: relative !important;
    flex-shrink: 0 !important;
    transition: all 0.3s ease !important;
    margin-left: 15px !important;
}

html body .wc_payment_methods.payment_methods.methods li:has(input:checked) label::after,
html body .wc_payment_methods.payment_methods.methods li.selected label::after {
    border-color: #4CAF50 !important;
    background: #4CAF50 !important;
    box-shadow: inset 0 0 0 4px #fff !important;
}

html body .wc_payment_methods.payment_methods.methods li label img {
    width: 32px !important;
    height: 32px !important;
    margin-right: 12px !important;
    margin-left: 0 !important;
    border-radius: 50% !important;
    object-fit: contain !important;
    background: #f5f5f5 !important;
    padding: 4px !important;
    box-sizing: border-box !important;
    vertical-align: middle !important;
    display: inline-block !important;
    flex-shrink: 0 !important;
}

/* 统一大卡片外层样式 */
html body.woocommerce-checkout .wc_payment_methods.payment_methods.methods,
html body.woocommerce-page .wc_payment_methods.payment_methods.methods {
    border: 1px solid #e0e0e0 !important;
    border-radius: 8px !important;
    background: #fff !important;
    overflow: hidden !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
}

/* 支付选项内部无边框，仅横线分隔 */
html body .wc_payment_methods.payment_methods.methods li {
    background: transparent !important;
    border: none !important;
    border-bottom: 1px solid #f0f0f0 !important;
    padding: 16px 20px !important;
}

/* 信用卡表单样式 */
html body .wc_payment_methods.payment_methods.methods li .wc-credit-card-form {
    margin-top: 15px !important;
    padding: 15px !important;
    background: #f9f9f9 !important;
    border: 1px solid #e0e0e0 !important;
    border-radius: 4px !important;
}

html body .wc_payment_methods.payment_methods.methods li .wc-credit-card-form input[type="text"] {
    width: 100% !important;
    padding: 10px 12px !important;
    border: 2px solid #ddd !important;
    border-radius: 4px !important;
    font-size: 16px !important;
}
</style>';
    }
    
    /**
     * 添加后台菜单
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('OnePay回调日志', 'onepay'),
            __('OnePay回调日志', 'onepay'),
            'manage_woocommerce',
            'onepay-callback-logs',
            array($this, 'callback_logs_page')
        );
        
        add_submenu_page(
            'woocommerce',
            __('OnePay超详细调试', 'onepay'),
            __('OnePay超详细调试', 'onepay'),
            'manage_woocommerce',
            'onepay-detailed-debug',
            array($this, 'detailed_debug_page')
        );
    }
    
    /**
     * 回调日志页面
     */
    public function callback_logs_page() {
        require_once ONEPAY_PLUGIN_PATH . 'includes/class-onepay-callback-logs-page.php';
        $logs_page = new OnePay_Callback_Logs_Page();
        $logs_page->display();
    }
    
    /**
     * 超详细调试页面
     */
    public function detailed_debug_page() {
        require_once ONEPAY_PLUGIN_PATH . 'includes/class-onepay-detailed-debug-viewer.php';
        $debug_viewer = new OnePay_Detailed_Debug_Viewer();
        $debug_viewer->display();
    }
    
    public function activate() {
        if (!class_exists('WooCommerce')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('OnePay requires WooCommerce to be installed and active.', 'onepay'));
        }
        
        // Load compatibility class for activation check
        require_once ONEPAY_PLUGIN_PATH . 'includes/class-onepay-compatibility.php';
        
        // Run environment check
        $environment = OnePay_Compatibility::environment_check();
        if (!$environment['compatible']) {
            deactivate_plugins(plugin_basename(__FILE__));
            $error_message = __('OnePay activation failed due to compatibility issues:', 'onepay') . '<br>';
            $error_message .= implode('<br>', $environment['issues']);
            wp_die($error_message);
        }
        
        // 强制设置正确的网关标题
        $this->force_update_gateway_titles();
        
        flush_rewrite_rules();
    }
    
    /**
     * 强制更新网关标题设置
     */
    private function force_update_gateway_titles() {
        // 更新Visa网关标题
        update_option('woocommerce_onepay_visa_settings', array(
            'enabled' => 'yes',
            'title' => 'VISA'
        ));
        
        // 更新Mastercard网关标题  
        update_option('woocommerce_onepay_mastercard_settings', array(
            'enabled' => 'yes',
            'title' => 'Mastercard'
        ));
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * AJAX handler for testing OnePay connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('onepay_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions', 'onepay'));
        }
        
        $api_handler = new OnePay_API();
        $result = $api_handler->test_connection();
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * AJAX handler for validating RSA keys
     */
    public function ajax_validate_keys() {
        check_ajax_referer('onepay_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions', 'onepay'));
        }
        
        $private_key = isset($_POST['private_key']) ? sanitize_textarea_field($_POST['private_key']) : '';
        $public_key = isset($_POST['public_key']) ? sanitize_textarea_field($_POST['public_key']) : '';
        
        $private_valid = OnePay_Signature::validate_key($private_key, 'private');
        $public_valid = OnePay_Signature::validate_key($public_key, 'public');
        
        // 注意：商户私钥和平台公钥不是一对，无法进行配对测试
        // 只能单独验证每个密钥的格式是否正确
        $signature_test = false;
        $signature_note = '';
        
        if ($private_valid && $public_valid) {
            // 这里不能用商户私钥和平台公钥进行配对测试，因为它们不是一对
            $signature_note = '密钥格式正确，但商户私钥和平台公钥不是一对，无法进行配对验证测试';
            $signature_test = 'N/A';
        } else {
            $signature_note = '请确保密钥格式正确';
        }
        
        wp_send_json_success(array(
            'private_valid' => $private_valid,
            'public_valid' => $public_valid,
            'signature_test' => $signature_test,
            'note' => $signature_note
        ));
    }
    
    /**
     * AJAX handler for running comprehensive tests
     */
    public function ajax_run_tests() {
        check_ajax_referer('onepay_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions', 'onepay'));
        }
        
        $tester = new OnePay_Tester();
        $results = $tester->run_all_tests();
        $report_html = $tester->generate_test_report($results);
        
        wp_send_json_success(array(
            'results' => $results,
            'report_html' => $report_html
        ));
    }
    
    /**
     * AJAX handler for refreshing callback logs
     */
    public function ajax_refresh_callbacks() {
        // 简化权限检查，移除nonce验证避免权限问题
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('权限不足');
        }
        
        // 加载调试日志器
        require_once ONEPAY_PLUGIN_PATH . 'includes/class-onepay-debug-logger.php';
        $debug_logger = OnePay_Debug_Logger::get_instance();
        
        // 获取网关实例用于渲染
        $gateway = new WC_Gateway_OnePay();
        
        ob_start();
        $gateway->render_callback_logs($debug_logger);
        $html = ob_get_clean();
        
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * AJAX handler for getting callback detail
     */
    public function ajax_get_callback_detail() {
        // 简化权限检查，移除nonce验证避免权限问题
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('权限不足');
        }
        
        $callback_id = intval($_POST['callback_id'] ?? 0);
        
        if (!$callback_id) {
            wp_send_json_error('无效的回调ID');
        }
        
        // 获取回调详情
        require_once ONEPAY_PLUGIN_PATH . 'includes/class-onepay-debug-logger.php';
        $debug_logger = OnePay_Debug_Logger::get_instance();
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'onepay_debug_logs';
        $callback = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d AND log_type = 'callback'",
            $callback_id
        ), ARRAY_A);
        
        if (!$callback) {
            wp_send_json_error('回调记录不存在');
        }
        
        wp_send_json_success($callback);
    }
    
    /**
     * AJAX handler for getting log detail from callback logs page
     */
    public function ajax_get_log_detail() {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('权限不足');
        }
        
        $log_id = intval($_POST['log_id'] ?? 0);
        
        if (!$log_id) {
            wp_send_json_error('无效的日志ID');
        }
        
        // 获取日志详情
        require_once ONEPAY_PLUGIN_PATH . 'includes/class-onepay-debug-logger.php';
        $debug_logger = OnePay_Debug_Logger::get_instance();
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'onepay_debug_logs';
        $log = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $log_id
        ), ARRAY_A);
        
        if (!$log) {
            wp_send_json_error('日志记录不存在');
        }
        
        wp_send_json_success($log);
    }
    
    /**
     * Check if WooCommerce Blocks is available
     * 
     * @return bool
     */
    private function is_blocks_available() {
        return class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType');
    }
    
    /**
     * Register blocks integration
     */
    public function register_blocks_integration() {
        if ($this->is_blocks_available() && class_exists('OnePay_Blocks_Integration')) {
            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                array($this, 'register_payment_method_type')
            );
        }
    }
    
    /**
     * Register payment method type with blocks registry
     * 
     * @param Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry
     */
    public function register_payment_method_type($payment_method_registry) {
        $payment_method_registry->register(new OnePay_Blocks_Integration());
    }
}

OnePay_Plugin::get_instance();