<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * OnePay Testing Utility Class
 * 
 * Provides testing and debugging utilities for OnePay integration
 */
class OnePay_Tester {
    
    private $gateway;
    private $logger;
    
    public function __construct() {
        $this->gateway = new WC_Gateway_OnePay();
        $this->logger = OnePay_Logger::get_instance();
    }
    
    /**
     * Run comprehensive plugin tests
     * 
     * @return array Test results
     */
    public function run_all_tests() {
        $results = array(
            'configuration' => $this->test_configuration(),
            'signature' => $this->test_signature_operations(),
            'api_connection' => $this->test_api_connection(),
            'callback_processing' => $this->test_callback_processing(),
            'order_management' => $this->test_order_management()
        );
        
        $results['overall'] = $this->calculate_overall_result($results);
        
        $this->logger->info('Comprehensive plugin test completed', $results);
        
        return $results;
    }
    
    /**
     * Test plugin configuration
     * 
     * @return array Configuration test results
     */
    public function test_configuration() {
        $tests = array(
            'merchant_no' => !empty($this->gateway->merchant_no),
            'private_key' => !empty($this->gateway->private_key),
            'platform_public_key' => !empty($this->gateway->platform_public_key),
            'api_url' => !empty($this->gateway->api_url),
            'woocommerce_active' => class_exists('WooCommerce'),
            'php_openssl' => extension_loaded('openssl'),
            'php_curl' => extension_loaded('curl'),
            'wp_remote_post' => function_exists('wp_remote_post')
        );
        
        $passed = array_filter($tests);
        $failed = array_diff_key($tests, $passed);
        
        return array(
            'passed' => count($passed),
            'failed' => count($failed),
            'total' => count($tests),
            'details' => $tests,
            'success' => empty($failed)
        );
    }
    
    /**
     * Test signature operations
     * 
     * @return array Signature test results
     */
    public function test_signature_operations() {
        $tests = array();
        
        // Test private key validation
        $tests['private_key_valid'] = OnePay_Signature::validate_key($this->gateway->private_key, 'private');
        
        // Test platform public key validation
        $tests['platform_public_key_valid'] = OnePay_Signature::validate_key($this->gateway->platform_public_key, 'public');
        
        // Test signature generation using merchant private key
        $test_data = '{"merchantNo":"' . $this->gateway->merchant_no . '","orderAmount":"1000","currency":"RUB"}';
        
        if ($tests['private_key_valid']) {
            $signature = OnePay_Signature::sign($test_data, $this->gateway->private_key);
            $tests['signature_generation'] = !empty($signature);
            
            // 注意：我们无法验证商户私钥生成的签名，因为我们没有对应的商户公钥
            // 商户公钥是提供给平台的，我们这里只有平台公钥
            // 所以这个测试只能测试签名生成，不能测试验证
            $tests['signature_verification'] = 'N/A - 需要商户公钥进行验证';
        } else {
            $tests['signature_generation'] = false;
            $tests['signature_verification'] = false;
        }
        
        // 测试平台公钥的格式和可用性
        if ($tests['platform_public_key_valid']) {
            // 我们可以测试平台公钥是否能正确加载，但无法完整测试验证功能
            // 因为我们没有平台私钥生成的测试签名
            $tests['platform_key_usable'] = true;
        } else {
            $tests['platform_key_usable'] = false;
        }
        
        // 计算通过的测试（排除verification，因为它需要成对密钥）
        $countable_tests = array(
            'private_key_valid' => $tests['private_key_valid'],
            'platform_public_key_valid' => $tests['platform_public_key_valid'],
            'signature_generation' => $tests['signature_generation'],
            'platform_key_usable' => $tests['platform_key_usable']
        );
        
        $passed = array_filter($countable_tests);
        
        return array(
            'passed' => count($passed),
            'failed' => count($countable_tests) - count($passed),
            'total' => count($countable_tests),
            'details' => $tests,
            'note' => '签名验证需要成对的密钥。商户私钥配对商户公钥，平台私钥配对平台公钥。',
            'success' => count($passed) === count($countable_tests)
        );
    }
    
    /**
     * Test API connection
     * 
     * @return array API connection test results
     */
    public function test_api_connection() {
        $api_handler = new OnePay_API();
        $connection_result = $api_handler->test_connection();
        
        $tests = array(
            'api_reachable' => $connection_result['success'],
            'url_valid' => filter_var($this->gateway->api_url, FILTER_VALIDATE_URL) !== false,
            'ssl_enabled' => strpos($this->gateway->api_url, 'https://') === 0 || 
                           (strpos($this->gateway->api_url, 'http://') === 0 && $this->gateway->testmode)
        );
        
        $passed = array_filter($tests);
        
        return array(
            'passed' => count($passed),
            'failed' => count($tests) - count($passed),
            'total' => count($tests),
            'details' => $tests,
            'connection_message' => $connection_result['message'],
            'api_url' => $this->gateway->api_url,
            'ssl_note' => !$tests['ssl_enabled'] ? 'HTTPS is recommended for production use' : 'SSL properly configured',
            'success' => count($passed) === count($tests)
        );
    }
    
    /**
     * Test callback processing functionality
     * 
     * @return array Callback test results
     */
    public function test_callback_processing() {
        $tests = array();
        
        // Test callback URL accessibility
        $callback_url = add_query_arg('wc-api', 'onepay_callback', home_url('/'));
        $tests['callback_url_format'] = filter_var($callback_url, FILTER_VALIDATE_URL) !== false;
        
        // Test callback handler exists
        $tests['callback_handler_exists'] = class_exists('OnePay_Callback');
        
        // Test sample callback data processing (without actual processing)
        $sample_callback = $this->create_sample_callback_data();
        $tests['callback_data_validation'] = $this->validate_callback_structure($sample_callback);
        
        $passed = array_filter($tests);
        
        return array(
            'passed' => count($passed),
            'failed' => count($tests) - count($passed),
            'total' => count($tests),
            'details' => $tests,
            'callback_url' => $callback_url,
            'success' => count($passed) === count($tests)
        );
    }
    
    /**
     * Test order management functionality
     * 
     * @return array Order management test results
     */
    public function test_order_management() {
        $tests = array();
        
        // Test WooCommerce integration
        $tests['woocommerce_orders'] = function_exists('wc_get_orders');
        $tests['order_manager_exists'] = class_exists('OnePay_Order_Manager');
        
        // Test order meta handling
        $tests['order_meta_functions'] = function_exists('update_post_meta') && function_exists('get_post_meta');
        
        // Test currency support
        $current_currency = get_woocommerce_currency();
        $supported_currencies = array('RUB', 'USD', 'EUR');
        $tests['currency_supported'] = in_array($current_currency, $supported_currencies);
        
        $passed = array_filter($tests);
        
        return array(
            'passed' => count($passed),
            'failed' => count($tests) - count($passed),
            'total' => count($tests),
            'details' => $tests,
            'current_currency' => $current_currency,
            'success' => count($passed) === count($tests)
        );
    }
    
    /**
     * Create sample callback data for testing
     * 
     * @return array Sample callback data
     */
    private function create_sample_callback_data() {
        $result_data = json_encode(array(
            'code' => '0000',
            'data' => array(
                'merchantNo' => $this->gateway->merchant_no,
                'merchantOrderNo' => 'TEST_' . time(),
                'orderNo' => 'ONEPAY_TEST_' . time(),
                'orderAmount' => 1000,
                'paidAmount' => 1000,
                'orderFee' => 100,
                'currency' => 'RUB',
                'payModel' => 'FPS',
                'orderStatus' => 'SUCCESS',
                'orderTime' => time() * 1000,
                'finishTime' => time() * 1000
            ),
            'message' => 'Request Success'
        ));
        
        return array(
            'merchantNo' => $this->gateway->merchant_no,
            'result' => $result_data,
            'sign' => 'test_signature'
        );
    }
    
    /**
     * Validate callback data structure
     * 
     * @param array $callback_data Callback data to validate
     * @return bool True if structure is valid
     */
    private function validate_callback_structure($callback_data) {
        $required_fields = array('merchantNo', 'result', 'sign');
        
        foreach ($required_fields as $field) {
            if (!isset($callback_data[$field]) || empty($callback_data[$field])) {
                return false;
            }
        }
        
        $result_data = json_decode($callback_data['result'], true);
        if (!$result_data) {
            return false;
        }
        
        $required_result_fields = array('code', 'data');
        foreach ($required_result_fields as $field) {
            if (!isset($result_data[$field])) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Calculate overall test result
     * 
     * @param array $results All test results
     * @return array Overall result summary
     */
    private function calculate_overall_result($results) {
        $total_passed = 0;
        $total_tests = 0;
        $failed_categories = array();
        
        foreach ($results as $category => $result) {
            if (is_array($result) && isset($result['passed'], $result['total'])) {
                $total_passed += $result['passed'];
                $total_tests += $result['total'];
                
                if (!$result['success']) {
                    $failed_categories[] = $category;
                }
            }
        }
        
        $success_rate = $total_tests > 0 ? ($total_passed / $total_tests) * 100 : 0;
        
        return array(
            'total_passed' => $total_passed,
            'total_tests' => $total_tests,
            'success_rate' => round($success_rate, 2),
            'failed_categories' => $failed_categories,
            'overall_success' => empty($failed_categories),
            'status' => empty($failed_categories) ? 'PASS' : 'FAIL'
        );
    }
    
    /**
     * Generate test report
     * 
     * @param array $results Test results
     * @return string HTML test report
     */
    public function generate_test_report($results) {
        ob_start();
        ?>
        <div class="onepay-test-report">
            <h3>OnePay Plugin Test Report</h3>
            
            <div class="test-summary <?php echo $results['overall']['overall_success'] ? 'success' : 'failure'; ?>">
                <h4>Overall Status: <?php echo $results['overall']['status']; ?></h4>
                <p>
                    Passed: <?php echo $results['overall']['total_passed']; ?>/<?php echo $results['overall']['total_tests']; ?> 
                    (<?php echo $results['overall']['success_rate']; ?>%)
                </p>
            </div>
            
            <?php foreach ($results as $category => $result): ?>
                <?php if ($category === 'overall') continue; ?>
                <div class="test-category <?php echo $result['success'] ? 'success' : 'failure'; ?>">
                    <h4><?php echo ucwords(str_replace('_', ' ', $category)); ?></h4>
                    <p>Passed: <?php echo $result['passed']; ?>/<?php echo $result['total']; ?></p>
                    
                    <?php if (!empty($result['details'])): ?>
                        <ul class="test-details">
                            <?php foreach ($result['details'] as $test => $passed): ?>
                                <li class="<?php echo $passed ? 'pass' : 'fail'; ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $test)); ?>: 
                                    <?php echo $passed ? 'PASS' : 'FAIL'; ?>
                                    <?php if ($test === 'ssl_enabled' && !$passed): ?>
                                        <br><small style="color: #856404;">⚠️ Using HTTP. HTTPS is required for production.</small>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    
                    <?php if ($category === 'api_connection' && isset($result['api_url'])): ?>
                        <p><small>Current API URL: <code><?php echo esc_html($result['api_url']); ?></code></small></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <style>
        .onepay-test-report .success { background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin: 10px 0; }
        .onepay-test-report .failure { background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; margin: 10px 0; }
        .onepay-test-report .pass { color: #155724; }
        .onepay-test-report .fail { color: #721c24; }
        .onepay-test-report ul { list-style: none; }
        .onepay-test-report li { margin: 5px 0; }
        </style>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Clear test logs and temporary data
     */
    public function cleanup_test_data() {
        $this->logger->clear_old_logs(1); // Clear logs older than 1 day
        
        // Clean up any test orders or temporary data
        $test_orders = wc_get_orders(array(
            'meta_key' => '_onepay_test_order',
            'meta_value' => 'yes',
            'limit' => -1
        ));
        
        foreach ($test_orders as $order) {
            if ($order->get_status() === 'pending') {
                $order->delete(true); // Force delete test orders
            }
        }
    }
}