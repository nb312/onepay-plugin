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
     * 记录回调
     */
    public function log_callback($callback_data, $order_id = null) {
        if ($this->debug_enabled !== 'yes') {
            return;
        }
        
        $log_data = array(
            'log_type' => 'callback',
            'order_id' => $order_id,
            'user_ip' => $this->get_client_ip(),
            'request_data' => json_encode($callback_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'status' => 'received',
            'extra_data' => json_encode(array(
                'headers' => getallheaders() ?: $_SERVER,
                'method' => $_SERVER['REQUEST_METHOD'] ?? '',
                'timestamp' => current_time('mysql')
            ), JSON_UNESCAPED_UNICODE)
        );
        
        $this->insert_log($log_data);
        $this->write_to_wc_log('callback', $log_data);
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
        
        $wpdb->insert(
            $this->table_name,
            $data,
            array(
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