<?php
/**
 * OnePay调试日志记录器
 * 
 * 详细记录所有支付相关信息，包括：
 * - 用户信息（用户名、IP地址）
 * - 订单信息（订单号、金额、商品）
 * - API请求和响应
 * - 回调数据
 * - 错误信息
 */

if (!defined('ABSPATH')) {
    exit;
}

class OnePay_Debug_Logger {
    
    private static $instance = null;
    private $table_name;
    private $debug_enabled;
    
    /**
     * 获取单例实例
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 构造函数
     */
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'onepay_debug_logs';
        $this->debug_enabled = get_option('woocommerce_onepay_settings')['debug'] ?? 'no';
        
        // 创建数据库表
        $this->create_table();
    }
    
    /**
     * 创建日志表
     */
    private function create_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            log_time datetime DEFAULT CURRENT_TIMESTAMP,
            log_type varchar(50) NOT NULL,
            order_id bigint(20) unsigned DEFAULT NULL,
            order_number varchar(100) DEFAULT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            user_name varchar(100) DEFAULT NULL,
            user_email varchar(100) DEFAULT NULL,
            user_ip varchar(45) DEFAULT NULL,
            amount decimal(10,2) DEFAULT NULL,
            currency varchar(10) DEFAULT NULL,
            payment_method varchar(50) DEFAULT NULL,
            request_url varchar(500) DEFAULT NULL,
            request_data longtext,
            response_data longtext,
            response_code varchar(50) DEFAULT NULL,
            error_message text,
            execution_time float DEFAULT NULL,
            status varchar(50) DEFAULT NULL,
            extra_data longtext,
            PRIMARY KEY (id),
            KEY idx_order_id (order_id),
            KEY idx_user_id (user_id),
            KEY idx_log_time (log_time),
            KEY idx_log_type (log_type),
            KEY idx_status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * 记录支付请求
     */
    public function log_payment_request($order, $payment_data = array()) {
        if ($this->debug_enabled !== 'yes') {
            return;
        }
        
        $user = wp_get_current_user();
        $log_data = array(
            'log_type' => 'payment_request',
            'order_id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'user_id' => $order->get_user_id(),
            'user_name' => $user->display_name ?: $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'user_email' => $order->get_billing_email(),
            'user_ip' => $this->get_client_ip(),
            'amount' => $order->get_total(),
            'currency' => $order->get_currency(),
            'payment_method' => $payment_data['payment_method'] ?? '',
            'request_data' => json_encode($payment_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'status' => 'pending',
            'extra_data' => json_encode(array(
                'items' => $this->get_order_items($order),
                'billing_address' => array(
                    'first_name' => $order->get_billing_first_name(),
                    'last_name' => $order->get_billing_last_name(),
                    'company' => $order->get_billing_company(),
                    'address_1' => $order->get_billing_address_1(),
                    'address_2' => $order->get_billing_address_2(),
                    'city' => $order->get_billing_city(),
                    'state' => $order->get_billing_state(),
                    'postcode' => $order->get_billing_postcode(),
                    'country' => $order->get_billing_country(),
                    'phone' => $order->get_billing_phone()
                ),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'referer' => $_SERVER['HTTP_REFERER'] ?? '',
                'session_id' => session_id() ?: wp_get_session_token()
            ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
        
        $this->insert_log($log_data);
        
        // 同时写入WooCommerce日志
        $this->write_to_wc_log('payment_request', $log_data);
    }
    
    /**
     * 记录API请求
     */
    public function log_api_request($url, $request_data, $order_id = null) {
        if ($this->debug_enabled !== 'yes') {
            return;
        }
        
        $log_data = array(
            'log_type' => 'api_request',
            'order_id' => $order_id,
            'user_ip' => $this->get_client_ip(),
            'request_url' => $url,
            'request_data' => is_array($request_data) || is_object($request_data) ? 
                            json_encode($request_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : 
                            $request_data,
            'status' => 'sent',
            'extra_data' => json_encode(array(
                'timestamp' => current_time('mysql'),
                'headers_sent' => $this->get_request_headers()
            ), JSON_UNESCAPED_UNICODE)
        );
        
        $log_id = $this->insert_log($log_data);
        $this->write_to_wc_log('api_request', $log_data);
        
        return $log_id;
    }
    
    /**
     * 记录API响应
     */
    public function log_api_response($log_id, $response, $execution_time = null) {
        if ($this->debug_enabled !== 'yes' || !$log_id) {
            return;
        }
        
        global $wpdb;
        
        $response_data = is_array($response) || is_object($response) ? 
                        json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : 
                        $response;
        
        $response_code = '';
        $status = 'completed';
        $error_message = '';
        
        // 解析响应状态
        if (is_array($response)) {
            if (isset($response['code'])) {
                $response_code = $response['code'];
                $status = ($response_code === '0000' || $response_code === 'SUCCESS') ? 'success' : 'failed';
            }
            if (isset($response['message'])) {
                $error_message = $response['message'];
            }
        }
        
        $wpdb->update(
            $this->table_name,
            array(
                'response_data' => $response_data,
                'response_code' => $response_code,
                'error_message' => $error_message,
                'execution_time' => $execution_time,
                'status' => $status
            ),
            array('id' => $log_id),
            array('%s', '%s', '%s', '%f', '%s'),
            array('%d')
        );
        
        // 写入WooCommerce日志
        $this->write_to_wc_log('api_response', array(
            'response' => $response_data,
            'execution_time' => $execution_time,
            'status' => $status
        ));
    }
    
    /**
     * 记录回调接收
     */
    public function log_callback_received($raw_data, $client_ip, $headers = array()) {
        if ($this->debug_enabled !== 'yes') {
            return;
        }
        
        $log_data = array(
            'log_type' => 'callback',
            'user_ip' => $client_ip,
            'request_data' => $raw_data,
            'status' => 'received',
            'extra_data' => json_encode(array(
                'headers' => $headers,
                'method' => $_SERVER['REQUEST_METHOD'] ?? '',
                'content_type' => $_SERVER['CONTENT_TYPE'] ?? '',
                'timestamp' => current_time('mysql'),
                'raw_data_length' => strlen($raw_data)
            ), JSON_UNESCAPED_UNICODE)
        );
        
        $log_id = $this->insert_log($log_data);
        $this->write_to_wc_log('callback_received', $log_data);
        
        return $log_id;
    }
    
    /**
     * 记录异步回调到本地数据库
     */
    public function log_async_callback($callback_data, $signature_valid, $message, $client_ip, $order_id = null) {
        if ($this->debug_enabled !== 'yes') {
            return null;
        }
        
        // 解析回调数据
        $payment_data = null;
        $merchant_no = '';
        $onepay_order_no = '';
        $merchant_order_no = '';
        $order_status = '';
        $order_amount = 0;
        $paid_amount = 0;
        $order_fee = 0;
        $currency = '';
        $pay_model = '';
        $pay_type = '';
        $order_time = '';
        $finish_time = '';
        $remark = '';
        
        // 处理不同格式的回调数据
        if (is_array($callback_data)) {
            $merchant_no = $callback_data['merchantNo'] ?? '';
            
            if (isset($callback_data['result'])) {
                $result_data = json_decode($callback_data['result'], true);
                if ($result_data && isset($result_data['data'])) {
                    $payment_data = $result_data['data'];
                }
            }
        } elseif (is_string($callback_data)) {
            // 如果是字符串，尝试解析JSON
            $parsed_data = json_decode($callback_data, true);
            if ($parsed_data) {
                $callback_data = $parsed_data;
                $merchant_no = $callback_data['merchantNo'] ?? '';
                
                if (isset($callback_data['result'])) {
                    $result_data = json_decode($callback_data['result'], true);
                    if ($result_data && isset($result_data['data'])) {
                        $payment_data = $result_data['data'];
                    }
                }
            }
        }
        
        // 从payment_data中提取字段
        if ($payment_data) {
            $onepay_order_no = $payment_data['orderNo'] ?? '';
            $merchant_order_no = $payment_data['merchantOrderNo'] ?? '';
            $order_status = $payment_data['orderStatus'] ?? '';
            $currency = $payment_data['currency'] ?? '';
            $pay_model = $payment_data['payModel'] ?? '';
            $pay_type = $payment_data['payType'] ?? '';
            $remark = $payment_data['remark'] ?? '';
            $msg = $payment_data['msg'] ?? '';
            
            // 金额处理（从分转换为元）
            if (isset($payment_data['orderAmount'])) {
                $order_amount = floatval($payment_data['orderAmount']) / 100;
            }
            if (isset($payment_data['paidAmount'])) {
                $paid_amount = floatval($payment_data['paidAmount']) / 100;
            }
            if (isset($payment_data['orderFee'])) {
                $order_fee = floatval($payment_data['orderFee']) / 100;
            }
            
            // 时间处理
            if (isset($payment_data['orderTime']) && $payment_data['orderTime'] > 0) {
                $order_time = date('Y-m-d H:i:s', $payment_data['orderTime'] / 1000);
            }
            if (isset($payment_data['finishTime']) && $payment_data['finishTime'] > 0) {
                $finish_time = date('Y-m-d H:i:s', $payment_data['finishTime'] / 1000);
            }
        }
        
        // 构造日志数据
        $log_data = array(
            'log_type' => 'async_callback',
            'order_id' => $order_id,
            'order_number' => $onepay_order_no,
            'user_id' => null,
            'user_name' => null,
            'user_email' => null,
            'user_ip' => $client_ip,
            'amount' => $paid_amount ?: $order_amount,
            'currency' => $currency,
            'payment_method' => $pay_model,
            'request_url' => $_SERVER['REQUEST_URI'] ?? '',
            'request_data' => is_array($callback_data) ? json_encode($callback_data, JSON_UNESCAPED_UNICODE) : $callback_data,
            'response_data' => json_encode(array('signature_valid' => $signature_valid, 'message' => $message), JSON_UNESCAPED_UNICODE),
            'response_code' => $order_status,
            'error_message' => $signature_valid ? null : $message,
            'execution_time' => null,
            'status' => $signature_valid ? 'received' : 'signature_failed',
            'extra_data' => json_encode(array(
                'merchant_no' => $merchant_no,
                'merchant_order_no' => $merchant_order_no,
                'onepay_order_no' => $onepay_order_no,
                'order_status' => $order_status,
                'order_amount' => $order_amount,
                'paid_amount' => $paid_amount,
                'order_fee' => $order_fee,
                'pay_model' => $pay_model,
                'pay_type' => $pay_type,
                'order_time' => $order_time,
                'finish_time' => $finish_time,
                'remark' => $remark,
                'msg' => $msg,
                'signature_valid' => $signature_valid,
                'signature_status' => $signature_valid ? 'PASS' : 'FAIL',
                'processing_status' => 'PENDING',
                'received_at' => current_time('mysql')
            ), JSON_UNESCAPED_UNICODE)
        );
        
        $log_id = $this->insert_log($log_data);
        
        // 同时写入WooCommerce日志
        $this->write_to_wc_log('async_callback', array(
            'signature_valid' => $signature_valid,
            'order_no' => $onepay_order_no,
            'merchant_order_no' => $merchant_order_no,
            'order_status' => $order_status,
            'amount' => $paid_amount ?: $order_amount,
            'message' => $message
        ));
        
        return $log_id;
    }
    
    /**
     * 更新回调处理状态
     */
    public function update_callback_processing_status($callback_id, $status, $message) {
        if ($this->debug_enabled !== 'yes' || !$callback_id) {
            return;
        }
        
        global $wpdb;
        
        // 获取当前的extra_data
        $current_data = $wpdb->get_var($wpdb->prepare(
            "SELECT extra_data FROM {$this->table_name} WHERE id = %d",
            $callback_id
        ));
        
        $extra_data = $current_data ? json_decode($current_data, true) : array();
        if (!$extra_data) $extra_data = array();
        
        // 更新处理状态
        $extra_data['processing_status'] = $status;
        $extra_data['processing_message'] = $message;
        $extra_data['processed_at'] = current_time('mysql');
        
        // 更新数据库
        $wpdb->update(
            $this->table_name,
            array(
                'status' => strtolower($status),
                'error_message' => ($status === 'ERROR') ? $message : null,
                'extra_data' => json_encode($extra_data, JSON_UNESCAPED_UNICODE)
            ),
            array('id' => $callback_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * 更新回调签名验证状态
     */
    public function update_callback_signature_status($callback_id, $signature_valid) {
        if ($this->debug_enabled !== 'yes' || !$callback_id) {
            return;
        }
        
        global $wpdb;
        
        // 获取当前的extra_data
        $current_data = $wpdb->get_var($wpdb->prepare(
            "SELECT extra_data FROM {$this->table_name} WHERE id = %d",
            $callback_id
        ));
        
        $extra_data = $current_data ? json_decode($current_data, true) : array();
        if (!$extra_data) $extra_data = array();
        
        // 更新签名状态
        $extra_data['signature_valid'] = $signature_valid;
        $extra_data['signature_status'] = $signature_valid ? 'PASS' : 'FAIL';
        $extra_data['signature_checked_at'] = current_time('mysql');
        
        // 更新数据库状态
        $new_status = $signature_valid ? 'received' : 'signature_failed';
        
        $wpdb->update(
            $this->table_name,
            array(
                'status' => $new_status,
                'error_message' => $signature_valid ? null : '签名验证失败',
                'extra_data' => json_encode($extra_data, JSON_UNESCAPED_UNICODE)
            ),
            array('id' => $callback_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * 更新回调订单信息
     */
    public function update_callback_order_info($callback_id, $order_id) {
        if ($this->debug_enabled !== 'yes' || !$callback_id) {
            return;
        }
        
        global $wpdb;
        
        // 直接更新order_id字段
        $wpdb->update(
            $this->table_name,
            array('order_id' => $order_id),
            array('id' => $callback_id),
            array('%d'),
            array('%d')
        );
    }
    
    /**
     * 记录回调处理结果（保持向后兼容）
     */
    public function log_callback_processed($callback_data, $result, $message, $execution_time = null, $order_id = null) {
        if ($this->debug_enabled !== 'yes') {
            return;
        }
        
        // 解析订单信息（根据实际回调格式）
        $order_number = null;
        $order_status = null;
        $amount = null;
        $currency = null;
        $merchant_order_no = null;
        $paid_amount = null;
        $order_fee = null;
        $payment_data = array(); // 初始化，确保变量可用
        
        if ($callback_data && isset($callback_data['result'])) {
            $result_data = json_decode($callback_data['result'], true);
            if ($result_data && isset($result_data['data'])) {
                $payment_data = $result_data['data'];
                $order_number = $payment_data['orderNo'] ?? null;
                $merchant_order_no = $payment_data['merchantOrderNo'] ?? null;
                $order_status = $payment_data['orderStatus'] ?? null;
                $currency = $payment_data['currency'] ?? null;
                
                // 订单金额和实际支付金额（从分转换为元）
                $amount = isset($payment_data['orderAmount']) ? floatval($payment_data['orderAmount']) / 100 : null;
                $paid_amount = isset($payment_data['paidAmount']) ? floatval($payment_data['paidAmount']) / 100 : null;
                $order_fee = isset($payment_data['orderFee']) ? floatval($payment_data['orderFee']) / 100 : null;
                
                // 优先使用实际支付金额，没有则使用订单金额
                if ($paid_amount !== null) {
                    $amount = $paid_amount;
                }
            }
        }
        
        // 构造完整的日志数据，确保所有字段都匹配数据库结构
        $log_data = array(
            'log_type' => 'callback',
            'order_id' => $order_id,
            'order_number' => $order_number, // OnePay订单号
            'user_id' => null, // 回调时通常没有用户上下文
            'user_name' => null,
            'user_email' => null,
            'user_ip' => $this->get_client_ip(),
            'amount' => $amount, // 已转换为元的金额
            'currency' => $currency,
            'payment_method' => null, // 回调中没有直接的支付方式信息
            'request_url' => null, // 这是接收回调的URL，不是请求URL
            'request_data' => $callback_data ? json_encode($callback_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : null,
            'response_data' => json_encode(array('result' => $result, 'message' => $message), JSON_UNESCAPED_UNICODE),
            'response_code' => $order_status, // 使用订单状态作为响应码
            'error_message' => $result === 'ERROR' ? $message : null,
            'execution_time' => $execution_time,
            'status' => strtolower($result),
            'extra_data' => json_encode(array(
                'order_status' => $order_status,
                'merchant_order_no' => $merchant_order_no,
                'paid_amount' => $paid_amount,
                'order_fee' => $order_fee,
                'original_order_amount' => isset($payment_data['orderAmount']) ? floatval($payment_data['orderAmount']) / 100 : null,
                'pay_model' => $payment_data['payModel'] ?? null,
                'order_time' => isset($payment_data['orderTime']) ? date('Y-m-d H:i:s', $payment_data['orderTime'] / 1000) : null,
                'finish_time' => isset($payment_data['finishTime']) && $payment_data['finishTime'] > 0 ? date('Y-m-d H:i:s', $payment_data['finishTime'] / 1000) : null,
                'timestamp' => current_time('mysql'),
                'callback_processed_at' => current_time('mysql', 1), // GMT时间
                'beijing_time' => date('Y-m-d H:i:s', current_time('timestamp') + 8 * 3600) // 北京时间
            ), JSON_UNESCAPED_UNICODE)
        );
        
        $this->insert_log($log_data);
        $this->write_to_wc_log('callback_processed', $log_data);
    }
    
    /**
     * 记录回调（兼容旧版本）
     */
    public function log_callback($callback_data, $order_id = null) {
        return $this->log_callback_received(
            is_array($callback_data) ? json_encode($callback_data) : $callback_data,
            $this->get_client_ip(),
            getallheaders() ?: $_SERVER
        );
    }
    
    /**
     * 记录错误
     */
    public function log_error($error_message, $context = array()) {
        if ($this->debug_enabled !== 'yes') {
            return;
        }
        
        $log_data = array(
            'log_type' => 'error',
            'order_id' => $context['order_id'] ?? null,
            'user_ip' => $this->get_client_ip(),
            'error_message' => $error_message,
            'status' => 'error',
            'extra_data' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
        
        $this->insert_log($log_data);
        $this->write_to_wc_log('error', $log_data);
    }
    
    /**
     * 插入日志记录
     */
    private function insert_log($data) {
        global $wpdb;
        
        // 确保明确设置北京时间
        $beijing_time = date('Y-m-d H:i:s', current_time('timestamp') + 8 * 3600);
        $data['log_time'] = $beijing_time;
        
        $wpdb->insert(
            $this->table_name,
            $data,
            array(
                '%s', // log_time - 显式设置北京时间
                '%s', // log_type
                '%d', // order_id
                '%s', // order_number
                '%d', // user_id
                '%s', // user_name
                '%s', // user_email
                '%s', // user_ip
                '%f', // amount
                '%s', // currency
                '%s', // payment_method
                '%s', // request_url
                '%s', // request_data
                '%s', // response_data
                '%s', // response_code
                '%s', // error_message
                '%f', // execution_time
                '%s', // status
                '%s'  // extra_data
            )
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * 获取客户端IP
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, 
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * 获取订单商品信息
     */
    private function get_order_items($order) {
        $items = array();
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $items[] = array(
                'product_id' => $item->get_product_id(),
                'product_name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'price' => $product ? $product->get_price() : 0,
                'total' => $item->get_total(),
                'sku' => $product ? $product->get_sku() : ''
            );
        }
        return $items;
    }
    
    /**
     * 获取请求头
     */
    private function get_request_headers() {
        $headers = array();
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        } else {
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }
        }
        return $headers;
    }
    
    /**
     * 写入WooCommerce日志
     */
    private function write_to_wc_log($type, $data) {
        $logger = wc_get_logger();
        $context = array('source' => 'onepay-debug');
        
        $message = sprintf(
            "[%s] %s\n%s",
            strtoupper($type),
            current_time('mysql'),
            print_r($data, true)
        );
        
        $logger->debug($message, $context);
    }
    
    /**
     * 获取日志记录
     */
    public function get_logs($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'limit' => 100,
            'offset' => 0,
            'order_by' => 'log_time',
            'order' => 'DESC',
            'log_type' => '',
            'status' => '',
            'order_id' => 0,
            'date_from' => '',
            'date_to' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        
        if (!empty($args['log_type'])) {
            $where[] = $wpdb->prepare("log_type = %s", $args['log_type']);
        }
        
        if (!empty($args['status'])) {
            $where[] = $wpdb->prepare("status = %s", $args['status']);
        }
        
        if (!empty($args['order_id'])) {
            $where[] = $wpdb->prepare("order_id = %d", $args['order_id']);
        }
        
        if (!empty($args['date_from'])) {
            $where[] = $wpdb->prepare("log_time >= %s", $args['date_from']);
        }
        
        if (!empty($args['date_to'])) {
            $where[] = $wpdb->prepare("log_time <= %s", $args['date_to']);
        }
        
        $where_clause = implode(' AND ', $where);
        
        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
            WHERE {$where_clause} 
            ORDER BY {$args['order_by']} {$args['order']} 
            LIMIT %d OFFSET %d",
            $args['limit'],
            $args['offset']
        );
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * 清理旧日志
     */
    public function cleanup_old_logs($days = 30) {
        global $wpdb;
        
        $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE log_time < %s",
                $date
            )
        );
    }
}