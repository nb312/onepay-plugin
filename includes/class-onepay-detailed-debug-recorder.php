<?php
/**
 * OnePay超详细调试记录器
 * 
 * 记录每个方法调用、if判断、变量变化等详细信息
 * 类似本地调试器的功能
 */

if (!defined('ABSPATH')) {
    exit;
}

class OnePay_Detailed_Debug_Recorder {
    
    private static $instance = null;
    private $table_name;
    private $current_session_id;
    private $current_request_id;
    private $call_stack = array();
    private $execution_depth = 0;
    private $start_time;
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
        $this->table_name = $wpdb->prefix . 'onepay_detailed_debug_records';
        $this->current_session_id = uniqid('debug_', true);
        $this->current_request_id = null;
        $this->start_time = microtime(true);
        
        // 检查调试是否启用
        $gateway_settings = get_option('woocommerce_onepay_settings', array());
        $this->debug_enabled = isset($gateway_settings['debug']) && $gateway_settings['debug'] === 'yes';
        
        $this->create_table();
    }
    
    /**
     * 创建调试记录表
     */
    private function create_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id varchar(100) NOT NULL,
            request_id varchar(100) DEFAULT NULL,
            timestamp decimal(16,6) NOT NULL,
            execution_time decimal(10,6) DEFAULT NULL,
            record_type varchar(50) NOT NULL,
            class_name varchar(100) DEFAULT NULL,
            method_name varchar(100) DEFAULT NULL,
            file_path varchar(500) DEFAULT NULL,
            line_number int DEFAULT NULL,
            execution_depth int DEFAULT 0,
            message text,
            variable_data longtext,
            condition_data longtext,
            stack_trace longtext,
            memory_usage bigint DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_session_id (session_id),
            KEY idx_request_id (request_id),
            KEY idx_timestamp (timestamp),
            KEY idx_record_type (record_type),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * 开始新的请求记录
     */
    public function start_request($request_type = 'callback', $request_data = null) {
        if (!$this->debug_enabled) return null;
        
        $this->current_request_id = uniqid('req_', true);
        $this->execution_depth = 0;
        $this->call_stack = array();
        
        $this->record(array(
            'record_type' => 'request_start',
            'message' => "开始处理 {$request_type} 请求",
            'variable_data' => json_encode(array(
                'request_type' => $request_type,
                'request_data' => $request_data,
                'server_info' => array(
                    'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? '',
                    'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? '',
                    'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? '',
                    'CONTENT_TYPE' => $_SERVER['CONTENT_TYPE'] ?? ''
                )
            ), JSON_UNESCAPED_UNICODE)
        ));
        
        return $this->current_request_id;
    }
    
    /**
     * 记录方法进入
     */
    public function enter_method($class_name, $method_name, $parameters = array()) {
        if (!$this->debug_enabled) return;
        
        $this->execution_depth++;
        
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller = isset($backtrace[1]) ? $backtrace[1] : array();
        
        $this->call_stack[] = array(
            'class' => $class_name,
            'method' => $method_name,
            'depth' => $this->execution_depth,
            'start_time' => microtime(true)
        );
        
        $this->record(array(
            'record_type' => 'method_enter',
            'class_name' => $class_name,
            'method_name' => $method_name,
            'file_path' => $caller['file'] ?? '',
            'line_number' => $caller['line'] ?? 0,
            'execution_depth' => $this->execution_depth,
            'message' => "进入方法: {$class_name}::{$method_name}()",
            'variable_data' => json_encode(array(
                'parameters' => $parameters,
                'memory_before' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true)
            ), JSON_UNESCAPED_UNICODE)
        ));
    }
    
    /**
     * 记录方法退出
     */
    public function exit_method($class_name, $method_name, $return_value = null) {
        if (!$this->debug_enabled) return;
        
        $execution_time = null;
        if (!empty($this->call_stack)) {
            $last_call = array_pop($this->call_stack);
            if ($last_call['class'] === $class_name && $last_call['method'] === $method_name) {
                $execution_time = microtime(true) - $last_call['start_time'];
            }
        }
        
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller = isset($backtrace[1]) ? $backtrace[1] : array();
        
        $this->record(array(
            'record_type' => 'method_exit',
            'class_name' => $class_name,
            'method_name' => $method_name,
            'file_path' => $caller['file'] ?? '',
            'line_number' => $caller['line'] ?? 0,
            'execution_depth' => $this->execution_depth,
            'execution_time' => $execution_time,
            'message' => "退出方法: {$class_name}::{$method_name}() [耗时: " . round($execution_time * 1000, 2) . "ms]",
            'variable_data' => json_encode(array(
                'return_value' => $return_value,
                'memory_after' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true)
            ), JSON_UNESCAPED_UNICODE)
        ));
        
        $this->execution_depth--;
    }
    
    /**
     * 记录条件判断
     */
    public function log_condition($condition_text, $condition_result, $variables = array()) {
        if (!$this->debug_enabled) return;
        
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = isset($backtrace[1]) ? $backtrace[1] : array();
        
        $this->record(array(
            'record_type' => 'condition',
            'file_path' => $caller['file'] ?? '',
            'line_number' => $caller['line'] ?? 0,
            'execution_depth' => $this->execution_depth,
            'message' => "条件判断: {$condition_text} = " . ($condition_result ? 'true' : 'false'),
            'condition_data' => json_encode(array(
                'condition' => $condition_text,
                'result' => $condition_result,
                'variables' => $variables
            ), JSON_UNESCAPED_UNICODE)
        ));
    }
    
    /**
     * 记录变量赋值
     */
    public function log_variable($variable_name, $value, $description = '') {
        if (!$this->debug_enabled) return;
        
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = isset($backtrace[1]) ? $backtrace[1] : array();
        
        $value_type = gettype($value);
        $value_preview = $this->format_value_preview($value);
        
        $this->record(array(
            'record_type' => 'variable',
            'file_path' => $caller['file'] ?? '',
            'line_number' => $caller['line'] ?? 0,
            'execution_depth' => $this->execution_depth,
            'message' => "变量赋值: \${$variable_name} = {$value_preview}" . ($description ? " // {$description}" : ''),
            'variable_data' => json_encode(array(
                'name' => $variable_name,
                'type' => $value_type,
                'value' => $value,
                'description' => $description,
                'size' => is_string($value) ? strlen($value) : (is_array($value) ? count($value) : null)
            ), JSON_UNESCAPED_UNICODE)
        ));
    }
    
    /**
     * 记录错误或异常
     */
    public function log_error($error_message, $error_code = null, $context = array()) {
        if (!$this->debug_enabled) return;
        
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = isset($backtrace[1]) ? $backtrace[1] : array();
        
        $this->record(array(
            'record_type' => 'error',
            'file_path' => $caller['file'] ?? '',
            'line_number' => $caller['line'] ?? 0,
            'execution_depth' => $this->execution_depth,
            'message' => "错误: {$error_message}" . ($error_code ? " (代码: {$error_code})" : ''),
            'variable_data' => json_encode(array(
                'error_message' => $error_message,
                'error_code' => $error_code,
                'context' => $context,
                'stack_trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10)
            ), JSON_UNESCAPED_UNICODE)
        ));
    }
    
    /**
     * 记录调试信息
     */
    public function log_debug($message, $data = null) {
        if (!$this->debug_enabled) return;
        
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = isset($backtrace[1]) ? $backtrace[1] : array();
        
        $this->record(array(
            'record_type' => 'debug',
            'file_path' => $caller['file'] ?? '',
            'line_number' => $caller['line'] ?? 0,
            'execution_depth' => $this->execution_depth,
            'message' => $message,
            'variable_data' => $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : null
        ));
    }
    
    /**
     * 记录HTTP请求
     */
    public function log_http_request($url, $method, $headers, $body, $response = null) {
        if (!$this->debug_enabled) return;
        
        $this->record(array(
            'record_type' => 'http_request',
            'execution_depth' => $this->execution_depth,
            'message' => "HTTP请求: {$method} {$url}",
            'variable_data' => json_encode(array(
                'url' => $url,
                'method' => $method,
                'headers' => $headers,
                'body' => $body,
                'response' => $response
            ), JSON_UNESCAPED_UNICODE)
        ));
    }
    
    /**
     * 结束请求记录
     */
    public function end_request($result = null, $error = null) {
        if (!$this->debug_enabled) return;
        
        $total_time = microtime(true) - $this->start_time;
        
        $this->record(array(
            'record_type' => 'request_end',
            'execution_time' => $total_time,
            'message' => "请求处理完成 [总耗时: " . round($total_time * 1000, 2) . "ms]",
            'variable_data' => json_encode(array(
                'result' => $result,
                'error' => $error,
                'total_execution_time' => $total_time,
                'peak_memory' => memory_get_peak_usage(true),
                'final_memory' => memory_get_usage(true)
            ), JSON_UNESCAPED_UNICODE)
        ));
    }
    
    /**
     * 格式化值预览
     */
    private function format_value_preview($value) {
        if (is_null($value)) {
            return 'null';
        } elseif (is_bool($value)) {
            return $value ? 'true' : 'false';
        } elseif (is_string($value)) {
            if (strlen($value) > 50) {
                return '"' . substr($value, 0, 47) . '..."';
            }
            return '"' . $value . '"';
        } elseif (is_array($value)) {
            return 'Array(' . count($value) . ')';
        } elseif (is_object($value)) {
            return get_class($value) . ' Object';
        } else {
            return (string)$value;
        }
    }
    
    /**
     * 核心记录方法
     */
    private function record($data) {
        if (!$this->debug_enabled) {
            return;
        }
        
        try {
            global $wpdb;
            
            // 检查wpdb状态
            if (!is_object($wpdb)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('OnePay详细调试：wpdb不是对象');
                }
                return;
            }
            
            $record = array_merge(array(
                'session_id' => $this->current_session_id,
                'request_id' => $this->current_request_id,
                'timestamp' => microtime(true),
                'memory_usage' => memory_get_usage(true)
            ), $data);
            
            // 增强诊断：记录每次插入操作
            $insert_start = microtime(true);
            $result = $wpdb->insert($this->table_name, $record);
            $insert_end = microtime(true);
            
            // 详细的插入结果诊断
            if ($result === false) {
                $error_msg = 'OnePay详细调试记录插入失败: ' . $wpdb->last_error . 
                           ' | 表名: ' . $this->table_name . 
                           ' | 会话: ' . substr($this->current_session_id, -8) .
                           ' | 记录类型: ' . ($data['record_type'] ?? 'unknown') .
                           ' | 消息: ' . substr($data['message'] ?? '', 0, 100);
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log($error_msg);
                }
                
                // 如果是关键的诊断记录，尝试写入到文件
                if (isset($data['message']) && strpos($data['message'], '【诊断】') !== false) {
                    $debug_file = dirname(dirname(__FILE__)) . '/debug-record-failures.log';
                    $log_entry = '[' . date('Y-m-d H:i:s') . '] ' . $error_msg . "\n";
                    file_put_contents($debug_file, $log_entry, FILE_APPEND | LOCK_EX);
                }
            } else {
                // 成功插入的诊断信息
                $insert_time = ($insert_end - $insert_start) * 1000;
                if ($insert_time > 100) { // 如果插入耗时超过100ms
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('OnePay详细调试：数据库插入耗时 ' . round($insert_time, 2) . 'ms，可能存在性能问题');
                    }
                }
                
                // 对于关键诊断记录，额外记录到文件
                if (isset($data['message']) && strpos($data['message'], '【诊断】') !== false) {
                    $debug_file = dirname(dirname(__FILE__)) . '/debug-record-success.log';
                    $log_entry = '[' . date('Y-m-d H:i:s') . '] 成功记录: ' . $data['message'] . "\n";
                    file_put_contents($debug_file, $log_entry, FILE_APPEND | LOCK_EX);
                }
            }
            
        } catch (Exception $e) {
            $error_msg = 'OnePay详细调试记录异常: ' . $e->getMessage() . 
                        ' | 文件: ' . $e->getFile() . 
                        ' | 行: ' . $e->getLine() .
                        ' | 会话: ' . substr($this->current_session_id, -8);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log($error_msg);
            }
            
            // 异常情况下也写入到文件
            $debug_file = dirname(dirname(__FILE__)) . '/debug-record-exceptions.log';
            $log_entry = '[' . date('Y-m-d H:i:s') . '] ' . $error_msg . "\n";
            file_put_contents($debug_file, $log_entry, FILE_APPEND | LOCK_EX);
        }
    }
    
    /**
     * 获取调试记录
     */
    public function get_records($filters = array()) {
        global $wpdb;
        
        $where_conditions = array('1=1');
        $where_values = array();
        
        if (isset($filters['session_id'])) {
            $where_conditions[] = 'session_id = %s';
            $where_values[] = $filters['session_id'];
        }
        
        if (isset($filters['request_id'])) {
            $where_conditions[] = 'request_id = %s';
            $where_values[] = $filters['request_id'];
        }
        
        if (isset($filters['record_type'])) {
            $where_conditions[] = 'record_type = %s';
            $where_values[] = $filters['record_type'];
        }
        
        if (isset($filters['limit'])) {
            $limit = intval($filters['limit']);
        } else {
            $limit = 1000;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        if (!empty($where_values)) {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY timestamp ASC LIMIT %d",
                array_merge($where_values, array($limit))
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY timestamp ASC LIMIT %d",
                $limit
            );
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * 获取最近的会话列表
     */
    public function get_recent_sessions($limit = 20) {
        global $wpdb;
        
        $sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT session_id, request_id, MIN(created_at) as start_time, MAX(created_at) as end_time, 
                    COUNT(*) as record_count, 
                    SUM(CASE WHEN record_type = 'error' THEN 1 ELSE 0 END) as error_count,
                    (SELECT variable_data FROM {$this->table_name} t2 
                     WHERE t2.session_id = t1.session_id AND t2.record_type = 'request_start' 
                     ORDER BY created_at ASC LIMIT 1) as request_start_data
             FROM {$this->table_name} t1
             GROUP BY session_id, request_id 
             ORDER BY start_time DESC 
             LIMIT %d",
            $limit
        ));
        
        // 解析client_ip从variable_data中
        foreach ($sessions as $session) {
            $session->client_ip = '-';
            if ($session->request_start_data) {
                $start_data = json_decode($session->request_start_data, true);
                if (isset($start_data['server_info']['REMOTE_ADDR'])) {
                    $session->client_ip = $start_data['server_info']['REMOTE_ADDR'];
                }
            }
        }
        
        return $sessions;
    }
    
    /**
     * 清理旧记录
     */
    public function cleanup_old_records($days = 7) {
        global $wpdb;
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
    }
}