<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * OnePay 国际卡收单处理类
 * 
 * 处理国际卡支付请求和3DS验证
 */
class OnePay_International_Card {
    
    private $gateway;
    private $logger;
    
    public function __construct() {
        $this->gateway = new WC_Gateway_OnePay();
        $this->logger = OnePay_Logger::get_instance();
    }
    
    /**
     * 创建国际卡支付请求
     * 
     * @param WC_Order $order WooCommerce订单
     * @param array $card_data 卡片信息
     * @return array 响应数组，包含成功状态和数据
     */
    public function create_international_card_payment($order, $card_data) {
        try {
            // 构建请求数据
            $request_data = $this->build_payment_request($order, $card_data);
            
            // 发送请求
            $response = $this->send_request($request_data);
            
            if ($response && isset($response['result'])) {
                $result_data = json_decode($response['result'], true);
                
                if ($result_data && isset($result_data['code']) && $result_data['code'] === '0000') {
                    // 保存订单元数据
                    $this->save_order_meta($order, $result_data['data']);
                    
                    return array(
                        'success' => true,
                        'data' => $result_data['data'],
                        'message' => $result_data['message']
                    );
                } else {
                    $error_message = isset($result_data['message']) ? $result_data['message'] : __('国际卡支付请求失败', 'onepay');
                    return array(
                        'success' => false,
                        'message' => $error_message,
                        'code' => isset($result_data['code']) ? $result_data['code'] : '9999'
                    );
                }
            } else {
                return array(
                    'success' => false,
                    'message' => __('无效的API响应', 'onepay')
                );
            }
            
        } catch (Exception $e) {
            $this->logger->error('国际卡支付请求异常: ' . $e->getMessage(), array(
                'exception' => $e->getMessage(),
                'order_id' => $order->get_id()
            ));
            return array(
                'success' => false,
                'message' => __('API请求失败', 'onepay')
            );
        }
    }
    
    /**
     * 构建国际卡支付请求数据
     * 
     * @param WC_Order $order WooCommerce订单
     * @param array $card_data 卡片信息
     * @return array 请求数据
     */
    private function build_payment_request($order, $card_data) {
        $merchant_order_no = $order->get_order_number() . '_' . time();
        $order_amount = intval($order->get_total() * 100); // 转换为最小货币单位（分）
        
        // 获取回调URL
        $callback_url = add_query_arg('wc-api', 'onepay_callback', home_url('/'));
        $notice_url = add_query_arg(
            array(
                'wc-api' => 'onepay_return',
                'order_id' => $order->get_id()
            ),
            home_url('/')
        );
        
        // 获取账单地址信息
        $billing_address = $this->get_billing_address($order);
        
        // 构建content数据
        $content_data = array(
            'timeStamp' => strval(time() * 1000),
            'merchantOrderNo' => $merchant_order_no,
            'payType' => 'INTERNATIONAL_CARD_PAY',
            'payModel' => 'CREDIT_CARD', // 贷记卡
            'currency' => get_woocommerce_currency(),
            'orderAmount' => strval($order_amount),
            'productDetail' => substr($this->get_order_description($order), 0, 256),
            
            // 卡片信息
            'cardNo' => $card_data['card_number'],
            'cardType' => $card_data['card_type'], // VISA, MASTERCARD, JCB, AMEX, DISCOVER
            'cardCcv' => $card_data['card_cvv'],
            'cardExpMonth' => $card_data['card_exp_month'],
            'cardExpYear' => $card_data['card_exp_year'],
            
            // 持卡人信息
            'firstName' => $billing_address['first_name'],
            'lastName' => $billing_address['last_name'],
            'country' => $billing_address['country'], // 3位ISO国家代码
            'city' => $billing_address['city'],
            'address' => $billing_address['address'],
            'phone' => $billing_address['phone'],
            'postcode' => $billing_address['postcode'],
            
            // 回调地址
            'callbackUrl' => substr($callback_url, 0, 256),
            'noticeUrl' => substr($notice_url, 0, 256),
            
            // 可选参数
            'userIp' => $this->get_user_ip(),
            'userAgent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 256),
            'customParam' => 'order_' . $order->get_id()
        );
        
        // 美国和加拿大必须传州信息
        if (in_array($billing_address['country'], array('USA', 'CAN'))) {
            $content_data['state'] = $billing_address['state'];
        }
        
        // 产品编码（如果有）
        if (!empty($card_data['product_code'])) {
            $content_data['productCode'] = $card_data['product_code'];
        }
        
        // 转换为JSON字符串
        $content_json = json_encode($content_data, JSON_UNESCAPED_SLASHES);
        
        // 生成签名
        $signature = OnePay_Signature::sign($content_json, $this->gateway->private_key);
        
        if (!$signature) {
            throw new Exception('生成签名失败');
        }
        
        return array(
            'merchantNo' => $this->gateway->merchant_no,
            'version' => '2.0',
            'content' => $content_json,
            'sign' => $signature
        );
    }
    
    /**
     * 发送请求到OnePay API
     * 
     * @param array $request_data 请求数据
     * @return array|false 响应数据或失败时返回false
     */
    private function send_request($request_data) {
        // 使用国际卡专用的API端点
        $url = $this->gateway->api_url;
        
        // 记录请求日志
        $this->logger->info('发送国际卡支付请求', array(
            'url' => $url,
            'merchant_order_no' => json_decode($request_data['content'], true)['merchantOrderNo']
        ));
        
        $args = array(
            'method' => 'POST',
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'WooCommerce-OnePay/' . ONEPAY_VERSION
            ),
            'body' => json_encode($request_data, JSON_UNESCAPED_SLASHES)
        );
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            $this->logger->error('API请求错误: ' . $response->get_error_message(), array(
                'url' => $url,
                'error_code' => $response->get_error_code()
            ));
            return false;
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        
        // 记录API请求详细信息
        $this->logger->log_api_request($url, $request_data, $response_data ?: $response_body, $http_code);
        
        if ($http_code !== 200) {
            return false;
        }
        
        if (!$response_data) {
            $this->logger->error('无效的JSON响应', array('response_body' => $response_body));
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
                return false;
            }
        }
        
        return $response_data;
    }
    
    /**
     * 获取账单地址信息
     * 
     * @param WC_Order $order 订单
     * @return array 格式化的地址信息
     */
    private function get_billing_address($order) {
        // 获取国家ISO代码（3位）
        $country_code = $order->get_billing_country();
        $country_iso3 = $this->get_iso3_country_code($country_code);
        
        return array(
            'first_name' => $order->get_billing_first_name(),
            'last_name' => $order->get_billing_last_name(),
            'country' => $country_iso3,
            'state' => $order->get_billing_state(),
            'city' => $order->get_billing_city(),
            'address' => $order->get_billing_address_1() . ' ' . $order->get_billing_address_2(),
            'phone' => $order->get_billing_phone(),
            'postcode' => $order->get_billing_postcode()
        );
    }
    
    /**
     * 将2位ISO国家代码转换为3位
     * 
     * @param string $iso2 2位ISO代码
     * @return string 3位ISO代码
     */
    private function get_iso3_country_code($iso2) {
        // 常用国家代码映射
        $iso_map = array(
            'US' => 'USA',
            'GB' => 'GBR',
            'CN' => 'CHN',
            'JP' => 'JPN',
            'DE' => 'DEU',
            'FR' => 'FRA',
            'IT' => 'ITA',
            'ES' => 'ESP',
            'CA' => 'CAN',
            'AU' => 'AUS',
            'NZ' => 'NZL',
            'SG' => 'SGP',
            'HK' => 'HKG',
            'MY' => 'MYS',
            'TH' => 'THA',
            'ID' => 'IDN',
            'PH' => 'PHL',
            'VN' => 'VNM',
            'IN' => 'IND',
            'RU' => 'RUS',
            'BR' => 'BRA',
            'MX' => 'MEX',
            'AR' => 'ARG'
        );
        
        return isset($iso_map[$iso2]) ? $iso_map[$iso2] : $iso2;
    }
    
    /**
     * 获取订单描述
     * 
     * @param WC_Order $order 订单
     * @return string 订单描述
     */
    private function get_order_description($order) {
        $items = array();
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $items[] = $product ? $product->get_name() : $item->get_name();
        }
        
        $description = implode(', ', array_slice($items, 0, 3));
        if (count($items) > 3) {
            $description .= '...';
        }
        
        return sprintf(__('订单 #%s: %s', 'onepay'), $order->get_order_number(), $description);
    }
    
    /**
     * 获取用户IP地址
     * 
     * @return string 用户IP地址
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
     * 保存订单元数据
     * 
     * @param WC_Order $order 订单
     * @param array $payment_data 支付数据
     */
    private function save_order_meta($order, $payment_data) {
        // 保存OnePay订单号
        $order->update_meta_data('_onepay_order_no', $payment_data['orderNo']);
        $order->update_meta_data('_onepay_payment_type', 'INTERNATIONAL_CARD');
        
        // 如果有3DS验证URL，保存它
        if (!empty($payment_data['webUrl'])) {
            $order->update_meta_data('_onepay_3ds_url', $payment_data['webUrl']);
        }
        
        // 保存其他相关信息
        $order->update_meta_data('_onepay_order_status', $payment_data['orderStatus']);
        $order->update_meta_data('_onepay_currency', $payment_data['currency']);
        $order->update_meta_data('_onepay_order_amount', $payment_data['orderAmount']);
        
        if (isset($payment_data['orderFee'])) {
            $order->update_meta_data('_onepay_order_fee', $payment_data['orderFee']);
        }
        
        $order->save();
    }
    
    /**
     * 验证卡号格式
     * 
     * @param string $card_number 卡号
     * @return bool 是否有效
     */
    public static function validate_card_number($card_number) {
        // 移除空格和破折号
        $card_number = str_replace(array(' ', '-'), '', $card_number);
        
        // 检查是否为数字
        if (!ctype_digit($card_number)) {
            return false;
        }
        
        // 检查长度（13-19位）
        $length = strlen($card_number);
        if ($length < 13 || $length > 19) {
            return false;
        }
        
        // Luhn算法验证
        $sum = 0;
        $alt = false;
        
        for ($i = $length - 1; $i >= 0; $i--) {
            $n = intval($card_number[$i]);
            if ($alt) {
                $n *= 2;
                if ($n > 9) {
                    $n -= 9;
                }
            }
            $sum += $n;
            $alt = !$alt;
        }
        
        return ($sum % 10 == 0);
    }
    
    /**
     * 根据卡号检测卡类型
     * 
     * @param string $card_number 卡号
     * @return string|false 卡类型或false
     */
    public static function detect_card_type($card_number) {
        // 移除空格和破折号
        $card_number = str_replace(array(' ', '-'), '', $card_number);
        
        // VISA: 以4开头
        if (preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/', $card_number)) {
            return 'VISA';
        }
        
        // MasterCard: 51-55或2221-2720开头
        if (preg_match('/^5[1-5][0-9]{14}$/', $card_number) || 
            preg_match('/^2(?:22[1-9]|2[3-9][0-9]|[3-6][0-9]{2}|7[0-1][0-9]|720)[0-9]{12}$/', $card_number)) {
            return 'MASTERCARD';
        }
        
        // American Express: 34或37开头，15位
        if (preg_match('/^3[47][0-9]{13}$/', $card_number)) {
            return 'AMEX';
        }
        
        // Discover: 6011, 622126-622925, 644-649, 65开头
        if (preg_match('/^6(?:011|5[0-9]{2})[0-9]{12}$/', $card_number) ||
            preg_match('/^622(?:12[6-9]|1[3-9][0-9]|[2-8][0-9]{2}|9[0-1][0-9]|92[0-5])[0-9]{10}$/', $card_number) ||
            preg_match('/^64[4-9][0-9]{13}$/', $card_number)) {
            return 'DISCOVER';
        }
        
        // JCB: 3528-3589开头
        if (preg_match('/^35(?:2[89]|[3-8][0-9])[0-9]{12}$/', $card_number)) {
            return 'JCB';
        }
        
        return false;
    }
}