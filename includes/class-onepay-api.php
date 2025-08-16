<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * OnePay API Handler Class
 * 
 * Handles communication with OnePay API endpoints
 */
class OnePay_API {
    
    private $gateway;
    private $logger;
    
    public function __construct() {
        $this->gateway = new WC_Gateway_OnePay();
        $this->logger = OnePay_Logger::get_instance();
    }
    
    /**
     * Create payment request to OnePay API
     * 
     * @param WC_Order $order The WooCommerce order
     * @param string $payment_method The payment method (FPS or CARDPAYMENT)
     * @return array Response array with success status and data
     */
    public function create_payment_request($order, $payment_method) {
        try {
            // 记录请求开始
            $this->logger->info('开始创建支付请求', array(
                'order_id' => $order->get_id(),
                'payment_method' => $payment_method,
                'order_total' => $order->get_total(),
                'currency' => get_woocommerce_currency()
            ));
            
            $request_data = $this->build_payment_request($order, $payment_method);
            $response = $this->send_request($request_data);
            
            // 详细记录响应
            $this->logger->info('API原始响应', array(
                'response_type' => gettype($response),
                'response_empty' => empty($response),
                'has_result' => isset($response['result']),
                'response_keys' => $response ? array_keys($response) : null,
                'response_data' => $response
            ));
            
            // 检查响应是否为空
            if (empty($response)) {
                $this->logger->error('API响应为空');
                return array(
                    'success' => false,
                    'message' => __('API服务器无响应，请检查网络连接', 'onepay'),
                    'debug_info' => 'Empty response from API'
                );
            }
            
            // 检查是否有result字段
            if (!isset($response['result'])) {
                $this->logger->error('API响应缺少result字段', array(
                    'response' => $response
                ));
                return array(
                    'success' => false,
                    'message' => __('API响应格式错误，缺少必要字段', 'onepay'),
                    'debug_info' => 'Missing result field in response',
                    'raw_response' => json_encode($response)
                );
            }
            
            // 尝试解析result
            $result_data = json_decode($response['result'], true);
            
            if ($result_data === null && json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('无法解析result字段', array(
                    'json_error' => json_last_error_msg(),
                    'result_field' => substr($response['result'], 0, 500)
                ));
                return array(
                    'success' => false,
                    'message' => __('API响应解析失败', 'onepay'),
                    'debug_info' => 'JSON parse error: ' . json_last_error_msg()
                );
            }
            
            // 记录解析后的结果
            $this->logger->info('解析后的result数据', array(
                'code' => isset($result_data['code']) ? $result_data['code'] : 'N/A',
                'message' => isset($result_data['message']) ? $result_data['message'] : 'N/A',
                'has_data' => isset($result_data['data'])
            ));
            
            // 检查响应代码
            if ($result_data && isset($result_data['code'])) {
                if ($result_data['code'] === '0000') {
                    // 成功响应
                    if (!isset($result_data['data'])) {
                        $this->logger->warning('成功响应但缺少data字段', array(
                            'result_data' => $result_data
                        ));
                    }
                    
                    return array(
                        'success' => true,
                        'data' => isset($result_data['data']) ? $result_data['data'] : array(),
                        'message' => isset($result_data['message']) ? $result_data['message'] : 'Success'
                    );
                } else {
                    // 业务错误
                    $error_message = isset($result_data['message']) ? $result_data['message'] : __('支付请求失败', 'onepay');
                    $this->logger->error('API业务错误', array(
                        'code' => $result_data['code'],
                        'message' => $error_message
                    ));
                    
                    return array(
                        'success' => false,
                        'message' => $error_message,
                        'code' => $result_data['code'],
                        'debug_info' => 'Business error from API'
                    );
                }
            } else {
                // 无法识别的响应格式
                $this->logger->error('无法识别的API响应格式', array(
                    'result_data' => $result_data
                ));
                
                return array(
                    'success' => false,
                    'message' => __('API响应格式无法识别', 'onepay'),
                    'debug_info' => 'Unrecognized response format',
                    'parsed_result' => $result_data
                );
            }
            
        } catch (Exception $e) {
            $this->logger->error('API请求异常: ' . $e->getMessage(), array(
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'order_id' => $order->get_id()
            ));
            return array(
                'success' => false,
                'message' => __('API请求异常: ', 'onepay') . $e->getMessage(),
                'debug_info' => 'Exception: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Build payment request data
     * 
     * @param WC_Order $order The WooCommerce order
     * @param string $payment_method The payment method
     * @return array Request data
     */
    private function build_payment_request($order, $payment_method) {
        $merchant_order_no = $order->get_order_number() . '_' . time();
        $order_amount = intval($order->get_total() * 100); // Convert to smallest currency unit
        
        $callback_url = add_query_arg('wc-api', 'onepay_callback', home_url('/'));
        $notice_url = add_query_arg(
            array(
                'wc-api' => 'onepay_return',
                'order_id' => $order->get_id()
            ),
            home_url('/')
        );
        
        $content_data = array(
            'timeStamp' => strval(time() * 1000),
            'orderAmount' => strval($order_amount),
            'payType' => 'RUSSIA_PAY',
            'productDetail' => urlencode($this->get_order_description($order)),
            'callbackUrl' => $callback_url,
            'payModel' => $payment_method,
            'noticeUrl' => $notice_url,
            'merchantOrderNo' => $merchant_order_no,
            'merchantNo' => $this->gateway->merchant_no,
            'userIp' => $this->get_user_ip(),
            'userId' => strval($order->get_customer_id()),
            'customParam' => urlencode('order_' . $order->get_id())
        );
        
        $content_json = json_encode($content_data, JSON_UNESCAPED_SLASHES);
        
        $signature = OnePay_Signature::sign($content_json, $this->gateway->private_key);
        
        if (!$signature) {
            throw new Exception('Failed to generate signature');
        }
        
        return array(
            'merchantNo' => $this->gateway->merchant_no,
            'version' => '2.0',
            'content' => $content_json,
            'sign' => $signature
        );
    }
    
    /**
     * Send request to OnePay API
     * 
     * @param array $request_data Request data
     * @return array|false Response data or false on failure
     */
    private function send_request($request_data) {
        $url = $this->gateway->api_url;
        
        // 记录请求详情
        $this->logger->info('发送API请求', array(
            'url' => $url,
            'merchant_no' => $request_data['merchantNo'],
            'content_length' => strlen($request_data['content']),
            'has_signature' => !empty($request_data['sign'])
        ));
        
        // 记录请求内容（调试模式）
        if ($this->gateway->debug) {
            $content_data = json_decode($request_data['content'], true);
            $this->logger->debug('请求内容详情', array(
                'content_data' => $content_data,
                'signature' => substr($request_data['sign'], 0, 50) . '...'
            ));
        }
        
        $args = array(
            'method' => 'POST',
            'timeout' => 30,
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking' => true,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'WooCommerce-OnePay/' . ONEPAY_VERSION
            ),
            'body' => json_encode($request_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'sslverify' => false // 测试环境允许不验证SSL
        );
        
        // 发送请求
        $response = wp_remote_post($url, $args);
        
        // 处理网络错误
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $error_code = $response->get_error_code();
            
            $this->logger->error('网络请求失败', array(
                'url' => $url,
                'error_code' => $error_code,
                'error_message' => $error_message
            ));
            
            // 提供更友好的错误信息
            if (strpos($error_message, 'cURL error 7') !== false) {
                $this->logger->error('无法连接到API服务器，可能是网络问题或服务器离线');
            } elseif (strpos($error_message, 'cURL error 28') !== false) {
                $this->logger->error('API请求超时，服务器响应太慢');
            }
            
            return false;
        }
        
        // 获取响应信息
        $http_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_headers = wp_remote_retrieve_headers($response);
        
        // 记录原始响应
        $this->logger->info('收到API响应', array(
            'http_code' => $http_code,
            'body_length' => strlen($response_body),
            'content_type' => isset($response_headers['content-type']) ? $response_headers['content-type'] : 'unknown'
        ));
        
        // 如果响应体为空
        if (empty($response_body)) {
            $this->logger->error('API响应体为空', array(
                'http_code' => $http_code
            ));
            return false;
        }
        
        // 记录原始响应体（用于调试）
        if ($this->gateway->debug) {
            $this->logger->debug('原始响应体', array(
                'body' => substr($response_body, 0, 1000) // 只记录前1000字符
            ));
        }
        
        // 尝试解析JSON
        $response_data = json_decode($response_body, true);
        
        // 处理JSON解析错误
        if ($response_data === null && json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error('响应JSON解析失败', array(
                'json_error' => json_last_error_msg(),
                'response_body' => substr($response_body, 0, 500)
            ));
            
            // 尝试清理响应并重新解析
            $cleaned_body = trim($response_body);
            $cleaned_body = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $cleaned_body);
            $response_data = json_decode($cleaned_body, true);
            
            if ($response_data === null) {
                return false;
            }
        }
        
        // 记录解析后的响应
        $this->logger->log_api_request($url, $request_data, $response_data, $http_code);
        
        // 检查HTTP状态码
        if ($http_code !== 200) {
            $this->logger->error('HTTP状态码异常', array(
                'http_code' => $http_code,
                'response_data' => $response_data
            ));
            
            // 某些API可能在非200状态码下仍返回有效数据
            if ($http_code >= 400 && $http_code < 500) {
                // 客户端错误，可能包含错误信息
                if ($response_data && isset($response_data['result'])) {
                    return $response_data;
                }
            }
            
            return false;
        }
        
        // 验证响应格式
        if (!$response_data) {
            $this->logger->error('响应不是有效的JSON格式', array(
                'response_body' => substr($response_body, 0, 200)
            ));
            return false;
        }
        
        // 验证签名（如果配置了平台公钥）
        if (!empty($this->gateway->platform_public_key) && isset($response_data['result']) && isset($response_data['sign'])) {
            $signature_valid = OnePay_Signature::verify(
                $response_data['result'],
                $response_data['sign'],
                $this->gateway->platform_public_key
            );
            
            $this->logger->log_signature('verify', $signature_valid, strlen($response_data['result']));
            
            if (!$signature_valid) {
                $this->logger->error('响应签名验证失败');
                // 在测试模式下，可以选择忽略签名错误
                if (!$this->gateway->testmode) {
                    return false;
                }
                $this->logger->warning('测试模式下忽略签名验证失败');
            }
        }
        
        return $response_data;
    }
    
    /**
     * Get order description for payment
     * 
     * @param WC_Order $order The order
     * @return string Order description (max 256 chars after URL encoding)
     */
    private function get_order_description($order) {
        // 获取订单号
        $order_number = $order->get_order_number();
        
        // 获取商品列表
        $items = array();
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $product_name = $product ? $product->get_name() : $item->get_name();
            // 清理产品名称，移除特殊字符
            $product_name = preg_replace('/[^\p{L}\p{N}\s\-.,]/u', '', $product_name);
            // 限制单个产品名称长度
            if (mb_strlen($product_name) > 30) {
                $product_name = mb_substr($product_name, 0, 30) . '...';
            }
            $items[] = $product_name;
        }
        
        // 构建基本描述
        $base_description = 'Order #' . $order_number;
        
        // 计算可用于商品描述的字符数
        // URL编码后最大256字符，预留一些空间给编码扩展
        $max_total_length = 200; // 保守估计，给URL编码留空间
        $current_length = strlen($base_description);
        $available_length = $max_total_length - $current_length - 10; // 预留10个字符
        
        // 添加商品描述
        $items_description = '';
        if (!empty($items)) {
            // 尝试添加商品，但要确保不超过长度限制
            $temp_items = array();
            $temp_length = 0;
            
            foreach ($items as $item) {
                $item_with_separator = ($temp_length > 0 ? ', ' : ': ') . $item;
                $item_length = mb_strlen($item_with_separator);
                
                if ($temp_length + $item_length < $available_length) {
                    $temp_items[] = $item;
                    $temp_length += $item_length;
                } else {
                    // 如果加上这个商品会超长，就停止添加
                    if (count($temp_items) == 0 && $available_length > 20) {
                        // 至少添加第一个商品的部分内容
                        $truncated_item = mb_substr($item, 0, $available_length - 10) . '...';
                        $temp_items[] = $truncated_item;
                    }
                    break;
                }
            }
            
            if (!empty($temp_items)) {
                $items_description = ': ' . implode(', ', $temp_items);
                if (count($items) > count($temp_items)) {
                    $items_description .= '...';
                }
            }
        }
        
        $final_description = $base_description . $items_description;
        
        // 最终检查：确保URL编码后不超过256字符
        $encoded = urlencode($final_description);
        if (strlen($encoded) > 256) {
            // 如果还是太长，使用更简短的描述
            $final_description = 'Order #' . $order_number;
            $encoded = urlencode($final_description);
            
            // 如果订单号本身就很长，截断它
            if (strlen($encoded) > 256) {
                $final_description = 'Order';
            }
        }
        
        return $final_description;
    }
    
    /**
     * Get user IP address
     * 
     * @return string User IP address
     */
    private function get_user_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }
        return '127.0.0.1';
    }
    
    /**
     * Process refund request
     * 
     * @param WC_Order $order The order to refund
     * @param float $amount Refund amount
     * @param string $reason Refund reason
     * @return array Response array
     */
    public function process_refund($order, $amount, $reason = '') {
        // OnePay refund implementation would go here
        // This is a placeholder as the specific refund API endpoint wasn't provided
        
        return array(
            'success' => false,
            'message' => __('Refund functionality not implemented yet', 'onepay')
        );
    }
    
    /**
     * Query order status from OnePay API
     * 
     * @param string $order_no OnePay order number
     * @return array Response array
     */
    public function query_order_status($order_no) {
        // OnePay order query implementation would go here
        // This is a placeholder as the specific query API endpoint wasn't provided
        
        return array(
            'success' => false,
            'message' => __('Order query functionality not implemented yet', 'onepay')
        );
    }
    
    /**
     * Test API connection
     * 
     * @return array Response array with connection status
     */
    public function test_connection() {
        $results = array(
            'api_reachable' => false,
            'url_valid' => false,
            'ssl_enabled' => false,
            'details' => array()
        );
        
        try {
            // 验证URL格式
            $parsed_url = parse_url($this->gateway->api_url);
            if (!$parsed_url || !isset($parsed_url['scheme']) || !isset($parsed_url['host'])) {
                $results['details'][] = '❌ URL格式无效';
                return array(
                    'success' => false,
                    'message' => 'URL格式无效: ' . $this->gateway->api_url,
                    'results' => $results
                );
            }
            
            $results['url_valid'] = true;
            $results['details'][] = '✅ URL格式有效';
            
            // 检查SSL
            $results['ssl_enabled'] = ($parsed_url['scheme'] === 'https');
            if ($results['ssl_enabled']) {
                $results['details'][] = '✅ 使用HTTPS加密连接';
            } else {
                $results['details'][] = '⚠️ 未使用HTTPS（使用HTTP连接，安全性较低）';
            }
            
            // 构建测试数据
            $test_data = array(
                'merchantNo' => $this->gateway->merchant_no ?: 'TEST',
                'version' => '2.0',
                'content' => json_encode(array(
                    'test' => true,
                    'timestamp' => time()
                )),
                'sign' => 'test_signature'
            );
            
            // 设置请求参数，允许不安全的HTTP连接（仅用于测试）
            $args = array(
                'method' => 'POST',
                'timeout' => 15,
                'redirection' => 5,
                'httpversion' => '1.1',
                'blocking' => true,
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'User-Agent' => 'OnePay-WooCommerce/' . ONEPAY_VERSION
                ),
                'body' => json_encode($test_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'sslverify' => false // 允许自签名证书（仅测试环境）
            );
            
            $this->logger->info('测试API连接', array(
                'url' => $this->gateway->api_url,
                'test_data' => $test_data
            ));
            
            // 发送请求
            $response = wp_remote_post($this->gateway->api_url, $args);
            
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                $error_code = $response->get_error_code();
                
                $results['details'][] = '❌ 连接失败: ' . $error_message;
                
                // 提供更详细的错误诊断
                if (strpos($error_code, 'http_request_failed') !== false) {
                    if (strpos($error_message, 'cURL error 7') !== false) {
                        $results['details'][] = '💡 提示: 无法连接到服务器，请检查：';
                        $results['details'][] = '   - API地址是否正确';
                        $results['details'][] = '   - 服务器是否在线';
                        $results['details'][] = '   - 防火墙是否阻止了连接';
                    } elseif (strpos($error_message, 'cURL error 28') !== false) {
                        $results['details'][] = '💡 提示: 连接超时，服务器响应太慢';
                    } elseif (strpos($error_message, 'SSL') !== false) {
                        $results['details'][] = '💡 提示: SSL证书问题，测试环境可忽略';
                    }
                }
                
                return array(
                    'success' => false,
                    'message' => '连接失败: ' . $error_message,
                    'results' => $results
                );
            }
            
            // 获取响应信息
            $http_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            $response_headers = wp_remote_retrieve_headers($response);
            
            $this->logger->info('API响应', array(
                'http_code' => $http_code,
                'body' => substr($response_body, 0, 500) // 只记录前500字符
            ));
            
            // 判断连接是否成功
            if ($http_code > 0) {
                $results['api_reachable'] = true;
                $results['details'][] = '✅ API服务器可访问';
                $results['details'][] = '📡 HTTP状态码: ' . $http_code;
                
                // 分析响应
                if ($http_code === 200) {
                    $results['details'][] = '✅ 服务器响应正常';
                } elseif ($http_code === 400 || $http_code === 401) {
                    $results['details'][] = '⚠️ 服务器拒绝了测试请求（这是预期的）';
                } elseif ($http_code === 404) {
                    $results['details'][] = '❌ API端点不存在，请检查URL';
                } elseif ($http_code === 500) {
                    $results['details'][] = '❌ 服务器内部错误';
                } elseif ($http_code === 503) {
                    $results['details'][] = '❌ 服务暂时不可用';
                }
                
                // 尝试解析响应
                if (!empty($response_body)) {
                    $json_response = json_decode($response_body, true);
                    if ($json_response) {
                        $results['details'][] = '✅ 服务器返回了有效的JSON响应';
                    } else {
                        $results['details'][] = '⚠️ 响应不是JSON格式';
                    }
                }
            } else {
                $results['details'][] = '❌ 无法获取HTTP响应码';
            }
            
            // 综合判断
            $success = $results['api_reachable'] && $results['url_valid'];
            
            return array(
                'success' => $success,
                'message' => $success ? 'API连接测试成功' : 'API连接测试失败',
                'results' => $results,
                'http_code' => $http_code,
                'response_preview' => substr($response_body, 0, 200)
            );
            
        } catch (Exception $e) {
            $results['details'][] = '❌ 异常: ' . $e->getMessage();
            return array(
                'success' => false,
                'message' => '测试异常: ' . $e->getMessage(),
                'results' => $results
            );
        }
    }
    
    /**
     * 创建信用卡支付请求
     * 
     * @param WC_Order $order 订单对象
     * @param array $card_data 卡片数据
     * @return array 响应数组
     */
    public function create_card_payment_request($order, $card_data) {
        try {
            // 记录请求开始
            $this->logger->info('开始创建信用卡支付请求', array(
                'order_id' => $order->get_id(),
                'card_type' => $card_data['card_type'],
                'order_total' => $order->get_total(),
                'currency' => get_woocommerce_currency()
            ));
            
            $request_data = $this->build_card_payment_request($order, $card_data);
            $response = $this->send_request($request_data);
            
            // 详细记录响应
            $this->logger->info('信用卡支付API原始响应', array(
                'response_type' => gettype($response),
                'response_empty' => empty($response),
                'has_result' => isset($response['result']),
                'response_keys' => $response ? array_keys($response) : null,
                'response_data' => $response
            ));
            
            // 检查响应是否为空
            if (empty($response)) {
                $this->logger->error('信用卡支付API响应为空');
                return array(
                    'success' => false,
                    'message' => __('API服务器无响应，请检查网络连接', 'onepay'),
                    'debug_info' => 'Empty response from API'
                );
            }
            
            // 检查是否有result字段
            if (!isset($response['result'])) {
                $this->logger->error('信用卡支付API响应缺少result字段', array(
                    'response' => $response
                ));
                return array(
                    'success' => false,
                    'message' => __('API响应格式错误，缺少必要字段', 'onepay'),
                    'debug_info' => 'Missing result field in response',
                    'raw_response' => json_encode($response)
                );
            }
            
            // 尝试解析result
            $result_data = json_decode($response['result'], true);
            
            if ($result_data === null && json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('无法解析信用卡支付result字段', array(
                    'json_error' => json_last_error_msg(),
                    'result_field' => substr($response['result'], 0, 500)
                ));
                return array(
                    'success' => false,
                    'message' => __('API响应解析失败', 'onepay'),
                    'debug_info' => 'JSON parse error: ' . json_last_error_msg()
                );
            }
            
            // 检查响应代码
            if ($result_data && isset($result_data['code'])) {
                if ($result_data['code'] === '0000') {
                    // 成功响应
                    if (!isset($result_data['data'])) {
                        $this->logger->error('成功响应中缺少data字段');
                        return array(
                            'success' => false,
                            'message' => __('支付请求创建失败：响应数据不完整', 'onepay')
                        );
                    }
                    
                    $this->logger->info('信用卡支付请求创建成功', array(
                        'order_no' => isset($result_data['data']['orderNo']) ? $result_data['data']['orderNo'] : 'N/A',
                        'web_url' => isset($result_data['data']['webUrl']) ? 'URL provided' : 'No URL'
                    ));
                    
                    return array(
                        'success' => true,
                        'message' => $result_data['message'] ?? __('支付请求创建成功', 'onepay'),
                        'data' => $result_data['data']
                    );
                } else {
                    // 错误响应
                    $error_message = $result_data['message'] ?? __('API返回错误代码: ', 'onepay') . $result_data['code'];
                    
                    $this->logger->error('信用卡支付API返回错误', array(
                        'error_code' => $result_data['code'],
                        'error_message' => $error_message
                    ));
                    
                    return array(
                        'success' => false,
                        'message' => $error_message,
                        'error_code' => $result_data['code']
                    );
                }
            } else {
                $this->logger->error('信用卡支付API响应格式异常', array(
                    'result_data' => $result_data
                ));
                return array(
                    'success' => false,
                    'message' => __('API响应格式异常', 'onepay')
                );
            }
            
        } catch (Exception $e) {
            $this->logger->error('信用卡支付请求异常', array(
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ));
            
            return array(
                'success' => false,
                'message' => __('支付请求创建失败: ', 'onepay') . $e->getMessage()
            );
        }
    }
    
    /**
     * 构建信用卡支付请求数据
     * 
     * @param WC_Order $order 订单对象
     * @param array $card_data 卡片数据
     * @return array 请求数据
     */
    private function build_card_payment_request($order, $card_data) {
        $merchant_order_no = $order->get_order_number() . '_' . time();
        $order_amount = intval($order->get_total() * 100); // 转换为最小货币单位
        
        $callback_url = add_query_arg('wc-api', 'onepay_callback', home_url('/'));
        $notice_url = add_query_arg(
            array(
                'wc-api' => 'onepay_return',
                'order_id' => $order->get_id()
            ),
            home_url('/')
        );
        
        // 处理信用卡有效期格式 (MM/YY -> MMYY)
        $expiry = str_replace('/', '', $card_data['card_expiry']);
        
        $content_data = array(
            'timeStamp' => strval(time() * 1000),
            'orderAmount' => strval($order_amount),
            'payType' => 'RUSSIA_PAY',
            'productDetail' => urlencode($this->get_order_description($order)),
            'callbackUrl' => $callback_url,
            'payModel' => 'CARDPAYMENT',
            'noticeUrl' => $notice_url,
            'merchantOrderNo' => $merchant_order_no,
            'merchantNo' => $this->gateway->merchant_no,
            'userIp' => $this->get_user_ip(),
            'userId' => strval($order->get_customer_id()),
            'customParam' => urlencode('order_' . $order->get_id()),
            // 信用卡特定参数
            'cardNo' => str_replace(' ', '', $card_data['card_number']),
            'cvv' => $card_data['card_cvc'],
            'expiryDate' => $expiry,
            'cardType' => strtoupper($card_data['card_type'])
        );
        
        $content_json = json_encode($content_data, JSON_UNESCAPED_SLASHES);
        
        $signature = OnePay_Signature::sign($content_json, $this->gateway->private_key);
        
        if (!$signature) {
            throw new Exception('Failed to generate signature for card payment');
        }
        
        return array(
            'merchantNo' => $this->gateway->merchant_no,
            'version' => '2.0',
            'content' => $content_json,
            'sign' => $signature
        );
    }
    
}