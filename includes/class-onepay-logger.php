<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * OnePay Logger Class
 * 
 * Comprehensive logging and error handling for OnePay plugin
 */
class OnePay_Logger {
    
    const LOG_SOURCE = 'onepay';
    
    private static $instance = null;
    private $logger = null;
    private $debug_enabled = false;
    
    /**
     * Get logger instance
     * 
     * @return OnePay_Logger
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->logger = wc_get_logger();
        
        $gateway = new WC_Gateway_OnePay();
        $this->debug_enabled = $gateway->debug;
    }
    
    /**
     * Log info message
     * 
     * @param string $message Log message
     * @param array $context Additional context
     */
    public function info($message, $context = array()) {
        if ($this->debug_enabled) {
            $this->log('info', $message, $context);
        }
    }
    
    /**
     * Log warning message
     * 
     * @param string $message Log message
     * @param array $context Additional context
     */
    public function warning($message, $context = array()) {
        $this->log('warning', $message, $context);
    }
    
    /**
     * Log error message
     * 
     * @param string $message Log message
     * @param array $context Additional context
     */
    public function error($message, $context = array()) {
        $this->log('error', $message, $context);
        
        // Also log to PHP error log for critical errors
        error_log('OnePay Error: ' . $message);
    }
    
    /**
     * Log critical error
     * 
     * @param string $message Log message
     * @param array $context Additional context
     */
    public function critical($message, $context = array()) {
        $this->log('critical', $message, $context);
        error_log('OnePay Critical: ' . $message);
        
        // Send admin notification for critical errors
        $this->notify_admin_critical_error($message, $context);
    }
    
    /**
     * Log debug message
     * 
     * @param string $message Log message
     * @param array $context Additional context
     */
    public function debug($message, $context = array()) {
        if ($this->debug_enabled) {
            $this->log('debug', $message, $context);
        }
    }
    
    /**
     * Log API request
     * 
     * @param string $url API URL
     * @param array $request_data Request data
     * @param array $response_data Response data
     * @param int $http_code HTTP response code
     */
    public function log_api_request($url, $request_data, $response_data, $http_code) {
        $message = sprintf('API Request to %s (HTTP %d)', $url, $http_code);
        
        $context = array(
            'url' => $url,
            'http_code' => $http_code,
            'request_size' => strlen(json_encode($request_data)),
            'response_size' => is_array($response_data) ? strlen(json_encode($response_data)) : strlen($response_data)
        );
        
        if ($this->debug_enabled) {
            $context['request_data'] = $this->sanitize_sensitive_data($request_data);
            $context['response_data'] = $this->sanitize_sensitive_data($response_data);
        }
        
        if ($http_code >= 200 && $http_code < 300) {
            $this->info($message, $context);
        } elseif ($http_code >= 400) {
            $this->error($message, $context);
        } else {
            $this->warning($message, $context);
        }
    }
    
    /**
     * Log callback processing
     * 
     * @param array $callback_data Callback data
     * @param string $result Processing result
     * @param string $order_id Order ID
     */
    public function log_callback($callback_data, $result, $order_id = null) {
        $message = sprintf('Callback processed with result: %s', $result);
        
        $context = array(
            'result' => $result,
            'order_id' => $order_id,
            'merchant_no' => isset($callback_data['merchantNo']) ? $callback_data['merchantNo'] : 'unknown'
        );
        
        if ($this->debug_enabled && is_array($callback_data)) {
            $context['callback_data'] = $this->sanitize_sensitive_data($callback_data);
        }
        
        if ($result === 'SUCCESS') {
            $this->info($message, $context);
        } else {
            $this->error($message, $context);
        }
    }
    
    /**
     * Log payment processing
     * 
     * @param WC_Order $order Order object
     * @param string $action Action performed
     * @param string $result Result of action
     * @param array $additional_data Additional data
     */
    public function log_payment($order, $action, $result, $additional_data = array()) {
        $message = sprintf('Payment %s for order %d: %s', $action, $order->get_id(), $result);
        
        $context = array_merge(array(
            'order_id' => $order->get_id(),
            'action' => $action,
            'result' => $result,
            'order_status' => $order->get_status(),
            'payment_method' => $order->get_payment_method(),
            'total' => $order->get_total()
        ), $additional_data);
        
        if (strpos($result, 'success') !== false || strpos($result, 'completed') !== false) {
            $this->info($message, $context);
        } else {
            $this->error($message, $context);
        }
    }
    
    /**
     * Log signature operations
     * 
     * @param string $operation Operation type (sign/verify)
     * @param bool $success Whether operation was successful
     * @param string $data_size Size of data being signed/verified
     */
    public function log_signature($operation, $success, $data_size = '') {
        $message = sprintf('Signature %s %s%s', 
            $operation, 
            $success ? 'successful' : 'failed',
            $data_size ? ' (data size: ' . $data_size . ')' : ''
        );
        
        $context = array(
            'operation' => $operation,
            'success' => $success,
            'data_size' => $data_size
        );
        
        if ($success) {
            $this->debug($message, $context);
        } else {
            $this->error($message, $context);
        }
    }
    
    /**
     * Get recent log entries
     * 
     * @param int $limit Number of entries to retrieve
     * @return array Log entries
     */
    public function get_recent_logs($limit = 50) {
        $log_files = $this->get_log_files();
        $entries = array();
        
        // Get log directory path
        $log_dir = '';
        if (defined('WC_LOG_DIR')) {
            $log_dir = WC_LOG_DIR;
        } else {
            $upload_dir = wp_upload_dir();
            $log_dir = $upload_dir['basedir'] . '/wc-logs/';
        }
        
        foreach ($log_files as $log_file) {
            $file_path = $log_dir . $log_file;
            if (file_exists($file_path)) {
                $content = file_get_contents($file_path);
                $lines = explode("\n", $content);
                
                foreach (array_reverse($lines) as $line) {
                    if (empty($line)) continue;
                    
                    $entries[] = $this->parse_log_line($line);
                    
                    if (count($entries) >= $limit) {
                        break 2;
                    }
                }
            }
        }
        
        return array_slice($entries, 0, $limit);
    }
    
    /**
     * Clear old log files
     * 
     * @param int $days_to_keep Number of days to keep logs
     */
    public function clear_old_logs($days_to_keep = 30) {
        $log_files = $this->get_log_files();
        $cutoff_time = time() - ($days_to_keep * 24 * 60 * 60);
        
        // Get log directory path
        $log_dir = '';
        if (defined('WC_LOG_DIR')) {
            $log_dir = WC_LOG_DIR;
        } else {
            $upload_dir = wp_upload_dir();
            $log_dir = $upload_dir['basedir'] . '/wc-logs/';
        }
        
        foreach ($log_files as $log_file) {
            $file_path = $log_dir . $log_file;
            if (file_exists($file_path) && filemtime($file_path) < $cutoff_time) {
                unlink($file_path);
                $this->info('Deleted old log file: ' . $log_file);
            }
        }
    }
    
    /**
     * Export logs for debugging
     * 
     * @param int $days Number of days to export
     * @return string Log content
     */
    public function export_logs($days = 7) {
        $log_files = $this->get_log_files();
        $cutoff_time = time() - ($days * 24 * 60 * 60);
        $export_content = "OnePay Logs Export\n";
        $export_content .= "Generated: " . date('Y-m-d H:i:s') . "\n";
        $export_content .= "Period: Last {$days} days\n\n";
        
        // Get log directory path
        $log_dir = '';
        if (defined('WC_LOG_DIR')) {
            $log_dir = WC_LOG_DIR;
        } else {
            $upload_dir = wp_upload_dir();
            $log_dir = $upload_dir['basedir'] . '/wc-logs/';
        }
        
        foreach ($log_files as $log_file) {
            $file_path = $log_dir . $log_file;
            if (file_exists($file_path) && filemtime($file_path) >= $cutoff_time) {
                $export_content .= "=== {$log_file} ===\n";
                $export_content .= file_get_contents($file_path);
                $export_content .= "\n\n";
            }
        }
        
        return $export_content;
    }
    
    /**
     * Internal log method
     * 
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Log context
     */
    private function log($level, $message, $context = array()) {
        if (!empty($context)) {
            $message .= ' | Context: ' . json_encode($context, JSON_UNESCAPED_SLASHES);
        }
        
        $this->logger->log($level, $message, array('source' => self::LOG_SOURCE));
    }
    
    /**
     * Sanitize sensitive data for logging
     * 
     * @param mixed $data Data to sanitize
     * @return mixed Sanitized data
     */
    private function sanitize_sensitive_data($data) {
        if (is_array($data)) {
            $sanitized = array();
            foreach ($data as $key => $value) {
                if (in_array(strtolower($key), array('sign', 'signature', 'private_key', 'password', 'token'))) {
                    $sanitized[$key] = '[REDACTED]';
                } else {
                    $sanitized[$key] = is_array($value) ? $this->sanitize_sensitive_data($value) : $value;
                }
            }
            return $sanitized;
        }
        
        return $data;
    }
    
    /**
     * Get OnePay log files
     * 
     * @return array Log file names
     */
    private function get_log_files() {
        $log_files = array();
        
        // Get WooCommerce log directory path
        $log_dir = '';
        if (defined('WC_LOG_DIR')) {
            $log_dir = WC_LOG_DIR;
        } else {
            // Fallback to default WooCommerce logs path
            $upload_dir = wp_upload_dir();
            $log_dir = $upload_dir['basedir'] . '/wc-logs/';
        }
        
        if (is_dir($log_dir)) {
            $files = scandir($log_dir);
            foreach ($files as $file) {
                if (strpos($file, self::LOG_SOURCE) !== false && pathinfo($file, PATHINFO_EXTENSION) === 'log') {
                    $log_files[] = $file;
                }
            }
        }
        
        return array_reverse($log_files); // Most recent first
    }
    
    /**
     * Parse log line
     * 
     * @param string $line Log line
     * @return array Parsed log entry
     */
    private function parse_log_line($line) {
        $pattern = '/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+\d{2}:\d{2})\s+(\w+)\s+(.+)$/';
        
        if (preg_match($pattern, $line, $matches)) {
            return array(
                'timestamp' => $matches[1],
                'level' => strtoupper($matches[2]),
                'message' => $matches[3]
            );
        }
        
        return array(
            'timestamp' => date('c'),
            'level' => 'INFO',
            'message' => $line
        );
    }
    
    /**
     * Notify admin of critical errors
     * 
     * @param string $message Error message
     * @param array $context Error context
     */
    private function notify_admin_critical_error($message, $context = array()) {
        // Only send notification once per hour for the same error to avoid spam
        $error_hash = md5($message . serialize($context));
        $last_notification = get_transient('onepay_error_notification_' . $error_hash);
        
        if ($last_notification) {
            return;
        }
        
        set_transient('onepay_error_notification_' . $error_hash, time(), HOUR_IN_SECONDS);
        
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        $subject = sprintf('[%s] OnePay Critical Error', $site_name);
        $body = "A critical error occurred in the OnePay payment gateway:\n\n";
        $body .= "Error: {$message}\n\n";
        
        if (!empty($context)) {
            $body .= "Context:\n" . print_r($context, true) . "\n\n";
        }
        
        $body .= "Time: " . date('Y-m-d H:i:s') . "\n";
        $body .= "Site: " . home_url() . "\n\n";
        $body .= "Please check the OnePay logs for more details.";
        
        wp_mail($admin_email, $subject, $body);
    }
}