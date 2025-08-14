<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * OnePay FPS Payment Gateway
 * 俄罗斯快速支付系统网关
 */
class WC_Gateway_OnePay_FPS extends WC_Payment_Gateway {
    
    protected $parent_gateway;
    
    public function __construct() {
        $this->id                 = 'onepay_fps';
        $this->icon               = ONEPAY_PLUGIN_URL . 'assets/images/fps-logo.svg';
        $this->has_fields         = false;
        $this->method_title       = __('OnePay FPS', 'onepay');
        $this->method_description = __('俄罗斯快速支付系统', 'onepay');
        
        // 从父网关获取设置
        $this->parent_gateway = new WC_Gateway_OnePay();
        
        $this->init_form_fields();
        $this->init_settings();
        
        $this->title       = $this->get_option('title', __('快速支付 FPS', 'onepay'));
        $this->description = $this->get_option('description', __('通过俄罗斯快速支付系统安全支付', 'onepay'));
        $this->enabled     = $this->get_option('enabled', 'yes');
        
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }
    
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('启用/禁用', 'onepay'),
                'type'    => 'checkbox',
                'label'   => __('启用FPS快速支付', 'onepay'),
                'default' => 'yes'
            ),
            'title' => array(
                'title'       => __('标题', 'onepay'),
                'type'        => 'text',
                'description' => __('客户在结账时看到的标题', 'onepay'),
                'default'     => __('快速支付 FPS', 'onepay'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('描述', 'onepay'),
                'type'        => 'textarea',
                'description' => __('支付方式描述', 'onepay'),
                'default'     => __('通过俄罗斯快速支付系统安全支付', 'onepay'),
                'desc_tip'    => true,
            ),
            'icon_style' => array(
                'title'       => __('图标样式', 'onepay'),
                'type'        => 'select',
                'description' => __('选择图标显示样式', 'onepay'),
                'default'     => 'colored',
                'options'     => array(
                    'colored'    => __('彩色图标', 'onepay'),
                    'monochrome' => __('单色图标', 'onepay'),
                    'none'       => __('不显示图标', 'onepay')
                )
            ),
            'min_amount' => array(
                'title'       => __('最小金额', 'onepay'),
                'type'        => 'text',
                'description' => __('最小支付金额（留空表示无限制）', 'onepay'),
                'default'     => '1',
                'desc_tip'    => true,
            ),
            'max_amount' => array(
                'title'       => __('最大金额', 'onepay'),
                'type'        => 'text',
                'description' => __('最大支付金额（留空表示无限制）', 'onepay'),
                'default'     => '',
                'desc_tip'    => true,
            )
        );
    }
    
    public function is_available() {
        if ('yes' !== $this->enabled) {
            return false;
        }
        
        // 检查父网关是否配置
        if (!$this->parent_gateway->is_configured()) {
            return false;
        }
        
        // 检查金额限制
        if (WC()->cart) {
            $total = WC()->cart->get_total('edit');
            
            $min = $this->get_option('min_amount');
            if ($min && $total < $min) {
                return false;
            }
            
            $max = $this->get_option('max_amount');
            if ($max && $total > $max) {
                return false;
            }
        }
        
        return true;
    }
    
    public function get_icon() {
        $icon_style = $this->get_option('icon_style', 'colored');
        
        if ($icon_style === 'none') {
            return '';
        }
        
        $icon_html = '<img src="' . ONEPAY_PLUGIN_URL . 'assets/images/fps-' . $icon_style . '.svg" 
                          alt="FPS" 
                          class="onepay-payment-icon onepay-fps-icon" 
                          style="max-height: 30px; vertical-align: middle; margin-left: 5px;" />';
        
        return apply_filters('woocommerce_gateway_icon', $icon_html, $this->id);
    }
    
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return array(
                'result'   => 'fail',
                'messages' => __('订单未找到', 'onepay')
            );
        }
        
        // 保存支付方式
        $order->update_meta_data('_onepay_payment_method', 'FPS');
        $order->update_meta_data('_payment_method_title', $this->title);
        $order->save();
        
        // 使用父网关的API处理
        $api_handler = new OnePay_API();
        $response = $api_handler->create_payment_request($order, 'FPS');
        
        if ($response['success']) {
            // 检查是否有webUrl
            if (empty($response['data']['webUrl'])) {
                // 记录错误
                error_log('OnePay FPS: 响应成功但缺少支付URL');
                error_log('OnePay FPS Response Data: ' . json_encode($response['data']));
                
                wc_add_notice(__('支付链接生成失败，请稍后重试或联系客服', 'onepay'), 'error');
                return array(
                    'result' => 'fail'
                );
            }
            
            $order->update_status('pending', __('等待FPS支付确认', 'onepay'));
            $order->update_meta_data('_onepay_order_no', $response['data']['orderNo']);
            $order->save();
            
            return array(
                'result'   => 'success',
                'redirect' => $response['data']['webUrl']
            );
        } else {
            // 提供更详细的错误信息
            $error_message = $response['message'];
            
            // 如果是调试模式，添加额外信息
            if ($this->parent_gateway->debug) {
                if (isset($response['debug_info'])) {
                    $error_message .= ' (' . $response['debug_info'] . ')';
                }
                
                // 记录到错误日志
                error_log('OnePay FPS Payment Error: ' . $error_message);
                if (isset($response['raw_response'])) {
                    error_log('OnePay FPS Raw Response: ' . $response['raw_response']);
                }
            }
            
            // 根据错误类型提供不同的用户提示
            if (strpos($error_message, '无响应') !== false || strpos($error_message, 'Empty response') !== false) {
                wc_add_notice(__('支付服务暂时不可用，请稍后重试', 'onepay'), 'error');
            } elseif (strpos($error_message, '格式错误') !== false) {
                wc_add_notice(__('支付请求处理失败，请联系网站管理员', 'onepay'), 'error');
            } else {
                wc_add_notice($error_message, 'error');
            }
            
            return array(
                'result' => 'fail'
            );
        }
    }
}