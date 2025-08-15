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
    private $detailed_debug; // 超详细调试记录器
    
    public function __construct() {
        $this->gateway = new WC_Gateway_OnePay();
        $this->logger = OnePay_Logger::get_instance();
        
        // 加载调试日志器用于详细回调记录
        require_once dirname(__FILE__) . '/class-onepay-debug-logger.php';
        $this->debug_logger = OnePay_Debug_Logger::get_instance();
        
        // 加载超详细调试记录器
        require_once dirname(__FILE__) . '/class-onepay-detailed-debug-recorder.php';
        $this->detailed_debug = OnePay_Detailed_Debug_Recorder::get_instance();
    }
    
    /**
     * Process payment callback from OnePay
     */
    public function process_callback() {
        // 开始超详细调试记录
        $this->detailed_debug->enter_method(__CLASS__, __FUNCTION__);
        $request_id = $this->detailed_debug->start_request('callback', array(
            'raw_input' => file_get_contents('php://input'),
            'headers' => $this->get_request_headers(),
            'server' => $_SERVER
        ));
        
        $callback_start_time = microtime(true);
        $this->detailed_debug->log_variable('callback_start_time', $callback_start_time, '回调开始时间');
        
        $callback_id = null;
        $this->detailed_debug->log_variable('callback_id', $callback_id, '回调ID初始化');
        
        $this->detailed_debug->log_debug('开始处理OnePay回调请求');
        
        try {
            // 获取原始回调数据和请求信息
            $this->detailed_debug->log_debug('获取原始回调数据');
            $raw_data = file_get_contents('php://input');
            $this->detailed_debug->log_variable('raw_data', $raw_data, '原始POST数据');
            
            $this->detailed_debug->log_debug('获取请求头信息');
            $request_headers = $this->get_request_headers();
            $this->detailed_debug->log_variable('request_headers', $request_headers, '请求头');
            
            $this->detailed_debug->log_debug('获取客户端IP');
            $client_ip = $this->get_client_ip();
            $this->detailed_debug->log_variable('client_ip', $client_ip, '客户端IP地址');
            
            // 第一时间记录回调接收（无论数据是否有效）
            $this->logger->info('异步回调请求开始处理', array(
                'client_ip' => $client_ip,
                'data_length' => strlen($raw_data),
                'content_type' => $_SERVER['CONTENT_TYPE'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
                'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
                'raw_data_preview' => substr($raw_data, 0, 200)
            ));
            
            // 立即记录原始回调数据
            $this->detailed_debug->log_condition('!empty($raw_data)', !empty($raw_data), array(
                'raw_data_length' => strlen($raw_data)
            ));
            
            if (!empty($raw_data)) {
                // 尝试解析JSON以获取基本信息
                $this->detailed_debug->log_debug('尝试解析JSON数据');
                $callback_data = json_decode($raw_data, true);
                $this->detailed_debug->log_variable('callback_data', $callback_data, 'JSON解析结果');
                
                $json_error = json_last_error();
                $json_error_msg = json_last_error_msg();
                $this->detailed_debug->log_variable('json_error', $json_error, 'JSON错误代码');
                $this->detailed_debug->log_variable('json_error_msg', $json_error_msg, 'JSON错误信息');
                $callback_id = $this->debug_logger->log_async_callback(
                    $callback_data ?: $raw_data, 
                    'pending', // 标记为待验签状态，而不是null
                    '回调已接收，开始验签处理', 
                    $client_ip
                );
                
                // 步骤1: 记录回调接收
                $this->debug_logger->add_callback_processing_step(
                    $callback_id, 
                    '01_callback_received', 
                    'success', 
                    array(
                        'raw_data_length' => strlen($raw_data),
                        'client_ip' => $client_ip,
                        'headers' => $request_headers,
                        'method' => $_SERVER['REQUEST_METHOD'] ?? '',
                        'content_type' => $_SERVER['CONTENT_TYPE'] ?? ''
                    )
                );
            }
            
            // 检查原始数据是否为空
            $this->detailed_debug->log_debug('检查原始数据是否为空');
            $data_is_empty = empty($raw_data);
            $this->detailed_debug->log_condition('empty($raw_data)', $data_is_empty, array(
                'raw_data_length' => strlen($raw_data),
                'raw_data_preview' => substr($raw_data, 0, 100)
            ));
            
            if ($data_is_empty) {
                $error_msg = '接收到空的回调数据';
                $this->detailed_debug->log_error($error_msg);
                $this->detailed_debug->log_variable('error_msg', $error_msg);
                
                $this->logger->error($error_msg);
                
                $this->detailed_debug->log_condition('!$callback_id', !$callback_id, array('callback_id' => $callback_id));
                if (!$callback_id) {
                    $this->detailed_debug->log_debug('创建错误回调记录');
                    $callback_id = $this->debug_logger->log_async_callback(null, false, $error_msg, $client_ip);
                    $this->detailed_debug->log_variable('callback_id', $callback_id, '新创建的回调ID');
                }
                
                $this->debug_logger->add_callback_processing_step($callback_id, '01_callback_received', 'error', null, $error_msg);
                $this->debug_logger->update_callback_processing_status($callback_id, 'ERROR', $error_msg);
                
                $this->detailed_debug->log_debug('发送ERROR响应并退出');
                $this->send_callback_response('ERROR');
                $this->detailed_debug->end_request(null, $error_msg);
                $this->detailed_debug->exit_method(__CLASS__, __FUNCTION__, 'ERROR');
                return;
            }
            
            // 步骤2: 解析JSON数据
            $this->detailed_debug->log_debug('检查JSON解析是否成功');
            $json_parse_failed = !$callback_data;
            $this->detailed_debug->log_condition('!$callback_data', $json_parse_failed, array(
                'callback_data' => $callback_data,
                'json_error' => json_last_error(),
                'json_error_msg' => json_last_error_msg()
            ));
            
            if ($json_parse_failed) {
                $error_msg = 'JSON解析失败：' . json_last_error_msg();
                $this->detailed_debug->log_error($error_msg, json_last_error(), array(
                    'raw_data_sample' => substr($raw_data, 0, 500)
                ));
                
                $this->logger->error($error_msg, array('raw_data' => substr($raw_data, 0, 500)));
                $this->debug_logger->add_callback_processing_step(
                    $callback_id, 
                    '02_json_parsing', 
                    'error', 
                    array(
                        'json_error' => json_last_error_msg(),
                        'json_error_code' => json_last_error(),
                        'raw_data_sample' => substr($raw_data, 0, 200)
                    ), 
                    $error_msg
                );
                $this->debug_logger->update_callback_processing_status($callback_id, 'ERROR', $error_msg);
                
                $this->detailed_debug->log_debug('JSON解析失败，发送ERROR响应并退出');
                $this->send_callback_response('ERROR');
                $this->detailed_debug->end_request(null, $error_msg);
                $this->detailed_debug->exit_method(__CLASS__, __FUNCTION__, 'ERROR');
                return;
            }
            
            // JSON解析成功
            $this->debug_logger->add_callback_processing_step(
                $callback_id, 
                '02_json_parsing', 
                'success', 
                array(
                    'parsed_keys' => array_keys($callback_data),
                    'merchant_no' => $callback_data['merchantNo'] ?? null,
                    'has_result' => isset($callback_data['result']),
                    'has_sign' => isset($callback_data['sign'])
                )
            );
            
            // 步骤3: 验证回调数据结构
            $validation_result = $this->validate_callback_data($callback_data);
            if (!$validation_result) {
                $error_msg = '回调数据结构验证失败';
                $this->debug_logger->add_callback_processing_step(
                    $callback_id, 
                    '03_data_validation', 
                    'error', 
                    array(
                        'required_fields' => array('merchantNo', 'result', 'sign'),
                        'received_fields' => array_keys($callback_data),
                        'merchant_no_match' => isset($callback_data['merchantNo']) ? ($callback_data['merchantNo'] === $this->gateway->merchant_no) : false,
                        'configured_merchant_no' => $this->gateway->merchant_no
                    ), 
                    $error_msg
                );
                $this->debug_logger->update_callback_processing_status($callback_id, 'ERROR', $error_msg);
                
                $this->detailed_debug->log_debug('数据验证失败，发送ERROR响应并退出');
                $this->detailed_debug->end_request(null, $error_msg);
                $this->detailed_debug->exit_method(__CLASS__, __FUNCTION__, 'ERROR');
                $this->send_callback_response('ERROR');
                return;
            }
            
            // 数据结构验证成功
            $this->debug_logger->add_callback_processing_step(
                $callback_id, 
                '03_data_validation', 
                'success', 
                array(
                    'merchant_no' => $callback_data['merchantNo'],
                    'has_result' => !empty($callback_data['result']),
                    'has_sign' => !empty($callback_data['sign']),
                    'result_length' => strlen($callback_data['result'] ?? ''),
                    'sign_length' => strlen($callback_data['sign'] ?? '')
                )
            );
            
            // 步骤4: 验证签名
            $this->detailed_debug->log_debug('【主流程】准备调用签名验证方法');
            $signature_start_time = microtime(true);
            $signature_valid = $this->verify_callback_signature($callback_data, $callback_id);
            $signature_end_time = microtime(true);
            $signature_status = $signature_valid ? 'PASS' : 'FAIL';
            
            $this->detailed_debug->log_debug('【主流程】签名验证方法已返回');
            $this->detailed_debug->log_variable('signature_valid_returned', $signature_valid, '签名验证返回结果');
            $this->detailed_debug->log_variable('signature_status', $signature_status, '签名状态');
            
            // 关键诊断点：检查调试记录器是否还在工作
            $this->detailed_debug->log_debug('【诊断】这条记录如果能看到，说明调试记录器在签名验证后仍然工作');
            
            // 强制刷新数据库连接
            global $wpdb;
            if (method_exists($wpdb, 'check_connection')) {
                $wpdb->check_connection();
            }
            $this->detailed_debug->log_debug('【诊断】数据库连接检查完成');
            
            // 更新签名验证结果
            if ($callback_id) {
                $this->debug_logger->update_callback_signature_status($callback_id, $signature_valid);
            }
            
            // 解析回调数据
            $this->detailed_debug->log_debug('解析回调result数据');
            $result_data = json_decode($callback_data['result'], true);
            $payment_data = null;
            $order = null;
            
            $this->detailed_debug->log_variable('result_data', $result_data, 'JSON解析后的result数据');
            
            $has_result_data = !empty($result_data);
            $has_data_field = isset($result_data['data']);
            
            $this->detailed_debug->log_condition('!empty($result_data)', $has_result_data, array(
                'result_data' => $result_data
            ));
            
            $this->detailed_debug->log_condition('isset($result_data["data"])', $has_data_field, array(
                'result_data_keys' => $result_data ? array_keys($result_data) : []
            ));
            
            if ($has_result_data && $has_data_field) {
                $this->detailed_debug->log_debug('提取支付数据');
                $payment_data = $result_data['data'];
                $onepay_order_no = $payment_data['orderNo'] ?? '';
                $merchant_order_no = $payment_data['merchantOrderNo'] ?? '';
                
                $this->detailed_debug->log_variable('payment_data', $payment_data, '支付数据');
                $this->detailed_debug->log_variable('onepay_order_no', $onepay_order_no, 'OnePay订单号');
                $this->detailed_debug->log_variable('merchant_order_no', $merchant_order_no, '商户订单号');
                
                // 步骤5: 查找对应的订单
                $this->detailed_debug->log_debug('开始查找对应订单');
                $order_lookup_start = microtime(true);
                
                $this->detailed_debug->log_debug('首先通过OnePay订单号查找');
                $order = $this->find_order_by_onepay_order_no($onepay_order_no);
                
                $found_by_onepay_no = !empty($order);
                $this->detailed_debug->log_condition('!empty($order)', $found_by_onepay_no, array(
                    'onepay_order_no' => $onepay_order_no,
                    'order_found' => $found_by_onepay_no
                ));
                
                $has_merchant_order_no = !empty($merchant_order_no);
                $this->detailed_debug->log_condition('!$order && $merchant_order_no', !$found_by_onepay_no && $has_merchant_order_no, array(
                    'order_found_by_onepay_no' => $found_by_onepay_no,
                    'has_merchant_order_no' => $has_merchant_order_no,
                    'merchant_order_no' => $merchant_order_no
                ));
                
                if (!$found_by_onepay_no && $has_merchant_order_no) {
                    $this->detailed_debug->log_debug('通过商户订单号查找');
                    $order = $this->find_order_by_merchant_order_no($merchant_order_no);
                    
                    $found_by_merchant_no = !empty($order);
                    $this->detailed_debug->log_condition('!empty($order)', $found_by_merchant_no, array(
                        'merchant_order_no' => $merchant_order_no,
                        'order_found' => $found_by_merchant_no
                    ));
                }
                
                $order_lookup_end = microtime(true);
                $lookup_time = $order_lookup_end - $order_lookup_start;
                
                $this->detailed_debug->log_variable('order_lookup_time', $lookup_time, '订单查找耗时(秒)');
                
                if ($order) {
                    $order_details = array(
                        'order_id' => $order->get_id(),
                        'order_status' => $order->get_status(),
                        'payment_method' => $order->get_payment_method(),
                        'order_total' => $order->get_total()
                    );
                    $this->detailed_debug->log_variable('found_order_details', $order_details, '找到的订单详情');
                } else {
                    $this->detailed_debug->log_debug('未找到对应订单');
                }
                
                $this->debug_logger->add_callback_processing_step(
                    $callback_id, 
                    '05_order_lookup', 
                    $order ? 'success' : 'warning', 
                    array(
                        'onepay_order_no' => $onepay_order_no,
                        'merchant_order_no' => $merchant_order_no,
                        'order_found' => $order ? true : false,
                        'order_id' => $order ? $order->get_id() : null,
                        'order_status' => $order ? $order->get_status() : null,
                        'lookup_time_ms' => round(($order_lookup_end - $order_lookup_start) * 1000, 2)
                    ), 
                    $order ? null : '未找到对应订单'
                );
                
                // 更新回调记录中的订单信息
                if ($callback_id && $order) {
                    $this->debug_logger->update_callback_order_info($callback_id, $order->get_id());
                }
            }
            
            // 如果验签失败，直接返回错误
            $this->detailed_debug->log_debug('检查签名验证结果');
            $this->detailed_debug->log_condition('!$signature_valid', !$signature_valid, array(
                'signature_valid' => $signature_valid,
                'signature_status' => $signature_status
            ));
            
            if (!$signature_valid) {
                $error_msg = '签名验证失败';
                $this->logger->error($error_msg);
                
                if ($callback_id) {
                    $this->debug_logger->add_callback_processing_step(
                        $callback_id, 
                        '99_signature_failed_exit', 
                        'error', 
                        array(
                            'signature_verification_result' => false,
                            'total_processing_time_ms' => round((microtime(true) - $callback_start_time) * 1000, 2)
                        ), 
                        $error_msg
                    );
                    
                    $this->debug_logger->update_callback_processing_status($callback_id, 'ERROR', $error_msg);
                }
                
                $this->detailed_debug->log_debug('签名验证失败，发送ERROR响应并退出');
                $this->detailed_debug->end_request(null, $error_msg);
                $this->detailed_debug->exit_method(__CLASS__, __FUNCTION__, 'ERROR');
                $this->send_callback_response('ERROR');
                return;
            }
            
            // 如果没有有效的支付数据，返回错误
            $this->detailed_debug->log_debug('检查支付数据是否有效');
            $payment_data_valid = !empty($payment_data);
            $this->detailed_debug->log_condition('!empty($payment_data)', $payment_data_valid, array(
                'payment_data' => $payment_data,
                'payment_data_type' => gettype($payment_data)
            ));
            
            if (!$payment_data_valid) {
                $error_msg = '回调result数据无效或缺少data字段';
                $this->logger->error($error_msg, array('result' => $callback_data['result'] ?? ''));
                
                if ($callback_id) {
                    $this->debug_logger->add_callback_processing_step(
                        $callback_id, 
                        '99_invalid_payment_data', 
                        'error', 
                        array(
                            'result_data_exists' => isset($callback_data['result']),
                            'result_content' => substr($callback_data['result'] ?? '', 0, 200),
                            'total_processing_time_ms' => round((microtime(true) - $callback_start_time) * 1000, 2)
                        ), 
                        $error_msg
                    );
                    
                    $this->debug_logger->update_callback_processing_status($callback_id, 'ERROR', $error_msg);
                }
                
                $this->detailed_debug->log_debug('支付数据无效，发送ERROR响应并退出');
                $this->detailed_debug->end_request(null, $error_msg);
                $this->detailed_debug->exit_method(__CLASS__, __FUNCTION__, 'ERROR');
                $this->send_callback_response('ERROR');
                return;
            }
            
            $this->detailed_debug->log_debug('重新获取订单号信息用于日志记录');
            $onepay_order_no = $payment_data['orderNo'] ?? '';
            $merchant_order_no = $payment_data['merchantOrderNo'] ?? '';
            $order_status_from_payment = $payment_data['orderStatus'] ?? 'UNKNOWN';
            
            $this->detailed_debug->log_variable('onepay_order_no_final', $onepay_order_no, '最终的OnePay订单号');
            $this->detailed_debug->log_variable('merchant_order_no_final', $merchant_order_no, '最终的商户订单号');
            $this->detailed_debug->log_variable('order_status_from_payment', $order_status_from_payment, '从支付数据中获取的订单状态');
            
            $callback_info = array(
                'onepay_order_no' => $onepay_order_no,
                'merchant_order_no' => $merchant_order_no,
                'order_status' => $order_status_from_payment,
                'signature_status' => $signature_status
            );
            
            $this->detailed_debug->log_variable('callback_info', $callback_info, '回调信息汇总');
            
            $this->logger->info('处理OnePay异步回调', $callback_info);
            
            // 步骤6: 处理订单状态更新
            $this->detailed_debug->log_debug('开始处理订单状态更新');
            $has_order = !empty($order);
            $this->detailed_debug->log_condition('!empty($order)', $has_order, array(
                'order' => $order ? 'WC_Order Object' : null,
                'order_id' => $order ? $order->get_id() : null
            ));
            
            if ($has_order) {
                $this->detailed_debug->log_debug('记录订单处理开始信息');
                $order_processing_start = microtime(true);
                
                $order_processing_info = array(
                    'order_id' => $order->get_id(),
                    'current_order_status' => $order->get_status(),
                    'new_payment_status' => $payment_data['orderStatus'] ?? 'UNKNOWN',
                    'order_total' => $order->get_total(),
                    'paid_amount' => isset($payment_data['paidAmount']) ? floatval($payment_data['paidAmount']) / 100 : null
                );
                
                $this->detailed_debug->log_variable('order_processing_info', $order_processing_info, '订单处理信息');
                
                $this->debug_logger->add_callback_processing_step(
                    $callback_id, 
                    '06_order_processing', 
                    'info', 
                    $order_processing_info, 
                    null
                );
                
                $this->detailed_debug->log_debug('开始调用process_payment_status处理支付状态');
                $this->process_payment_status($order, $payment_data, $result_data);
                $this->detailed_debug->log_debug('process_payment_status处理完成');
                
                $order_processing_end = microtime(true);
                $processing_time = $order_processing_end - $order_processing_start;
                
                $this->detailed_debug->log_variable('order_processing_time', $processing_time, '订单处理耗时(秒)');
                
                $final_order_status = $order->get_status();
                $processing_result = array(
                    'final_order_status' => $final_order_status,
                    'processing_time_ms' => round($processing_time * 1000, 2),
                    'order_updated' => true
                );
                
                $this->detailed_debug->log_variable('final_order_status', $final_order_status, '最终订单状态');
                $this->detailed_debug->log_variable('processing_result', $processing_result, '订单处理结果');
                
                $this->debug_logger->add_callback_processing_step(
                    $callback_id, 
                    '07_order_processed', 
                    'success', 
                    $processing_result
                );
                
                // 更新回调记录的处理状态
                $status_update_msg = '订单状态已更新：' . ($payment_data['orderStatus'] ?? 'UNKNOWN');
                $this->detailed_debug->log_variable('status_update_msg', $status_update_msg, '状态更新消息');
                
                $this->debug_logger->update_callback_processing_status(
                    $callback_id, 
                    'SUCCESS', 
                    $status_update_msg
                );
                
                $success_msg = '回调处理成功，订单：' . $order->get_id() . '，状态：' . ($payment_data['orderStatus'] ?? 'UNKNOWN');
                $this->detailed_debug->log_variable('success_msg', $success_msg, '成功消息');
                $this->logger->info($success_msg);
            } else {
                // 没有找到对应订单，但仍然记录回调
                $this->detailed_debug->log_debug('没有找到对应订单，记录警告信息');
                $warn_msg = '未找到对应订单 - OnePay订单号: ' . $onepay_order_no . ', 商户订单号: ' . $merchant_order_no;
                $this->detailed_debug->log_variable('warn_msg', $warn_msg, '警告消息');
                $this->logger->warning($warn_msg);
                
                $order_not_found_info = array(
                    'onepay_order_no' => $onepay_order_no,
                    'merchant_order_no' => $merchant_order_no,
                    'order_found' => false
                );
                
                $this->detailed_debug->log_variable('order_not_found_info', $order_not_found_info, '订单未找到信息');
                
                $this->debug_logger->add_callback_processing_step(
                    $callback_id, 
                    '06_order_processing', 
                    'error', 
                    $order_not_found_info, 
                    $warn_msg
                );
                
                $this->debug_logger->update_callback_processing_status(
                    $callback_id, 
                    'WARNING', 
                    $warn_msg
                );
                
                $this->detailed_debug->log_debug('订单未找到的情况已记录');
            }
            
            // 步骤8: 发送响应
            $this->detailed_debug->log_debug('准备发送成功响应');
            $total_processing_time = microtime(true) - $callback_start_time;
            $response_info = array(
                'response_status' => 'SUCCESS',
                'total_processing_time_ms' => round($total_processing_time * 1000, 2)
            );
            
            $this->detailed_debug->log_variable('total_processing_time', $total_processing_time, '总处理耗时(秒)');
            $this->detailed_debug->log_variable('response_info', $response_info, '响应信息');
            
            $this->debug_logger->add_callback_processing_step(
                $callback_id, 
                '08_response_sending', 
                'success', 
                $response_info
            );
            
            // 发送成功响应
            $this->detailed_debug->log_debug('所有回调处理步骤完成，准备发送SUCCESS响应');
            $this->detailed_debug->log_debug('调用end_request结束请求记录');
            $this->detailed_debug->end_request('SUCCESS', null);
            $this->detailed_debug->log_debug('调用exit_method结束方法记录');
            $this->detailed_debug->exit_method(__CLASS__, __FUNCTION__, 'SUCCESS');
            $this->detailed_debug->log_debug('最终调用send_callback_response发送响应');
            $this->send_callback_response('SUCCESS');
            
        } catch (Exception $e) {
            $error_msg = '回调处理异常: ' . $e->getMessage();
            
            // 详细调试记录异常
            $this->detailed_debug->log_error($error_msg, $e->getCode(), array(
                'exception_message' => $e->getMessage(),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
                'exception_trace' => $e->getTraceAsString(),
                'callback_id' => $callback_id ?? null,
                'total_processing_time_ms' => round((microtime(true) - $callback_start_time) * 1000, 2)
            ));
            
            $this->logger->error($error_msg);
            
            // 记录异常到回调日志
            if (isset($callback_id)) {
                $this->debug_logger->add_callback_processing_step(
                    $callback_id, 
                    '99_exception_occurred', 
                    'error', 
                    array(
                        'exception_message' => $e->getMessage(),
                        'exception_file' => $e->getFile(),
                        'exception_line' => $e->getLine(),
                        'total_processing_time_ms' => round((microtime(true) - $callback_start_time) * 1000, 2)
                    ), 
                    $error_msg
                );
                
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
            
            // 确保详细调试正确结束
            $this->detailed_debug->end_request(null, $error_msg);
            $this->detailed_debug->exit_method(__CLASS__, __FUNCTION__, 'EXCEPTION');
            
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
        $this->detailed_debug->enter_method(__CLASS__, __FUNCTION__, array(
            'callback_data_keys' => array_keys($callback_data)
        ));
        
        $required_fields = array('merchantNo', 'result', 'sign');
        $this->detailed_debug->log_variable('required_fields', $required_fields, '必需的字段列表');
        
        foreach ($required_fields as $field) {
            $this->detailed_debug->log_debug("检查字段: {$field}");
            $field_exists = isset($callback_data[$field]);
            $field_not_empty = !empty($callback_data[$field]);
            
            $this->detailed_debug->log_condition("isset(\$callback_data['{$field}'])", $field_exists, array(
                'field' => $field,
                'field_value' => $callback_data[$field] ?? null
            ));
            
            $this->detailed_debug->log_condition("!empty(\$callback_data['{$field}'])", $field_not_empty, array(
                'field' => $field,
                'field_value' => $callback_data[$field] ?? null,
                'field_length' => isset($callback_data[$field]) ? strlen($callback_data[$field]) : 0
            ));
            
            if (!$field_exists || !$field_not_empty) {
                $error_msg = 'Missing required callback field: ' . $field;
                $this->detailed_debug->log_error($error_msg, null, array(
                    'missing_field' => $field,
                    'received_fields' => array_keys($callback_data)
                ));
                
                $this->logger->error($error_msg, array(
                    'received_fields' => array_keys($callback_data)
                ));
                
                $this->detailed_debug->exit_method(__CLASS__, __FUNCTION__, false);
                return false;
            }
        }
        
        // Validate merchantNo
        $this->detailed_debug->log_debug('验证商户号匹配');
        $received_merchant_no = $callback_data['merchantNo'];
        $expected_merchant_no = $this->gateway->merchant_no;
        $merchant_no_matches = ($received_merchant_no === $expected_merchant_no);
        
        $this->detailed_debug->log_variable('received_merchant_no', $received_merchant_no, '接收到的商户号');
        $this->detailed_debug->log_variable('expected_merchant_no', $expected_merchant_no, '期望的商户号');
        
        $this->detailed_debug->log_condition(
            "\$callback_data['merchantNo'] === \$this->gateway->merchant_no", 
            $merchant_no_matches, 
            array(
                'received' => $received_merchant_no,
                'expected' => $expected_merchant_no
            )
        );
        
        if (!$merchant_no_matches) {
            $error_msg = 'Merchant number mismatch';
            $this->detailed_debug->log_error($error_msg, null, array(
                'received' => $received_merchant_no,
                'expected' => $expected_merchant_no
            ));
            
            $this->logger->error($error_msg, array(
                'received' => $received_merchant_no,
                'expected' => $expected_merchant_no
            ));
            
            $this->detailed_debug->exit_method(__CLASS__, __FUNCTION__, false);
            return false;
        }
        
        $this->detailed_debug->log_debug('回调数据结构验证通过');
        $this->detailed_debug->exit_method(__CLASS__, __FUNCTION__, true);
        return true;
    }
    
    /**
     * Verify callback signature
     * 
     * @param array $callback_data The callback data
     * @param int $callback_id Optional callback ID for step tracking
     * @return bool True if signature is valid, false otherwise
     */
    private function verify_callback_signature($callback_data, $callback_id = null) {
        $this->detailed_debug->enter_method(__CLASS__, __FUNCTION__, array(
            'callback_data_keys' => array_keys($callback_data),
            'callback_id' => $callback_id
        ));
        
        $this->detailed_debug->log_debug('收集签名验证所需的数据');
        $platform_public_key_configured = !empty($this->gateway->platform_public_key);
        $this->detailed_debug->log_variable('platform_public_key_configured', $platform_public_key_configured);
        
        $platform_public_key_length = strlen($this->gateway->platform_public_key ?? '');
        $this->detailed_debug->log_variable('platform_public_key_length', $platform_public_key_length);
        
        $result_data = $callback_data['result'] ?? '';
        $this->detailed_debug->log_variable('result_data', $result_data, '待验证的result字段');
        
        $signature_data = $callback_data['sign'] ?? '';
        $this->detailed_debug->log_variable('signature_data', $signature_data, '签名数据');
        
        $step_data = array(
            'platform_public_key_configured' => $platform_public_key_configured,
            'platform_public_key_length' => $platform_public_key_length,
            'result_data' => $result_data,
            'signature_data' => $signature_data,
            'result_length' => strlen($result_data),
            'signature_length' => strlen($signature_data)
        );
        $this->detailed_debug->log_variable('step_data', $step_data, '步骤数据');
        
        $this->detailed_debug->log_debug('检查平台公钥是否已配置');
        $public_key_empty = empty($this->gateway->platform_public_key);
        $this->detailed_debug->log_condition('empty($this->gateway->platform_public_key)', $public_key_empty, array(
            'public_key_length' => strlen($this->gateway->platform_public_key ?? ''),
            'public_key_preview' => substr($this->gateway->platform_public_key ?? '', 0, 50)
        ));
        
        if ($public_key_empty) {
            $warning_msg = '平台公钥未配置，跳过签名验证';
            $this->detailed_debug->log_debug($warning_msg);
            $this->log('Platform public key not configured, skipping signature verification', 'warning');
            
            $this->detailed_debug->log_condition('$callback_id', !empty($callback_id), array('callback_id' => $callback_id));
            if ($callback_id) {
                $this->debug_logger->add_callback_processing_step(
                    $callback_id, 
                    '04_signature_verification', 
                    'warning', 
                    $step_data, 
                    $warning_msg
                );
            }
            
            $this->detailed_debug->log_debug('公钥未配置，返回true（允许处理）');
            $this->detailed_debug->exit_method(__CLASS__, __FUNCTION__, true);
            return true; // Allow processing without signature verification if key not configured
        }
        
        $this->detailed_debug->log_debug('获取签名验证所需的字段');
        $result = $callback_data['result'];
        $signature = $callback_data['sign'];
        
        $this->detailed_debug->log_variable('result', $result, 'result字段内容');
        $this->detailed_debug->log_variable('signature', $signature, 'sign字段内容');
        
        // 记录验签前的详细信息
        $this->detailed_debug->log_debug('准备签名验证参数');
        $step_data['signature_algorithm'] = 'MD5withRSA';
        $step_data['result_content_preview'] = substr($result, 0, 200);
        $step_data['signature_preview'] = substr($signature, 0, 50) . '...';
        
        $this->detailed_debug->log_variable('signature_algorithm', 'MD5withRSA', '签名算法');
        $this->detailed_debug->log_variable('result_preview', substr($result, 0, 200), 'result内容预览');
        $this->detailed_debug->log_variable('signature_preview', substr($signature, 0, 50) . '...', '签名内容预览');
        
        try {
            $this->detailed_debug->log_debug('开始调用OnePay_Signature::verify进行签名验证');
            
            $signature_valid = OnePay_Signature::verify(
                $result,
                $signature,
                $this->gateway->platform_public_key
            );
            
            $this->detailed_debug->log_variable('signature_valid', $signature_valid, '签名验证结果');
            
            $step_data['verification_result'] = $signature_valid;
            $openssl_errors = $this->get_openssl_errors();
            $step_data['openssl_errors'] = $openssl_errors;
            
            $this->detailed_debug->log_variable('openssl_errors', $openssl_errors, 'OpenSSL错误信息');
            
            $this->detailed_debug->log_condition('!$signature_valid', !$signature_valid, array(
                'signature_valid' => $signature_valid,
                'verification_result' => $signature_valid
            ));
            
            if (!$signature_valid) {
                $error_msg = 'Signature verification failed';
                $this->detailed_debug->log_error('签名验证失败', null, array(
                    'signature_valid' => $signature_valid,
                    'openssl_errors' => $openssl_errors,
                    'step_data' => $step_data
                ));
                
                $this->log($error_msg, 'error');
                
                $this->detailed_debug->log_condition('$callback_id', !empty($callback_id), array('callback_id' => $callback_id));
                if ($callback_id) {
                    $this->debug_logger->add_callback_processing_step(
                        $callback_id, 
                        '04_signature_verification', 
                        'error', 
                        $step_data, 
                        '签名验证失败'
                    );
                }
                
                $this->detailed_debug->log_debug('签名验证失败，返回false');
                $this->detailed_debug->exit_method(__CLASS__, __FUNCTION__, false);
                return false;
            }
            
            $success_msg = 'Signature verification successful';
            $this->detailed_debug->log_debug('签名验证成功');
            $this->log($success_msg);
            
            // 关键诊断：检查调试记录器状态
            $this->detailed_debug->log_debug('【诊断】准备记录callback_id条件');
            $this->detailed_debug->log_condition('$callback_id', !empty($callback_id), array('callback_id' => $callback_id));
            
            if ($callback_id) {
                $this->detailed_debug->log_debug('【诊断】准备调用debug_logger->add_callback_processing_step');
                $this->debug_logger->add_callback_processing_step(
                    $callback_id, 
                    '04_signature_verification', 
                    'success', 
                    $step_data
                );
                $this->detailed_debug->log_debug('【诊断】debug_logger->add_callback_processing_step调用完成');
            }
            
            // 关键诊断：测试调试记录器是否还能正常工作
            $this->detailed_debug->log_debug('【诊断】准备退出签名验证方法，测试调试记录器状态');
            $this->detailed_debug->log_variable('method_about_to_return', true, '方法即将返回');
            
            // 强制刷新数据库
            global $wpdb;
            $this->detailed_debug->log_debug('【诊断】检查wpdb状态：' . (is_object($wpdb) ? 'OK' : 'ERROR'));
            
            $this->detailed_debug->log_debug('签名验证成功，返回true');
            $this->detailed_debug->exit_method(__CLASS__, __FUNCTION__, true);
            
            // 添加额外的诊断日志
            $this->detailed_debug->log_debug('【诊断】exit_method调用完成，即将return');
            
            return true;
            
        } catch (Exception $e) {
            $error_msg = '签名验证异常: ' . $e->getMessage();
            $this->detailed_debug->log_error($error_msg, $e->getCode(), array(
                'exception_message' => $e->getMessage(),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
                'exception_trace' => $e->getTraceAsString(),
                'step_data' => $step_data
            ));
            
            $this->log($error_msg, 'error');
            
            $step_data['exception'] = $e->getMessage();
            $step_data['exception_trace'] = $e->getTraceAsString();
            
            $this->detailed_debug->log_condition('$callback_id', !empty($callback_id), array('callback_id' => $callback_id));
            if ($callback_id) {
                $this->debug_logger->add_callback_processing_step(
                    $callback_id, 
                    '04_signature_verification', 
                    'error', 
                    $step_data, 
                    $error_msg
                );
            }
            
            $this->detailed_debug->log_debug('签名验证异常，返回false');
            $this->detailed_debug->exit_method(__CLASS__, __FUNCTION__, false);
            return false;
        }
    }
    
    /**
     * 获取OpenSSL错误信息
     */
    private function get_openssl_errors() {
        $errors = array();
        while ($error = openssl_error_string()) {
            $errors[] = $error;
        }
        return $errors;
    }
    
    /**
     * Find order by OnePay order number
     * 
     * @param string $onepay_order_no OnePay order number
     * @return WC_Order|false The order object or false if not found
     */
    private function find_order_by_onepay_order_no($onepay_order_no) {
        $this->detailed_debug->enter_method(__CLASS__, __FUNCTION__, array(
            'onepay_order_no' => $onepay_order_no
        ));
        
        $this->detailed_debug->log_debug('通过OnePay订单号查找订单');
        $this->detailed_debug->log_variable('search_params', array(
            'meta_key' => '_onepay_order_no',
            'meta_value' => $onepay_order_no,
            'limit' => 1
        ), 'WC查询参数');
        
        $orders = wc_get_orders(array(
            'meta_key' => '_onepay_order_no',
            'meta_value' => $onepay_order_no,
            'limit' => 1
        ));
        
        $orders_found = !empty($orders);
        $orders_count = count($orders);
        
        $this->detailed_debug->log_variable('orders_count', $orders_count, '找到的订单数量');
        $this->detailed_debug->log_condition('!empty($orders)', $orders_found, array(
            'orders_count' => $orders_count,
            'search_key' => '_onepay_order_no',
            'search_value' => $onepay_order_no
        ));
        
        $result = $orders_found ? $orders[0] : false;
        
        if ($result) {
            $order_details = array(
                'order_id' => $result->get_id(),
                'order_status' => $result->get_status(),
                'payment_method' => $result->get_payment_method(),
                'order_total' => $result->get_total()
            );
            $this->detailed_debug->log_variable('found_order', $order_details, '找到的订单详情');
        } else {
            $this->detailed_debug->log_debug('未找到匹配的订单');
        }
        
        $this->detailed_debug->exit_method(__CLASS__, __FUNCTION__, $result ? 'WC_Order Object' : false);
        return $result;
    }
    
    /**
     * Find order by merchant order number
     * 
     * @param string $merchant_order_no Merchant order number
     * @return WC_Order|false The order object or false if not found
     */
    private function find_order_by_merchant_order_no($merchant_order_no) {
        $this->detailed_debug->enter_method(__CLASS__, __FUNCTION__, array(
            'merchant_order_no' => $merchant_order_no
        ));
        
        // 先尝试直接通过订单ID查找
        $this->detailed_debug->log_debug('尝试直接通过订单ID查找');
        $is_numeric_order_no = is_numeric($merchant_order_no);
        $this->detailed_debug->log_condition('is_numeric($merchant_order_no)', $is_numeric_order_no, array(
            'merchant_order_no' => $merchant_order_no,
            'is_numeric' => $is_numeric_order_no
        ));
        
        if ($is_numeric_order_no) {
            $this->detailed_debug->log_debug('商户订单号是数字，尝试直接获取订单');
            $order = wc_get_order($merchant_order_no);
            $order_exists = !empty($order);
            
            $this->detailed_debug->log_condition('!empty($order)', $order_exists, array(
                'order_id' => $merchant_order_no,
                'order_found' => $order_exists
            ));
            
            if ($order_exists) {
                $payment_method = $order->get_payment_method();
                $is_onepay_payment = strpos($payment_method, 'onepay') !== false;
                
                $this->detailed_debug->log_variable('payment_method', $payment_method, '订单支付方式');
                $this->detailed_debug->log_condition('strpos($payment_method, "onepay") !== false', $is_onepay_payment, array(
                    'payment_method' => $payment_method,
                    'is_onepay' => $is_onepay_payment
                ));
                
                if ($is_onepay_payment) {
                    $this->detailed_debug->log_debug('找到匹配的OnePay订单');
                    $this->detailed_debug->exit_method(__CLASS__, __FUNCTION__, 'WC_Order Object');
                    return $order;
                }
            }
        }
        
        // 尝试通过订单号查找
        $this->detailed_debug->log_debug('通过meta查询查找OnePay订单');
        $query_params = array(
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
        );
        
        $this->detailed_debug->log_variable('query_params', $query_params, 'WC查询参数');
        
        $orders = wc_get_orders($query_params);
        $orders_count = count($orders);
        
        $this->detailed_debug->log_variable('orders_count', $orders_count, '找到的OnePay订单总数');
        
        foreach ($orders as $index => $order) {
            $this->detailed_debug->log_debug("检查订单 #{$index}: {$order->get_id()}");
            
            // 检查订单号或包含时间戳的订单号
            $order_number = $order->get_order_number();
            $this->detailed_debug->log_variable('order_number', $order_number, '当前订单号');
            
            $exact_match = ($order_number === $merchant_order_no);
            $partial_match = (strpos($merchant_order_no, $order_number) !== false);
            
            $this->detailed_debug->log_condition('$order_number === $merchant_order_no', $exact_match, array(
                'order_number' => $order_number,
                'merchant_order_no' => $merchant_order_no
            ));
            
            $this->detailed_debug->log_condition('strpos($merchant_order_no, $order_number) !== false', $partial_match, array(
                'merchant_order_no' => $merchant_order_no,
                'order_number' => $order_number
            ));
            
            if ($exact_match || $partial_match) {
                $this->detailed_debug->log_debug('找到匹配的订单（精确或部分匹配）');
                $this->detailed_debug->exit_method(__CLASS__, __FUNCTION__, 'WC_Order Object');
                return $order;
            }
            
            // 检查是否是带时间戳的格式 (订单号_时间戳)
            $has_underscore = (strpos($merchant_order_no, '_') !== false);
            $this->detailed_debug->log_condition('strpos($merchant_order_no, "_") !== false', $has_underscore, array(
                'merchant_order_no' => $merchant_order_no
            ));
            
            if ($has_underscore) {
                $parts = explode('_', $merchant_order_no);
                $parts_count = count($parts);
                $first_part_matches = ($parts_count >= 2 && $parts[0] === $order_number);
                
                $this->detailed_debug->log_variable('parts', $parts, '分割后的订单号部分');
                $this->detailed_debug->log_condition('count($parts) >= 2 && $parts[0] === $order_number', $first_part_matches, array(
                    'parts_count' => $parts_count,
                    'first_part' => $parts[0] ?? '',
                    'order_number' => $order_number
                ));
                
                if ($first_part_matches) {
                    $this->detailed_debug->log_debug('找到匹配的订单（时间戳格式）');
                    $this->detailed_debug->exit_method(__CLASS__, __FUNCTION__, 'WC_Order Object');
                    return $order;
                }
            }
        }
        
        $this->detailed_debug->log_debug('未找到匹配的订单');
        $this->detailed_debug->exit_method(__CLASS__, __FUNCTION__, false);
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
        $this->detailed_debug->enter_method(__CLASS__, __FUNCTION__, array(
            'order_id' => $order->get_id(),
            'payment_data_keys' => array_keys($payment_data),
            'result_data_keys' => array_keys($result_data)
        ));
        
        $order_status = $payment_data['orderStatus'] ?? 'UNKNOWN';
        $order_id = $order->get_id();
        $onepay_order_no = $payment_data['orderNo'] ?? '';
        
        $this->detailed_debug->log_variable('order_status', $order_status, '支付状态');
        $this->detailed_debug->log_variable('order_id', $order_id, '订单ID');
        $this->detailed_debug->log_variable('onepay_order_no', $onepay_order_no, 'OnePay订单号');
        
        // 加强幂等性检查 - 防止重复处理
        $this->detailed_debug->log_debug('开始幂等性检查');
        $last_processed_callback = $order->get_meta('_onepay_last_callback_hash');
        $current_callback_hash = md5(json_encode($payment_data));
        
        $this->detailed_debug->log_variable('last_processed_callback', $last_processed_callback, '上次处理的回调哈希');
        $this->detailed_debug->log_variable('current_callback_hash', $current_callback_hash, '当前回调哈希');
        
        $is_duplicate_callback = ($last_processed_callback === $current_callback_hash);
        $this->detailed_debug->log_condition('$last_processed_callback === $current_callback_hash', $is_duplicate_callback, array(
            'last_hash' => $last_processed_callback,
            'current_hash' => $current_callback_hash,
            'order_id' => $order_id
        ));
        
        if ($is_duplicate_callback) {
            $skip_msg = '订单 ' . $order_id . ' 的相同回调已处理，跳过重复处理';
            $this->detailed_debug->log_debug($skip_msg);
            
            $this->logger->info($skip_msg, array(
                'order_status' => $order_status,
                'callback_hash' => $current_callback_hash
            ));
            
            $this->detailed_debug->exit_method(__CLASS__, __FUNCTION__, 'duplicate_callback_skipped');
            return;
        }
        
        // 检查终态订单状态，避免状态倒退
        $this->detailed_debug->log_debug('检查订单终态状态');
        $current_wc_status = $order->get_status();
        $final_statuses = array('completed', 'refunded', 'cancelled');
        $is_final_status = in_array($current_wc_status, $final_statuses);
        $is_success_callback = ($order_status === 'SUCCESS');
        
        $this->detailed_debug->log_variable('current_wc_status', $current_wc_status, '当前WC订单状态');
        $this->detailed_debug->log_variable('final_statuses', $final_statuses, '终态状态列表');
        $this->detailed_debug->log_condition('in_array($current_wc_status, $final_statuses)', $is_final_status, array(
            'current_status' => $current_wc_status,
            'final_statuses' => $final_statuses
        ));
        $this->detailed_debug->log_condition('$order_status === "SUCCESS"', $is_success_callback, array(
            'order_status' => $order_status
        ));
        
        $should_skip_final_status = ($is_final_status && !$is_success_callback);
        $this->detailed_debug->log_condition('$is_final_status && !$is_success_callback', $should_skip_final_status, array(
            'is_final_status' => $is_final_status,
            'is_success_callback' => $is_success_callback,
            'current_status' => $current_wc_status,
            'new_status' => $order_status
        ));
        
        if ($should_skip_final_status) {
            $warning_msg = '订单 ' . $order_id . ' 已处于终态 (' . $current_wc_status . ')，忽略新回调状态: ' . $order_status;
            $this->detailed_debug->log_debug($warning_msg);
            
            $this->logger->warning($warning_msg);
            
            $this->detailed_debug->exit_method(__CLASS__, __FUNCTION__, 'final_status_skipped');
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
        $this->detailed_debug->log_debug('开始根据状态处理订单');
        $this->detailed_debug->log_variable('order_status_for_switch', $order_status, '用于switch判断的状态');
        
        switch ($order_status) {
            case 'SUCCESS':
                $this->detailed_debug->log_debug('处理成功支付状态');
                $this->process_successful_payment($order, $payment_data);
                break;
                
            case 'PENDING':
                $this->detailed_debug->log_debug('处理待处理支付状态');
                $this->process_pending_payment($order, $payment_data);
                break;
                
            case 'FAIL':
            case 'FAILED':
                $this->detailed_debug->log_debug('处理失败支付状态');
                $this->process_failed_payment($order, $payment_data);
                break;
                
            case 'CANCEL':
                $this->detailed_debug->log_debug('处理取消支付状态');
                $this->process_cancelled_payment($order, $payment_data);
                break;
                
            case 'WAIT3D':
                $this->detailed_debug->log_debug('处理等待3D验证状态');
                $this->process_wait3d_payment($order, $payment_data);
                break;
                
            default:
                $this->detailed_debug->log_debug('处理未知支付状态');
                $unknown_status_msg = '未知的支付状态: ' . $order_status;
                $this->detailed_debug->log_error($unknown_status_msg, null, array(
                    'order_status' => $order_status,
                    'order_id' => $order->get_id(),
                    'payment_data' => $payment_data
                ));
                
                $this->logger->warning($unknown_status_msg);
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
        
        $this->detailed_debug->log_debug('保存订单数据');
        $order->save();
        $this->detailed_debug->log_debug('订单数据保存完成');
        
        $this->detailed_debug->exit_method(__CLASS__, __FUNCTION__, 'status_processed');
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
        $this->detailed_debug->enter_method(__CLASS__, __FUNCTION__, array(
            'order_id' => $order->get_id(),
            'payment_data_keys' => array_keys($payment_data)
        ));
        
        $this->detailed_debug->log_debug('检查订单是否已经处理过');
        $current_status = $order->get_status();
        $processed_statuses = array('processing', 'completed');
        $already_processed = $order->has_status($processed_statuses);
        
        $this->detailed_debug->log_variable('current_status', $current_status, '当前订单状态');
        $this->detailed_debug->log_variable('processed_statuses', $processed_statuses, '已处理状态列表');
        $this->detailed_debug->log_condition('$order->has_status($processed_statuses)', $already_processed, array(
            'current_status' => $current_status,
            'processed_statuses' => $processed_statuses
        ));
        
        if ($already_processed) {
            $skip_msg = 'Order ' . $order->get_id() . ' already processed';
            $this->detailed_debug->log_debug($skip_msg);
            $this->log($skip_msg);
            $this->detailed_debug->exit_method(__CLASS__, __FUNCTION__, 'already_processed');
            return;
        }
        
        $this->detailed_debug->log_debug('验证支付金额');
        $paid_amount = isset($payment_data['paidAmount']) ? floatval($payment_data['paidAmount']) / 100 : null;
        $order_amount = floatval($order->get_total());
        
        $this->detailed_debug->log_variable('paid_amount_raw', $payment_data['paidAmount'] ?? null, '原始支付金额（分）');
        $this->detailed_debug->log_variable('paid_amount', $paid_amount, '转换后支付金额（元）');
        $this->detailed_debug->log_variable('order_amount', $order_amount, '订单金额（元）');
        
        // Verify payment amount
        $has_paid_amount = !empty($paid_amount);
        $this->detailed_debug->log_condition('!empty($paid_amount)', $has_paid_amount, array(
            'paid_amount' => $paid_amount
        ));
        
        if ($has_paid_amount) {
            $amount_difference = abs($paid_amount - $order_amount);
            $amount_mismatch = ($amount_difference > 0.01);
            
            $this->detailed_debug->log_variable('amount_difference', $amount_difference, '金额差异');
            $this->detailed_debug->log_condition('abs($paid_amount - $order_amount) > 0.01', $amount_mismatch, array(
                'paid_amount' => $paid_amount,
                'order_amount' => $order_amount,
                'difference' => $amount_difference,
                'tolerance' => 0.01
            ));
            
            if ($amount_mismatch) {
                $mismatch_msg = 'Payment amount mismatch. Expected: ' . $order_amount . ', Received: ' . $paid_amount;
                $this->detailed_debug->log_error($mismatch_msg, null, array(
                    'expected' => $order_amount,
                    'received' => $paid_amount,
                    'difference' => $amount_difference
                ));
                
                $this->log($mismatch_msg, 'warning');
                $order->add_order_note(
                    sprintf(
                        __('OnePay payment amount mismatch. Expected: %s, Received: %s', 'onepay'),
                        wc_price($order_amount),
                        wc_price($paid_amount)
                    )
                );
            }
        }
        
        // Complete payment
        $this->detailed_debug->log_debug('完成订单支付');
        $transaction_id = $payment_data['orderNo'];
        $this->detailed_debug->log_variable('transaction_id', $transaction_id, '交易ID');
        
        $order->payment_complete($transaction_id);
        $this->detailed_debug->log_debug('调用payment_complete方法完成');
        
        $display_amount = isset($paid_amount) ? wc_price($paid_amount) : wc_price($order_amount);
        $order_note = sprintf(
            __('OnePay payment completed. Transaction ID: %s, Amount: %s', 'onepay'),
            $transaction_id,
            $display_amount
        );
        
        $this->detailed_debug->log_variable('order_note', $order_note, '订单备注');
        $order->add_order_note($order_note);
        
        $completion_msg = 'Payment completed for order ' . $order->get_id();
        $this->detailed_debug->log_debug($completion_msg);
        $this->log($completion_msg);
        
        // Trigger action for other plugins
        $this->detailed_debug->log_debug('触发onepay_payment_complete动作钩子');
        do_action('onepay_payment_complete', $order, $payment_data);
        
        $this->detailed_debug->exit_method(__CLASS__, __FUNCTION__, 'payment_completed');
    }
    
    /**
     * Process pending payment
     * 
     * @param WC_Order $order The order
     * @param array $payment_data Payment data
     */
    private function process_pending_payment($order, $payment_data) {
        $this->detailed_debug->enter_method(__CLASS__, __FUNCTION__, array(
            'order_id' => $order->get_id(),
            'payment_data_keys' => array_keys($payment_data)
        ));
        
        $current_status = $order->get_status();
        $is_pending = $order->has_status('pending');
        
        $this->detailed_debug->log_variable('current_status', $current_status, '当前订单状态');
        $this->detailed_debug->log_condition('!$order->has_status("pending")', !$is_pending, array(
            'current_status' => $current_status,
            'is_pending' => $is_pending
        ));
        
        if (!$is_pending) {
            $this->detailed_debug->log_debug('更新订单状态为pending');
            $status_reason = __('OnePay payment is pending confirmation.', 'onepay');
            $this->detailed_debug->log_variable('status_reason', $status_reason, '状态更新原因');
            $order->update_status('pending', $status_reason);
        }
        
        $transaction_id = $payment_data['orderNo'];
        $order_note = sprintf(
            __('OnePay payment pending. Transaction ID: %s', 'onepay'),
            $transaction_id
        );
        
        $this->detailed_debug->log_variable('transaction_id', $transaction_id, '交易ID');
        $this->detailed_debug->log_variable('order_note', $order_note, '订单备注');
        
        $order->add_order_note($order_note);
        
        $pending_msg = 'Payment pending for order ' . $order->get_id();
        $this->detailed_debug->log_debug($pending_msg);
        $this->log($pending_msg);
        
        $this->detailed_debug->exit_method(__CLASS__, __FUNCTION__, 'pending_processed');
    }
    
    /**
     * Process failed payment
     * 
     * @param WC_Order $order The order
     * @param array $payment_data Payment data
     */
    private function process_failed_payment($order, $payment_data) {
        $this->detailed_debug->enter_method(__CLASS__, __FUNCTION__, array(
            'order_id' => $order->get_id(),
            'payment_data_keys' => array_keys($payment_data)
        ));
        
        $this->detailed_debug->log_debug('更新订单状态为失败');
        $failure_status_reason = __('OnePay payment failed.', 'onepay');
        $this->detailed_debug->log_variable('failure_status_reason', $failure_status_reason, '失败状态原因');
        
        $order->update_status('failed', $failure_status_reason);
        
        $this->detailed_debug->log_debug('获取失败原因');
        $has_failure_msg = isset($payment_data['msg']);
        $failure_reason = $has_failure_msg ? $payment_data['msg'] : __('Payment failed', 'onepay');
        
        $this->detailed_debug->log_condition('isset($payment_data["msg"])', $has_failure_msg, array(
            'msg_field' => $payment_data['msg'] ?? null
        ));
        $this->detailed_debug->log_variable('failure_reason', $failure_reason, '失败原因');
        
        $transaction_id = $payment_data['orderNo'];
        $order_note = sprintf(
            __('OnePay payment failed. Transaction ID: %s, Reason: %s', 'onepay'),
            $transaction_id,
            $failure_reason
        );
        
        $this->detailed_debug->log_variable('transaction_id', $transaction_id, '交易ID');
        $this->detailed_debug->log_variable('order_note', $order_note, '订单备注');
        
        $order->add_order_note($order_note);
        
        $failed_msg = 'Payment failed for order ' . $order->get_id() . ': ' . $failure_reason;
        $this->detailed_debug->log_debug($failed_msg);
        $this->log($failed_msg);
        
        // Trigger action for other plugins
        $this->detailed_debug->log_debug('触发onepay_payment_failed动作钩子');
        do_action('onepay_payment_failed', $order, $payment_data);
        
        $this->detailed_debug->exit_method(__CLASS__, __FUNCTION__, 'payment_failed');
    }
    
    /**
     * 处理取消的支付
     * 
     * @param WC_Order $order 订单对象
     * @param array $payment_data 支付数据
     */
    private function process_cancelled_payment($order, $payment_data) {
        $this->detailed_debug->enter_method(__CLASS__, __FUNCTION__, array(
            'order_id' => $order->get_id(),
            'payment_data_keys' => array_keys($payment_data)
        ));
        
        // 更新订单状态为已取消
        $this->detailed_debug->log_debug('更新订单状态为取消');
        $cancelled_reason = __('OnePay支付已取消（用户未完成收银台操作）', 'onepay');
        $this->detailed_debug->log_variable('cancelled_reason', $cancelled_reason, '取消原因');
        
        $order->update_status('cancelled', $cancelled_reason);
        
        $transaction_id = $payment_data['orderNo'];
        $order_note = sprintf(
            __('OnePay支付已取消。交易ID: %s', 'onepay'),
            $transaction_id
        );
        
        $this->detailed_debug->log_variable('transaction_id', $transaction_id, '交易ID');
        $this->detailed_debug->log_variable('order_note', $order_note, '订单备注');
        
        $order->add_order_note($order_note);
        
        $cancelled_msg = '支付已取消，订单: ' . $order->get_id();
        $this->detailed_debug->log_debug($cancelled_msg);
        $this->logger->info($cancelled_msg);
        
        // 触发取消支付的动作钩子
        $this->detailed_debug->log_debug('触发onepay_payment_cancelled动作钩子');
        do_action('onepay_payment_cancelled', $order, $payment_data);
        
        $this->detailed_debug->exit_method(__CLASS__, __FUNCTION__, 'payment_cancelled');
    }
    
    /**
     * 处理等待3D验证的支付（国际卡专用）
     * 
     * @param WC_Order $order 订单对象
     * @param array $payment_data 支付数据
     */
    private function process_wait3d_payment($order, $payment_data) {
        $this->detailed_debug->enter_method(__CLASS__, __FUNCTION__, array(
            'order_id' => $order->get_id(),
            'payment_data_keys' => array_keys($payment_data)
        ));
        
        // 保持订单为处理中状态，等待3D验证完成
        $this->detailed_debug->log_debug('检查订单是否需要更新状态');
        $wait3d_statuses = array('processing', 'on-hold');
        $has_wait3d_status = $order->has_status($wait3d_statuses);
        
        $this->detailed_debug->log_variable('wait3d_statuses', $wait3d_statuses, '等待3D状态列表');
        $this->detailed_debug->log_condition('!$order->has_status($wait3d_statuses)', !$has_wait3d_status, array(
            'current_status' => $order->get_status(),
            'wait3d_statuses' => $wait3d_statuses
        ));
        
        if (!$has_wait3d_status) {
            $this->detailed_debug->log_debug('更新订单状态为on-hold');
            $wait3d_reason = __('OnePay国际卡支付等待3D验证', 'onepay');
            $this->detailed_debug->log_variable('wait3d_reason', $wait3d_reason, '3D等待原因');
            $order->update_status('on-hold', $wait3d_reason);
        }
        
        $transaction_id = $payment_data['orderNo'];
        $order_note = sprintf(
            __('OnePay国际卡支付等待3D验证。交易ID: %s', 'onepay'),
            $transaction_id
        );
        
        $this->detailed_debug->log_variable('transaction_id', $transaction_id, '交易ID');
        $this->detailed_debug->log_variable('order_note', $order_note, '订单备注');
        
        $order->add_order_note($order_note);
        
        // 记录3D验证相关的额外信息
        $this->detailed_debug->log_debug('处理3D验证相关信息');
        
        $has_3d_redirect = isset($payment_data['redirect3DUrl']);
        $this->detailed_debug->log_condition('isset($payment_data["redirect3DUrl"])', $has_3d_redirect, array(
            'redirect3DUrl' => $payment_data['redirect3DUrl'] ?? null
        ));
        
        if ($has_3d_redirect) {
            $redirect_url = $payment_data['redirect3DUrl'];
            $this->detailed_debug->log_variable('redirect_url', $redirect_url, '3D重定向URL');
            $order->update_meta_data('_onepay_3d_redirect_url', $redirect_url);
        }
        
        $has_3d_flow = isset($payment_data['threeDSecureFlow']);
        $this->detailed_debug->log_condition('isset($payment_data["threeDSecureFlow"])', $has_3d_flow, array(
            'threeDSecureFlow' => $payment_data['threeDSecureFlow'] ?? null
        ));
        
        if ($has_3d_flow) {
            $secure_flow = $payment_data['threeDSecureFlow'];
            $this->detailed_debug->log_variable('secure_flow', $secure_flow, '3D安全流程');
            $order->update_meta_data('_onepay_3d_flow', $secure_flow);
        }
        
        $wait3d_msg = '国际卡支付等待3D验证，订单: ' . $order->get_id();
        $this->detailed_debug->log_debug($wait3d_msg);
        $this->logger->info($wait3d_msg);
        
        // 触发3D验证等待的动作钩子
        $this->detailed_debug->log_debug('触发onepay_payment_wait3d动作钩子');
        do_action('onepay_payment_wait3d', $order, $payment_data);
        
        $this->detailed_debug->exit_method(__CLASS__, __FUNCTION__, 'wait3d_processed');
    }
    
    /**
     * Send callback response to OnePay
     * 
     * @param string $status Response status (SUCCESS or ERROR)
     */
    private function send_callback_response($status) {
        $this->detailed_debug->enter_method(__CLASS__, __FUNCTION__, array(
            'status' => $status
        ));
        
        $log_msg = 'Sending callback response: ' . $status;
        $this->detailed_debug->log_debug($log_msg);
        $this->log($log_msg);
        
        // Clear any output that might have been generated
        $this->detailed_debug->log_debug('检查和清理输出缓冲区');
        $output_buffer_level = ob_get_level();
        $has_output_buffer = ($output_buffer_level > 0);
        
        $this->detailed_debug->log_variable('output_buffer_level', $output_buffer_level, '输出缓冲区层级');
        $this->detailed_debug->log_condition('ob_get_level() > 0', $has_output_buffer, array(
            'buffer_level' => $output_buffer_level
        ));
        
        if ($has_output_buffer) {
            $this->detailed_debug->log_debug('清理输出缓冲区');
            ob_clean();
        }
        
        // Set appropriate headers
        $this->detailed_debug->log_debug('设置响应头');
        $this->detailed_debug->log_variable('http_status', 200, 'HTTP状态码');
        $this->detailed_debug->log_variable('content_type', 'text/plain', '内容类型');
        
        status_header(200);
        header('Content-Type: text/plain');
        
        // Send the response
        $this->detailed_debug->log_debug('发送响应内容');
        $this->detailed_debug->log_variable('response_content', $status, '响应内容');
        
        echo $status;
        
        // End execution
        $this->detailed_debug->log_debug('结束执行');
        $this->detailed_debug->exit_method(__CLASS__, __FUNCTION__, 'response_sent');
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
    
    /**
     * 记录日志信息
     * 
     * @param string $message 日志信息
     * @param string $level 日志级别 (info, warning, error, debug)
     */
    public function log($message, $level = 'info') {
        // 使用网关的调试设置
        if ($this->gateway && $this->gateway->debug) {
            if (empty($this->gateway->logger)) {
                $this->gateway->logger = wc_get_logger();
            }
            $this->gateway->logger->log($level, $message, array('source' => 'onepay-callback'));
        }
        
        // 同时使用OnePay_Logger记录
        if ($this->logger) {
            switch ($level) {
                case 'error':
                    $this->logger->error($message);
                    break;
                case 'warning':
                    $this->logger->warning($message);
                    break;
                case 'debug':
                    $this->logger->debug($message);
                    break;
                case 'info':
                default:
                    $this->logger->info($message);
                    break;
            }
        }
    }
}