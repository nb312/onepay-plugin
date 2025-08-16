<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * OnePay Russian Card Payment Gateway
 * 俄罗斯银行卡支付网关
 */
class WC_Gateway_OnePay_Russian_Card extends WC_Payment_Gateway {
    
    protected $parent_gateway;
    
    public function __construct() {
        $this->id                 = 'onepay_russian_card';
        $this->icon               = ONEPAY_PLUGIN_URL . 'assets/images/russian-card-colored.svg';
        $this->has_fields         = false;
        $this->method_title       = __('OnePay 俄罗斯卡', 'onepay');
        $this->method_description = __('俄罗斯银行卡支付', 'onepay');
        
        // 从父网关获取设置
        $this->parent_gateway = new WC_Gateway_OnePay();
        
        $this->init_form_fields();
        $this->init_settings();
        
        $this->title       = $this->get_option('title', __('俄罗斯银行卡', 'onepay'));
        $this->description = $this->get_option('description', __('使用俄罗斯银行卡支付', 'onepay'));
        $this->enabled     = $this->get_option('enabled', 'yes');
        
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }
    
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('启用/禁用', 'onepay'),
                'type'    => 'checkbox',
                'label'   => __('启用俄罗斯银行卡支付', 'onepay'),
                'default' => 'yes'
            ),
            'title' => array(
                'title'       => __('标题', 'onepay'),
                'type'        => 'text',
                'description' => __('客户在结账时看到的标题', 'onepay'),
                'default'     => __('俄罗斯银行卡', 'onepay'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('描述', 'onepay'),
                'type'        => 'textarea',
                'description' => __('支付方式描述', 'onepay'),
                'default'     => __('使用俄罗斯银行卡支付', 'onepay'),
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
            'supported_banks' => array(
                'title'       => __('支持的银行', 'onepay'),
                'type'        => 'multiselect',
                'description' => __('选择支持的银行（留空表示支持所有）', 'onepay'),
                'options'     => array(
                    'sberbank' => __('Sberbank 俄罗斯储蓄银行', 'onepay'),
                    'vtb'      => __('VTB 俄罗斯外贸银行', 'onepay'),
                    'gazprom'  => __('Gazprombank 俄罗斯天然气工业银行', 'onepay'),
                    'alfa'     => __('Alfa-Bank 阿尔法银行', 'onepay'),
                    'tinkoff'  => __('Tinkoff 腾科夫银行', 'onepay'),
                ),
                'default'     => array(),
                'class'       => 'wc-enhanced-select',
                'css'         => 'width: 400px;',
                'custom_attributes' => array(
                    'data-placeholder' => __('选择银行', 'onepay')
                )
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
        
        return true;
    }
    
    public function get_icon() {
        $icon_style = $this->get_option('icon_style', 'colored');
        
        if ($icon_style === 'none') {
            return '';
        }
        
        $supported_banks = $this->get_option('supported_banks', array());
        $icons = array();
        
        // 主图标
        $icons[] = '<img src="' . ONEPAY_PLUGIN_URL . 'assets/images/russian-card-' . $icon_style . '.svg" 
                      alt="Russian Card" 
                      class="onepay-payment-icon onepay-russian-card-icon">';
        
        // 如果配置了特定银行，显示银行logo
        if (!empty($supported_banks) && is_array($supported_banks)) {
            foreach ($supported_banks as $bank) {
                if (file_exists(ONEPAY_PLUGIN_PATH . 'assets/images/banks/' . $bank . '.svg')) {
                    $icons[] = '<img src="' . ONEPAY_PLUGIN_URL . 'assets/images/banks/' . $bank . '.svg" 
                                  alt="' . $bank . '" 
                                  class="onepay-bank-icon">';
                }
            }
        }
        
        $icon_html = '<span class="onepay-payment-icons">' . implode('', $icons) . '</span>';
        
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
        $order->update_meta_data('_onepay_payment_method', 'CARDPAYMENT');
        $order->update_meta_data('_payment_method_title', $this->title);
        $order->save();
        
        // 使用父网关的API处理
        $api_handler = new OnePay_API();
        $response = $api_handler->create_payment_request($order, 'CARDPAYMENT');
        
        if ($response['success']) {
            $order->update_status('pending', __('等待银行卡支付确认', 'onepay'));
            $order->update_meta_data('_onepay_order_no', $response['data']['orderNo']);
            $order->save();
            
            return array(
                'result'   => 'success',
                'redirect' => $response['data']['webUrl']
            );
        } else {
            wc_add_notice($response['message'], 'error');
            return array(
                'result' => 'fail'
            );
        }
    }
}