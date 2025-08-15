<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * OnePay Callback Handler Class
 * 
 * Handles payment notification callbacks from OnePay
 * Implements idempotency and proper order status management
 */
class OnePay_Callback {
    
    private $gateway;
    private $logger;
    private $debug_logger;
    
    public function __construct() {
        $this->gateway = new WC_Gateway_OnePay();
        $this->logger = OnePay_Logger::get_instance();
        
        // 加载调试日志器用于详细回调记录
        require_once dirname(__FILE__) . '/class-onepay-debug-logger.php';
        $this->debug_logger = OnePay_Debug_Logger::get_instance();
    }
    
    /**
     * Process payment callback from OnePay
     */
    public function process_callback() {
        $callback_start_time = microtime(true);
        $callback_id = null;
        
        try {
            // 获取原始回调数据和请求信息
            $raw_data = file_get_contents('php://input');
            $request_headers = $this->get_request_headers();
            $client_ip = $this->get_client_ip();
            
            // 第一时间记录回调接收（无论数据是否有效）
            $this->logger->info('异步回调请求开始处理', array(
                'client_ip' => $client_ip,
                'data_length' => strlen($raw_data),
                'content_type' => $_SERVER['CONTENT_TYPE'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ));
            
            // 立即记录原始回调数据
            if (!empty($raw_data)) {
                // 尝试解析JSON以获取基本信息
                $callback_data = json_decode($raw_data, true);
                $callback_id = $this->debug_logger->log_async_callback(
                    $callback_data ?: $raw_data, 
                    null, // 暂时不验签，先记录
                    '回调已接收，开始处理', 
                    $client_ip
                );
            }
            
            if (empty($raw_data)) {
                $error_msg = '接收到空的回调数据';
                $this->logger->error($error_msg);
                if (!$callback_id) {
                    $callback_id = $this->debug_logger->log_async_callback(null, false, $error_msg, $client_ip);
                }
                $this->debug_logger->update_callback_processing_status($callback_id, 'ERROR', $error_msg);
                $this->send_callback_response('ERROR');
                return;
            }
            
            // 解析JSON数据
            if (!$callback_data) {
                $error_msg = 'JSON解析失败：' . json_last_error_msg();
                $this->logger->error($error_msg, array('raw_data' => substr($raw_data, 0, 500)));
                $this->debug_logger->update_callback_processing_status($callback_id, 'ERROR', $error_msg);
                $this->send_callback_response('ERROR');
                return;
            }
            
            // 验证回调数据结构
            if (!$this->validate_callback_data($callback_data)) {
                $error_msg = '回调数据结构验证失败';
                $this->debug_logger->update_callback_processing_status($callback_id, 'ERROR', $error_msg);
                $this->send_callback_response('ERROR');
                return;
            }
            
            // 验证签名
            $signature_valid = $this->verify_callback_signature($callback_data);
            $signature_status = $signature_valid ? 'PASS' : 'FAIL';
            
            // 更新签名验证结果
            if ($callback_id) {
                $this->debug_logger->update_callback_signature_status($callback_id, $signature_valid);
            }
            
            // 解析回调数据
            $result_data = json_decode($callback_data['result'], true);
            $payment_data = null;
            $order = null;
            
            if ($result_data && isset($result_data['data'])) {
                $payment_data = $result_data['data'];
                $onepay_order_no = $payment_data['orderNo'] ?? '';
                $merchant_order_no = $payment_data['merchantOrderNo'] ?? '';
                
                // 查找对应的订单
                $order = $this->find_order_by_onepay_order_no($onepay_order_no);
                if (!$order && $merchant_order_no) {
                    $order = $this->find_order_by_merchant_order_no($merchant_order_no);
                }
                
                // 更新回调记录中的订单信息
                if ($callback_id && $order) {
                    $this->debug_logger->update_callback_order_info($callback_id, $order->get_id());
                }
            }
            
            // 如果验签失败，直接返回错误
            if (!$signature_valid) {
                $error_msg = '签名验证失败';
                $this->logger->error($error_msg);
                $this->send_callback_response('ERROR');
                return;
            }
            
            // 如果没有有效的支付数据，返回错误
            if (!$payment_data) {
                $error_msg = '回调result数据无效或缺少data字段';
                $this->logger->error($error_msg, array('result' => $callback_data['result'] ?? ''));
                $this->send_callback_response('ERROR');
                return;
            }
            
            $onepay_order_no = $payment_data['orderNo'] ?? '';
            $merchant_order_no = $payment_data['merchantOrderNo'] ?? '';
            
            $this->logger->info('处理OnePay异步回调', array(
                'onepay_order_no' => $onepay_order_no,
                'merchant_order_no' => $merchant_order_no,
                'order_status' => $payment_data['orderStatus'] ?? 'UNKNOWN',
                'signature_status' => $signature_status
            ));
            
            // 如果找到订单，处理订单状态
            if ($order) {
                $this->process_payment_status($order, $payment_data, $result_data);
                
                // 更新回调记录的处理状态
                $this->debug_logger->update_callback_processing_status(
                    $callback_id, 
                    'SUCCESS', 
                    '订单状态已更新：' . ($payment_data['orderStatus'] ?? 'UNKNOWN')
                );
                
                $success_msg = '回调处理成功，订单：' . $order->get_id() . '，状态：' . ($payment_data['orderStatus'] ?? 'UNKNOWN');
                $this->logger->info($success_msg);
            } else {
                // 没有找到对应订单，但仍然记录回调
                $warn_msg = '未找到对应订单 - OnePay订单号: ' . $onepay_order_no . ', 商户订单号: ' . $merchant_order_no;
                $this->logger->warning($warn_msg);
                
                $this->debug_logger->update_callback_processing_status(
                    $callback_id, 
                    'WARNING', 
                    $warn_msg
                );
            }
            
            // 发送成功响应
            $this->send_callback_response('SUCCESS');
            
        } catch (Exception $e) {
            $error_msg = '回调处理异常: ' . $e->getMessage();
            $this->logger->error($error_msg);
            
            // 记录异常到回调日志
            if (isset($callback_id)) {
                $this->debug_logger->update_callback_processing_status(
                    $callback_id, 
                    'ERROR', 
                    $error_msg
                );
            } else {
                $this->debug_logger->log_async_callback(
                    isset($callback_data) ? $callback_data : $raw_data, 
                    false, 
                    $error_msg, 
                    $this->get_client_ip()
                );
            }
            
            $this->send_callback_response('ERROR');
        }
    }
    
    /**
     * Validate callback data structure
     * 
     * @param array $callback_data The callback data
     * @return bool True if valid, false otherwise
     */
    private function validate_callback_data($callback_data) {
        $required_fields = array('merchantNo', 'result', 'sign');
        
        foreach ($required_fields as $field) {
            if (!isset($callback_data[$field]) || empty($callback_data[$field])) {
                $this->logger->error('Missing required callback field: ' . $field, array(
                    'received_fields' => array_keys($callback_data)
                ));
                return false;
            }
        }
        
        // Validate merchantNo
        if ($callback_data['merchantNo'] !== $this->gateway->merchant_no) {
            $this->logger->error('Merchant number mismatch', array(
                'received' => $callback_data['merchantNo'],
                'expected' => $this->gateway->merchant_no
            ));
            return false;
        }
        
        return true;
    }
    
    /**
     * Verify callback signature
     * 
     * @param array $callback_data The callback data
     * @return bool True if signature is valid, false otherwise
     */
    private function verify_callback_signature($callback_data) {
        if (empty($this->gateway->platform_public_key)) {
            $this->log('Platform public key not configured, skipping signature verification', 'warning');
            return true; // Allow processing without signature verification if key not configured
        }
        
        $result = $callback_data['result'];
        $signature = $callback_data['sign'];
        
        $signature_valid = OnePay_Signature::verify(
            $result,
            $signature,
            $this->gateway->platform_public_key
        );
        
        if (!$signature_valid) {
            $this->log('Signature verification failed', 'error');
            return false;
        }
        
        $this->log('Signature verification successful');
        return true;
    }
    
    /**
     * Find order by OnePay order number
     * 
     * @param string $onepay_order_no OnePay order number
     * @return WC_Order|false The order object or false if not found
     */
    private function find_order_by_onepay_order_no($onepay_order_no) {
        $orders = wc_get_orders(array(
            'meta_key' => '_onepay_order_no',
            'meta_value' => $onepay_order_no,
            'limit' => 1
        ));
        
        return !empty($orders) ? $orders[0] : false;
    }
    
    /**
     * Find order by merchant order number
     * 
     * @param string $merchant_order_no Merchant order number
     * @return WC_Order|false The order object or false if not found
     */
    private function find_order_by_merchant_order_no($merchant_order_no) {
        // 先尝试直接通过订单ID查找
        if (is_numeric($merchant_order_no)) {
            $order = wc_get_order($merchant_order_no);
            if ($order && $order->get_payment_method() && strpos($order->get_payment_method(), 'onepay') !== false) {
                return $order;
            }
        }
        
        // 尝试通过订单号查找
        $orders = wc_get_orders(array(
            'orderby' => 'id',
            'order' => 'DESC',
            'limit' => 50, // 查找最近50个订单
            'meta_query' => array(
                array(
                    'key' => '_payment_method',
                    'value' => array('onepay', 'onepay_fps', 'onepay_russian_card', 'onepay_cards'),
                    'compare' => 'IN'
                )
            )
        ));
        
        foreach ($orders as $order) {
            // 检查订单号或包含时间戳的订单号
            $order_number = $order->get_order_number();
            if ($order_number === $merchant_order_no || 
                strpos($merchant_order_no, $order_number) !== false) {
                return $order;
            }
            
            // 检查是否是带时间戳的格式 (订单号_时间戳)
            if (strpos($merchant_order_no, '_') !== false) {
                $parts = explode('_', $merchant_order_no);
                if (count($parts) >= 2 && $parts[0] === $order_number) {
                    return $order;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Process payment status update
     * 
     * @param WC_Order $order The WooCommerce order
     * @param array $payment_data Payment data from callback
     * @param array $result_data Full result data
     */
    private function process_payment_status($order, $payment_data, $result_data) {
        $order_status = $payment_data['orderStatus'] ?? 'UNKNOWN';
        $order_id = $order->get_id();
        $onepay_order_no = $payment_data['orderNo'] ?? '';
        
        // 加强幂等性检查 - 防止重复处理
        $last_processed_callback = $order->get_meta('_onepay_last_callback_hash');
        $current_callback_hash = md5(json_encode($payment_data));
        
        if ($last_processed_callback === $current_callback_hash) {
            $this->logger->info('订单 ' . $order_id . ' 的相同回调已处理，跳过重复处理', array(
                'order_status' => $order_status,
                'callback_hash' => $current_callback_hash
            ));
            return;
        }
        
        // 检查终态订单状态，避免状态倒退
        $current_wc_status = $order->get_status();
        if (in_array($current_wc_status, array('completed', 'refunded', 'cancelled')) && 
            $order_status !== 'SUCCESS') {
            $this->logger->warning('订单 ' . $order_id . ' 已处于终态 (' . $current_wc_status . ')，忽略新回调状态: ' . $order_status);
            return;
        }
        
        $this->logger->info('处理订单 ' . $order_id . ' 的状态更新: ' . $order_status);
        
        // 更新订单元数据与回调数据
        $order->update_meta_data('_onepay_callback_data', json_encode($payment_data, JSON_UNESCAPED_UNICODE));
        $order->update_meta_data('_onepay_callback_processed', $order_status);
        $order->update_meta_data('_onepay_last_callback_hash', $current_callback_hash);
        $order->update_meta_data('_onepay_callback_time', current_time('mysql'));
        $order->update_meta_data('_onepay_order_no', $onepay_order_no);
        
        // 记录完整的回调信息
        if (isset($payment_data['merchantOrderNo'])) {
            $order->update_meta_data('_onepay_merchant_order_no', $payment_data['merchantOrderNo']);
        }
        
        // 处理金额信息（从分转换为元）
        if (isset($payment_data['paidAmount'])) {
            $paid_amount = floatval($payment_data['paidAmount']) / 100;
            $order->update_meta_data('_onepay_paid_amount', $paid_amount);
        }
        
        if (isset($payment_data['orderAmount'])) {
            $order_amount = floatval($payment_data['orderAmount']) / 100;
            $order->update_meta_data('_onepay_order_amount', $order_amount);
        }
        
        if (isset($payment_data['orderFee'])) {
            $order_fee = floatval($payment_data['orderFee']) / 100;
            $order->update_meta_data('_onepay_fee', $order_fee);
        }
        
        // 记录支付相关信息
        if (isset($payment_data['currency'])) {
            $order->update_meta_data('_onepay_currency', $payment_data['currency']);
        }
        
        if (isset($payment_data['payModel'])) {
            $order->update_meta_data('_onepay_pay_model', $payment_data['payModel']);
        }
        
        if (isset($payment_data['orderTime'])) {
            $order_time = date('Y-m-d H:i:s', $payment_data['orderTime'] / 1000); // 转换毫秒时间戳
            $order->update_meta_data('_onepay_order_time', $order_time);
        }
        
        if (isset($payment_data['finishTime']) && $payment_data['finishTime'] > 0) {
            $finish_time = date('Y-m-d H:i:s', $payment_data['finishTime'] / 1000);
            $order->update_meta_data('_onepay_finish_time', $finish_time);
        }
        
        // 根据状态处理 (支持全部代收订单状态)
        switch ($order_status) {
            case 'SUCCESS':
                $this->process_successful_payment($order, $payment_data);
                break;
                
            case 'PENDING':
                $this->process_pending_payment($order, $payment_data);
                break;
                
            case 'FAIL':
            case 'FAILED':
                $this->process_failed_payment($order, $payment_data);
                break;
                
            case 'CANCEL':
                $this->process_cancelled_payment($order, $payment_data);
                break;
                
            case 'WAIT3D':
                $this->process_wait3d_payment($order, $payment_data);
                break;
                
            default:
                $this->logger->warning('未知的支付状态: ' . $order_status);
                $order->add_order_note(
                    sprintf(__('OnePay回调收到未知状态: %s', 'onepay'), $order_status)
                );
                // 记录未知状态到调试日志
                $this->debug_logger->log_error('未知订单状态', array(
                    'order_id' => $order->get_id(),
                    'order_status' => $order_status,
                    'payment_data' => $payment_data
                ));
        }
        
        $order->save();
    }
    
    /**
     * 处理国际卡支付回调
     * 
     * @param WC_Order $order 订单
     * @param array $payment_data 支付数据
     */
    private function process_international_card_callback($order, $payment_data) {
        // 记录国际卡特有的信息
        if (isset($payment_data['descriptor'])) {
            $order->update_meta_data('_onepay_descriptor', $payment_data['descriptor']);
        }
        
        if (isset($payment_data['errorMessage'])) {
            $order->update_meta_data('_onepay_error_message', $payment_data['errorMessage']);
        }
        
        // 更新支付模式
        if (isset($payment_data['payModel'])) {
            $order->update_meta_data('_onepay_pay_model', $payment_data['payModel']);
        }
        
        // 记录货币信息
        if (isset($payment_data['currency'])) {
            $order->update_meta_data('_onepay_currency', $payment_data['currency']);
        }
        
        $this->log('处理国际卡支付回调，订单号: ' . $payment_data['orderNo']);
    }
    
    /**
     * Process successful payment
     * 
     * @param WC_Order $order The order
     * @param array $payment_data Payment data
     */
    private function process_successful_payment($order, $payment_data) {
        if ($order->has_status(array('processing', 'completed'))) {
            $this->log('Order ' . $order->get_id() . ' already processed');
            return;
        }
        
        $paid_amount = isset($payment_data['paidAmount']) ? floatval($payment_data['paidAmount']) / 100 : null;
        $order_amount = floatval($order->get_total());
        
        // Verify payment amount
        if ($paid_amount && abs($paid_amount - $order_amount) > 0.01) {
            $this->log('Payment amount mismatch. Expected: ' . $order_amount . ', Received: ' . $paid_amount, 'warning');
            $order->add_order_note(
                sprintf(
                    __('OnePay payment amount mismatch. Expected: %s, Received: %s', 'onepay'),
                    wc_price($order_amount),
                    wc_price($paid_amount)
                )
            );
        }
        
        // Complete payment
        $order->payment_complete($payment_data['orderNo']);
        
        $order->add_order_note(
            sprintf(
                __('OnePay payment completed. Transaction ID: %s, Amount: %s', 'onepay'),
                $payment_data['orderNo'],
                isset($paid_amount) ? wc_price($paid_amount) : wc_price($order_amount)
            )
        );
        
        $this->log('Payment completed for order ' . $order->get_id());
        
        // Trigger action for other plugins
        do_action('onepay_payment_complete', $order, $payment_data);
    }
    
    /**
     * Process pending payment
     * 
     * @param WC_Order $order The order
     * @param array $payment_data Payment data
     */
    private function process_pending_payment($order, $payment_data) {
        if (!$order->has_status('pending')) {
            $order->update_status('pending', __('OnePay payment is pending confirmation.', 'onepay'));
        }
        
        $order->add_order_note(
            sprintf(
                __('OnePay payment pending. Transaction ID: %s', 'onepay'),
                $payment_data['orderNo']
            )
        );
        
        $this->log('Payment pending for order ' . $order->get_id());
    }
    
    /**
     * Process failed payment
     * 
     * @param WC_Order $order The order
     * @param array $payment_data Payment data
     */
    private function process_failed_payment($order, $payment_data) {
        $order->update_status('failed', __('OnePay payment failed.', 'onepay'));
        
        $failure_reason = isset($payment_data['msg']) ? $payment_data['msg'] : __('Payment failed', 'onepay');
        
        $order->add_order_note(
            sprintf(
                __('OnePay payment failed. Transaction ID: %s, Reason: %s', 'onepay'),
                $payment_data['orderNo'],
                $failure_reason
            )
        );
        
        $this->log('Payment failed for order ' . $order->get_id() . ': ' . $failure_reason);
        
        // Trigger action for other plugins
        do_action('onepay_payment_failed', $order, $payment_data);
    }
    
    /**
     * 处理取消的支付
     * 
     * @param WC_Order $order 订单对象
     * @param array $payment_data 支付数据
     */
    private function process_cancelled_payment($order, $payment_data) {
        // 更新订单状态为已取消
        $order->update_status('cancelled', __('OnePay支付已取消（用户未完成收银台操作）', 'onepay'));
        
        $order->add_order_note(
            sprintf(
                __('OnePay支付已取消。交易ID: %s', 'onepay'),
                $payment_data['orderNo']
            )
        );
        
        $this->logger->info('支付已取消，订单: ' . $order->get_id());
        
        // 触发取消支付的动作钩子
        do_action('onepay_payment_cancelled', $order, $payment_data);
    }
    
    /**
     * 处理等待3D验证的支付（国际卡专用）
     * 
     * @param WC_Order $order 订单对象
     * @param array $payment_data 支付数据
     */
    private function process_wait3d_payment($order, $payment_data) {
        // 保持订单为处理中状态，等待3D验证完成
        if (!$order->has_status(array('processing', 'on-hold'))) {
            $order->update_status('on-hold', __('OnePay国际卡支付等待3D验证', 'onepay'));
        }
        
        $order->add_order_note(
            sprintf(
                __('OnePay国际卡支付等待3D验证。交易ID: %s', 'onepay'),
                $payment_data['orderNo']
            )
        );
        
        // 记录3D验证相关的额外信息
        if (isset($payment_data['redirect3DUrl'])) {
            $order->update_meta_data('_onepay_3d_redirect_url', $payment_data['redirect3DUrl']);
        }
        
        if (isset($payment_data['threeDSecureFlow'])) {
            $order->update_meta_data('_onepay_3d_flow', $payment_data['threeDSecureFlow']);
        }
        
        $this->logger->info('国际卡支付等待3D验证，订单: ' . $order->get_id());
        
        // 触发3D验证等待的动作钩子
        do_action('onepay_payment_wait3d', $order, $payment_data);
    }
    
    /**
     * Send callback response to OnePay
     * 
     * @param string $status Response status (SUCCESS or ERROR)
     */
    private function send_callback_response($status) {
        $this->log('Sending callback response: ' . $status);
        
        // Clear any output that might have been generated
        if (ob_get_level()) {
            ob_clean();
        }
        
        // Set appropriate headers
        status_header(200);
        header('Content-Type: text/plain');
        
        // Send the response
        echo $status;
        
        // End execution
        exit;
    }
    
    
    /**
     * Handle synchronous return from OnePay
     * 
     * @param array $params URL parameters
     */
    public function process_return($params) {
        $this->log('Processing return from OnePay');
        $this->log('Return parameters: ' . json_encode($params));
        
        $required_params = array('orderNo', 'orderAmount', 'orderStatus', 'currency', 'merchantOrderNo');
        
        foreach ($required_params as $param) {
            if (!isset($params[$param])) {
                $this->log('Missing return parameter: ' . $param, 'error');
                return false;
            }
        }
        
        // Find order by OnePay order number
        $order = $this->find_order_by_onepay_order_no($params['orderNo']);
        
        if (!$order) {
            $this->log('Order not found for return: ' . $params['orderNo'], 'warning');
            return false;
        }
        
        // Log the return
        $order->add_order_note(
            sprintf(
                __('Customer returned from OnePay. Status: %s', 'onepay'),
                $params['orderStatus']
            )
        );
        
        return $order;
    }
    
    /**
     * 获取客户端IP地址
     * 
     * @return string 客户端IP
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * 获取请求头信息
     * 
     * @return array 请求头数组
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
}