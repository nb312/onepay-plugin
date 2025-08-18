<?php
/**
 * OnePay支付网关增强版 - 带详细调试日志
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Gateway_OnePay_Enhanced extends WC_Payment_Gateway {
    
    private $debug_logger;
    private $api_handler;
    private $callback_handler;
    
    public function __construct() {
        $this->id                 = 'onepay_enhanced';
        $this->icon               = '';
        $this->has_fields         = true;
        $this->method_title       = 'OnePay增强版';
        $this->method_description = 'OnePay支付网关，带完整调试日志功能';
        
        $this->supports = array(
            'products',
            'refunds'
        );
        
        // 初始化调试日志记录器
        require_once dirname(__FILE__) . '/class-onepay-debug-logger.php';
        $this->debug_logger = OnePay_Debug_Logger::get_instance();
        
        // 加载设置
        $this->init_form_fields();
        $this->init_settings();
        
        // 获取设置值
        $this->title        = $this->get_option('title');
        $this->description  = $this->get_option('description');
        $this->enabled      = $this->get_option('enabled');
        $this->testmode     = 'yes' === $this->get_option('testmode');
        $this->debug        = 'yes' === $this->get_option('debug');
        $this->merchant_no  = $this->get_option('merchant_no');
        $this->private_key  = $this->get_option('private_key');
        $this->public_key   = $this->get_option('platform_public_key');
        $this->api_url      = $this->testmode ? 
                              $this->get_option('test_api_url') : 
                              $this->get_option('live_api_url');
        
        // 保存设置的action
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        
        // 注册回调处理
        add_action('woocommerce_api_wc_gateway_' . $this->id, array($this, 'handle_callback'));
    }
    
    /**
     * 初始化设置表单字段
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => '启用/禁用',
                'type'    => 'checkbox',
                'label'   => '启用OnePay支付',
                'default' => 'yes'
            ),
            'title' => array(
                'title'       => '标题',
                'type'        => 'text',
                'description' => '客户在结账时看到的支付方式标题',
                'default'     => 'OnePay在线支付',
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => '描述',
                'type'        => 'textarea',
                'description' => '客户选择支付方式时看到的描述',
                'default'     => '通过OnePay安全支付，支持银行卡和快速支付',
                'desc_tip'    => true,
            ),
            'testmode' => array(
                'title'       => '测试模式',
                'label'       => '启用测试模式',
                'type'        => 'checkbox',
                'description' => '使用测试API进行支付测试',
                'default'     => 'yes',
                'desc_tip'    => true,
            ),
            'debug' => array(
                'title'       => '调试模式',
                'label'       => '启用详细日志记录',
                'type'        => 'checkbox',
                'description' => '记录所有支付相关信息用于调试（包括金额、IP、用户名、订单号、API请求/响应等）',
                'default'     => 'yes',
                'desc_tip'    => true,
            ),
            'merchant_no' => array(
                'title'       => '商户号',
                'type'        => 'text',
                'description' => '您的OnePay商户号',
                'default'     => 'TEST_MERCHANT',
                'desc_tip'    => true,
            ),
            'private_key' => array(
                'title'       => '商户私钥',
                'type'        => 'textarea',
                'description' => 'RSA私钥（PEM格式）',
                'default'     => '',
                'desc_tip'    => true,
            ),
            'platform_public_key' => array(
                'title'       => '平台公钥',
                'type'        => 'textarea',
                'description' => 'OnePay平台公钥',
                'default'     => '',
                'desc_tip'    => true,
            ),
            'test_api_url' => array(
                'title'       => '测试API地址',
                'type'        => 'text',
                'description' => '测试环境API端点',
                'default'     => 'https://test-api.onepay.com/v2/card/payment',
                'desc_tip'    => true,
            ),
            'live_api_url' => array(
                'title'       => '生产API地址',
                'type'        => 'text',
                'description' => '生产环境API端点',
                'default'     => 'https://gateway.lapay.cc/nh-gateway/v2/card/payment',
                'desc_tip'    => true,
            )
        );
    }
    
    /**
     * 检查网关是否可用
     */
    public function is_available() {
        if ('yes' !== $this->enabled) {
            return false;
        }
        
        if (is_admin()) {
            return true;
        }
        
        // 测试模式下始终可用
        if ($this->testmode) {
            return true;
        }
        
        // 生产模式需要商户号
        if (empty($this->merchant_no)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * 支付字段
     */
    public function payment_fields() {
        if ($this->description) {
            if ($this->testmode) {
                $this->description .= ' （测试模式已启用，不会产生实际扣款）';
            }
            echo wpautop(wp_kses_post($this->description));
        }
        
        ?>
        <fieldset id="wc-<?php echo esc_attr($this->id); ?>-form" class="wc-payment-form">
            <p class="form-row form-row-wide">
                <label for="<?php echo esc_attr($this->id); ?>_payment_method">
                    支付方式 <span class="required">*</span>
                </label>
                <select name="<?php echo esc_attr($this->id); ?>_payment_method" 
                        id="<?php echo esc_attr($this->id); ?>_payment_method">
                    <option value="FPS">快速支付系统 (FPS/SBP)</option>
                    <option value="CARDPAYMENT">银行卡支付</option>
                </select>
            </p>
            <div class="clear"></div>
        </fieldset>
        <?php
    }
    
    /**
     * 验证支付字段
     */
    public function validate_fields() {
        $payment_method = isset($_POST[$this->id . '_payment_method']) ? 
                         sanitize_text_field($_POST[$this->id . '_payment_method']) : '';
        
        if (empty($payment_method)) {
            wc_add_notice('请选择支付方式', 'error');
            return false;
        }
        
        return true;
    }
    
    /**
     * 处理支付
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        
        // 获取支付方式
        $payment_method = isset($_POST[$this->id . '_payment_method']) ? 
                         sanitize_text_field($_POST[$this->id . '_payment_method']) : 'FPS';
        
        // 记录支付请求日志
        if ($this->debug) {
            $this->debug_logger->log_payment_request($order, array(
                'payment_method' => $payment_method,
                'merchant_no' => $this->merchant_no,
                'test_mode' => $this->testmode,
                'api_url' => $this->api_url
            ));
        }
        
        // 保存支付方式到订单
        $order->update_meta_data('_onepay_payment_method', $payment_method);
        $order->save();
        
        // 测试模式处理
        if ($this->testmode) {
            // 记录测试模式支付成功
            if ($this->debug) {
                $this->debug_logger->log_api_response(null, array(
                    'code' => 'SUCCESS',
                    'message' => '测试模式支付成功',
                    'order_id' => $order_id,
                    'payment_method' => $payment_method
                ), 0.001);
            }
            
            $order->payment_complete();
            $order->add_order_note(sprintf('OnePay测试支付成功 (方式: %s)', $payment_method));
            
            // 减少库存
            wc_reduce_stock_levels($order_id);
            
            // 清空购物车
            WC()->cart->empty_cart();
            
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            );
        }
        
        // 生产模式：调用API
        try {
            $start_time = microtime(true);
            
            // 构建API请求
            $request_data = $this->build_api_request($order, $payment_method);
            
            // 记录API请求
            $log_id = null;
            if ($this->debug) {
                $log_id = $this->debug_logger->log_api_request($this->api_url, $request_data, $order_id);
            }
            
            // 这里应该调用实际的API
            // $response = $this->call_api($request_data);
            
            // 模拟响应（实际应该是API响应）
            $response = array(
                'code' => '0000',
                'message' => '请求成功',
                'data' => array(
                    'orderNo' => 'ONEPAY' . $order_id . time(),
                    'payUrl' => $this->get_return_url($order)
                )
            );
            
            $execution_time = microtime(true) - $start_time;
            
            // 记录API响应
            if ($this->debug && $log_id) {
                $this->debug_logger->log_api_response($log_id, $response, $execution_time);
            }
            
            if ($response['code'] === '0000') {
                // 标记订单为待支付
                $order->update_status('pending', '等待OnePay支付确认');
                
                // 减少库存
                wc_reduce_stock_levels($order_id);
                
                // 清空购物车
                WC()->cart->empty_cart();
                
                // 跳转到支付页面
                return array(
                    'result' => 'success',
                    'redirect' => $response['data']['payUrl'] ?? $this->get_return_url($order)
                );
            } else {
                throw new Exception($response['message'] ?? '支付请求失败');
            }
            
        } catch (Exception $e) {
            // 记录错误
            if ($this->debug) {
                $this->debug_logger->log_error($e->getMessage(), array(
                    'order_id' => $order_id,
                    'payment_method' => $payment_method,
                    'trace' => $e->getTraceAsString()
                ));
            }
            
            wc_add_notice('支付失败: ' . $e->getMessage(), 'error');
            return array(
                'result' => 'fail',
                'redirect' => ''
            );
        }
    }
    
    /**
     * 构建API请求
     */
    private function build_api_request($order, $payment_method) {
        $merchant_order_no = $order->get_order_number() . '_' . time();
        
        $content = array(
            'merchantOrderNo' => $merchant_order_no,
            'orderAmount' => intval($order->get_total() * 100), // 转换为分
            'payCurrency' => $order->get_currency(),
            'paymentMethod' => $payment_method,
            'notifyUrl' => WC()->api_request_url('wc_gateway_' . $this->id),
            'returnUrl' => $this->get_return_url($order),
            'orderTime' => current_time('YmdHis'),
            'expireTime' => date('YmdHis', strtotime('+30 minutes')),
            'customerInfo' => array(
                'customerNo' => $order->get_user_id() ?: 'GUEST_' . $order->get_id(),
                'customerName' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'customerEmail' => $order->get_billing_email(),
                'customerPhone' => $order->get_billing_phone(),
                'customerIp' => $_SERVER['REMOTE_ADDR'] ?? ''
            ),
            'productInfo' => array(
                'productName' => '订单 #' . $order->get_order_number(),
                'productDesc' => $this->get_order_description($order)
            )
        );
        
        // 生成签名
        $content_json = json_encode($content, JSON_UNESCAPED_UNICODE);
        $sign = $this->generate_signature($content_json);
        
        return array(
            'merchantNo' => $this->merchant_no,
            'version' => '1.0',
            'content' => $content_json,
            'sign' => $sign
        );
    }
    
    /**
     * 生成签名
     */
    private function generate_signature($data) {
        if (empty($this->private_key)) {
            return '';
        }
        
        // MD5withRSA签名
        $md5_hash = md5($data);
        $private_key = openssl_pkey_get_private($this->private_key);
        
        if (!$private_key) {
            return '';
        }
        
        openssl_sign($md5_hash, $signature, $private_key, OPENSSL_ALGO_SHA1);
        openssl_pkey_free($private_key);
        
        return base64_encode($signature);
    }
    
    /**
     * 获取订单描述
     */
    private function get_order_description($order) {
        $items = array();
        foreach ($order->get_items() as $item) {
            $items[] = $item->get_name() . ' x ' . $item->get_quantity();
        }
        return implode(', ', $items);
    }
    
    /**
     * 处理回调
     */
    public function handle_callback() {
        // 获取回调数据
        $raw_data = file_get_contents('php://input');
        
        // 记录回调日志
        if ($this->debug) {
            $this->debug_logger->log_callback(json_decode($raw_data, true));
        }
        
        try {
            // 解析回调数据
            $callback_data = json_decode($raw_data, true);
            
            if (!$callback_data) {
                throw new Exception('无效的回调数据');
            }
            
            // 验证签名
            if (!$this->verify_signature($callback_data)) {
                throw new Exception('签名验证失败');
            }
            
            // 处理订单状态
            $result = json_decode($callback_data['result'], true);
            $order_no = $result['data']['merchantOrderNo'] ?? '';
            
            // 从订单号中提取WooCommerce订单ID
            $order_id = intval(explode('_', $order_no)[0]);
            $order = wc_get_order($order_id);
            
            if (!$order) {
                throw new Exception('订单不存在: ' . $order_id);
            }
            
            // 更新订单状态
            if ($result['data']['orderStatus'] === 'SUCCESS') {
                $order->payment_complete($result['data']['orderNo']);
                $order->add_order_note('OnePay支付成功确认');
            } elseif ($result['data']['orderStatus'] === 'FAIL') {
                $order->update_status('failed', 'OnePay支付失败');
            }
            
            // 记录成功处理
            if ($this->debug) {
                $this->debug_logger->log_callback(array(
                    'status' => 'processed',
                    'order_id' => $order_id,
                    'order_status' => $result['data']['orderStatus']
                ), $order_id);
            }
            
            // 返回成功响应
            echo 'SUCCESS';
            
        } catch (Exception $e) {
            // 记录错误
            if ($this->debug) {
                $this->debug_logger->log_error('回调处理失败: ' . $e->getMessage(), array(
                    'raw_data' => $raw_data
                ));
            }
            
            echo 'ERROR: ' . $e->getMessage();
        }
        
        exit;
    }
    
    /**
     * 验证签名
     */
    private function verify_signature($data) {
        if (empty($this->public_key) || empty($data['sign'])) {
            return false;
        }
        
        $result_json = $data['result'] ?? '';
        $sign = $data['sign'] ?? '';
        
        // MD5withRSA验证
        $md5_hash = md5($result_json);
        $public_key = openssl_pkey_get_public($this->public_key);
        
        if (!$public_key) {
            return false;
        }
        
        $verified = openssl_verify($md5_hash, base64_decode($sign), $public_key, OPENSSL_ALGO_SHA1);
        openssl_pkey_free($public_key);
        
        return $verified === 1;
    }
}